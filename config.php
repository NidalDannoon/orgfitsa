<?php
/**
 * ملف الإعدادات الرئيسي للموقع
 * config.php
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');          // عنوان خادم قاعدة البيانات
define('DB_NAME', 'dorfitbase');           // اسم قاعدة البيانات
define('DB_USER', 'urgfitser');               // اسم مستخدم قاعدة البيانات
define('DB_PASS', 'T7n%9C3TF33pH4WcvXz0Mob5apjhkrVm2');             // كلمة مرور قاعدة البيانات
define('DB_CHARSET', 'utf8mb4');         // ترميز قاعدة البيانات

// إعدادات الموقع العامة
define('SITE_NAME', 'استبيانات تقييم ثقافة الشركات');
define('SITE_NAME_EN', 'Corporate Culture Survey Platform');
define('SITE_URL', 'https://orgfit.sa.com/'); // رابط الموقع
define('ADMIN_EMAIL', 'admin@orgfit.sa.com');
define('TIMEZONE', 'Asia/Riyadh');

// إعدادات الجلسات
define('SESSION_LIFETIME', 3600); // مدة الجلسة بالثواني (ساعة واحدة)
define('SESSION_NAME', 'survey_session');

// إعدادات الأمان
define('SALT', 'xK9mP2vL8nQ3wR5tY7uA1zC4eF6gH0jI2kM4oP6qR8sT0uV2wX4yZ6aB8cD0eF2gH4iJ6kL8mN0oP2qR4sT6uV8wX0yZ2aB4cD6eF8gH0iJ2kL4mN6oP8qR0sT2uV4wX6yZ8aB0cD');
define('ENCRYPTION_KEY', 'E92B1F69C8B50D2704B469950B94A6E890887A246DA6812DA15DE39F3B4890A6'); // مفتاح تشفير

// إعدادات الاستبيان
define('MIN_PARTICIPANTS', 4);
define('MAX_PARTICIPANTS', 999);
define('REGULAR_QUESTIONS_COUNT', 40);
define('ADMIN_QUESTIONS_COUNT', 18);

// أنواع الاستبيانات المدعومة
define('POLL_TYPES', ['regular', 'admin', 'personal', 'executive']);
define('POLL_TYPE_REGULAR', 'regular');
define('POLL_TYPE_ADMIN', 'admin');
define('POLL_TYPE_PERSONAL', 'personal');
define('POLL_TYPE_EXECUTIVE', 'executive');

// أسماء أنواع الاستبيانات للعرض
define('POLL_TYPE_LABELS_AR', [
    'regular' => 'عادي',
    'admin' => 'إداري',
    'personal' => 'شخصي',
    'executive' => 'تنفيذي'
]);

define('POLL_TYPE_LABELS_EN', [
    'regular' => 'Regular',
    'admin' => 'Admin',
    'personal' => 'Personal',
    'executive' => 'Executive'
]);

// إعدادات خيارات الإجابة (مقياس ليكرت)
define('LIKERT_OPTIONS', [
    1 => 'أعارض بشدة',
    2 => 'أعارض',
    3 => 'محايد',
    4 => 'أوافق',
    5 => 'أوافق بشدة'
]);

define('LIKERT_OPTIONS_EN', [
    1 => 'Strongly Disagree',
    2 => 'Disagree',
    3 => 'Neutral',
    4 => 'Agree',
    5 => 'Strongly Agree'
]);

// إعدادات الصلاحيات
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// إعدادات الملفات والمسارات
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('TEMP_PATH', __DIR__ . '/temp/');
define('LOG_PATH', __DIR__ . '/logs/');
define('IMAGES_PATH', __DIR__ . '/images/');
define('LOGO_FILENAME', 'logo.png');
define('LOGO_PATH', IMAGES_PATH . LOGO_FILENAME);

// إعدادات البريد الإلكتروني (للإشعارات)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_SECURE', 'tls');

// إعدادات التطوير
define('DEBUG_MODE', true); // تشغيل وضع التصحيح
define('DISPLAY_ERRORS', true); // عرض الأخطاء

// تحديد اللغة الافتراضية
define('DEFAULT_LANGUAGE', 'ar');
define('AVAILABLE_LANGUAGES', ['ar', 'en']);

// دالة الاتصال بقاعدة البيانات
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
        } else {
            die("حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة مرة أخرى.");
        }
    }
}

// دالة لبدء الجلسة
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        session_name(SESSION_NAME);
        session_start();
    }
}

// دالة للتحقق من صلاحية المستخدم
function checkAuth() {
    startSession();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    return true;
}

// دالة للتحقق من صلاحية المشرف
function isAdmin() {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN;
}

// دالة للتحقق من صلاحية المستخدم العادي
function isUser() {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === ROLE_USER;
}

// دالة لتسجيل الخروج
function logout() {
    startSession();
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// دالة للحصول على نص الخطأ
function getErrorMessage($code) {
    $messages = [
        'login_failed' => 'اسم المستخدم أو كلمة المرور غير صحيحة',
        'already_responded' => 'لقد قمت بإكمال الاستبيان شكراً لك',
        'poll_closed' => 'الاستبيان غير متاح حالياً',
        'access_denied' => 'ليس لديك صلاحية للوصول إلى هذه الصفحة',
        'invalid_poll' => 'الاستبيان غير صالح',
        'already_submitted' => 'تم إرسال الاستبيان مسبقاً'
    ];
    
    return isset($messages[$code]) ? $messages[$code] : 'حدث خطأ غير متوقع';
}

// دالة لتسجيل الأخطاء
function logError($message, $file = null, $line = null) {
    if (DEBUG_MODE) {
        $log = date('Y-m-d H:i:s') . ' - ';
        if ($file) $log .= "File: $file - ";
        if ($line) $log .= "Line: $line - ";
        $log .= $message . PHP_EOL;
        
        $logFile = LOG_PATH . 'error_' . date('Y-m-d') . '.log';
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0777, true);
        }
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}

// دالة لتنظيف المدخلات
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// دالة لإنشاء كلمة مرور عشوائية
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// دالة لإنشاء اسم مستخدم عشوائي
function generateRandomUsername($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $username = '';
    for ($i = 0; $i < $length; $i++) {
        $username .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'user_' . $username;
}

// دالة لتشفير كلمة المرور
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// دالة للتحقق من كلمة المرور
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// دالة للحصول على اللغة المفضلة
function getPreferredLanguage() {
    startSession();
    if (isset($_SESSION['language']) && in_array($_SESSION['language'], AVAILABLE_LANGUAGES)) {
        return $_SESSION['language'];
    }
    
    // التحقق من لغة المتصفح
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLang, AVAILABLE_LANGUAGES)) {
            return $browserLang;
        }
    }
    
    return DEFAULT_LANGUAGE;
}

// دالة للحصول على نص مترجم
function translate($text_ar, $text_en) {
    $lang = getPreferredLanguage();
    return $lang === 'en' ? $text_en : $text_ar;
}

// دالة للحصول على نوع الاستبيان مترجماً
function getPollTypeLabel($type, $lang = null) {
    if ($lang === null) {
        $lang = getPreferredLanguage();
    }
    
    if ($lang === 'en') {
        return isset(POLL_TYPE_LABELS_EN[$type]) ? POLL_TYPE_LABELS_EN[$type] : $type;
    }
    return isset(POLL_TYPE_LABELS_AR[$type]) ? POLL_TYPE_LABELS_AR[$type] : $type;
}

// دالة للتحقق من صحة نوع الاستبيان
function isValidPollType($type) {
    return in_array($type, POLL_TYPES);
}

// إعدادات الوقت
date_default_timezone_set(TIMEZONE);

// تشغيل الجلسة تلقائياً
startSession();

// تعيين معالجة الأخطاء
if (!DEBUG_MODE) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// إنشاء المجلدات اللازمة إذا لم تكن موجودة
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
}
if (!is_dir(TEMP_PATH)) {
    mkdir(TEMP_PATH, 0777, true);
}
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0777, true);
}
if (!is_dir(IMAGES_PATH)) {
    mkdir(IMAGES_PATH, 0777, true);
}

// تعريف الثوابت الإضافية
define('CURRENT_LANGUAGE', getPreferredLanguage());
define('IS_RTL', CURRENT_LANGUAGE === 'ar');

// دالة للتحقق من وجود مستخدم عشوائي
function isRandomUser($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM random_accounts WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// دالة للتحقق من إكمال المستخدم للاستبيان
function hasCompletedPoll($userId, $pollId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM responses WHERE user_id = ? AND poll_id = ?");
        $stmt->execute([$userId, $pollId]);
        $count = $stmt->fetchColumn();
        
        // التحقق من عدد الأسئلة حسب نوع الاستبيان
        $stmt2 = $pdo->prepare("SELECT type FROM polls WHERE id = ?");
        $stmt2->execute([$pollId]);
        $pollType = $stmt2->fetchColumn();
        
        $expectedQuestions = ($pollType === 'admin') ? ADMIN_QUESTIONS_COUNT : REGULAR_QUESTIONS_COUNT;
        return $count >= $expectedQuestions;
    } catch (PDOException $e) {
        return false;
    }
}

// دالة للحصول على إعدادات الاستبيان
function getPollSettings($pollId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as company_name 
            FROM polls p 
            JOIN companies c ON p.company_id = c.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$pollId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// دالة لتغيير كلمة مرور المستخدم
function changeUserPassword($userId, $newPassword) {
    try {
        $pdo = getDBConnection();
        $hashedPassword = hashPassword($newPassword);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, last_password_change = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logError("خطأ في تغيير كلمة المرور: " . $e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

// دالة لتحديث شعار الموقع
function updateSiteLogo($file) {
    try {
        // التحقق من وجود الملف
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'لم يتم تحميل أي ملف'];
        }
        
        // التحقق من نوع الملف
        $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'نوع الملف غير مدعوم. يرجى تحميل صورة بصيغة PNG, JPG, GIF أو WebP'];
        }
        
        // التحقق من حجم الملف (حد أقصى 5 ميجابايت)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['success' => false, 'message' => 'حجم الملف كبير جداً. الحد الأقصى 5 ميجابايت'];
        }
        
        // حذف الشعار القديم إذا كان موجوداً
        if (file_exists(LOGO_PATH)) {
            unlink(LOGO_PATH);
        }
        
        // نقل الملف الجديد إلى المسار المطلوب
        if (move_uploaded_file($file['tmp_name'], LOGO_PATH)) {
            return ['success' => true, 'message' => 'تم تحديث الشعار بنجاح'];
        } else {
            return ['success' => false, 'message' => 'حدث خطأ أثناء حفظ الملف'];
        }
    } catch (Exception $e) {
        logError("خطأ في تحديث الشعار: " . $e->getMessage(), __FILE__, __LINE__);
        return ['success' => false, 'message' => 'حدث خطأ غير متوقع'];
    }
}

// دالة للتحقق من وجود شعار
function hasLogo() {
    return file_exists(LOGO_PATH);
}

// دالة للحصول على مسار الشعار
function getLogoPath() {
    if (hasLogo()) {
        return SITE_URL . 'images/' . LOGO_FILENAME;
    }
    return null;
}

// دالة لحذف شركة وجميع بياناتها المرتبطة
function deleteCompany($companyId) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // 1. الحصول على جميع استبيانات الشركة
        $stmt = $pdo->prepare("SELECT id FROM polls WHERE company_id = ?");
        $stmt->execute([$companyId]);
        $pollIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 2. حذف بيانات كل استبيان
        foreach ($pollIds as $pollId) {
            // حذف الإجابات الخاصة
            $pdo->prepare("DELETE FROM text_responses WHERE poll_id = ?")->execute([$pollId]);
            $pdo->prepare("DELETE FROM choice_responses WHERE poll_id = ?")->execute([$pollId]);
            $pdo->prepare("DELETE FROM ranking_responses WHERE poll_id = ?")->execute([$pollId]);
            $pdo->prepare("DELETE FROM numeric_responses WHERE poll_id = ?")->execute([$pollId]);
            
            // حذف الإجابات الرئيسية
            $pdo->prepare("DELETE FROM responses WHERE poll_id = ?")->execute([$pollId]);
            
            // حذف ملاحظات المحاور
            $pdo->prepare("DELETE FROM section_notes WHERE poll_id = ?")->execute([$pollId]);
            
            // حذف الحسابات العشوائية
            $pdo->prepare("DELETE FROM random_accounts WHERE poll_id = ?")->execute([$pollId]);
            
            // حذف المستخدمين المرتبطين
            $pdo->prepare("DELETE FROM users WHERE poll_id = ? AND role = 'user'")->execute([$pollId]);
            
            // حذف نتائج الاستبيان
            $pdo->prepare("DELETE FROM poll_results WHERE poll_id = ?")->execute([$pollId]);
        }
        
        // 3. حذف الاستبيانات
        $pdo->prepare("DELETE FROM polls WHERE company_id = ?")->execute([$companyId]);
        
        // 4. حذف الشركة
        $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$companyId]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'تم حذف الشركة وجميع بياناتها بنجاح'];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("خطأ في حذف الشركة: " . $e->getMessage(), __FILE__, __LINE__);
        return ['success' => false, 'message' => 'حدث خطأ أثناء حذف الشركة: ' . $e->getMessage()];
    }
}

// دالة لتحديث بيانات الشركة
function updateCompany($companyId, $data) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE companies 
            SET name = ?, address = ?, industry = ?, phone = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['address'] ?? '',
            $data['industry'] ?? '',
            $data['phone'] ?? '',
            $companyId
        ]);
        return ['success' => true, 'message' => 'تم تحديث بيانات الشركة بنجاح'];
    } catch (PDOException $e) {
        logError("خطأ في تحديث الشركة: " . $e->getMessage(), __FILE__, __LINE__);
        return ['success' => false, 'message' => 'حدث خطأ أثناء تحديث الشركة'];
    }
}

// تعريف الوظائف الإضافية التي قد تحتاجها
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>