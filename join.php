<?php
/**
 * صفحة المشاركة في الاستبيان - النسخة المتطورة مع دعم أنواع الأسئلة المتعددة
 * join.php
 * 
 * تدعم:
 * - أسئلة ليكرت (5 خيارات) مع دعم النتيجة العكسية (Reverse Coded)
 * - أسئلة نصية (إجابة مفتوحة)
 * - أسئلة اختيار من متعدد
 * - أسئلة ترتيب (بدون قيود)
 * - أسئلة رقمية (0-10)
 * - ملاحظات على المحاور
 * - صفحة شكر مع عد تنازلي
 * - دعم أنواع الاستبيان: عادي، إداري، شخصي، تنفيذي
 * 
 * تم التحديث: دعم أنواع الاستبيان الجديدة (شخصي، تنفيذي)
 * تم التحديث: ترتيب الأسئلة حسب المحور و display_order
 * تم التحديث: إصلاح مشكلة أسئلة الترتيب (إدراج NULL في answer_value)
 * تم التحديث: تفعيل خاصية النتيجة العكسية (Reverse Coded) لأسئلة ليكرت
 */

require_once 'config.php';

// التحقق من تسجيل الدخول
startSession();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// التحقق من أن المستخدم عادي وليس مشرف
if ($_SESSION['role'] === ROLE_ADMIN) {
    header('Location: admin.php');
    exit;
}

// الحصول على معلومات المستخدم
$userId = $_SESSION['user_id'];
$pollId = isset($_SESSION['poll_id']) ? intval($_SESSION['poll_id']) : 0;

if ($pollId <= 0) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// الحصول على اللغة
$lang = getPreferredLanguage();
$isRtl = IS_RTL;

// تهيئة متغيرات التنقل
$showPrevious = false;
$currentQuestionIndex = 0;
$totalQuestions = 0;
$isAdminPoll = false;
$questions = [];
$sections = [];
$currentQuestion = null;
$error = '';
$progress = 0;
$isLastQuestionInSection = false;
$currentSectionId = 0;
$currentSectionNumber = 0;
$sectionNote = '';
$sectionTitle = '';
$isComplete = false;

// متغيرات لأنواع الأسئلة الجديدة
$questionType = 'likert';
$questionOptions = [];
$isTextQuestion = false;
$isChoiceQuestion = false;
$isRankingQuestion = false;
$isNumericQuestion = false;
$isReverseCoded = false;

// ============================================================
// ===== دالة عكس النتيجة (Reverse Coded) =====
// ============================================================
function reverseLikertValue($value) {
    // 1 ← 5, 2 ← 4, 3 ← 3, 4 ← 2, 5 ← 1
    if ($value >= 1 && $value <= 5) {
        return 6 - $value;
    }
    return $value;
}

try {
    $pdo = getDBConnection();
    
    // ===== الحصول على معلومات الاستبيان =====
    $stmt = $pdo->prepare("SELECT type, participant_count FROM polls WHERE id = ?");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();
    
    if (!$poll) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $isAdminPoll = ($poll['type'] === 'admin');
    $pollType = $poll['type'];
    
    // ===== الحصول على المحاور حسب نوع الاستبيان =====
    $stmt = $pdo->prepare("
        SELECT * FROM sections 
        WHERE poll_type = ? 
        ORDER BY section_number
    ");
    $stmt->execute([$pollType]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== الحصول على الأسئلة حسب النوع مع رقم المحور =====
    // ترتيب حسب section_id ثم display_order ثم question_number
    $tableName = getQuestionTableName($pollType);
    
    $stmt = $pdo->prepare("
        SELECT q.*, s.id as section_id, s.section_number, s.title_ar, s.title_en
        FROM $tableName q
        LEFT JOIN sections s ON q.section_id = s.id
        ORDER BY q.section_id, q.display_order, q.question_number
    ");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalQuestions = count($questions);
    
    // ===== جلب خيارات الإجابة للأسئلة من نوع choice و ranking =====
    foreach ($questions as &$q) {
        if ($q['question_type'] === 'choice' || $q['question_type'] === 'ranking') {
            $stmt = $pdo->prepare("
                SELECT * FROM question_options 
                WHERE question_id = ? AND question_type = ? 
                ORDER BY option_order
            ");
            $stmt->execute([$q['id'], $pollType]);
            $q['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // ===== الحصول على عدد الإجابات =====
    $stmt = $pdo->prepare("SELECT COUNT(*) as response_count FROM responses WHERE user_id = ? AND poll_id = ?");
    $stmt->execute([$userId, $pollId]);
    $result = $stmt->fetch();
    $answeredCount = intval($result['response_count']);
    
    // ===== التحقق من إكمال الاستبيان =====
    if ($answeredCount >= $totalQuestions) {
        $isComplete = true;
    }
    
    // ============================================================
    // ===== معالجة POST =====
    // ============================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isComplete) {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        // ===== معالجة "التالي" =====
        if ($action === 'next') {
            $questionId = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
            $questionType = isset($_POST['question_type']) ? sanitizeInput($_POST['question_type']) : 'likert';
            $sectionNote = isset($_POST['section_note']) ? trim($_POST['section_note']) : '';
            $isReverse = isset($_POST['is_reverse']) ? intval($_POST['is_reverse']) : 0;
            
            $answerValid = false;
            $answerValue = null;
            $naResponse = isset($_POST['na_response']) ? 1 : 0;
            $noPlanResponse = isset($_POST['no_plan_response']) ? 1 : 0;
            
            // ===== معالجة الإجابة حسب نوع السؤال =====
            switch ($questionType) {
                case 'likert':
                    $answerValue = isset($_POST['answer']) ? intval($_POST['answer']) : 0;
                    
                    // ===== تطبيق النتيجة العكسية (Reverse Coded) =====
                    if ($isReverse == 1 && $answerValue >= 1 && $answerValue <= 5) {
                        $answerValue = reverseLikertValue($answerValue);
                    }
                    
                    if (($answerValue >= 1 && $answerValue <= 5) || $naResponse == 1 || $noPlanResponse == 1) {
                        $answerValid = true;
                    }
                    break;
                    
                case 'text':
                    $answerText = isset($_POST['answer_text']) ? trim($_POST['answer_text']) : '';
                    if (!empty($answerText) && strlen($answerText) <= 500) {
                        $answerValid = true;
                        $answerValue = null;
                    } else {
                        $error = $lang === 'en' ? 'Please enter a text answer (max 500 characters)' : 'الرجاء إدخال إجابة نصية (حد أقصى 500 حرف)';
                    }
                    break;
                    
                case 'choice':
                    $optionId = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;
                    if ($optionId > 0) {
                        $answerValid = true;
                        $answerValue = $optionId;
                    } else {
                        $error = $lang === 'en' ? 'Please select an option' : 'الرجاء اختيار خيار';
                    }
                    break;
                    
                case 'ranking':
                    // ===== معالجة بيانات الترتيب - بدون أي قيود =====
                    $rankingData = isset($_POST['ranking']) ? $_POST['ranking'] : '[]';
                    
                    // فك تشفير JSON
                    if (is_string($rankingData)) {
                        $decoded = json_decode($rankingData, true);
                        if (is_array($decoded)) {
                            $rankingData = $decoded;
                        } else {
                            $rankingData = [];
                        }
                    } elseif (!is_array($rankingData)) {
                        $rankingData = [];
                    }
                    
                    // السماح بأي عدد من الخيارات (حتى 0)
                    $answerValid = true;
                    // تخزين البيانات كـ JSON لحفظها في ranking_responses
                    $answerValue = json_encode($rankingData);
                    break;
                    
                case 'numeric':
                    $numericValue = isset($_POST['numeric_value']) ? intval($_POST['numeric_value']) : -1;
                    if ($numericValue >= 0 && $numericValue <= 10) {
                        $answerValid = true;
                        $answerValue = $numericValue;
                    } else {
                        $error = $lang === 'en' ? 'Please enter a value between 0 and 10' : 'الرجاء إدخال قيمة بين 0 و 10';
                    }
                    break;
                    
                default:
                    $error = $lang === 'en' ? 'Invalid question type' : 'نوع سؤال غير صالح';
            }
            
            // ===== حفظ الإجابة إذا كانت صالحة =====
            if ($answerValid) {
                // ===== تحديد قيمة answer_value للإدراج =====
                // بالنسبة لأسئلة الترتيب، answer_value يكون NULL لأن البيانات تُخزن في ranking_responses
                $saveAnswerValue = $answerValue;
                if ($questionType === 'ranking') {
                    $saveAnswerValue = null;
                }
                
                // حفظ الإجابة في جدول responses
                $stmt = $pdo->prepare("
                    INSERT INTO responses (poll_id, user_id, question_id, question_type, answer_value, na_response, no_plan_response) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $pollId, 
                    $userId, 
                    $questionId, 
                    $pollType,
                    $saveAnswerValue, 
                    $naResponse, 
                    $noPlanResponse
                ]);
                
                // ===== حفظ الإجابات الخاصة حسب نوع السؤال =====
                switch ($questionType) {
                    case 'text':
                        $answerText = isset($_POST['answer_text']) ? trim($_POST['answer_text']) : '';
                        if (!empty($answerText)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO text_responses (poll_id, user_id, question_id, question_type, response_text) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$pollId, $userId, $questionId, $pollType, $answerText]);
                        }
                        break;
                        
                    case 'choice':
                        $optionId = isset($_POST['option_id']) ? intval($_POST['option_id']) : 0;
                        if ($optionId > 0) {
                            $stmt = $pdo->prepare("
                                INSERT INTO choice_responses (poll_id, user_id, question_id, question_type, option_id) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$pollId, $userId, $questionId, $pollType, $optionId]);
                        }
                        break;
                        
                    case 'ranking':
                        // ===== حفظ بيانات الترتيب - بدون أي قيود =====
                        $rankingData = isset($_POST['ranking']) ? $_POST['ranking'] : '[]';
                        
                        // فك تشفير JSON
                        if (is_string($rankingData)) {
                            $decoded = json_decode($rankingData, true);
                            if (is_array($decoded)) {
                                $rankingData = $decoded;
                            } else {
                                $rankingData = [];
                            }
                        } elseif (!is_array($rankingData)) {
                            $rankingData = [];
                        }
                        
                        // حفظ الخيارات المرتبة (إذا وجدت)
                        if (is_array($rankingData) && count($rankingData) > 0) {
                            foreach ($rankingData as $rank => $optionId) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO ranking_responses (poll_id, user_id, question_id, question_type, option_id, rank_order) 
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $pollId, 
                                    $userId, 
                                    $questionId, 
                                    $pollType, 
                                    intval($optionId), 
                                    $rank + 1
                                ]);
                            }
                        }
                        // إذا لم يتم ترتيب أي خيار، نمرر بدون حفظ
                        break;
                        
                    case 'numeric':
                        $numericValue = isset($_POST['numeric_value']) ? intval($_POST['numeric_value']) : 0;
                        if ($numericValue >= 0 && $numericValue <= 10) {
                            $stmt = $pdo->prepare("
                                INSERT INTO numeric_responses (poll_id, user_id, question_id, question_type, numeric_value) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$pollId, $userId, $questionId, $pollType, $numericValue]);
                        }
                        break;
                }
                
                // ===== حفظ ملاحظة المحور إذا كانت موجودة =====
                if (!empty($sectionNote) && $currentSectionId > 0) {
                    $stmt = $pdo->prepare("
                        DELETE FROM section_notes 
                        WHERE poll_id = ? AND user_id = ? AND section_id = ?
                    ");
                    $stmt->execute([$pollId, $userId, $currentSectionId]);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO section_notes (poll_id, user_id, section_id, section_number, note_text) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$pollId, $userId, $currentSectionId, $currentSectionNumber, $sectionNote]);
                }
                
                // إعادة التوجيه إلى نفس الصفحة
                header('Location: join.php');
                exit;
            }
        }
        
        // ===== معالجة "السابق" =====
        if ($action === 'previous') {
            if ($answeredCount > 0) {
                // حذف آخر إجابة من جدول responses
                $stmt = $pdo->prepare("
                    SELECT question_id, question_type FROM responses 
                    WHERE user_id = ? AND poll_id = ? 
                    ORDER BY id DESC LIMIT 1
                ");
                $stmt->execute([$userId, $pollId]);
                $lastResponse = $stmt->fetch();
                
                if ($lastResponse) {
                    $questionId = $lastResponse['question_id'];
                    $qType = $lastResponse['question_type'];
                    
                    // حذف من جدول responses
                    $stmt = $pdo->prepare("
                        DELETE FROM responses 
                        WHERE user_id = ? AND poll_id = ? 
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt->execute([$userId, $pollId]);
                    
                    // حذف من الجداول الخاصة حسب نوع السؤال
                    switch ($qType) {
                        case 'text':
                            $stmt = $pdo->prepare("
                                DELETE FROM text_responses 
                                WHERE poll_id = ? AND user_id = ? AND question_id = ?
                                ORDER BY id DESC LIMIT 1
                            ");
                            $stmt->execute([$pollId, $userId, $questionId]);
                            break;
                            
                        case 'choice':
                            $stmt = $pdo->prepare("
                                DELETE FROM choice_responses 
                                WHERE poll_id = ? AND user_id = ? AND question_id = ?
                                ORDER BY id DESC LIMIT 1
                            ");
                            $stmt->execute([$pollId, $userId, $questionId]);
                            break;
                            
                        case 'ranking':
                            $stmt = $pdo->prepare("
                                DELETE FROM ranking_responses 
                                WHERE poll_id = ? AND user_id = ? AND question_id = ?
                            ");
                            $stmt->execute([$pollId, $userId, $questionId]);
                            break;
                            
                        case 'numeric':
                            $stmt = $pdo->prepare("
                                DELETE FROM numeric_responses 
                                WHERE poll_id = ? AND user_id = ? AND question_id = ?
                                ORDER BY id DESC LIMIT 1
                            ");
                            $stmt->execute([$pollId, $userId, $questionId]);
                            break;
                    }
                }
                
                // إعادة التوجيه إلى نفس الصفحة
                header('Location: join.php');
                exit;
            }
        }
    }
    
    // ===== تحديث عدد الإجابات بعد المعالجة =====
    if (!$isComplete) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as response_count FROM responses WHERE user_id = ? AND poll_id = ?");
        $stmt->execute([$userId, $pollId]);
        $result = $stmt->fetch();
        $answeredCount = intval($result['response_count']);
        
        if ($answeredCount >= $totalQuestions) {
            $isComplete = true;
        }
    }
    
    // ===== إذا كان الاستبيان مكتملاً، عرض صفحة الشكر =====
    if ($isComplete) {
        // لا نقوم بأي معالجة إضافية
    } else {
        // ===== الحصول على السؤال الحالي =====
        $currentQuestion = isset($questions[$answeredCount]) ? $questions[$answeredCount] : null;
        
        if (!$currentQuestion) {
            $isComplete = true;
        } else {
            // ===== تحديد معلومات المحور =====
            $currentSectionId = isset($currentQuestion['section_id']) ? intval($currentQuestion['section_id']) : 0;
            $currentSectionNumber = isset($currentQuestion['section_number']) ? intval($currentQuestion['section_number']) : 0;
            
            // ===== تحديد نوع السؤال =====
            $questionType = isset($currentQuestion['question_type']) ? $currentQuestion['question_type'] : 'likert';
            $isTextQuestion = ($questionType === 'text');
            $isChoiceQuestion = ($questionType === 'choice');
            $isRankingQuestion = ($questionType === 'ranking');
            $isNumericQuestion = ($questionType === 'numeric');
            
            // ===== تحديد إذا كان السؤال معكوساً (Reverse Coded) =====
            $isReverseCoded = (isset($currentQuestion['is_reverse']) && $currentQuestion['is_reverse'] == 1);
            
            // ===== جلب خيارات الإجابة =====
            if ($isChoiceQuestion || $isRankingQuestion) {
                $questionOptions = isset($currentQuestion['options']) ? $currentQuestion['options'] : [];
            }
            
            // الحصول على عنوان المحور
            if ($currentSectionId > 0) {
                foreach ($sections as $s) {
                    if ($s['id'] == $currentSectionId) {
                        $sectionTitle = $lang === 'en' ? $s['title_en'] : $s['title_ar'];
                        break;
                    }
                }
            }
            
            // تحديد إذا كان هذا هو آخر سؤال في المحور
            $isLastQuestionInSection = false;
            if ($currentSectionId > 0) {
                // البحث عن آخر سؤال في نفس المحور باستخدام display_order
                $sectionQuestions = array_filter($questions, function($q) use ($currentSectionId) {
                    return isset($q['section_id']) && intval($q['section_id']) == $currentSectionId;
                });
                $lastQuestionInSection = end($sectionQuestions);
                if ($currentQuestion['id'] == $lastQuestionInSection['id']) {
                    $isLastQuestionInSection = true;
                }
            }
            
            // ===== جلب ملاحظة المحور الحالية إن وجدت =====
            $sectionNote = '';
            if ($currentSectionId > 0) {
                $stmt = $pdo->prepare("
                    SELECT note_text FROM section_notes 
                    WHERE poll_id = ? AND user_id = ? AND section_id = ?
                ");
                $stmt->execute([$pollId, $userId, $currentSectionId]);
                $noteResult = $stmt->fetch();
                if ($noteResult) {
                    $sectionNote = $noteResult['note_text'];
                }
            }
            
            // ===== حساب التقدم =====
            $progress = round(($answeredCount / $totalQuestions) * 100, 2);
            $showPrevious = ($answeredCount > 0);
            $isLastQuestion = ($answeredCount == $totalQuestions - 1);
            $currentQuestionIndex = $answeredCount;
        }
    }
    
} catch (PDOException $e) {
    logError("Error in join.php: " . $e->getMessage(), __FILE__, __LINE__);
    die($lang === 'en' ? 'An error occurred. Please try again.' : 'حدث خطأ. الرجاء المحاولة مرة أخرى.');
}

// ============================================================
// ===== دوال مساعدة =====
// ============================================================

function getQuestionTableName($pollType) {
    switch ($pollType) {
        case 'admin': return 'admin_questions';
        case 'personal': return 'personal_questions';
        case 'executive': return 'executive_questions';
        default: return 'regular_questions';
    }
}

function getQuestionText($question, $lang) {
    if ($lang === 'en') {
        return $question['question_text_en'];
    }
    return $question['question_text_ar'];
}

function getAnswerOptions($lang) {
    if ($lang === 'en') {
        return [
            5 => 'Strongly Agree',
            4 => 'Agree',
            3 => 'Neutral',
            2 => 'Disagree',
            1 => 'Strongly Disagree'
        ];
    }
    return [
        5 => 'أوافق بشدة',
        4 => 'أوافق',
        3 => 'محايد',
        2 => 'أعارض',
        1 => 'أعارض بشدة'
    ];
}

function hasNaOption($question) {
    return isset($question['has_na_option']) && $question['has_na_option'] == 1;
}

function hasNoPlanOption($question) {
    return isset($question['has_no_plan_option']) && $question['has_no_plan_option'] == 1;
}

function getOptionText($option, $lang) {
    if ($lang === 'en') {
        return $option['option_text_en'];
    }
    return $option['option_text_ar'];
}

function getQuestionTypeLabel($type, $lang) {
    $labels = [
        'likert' => ['ar' => 'مقياس ليكرت', 'en' => 'Likert Scale'],
        'text' => ['ar' => 'نصي', 'en' => 'Text'],
        'choice' => ['ar' => 'اختيار من متعدد', 'en' => 'Multiple Choice'],
        'ranking' => ['ar' => 'ترتيب', 'en' => 'Ranking'],
        'numeric' => ['ar' => 'رقمي', 'en' => 'Numeric']
    ];
    return isset($labels[$type][$lang]) ? $labels[$type][$lang] : $type;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $isRtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#4A90E2">
    <title><?php echo $isComplete 
        ? ($lang === 'en' ? 'Thank You!' : 'شكراً لك!') 
        : ($lang === 'en' ? 'Survey Participation' : 'المشاركة في الاستبيان'); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4A90E2;
            --primary-dark: #357ABD;
            --primary-light: #6BA8F0;
            --gradient-start: #4A90E2;
            --gradient-end: #357ABD;
            --text-dark: #2C3E50;
            --text-light: #7F8C8D;
            --bg-light: #F5F7FA;
            --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success-color: #27ae60;
            --success-dark: #1e8449;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }

        .language-selector {
            position: fixed;
            top: 20px;
            <?php echo $isRtl ? 'left' : 'right'; ?>: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 5px;
            backdrop-filter: blur(10px);
        }

        .language-selector button {
            background: transparent;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-light);
            font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
        }

        .language-selector button.active {
            background: var(--primary-color);
            color: white;
        }

        .language-selector button:hover:not(.active) {
            background: #F0F4F8;
        }

        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .bg-animation .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            animation: float 20s infinite ease-in-out;
        }

        .bg-animation .circle:nth-child(1) {
            width: 400px;
            height: 400px;
            top: -150px;
            right: -150px;
            animation-delay: 0s;
        }

        .bg-animation .circle:nth-child(2) {
            width: 300px;
            height: 300px;
            bottom: -100px;
            left: -100px;
            animation-delay: 5s;
        }

        .bg-animation .circle:nth-child(3) {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(180deg); }
        }

        .survey-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 800px;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .survey-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 40px 35px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .survey-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 40px 70px rgba(0, 0, 0, 0.25);
        }

        /* Thank You Page */
        .thank-you-container {
            text-align: center;
            padding: 20px 0;
        }

        .thank-you-container .checkmark {
            display: inline-block;
            width: 100px;
            height: 100px;
            line-height: 100px;
            background: rgba(39, 174, 96, 0.1);
            border-radius: 50%;
            margin-bottom: 15px;
        }

        .thank-you-container .checkmark i {
            font-size: 50px;
            color: var(--success-color);
        }

        .thank-you-container h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .thank-you-container .sub-message {
            font-size: 18px;
            color: var(--text-light);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .countdown-container {
            margin: 25px 0;
            padding: 20px;
            background: rgba(74, 144, 226, 0.05);
            border-radius: 16px;
            border: 2px solid rgba(74, 144, 226, 0.1);
        }

        .countdown-container .timer-label {
            font-size: 14px;
            color: var(--text-light);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .countdown-container .timer-circle {
            display: inline-block;
            width: 80px;
            height: 80px;
            line-height: 80px;
            background: rgba(74, 144, 226, 0.1);
            border-radius: 50%;
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 10px auto;
            border: 3px solid var(--primary-color);
            transition: all 0.3s ease;
            animation: timerPulse 1s ease-in-out infinite;
        }

        @keyframes timerPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .countdown-container .timer-circle.warning {
            color: #e74c3c;
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
        }

        .countdown-container .timer-bar {
            width: 100%;
            height: 6px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
        }

        .countdown-container .timer-bar .timer-progress {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-color));
            border-radius: 10px;
            transition: width 0.3s linear;
            width: 100%;
        }

        .countdown-container .timer-bar .timer-progress.warning {
            background: linear-gradient(90deg, #f39c12, #e74c3c);
        }

        .thank-you-container .btn-manual-exit {
            display: inline-block;
            padding: 12px 35px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: var(--transition);
            margin-top: 10px;
            cursor: pointer;
        }

        .thank-you-container .btn-manual-exit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(74, 144, 226, 0.35);
            color: white;
        }

        .thank-you-container .btn-manual-exit i {
            margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 8px;
        }

        /* Survey Header */
        .survey-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .survey-header .icon {
            font-size: 40px;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .survey-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .survey-header .subtitle {
            color: var(--text-light);
            font-size: 13px;
            margin-top: 3px;
        }

        /* Section Title */
        .section-title-box {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 15px rgba(74, 144, 226, 0.3);
        }

        .section-title-box i {
            margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 8px;
        }

        /* Question Type Badge */
        .question-type-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #e8daef;
            color: #6c3483;
            margin-bottom: 8px;
        }

        /* Reverse Coded Badge */
        .reverse-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            background: #e8daef;
            color: #6c3483;
            margin-bottom: 5px;
        }

        /* Progress Bar */
        .progress-container { margin-bottom: 25px; }
        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            font-size: 13px;
            color: var(--text-light);
        }
        .progress-info .question-counter {
            font-weight: 600;
            color: var(--text-dark);
        }
        .progress {
            height: 8px;
            border-radius: 10px;
            background: #f0f0f0;
            overflow: hidden;
        }
        .progress-bar {
            background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
            transition: width 0.5s ease;
            height: 100%;
        }

        /* Question Area */
        .question-area { margin-bottom: 25px; }
        .question-number {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .question-text {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            line-height: 1.6;
            margin-bottom: 5px;
        }
        .question-hint {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 5px;
        }

        /* Answer Options - Likert */
        .options-grid {
            display: grid;
            gap: 8px;
            margin-top: 15px;
        }

        .option-item { position: relative; }
        .option-item input[type="radio"] { display: none; }
        .option-item label {
            display: flex;
            align-items: center;
            padding: 12px 18px;
            background: white;
            border: 2px solid #E8ECF1;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            color: var(--text-dark);
            font-size: 15px;
            gap: 12px;
        }
        .option-item label:hover {
            border-color: var(--primary-light);
            background: #f8f9fa;
            transform: translateX(<?php echo $isRtl ? '-5px' : '5px'; ?>);
        }
        .option-item input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            background: rgba(74, 144, 226, 0.05);
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
        }
        .option-item label .radio-circle {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid #d0d5dd;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition);
        }
        .option-item input[type="radio"]:checked + label .radio-circle {
            border-color: var(--primary-color);
            background: var(--primary-color);
        }
        .option-item input[type="radio"]:checked + label .radio-circle::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
        }
        .option-item label .option-label { flex: 1; }

        /* Text Answer */
        .text-answer-area {
            margin-top: 15px;
        }
        .text-answer-area textarea {
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            border: 2px solid #E8ECF1;
            font-size: 15px;
            font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            transition: var(--transition);
            resize: vertical;
            min-height: 100px;
            background: white;
            color: var(--text-dark);
        }
        .text-answer-area textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
            outline: none;
        }
        .text-answer-area .char-count {
            font-size: 12px;
            color: var(--text-light);
            text-align: <?php echo $isRtl ? 'left' : 'right'; ?>;
            margin-top: 4px;
        }

        /* Choice Options */
        .choice-options-grid {
            display: grid;
            gap: 8px;
            margin-top: 15px;
        }
        .choice-option { position: relative; }
        .choice-option input[type="radio"] { display: none; }
        .choice-option label {
            display: flex;
            align-items: center;
            padding: 12px 18px;
            background: white;
            border: 2px solid #E8ECF1;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            color: var(--text-dark);
            font-size: 15px;
            gap: 12px;
        }
        .choice-option label:hover {
            border-color: var(--primary-light);
            background: #f8f9fa;
        }
        .choice-option input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            background: rgba(74, 144, 226, 0.05);
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
        }
        .choice-option label .radio-circle {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid #d0d5dd;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: var(--transition);
        }
        .choice-option input[type="radio"]:checked + label .radio-circle {
            border-color: var(--primary-color);
            background: var(--primary-color);
        }
        .choice-option input[type="radio"]:checked + label .radio-circle::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: white;
        }

        /* Ranking */
        .ranking-container {
            margin-top: 15px;
        }
        .ranking-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .ranking-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: white;
            border: 2px solid #E8ECF1;
            border-radius: 12px;
            margin-bottom: 8px;
            cursor: grab;
            transition: var(--transition);
            user-select: none;
        }
        .ranking-item:hover {
            border-color: var(--primary-light);
            background: #f8f9fa;
        }
        .ranking-item:active {
            cursor: grabbing;
        }
        .ranking-item .rank-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
        }
        .ranking-item .rank-text {
            flex: 1;
            font-weight: 500;
            color: var(--text-dark);
        }
        .ranking-item .rank-drag-icon {
            color: var(--text-light);
            font-size: 18px;
        }
        .ranking-item.dragging {
            opacity: 0.5;
            border-color: var(--primary-color);
        }
        .ranking-item.drag-over {
            border-color: var(--primary-color);
            background: rgba(74, 144, 226, 0.05);
        }

        /* Numeric */
        .numeric-container {
            margin-top: 15px;
        }
        .numeric-slider {
            width: 100%;
            padding: 10px 0;
        }
        .numeric-slider input[type="range"] {
            width: 100%;
            height: 8px;
            -webkit-appearance: none;
            appearance: none;
            background: #E8ECF1;
            border-radius: 4px;
            outline: none;
            transition: var(--transition);
        }
        .numeric-slider input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 8px rgba(74, 144, 226, 0.3);
        }
        .numeric-slider input[type="range"]::-webkit-slider-thumb:hover {
            transform: scale(1.1);
        }
        .numeric-slider input[type="range"]::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            border: none;
        }
        .numeric-value-display {
            text-align: center;
            font-size: 36px;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 10px;
        }
        .numeric-labels {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: var(--text-light);
            margin-top: 5px;
        }

        /* Special Options */
        .special-options {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .special-options .btn-special {
            padding: 8px 16px;
            border-radius: 10px;
            border: 2px solid #E8ECF1;
            background: white;
            color: var(--text-dark);
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            font-size: 13px;
        }
        .special-options .btn-special:hover {
            border-color: var(--primary-light);
            background: #f8f9fa;
        }
        .special-options .btn-special.active {
            border-color: var(--primary-color);
            background: rgba(74, 144, 226, 0.05);
            color: var(--primary-color);
        }
        .special-options .btn-special i {
            margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 6px;
        }

        /* Section Note */
        .section-note-container {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px dashed #d0d5dd;
        }
        .section-note-container .note-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            display: block;
            margin-bottom: 8px;
        }
        .section-note-container .note-label i {
            color: var(--primary-color);
            margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 6px;
        }
        .section-note-container .note-textarea {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid #E8ECF1;
            font-size: 14px;
            font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            transition: var(--transition);
            resize: vertical;
            min-height: 80px;
            background: white;
            color: var(--text-dark);
        }
        .section-note-container .note-textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
            outline: none;
        }
        .section-note-container .note-textarea::placeholder {
            color: #B0B8C4;
        }
        .section-note-container .note-char-count {
            font-size: 12px;
            color: var(--text-light);
            text-align: <?php echo $isRtl ? 'left' : 'right'; ?>;
            margin-top: 4px;
        }

        /* Navigation Buttons */
        .nav-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            align-items: stretch;
        }
        .btn-prev {
            padding: 12px 25px;
            background: #6c757d;
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 15px;
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            min-width: 120px;
        }
        .btn-prev:hover:not(:disabled) {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .btn-prev:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-prev i { font-size: 16px; }
        .btn-submit {
            flex: 1;
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            font-size: 15px;
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(74, 144, 226, 0.35);
        }
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }
        .btn-submit i { font-size: 16px; }

        /* Error Message */
        .alert-error {
            background: #FEE2E2;
            border: 1px solid #FECACA;
            color: #991B1B;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        .alert-error i { font-size: 16px; flex-shrink: 0; }

        /* Footer */
        .survey-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        .survey-footer p {
            color: var(--text-light);
            font-size: 12px;
            margin: 0;
        }
        .survey-footer .logout-link {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        .survey-footer .logout-link:hover {
            color: #c0392b;
            text-decoration: underline;
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .loading-overlay.active { display: flex; }
        .loading-spinner {
            background: white;
            padding: 25px 35px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
        }
        .loading-spinner i {
            font-size: 40px;
            color: var(--primary-color);
            animation: spin 1s linear infinite;
        }
        .loading-spinner p {
            margin-top: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .survey-card { padding: 20px 15px; border-radius: 20px; }
            .survey-header h2 { font-size: 18px; }
            .survey-header .icon { font-size: 32px; }
            .section-title-box { font-size: 15px; padding: 10px 15px; }
            .question-text { font-size: 16px; }
            .option-item label, .choice-option label { padding: 10px 14px; font-size: 14px; }
            .btn-submit, .btn-prev { font-size: 14px; padding: 10px 18px; }
            .btn-prev { min-width: 90px; }
            .special-options .btn-special { font-size: 12px; padding: 6px 12px; }
            .section-note-container .note-textarea { min-height: 60px; font-size: 13px; }
            body { padding: 10px; }
            .progress-info { font-size: 11px; }
            .language-selector { top: 10px; <?php echo $isRtl ? 'left' : 'right'; ?>: 10px; padding: 5px; }
            .language-selector button { padding: 5px 10px; font-size: 11px; }
            .nav-buttons { flex-direction: column-reverse; }
            .nav-buttons .btn-prev { width: 100%; }
            .nav-buttons .btn-submit { width: 100%; }
            .thank-you-container h1 { font-size: 24px; }
            .thank-you-container .sub-message { font-size: 15px; }
            .thank-you-container .checkmark { width: 80px; height: 80px; line-height: 80px; }
            .thank-you-container .checkmark i { font-size: 40px; }
            .countdown-container .timer-circle { width: 60px; height: 60px; line-height: 60px; font-size: 28px; }
            .countdown-container .timer-label { font-size: 12px; }
            .numeric-value-display { font-size: 28px; }
            .ranking-item { padding: 10px 14px; }
            .ranking-item .rank-number { width: 24px; height: 24px; font-size: 12px; }
        }

        @media (max-width: 400px) {
            .survey-card { padding: 15px 10px; }
            .question-text { font-size: 14px; }
            .option-item label, .choice-option label { padding: 8px 10px; font-size: 13px; }
            .option-item label .radio-circle, .choice-option label .radio-circle { width: 18px; height: 18px; }
            .section-note-container .note-textarea { min-height: 50px; padding: 8px 12px; font-size: 12px; }
            .numeric-value-display { font-size: 24px; }
            .ranking-item .rank-number { width: 20px; height: 20px; font-size: 11px; }
        }

        /* Dark Mode */
        @media (prefers-color-scheme: dark) {
            body { background: linear-gradient(135deg, #1a2332 0%, #2c3e50 100%); }
            .survey-card { background: rgba(30, 30, 40, 0.95); border-color: rgba(255, 255, 255, 0.05); }
            .survey-header { border-bottom-color: rgba(255, 255, 255, 0.05); }
            .survey-header h2 { color: #ECF0F1; }
            .survey-header .subtitle { color: #A0A8B4; }
            .section-title-box { background: linear-gradient(135deg, #1a2332, #2c3e50); border: 1px solid rgba(255, 255, 255, 0.1); }
            .question-text { color: #ECF0F1; }
            .option-item label, .choice-option label {
                background: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.1);
                color: #ECF0F1;
            }
            .option-item label:hover, .choice-option label:hover { background: rgba(255, 255, 255, 0.08); }
            .option-item input[type="radio"]:checked + label, .choice-option input[type="radio"]:checked + label {
                background: rgba(74, 144, 226, 0.1);
            }
            .option-item label .radio-circle, .choice-option label .radio-circle { border-color: rgba(255, 255, 255, 0.3); }
            .special-options { border-top-color: rgba(255, 255, 255, 0.05); }
            .special-options .btn-special {
                background: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.1);
                color: #ECF0F1;
            }
            .special-options .btn-special:hover { background: rgba(255, 255, 255, 0.08); }
            .special-options .btn-special.active {
                background: rgba(74, 144, 226, 0.2);
                border-color: var(--primary-color);
                color: var(--primary-light);
            }
            .section-note-container { border-top-color: rgba(255, 255, 255, 0.1); }
            .section-note-container .note-label { color: #ECF0F1; }
            .section-note-container .note-textarea {
                background: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.1);
                color: #ECF0F1;
            }
            .section-note-container .note-textarea:focus {
                border-color: var(--primary-color);
                background: rgba(255, 255, 255, 0.08);
            }
            .section-note-container .note-textarea::placeholder { color: rgba(255, 255, 255, 0.3); }
            .section-note-container .note-char-count { color: #A0A8B4; }
            .survey-footer { border-top-color: rgba(255, 255, 255, 0.05); }
            .survey-footer p { color: #A0A8B4; }
            .progress { background: rgba(255, 255, 255, 0.05); }
            .progress-info { color: #A0A8B4; }
            .progress-info .question-counter { color: #ECF0F1; }
            .question-number { background: var(--primary-dark); }
            .question-type-badge { background: rgba(128, 0, 128, 0.2); color: #c77dff; }
            .reverse-badge { background: rgba(128, 0, 128, 0.2); color: #c77dff; }
            .language-selector { background: rgba(30, 30, 40, 0.95); }
            .language-selector button { color: #A0A8B4; }
            .language-selector button:hover:not(.active) { background: rgba(255, 255, 255, 0.05); }
            .language-selector button.active { background: var(--primary-color); color: white; }
            .btn-prev { background: #4a4a5a; }
            .btn-prev:hover:not(:disabled) { background: #3a3a4a; }
            .loading-spinner { background: #2d2d3d; }
            .loading-spinner p { color: #ECF0F1; }
            .alert-error {
                background: rgba(254, 226, 226, 0.15);
                border-color: rgba(254, 202, 202, 0.2);
                color: #FCA5A5;
            }
            .thank-you-container h1 { color: #ECF0F1; }
            .thank-you-container .sub-message { color: #A0A8B4; }
            .countdown-container {
                background: rgba(74, 144, 226, 0.08);
                border-color: rgba(74, 144, 226, 0.15);
            }
            .countdown-container .timer-label { color: #A0A8B4; }
            .countdown-container .timer-circle {
                background: rgba(74, 144, 226, 0.15);
                color: var(--primary-light);
                border-color: var(--primary-light);
            }
            .countdown-container .timer-circle.warning {
                color: #e74c3c;
                border-color: #e74c3c;
                background: rgba(231, 76, 60, 0.15);
            }
            .countdown-container .timer-bar { background: rgba(255, 255, 255, 0.05); }
            .countdown-container .timer-bar .timer-progress {
                background: linear-gradient(90deg, var(--success-color), var(--primary-color));
            }
            .countdown-container .timer-bar .timer-progress.warning {
                background: linear-gradient(90deg, #f39c12, #e74c3c);
            }
            .thank-you-container .btn-manual-exit { background: var(--primary-dark); }
            .thank-you-container .btn-manual-exit:hover { background: var(--primary-color); }
            .thank-you-container .checkmark { background: rgba(39, 174, 96, 0.15); }
            .thank-you-container .checkmark i { color: #2ecc71; }
            .ranking-item {
                background: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.1);
                color: #ECF0F1;
            }
            .ranking-item:hover { background: rgba(255, 255, 255, 0.08); }
            .ranking-item.drag-over { border-color: var(--primary-color); background: rgba(74, 144, 226, 0.1); }
            .ranking-item .rank-text { color: #ECF0F1; }
            .numeric-slider input[type="range"] { background: rgba(255, 255, 255, 0.1); }
            .numeric-value-display { color: var(--primary-light); }
            .numeric-labels { color: #A0A8B4; }
            .text-answer-area textarea {
                background: rgba(255, 255, 255, 0.05);
                border-color: rgba(255, 255, 255, 0.1);
                color: #ECF0F1;
            }
            .text-answer-area textarea:focus {
                border-color: var(--primary-color);
                background: rgba(255, 255, 255, 0.08);
            }
            .text-answer-area .char-count { color: #A0A8B4; }
        }
    </style>
</head>
<body>

<!-- ===== محول اللغة ===== -->
<div class="language-selector">
    <button class="<?php echo $lang === 'ar' ? 'active' : ''; ?>" onclick="switchLanguage('ar')">
        <i class="fas fa-flag"></i> ع
    </button>
    <button class="<?php echo $lang === 'en' ? 'active' : ''; ?>" onclick="switchLanguage('en')">
        <i class="fas fa-flag"></i> E
    </button>
</div>

<!-- Background Animation -->
<div class="bg-animation">
    <div class="circle"></div>
    <div class="circle"></div>
    <div class="circle"></div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <i class="fas fa-spinner"></i>
        <p><?php echo $lang === 'en' ? 'Processing...' : 'جاري المعالجة...'; ?></p>
    </div>
</div>

<!-- Main Container -->
<div class="survey-container">
    <div class="survey-card">
        
        <?php if ($isComplete): ?>
        <!-- ============================================================
        ===== صفحة الشكر - Thank You Page =====
        ============================================================ -->
        <div class="thank-you-container">
            <div class="checkmark">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <h1>
                <i class="fas fa-heart" style="color: #e74c3c; font-size: 28px;"></i>
                <?php echo $lang === 'en' ? 'Thank You!' : 'شكراً لك!'; ?>
            </h1>
            
            <p class="sub-message">
                <i class="fas fa-clipboard-check"></i>
                <?php echo $lang === 'en' 
                    ? 'You have successfully completed the survey. Your responses have been recorded.' 
                    : 'لقد أكملت الاستبيان بنجاح. تم تسجيل إجاباتك.'; ?>
            </p>
            
            <p class="sub-message" style="font-size: 15px; color: var(--text-light);">
                <i class="fas fa-gratipay"></i>
                <?php echo $lang === 'en' 
                    ? 'We greatly appreciate your valuable time and honest feedback.' 
                    : 'نقدر وقتك الثمين وملاحظاتك الصادقة.'; ?>
            </p>
            
            <!-- ===== Countdown Timer ===== -->
            <div class="countdown-container" id="countdownContainer">
                <div class="timer-label">
                    <i class="fas fa-clock"></i>
                    <?php echo $lang === 'en' 
                        ? 'You will be automatically logged out in' 
                        : 'سيتم تسجيل خروجك تلقائياً خلال'; ?>
                </div>
                
                <div class="timer-circle" id="timerDisplay">5</div>
                
                <div class="timer-bar">
                    <div class="timer-progress" id="timerProgress" style="width: 100%;"></div>
                </div>
            </div>
            
            <a href="logout.php" class="btn-manual-exit">
                <i class="fas fa-sign-out-alt"></i>
                <?php echo $lang === 'en' ? 'Exit Now' : 'خروج الآن'; ?>
            </a>
        </div>
        
        <?php else: ?>
        <!-- ============================================================
        ===== نموذج الأسئلة =====
        ============================================================ -->
        
        <!-- Header -->
        <div class="survey-header">
            <div class="icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <h2>
                <?php echo $lang === 'en' ? 'Survey Participation' : 'المشاركة في الاستبيان'; ?>
            </h2>
            <p class="subtitle">
                <?php echo $lang === 'en' 
                    ? 'Your answers are the foundation for the accuracy of the results; therefore, we ask that you reflect your actual experience with complete transparency.' 
                    : 'إجابتك هي اساس دقة النتائج لذا نرجو أن تعكس تجربتك الفعلية بكل شفافية'; ?>
            </p>
        </div>

        <!-- Progress -->
        <div class="progress-container">
            <div class="progress-info">
                <span class="question-counter">
                    <?php echo $lang === 'en' ? 'Question' : 'السؤال'; ?> 
                    <?php echo ($currentQuestionIndex + 1); ?> 
                    <?php echo $lang === 'en' ? 'of' : 'من'; ?> 
                    <?php echo $totalQuestions; ?>
                </span>
                <span><?php echo number_format($progress, 1); ?>%</span>
            </div>
            <div class="progress">
                <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
            </div>
        </div>

        <!-- ===== عنوان المحور ===== -->
        <?php if (!empty($sectionTitle)): ?>
        <div class="section-title-box">
            <i class="fas fa-layer-group"></i>
            <?php echo $sectionTitle; ?>
        </div>
        <?php endif; ?>


        <!-- Error Message -->
        <?php if (isset($error) && !empty($error)): ?>
        <div class="alert-error" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        ===== نموذج واحد رئيسي =====
        ============================================================ -->
        <form method="POST" action="" id="surveyForm">
            <!-- Hidden fields -->
            <input type="hidden" name="action" id="actionField" value="">
            <input type="hidden" name="question_id" value="<?php echo $currentQuestion['id']; ?>">
            <input type="hidden" name="question_type" value="<?php echo $questionType; ?>">
            <input type="hidden" name="is_reverse" value="<?php echo $isReverseCoded ? 1 : 0; ?>">
            <input type="hidden" name="na_response" id="naResponse" value="0">
            <input type="hidden" name="no_plan_response" id="noPlanResponse" value="0">

            <!-- Question Area -->
            <div class="question-area">
                <div class="question-number">
                    <i class="fas fa-question-circle"></i>
                    <?php echo $lang === 'en' ? 'Question' : 'سؤال'; ?> <?php echo $currentQuestion['question_number']; ?>
                </div>
                <?php if ($isReverseCoded): ?>
                <!--<div class="reverse-badge">
                    <i class="fas fa-exchange-alt"></i> <?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?>
                </div> -->
                <?php endif; ?>
                <div class="question-text">
                    <?php echo getQuestionText($currentQuestion, $lang); ?>
                </div>
                <?php if (hasNaOption($currentQuestion) || hasNoPlanOption($currentQuestion)): ?>
                <div class="question-hint">
                    <i class="fas fa-info-circle"></i>
                    <?php 
                    $hints = [];
                    if (hasNaOption($currentQuestion)) {
                        $hints[] = $lang === 'en' ? 'Select "N/A" if not applicable' : 'اختر "لا ينطبق" إذا كان لا ينطبق عليك';
                    }
                    if (hasNoPlanOption($currentQuestion)) {
                        $hints[] = $lang === 'en' ? 'Select "No development plan" if you don\'t have one' : 'اختر "لا توجد خطة تطوير" إذا لم تكن لديك';
                    }
                    echo implode(' | ', $hints);
                    ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ============================================================
            ===== Answer Options حسب نوع السؤال =====
            ============================================================ -->
            
            <?php if ($questionType === 'likert'): ?>
            <!-- ===== مقياس ليكرت ===== -->
            <div class="options-grid" id="optionsGrid">
                <?php 
                $options = getAnswerOptions($lang);
                foreach ($options as $value => $label): 
                ?>
                <div class="option-item">
                    <input type="radio" name="answer" id="option_<?php echo $value; ?>" value="<?php echo $value; ?>">
                    <label for="option_<?php echo $value; ?>">
                        <span class="radio-circle"></span>
                        <span class="option-label"><?php echo $label; ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php elseif ($questionType === 'text'): ?>
            <!-- ===== إجابة نصية ===== -->
            <div class="text-answer-area">
                <textarea 
                    id="answerText"
                    name="answer_text" 
                    placeholder="<?php echo $lang === 'en' ? 'Write your answer here...' : 'اكتب إجابتك هنا...'; ?>"
                    maxlength="500"
                    onkeyup="updateTextCharCount()"
                ></textarea>
                <div class="char-count">
                    <span id="textCharCount">0</span> / 500
                </div>
            </div>
            
            <?php elseif ($questionType === 'choice'): ?>
            <!-- ===== اختيار من متعدد ===== -->
            <div class="choice-options-grid">
                <?php foreach ($questionOptions as $option): ?>
                <div class="choice-option">
                    <input type="radio" name="option_id" id="choice_<?php echo $option['id']; ?>" value="<?php echo $option['id']; ?>">
                    <label for="choice_<?php echo $option['id']; ?>">
                        <span class="radio-circle"></span>
                        <span class="option-label"><?php echo getOptionText($option, $lang); ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php elseif ($questionType === 'ranking'): ?>
            <!-- ===== ترتيب ===== -->
            <div class="ranking-container">
                <p class="text-muted small mb-2">
                    <i class="fas fa-arrows-alt"></i>
                    <?php echo $lang === 'en' 
                        ? 'Drag and drop to rank the following options from most important (1) to least important' 
                        : 'اسحب وأفلت لترتيب الخيارات التالية من الأكثر أهمية (1) إلى الأقل أهمية'; ?>
                </p>
                <ul class="ranking-list" id="rankingList">
                    <?php foreach ($questionOptions as $index => $option): ?>
                    <li class="ranking-item" data-id="<?php echo $option['id']; ?>" draggable="true">
                        <span class="rank-number"><?php echo $index + 1; ?></span>
                        <span class="rank-text"><?php echo getOptionText($option, $lang); ?></span>
                        <span class="rank-drag-icon"><i class="fas fa-grip-lines"></i></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <input type="hidden" name="ranking" id="rankingInput" value="">
            </div>
            
            <?php elseif ($questionType === 'numeric'): ?>
            <!-- ===== رقمي (0-10) ===== -->
            <div class="numeric-container">
                <div class="numeric-slider">
                    <input type="range" id="numericSlider" name="numeric_value" min="0" max="10" value="5" step="1">
                </div>
                <div class="numeric-value-display" id="numericDisplay">5</div>
                <div class="numeric-labels">
                    <span><?php echo $lang === 'en' ? 'Low' : 'منخفض'; ?></span>
                    <span><?php echo $lang === 'en' ? 'High' : 'مرتفع'; ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================================
            ===== Special Options (N/A, No Plan) =====
            ============================================================ -->
            <?php if (($questionType === 'likert') && (hasNaOption($currentQuestion) || hasNoPlanOption($currentQuestion))): ?>
            <div class="special-options">
                <?php if (hasNaOption($currentQuestion)): ?>
                <button type="button" class="btn-special" id="naOption" onclick="toggleSpecialOption(this, 'naResponse')">
                    <i class="fas fa-times-circle"></i>
                    <?php echo $lang === 'en' ? 'N/A (Not Applicable)' : 'لا ينطبق (N/A)'; ?>
                </button>
                <?php endif; ?>
                
                <?php if (hasNoPlanOption($currentQuestion)): ?>
                <button type="button" class="btn-special" id="noPlanOption" onclick="toggleSpecialOption(this, 'noPlanResponse')">
                    <i class="fas fa-ban"></i>
                    <?php echo $lang === 'en' ? 'No Development Plan' : 'لا توجد خطة تطوير'; ?>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ============================================================
            ===== ملاحظة المحور (تظهر فقط في آخر سؤال في المحور) =====
            ============================================================ -->
            <?php if ($isLastQuestionInSection && $currentSectionId > 0): ?>
            <div class="section-note-container">
                <label class="note-label" for="sectionNote">
                    <i class="fas fa-pen"></i>
                    <?php echo $lang === 'en' 
                        ? 'Add a note about this section (optional)' 
                        : 'أضف ملاحظة حول هذا المحور (اختياري)'; ?>
                </label>
                <textarea 
                    class="note-textarea" 
                    id="sectionNote" 
                    name="section_note" 
                    placeholder="<?php echo $lang === 'en' 
                        ? 'Write your notes here...' 
                        : 'اكتب ملاحظاتك هنا...'; ?>"
                    maxlength="500"
                    onkeyup="updateCharCount()"
                ><?php echo htmlspecialchars($sectionNote); ?></textarea>
                <div class="note-char-count">
                    <span id="charCount"><?php echo strlen($sectionNote); ?></span> / 500
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================================
            ===== Navigation Buttons =====
            ============================================================ -->
            <div class="nav-buttons">
                <?php if ($showPrevious): ?>
                <button type="button" class="btn-prev" id="prevBtn" onclick="submitForm('previous')">
                    <i class="fas fa-arrow-<?php echo $isRtl ? 'right' : 'left'; ?>"></i>
                    <?php echo $lang === 'en' ? 'Previous' : 'السابق'; ?>
                </button>
                <?php else: ?>
                <button type="button" class="btn-prev" disabled>
                    <i class="fas fa-arrow-<?php echo $isRtl ? 'right' : 'left'; ?>"></i>
                    <?php echo $lang === 'en' ? 'Previous' : 'السابق'; ?>
                </button>
                <?php endif; ?>
                
                <button type="button" class="btn-submit" id="submitBtn" onclick="submitForm('next')">
                    <?php 
                    if ($isLastQuestion) {
                        echo $lang === 'en' ? 'Submit Survey' : 'إرسال الاستبيان';
                    } else {
                        echo $lang === 'en' ? 'Next Question' : 'السؤال التالي';
                    }
                    ?>
                    <i class="fas fa-arrow-<?php echo $isRtl ? 'left' : 'right'; ?>"></i>
                </button>
            </div>
        </form>

        <!-- Footer -->
        <div class="survey-footer">
            <p>
                <?php echo $lang === 'en' ? 'Logged in as:' : 'مسجل الدخول كـ:'; ?> 
                <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <br>
                <a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <?php echo $lang === 'en' ? 'Logout' : 'تسجيل الخروج'; ?>
                </a>
            </p>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<!-- jQuery 3 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    'use strict';
    
    <?php if ($isComplete): ?>
    // ============================================================
    // ===== عد تنازلي 5 ثوانٍ ثم تسجيل الخروج التلقائي =====
    // ============================================================
    var countdown = 5;
    var timerDisplay = document.getElementById('timerDisplay');
    var timerProgress = document.getElementById('timerProgress');
    
    function updateTimer() {
        countdown--;
        
        if (timerDisplay) {
            timerDisplay.textContent = countdown;
            if (countdown <= 3) {
                timerDisplay.classList.add('warning');
                if (timerProgress) {
                    timerProgress.classList.add('warning');
                }
            }
        }
        
        if (timerProgress) {
            var percentage = (countdown / 5) * 100;
            timerProgress.style.width = percentage + '%';
        }
        
        if (countdown <= 0) {
            window.location.href = 'logout.php';
        }
    }
    
    setTimeout(function() {
        var interval = setInterval(function() {
            updateTimer();
            if (countdown <= 0) {
                clearInterval(interval);
            }
        }, 1000);
    }, 1000);
    
    <?php endif; ?>
    
    // ============================================================
    // ===== تحديث عدد الأحرف =====
    // ============================================================
    window.updateCharCount = function() {
        var textarea = document.getElementById('sectionNote');
        var countSpan = document.getElementById('charCount');
        if (textarea && countSpan) {
            countSpan.textContent = textarea.value.length;
        }
    };
    
    window.updateTextCharCount = function() {
        var textarea = document.getElementById('answerText');
        var countSpan = document.getElementById('textCharCount');
        if (textarea && countSpan) {
            countSpan.textContent = textarea.value.length;
        }
    };
    
    // ============================================================
    // ===== التنقل عبر الأسئلة باستخدام لوحة المفاتيح =====
    // ============================================================
    <?php if (!$isComplete): ?>
    <?php if ($questionType === 'likert'): ?>
    $(document).keydown(function(e) {
        if (e.key >= '1' && e.key <= '5') {
            var index = parseInt(e.key) - 1;
            var option = $('.option-item input[type="radio"]').eq(index);
            if (option.length) {
                option.prop('checked', true);
                option.trigger('change');
                if ($('#naResponse').val() === '1') {
                    $('#naOption').removeClass('active');
                    $('#naResponse').val('0');
                }
                if ($('#noPlanResponse').val() === '1') {
                    $('#noPlanOption').removeClass('active');
                    $('#noPlanResponse').val('0');
                }
            }
        }
        
        if (e.key === 'Enter' && !e.shiftKey) {
            var activeElement = document.activeElement;
            if (activeElement && activeElement.tagName !== 'BUTTON' && 
                activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                submitForm('next');
            }
        }
    });
    <?php endif; ?>
    
    <?php if ($questionType === 'numeric'): ?>
    // ===== Numeric Slider =====
    var slider = document.getElementById('numericSlider');
    var display = document.getElementById('numericDisplay');
    if (slider && display) {
        slider.addEventListener('input', function() {
            display.textContent = this.value;
        });
    }
    <?php endif; ?>
    
    <?php if ($questionType === 'ranking'): ?>
    // ===== Ranking Drag and Drop =====
    var rankingItems = document.querySelectorAll('.ranking-item');
    var rankingList = document.getElementById('rankingList');
    
    rankingItems.forEach(function(item) {
        item.addEventListener('dragstart', function(e) {
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.dataset.id);
        });
        
        item.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var dragging = document.querySelector('.ranking-item.dragging');
            if (dragging && dragging !== this) {
                var rect = this.getBoundingClientRect();
                var offset = <?php echo $isRtl ? 'rect.left' : 'rect.left'; ?>;
                if (offset < rect.left + rect.width / 2) {
                    this.parentElement.insertBefore(dragging, this);
                } else {
                    this.parentElement.insertBefore(dragging, this.nextSibling);
                }
            }
        });
        
        item.addEventListener('drop', function(e) {
            e.preventDefault();
            updateRankNumbers();
            updateRankingInput();
        });
    });
    
    function updateRankNumbers() {
        var items = document.querySelectorAll('.ranking-item');
        items.forEach(function(item, index) {
            var numSpan = item.querySelector('.rank-number');
            if (numSpan) {
                numSpan.textContent = index + 1;
            }
        });
    }
    
    function updateRankingInput() {
        var items = document.querySelectorAll('.ranking-item');
        var ids = [];
        items.forEach(function(item) {
            ids.push(item.dataset.id);
        });
        document.getElementById('rankingInput').value = JSON.stringify(ids);
    }
    
    // تحديث الترتيب الأولي
    updateRankingInput();
    <?php endif; ?>
    
    <?php if ($questionType === 'likert'): ?>
    // ============================================================
    // ===== تأثيرات عند اختيار إجابة =====
    // ============================================================
    $('input[name="answer"]').on('change', function() {
        if ($('#naResponse').val() === '1') {
            $('#naOption').removeClass('active');
            $('#naResponse').val('0');
        }
        if ($('#noPlanResponse').val() === '1') {
            $('#noPlanOption').removeClass('active');
            $('#noPlanResponse').val('0');
        }
    });
    <?php endif; ?>
    
    <?php endif; ?>
});

// ============================================================
// ===== دالة إرسال النموذج =====
// ============================================================
function submitForm(action) {
    document.getElementById('actionField').value = action;
    
    if (action === 'next') {
        <?php if ($questionType === 'likert'): ?>
        var hasAnswer = document.querySelector('input[name="answer"]:checked');
        var naActive = document.getElementById('naOption') && document.getElementById('naOption').classList.contains('active');
        var noPlanActive = document.getElementById('noPlanOption') && document.getElementById('noPlanOption').classList.contains('active');
        if (!hasAnswer && !naActive && !noPlanActive) {
            alert('<?php echo $lang === 'en' ? 'Please select an answer or a special option' : 'الرجاء اختيار إجابة أو خيار خاص'; ?>');
            return;
        }
        <?php elseif ($questionType === 'text'): ?>
        var textValue = document.getElementById('answerText').value.trim();
        if (!textValue) {
            alert('<?php echo $lang === 'en' ? 'Please enter your answer' : 'الرجاء إدخال إجابتك'; ?>');
            return;
        }
        <?php elseif ($questionType === 'choice'): ?>
        var hasChoice = document.querySelector('input[name="option_id"]:checked');
        if (!hasChoice) {
            alert('<?php echo $lang === 'en' ? 'Please select an option' : 'الرجاء اختيار خيار'; ?>');
            return;
        }
        <?php elseif ($questionType === 'ranking'): ?>
        // ===== تحديث حقل الترتيب قبل الإرسال - بدون أي قيود =====
        var items = document.querySelectorAll('.ranking-item');
        var ids = [];
        items.forEach(function(item) {
            var id = item.getAttribute('data-id');
            if (id) {
                ids.push(id);
            }
        });
        document.getElementById('rankingInput').value = JSON.stringify(ids);
        // لا توجد قيود على عدد الخيارات - يسمح بأي عدد (0, 1, 2, أو أكثر)
        <?php elseif ($questionType === 'numeric'): ?>
        var numericValue = document.getElementById('numericSlider').value;
        if (numericValue === undefined || numericValue === null) {
            alert('<?php echo $lang === 'en' ? 'Please select a value' : 'الرجاء اختيار قيمة'; ?>');
            return;
        }
        <?php endif; ?>
    }
    
    document.getElementById('loadingOverlay').classList.add('active');
    document.getElementById('submitBtn').disabled = true;
    var prevBtn = document.getElementById('prevBtn');
    if (prevBtn) {
        prevBtn.disabled = true;
    }
    
    document.getElementById('surveyForm').submit();
}

// ============================================================
// ===== دالة تبديل الخيارات الخاصة =====
// ============================================================
function toggleSpecialOption(button, hiddenFieldId) {
    var $button = $(button);
    var $hiddenField = $('#' + hiddenFieldId);
    var isActive = $button.hasClass('active');
    
    if (isActive) {
        $button.removeClass('active');
        $hiddenField.val('0');
    } else {
        if (hiddenFieldId === 'naResponse') {
            $('#noPlanOption').removeClass('active');
            $('#noPlanResponse').val('0');
        } else if (hiddenFieldId === 'noPlanResponse') {
            $('#naOption').removeClass('active');
            $('#naResponse').val('0');
        }
        $('input[name="answer"]').prop('checked', false);
        $button.addClass('active');
        $hiddenField.val('1');
    }
}

// ============================================================
// ===== تبديل اللغة =====
// ============================================================
function switchLanguage(lang) {
    $.ajax({
        url: 'set_language.php',
        method: 'POST',
        data: { language: lang },
        success: function() {
            location.reload();
        },
        error: function() {
            window.location.href = window.location.pathname + '?lang=' + lang;
        }
    });
}
</script>

</body>
</html>