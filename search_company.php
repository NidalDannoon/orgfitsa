<?php
/**
 * ملف البحث عن شركة وعرض بياناتها واستبياناتها
 * search_company.php
 * يستخدم في admin.php لعرض معلومات الشركة والاستبيانات المرتبطة بها
 */

// تضمين ملف الإعدادات
require_once 'config.php';

// التحقق من تسجيل الدخول وصلاحية المشرف
startSession();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// التحقق من وجود company_id
$companyId = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;

if ($companyId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid company ID']);
    exit;
}

// الحصول على اللغة المفضلة
$lang = getPreferredLanguage();

try {
    // الاتصال بقاعدة البيانات
    $pdo = getDBConnection();
    
    // ===== 1. جلب بيانات الشركة =====
    $stmt = $pdo->prepare("
        SELECT 
            id,
            name,
            address,
            industry,
            phone,
            created_at,
            (SELECT COUNT(*) FROM polls WHERE company_id = companies.id) as total_polls,
            (SELECT COUNT(*) FROM polls WHERE company_id = companies.id AND type = 'regular') as regular_polls,
            (SELECT COUNT(*) FROM polls WHERE company_id = companies.id AND type = 'admin') as admin_polls,
            (SELECT SUM(participant_count) FROM polls WHERE company_id = companies.id) as total_participants,
            (SELECT COUNT(DISTINCT user_id) FROM responses r JOIN polls p ON r.poll_id = p.id WHERE p.company_id = companies.id) as total_responses
        FROM companies
        WHERE id = ?
    ");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Company not found']);
        exit;
    }
    
    // ===== 2. جلب جميع الاستبيانات للشركة مع إحصائياتها =====
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.date,
            p.type,
            p.departments,
            p.participant_count,
            p.created_at,
            (SELECT COUNT(*) FROM random_accounts WHERE poll_id = p.id) as generated_accounts,
            (SELECT COUNT(DISTINCT user_id) FROM responses WHERE poll_id = p.id) as responses_count,
            (SELECT AVG(answer_value) FROM responses WHERE poll_id = p.id AND answer_value IS NOT NULL) as avg_score,
            (SELECT MIN(answer_value) FROM responses WHERE poll_id = p.id AND answer_value IS NOT NULL) as min_score,
            (SELECT MAX(answer_value) FROM responses WHERE poll_id = p.id AND answer_value IS NOT NULL) as max_score,
            (SELECT COUNT(DISTINCT user_id) FROM responses WHERE poll_id = p.id AND answer_value IS NOT NULL) as valid_responses
        FROM polls p
        WHERE p.company_id = ?
        ORDER BY p.date DESC, p.created_at DESC
    ");
    $stmt->execute([$companyId]);
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== 3. جلب أحدث 5 ردود للاستبيانات =====
    $recentResponses = [];
    if (!empty($polls)) {
        $pollIds = array_column($polls, 'id');
        $placeholders = implode(',', array_fill(0, count($pollIds), '?'));
        
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                r.poll_id,
                r.user_id,
                r.question_id,
                r.answer_value,
                r.answer_label,
                r.submitted_at,
                u.username
            FROM responses r
            JOIN users u ON r.user_id = u.id
            WHERE r.poll_id IN ($placeholders)
            ORDER BY r.submitted_at DESC
            LIMIT 10
        ");
        $stmt->execute($pollIds);
        $recentResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // ===== 4. جلب إحصائيات عامة للشركة =====
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.id) as total_polls_created,
            SUM(p.participant_count) as total_expected_participants,
            COUNT(DISTINCT r.user_id) as total_actual_participants,
            COUNT(r.id) as total_responses_count,
            AVG(r.answer_value) as overall_avg_score,
            COUNT(DISTINCT CASE WHEN r.answer_value >= 4 THEN r.user_id END) as satisfied_count,
            COUNT(DISTINCT CASE WHEN r.answer_value <= 2 THEN r.user_id END) as dissatisfied_count
        FROM polls p
        LEFT JOIN responses r ON p.id = r.poll_id
        WHERE p.company_id = ?
    ");
    $stmt->execute([$companyId]);
    $statistics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ===== 5. تنسيق البيانات للرد =====
    $formattedPolls = [];
    foreach ($polls as $poll) {
        $participantCount = intval($poll['participant_count']);
        $responsesCount = intval($poll['responses_count']);
        $generatedAccounts = intval($poll['generated_accounts']);
        $validResponses = intval($poll['valid_responses']);
        
        // حساب نسبة الاستجابة
        $responseRate = ($participantCount > 0) ? round(($responsesCount / $participantCount) * 100, 2) : 0;
        
        // حساب متوسط الدرجة
        $avgScore = $poll['avg_score'] ? round(floatval($poll['avg_score']), 2) : null;
        
        // تحديد حالة الاستبيان
        $status = 'pending';
        if ($generatedAccounts > 0 && $responsesCount > 0) {
            $status = 'completed';
        } elseif ($generatedAccounts > 0) {
            $status = 'active';
        }
        
        $formattedPolls[] = [
            'id' => $poll['id'],
            'date' => $poll['date'],
            'type' => $poll['type'],
            'type_label' => ($poll['type'] === 'admin') ? 
                ($lang === 'en' ? 'Admin' : 'إداري') : 
                ($lang === 'en' ? 'Regular' : 'عادي'),
            'departments' => $poll['departments'] ? explode(',', $poll['departments']) : [],
            'departments_text' => $poll['departments'] ?: '-',
            'participant_count' => $participantCount,
            'generated_accounts' => $generatedAccounts,
            'responses_count' => $responsesCount,
            'valid_responses' => $validResponses,
            'response_rate' => $responseRate,
            'avg_score' => $avgScore,
            'min_score' => $poll['min_score'] ? floatval($poll['min_score']) : null,
            'max_score' => $poll['max_score'] ? floatval($poll['max_score']) : null,
            'status' => $status,
            'status_label' => ($lang === 'en') ? 
                ['pending' => 'Pending', 'active' => 'Active', 'completed' => 'Completed'][$status] :
                ['pending' => 'قيد الانتظار', 'active' => 'نشط', 'completed' => 'مكتمل'][$status],
            'status_color' => ['pending' => 'warning', 'active' => 'info', 'completed' => 'success'][$status],
            'created_at' => $poll['created_at']
        ];
    }
    
    // ===== 6. تجهيز الرد النهائي =====
    $response = [
        'success' => true,
        'company' => [
            'id' => intval($company['id']),
            'name' => $company['name'],
            'address' => $company['address'] ?: '-',
            'industry' => $company['industry'] ?: '-',
            'phone' => $company['phone'] ?: '-',
            'created_at' => $company['created_at'],
            'total_polls' => intval($company['total_polls']),
            'regular_polls' => intval($company['regular_polls']),
            'admin_polls' => intval($company['admin_polls']),
            'total_participants' => intval($company['total_participants']),
            'total_responses' => intval($company['total_responses'])
        ],
        'statistics' => [
            'total_polls_created' => intval($statistics['total_polls_created']),
            'total_expected_participants' => intval($statistics['total_expected_participants']),
            'total_actual_participants' => intval($statistics['total_actual_participants']),
            'total_responses_count' => intval($statistics['total_responses_count']),
            'overall_avg_score' => $statistics['overall_avg_score'] ? round(floatval($statistics['overall_avg_score']), 2) : null,
            'satisfied_count' => intval($statistics['satisfied_count']),
            'dissatisfied_count' => intval($statistics['dissatisfied_count']),
            'participation_rate' => ($statistics['total_expected_participants'] > 0) ? 
                round(($statistics['total_actual_participants'] / $statistics['total_expected_participants']) * 100, 2) : 0
        ],
        'polls' => $formattedPolls,
        'recent_responses' => $recentResponses,
        'total_polls_count' => count($formattedPolls),
        'language' => $lang
    ];
    
    // إرجاع النتيجة بتنسيق JSON
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    // تسجيل الخطأ
    logError("خطأ في البحث عن الشركة: " . $e->getMessage(), __FILE__, __LINE__);
    
    // إرجاع رسالة خطأ
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => ($lang === 'en' ? 'Database error occurred' : 'حدث خطأ في قاعدة البيانات')
    ]);
}
?>