<?php
/**
 * ملف تبديل اللغة
 * set_language.php
 * يستخدم لتغيير لغة الموقع وتخزين التفضيل في الجلسة
 */

// تضمين ملف الإعدادات
require_once 'config.php';

// بدء الجلسة
startSession();

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // إذا كان الطلب GET، إعادة توجيه إلى الصفحة الرئيسية
    header('Location: index.php');
    exit;
}

// الحصول على اللغة من الطلب
$language = isset($_POST['language']) ? trim($_POST['language']) : '';
$language = strtolower($language);

// التحقق من صحة اللغة
$availableLanguages = AVAILABLE_LANGUAGES;
if (!in_array($language, $availableLanguages)) {
    // إذا كانت اللغة غير صالحة، استخدام اللغة الافتراضية
    $language = DEFAULT_LANGUAGE;
}

// تخزين اللغة في الجلسة
$_SESSION['language'] = $language;

// تخزين اللغة في كوكي لمدة سنة (اختياري)
setcookie(
    'user_language',
    $language,
    time() + (365 * 24 * 60 * 60), // سنة واحدة
    '/',
    '',
    isset($_SERVER['HTTPS']),
    true
);

// التحقق إذا كان الطلب AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    // إرجاع استجابة JSON للطلبات AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'language' => $language,
        'message' => 'Language updated successfully'
    ]);
    exit;
}

// إذا كان هناك صفحة مرجعية، العودة إليها
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    $referer = $_SERVER['HTTP_REFERER'];
    // منع إعادة التوجيه إلى مواقع خارجية
    $host = $_SERVER['HTTP_HOST'];
    if (strpos($referer, $host) !== false) {
        header('Location: ' . $referer);
        exit;
    }
}

// إذا لم يكن هناك صفحة مرجعية، التوجه إلى الصفحة الرئيسية
header('Location: index.php');
exit;
?>