<?php
/**
 * ملف جلب الاستبيانات لشركة معينة (نسخة محسنة)
 * get_polls.php
 * يدعم الترجمة والتحقق من الصلاحية
 * يدعم أنواع الاستبيان: عادي، إداري، شخصي، تنفيذي
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

// دالة للحصول على تسمية نوع الاستبيان
function getPollTypeLabel($type, $lang) {
    $labels = [
        'regular' => ['ar' => 'عادي', 'en' => 'Regular'],
        'admin' => ['ar' => 'إداري', 'en' => 'Admin'],
        'personal' => ['ar' => 'شخصي', 'en' => 'Personal'],
        'executive' => ['ar' => 'تنفيذي', 'en' => 'Executive']
    ];
    return isset($labels[$type][$lang]) ? $labels[$type][$lang] : $type;
}

try {
    // الاتصال بقاعدة البيانات
    $pdo = getDBConnection();
    
    // التحقق من وجود الشركة
    $stmt = $pdo->prepare("SELECT id, name FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Company not found']);
        exit;
    }
    
    // جلب الاستبيانات للشركة المحددة مع عدد المشاركين الفعليين
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.date,
            p.type,
            p.departments,
            p.participant_count,
            p.created_at,
            c.name as company_name,
            (SELECT COUNT(*) FROM random_accounts WHERE poll_id = p.id) as generated_accounts,
            (SELECT COUNT(DISTINCT user_id) FROM responses WHERE poll_id = p.id) as responses_count,
            (SELECT COUNT(*) FROM responses WHERE poll_id = p.id AND answer_value IS NOT NULL) as valid_responses_count,
            (SELECT AVG(answer_value) FROM responses WHERE poll_id = p.id AND answer_value IS NOT NULL) as avg_score,
            (SELECT COUNT(*) FROM users WHERE poll_id = p.id AND role = 'user') as total_users
        FROM polls p
        JOIN companies c ON p.company_id = c.id
        WHERE p.company_id = ?
        ORDER BY p.date DESC, p.created_at DESC
    ");
    $stmt->execute([$companyId]);
    $polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنسيق البيانات
    $result = [];
    foreach ($polls as $poll) {
        $generatedAccounts = intval($poll['generated_accounts']);
        $responsesCount = intval($poll['responses_count']);
        $validResponses = intval($poll['valid_responses_count']);
        $participantCount = intval($poll['participant_count']);
        $totalUsers = intval($poll['total_users']);
        
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
        
        $statusLabels = [
            'pending' => ['ar' => 'قيد الانتظار', 'en' => 'Pending'],
            'active' => ['ar' => 'نشط', 'en' => 'Active'],
            'completed' => ['ar' => 'مكتمل', 'en' => 'Completed']
        ];
        
        $statusColors = [
            'pending' => 'warning',
            'active' => 'info',
            'completed' => 'success'
        ];
        
        $result[] = [
            'id' => $poll['id'],
            'date' => $poll['date'],
            'type' => $poll['type'],
            'type_label' => getPollTypeLabel($poll['type'], $lang),
            'departments' => $poll['departments'],
            'participant_count' => $participantCount,
            'generated_accounts' => $generatedAccounts,
            'responses_count' => $responsesCount,
            'valid_responses' => $validResponses,
            'response_rate' => $responseRate,
            'avg_score' => $avgScore,
            'company_name' => $poll['company_name'],
            'created_at' => $poll['created_at'],
            'has_accounts' => $generatedAccounts > 0,
            'has_responses' => $responsesCount > 0,
            'is_completed' => ($generatedAccounts > 0 && $responsesCount > 0 && $responsesCount >= $participantCount),
            'status' => $status,
            'status_label' => isset($statusLabels[$status][$lang]) ? $statusLabels[$status][$lang] : $status,
            'status_color' => isset($statusColors[$status]) ? $statusColors[$status] : 'secondary',
            'total_users' => $totalUsers,
            'completion_rate' => ($participantCount > 0) ? round(($responsesCount / $participantCount) * 100, 2) : 0
        ];
    }
    
    // إرجاع النتيجة بتنسيق JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'company' => [
            'id' => $company['id'],
            'name' => $company['name']
        ],
        'polls' => $result,
        'total' => count($result),
        'statistics' => [
            'total_polls' => count($result),
            'total_participants' => array_sum(array_column($result, 'participant_count')),
            'total_responses' => array_sum(array_column($result, 'responses_count')),
            'average_response_rate' => count($result) > 0 ? round(array_sum(array_column($result, 'response_rate')) / count($result), 2) : 0
        ],
        'language' => $lang
    ]);
    
} catch (PDOException $e) {
    // تسجيل الخطأ
    logError("خطأ في جلب الاستبيانات: " . $e->getMessage(), __FILE__, __LINE__);
    
    // إرجاع رسالة خطأ
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => ($lang === 'en' ? 'Database error occurred' : 'حدث خطأ في قاعدة البيانات')
    ]);
}
?>