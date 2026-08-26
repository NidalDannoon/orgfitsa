<?php
/**
 * صفحة عرض نتائج الاستبيان - النسخة المتطورة مع دعم جميع أنواع الأسئلة
 * poll.php
 * 
 * تعرض:
 * - بيانات الاستبيان
 * - إحصائيات متقدمة (Mean, StdDev, Cronbach's Alpha, Margin of Error, إلخ)
 * - نتائج الأسئلة حسب المحاور
 * - توزيع الإجابات مع الرسوم البيانية
 * - ملاحظات المحاور
 * - دعم كامل لأنواع الأسئلة الجديدة (نصية، اختيار، ترتيب، رقمي)
 * - دعم أنواع الاستبيان: عادي، إداري، شخصي، تنفيذي
 * - دعم خاصية النتيجة العكسية (Reverse Coded) لأسئلة ليكرت
 * 
 * ملاحظة: الأسئلة من نوع (text, choice, ranking, numeric) لا تدخل في الإحصاءات
 * وتظهر فقط كعرض للنتائج بدون تأثير على المقاييس الإحصائية
 * 
 * تم التحديث: دعم أنواع الاستبيان الجديدة (شخصي، تنفيذي)
 * تم التحديث: ترتيب الأسئلة حسب المحور و display_order
 * تم التحديث: تفعيل خاصية النتيجة العكسية (Reverse Coded) لأسئلة ليكرت
 * تم التحديث: إصلاح مشكلة محول اللغة
 */

// ============================================================
// ===== تشغيل عرض الأخطاء للتصحيح =====
// ============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// ============================================================
// ===== التحقق من تسجيل الدخول =====
// ============================================================
startSession();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ============================================================
// ===== الحصول على معرف الاستبيان =====
// ============================================================
$pollId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pollId <= 0) {
    die('Invalid poll ID');
}

// ============================================================
// ===== الحصول على اللغة =====
// ============================================================
$lang = getPreferredLanguage();
$isRtl = IS_RTL;

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

function getScoreLabel($score, $lang = 'ar') {
    if ($score === null) return '-';
    $labels = [
        1 => ['ar' => 'أعارض بشدة', 'en' => 'Strongly Disagree'],
        2 => ['ar' => 'أعارض', 'en' => 'Disagree'],
        3 => ['ar' => 'محايد', 'en' => 'Neutral'],
        4 => ['ar' => 'أوافق', 'en' => 'Agree'],
        5 => ['ar' => 'أوافق بشدة', 'en' => 'Strongly Agree']
    ];
    $rounded = round($score);
    if (isset($labels[$rounded])) {
        return $labels[$rounded][$lang];
    }
    return $score;
}

function getSectionTitleById($sectionId, $sections, $lang) {
    foreach ($sections as $s) {
        if ($s['id'] == $sectionId) {
            return $lang === 'en' ? $s['title_en'] : $s['title_ar'];
        }
    }
    return '';
}

function getSectionDescription($sectionId, $sections, $lang) {
    foreach ($sections as $s) {
        if ($s['id'] == $sectionId) {
            $desc = $lang === 'en' ? $s['description_en'] : $s['description_ar'];
            return !empty($desc) ? $desc : '';
        }
    }
    return '';
}

function getOptionText($option, $lang) {
    return $lang === 'en' ? $option['option_text_en'] : $option['option_text_ar'];
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

// ===== دالة عكس النتيجة (Reverse Coded) =====
function reverseLikertValue($value) {
    // 1 ← 5, 2 ← 4, 3 ← 3, 4 ← 2, 5 ← 1
    if ($value >= 1 && $value <= 5) {
        return 6 - $value;
    }
    return $value;
}

function calculateCronbachAlpha($responsesMatrix) {
    if (empty($responsesMatrix)) return null;
    
    $items = count($responsesMatrix);
    if ($items < 2) return null;
    
    $respondents = count($responsesMatrix[0]);
    if ($respondents < 2) return null;
    
    $variances = [];
    for ($i = 0; $i < $items; $i++) {
        $mean = array_sum($responsesMatrix[$i]) / $respondents;
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $responsesMatrix[$i])) / $respondents;
        $variances[] = $variance;
    }
    
    $totalVariance = array_sum($variances);
    $sumVariances = array_sum($variances);
    
    if ($totalVariance == 0) return null;
    
    $alpha = ($items / ($items - 1)) * (1 - ($sumVariances / $totalVariance));
    return $alpha;
}

function calculateSampleSize($populationSize, $confidenceLevel = 1.96, $marginOfError = 0.05, $p = 0.5) {
    if ($populationSize <= 0) return null;
    
    $z = $confidenceLevel;
    $e = $marginOfError;
    
    $n0 = ($z * $z * $p * (1 - $p)) / ($e * $e);
    $n = $n0 / (1 + (($n0 - 1) / $populationSize));
    
    return ceil($n);
}

function calculatePearsonCorrelation($x, $y) {
    $n = count($x);
    if ($n < 2 || count($y) != $n) return null;
    
    $sumX = array_sum($x);
    $sumY = array_sum($y);
    $sumXY = array_sum(array_map(function($a, $b) { return $a * $b; }, $x, $y));
    $sumX2 = array_sum(array_map(function($a) { return $a * $a; }, $x));
    $sumY2 = array_sum(array_map(function($a) { return $a * $a; }, $y));
    
    $numerator = ($n * $sumXY) - ($sumX * $sumY);
    $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));
    
    return ($denominator != 0) ? $numerator / $denominator : null;
}

// ============================================================
// ===== جلب البيانات من قاعدة البيانات =====
// ============================================================

try {
    $pdo = getDBConnection();
    
    // جلب بيانات الاستبيان
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.name as company_name,
            c.address as company_address,
            c.industry as company_industry,
            c.phone as company_phone,
            u.username as created_by_username
        FROM polls p
        JOIN companies c ON p.company_id = c.id
        LEFT JOIN users u ON p.created_by = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$poll) {
        die('Poll not found');
    }
    
    $pollType = $poll['type'];
    $tableName = getQuestionTableName($pollType);
    
    // جلب المحاور
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE poll_type = ? ORDER BY section_number");
    $stmt->execute([$pollType]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب الأسئلة
    $stmt = $pdo->prepare("
        SELECT q.*, s.id as section_id, s.section_number, s.title_ar, s.title_en
        FROM $tableName q
        LEFT JOIN sections s ON q.section_id = s.id
        ORDER BY q.section_id, q.display_order, q.question_number
    ");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب خيارات الإجابة
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
    
    // جلب الإجابات
    $stmt = $pdo->prepare("
        SELECT r.*, u.username
        FROM responses r
        JOIN users u ON r.user_id = u.id
        WHERE r.poll_id = ?
        ORDER BY r.user_id, r.question_id
    ");
    $stmt->execute([$pollId]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب الإجابات الخاصة
    $stmt = $pdo->prepare("SELECT * FROM text_responses WHERE poll_id = ?");
    $stmt->execute([$pollId]);
    $textResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT cr.*, qo.option_text_ar, qo.option_text_en 
        FROM choice_responses cr
        JOIN question_options qo ON cr.option_id = qo.id
        WHERE cr.poll_id = ?
    ");
    $stmt->execute([$pollId]);
    $choiceResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT rr.*, qo.option_text_ar, qo.option_text_en 
        FROM ranking_responses rr
        JOIN question_options qo ON rr.option_id = qo.id
        WHERE rr.poll_id = ?
        ORDER BY rr.user_id, rr.question_id, rr.rank_order
    ");
    $stmt->execute([$pollId]);
    $rankingResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM numeric_responses WHERE poll_id = ?");
    $stmt->execute([$pollId]);
    $numericResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب ملاحظات المحاور
    $stmt = $pdo->prepare("
        SELECT sn.section_id, sn.section_number, sn.note_text, u.username 
        FROM section_notes sn
        JOIN users u ON sn.user_id = u.id
        WHERE sn.poll_id = ?
        ORDER BY sn.section_number, sn.created_at
    ");
    $stmt->execute([$pollId]);
    $sectionNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنظيم الملاحظات حسب المحور
    $notesBySection = [];
    foreach ($sectionNotes as $note) {
        $sectionId = $note['section_id'];
        if (!isset($notesBySection[$sectionId])) {
            $notesBySection[$sectionId] = [];
        }
        $notesBySection[$sectionId][] = $note;
    }
    
    // ===== معالجة البيانات للإحصائيات =====
    $questionScores = [];
    $userScores = [];
    $allAnswers = [];
    $nonStatisticalQuestions = [];
    
    // تهيئة مصفوفة scores لكل سؤال
    foreach ($questions as $q) {
        $qType = isset($q['question_type']) ? $q['question_type'] : 'likert';
        $isReverse = isset($q['is_reverse']) ? intval($q['is_reverse']) : 0;
        
        // أسئلة من نوع likert فقط تدخل في الإحصاءات
        if ($qType === 'likert') {
            $questionScores[$q['id']] = [
                'question_number' => $q['question_number'],
                'question_text' => $lang === 'en' ? $q['question_text_en'] : $q['question_text_ar'],
                'answers' => [],
                'values' => [],
                'is_reverse' => $isReverse,
                'section_id' => isset($q['section_id']) ? intval($q['section_id']) : 0,
                'section_number' => isset($q['section_number']) ? intval($q['section_number']) : 0,
                'question_type' => $qType,
                'display_order' => isset($q['display_order']) ? intval($q['display_order']) : 0,
                'has_na_option' => isset($q['has_na_option']) ? $q['has_na_option'] : 0,
                'has_no_plan_option' => isset($q['has_no_plan_option']) ? $q['has_no_plan_option'] : 0
            ];
        } else {
            // تخزين الأسئلة غير الإحصائية لعرضها
            $nonStatisticalQuestions[$q['id']] = [
                'question_number' => $q['question_number'],
                'question_text' => $lang === 'en' ? $q['question_text_en'] : $q['question_text_ar'],
                'section_id' => isset($q['section_id']) ? intval($q['section_id']) : 0,
                'section_number' => isset($q['section_number']) ? intval($q['section_number']) : 0,
                'question_type' => $qType,
                'options' => isset($q['options']) ? $q['options'] : [],
                'display_order' => isset($q['display_order']) ? intval($q['display_order']) : 0,
                'is_reverse' => 0
            ];
        }
    }
    
    // تجميع الإجابات لأسئلة likert فقط
    foreach ($responses as $response) {
        $questionId = $response['question_id'];
        $userId = $response['user_id'];
        $answerValue = $response['answer_value'];
        
        // فقط أسئلة likert
        if (isset($questionScores[$questionId])) {
            // ===== تطبيق النتيجة العكسية (Reverse Coded) =====
            if ($questionScores[$questionId]['is_reverse'] == 1 && $answerValue !== null) {
                $answerValue = reverseLikertValue($answerValue);
            }
            
            if ($answerValue !== null) {
                $questionScores[$questionId]['values'][] = $answerValue;
                $questionScores[$questionId]['answers'][] = $response;
                $allAnswers[] = $answerValue;
            }
            
            // تجميع scores لكل مستخدم
            if (!isset($userScores[$userId])) {
                $userScores[$userId] = [
                    'username' => $response['username'],
                    'answers' => [],
                    'total' => 0,
                    'count' => 0
                ];
            }
            if ($answerValue !== null) {
                $userScores[$userId]['answers'][] = $answerValue;
                $userScores[$userId]['total'] += $answerValue;
                $userScores[$userId]['count']++;
            }
        }
    }
    
    // ===== حساب الإحصائيات لكل سؤال (likert only) =====
    $statistics = [];
    foreach ($questionScores as $qId => $data) {
        $values = $data['values'];
        $n = count($values);
        
        if ($n > 0) {
            // 1. Mean
            $mean = array_sum($values) / $n;
            
            // 2. Standard Deviation
            $variance = array_sum(array_map(function($x) use ($mean) {
                return pow($x - $mean, 2);
            }, $values)) / $n;
            $stdDev = sqrt($variance);
            
            // 3. Weighted Average
            $weightedAvg = $mean;
            
            // 4. Median
            sort($values);
            $median = ($n % 2 == 0) ? 
                ($values[$n/2 - 1] + $values[$n/2]) / 2 : 
                $values[floor($n/2)];
            
            // 5. Min / Max / Range
            $min = min($values);
            $max = max($values);
            $range = $max - $min;
            
            // 6. Coefficient of Variation
            $cv = ($mean != 0) ? ($stdDev / $mean) * 100 : 0;
            
            // 7. Z-Score لكل قيمة
            $zScores = array_map(function($x) use ($mean, $stdDev) {
                return ($stdDev != 0) ? ($x - $mean) / $stdDev : 0;
            }, $values);
            
            // 8. Response Rate
            $totalParticipants = $poll['participant_count'];
            $responseRate = ($totalParticipants > 0) ? ($n / $totalParticipants) * 100 : 0;
            
            // 9. توزيع الإجابات مع النسب المئوية
            $distribution = array_count_values($values);
            for ($i = 1; $i <= 5; $i++) {
                if (!isset($distribution[$i])) {
                    $distribution[$i] = 0;
                }
            }
            ksort($distribution);
            
            $distributionPercentages = [];
            foreach ($distribution as $key => $count) {
                $distributionPercentages[$key] = ($n > 0) ? round(($count / $n) * 100, 1) : 0;
            }
            
            $statistics[$qId] = [
                'question_number' => $data['question_number'],
                'question_text' => $data['question_text'],
                'n' => $n,
                'mean' => round($mean, 2),
                'std_dev' => round($stdDev, 2),
                'weighted_avg' => round($weightedAvg, 2),
                'median' => round($median, 2),
                'min' => $min,
                'max' => $max,
                'range' => $range,
                'cv' => round($cv, 2),
                'response_rate' => round($responseRate, 2),
                'distribution' => $distribution,
                'distribution_percentages' => $distributionPercentages,
                'z_scores' => array_map(function($z) { return round($z, 2); }, $zScores),
                'is_reverse' => $data['is_reverse'],
                'section_id' => $data['section_id'],
                'section_number' => $data['section_number'],
                'question_type' => $data['question_type'],
                'display_order' => $data['display_order']
            ];
        } else {
            $statistics[$qId] = [
                'question_number' => $data['question_number'],
                'question_text' => $data['question_text'],
                'n' => 0,
                'mean' => null,
                'std_dev' => null,
                'weighted_avg' => null,
                'median' => null,
                'min' => null,
                'max' => null,
                'range' => null,
                'cv' => null,
                'response_rate' => 0,
                'distribution' => [1=>0, 2=>0, 3=>0, 4=>0, 5=>0],
                'distribution_percentages' => [1=>0, 2=>0, 3=>0, 4=>0, 5=>0],
                'z_scores' => [],
                'is_reverse' => $data['is_reverse'],
                'section_id' => $data['section_id'],
                'section_number' => $data['section_number'],
                'question_type' => $data['question_type'],
                'display_order' => $data['display_order']
            ];
        }
    }
    
    // ===== حساب Cronbach's Alpha (لأسئلة likert فقط) =====
    $responsesMatrix = [];
    foreach ($questionScores as $qId => $data) {
        if (!empty($data['values'])) {
            $responsesMatrix[] = $data['values'];
        }
    }
    $cronbachAlpha = calculateCronbachAlpha($responsesMatrix);
    
    // ===== حساب إحصائيات عامة =====
    $totalResponses = count($allAnswers);
    $overallMean = ($totalResponses > 0) ? array_sum($allAnswers) / $totalResponses : null;
    $overallStdDev = ($totalResponses > 0) ? 
        sqrt(array_sum(array_map(function($x) use ($overallMean) {
            return pow($x - $overallMean, 2);
        }, $allAnswers)) / $totalResponses) : null;
    
    $recommendedSampleSize = calculateSampleSize($poll['participant_count']);
    
    $marginOfError = ($totalResponses > 0 && $overallMean !== null) ? 
        1.96 * ($overallStdDev / sqrt($totalResponses)) : null;
    
    $confidenceInterval = ($marginOfError !== null && $overallMean !== null) ? [
        'lower' => $overallMean - $marginOfError,
        'upper' => $overallMean + $marginOfError
    ] : null;
    
    $correlation = null;
    $questionIds = array_keys($questionScores);
    if (count($questionIds) >= 2) {
        $firstQ = $questionIds[0];
        $lastQ = $questionIds[count($questionIds) - 1];
        if (!empty($questionScores[$firstQ]['values']) && !empty($questionScores[$lastQ]['values'])) {
            $minCount = min(count($questionScores[$firstQ]['values']), count($questionScores[$lastQ]['values']));
            $x = array_slice($questionScores[$firstQ]['values'], 0, $minCount);
            $y = array_slice($questionScores[$lastQ]['values'], 0, $minCount);
            $correlation = calculatePearsonCorrelation($x, $y);
        }
    }
    
    // تنظيم الأسئلة حسب المحاور
    $questionsBySection = [];
    foreach ($questions as $q) {
        $sectionId = isset($q['section_id']) ? intval($q['section_id']) : 0;
        if ($sectionId > 0) {
            if (!isset($questionsBySection[$sectionId])) {
                $questionsBySection[$sectionId] = [];
            }
            $questionsBySection[$sectionId][] = $q['id'];
        }
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// ============================================================
// ===== بدء عرض HTML =====
// ============================================================

$labels = $lang === 'en' 
    ? ['Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree']
    : ['أعارض بشدة', 'أعارض', 'محايد', 'أوافق', 'أوافق بشدة'];

$currentSectionId = 0;
$sectionQuestionCounter = 0;

// دمج جميع الأسئلة
$allQuestions = [];
foreach ($questions as $q) {
    $qId = $q['id'];
    $qType = isset($q['question_type']) ? $q['question_type'] : 'likert';
    $sectionId = isset($q['section_id']) ? intval($q['section_id']) : 0;
    $displayOrder = isset($q['display_order']) ? intval($q['display_order']) : 0;
    $isReverse = isset($q['is_reverse']) ? intval($q['is_reverse']) : 0;
    
    if ($qType === 'likert') {
        $stat = isset($statistics[$qId]) ? $statistics[$qId] : null;
        if ($stat) {
            $allQuestions[] = [
                'type' => 'statistical',
                'data' => $stat,
                'section_id' => $sectionId,
                'question_number' => $q['question_number'],
                'display_order' => $displayOrder,
                'is_reverse' => $isReverse
            ];
        }
    } else {
        $nonStat = isset($nonStatisticalQuestions[$qId]) ? $nonStatisticalQuestions[$qId] : null;
        if ($nonStat) {
            $allQuestions[] = [
                'type' => 'non-statistical',
                'data' => $nonStat,
                'section_id' => $sectionId,
                'question_number' => $q['question_number'],
                'question_id' => $qId,
                'display_order' => $displayOrder
            ];
        }
    }
}

usort($allQuestions, function($a, $b) {
    if ($a['section_id'] != $b['section_id']) {
        return $a['section_id'] - $b['section_id'];
    }
    return $a['display_order'] - $b['display_order'];
});
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $isRtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4A90E2">
    <title><?php echo $lang === 'en' ? 'Poll Results' : 'نتائج الاستبيان'; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            background: var(--bg-light);
            padding: 20px;
            min-height: 100vh;
        }

        .language-selector {
            position: fixed;
            top: 1px;
            <?php echo $isRtl ? 'left' : 'right'; ?>: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 1px;
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

        .container-custom { max-width: 1400px; margin: 0 auto; }

        .poll-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border-radius: var(--border-radius);
            padding: 30px 35px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(74, 144, 226, 0.3);
        }

        .poll-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 10px; }

        .poll-header .poll-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 14px;
            opacity: 0.9;
        }

        .poll-header .poll-meta i { margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 6px; }
        .poll-header .poll-meta span { background: rgba(255,255,255,0.15); padding: 4px 14px; border-radius: 20px; }

        .btn-print {
            background: #34495e;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-print:hover { background: #2c3e50; color: white; transform: translateY(-2px); }
        .btn-print i { margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 8px; }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px 25px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .summary-card:hover { transform: translateY(-5px); }
        .summary-card .number { font-size: 32px; font-weight: 700; color: var(--primary-color); }
        .summary-card .label { font-size: 13px; color: var(--text-light); margin-top: 5px; }
        .summary-card .icon { font-size: 24px; color: var(--primary-light); margin-bottom: 8px; }

        .stat-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: var(--card-shadow);
        }

        .stat-section .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-section .section-title i { color: var(--primary-color); }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .stat-item {
            background: var(--bg-light);
            padding: 15px 20px;
            border-radius: 10px;
        }

        .stat-item .stat-label { font-size: 12px; color: var(--text-light); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .stat-item .stat-value { font-size: 22px; font-weight: 700; color: var(--text-dark); margin-top: 4px; }
        .stat-item .stat-value .small { font-size: 14px; font-weight: 400; color: var(--text-light); }

        .section-header-box {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin: 30px 0 20px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .section-header-box .section-title-text {
            font-weight: 700;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header-box .section-title-text i {
            font-size: 24px;
            opacity: 0.8;
        }

        .section-header-box .section-desc {
            font-size: 14px;
            opacity: 0.8;
            margin-top: 5px;
            padding-<?php echo $isRtl ? 'right' : 'left'; ?>: 36px;
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
            margin-left: 8px;
        }

        .question-result {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: var(--card-shadow);
            border-<?php echo $isRtl ? 'right' : 'left'; ?>: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .question-result:hover { box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1); }

        .question-result .q-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }

        .question-result .q-number { font-weight: 700; color: var(--primary-color); font-size: 14px; }
        .question-result .q-text { font-weight: 600; color: var(--text-dark); font-size: 16px; flex: 1; }
        .question-result .q-mean { font-size: 24px; font-weight: 700; color: var(--primary-color); white-space: nowrap; }
        .question-result .q-mean .label { font-size: 13px; font-weight: 400; color: var(--text-light); }
        .question-result .q-type-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            background: #e8daef;
            color: #6c3483;
            margin-bottom: 5px;
        }
        .question-result .q-type-badge.reverse {
            background: #fff3cd;
            color: #856404;
        }

        .question-result .q-distribution {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .question-result .q-distribution .bar-wrapper {
            flex: 1;
            min-width: 60px;
            text-align: center;
        }

        .question-result .q-distribution .bar {
            height: 30px;
            border-radius: 4px;
            transition: height 0.5s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
        }

        .question-result .q-distribution .bar .bar-value {
            font-size: 11px;
            font-weight: 700;
            color: white;
            text-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .question-result .q-distribution .bar-label {
            margin-top: 4px;
            font-size: 10px;
            color: var(--text-light);
            font-weight: 600;
        }

        .question-result .q-distribution .bar-percentage {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-dark);
            margin-top: 2px;
        }

        .question-result .q-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
            font-size: 13px;
            color: var(--text-light);
        }

        .question-result .q-details span { display: inline-flex; align-items: center; gap: 4px; }
        .question-result .q-details i { color: var(--primary-color); }

        .non-stat-question {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px 25px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
            border-<?php echo $isRtl ? 'right' : 'left'; ?>: 4px solid #f39c12;
        }

        .non-stat-question .q-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }

        .non-stat-question .q-number { font-weight: 700; color: #f39c12; font-size: 14px; }
        .non-stat-question .q-text { font-weight: 600; color: var(--text-dark); font-size: 16px; flex: 1; }
        .non-stat-question .q-type-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            background: #fff3cd;
            color: #856404;
        }

        .non-stat-question .q-response {
            margin-top: 10px;
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .non-stat-question .q-response .response-item {
            padding: 4px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: var(--text-dark);
        }

        .non-stat-question .q-response .response-item:last-child {
            border-bottom: none;
        }

        .non-stat-question .q-response .response-item .user-label {
            font-weight: 600;
            color: var(--text-light);
            font-size: 12px;
        }

        .section-notes-container {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px 25px;
            margin-top: 20px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }

        .section-notes-container .notes-title {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-notes-container .notes-title i { color: var(--warning-color); }

        .section-notes-container .note-item {
            background: white;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            border-<?php echo $isRtl ? 'right' : 'left'; ?>: 3px solid var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .section-notes-container .note-item:last-child { margin-bottom: 0; }

        .section-notes-container .note-item .note-author {
            font-size: 12px;
            color: var(--text-light);
            font-weight: 600;
        }

        .section-notes-container .note-item .note-author i { margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 4px; }

        .section-notes-container .note-item .note-text {
            font-size: 14px;
            color: var(--text-dark);
            margin-top: 4px;
            line-height: 1.6;
        }

        .section-notes-container .no-notes {
            color: var(--text-light);
            font-size: 14px;
            text-align: center;
            padding: 20px;
        }

        .section-notes-container .no-notes i {
            font-size: 24px;
            display: block;
            margin-bottom: 8px;
            opacity: 0.5;
        }

        .bar-color-1 { background: #e74c3c; }
        .bar-color-2 { background: #f39c12; }
        .bar-color-3 { background: #f1c40f; }
        .bar-color-4 { background: #2ecc71; }
        .bar-color-5 { background: #27ae60; }

        .poll-type-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .poll-type-badge.regular { background: #d4edda; color: #155724; }
        .poll-type-badge.admin { background: #f8d7da; color: #721c24; }
        .poll-type-badge.personal { background: #cce5ff; color: #004085; }
        .poll-type-badge.executive { background: #e8daef; color: #6c3483; }

        @media (max-width: 768px) {
            body { padding: 10px; }
            .poll-header { padding: 20px; }
            .poll-header h1 { font-size: 22px; }
            .poll-header .poll-meta { font-size: 12px; gap: 10px; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .summary-card { padding: 15px; }
            .summary-card .number { font-size: 24px; }
            .stat-section { padding: 20px; }
            .stat-row { grid-template-columns: 1fr 1fr; }
            .question-result { padding: 15px; }
            .question-result .q-text { font-size: 14px; }
            .question-result .q-mean { font-size: 20px; }
            .question-result .q-distribution .bar { height: 25px; min-height: 25px; }
            .question-result .q-distribution .bar-wrapper { min-width: 40px; }
            .question-result .q-details { font-size: 12px; gap: 10px; }
            .non-stat-question { padding: 15px; }
            .non-stat-question .q-text { font-size: 14px; }
            .language-selector { top: 10px; <?php echo $isRtl ? 'left' : 'right'; ?>: 10px; padding: 5px; }
            .language-selector button { padding: 5px 10px; font-size: 11px; }
            .section-notes-container { padding: 15px; }
            .section-notes-container .notes-title { font-size: 16px; }
            .section-header-box .section-title-text { font-size: 17px; }
            .section-header-box .section-desc { font-size: 13px; padding-<?php echo $isRtl ? 'right' : 'left'; ?>: 0; }
        }

        @media print {
            .btn-print, .no-print, .language-selector { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .poll-header { background: #4A90E2 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .question-result { break-inside: avoid; page-break-inside: avoid; }
            .non-stat-question { break-inside: avoid; page-break-inside: avoid; }
            .summary-card, .stat-section, .section-notes-container { break-inside: avoid; }
        }

        @media (prefers-color-scheme: dark) {
            body { background: #1a2332; }
            .summary-card { background: #1a2332; border: 1px solid rgba(255,255,255,0.05); }
            .summary-card .number { color: var(--primary-light); }
            .stat-section { background: #1a2332; border: 1px solid rgba(255,255,255,0.05); }
            .stat-section .section-title { color: #ECF0F1; }
            .stat-item { background: rgba(255,255,255,0.05); }
            .stat-item .stat-value { color: #ECF0F1; }
            .question-result { background: #1a2332; border-color: var(--primary-color); }
            .question-result .q-text { color: #ECF0F1; }
            .question-result .q-details { border-top-color: rgba(255,255,255,0.05); color: rgba(255,255,255,0.6); }
            .question-result .q-distribution .bar-label { color: rgba(255,255,255,0.6); }
            .question-result .q-distribution .bar-percentage { color: #ECF0F1; }
            .non-stat-question { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
            .non-stat-question .q-text { color: #ECF0F1; }
            .non-stat-question .q-response { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
            .non-stat-question .q-response .response-item { color: #ECF0F1; border-bottom-color: rgba(255,255,255,0.05); }
            .non-stat-question .q-response .response-item .user-label { color: #A0A8B4; }
            .btn-print { background: #2c3e50; }
            .language-selector { background: rgba(30, 30, 40, 0.95); }
            .language-selector button { color: #A0A8B4; }
            .language-selector button:hover:not(.active) { background: rgba(255, 255, 255, 0.05); }
            .language-selector button.active { background: var(--primary-color); color: white; }
            .section-notes-container { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
            .section-notes-container .notes-title { color: #ECF0F1; }
            .section-notes-container .note-item { background: rgba(255,255,255,0.05); border-color: var(--primary-color); }
            .section-notes-container .note-item .note-text { color: #ECF0F1; }
            .section-notes-container .note-item .note-author { color: #A0A8B4; }
            .section-notes-container .no-notes { color: #A0A8B4; }
            .section-header-box { background: linear-gradient(135deg, #1a2332, #2c3e50); border: 1px solid rgba(255,255,255,0.1); }
            .poll-type-badge.regular { background: rgba(40,167,69,0.2); color: #75b798; }
            .poll-type-badge.admin { background: rgba(220,53,69,0.2); color: #ea868f; }
            .poll-type-badge.personal { background: rgba(0,123,255,0.2); color: #6ea8fe; }
            .poll-type-badge.executive { background: rgba(128,0,128,0.2); color: #c77dff; }
            .reverse-badge { background: rgba(128,0,128,0.2); color: #c77dff; }
        }
    </style>
</head>
<body>

<div class="language-selector">
    <button class="<?php echo $lang === 'ar' ? 'active' : ''; ?>" onclick="switchLanguage('ar')">
        <i class="fas fa-flag"></i> ع
    </button>
    <button class="<?php echo $lang === 'en' ? 'active' : ''; ?>" onclick="switchLanguage('en')">
        <i class="fas fa-flag"></i> E
    </button>
</div>

<div class="container-custom">
    <div class="poll-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="mt-3">
                    <i class="fas fa-poll"></i>
                    <?php echo $lang === 'en' ? 'Poll Results' : 'نتائج الاستبيان'; ?>
                </h1>
            </div>
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i>
                <?php echo $lang === 'en' ? 'Print Results' : 'طباعة النتائج'; ?>
            </button>
        </div>
        
        <div class="poll-meta mt-3">
            <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($poll['company_name']); ?></span>
            <span><i class="fas fa-calendar"></i> <?php echo $poll['date']; ?></span>
            <span><i class="fas fa-tag"></i> 
                <span class="poll-type-badge <?php echo $poll['type']; ?>">
                    <?php echo getPollTypeLabel($poll['type'], $lang); ?>
                </span>
            </span>
            <span><i class="fas fa-users"></i> <?php echo $poll['participant_count']; ?> <?php echo $lang === 'en' ? 'participants' : 'مشارك'; ?></span>
            <?php if ($poll['departments']): ?>
            <span><i class="fas fa-sitemap"></i> <?php echo htmlspecialchars($poll['departments']); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="icon"><i class="fas fa-users"></i></div>
            <div class="number"><?php echo count($userScores); ?></div>
            <div class="label"><?php echo $lang === 'en' ? 'Respondents' : 'المشاركون'; ?></div>
        </div>
        <div class="summary-card">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <div class="number"><?php echo $totalResponses; ?></div>
            <div class="label"><?php echo $lang === 'en' ? 'Total Responses (Likert)' : 'إجمالي الردود (ليكرت)'; ?></div>
        </div>
        <div class="summary-card">
            <div class="icon"><i class="fas fa-star"></i></div>
            <div class="number"><?php echo $overallMean !== null ? number_format($overallMean, 2) : 'N/A'; ?></div>
            <div class="label"><?php echo $lang === 'en' ? 'Overall Average' : 'المتوسط العام'; ?></div>
        </div>
        <div class="summary-card">
            <div class="icon"><i class="fas fa-chart-line"></i></div>
            <div class="number"><?php echo $cronbachAlpha !== null ? number_format($cronbachAlpha, 3) : 'N/A'; ?></div>
            <div class="label"><?php echo $lang === 'en' ? 'Cronbach\'s Alpha' : 'ألفا كرونباخ'; ?></div>
        </div>
    </div>

    <div class="stat-section">
        <div class="section-title">
            <i class="fas fa-chart-bar"></i>
            <?php echo $lang === 'en' ? 'Statistical Summary (Likert Questions)' : 'ملخص إحصائي (أسئلة ليكرت)'; ?>
        </div>
        <div class="stat-row">
            <div class="stat-item">
                <div class="stat-label"><?php echo $lang === 'en' ? 'Likert Questions' : 'أسئلة ليكرت'; ?></div>
                <div class="stat-value"><?php echo count($questionScores); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label"><?php echo $lang === 'en' ? 'Valid Responses' : 'الردود الصالحة'; ?></div>
                <div class="stat-value"><?php echo $totalResponses; ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label"><?php echo $lang === 'en' ? 'Standard Deviation' : 'الانحراف المعياري'; ?></div>
                <div class="stat-value"><?php echo $overallStdDev !== null ? number_format($overallStdDev, 3) : 'N/A'; ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label"><?php echo $lang === 'en' ? 'Margin of Error' : 'هامش الخطأ'; ?></div>
                <div class="stat-value">±<?php echo $marginOfError !== null ? number_format($marginOfError, 3) : 'N/A'; ?></div>
            </div>
            <?php if ($recommendedSampleSize): ?>
            <div class="stat-item">
                <div class="stat-label"><?php echo $lang === 'en' ? 'Recommended Sample Size' : 'حجم العينة الموصى به'; ?></div>
                <div class="stat-value"><?php echo $recommendedSampleSize; ?></div>
            </div>
            <?php endif; ?>
            <?php if ($confidenceInterval): ?>
            <div class="stat-item">
                <div class="stat-label"><?php echo $lang === 'en' ? '95% Confidence Interval' : 'فاصل الثقة 95%'; ?></div>
                <div class="stat-value">
                    <?php echo number_format($confidenceInterval['lower'], 2); ?> - <?php echo number_format($confidenceInterval['upper'], 2); ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($correlation !== null): ?>
            <div class="stat-item">
                <div class="stat-label"><?php echo $lang === 'en' ? 'Pearson Correlation (Q1 vs QN)' : 'معامل الارتباط (س1 vs سN)'; ?></div>
                <div class="stat-value"><?php echo number_format($correlation, 3); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-section">
        <div class="section-title">
            <i class="fas fa-list"></i>
            <?php echo $lang === 'en' ? 'All Questions Results' : 'نتائج جميع الأسئلة'; ?>
        </div>

        <?php foreach ($allQuestions as $index => $qItem):
            $sectionId = $qItem['section_id'];
            
            if ($sectionId > 0 && $sectionId != $currentSectionId) {
                if ($currentSectionId > 0 && isset($notesBySection[$currentSectionId])) {
                    $sectionTitle = getSectionTitleById($currentSectionId, $sections, $lang);
        ?>
        <div class="section-notes-container">
            <div class="notes-title">
                <i class="fas fa-comment-dots"></i>
                <?php echo $lang === 'en' ? 'Notes on ' : 'ملاحظات على '; ?>
                <?php echo $sectionTitle; ?>
            </div>
            <?php if (!empty($notesBySection[$currentSectionId])): ?>
                <?php foreach ($notesBySection[$currentSectionId] as $note): ?>
                <div class="note-item">
                    <div class="note-text">
                        <?php echo nl2br(htmlspecialchars($note['note_text'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-notes">
                    <i class="fas fa-comment-slash"></i>
                    <?php echo $lang === 'en' ? 'No notes added for this section.' : 'لا توجد ملاحظات مضافة لهذا المحور.'; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
                }
                
                $currentSectionId = $sectionId;
                $sectionTitle = getSectionTitleById($sectionId, $sections, $lang);
                $sectionDesc = getSectionDescription($sectionId, $sections, $lang);
                $sectionQuestionCounter = 0;
        ?>
        <div class="section-header-box">
            <div class="section-title-text">
                <i class="fas fa-layer-group"></i>
                <?php echo htmlspecialchars($sectionTitle); ?>
                <span class="badge bg-light text-dark ms-2">
                    <?php 
                    $count = 0;
                    foreach ($allQuestions as $q) {
                        if ($q['section_id'] == $sectionId) $count++;
                    }
                    echo $count;
                    ?> 
                    <?php echo $lang === 'en' ? 'questions' : 'سؤال'; ?>
                </span>
            </div>
            <?php if (!empty($sectionDesc)): ?>
            <div class="section-desc"><?php echo htmlspecialchars($sectionDesc); ?></div>
            <?php endif; ?>
        </div>
        <?php
            }
            
            $sectionQuestionCounter++;
            
            if ($qItem['type'] === 'statistical'):
                $stat = $qItem['data'];
                $distribution = $stat['distribution'];
                $distributionPercentages = $stat['distribution_percentages'];
                $maxCount = max($distribution) ?: 1;
                $isReverse = isset($qItem['is_reverse']) ? $qItem['is_reverse'] : 0;
        ?>
        <div class="question-result">
            <div class="q-header">
                <div>
                    <div class="q-number">
                        <?php echo $lang === 'en' ? 'Question' : 'سؤال'; ?> 
                        <?php echo $sectionQuestionCounter; ?>
                        <span class="text-muted small">(#<?php echo $stat['question_number']; ?>)</span>
                    </div>
                    <span class="q-type-badge <?php echo $isReverse ? 'reverse' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> <?php echo getQuestionTypeLabel('likert', $lang); ?>
                        <?php if ($isReverse): ?>
                        <span class="reverse-badge"><i class="fas fa-exchange-alt"></i> <?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></span>
                        <?php endif; ?>
                    </span>
                    <div class="q-text"><?php echo $stat['question_text']; ?></div>
                </div>
                <div class="q-mean">
                    <span class="label"><?php echo $lang === 'en' ? 'Mean' : 'المتوسط'; ?>: </span>
                    <?php echo $stat['mean'] !== null ? number_format($stat['mean'], 2) : 'N/A'; ?>
                </div>
            </div>

            <div class="q-distribution">
                <?php for ($i = 1; $i <= 5; $i++): 
                    $count = isset($distribution[$i]) ? $distribution[$i] : 0;
                    $percentage = isset($distributionPercentages[$i]) ? $distributionPercentages[$i] : 0;
                ?>
                <div class="bar-wrapper">
                    <div class="bar bar-color-<?php echo $i; ?>" style="height: <?php echo max(20, ($count / $maxCount) * 30); ?>px; min-height: 20px;">
                        <?php if ($count > 0): ?>
                        <span class="bar-value"><?php echo $count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bar-percentage"><?php echo $percentage; ?>%</div>
                    <div class="bar-label"><?php echo $labels[$i-1]; ?></div>
                </div>
                <?php endfor; ?>
            </div>

            <div class="q-details">
                <span><i class="fas fa-users"></i> <?php echo $stat['n']; ?> <?php echo $lang === 'en' ? 'responses' : 'رد'; ?></span>
                <span><i class="fas fa-arrows-alt-h"></i> <?php echo $lang === 'en' ? 'Range' : 'المدى'; ?>: <?php echo $stat['range'] !== null ? $stat['range'] : 'N/A'; ?></span>
                <span><i class="fas fa-arrow-up"></i> <?php echo $lang === 'en' ? 'Max' : 'الحد الأقصى'; ?>: <?php echo $stat['max'] !== null ? $stat['max'] : 'N/A'; ?></span>
                <span><i class="fas fa-arrow-down"></i> <?php echo $lang === 'en' ? 'Min' : 'الحد الأدنى'; ?>: <?php echo $stat['min'] !== null ? $stat['min'] : 'N/A'; ?></span>
                <span><i class="fas fa-arrow-right"></i> <?php echo $lang === 'en' ? 'Median' : 'الوسيط'; ?>: <?php echo $stat['median'] !== null ? number_format($stat['median'], 2) : 'N/A'; ?></span>
                <span><i class="fas fa-chart-line"></i> <?php echo $lang === 'en' ? 'Std Dev' : 'الانحراف المعياري'; ?>: <?php echo $stat['std_dev'] !== null ? number_format($stat['std_dev'], 3) : 'N/A'; ?></span>
                <span><i class="fas fa-percent"></i> <?php echo $lang === 'en' ? 'CV' : 'معامل التباين'; ?>: <?php echo $stat['cv'] !== null ? number_format($stat['cv'], 2) . '%' : 'N/A'; ?></span>
                <?php if ($isReverse): ?>
                <span class="badge bg-warning text-dark"><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></span>
                <?php endif; ?>
                <?php if ($stat['response_rate'] > 0): ?>
                <span><i class="fas fa-check-circle"></i> <?php echo $lang === 'en' ? 'Response Rate' : 'نسبة الاستجابة'; ?>: <?php echo number_format($stat['response_rate'], 1); ?>%</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: 
            $nonStat = $qItem['data'];
            $qId = $qItem['question_id'];
            $qType = $nonStat['question_type'];
            $options = isset($nonStat['options']) ? $nonStat['options'] : [];
        ?>
        <div class="non-stat-question">
            <div class="q-header">
                <div>
                    <div class="q-number">
                        <?php echo $lang === 'en' ? 'Question' : 'سؤال'; ?> 
                        <?php echo $sectionQuestionCounter; ?>
                        <span class="text-muted small">(#<?php echo $nonStat['question_number']; ?>)</span>
                    </div>
                    <span class="q-type-badge"><i class="fas fa-info-circle"></i> <?php echo getQuestionTypeLabel($qType, $lang); ?></span>
                    <div class="q-text"><?php echo $nonStat['question_text']; ?></div>
                </div>
            </div>
            
            <div class="q-response">
                <?php if ($qType === 'text'): 
                    $texts = array_filter($textResponses, function($t) use ($qId) {
                        return $t['question_id'] == $qId;
                    });
                    if (!empty($texts)): 
                ?>
                    <?php foreach ($texts as $text): ?>
                    <div class="response-item">
                        <span class="user-label"><i class="fas fa-user"></i> <?php echo htmlspecialchars($text['user_id']); ?>:</span>
                        <?php echo nl2br(htmlspecialchars($text['response_text'])); ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="response-item text-muted"><?php echo $lang === 'en' ? 'No text responses yet.' : 'لا توجد إجابات نصية بعد.'; ?></div>
                <?php endif; ?>
                
                <?php elseif ($qType === 'choice'):
                    $choices = array_filter($choiceResponses, function($c) use ($qId) {
                        return $c['question_id'] == $qId;
                    });
                    if (!empty($choices)):
                        $choiceDistribution = [];
                        foreach ($choices as $choice) {
                            $optId = $choice['option_id'];
                            if (!isset($choiceDistribution[$optId])) {
                                $choiceDistribution[$optId] = 0;
                            }
                            $choiceDistribution[$optId]++;
                        }
                ?>
                    <div class="row">
                        <?php foreach ($options as $opt): 
                            $count = isset($choiceDistribution[$opt['id']]) ? $choiceDistribution[$opt['id']] : 0;
                            $total = count($choices);
                            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                        ?>
                        <div class="col-md-6 col-lg-4 mb-2">
                            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                <span><?php echo getOptionText($opt, $lang); ?></span>
                                <span class="badge bg-primary"><?php echo $count; ?> (<?php echo $percentage; ?>%)</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="response-item text-muted"><?php echo $lang === 'en' ? 'No choice responses yet.' : 'لا توجد إجابات اختيار بعد.'; ?></div>
                <?php endif; ?>
                
                <?php elseif ($qType === 'ranking'):
                    $rankings = array_filter($rankingResponses, function($r) use ($qId) {
                        return $r['question_id'] == $qId;
                    });
                    if (!empty($rankings)):
                        $userRankings = [];
                        foreach ($rankings as $rank) {
                            $userId = $rank['user_id'];
                            if (!isset($userRankings[$userId])) {
                                $userRankings[$userId] = [];
                            }
                            $userRankings[$userId][] = $rank;
                        }
                ?>
                    <?php foreach ($userRankings as $userId => $ranks): 
                        usort($ranks, function($a, $b) {
                            return $a['rank_order'] - $b['rank_order'];
                        });
                    ?>
                    <div class="response-item">
                        <span class="user-label"><i class="fas fa-user"></i> <?php echo htmlspecialchars($userId); ?>:</span>
                        <?php 
                        $rankTexts = [];
                        foreach ($ranks as $rank) {
                            $rankTexts[] = '<span class="badge bg-secondary me-1">' . $rank['rank_order'] . '</span> ' . getOptionText($rank, $lang);
                        }
                        echo implode(' → ', $rankTexts);
                        ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="response-item text-muted"><?php echo $lang === 'en' ? 'No ranking responses yet.' : 'لا توجد إجابات ترتيب بعد.'; ?></div>
                <?php endif; ?>
                
                <?php elseif ($qType === 'numeric'):
                    $numerics = array_filter($numericResponses, function($n) use ($qId) {
                        return $n['question_id'] == $qId;
                    });
                    if (!empty($numerics)):
                        $numericValues = array_column($numerics, 'numeric_value');
                        $avg = array_sum($numericValues) / count($numericValues);
                        $min = min($numericValues);
                        $max = max($numericValues);
                ?>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="small text-muted"><?php echo $lang === 'en' ? 'Average' : 'المتوسط'; ?></div>
                                <div class="h5 mb-0"><?php echo number_format($avg, 1); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="small text-muted"><?php echo $lang === 'en' ? 'Min' : 'الحد الأدنى'; ?></div>
                                <div class="h5 mb-0"><?php echo $min; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="small text-muted"><?php echo $lang === 'en' ? 'Max' : 'الحد الأقصى'; ?></div>
                                <div class="h5 mb-0"><?php echo $max; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <?php foreach ($numerics as $num): ?>
                        <span class="badge bg-info me-1"><?php echo $num['numeric_value']; ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="response-item text-muted"><?php echo $lang === 'en' ? 'No numeric responses yet.' : 'لا توجد إجابات رقمية بعد.'; ?></div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if ($currentSectionId > 0 && isset($notesBySection[$currentSectionId])): 
            $sectionTitle = getSectionTitleById($currentSectionId, $sections, $lang);
        ?>
        <div class="section-notes-container">
            <div class="notes-title">
                <i class="fas fa-comment-dots"></i>
                <?php echo $lang === 'en' ? 'Notes on ' : 'ملاحظات على '; ?>
                <?php echo $sectionTitle; ?>
            </div>
            <?php if (!empty($notesBySection[$currentSectionId])): ?>
                <?php foreach ($notesBySection[$currentSectionId] as $note): ?>
                <div class="note-item">
                    <div class="note-text">
                        <?php echo nl2br(htmlspecialchars($note['note_text'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-notes">
                    <i class="fas fa-comment-slash"></i>
                    <?php echo $lang === 'en' ? 'No notes added for this section.' : 'لا توجد ملاحظات مضافة لهذا المحور.'; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function switchLanguage(lang) {
    // الحصول على معرف الاستبيان من عنوان URL الحالي
    var urlParams = new URLSearchParams(window.location.search);
    var pollId = urlParams.get('id');
    
    // إذا لم يكن هناك معرف، استخدم المعرف الموجود في الصفحة (من PHP)
    <?php if (isset($pollId) && $pollId > 0): ?>
    var pollId = <?php echo $pollId; ?>;
    <?php endif; ?>
    
    // إرسال الطلب مع الحفاظ على المعرف
    fetch('set_language.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'language=' + encodeURIComponent(lang)
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            // إعادة التوجيه مع الحفاظ على معرف الاستبيان
            window.location.href = 'poll.php?id=' + pollId;
        } else {
            window.location.href = 'poll.php?id=' + pollId + '&lang=' + lang;
        }
    })
    .catch(function() {
        window.location.href = 'poll.php?id=' + pollId + '&lang=' + lang;
    });
}
</script>

</body>
</html>