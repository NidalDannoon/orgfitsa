<?php
/**
 * لوحة تحكم المشرف - النسخة المتطورة مع دعم أنواع الأسئلة المتعددة
 * admin.php
 * 
 * يدعم:
 * - إدارة الشركات (إضافة، عرض مع Infinite Scroll، تعديل، حذف)
 * - إدارة الاستبيانات (إضافة، حذف)
 * - إدارة المحاور (إضافة، تعديل، حذف)
 * - إدارة الأسئلة (إضافة، تعديل، حذف) مع 5 أنواع مختلفة
 * - البحث عن شركة
 * - إنشاء حسابات عشوائية للاستبيانات
 * - تغيير كلمة مرور المشرف
 * - تغيير شعار الموقع
 * - دعم أنواع الاستبيان: عادي، إداري، شخصي، تنفيذي
 * - دعم خاصية النتيجة العكسية (Reverse Coded) لأسئلة مقياس ليكرت
 * 
 * تم التحديث: إضافة أنواع استبيان جديدة (شخصي، تنفيذي)
 * تم التحديث: إصلاح مشكلة حذف السؤال
 * تم التحديث: عرض أنواع الاستبيان الجديدة في تبويبات الأسئلة
 * تم التحديث: إضافة خاصية النتيجة العكسية (Reverse Coded) لأسئلة ليكرت
 */

require_once 'config.php';

// التحقق من تسجيل الدخول وصلاحية المشرف
startSession();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: join.php');
    exit;
}

// الحصول على معلومات المشرف
$adminId = $_SESSION['user_id'];
$adminUsername = $_SESSION['username'];

// متغيرات للرسائل
$successMessage = '';
$errorMessage = '';
$passwordMessage = '';
$logoMessage = '';

// تحديد اللغة
$lang = getPreferredLanguage();
$isRtl = IS_RTL;

try {
    $pdo = getDBConnection();
} catch (PDOException $e) {
    die('خطأ في الاتصال بقاعدة البيانات');
}

// ============================================================
// ===== التحقق من وجود الأعمدة المطلوبة =====
// ============================================================
function checkRequiredColumns($pdo) {
    $errors = [];
    
    // التحقق من وجود عمود question_type في regular_questions
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM regular_questions LIKE 'question_type'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE regular_questions ADD COLUMN question_type ENUM('likert', 'text', 'choice', 'ranking', 'numeric') NOT NULL DEFAULT 'likert' AFTER question_text_en");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في regular_questions.question_type: " . $e->getMessage();
    }
    
    // التحقق من وجود عمود question_type في admin_questions
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM admin_questions LIKE 'question_type'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE admin_questions ADD COLUMN question_type ENUM('likert', 'text', 'choice', 'ranking', 'numeric') NOT NULL DEFAULT 'likert' AFTER question_text_en");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في admin_questions.question_type: " . $e->getMessage();
    }
    
    // التحقق من وجود جدول personal_questions
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'personal_questions'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE personal_questions (
                    id int NOT NULL AUTO_INCREMENT,
                    question_text_ar text NOT NULL,
                    question_text_en text NOT NULL,
                    question_type enum('likert','text','choice','ranking','numeric') NOT NULL DEFAULT 'likert',
                    question_number int NOT NULL,
                    section_number int DEFAULT NULL,
                    section_id int DEFAULT NULL,
                    display_order int DEFAULT '0',
                    has_na_option tinyint(1) DEFAULT '0',
                    has_no_plan_option tinyint(1) DEFAULT '0',
                    is_reverse tinyint(1) DEFAULT '0',
                    PRIMARY KEY (id),
                    KEY idx_section_id (section_id),
                    KEY idx_question_number (question_number),
                    KEY idx_display_order (display_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في إنشاء personal_questions: " . $e->getMessage();
    }
    
    // التحقق من وجود جدول executive_questions
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'executive_questions'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE executive_questions (
                    id int NOT NULL AUTO_INCREMENT,
                    question_text_ar text NOT NULL,
                    question_text_en text NOT NULL,
                    question_type enum('likert','text','choice','ranking','numeric') NOT NULL DEFAULT 'likert',
                    question_number int NOT NULL,
                    section_number int DEFAULT NULL,
                    section_id int DEFAULT NULL,
                    display_order int DEFAULT '0',
                    has_na_option tinyint(1) DEFAULT '0',
                    has_no_plan_option tinyint(1) DEFAULT '0',
                    is_reverse tinyint(1) DEFAULT '0',
                    PRIMARY KEY (id),
                    KEY idx_section_id (section_id),
                    KEY idx_question_number (question_number),
                    KEY idx_display_order (display_order)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في إنشاء executive_questions: " . $e->getMessage();
    }
    
    // التحقق من وجود عمود display_order في regular_questions
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM regular_questions LIKE 'display_order'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE regular_questions ADD COLUMN display_order int DEFAULT 0 AFTER section_id");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في regular_questions.display_order: " . $e->getMessage();
    }
    
    // التحقق من وجود عمود display_order في admin_questions
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM admin_questions LIKE 'display_order'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE admin_questions ADD COLUMN display_order int DEFAULT 0 AFTER section_id");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في admin_questions.display_order: " . $e->getMessage();
    }
    
    // التحقق من وجود عمود is_reverse في regular_questions
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM regular_questions LIKE 'is_reverse'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE regular_questions ADD COLUMN is_reverse TINYINT(1) NOT NULL DEFAULT 0 AFTER display_order");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في regular_questions.is_reverse: " . $e->getMessage();
    }
    
    // التحقق من وجود عمود is_reverse في admin_questions
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM admin_questions LIKE 'is_reverse'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE admin_questions ADD COLUMN is_reverse TINYINT(1) NOT NULL DEFAULT 0 AFTER display_order");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في admin_questions.is_reverse: " . $e->getMessage();
    }
    
    // التحقق من وجود عمود last_password_change في users
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_password_change'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_password_change TIMESTAMP NULL DEFAULT NULL AFTER last_login");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في users.last_password_change: " . $e->getMessage();
    }
    
    // التحقق من وجود عمود logo_path في companies
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'logo_path'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE companies ADD COLUMN logo_path VARCHAR(255) NULL DEFAULT NULL AFTER phone");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في companies.logo_path: " . $e->getMessage();
    }
    
    // تحديث هيكل جدول polls لدعم الأنواع الجديدة
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM polls LIKE 'type'");
        $column = $stmt->fetch();
        if ($column && strpos($column['Type'], 'personal') === false) {
            $pdo->exec("ALTER TABLE polls MODIFY COLUMN type ENUM('regular', 'admin', 'personal', 'executive') NOT NULL DEFAULT 'regular'");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في تحديث polls.type: " . $e->getMessage();
    }
    
    // تحديث هيكل جدول sections لدعم الأنواع الجديدة
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM sections LIKE 'poll_type'");
        $column = $stmt->fetch();
        if ($column && strpos($column['Type'], 'personal') === false) {
            $pdo->exec("ALTER TABLE sections MODIFY COLUMN poll_type ENUM('regular', 'admin', 'personal', 'executive') NOT NULL");
        }
    } catch (PDOException $e) {
        $errors[] = "خطأ في تحديث sections.poll_type: " . $e->getMessage();
    }
    
    return $errors;
}

// تنفيذ التحقق
$columnErrors = checkRequiredColumns($pdo);
if (!empty($columnErrors)) {
    foreach ($columnErrors as $error) {
        logError($error, __FILE__, __LINE__);
    }
}

// ============================================================
// ===== دالة للحصول على رقم السؤال التالي داخل المحور =====
// ============================================================
function getNextQuestionNumber($pdo, $pollType, $sectionId) {
    try {
        $tableName = getQuestionTableName($pollType);
        $stmt = $pdo->prepare("SELECT MAX(question_number) as max_num FROM $tableName WHERE section_id = ?");
        $stmt->execute([$sectionId]);
        $result = $stmt->fetch();
        $maxNum = intval($result['max_num']);
        return $maxNum + 1;
    } catch (PDOException $e) {
        logError("خطأ في الحصول على رقم السؤال التالي: " . $e->getMessage(), __FILE__, __LINE__);
        return 1;
    }
}

// ============================================================
// ===== دالة للحصول على اسم جدول الأسئلة حسب النوع =====
// ============================================================
function getQuestionTableName($pollType) {
    switch ($pollType) {
        case 'admin': return 'admin_questions';
        case 'personal': return 'personal_questions';
        case 'executive': return 'executive_questions';
        default: return 'regular_questions';
    }
}

// ============================================================
// ===== دالة للحصول على display_order التالي داخل المحور =====
// ============================================================
function getNextDisplayOrder($pdo, $pollType, $sectionId) {
    try {
        $tableName = getQuestionTableName($pollType);
        $stmt = $pdo->prepare("SELECT MAX(display_order) as max_order FROM $tableName WHERE section_id = ?");
        $stmt->execute([$sectionId]);
        $result = $stmt->fetch();
        $maxOrder = intval($result['max_order']);
        return $maxOrder + 1;
    } catch (PDOException $e) {
        logError("خطأ في الحصول على display_order التالي: " . $e->getMessage(), __FILE__, __LINE__);
        return 1;
    }
}

// ============================================================
// ===== دالة لإعادة ترتيب الأسئلة بعد الحذف =====
// ============================================================
function reorderQuestions($pdo, $pollType, $sectionId) {
    try {
        $pdo->beginTransaction();
        $tableName = getQuestionTableName($pollType);
        
        $stmt = $pdo->prepare("
            SELECT id FROM $tableName 
            WHERE section_id = ? 
            ORDER BY display_order
        ");
        $stmt->execute([$sectionId]);
        $questions = $stmt->fetchAll();
        
        $order = 1;
        foreach ($questions as $q) {
            $updateStmt = $pdo->prepare("
                UPDATE $tableName 
                SET display_order = ?, question_number = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$order, $order, $q['id']]);
            $order++;
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("خطأ في إعادة ترتيب الأسئلة: " . $e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

// ============================================================
// ===== دالة لحذف محور بالكامل =====
// ============================================================
function deleteSection($pdo, $sectionId) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT poll_type FROM sections WHERE id = ?");
        $stmt->execute([$sectionId]);
        $section = $stmt->fetch();
        
        if (!$section) {
            throw new Exception($GLOBALS['lang'] === 'en' ? 'Section not found' : 'المحور غير موجود');
        }
        
        $pollType = $section['poll_type'];
        $tableName = getQuestionTableName($pollType);
        
        // حذف خيارات الأسئلة
        $stmt = $pdo->prepare("
            DELETE qo FROM question_options qo
            JOIN $tableName q ON qo.question_id = q.id
            WHERE q.section_id = ? AND qo.question_type = ?
        ");
        $stmt->execute([$sectionId, $pollType]);
        
        // حذف الأسئلة
        $stmt = $pdo->prepare("DELETE FROM $tableName WHERE section_id = ?");
        $stmt->execute([$sectionId]);
        
        // حذف المحور
        $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->execute([$sectionId]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("خطأ في حذف المحور: " . $e->getMessage(), __FILE__, __LINE__);
        return false;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("خطأ في حذف المحور: " . $e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

// ============================================================
// ===== دالة لحذف استبيان بالكامل =====
// ============================================================
function deletePoll($pdo, $pollId) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM polls WHERE id = ?");
        $stmt->execute([$pollId]);
        $poll = $stmt->fetch();
        
        if (!$poll) {
            throw new Exception($GLOBALS['lang'] === 'en' ? 'Poll not found' : 'الاستبيان غير موجود');
        }
        
        // حذف الإجابات الخاصة
        $pdo->prepare("DELETE FROM text_responses WHERE poll_id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM choice_responses WHERE poll_id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM ranking_responses WHERE poll_id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM numeric_responses WHERE poll_id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM responses WHERE poll_id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM section_notes WHERE poll_id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM random_accounts WHERE poll_id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM users WHERE poll_id = ? AND role = 'user'")->execute([$pollId]);
        $pdo->prepare("DELETE FROM poll_results WHERE poll_id = ?")->execute([$pollId]);
        $pdo->prepare("DELETE FROM polls WHERE id = ?")->execute([$pollId]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("خطأ في حذف الاستبيان: " . $e->getMessage(), __FILE__, __LINE__);
        return false;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("خطأ في حذف الاستبيان: " . $e->getMessage(), __FILE__, __LINE__);
        return false;
    }
}

// ============================================================
// ===== معالجة حذف محور =====
// ============================================================
if (isset($_GET['delete_section']) && !empty($_GET['delete_section'])) {
    $sectionId = intval($_GET['delete_section']);
    
    if (deleteSection($pdo, $sectionId)) {
        $successMessage = $lang === 'en' ? 'Section deleted successfully' : 'تم حذف المحور بنجاح';
    } else {
        $errorMessage = $lang === 'en' ? 'Error deleting section' : 'خطأ في حذف المحور';
    }
    header('Location: admin.php#section-questions');
    exit;
}

// ============================================================
// ===== معالجة حذف استبيان =====
// ============================================================
if (isset($_GET['delete_poll']) && !empty($_GET['delete_poll'])) {
    $pollId = intval($_GET['delete_poll']);
    $companyId = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
    
    if (deletePoll($pdo, $pollId)) {
        $successMessage = $lang === 'en' ? 'Poll deleted successfully' : 'تم حذف الاستبيان بنجاح';
    } else {
        $errorMessage = $lang === 'en' ? 'Error deleting poll' : 'خطأ في حذف الاستبيان';
    }
    
    if ($companyId > 0) {
        header('Location: admin.php#section-search&company=' . $companyId);
    } else {
        header('Location: admin.php#section-search');
    }
    exit;
}

// ============================================================
// ===== معالجة إضافة شركة جديدة =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {
    $companyName = sanitizeInput($_POST['company_name'] ?? '');
    $companyAddress = sanitizeInput($_POST['company_address'] ?? '');
    $industry = sanitizeInput($_POST['industry'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    
    if (empty($companyName)) {
        $errorMessage = $lang === 'en' ? 'Company name is required' : 'اسم الشركة مطلوب';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO companies (name, address, industry, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$companyName, $companyAddress, $industry, $phone]);
            $successMessage = $lang === 'en' ? 'Company added successfully' : 'تم إضافة الشركة بنجاح';
        } catch (PDOException $e) {
            $errorMessage = $lang === 'en' ? 'Error adding company' : 'خطأ في إضافة الشركة';
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
}

// ============================================================
// ===== معالجة تعديل شركة =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_company'])) {
    $companyId = intval($_POST['edit_company_id'] ?? 0);
    $companyName = sanitizeInput($_POST['edit_company_name'] ?? '');
    $companyAddress = sanitizeInput($_POST['edit_company_address'] ?? '');
    $industry = sanitizeInput($_POST['edit_industry'] ?? '');
    $phone = sanitizeInput($_POST['edit_phone'] ?? '');
    
    if ($companyId <= 0 || empty($companyName)) {
        $errorMessage = $lang === 'en' ? 'Company name is required' : 'اسم الشركة مطلوب';
    } else {
        $result = updateCompany($companyId, [
            'name' => $companyName,
            'address' => $companyAddress,
            'industry' => $industry,
            'phone' => $phone
        ]);
        
        if ($result['success']) {
            $successMessage = $result['message'];
        } else {
            $errorMessage = $result['message'];
        }
    }
}

// ============================================================
// ===== معالجة حذف شركة =====
// ============================================================
if (isset($_GET['delete_company']) && !empty($_GET['delete_company'])) {
    $companyId = intval($_GET['delete_company']);
    
    $result = deleteCompany($companyId);
    
    if ($result['success']) {
        $successMessage = $result['message'];
    } else {
        $errorMessage = $result['message'];
    }
    
    header('Location: admin.php#section-companies');
    exit;
}

// ============================================================
// ===== معالجة تغيير كلمة المرور =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordMessage = $lang === 'en' ? 'All fields are required' : 'جميع الحقول مطلوبة';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordMessage = $lang === 'en' ? 'Passwords do not match' : 'كلمات المرور غير متطابقة';
    } elseif (strlen($newPassword) < 8) {
        $passwordMessage = $lang === 'en' ? 'Password must be at least 8 characters' : 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$adminId]);
            $user = $stmt->fetch();
            
            if (!$user || !verifyPassword($currentPassword, $user['password'])) {
                $passwordMessage = $lang === 'en' ? 'Current password is incorrect' : 'كلمة المرور الحالية غير صحيحة';
            } else {
                if (changeUserPassword($adminId, $newPassword)) {
                    $passwordMessage = $lang === 'en' ? 'Password changed successfully' : 'تم تغيير كلمة المرور بنجاح';
                } else {
                    $passwordMessage = $lang === 'en' ? 'Error changing password' : 'خطأ في تغيير كلمة المرور';
                }
            }
        } catch (PDOException $e) {
            $passwordMessage = $lang === 'en' ? 'Database error' : 'خطأ في قاعدة البيانات';
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
}

// ============================================================
// ===== معالجة تغيير الشعار =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_logo'])) {
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $result = updateSiteLogo($_FILES['logo_file']);
        if ($result['success']) {
            $logoMessage = $result['message'];
        } else {
            $logoMessage = $result['message'];
        }
    } else {
        $logoMessage = $lang === 'en' ? 'Please select a file to upload' : 'الرجاء اختيار ملف للتحميل';
    }
}

// ============================================================
// ===== معالجة إضافة استبيان جديد =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_poll'])) {
    $companyId = intval($_POST['company_id'] ?? 0);
    $pollDate = sanitizeInput($_POST['poll_date'] ?? '');
    $departments = sanitizeInput($_POST['departments'] ?? '');
    $participantCount = intval($_POST['participant_count'] ?? 0);
    $pollType = sanitizeInput($_POST['poll_type'] ?? 'regular');
    
    if ($companyId <= 0 || empty($pollDate) || $participantCount < 4 || $participantCount > 999) {
        $errorMessage = $lang === 'en' ? 'Please fill all fields correctly' : 'الرجاء تعبئة جميع الحقول بشكل صحيح';
    } elseif (!isValidPollType($pollType)) {
        $errorMessage = $lang === 'en' ? 'Invalid poll type' : 'نوع استبيان غير صالح';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO polls (company_id, date, departments, participant_count, type, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$companyId, $pollDate, $departments, $participantCount, $pollType, $adminId]);
            $successMessage = $lang === 'en' ? 'Poll added successfully' : 'تم إضافة الاستبيان بنجاح';
        } catch (PDOException $e) {
            $errorMessage = $lang === 'en' ? 'Error adding poll' : 'خطأ في إضافة الاستبيان';
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
}

// ============================================================
// ===== معالجة إضافة محور جديد =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section'])) {
    $pollType = sanitizeInput($_POST['section_poll_type'] ?? 'regular');
    $sectionNumber = intval($_POST['section_number'] ?? 0);
    $titleAr = sanitizeInput($_POST['section_title_ar'] ?? '');
    $titleEn = sanitizeInput($_POST['section_title_en'] ?? '');
    $descriptionAr = sanitizeInput($_POST['section_description_ar'] ?? '');
    $descriptionEn = sanitizeInput($_POST['section_description_en'] ?? '');
    
    if (empty($titleAr) || empty($titleEn) || $sectionNumber <= 0) {
        $errorMessage = $lang === 'en' ? 'Please fill all fields correctly' : 'الرجاء تعبئة جميع الحقول بشكل صحيح';
    } elseif (!isValidPollType($pollType)) {
        $errorMessage = $lang === 'en' ? 'Invalid poll type' : 'نوع استبيان غير صالح';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sections (poll_type, section_number, title_ar, title_en, description_ar, description_en) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$pollType, $sectionNumber, $titleAr, $titleEn, $descriptionAr, $descriptionEn]);
            $successMessage = $lang === 'en' ? 'Section added successfully' : 'تم إضافة المحور بنجاح';
        } catch (PDOException $e) {
            $errorMessage = $lang === 'en' ? 'Error adding section' : 'خطأ في إضافة المحور';
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
}

// ============================================================
// ===== معالجة تعديل محور =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_section'])) {
    $sectionId = intval($_POST['edit_section_id'] ?? 0);
    $sectionNumber = intval($_POST['edit_section_number'] ?? 0);
    $titleAr = sanitizeInput($_POST['edit_section_title_ar'] ?? '');
    $titleEn = sanitizeInput($_POST['edit_section_title_en'] ?? '');
    $descriptionAr = sanitizeInput($_POST['edit_section_description_ar'] ?? '');
    $descriptionEn = sanitizeInput($_POST['edit_section_description_en'] ?? '');
    
    if ($sectionId <= 0 || empty($titleAr) || empty($titleEn) || $sectionNumber <= 0) {
        $errorMessage = $lang === 'en' ? 'Please fill all fields correctly' : 'الرجاء تعبئة جميع الحقول بشكل صحيح';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE sections 
                SET section_number = ?, title_ar = ?, title_en = ?, description_ar = ?, description_en = ? 
                WHERE id = ?
            ");
            $stmt->execute([$sectionNumber, $titleAr, $titleEn, $descriptionAr, $descriptionEn, $sectionId]);
            $successMessage = $lang === 'en' ? 'Section updated successfully' : 'تم تحديث المحور بنجاح';
        } catch (PDOException $e) {
            $errorMessage = $lang === 'en' ? 'Error updating section' : 'خطأ في تحديث المحور';
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
}

// ============================================================
// ===== معالجة إضافة سؤال جديد (مع دعم النتيجة العكسية) =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $pollType = sanitizeInput($_POST['question_poll_type'] ?? 'regular');
    $sectionId = intval($_POST['question_section_id'] ?? 0);
    $questionTextAr = sanitizeInput($_POST['question_text_ar'] ?? '');
    $questionTextEn = sanitizeInput($_POST['question_text_en'] ?? '');
    $questionType = sanitizeInput($_POST['question_type'] ?? 'likert');
    $hasNaOption = isset($_POST['has_na_option']) ? 1 : 0;
    $hasNoPlanOption = isset($_POST['has_no_plan_option']) ? 1 : 0;
    $isReverse = isset($_POST['is_reverse']) ? 1 : 0;
    
    if (empty($questionTextAr) || empty($questionTextEn)) {
        $errorMessage = $lang === 'en' ? 'Question text is required in both languages' : 'نص السؤال مطلوب باللغتين';
    } elseif ($sectionId <= 0) {
        $errorMessage = $lang === 'en' ? 'Please select a section' : 'الرجاء اختيار محور';
    } elseif (!isValidPollType($pollType)) {
        $errorMessage = $lang === 'en' ? 'Invalid poll type' : 'نوع استبيان غير صالح';
    } else {
        try {
            $pdo->beginTransaction();
            
            $nextNumber = getNextQuestionNumber($pdo, $pollType, $sectionId);
            $nextDisplayOrder = getNextDisplayOrder($pdo, $pollType, $sectionId);
            $tableName = getQuestionTableName($pollType);
            
            $stmt = $pdo->prepare("
                INSERT INTO $tableName 
                (question_number, section_id, display_order, question_text_ar, question_text_en, question_type, has_na_option, has_no_plan_option, is_reverse) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nextNumber, $sectionId, $nextDisplayOrder, $questionTextAr, $questionTextEn, $questionType, $hasNaOption, $hasNoPlanOption, $isReverse]);
            $questionId = $pdo->lastInsertId();
            
            if ($questionId > 0) {
                if (($questionType === 'choice' || $questionType === 'ranking') && isset($_POST['option_text_ar']) && is_array($_POST['option_text_ar'])) {
                    $optionTextsAr = $_POST['option_text_ar'];
                    $optionTextsEn = $_POST['option_text_en'] ?? [];
                    $optionCount = count($optionTextsAr);
                    
                    $validOptions = 0;
                    for ($i = 0; $i < $optionCount; $i++) {
                        if (!empty($optionTextsAr[$i]) && !empty($optionTextsEn[$i])) {
                            $validOptions++;
                        }
                    }
                    
                    if ($validOptions >= 2) {
                        for ($i = 0; $i < $optionCount; $i++) {
                            if (!empty($optionTextsAr[$i]) && !empty($optionTextsEn[$i])) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO question_options 
                                    (question_id, question_type, option_text_ar, option_text_en, option_order) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $questionId,
                                    $pollType,
                                    sanitizeInput($optionTextsAr[$i]),
                                    sanitizeInput($optionTextsEn[$i]),
                                    $i + 1
                                ]);
                            }
                        }
                    } else {
                        $pdo->prepare("DELETE FROM $tableName WHERE id = ?")->execute([$questionId]);
                        throw new Exception($lang === 'en' ? 'Please add at least 2 options for choice/ranking questions' : 'الرجاء إضافة خيارين على الأقل للأسئلة من نوع اختيار/ترتيب');
                    }
                }
                
                $pdo->commit();
                $successMessage = $lang === 'en' ? 'Question added successfully (#' . $nextNumber . ' in section)' : 'تم إضافة السؤال بنجاح (#' . $nextNumber . ' في المحور)';
            } else {
                throw new Exception($lang === 'en' ? 'Failed to insert question' : 'فشل إدراج السؤال');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $lang === 'en' ? 'Database error: ' . $e->getMessage() : 'خطأ في قاعدة البيانات: ' . $e->getMessage();
            logError($e->getMessage(), __FILE__, __LINE__);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $e->getMessage();
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
}

// ============================================================
// ===== معالجة تعديل سؤال (مع دعم النتيجة العكسية) =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_question'])) {
    $questionId = intval($_POST['edit_question_id'] ?? 0);
    $pollType = sanitizeInput($_POST['edit_question_poll_type'] ?? 'regular');
    $sectionId = intval($_POST['edit_question_section_id'] ?? 0);
    $questionNumber = intval($_POST['edit_question_number'] ?? 0);
    $questionTextAr = sanitizeInput($_POST['edit_question_text_ar'] ?? '');
    $questionTextEn = sanitizeInput($_POST['edit_question_text_en'] ?? '');
    $questionType = sanitizeInput($_POST['edit_question_type'] ?? 'likert');
    $hasNaOption = isset($_POST['edit_has_na_option']) ? 1 : 0;
    $hasNoPlanOption = isset($_POST['edit_has_no_plan_option']) ? 1 : 0;
    $displayOrder = intval($_POST['edit_display_order'] ?? 0);
    $isReverse = isset($_POST['edit_is_reverse']) ? 1 : 0;
    
    if ($questionId <= 0 || empty($questionTextAr) || empty($questionTextEn) || $sectionId <= 0 || $questionNumber <= 0) {
        $errorMessage = $lang === 'en' ? 'Please fill all fields correctly' : 'الرجاء تعبئة جميع الحقول بشكل صحيح';
    } else {
        try {
            $pdo->beginTransaction();
            $tableName = getQuestionTableName($pollType);
            
            $stmt = $pdo->prepare("
                UPDATE $tableName 
                SET question_number = ?, section_id = ?, display_order = ?, question_text_ar = ?, question_text_en = ?, 
                    question_type = ?, has_na_option = ?, has_no_plan_option = ?, is_reverse = ? 
                WHERE id = ?
            ");
            $stmt->execute([$questionNumber, $sectionId, $displayOrder, $questionTextAr, $questionTextEn, $questionType, $hasNaOption, $hasNoPlanOption, $isReverse, $questionId]);
            
            $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
            $stmt->execute([$questionId]);
            
            if (($questionType === 'choice' || $questionType === 'ranking') && isset($_POST['edit_option_text_ar']) && is_array($_POST['edit_option_text_ar'])) {
                $optionTextsAr = $_POST['edit_option_text_ar'];
                $optionTextsEn = $_POST['edit_option_text_en'] ?? [];
                
                for ($i = 0; $i < count($optionTextsAr); $i++) {
                    if (!empty($optionTextsAr[$i]) && !empty($optionTextsEn[$i])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO question_options 
                            (question_id, question_type, option_text_ar, option_text_en, option_order) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $questionId,
                            $pollType,
                            sanitizeInput($optionTextsAr[$i]),
                            sanitizeInput($optionTextsEn[$i]),
                            $i + 1
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            $successMessage = $lang === 'en' ? 'Question updated successfully' : 'تم تحديث السؤال بنجاح';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $lang === 'en' ? 'Error updating question' : 'خطأ في تحديث السؤال';
            logError($e->getMessage(), __FILE__, __LINE__);
        }
    }
}

// ============================================================
// ===== معالجة حذف سؤال (الحل النهائي مع التحقق من المعاملة) =====
// ============================================================
if (isset($_GET['delete_question']) && !empty($_GET['delete_question'])) {
    $questionId = intval($_GET['delete_question']);
    $pollType = sanitizeInput($_GET['poll_type'] ?? '');
    
    try {
        // تحديد الجدول المناسب
        $tables = ['regular_questions', 'admin_questions', 'personal_questions', 'executive_questions'];
        $found = false;
        $tableName = '';
        $sectionId = 0;
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT id, section_id FROM $table WHERE id = ?");
            $stmt->execute([$questionId]);
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch();
                $found = true;
                $tableName = $table;
                $sectionId = $row['section_id'];
                break;
            }
        }
        
        if (!$found) {
            $errorMessage = $lang === 'en' ? 'Question not found' : 'السؤال غير موجود';
            header('Location: admin.php#section-questions');
            exit;
        }
        
        // حذف خيارات السؤال (بدون ترانزاكشن)
        $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
        $stmt->execute([$questionId]);
        
        // حذف السؤال نفسه (بدون ترانزاكشن)
        $stmt = $pdo->prepare("DELETE FROM $tableName WHERE id = ?");
        $stmt->execute([$questionId]);
        
        // إعادة ترتيب الأسئلة (بدون ترانزاكشن)
        if ($sectionId > 0) {
            $pollType = str_replace('_questions', '', $tableName);
            // استدعاء دالة reorderQuestions ولكن بدون ترانزاكشن
            $orderStmt = $pdo->prepare("
                SELECT id FROM $tableName 
                WHERE section_id = ? 
                ORDER BY display_order
            ");
            $orderStmt->execute([$sectionId]);
            $questions = $orderStmt->fetchAll();
            
            $order = 1;
            foreach ($questions as $q) {
                $updateStmt = $pdo->prepare("
                    UPDATE $tableName 
                    SET display_order = ?, question_number = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$order, $order, $q['id']]);
                $order++;
            }
        }
        
        $successMessage = $lang === 'en' ? 'Question deleted successfully' : 'تم حذف السؤال بنجاح';
        
    } catch (PDOException $e) {
        $errorMessage = $lang === 'en' ? 'Error deleting question: ' . $e->getMessage() : 'خطأ في حذف السؤال: ' . $e->getMessage();
        logError($e->getMessage(), __FILE__, __LINE__);
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        logError($e->getMessage(), __FILE__, __LINE__);
    }
    header('Location: admin.php#section-questions');
    exit;
}

// ============================================================
// ===== دوال مساعدة للحسابات =====
// ============================================================
function pollHasAccounts($pdo, $pollId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM random_accounts WHERE poll_id = ?");
    $stmt->execute([$pollId]);
    return $stmt->fetchColumn() > 0;
}

function getPollAccounts($pdo, $pollId) {
    $stmt = $pdo->prepare("SELECT username, password FROM random_accounts WHERE poll_id = ?");
    $stmt->execute([$pollId]);
    return $stmt->fetchAll();
}

function getPollInfo($pdo, $pollId) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as company_name 
        FROM polls p 
        JOIN companies c ON p.company_id = c.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$pollId]);
    return $stmt->fetch();
}

function getQuestionOptions($pdo, $questionId, $questionType) {
    $stmt = $pdo->prepare("
        SELECT * FROM question_options 
        WHERE question_id = ? AND question_type = ? 
        ORDER BY option_order
    ");
    $stmt->execute([$questionId, $questionType]);
    return $stmt->fetchAll();
}

function getQuestionsByType($pdo, $tableName, $pollType) {
    try {
        $stmt = $pdo->prepare("
            SELECT q.*, s.title_ar as section_title_ar, s.title_en as section_title_en
            FROM $tableName q
            LEFT JOIN sections s ON q.section_id = s.id
            WHERE s.poll_type = ? OR s.poll_type IS NULL
            ORDER BY q.section_id, q.display_order, q.question_number
        ");
        $stmt->execute([$pollType]);
        $questions = $stmt->fetchAll();
        
        foreach ($questions as &$q) {
            if ($q['question_type'] === 'choice' || $q['question_type'] === 'ranking') {
                $q['options'] = getQuestionOptions($pdo, $q['id'], $pollType);
            }
        }
        return $questions;
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        return [];
    }
}

// ============================================================
// ===== معالجة AJAX لإنشاء حسابات =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_generate_accounts'])) {
    header('Content-Type: application/json');
    
    $pollId = intval($_POST['poll_id'] ?? 0);
    $response = ['success' => false, 'message' => '', 'accounts' => [], 'poll_info' => null, 'already_exist' => false];
    
    if ($pollId <= 0) {
        $response['message'] = $lang === 'en' ? 'Please select a poll' : 'الرجاء اختيار استبيان';
        echo json_encode($response);
        exit;
    }
    
    try {
        if (pollHasAccounts($pdo, $pollId)) {
            $accountsList = getPollAccounts($pdo, $pollId);
            $poll = getPollInfo($pdo, $pollId);
            
            $response['success'] = true;
            $response['accounts'] = $accountsList;
            $response['poll_info'] = [
                'company_name' => $poll['company_name'],
                'date' => $poll['date'],
                'type' => $poll['type'],
                'participant_count' => $poll['participant_count'],
                'departments' => $poll['departments']
            ];
            $response['message'] = $lang === 'en' ? 'Accounts already exist' : 'حسابات موجودة مسبقاً';
            $response['already_exist'] = true;
        } else {
            $poll = getPollInfo($pdo, $pollId);
            
            if (!$poll) {
                $response['message'] = $lang === 'en' ? 'Poll not found' : 'الاستبيان غير موجود';
                echo json_encode($response);
                exit;
            }
            
            $participantCount = $poll['participant_count'];
            $accountsList = [];
            
            $stmt = $pdo->prepare("DELETE FROM random_accounts WHERE poll_id = ?");
            $stmt->execute([$pollId]);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE poll_id = ? AND role = 'user'");
            $stmt->execute([$pollId]);
            
            for ($i = 0; $i < $participantCount; $i++) {
                $username = generateRandomUsername();
                $password = generateRandomPassword();
                $hashedPassword = hashPassword($password);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, poll_id, is_active) VALUES (?, ?, 'user', ?, 1)");
                $stmt->execute([$username, $hashedPassword, $pollId]);
                
                $stmt = $pdo->prepare("INSERT INTO random_accounts (poll_id, username, password, is_used) VALUES (?, ?, ?, 0)");
                $stmt->execute([$pollId, $username, $password]);
                
                $accountsList[] = ['username' => $username, 'password' => $password];
            }
            
            $pollInfo = getPollInfo($pdo, $pollId);
            
            $response['success'] = true;
            $response['accounts'] = $accountsList;
            $response['poll_info'] = [
                'company_name' => $pollInfo['company_name'],
                'date' => $pollInfo['date'],
                'type' => $pollInfo['type'],
                'participant_count' => $pollInfo['participant_count'],
                'departments' => $pollInfo['departments']
            ];
            $response['message'] = ($lang === 'en' ? 'Generated ' : 'تم إنشاء ') . count($accountsList) . 
                                  ($lang === 'en' ? ' accounts successfully' : ' حساباً بنجاح');
            $response['already_exist'] = false;
            
            $_SESSION['generated_accounts'] = $accountsList;
            $_SESSION['generated_poll_id'] = $pollId;
            $_SESSION['generated_poll_info'] = $response['poll_info'];
            $_SESSION['accounts_already_exist'] = false;
        }
    } catch (PDOException $e) {
        $response['message'] = $lang === 'en' ? 'Error generating accounts' : 'خطأ في إنشاء الحسابات';
        logError($e->getMessage(), __FILE__, __LINE__);
    }
    
    echo json_encode($response);
    exit;
}

// ============================================================
// ===== معالجة عرض الحسابات =====
// ============================================================
if (isset($_GET['show_accounts']) && !empty($_GET['show_accounts'])) {
    $pollId = intval($_GET['show_accounts']);
    
    try {
        if (pollHasAccounts($pdo, $pollId)) {
            $accountsList = getPollAccounts($pdo, $pollId);
            $poll = getPollInfo($pdo, $pollId);
            
            $_SESSION['generated_accounts'] = $accountsList;
            $_SESSION['generated_poll_id'] = $pollId;
            $_SESSION['generated_poll_info'] = [
                'company_name' => $poll['company_name'],
                'date' => $poll['date'],
                'type' => $poll['type'],
                'participant_count' => $poll['participant_count'],
                'departments' => $poll['departments']
            ];
            $_SESSION['accounts_already_exist'] = true;
        }
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
    }
    
    header('Location: admin.php');
    exit;
}

// ============================================================
// ===== معالجة مسح الحسابات =====
// ============================================================
if (isset($_GET['clear_accounts']) && $_GET['clear_accounts'] == 1) {
    unset($_SESSION['generated_accounts']);
    unset($_SESSION['generated_poll_id']);
    unset($_SESSION['generated_poll_info']);
    unset($_SESSION['accounts_already_exist']);
    header('Location: admin.php');
    exit;
}

// ============================================================
// ===== جلب البيانات للصفحة الأولى =====
// ============================================================
$companies = [];
$companiesTotalCount = 0;
$companiesPerPage = 20;
$currentPage = 1;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM companies");
    $totalResult = $stmt->fetch();
    $companiesTotalCount = intval($totalResult['total']);
    
    $stmt = $pdo->query("
        SELECT id, name, address, industry, phone, created_at 
        FROM companies 
        ORDER BY created_at DESC 
        LIMIT " . $companiesPerPage . "
    ");
    $companies = $stmt->fetchAll();
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
}

$polls = [];
try {
    $stmt = $pdo->query("
        SELECT p.id, p.date, p.type, p.departments, p.participant_count, c.name as company_name, p.company_id
        FROM polls p 
        JOIN companies c ON p.company_id = c.id 
        ORDER BY p.date DESC
    ");
    $polls = $stmt->fetchAll();
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
}

// جلب المحاور
$sections = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM sections 
        ORDER BY poll_type, section_number
    ");
    $sections = $stmt->fetchAll();
} catch (PDOException $e) {
    logError($e->getMessage(), __FILE__, __LINE__);
}

// جلب الأسئلة حسب النوع
$regularQuestions = getQuestionsByType($pdo, 'regular_questions', 'regular');
$adminQuestions = getQuestionsByType($pdo, 'admin_questions', 'admin');
$personalQuestions = getQuestionsByType($pdo, 'personal_questions', 'personal');
$executiveQuestions = getQuestionsByType($pdo, 'executive_questions', 'executive');

$generatedAccounts = isset($_SESSION['generated_accounts']) ? $_SESSION['generated_accounts'] : [];
$generatedPollInfo = isset($_SESSION['generated_poll_info']) ? $_SESSION['generated_poll_info'] : null;
$accountsAlreadyExist = isset($_SESSION['accounts_already_exist']) ? $_SESSION['accounts_already_exist'] : false;

// ============================================================
// ===== JSON للـ JavaScript =====
// ============================================================
$companiesJson = json_encode(array_map(function($c) {
    return ['label' => $c['name'], 'value' => $c['id']];
}, $companies));

$pollsJson = json_encode(array_map(function($p) {
    return [
        'id' => $p['id'],
        'company_id' => $p['company_id'] ?? 0,
        'date' => $p['date'],
        'type' => $p['type'],
        'type_label' => getPollTypeLabel($p['type']),
        'departments' => $p['departments'] ?? '',
        'participant_count' => $p['participant_count'] ?? 0,
        'company_name' => $p['company_name'] ?? '',
        'has_accounts' => pollHasAccounts($GLOBALS['pdo'], $p['id'])
    ];
}, $polls));

// ============================================================
// ===== رسائل JavaScript =====
// ============================================================
$jsSelectCompanyFirst = $lang === 'en' ? 'Select company first' : 'اختر الشركة أولاً';
$jsSelectPoll = $lang === 'en' ? 'Select poll...' : 'اختر الاستبيان...';
$jsNoPolls = $lang === 'en' ? 'No polls found' : 'لا توجد استبيانات';
$jsParticipants = $lang === 'en' ? 'participants' : 'مشارك';
$jsPleaseSelectCompany = $lang === 'en' ? 'Please select a company' : 'الرجاء اختيار شركة';
$jsSearching = $lang === 'en' ? 'Searching...' : 'جاري البحث...';
$jsErrorSearching = $lang === 'en' ? 'Error searching company' : 'خطأ في البحث عن الشركة';
$jsAllAccountsCopied = $lang === 'en' ? 'All accounts copied!' : 'تم نسخ جميع الحسابات!';
$jsPollAccounts = $lang === 'en' ? 'Poll Accounts' : 'حسابات الاستبيان';
$jsTitleCompanies = $lang === 'en' ? 'Companies Management' : 'إدارة الشركات';
$jsTitlePolls = $lang === 'en' ? 'Polls Management' : 'إدارة الاستبيانات';
$jsTitleSearch = $lang === 'en' ? 'Search Company' : 'البحث عن شركة';
$jsTitleAccounts = $lang === 'en' ? 'Poll Accounts' : 'حسابات الاستبيان';
$jsTitleQuestions = $lang === 'en' ? 'Questions Management' : 'إدارة الأسئلة';
$jsAccountsExist = $lang === 'en' ? 'Accounts already exist' : 'حسابات موجودة مسبقاً';
$jsViewAccounts = $lang === 'en' ? 'View Accounts' : 'عرض الحسابات';
$jsNoAccounts = $lang === 'en' ? 'No accounts found' : 'لا توجد حسابات';
$jsCompanyDetails = $lang === 'en' ? 'Company Details' : 'بيانات الشركة';
$jsName = $lang === 'en' ? 'Name' : 'الاسم';
$jsAddress = $lang === 'en' ? 'Address' : 'العنوان';
$jsIndustry = $lang === 'en' ? 'Industry' : 'المجال';
$jsPhone = $lang === 'en' ? 'Phone' : 'الهاتف';
$jsDateLabel = $lang === 'en' ? 'Date' : 'التاريخ';
$jsType = $lang === 'en' ? 'Type' : 'النوع';
$jsDepartmentsLabel = $lang === 'en' ? 'Departments' : 'الأقسام';
$jsParticipantsLabel = $lang === 'en' ? 'Participants' : 'المشاركون';
$jsAction = $lang === 'en' ? 'Action' : 'الإجراء';
$jsView = $lang === 'en' ? 'View' : 'عرض';
$jsCompanyNotFound = $lang === 'en' ? 'Company not found' : 'الشركة غير موجودة';
$jsGenerating = $lang === 'en' ? 'Generating...' : 'جاري الإنشاء...';
$jsErrorGenerating = $lang === 'en' ? 'Error generating accounts' : 'خطأ في إنشاء الحسابات';
$jsGenerate = $lang === 'en' ? 'Generate' : 'إنشاء';
$jsAlreadyGenerated = $lang === 'en' ? 'Already Generated' : 'تم الإنشاء مسبقاً';
$jsCompany = $lang === 'en' ? 'Company' : 'الشركة';
$jsDate = $lang === 'en' ? 'Date' : 'التاريخ';
$jsParticipantsCount = $lang === 'en' ? 'Participants' : 'المشاركون';
$jsDepartments = $lang === 'en' ? 'Departments' : 'الأقسام';
$jsGeneratedAccounts = $lang === 'en' ? 'Generated Accounts' : 'الحسابات المنشأة';
$jsRegular = $lang === 'en' ? 'Regular' : 'عادي';
$jsAdmin = $lang === 'en' ? 'Admin' : 'إداري';
$jsPersonal = $lang === 'en' ? 'Personal' : 'شخصي';
$jsExecutive = $lang === 'en' ? 'Executive' : 'تنفيذي';
$jsQuestionNumber = $lang === 'en' ? 'Question Number' : 'رقم السؤال';
$jsQuestionText = $lang === 'en' ? 'Question Text' : 'نص السؤال';
$jsSection = $lang === 'en' ? 'Section' : 'المحور';
$jsSpecialOptions = $lang === 'en' ? 'Special Options' : 'خيارات خاصة';
$jsAddQuestion = $lang === 'en' ? 'Add Question' : 'إضافة سؤال';
$jsEditQuestion = $lang === 'en' ? 'Edit Question' : 'تعديل سؤال';
$jsDeleteQuestion = $lang === 'en' ? 'Delete Question' : 'حذف سؤال';
$jsConfirmDelete = $lang === 'en' ? 'Are you sure you want to delete this question?' : 'هل أنت متأكد من حذف هذا السؤال؟';
$jsLoadingMore = $lang === 'en' ? 'Loading more...' : 'جاري تحميل المزيد...';
$jsNoMoreCompanies = $lang === 'en' ? 'No more companies to load' : 'لا توجد شركات أخرى للتحميل';
$jsLoadError = $lang === 'en' ? 'Error loading companies' : 'خطأ في تحميل الشركات';
$jsEditSection = $lang === 'en' ? 'Edit Section' : 'تعديل المحور';
$jsDeleteSection = $lang === 'en' ? 'Delete Section' : 'حذف محور';
$jsDeletePoll = $lang === 'en' ? 'Delete Poll' : 'حذف استبيان';
$jsQuestionType = $lang === 'en' ? 'Question Type' : 'نوع السؤال';
$jsLikert = $lang === 'en' ? 'Likert Scale' : 'مقياس ليكرت';
$jsText = $lang === 'en' ? 'Text Answer' : 'إجابة نصية';
$jsChoice = $lang === 'en' ? 'Multiple Choice' : 'اختيار من متعدد';
$jsRanking = $lang === 'en' ? 'Ranking' : 'ترتيب';
$jsNumeric = $lang === 'en' ? 'Numeric (0-10)' : 'رقمي (0-10)';
$jsAddOption = $lang === 'en' ? 'Add Option' : 'إضافة خيار';
$jsRemoveOption = $lang === 'en' ? 'Remove' : 'إزالة';
$jsAtLeast2Options = $lang === 'en' ? 'Please add at least 2 options' : 'الرجاء إضافة خيارين على الأقل';
$jsMax5Options = $lang === 'en' ? 'Maximum 5 options allowed' : 'الحد الأقصى 5 خيارات';
$jsConfirmDeleteSection = $lang === 'en' ? 'Are you sure you want to delete this section and all its questions?' : 'هل أنت متأكد من حذف هذا المحور وجميع أسئلته؟';
$jsConfirmDeletePoll = $lang === 'en' ? 'Are you sure you want to delete this poll and all its data?' : 'هل أنت متأكد من حذف هذا الاستبيان وجميع بياناته؟';
$jsConfirmDeleteCompany = $lang === 'en' ? 'Are you sure you want to delete this company and all its data (polls, accounts, responses)?' : 'هل أنت متأكد من حذف هذه الشركة وجميع بياناتها (استبيانات، حسابات، ردود)؟';
$jsChangePassword = $lang === 'en' ? 'Change Password' : 'تغيير كلمة المرور';
$jsChangeLogo = $lang === 'en' ? 'Change Logo' : 'تغيير الشعار';
$jsCurrentPassword = $lang === 'en' ? 'Current Password' : 'كلمة المرور الحالية';
$jsNewPassword = $lang === 'en' ? 'New Password' : 'كلمة المرور الجديدة';
$jsConfirmPassword = $lang === 'en' ? 'Confirm Password' : 'تأكيد كلمة المرور';
$jsSelectLogoFile = $lang === 'en' ? 'Select logo file (PNG recommended)' : 'اختر ملف الشعار (PNG مفضل)';
$jsUploadLogo = $lang === 'en' ? 'Upload Logo' : 'رفع الشعار';
$jsEditCompany = $lang === 'en' ? 'Edit Company' : 'تعديل الشركة';
$jsDeleteCompany = $lang === 'en' ? 'Delete Company' : 'حذف الشركة';
$jsCompanyName = $lang === 'en' ? 'Company Name' : 'اسم الشركة';
$jsCompanyAddress = $lang === 'en' ? 'Company Address' : 'عنوان الشركة';
$jsCompanyIndustry = $lang === 'en' ? 'Industry' : 'مجال العمل';
$jsCompanyPhone = $lang === 'en' ? 'Phone Number' : 'رقم الهاتف';
$jsReverseCoded = $lang === 'en' ? 'Reverse Coded' : 'معكوس';
$jsReverseCodedHelp = $lang === 'en' ? 'Check this if the question is reverse coded (Agree = Negative, Disagree = Positive)' : 'حدد هذا إذا كان السؤال معكوساً (الموافقة = نتيجة سلبية، عدم الموافقة = نتيجة إيجابية)';

// ============================================================
// ===== معالجة AJAX لتحميل الشركات (Infinite Scroll) =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_load_companies'])) {
    header('Content-Type: application/json');
    
    $page = intval($_POST['page'] ?? 1);
    $perPage = intval($_POST['per_page'] ?? 20);
    $offset = ($page - 1) * $perPage;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, address, industry, phone, created_at 
            FROM companies 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        $companiesList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM companies");
        $totalResult = $stmt->fetch();
        $totalCount = intval($totalResult['total']);
        
        $response = [
            'success' => true,
            'companies' => $companiesList,
            'total' => $totalCount,
            'has_more' => ($offset + $perPage) < $totalCount,
            'page' => $page
        ];
    } catch (PDOException $e) {
        logError($e->getMessage(), __FILE__, __LINE__);
        $response = [
            'success' => false,
            'message' => $lang === 'en' ? 'Error loading companies' : 'خطأ في تحميل الشركات'
        ];
    }
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $isRtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#4A90E2">
    <title><?php echo $lang === 'en' ? 'Admin Dashboard' : 'لوحة التحكم - المشرف'; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4A90E2;
            --primary-dark: #357ABD;
            --primary-light: #6BA8F0;
            --gradient-start: #4A90E2;
            --gradient-end: #357ABD;
            --sidebar-bg: #1a2332;
            --text-dark: #2C3E50;
            --text-light: #7F8C8D;
            --bg-light: #F5F7FA;
            --card-shadow: 0 10px 40px rgba(0,0,0,0.08);
            --border-radius: 16px;
            --danger-color: #e74c3c;
            --danger-dark: #c0392b;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>; background: var(--bg-light); min-height:100vh; display:flex; }
        
        .sidebar { width:280px; min-height:100vh; background:var(--sidebar-bg); position:fixed; top:0; <?php echo $isRtl ? 'right' : 'left'; ?>:0; height:100%; z-index:1000; overflow-y:auto; box-shadow:4px 0 20px rgba(0,0,0,0.1); transition:0.3s; }
        .sidebar-brand { padding:25px 20px; border-bottom:1px solid rgba(255,255,255,0.05); display:flex; align-items:center; gap:12px; }
        .sidebar-brand .brand-icon { width:45px; height:45px; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); border-radius:12px; display:flex; align-items:center; justify-content:center; }
        .sidebar-brand .brand-icon i { font-size:22px; color:white; }
        .sidebar-brand .brand-text { color:white; font-weight:700; font-size:18px; }
        .sidebar-brand .brand-sub { color:rgba(255,255,255,0.5); font-size:12px; }
        .sidebar-menu { padding:20px 0; list-style:none; }
        .sidebar-menu li a { display:flex; align-items:center; gap:12px; padding:14px 24px; color:rgba(255,255,255,0.6); text-decoration:none; font-weight:500; font-size:15px; border-<?php echo $isRtl ? 'right' : 'left'; ?>:3px solid transparent; cursor:pointer; transition:0.3s; }
        .sidebar-menu li a:hover { color:white; background:rgba(255,255,255,0.05); }
        .sidebar-menu li a.active { color:white; background:rgba(74,144,226,0.15); border-<?php echo $isRtl ? 'right' : 'left'; ?>-color:var(--primary-color); }
        .sidebar-menu li a i { width:20px; text-align:center; }
        .sidebar-footer { padding:20px 24px; border-top:1px solid rgba(255,255,255,0.05); margin-top:auto; }
        .sidebar-footer .user-info { display:flex; align-items:center; gap:12px; }
        .sidebar-footer .user-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:18px; }
        .sidebar-footer .user-name { color:white; font-weight:600; font-size:14px; }
        .sidebar-footer .user-role { color:rgba(255,255,255,0.4); font-size:12px; }
        .sidebar-footer .logout-btn { display:flex; align-items:center; gap:8px; padding:10px 16px; background:rgba(255,255,255,0.05); border-radius:10px; color:rgba(255,255,255,0.6); text-decoration:none; transition:0.3s; margin-top:12px; }
        .sidebar-footer .logout-btn:hover { background:rgba(231,76,60,0.2); color:#e74c3c; }
        .sidebar-footer .change-password-btn { display:flex; align-items:center; gap:8px; padding:10px 16px; background:rgba(255,255,255,0.05); border-radius:10px; color:rgba(255,255,255,0.6); text-decoration:none; transition:0.3s; margin-top:6px; cursor:pointer; border:none; width:100%; font-family:inherit; font-size:14px; }
        .sidebar-footer .change-password-btn:hover { background:rgba(74,144,226,0.2); color:var(--primary-light); }
        
        .main-content { flex:1; margin-<?php echo $isRtl ? 'right' : 'left'; ?>:280px; padding:0; min-height:100vh; }
        
        .top-bar { background:white; padding:16px 30px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #E8ECF1; position:sticky; top:0; z-index:100; box-shadow:0 2px 10px rgba(0,0,0,0.02); }
        .top-bar .page-title { font-weight:700; font-size:22px; color:var(--text-dark); margin:0; }
        .top-bar .page-title i { color:var(--primary-color); margin-<?php echo $isRtl ? 'left' : 'right'; ?>:10px; }
        .top-bar .top-actions { display:flex; align-items:center; gap:15px; }
        .top-bar .top-actions .language-toggle { background:var(--bg-light); border:none; padding:8px 14px; border-radius:10px; font-weight:600; font-size:13px; color:var(--text-dark); cursor:pointer; transition:0.3s; }
        .top-bar .top-actions .language-toggle:hover { background:var(--primary-color); color:white; }
        .top-bar .top-actions .mobile-menu-toggle { display:none; background:none; border:none; font-size:24px; color:var(--text-dark); cursor:pointer; padding:5px; }
        
        .content-wrapper { padding:30px; }
        .section-card { background:white; border-radius:var(--border-radius); box-shadow:var(--card-shadow); padding:25px 30px; margin-bottom:30px; border:1px solid rgba(0,0,0,0.03); display:none; }
        .section-card.active { display:block; }
        .section-card .section-title { font-weight:700; font-size:18px; color:var(--text-dark); margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .section-card .section-title i { color:var(--primary-color); font-size:20px; }
        .section-card .section-title .badge-count { background:var(--bg-light); color:var(--text-light); font-size:13px; padding:2px 12px; border-radius:12px; font-weight:600; margin-<?php echo $isRtl ? 'right' : 'left'; ?>:auto; }
        
        .form-group { margin-bottom:20px; }
        .form-group label { font-weight:600; color:var(--text-dark); font-size:14px; margin-bottom:6px; display:block; }
        .form-group label .required { color:#e74c3c; margin-<?php echo $isRtl ? 'right' : 'left'; ?>:4px; }
        .form-control, .form-select { border-radius:10px; border:2px solid #E8ECF1; padding:10px 14px; font-size:14px; transition:0.3s; background:white; }
        .form-control:focus, .form-select:focus { border-color:var(--primary-color); box-shadow:0 0 0 4px rgba(74,144,226,0.1); }
        
        .btn-primary { background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); border:none; padding:10px 24px; border-radius:10px; font-weight:600; transition:0.3s; color:white; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(74,144,226,0.3); color:white; }
        .btn-success { background:linear-gradient(135deg,#27ae60,#2ecc71); border:none; padding:10px 24px; border-radius:10px; font-weight:600; transition:0.3s; color:white; }
        .btn-success:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(46,204,113,0.3); color:white; }
        .btn-success:disabled { opacity:0.5; cursor:not-allowed; transform:none !important; box-shadow:none !important; }
        .btn-warning-custom { background:linear-gradient(135deg,#f39c12,#e67e22); border:none; padding:10px 24px; border-radius:10px; font-weight:600; transition:0.3s; color:white; }
        .btn-warning-custom:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(243,156,18,0.3); color:white; }
        .btn-danger-custom { background:linear-gradient(135deg,#e74c3c,#c0392b); border:none; padding:8px 16px; border-radius:8px; font-weight:600; transition:0.3s; color:white; font-size:13px; }
        .btn-danger-custom:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(231,76,60,0.3); color:white; }
        .btn-outline-danger-custom { background:transparent; border:2px solid #e74c3c; padding:8px 16px; border-radius:8px; font-weight:600; transition:0.3s; color:#e74c3c; font-size:13px; }
        .btn-outline-danger-custom:hover { background:#e74c3c; color:white; transform:translateY(-2px); box-shadow:0 8px 25px rgba(231,76,60,0.3); }
        .btn-outline-primary-custom { background:transparent; border:2px solid var(--primary-color); padding:8px 16px; border-radius:8px; font-weight:600; transition:0.3s; color:var(--primary-color); font-size:13px; }
        .btn-outline-primary-custom:hover { background:var(--primary-color); color:white; transform:translateY(-2px); box-shadow:0 8px 25px rgba(74,144,226,0.3); }
        
        .alert-custom { border-radius:12px; padding:14px 18px; font-weight:500; display:flex; align-items:center; gap:12px; margin-bottom:20px; }
        .alert-custom i { font-size:20px; }
        .alert-success-custom { background:#d4edda; border:1px solid #c3e6cb; color:#155724; }
        .alert-danger-custom { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; }
        .alert-warning-custom { background:#fff3cd; border:1px solid #ffeaa7; color:#856404; }
        .alert-info-custom { background:#d1ecf1; border:1px solid #bee5eb; color:#0c5460; }
        
        .ui-autocomplete { max-height:200px; overflow-y:auto; border-radius:10px; border:2px solid var(--primary-color); box-shadow:0 10px 30px rgba(0,0,0,0.1); z-index:9999; }
        .ui-autocomplete .ui-menu-item { padding:10px 14px; font-size:14px; border-bottom:1px solid #f0f0f0; }
        .ui-autocomplete .ui-menu-item.ui-state-active { background:var(--primary-color); color:white; }
        
        .accounts-table-wrapper { max-height:400px; overflow-y:auto; }
        .accounts-table-wrapper .table th { position:sticky; top:0; background:white; z-index:10; }
        .text-muted-light { color:#B0B8C4; }
        
        .mobile-bottom-nav { display:none; position:fixed; bottom:0; left:0; right:0; background:white; border-top:1px solid #E8ECF1; padding:8px 0; z-index:999; justify-content:space-around; align-items:center; box-shadow:0 -5px 20px rgba(0,0,0,0.05); }
        .mobile-bottom-nav .nav-item { display:flex; flex-direction:column; align-items:center; text-decoration:none; color:var(--text-light); font-size:10px; padding:4px 12px; transition:0.3s; border:none; background:none; cursor:pointer; }
        .mobile-bottom-nav .nav-item i { font-size:20px; margin-bottom:2px; }
        .mobile-bottom-nav .nav-item.active { color:var(--primary-color); }
        .mobile-bottom-nav .nav-item:hover { color:var(--primary-color); }
        .sidebar-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:999; }
        
        #accountsDisplay { animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        
        .loading-spinner { display:inline-block; width:20px; height:20px; border:3px solid rgba(255,255,255,0.3); border-radius:50%; border-top-color:#fff; animation:spin 1s ease-in-out infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        
        /* Question management */
        .question-table-wrapper { max-height:500px; overflow-y:auto; }
        .question-table-wrapper .table th { position:sticky; top:0; background:white; z-index:10; box-shadow:0 2px 4px rgba(0,0,0,0.05); }
        .question-item { transition:0.3s; }
        .question-item:hover { background:rgba(74,144,226,0.03); }
        .badge-option { font-size:11px; padding:3px 10px; border-radius:12px; }
        .badge-option.na { background:#fff3cd; color:#856404; }
        .badge-option.noplan { background:#fce4ec; color:#c62828; }
        .badge-option.reverse { background:#e8daef; color:#6c3483; }
        .badge-type { font-size:10px; padding:2px 8px; border-radius:10px; }
        .badge-type.likert { background:#d4edda; color:#155724; }
        .badge-type.text { background:#cce5ff; color:#004085; }
        .badge-type.choice { background:#fff3cd; color:#856404; }
        .badge-type.ranking { background:#fce4ec; color:#c62828; }
        .badge-type.numeric { background:#e8daef; color:#6c3483; }
        
        .edit-form { background:#f8f9fa; border-radius:12px; padding:15px; margin-top:10px; border:1px solid #e9ecef; display:none; }
        .edit-form.active { display:block; }
        
        /* Companies Table with Infinite Scroll */
        .companies-table-wrapper { max-height:500px; overflow-y:auto; position:relative; }
        .companies-table-wrapper .table th { position:sticky; top:0; background:white; z-index:10; box-shadow:0 2px 4px rgba(0,0,0,0.05); }
        .company-row { transition:background 0.3s ease; }
        .company-row:hover { background:rgba(74,144,226,0.03); }
        .company-row .created-at { font-size:12px; color:var(--text-light); }
        
        .infinite-scroll-trigger { text-align:center; padding:15px 0; color:var(--text-light); font-size:14px; display:none; }
        .infinite-scroll-trigger.active { display:block; }
        .infinite-scroll-trigger .spinner-border { width:30px; height:30px; border-width:3px; color:var(--primary-color); }
        .infinite-scroll-trigger .no-more { color:var(--text-light); font-weight:500; }
        .infinite-scroll-trigger .no-more i { margin-<?php echo $isRtl ? 'left' : 'right'; ?>:6px; }
        .infinite-scroll-trigger .error-text { color:#e74c3c; }
        
        .companies-count-badge { font-size:13px; background:var(--bg-light); color:var(--text-light); padding:2px 12px; border-radius:12px; font-weight:600; }
        
        /* Options for choice/ranking questions */
        .options-container { margin-top:10px; padding:10px; background:#f8f9fa; border-radius:8px; border:1px solid #e9ecef; }
        .option-row { display:flex; gap:10px; align-items:center; margin-bottom:8px; }
        .option-row input { flex:1; }
        .option-row .btn-remove-option { flex-shrink:0; }
        .btn-add-option { margin-top:8px; }
        
        /* Delete button for sections */
        .btn-delete-section {
            background:transparent;
            border:2px solid #e74c3c;
            padding:4px 12px;
            border-radius:6px;
            font-weight:600;
            transition:0.3s;
            color:#e74c3c;
            font-size:12px;
            cursor:pointer;
        }
        .btn-delete-section:hover {
            background:#e74c3c;
            color:white;
            transform:translateY(-2px);
            box-shadow:0 4px 15px rgba(231,76,60,0.3);
        }
        
        /* Poll delete button */
        .btn-delete-poll {
            background:transparent;
            border:2px solid #e74c3c;
            padding:4px 12px;
            border-radius:6px;
            font-weight:600;
            transition:0.3s;
            color:#e74c3c;
            font-size:12px;
            cursor:pointer;
        }
        .btn-delete-poll:hover {
            background:#e74c3c;
            color:white;
            transform:translateY(-2px);
            box-shadow:0 4px 15px rgba(231,76,60,0.3);
        }
        
        /* Company delete button */
        .btn-delete-company {
            background:transparent;
            border:2px solid #e74c3c;
            padding:4px 12px;
            border-radius:6px;
            font-weight:600;
            transition:0.3s;
            color:#e74c3c;
            font-size:12px;
            cursor:pointer;
        }
        .btn-delete-company:hover {
            background:#e74c3c;
            color:white;
            transform:translateY(-2px);
            box-shadow:0 4px 15px rgba(231,76,60,0.3);
        }
        
        /* Company edit button */
        .btn-edit-company {
            background:transparent;
            border:2px solid var(--primary-color);
            padding:4px 12px;
            border-radius:6px;
            font-weight:600;
            transition:0.3s;
            color:var(--primary-color);
            font-size:12px;
            cursor:pointer;
        }
        .btn-edit-company:hover {
            background:var(--primary-color);
            color:white;
            transform:translateY(-2px);
            box-shadow:0 4px 15px rgba(74,144,226,0.3);
        }
        
        /* Modal styles */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
        }
        .modal-header {
            border-bottom: 2px solid #f0f0f0;
            padding: 20px 25px;
        }
        .modal-header .modal-title {
            font-weight: 700;
            font-size: 20px;
            color: var(--text-dark);
        }
        .modal-body {
            padding: 25px;
        }
        .modal-footer {
            border-top: 2px solid #f0f0f0;
            padding: 20px 25px;
        }
        
        .logo-preview {
            max-width: 200px;
            max-height: 100px;
            border: 2px solid #E8ECF1;
            border-radius: 10px;
            padding: 10px;
            background: white;
            object-fit: contain;
        }
        
        /* Poll type badges */
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
        
        @media (max-width:991px) {
            .sidebar { transform:translateX(<?php echo $isRtl ? '280px' : '-280px'; ?>); width:280px; }
            .sidebar.open { transform:translateX(0); }
            .sidebar-overlay.active { display:block; }
            .main-content { margin-<?php echo $isRtl ? 'right' : 'left'; ?>:0; }
            .top-bar .top-actions .mobile-menu-toggle { display:block; }
            .mobile-bottom-nav { display:flex; }
            .content-wrapper { padding:20px 16px; padding-bottom:80px; }
            .section-card { padding:20px; margin-bottom:20px; }
            .top-bar { padding:12px 16px; }
            .top-bar .page-title { font-size:18px; }
            .top-bar .top-actions .language-toggle span { display:none; }
            .companies-table-wrapper { max-height:400px; }
        }
        @media (max-width:576px) {
            .section-card { padding:16px; }
            .section-card .section-title { font-size:16px; }
            .top-bar .page-title { font-size:16px; }
            .form-control, .form-select { font-size:13px; padding:8px 12px; }
            .btn { font-size:13px; padding:8px 16px; }
            .companies-table-wrapper { max-height:350px; }
            .btn-delete-section, .btn-delete-poll, .btn-delete-company, .btn-edit-company { font-size:10px; padding:3px 8px; }
            .modal-content { margin:10px; }
        }
        
        @media (prefers-color-scheme:dark) {
            .top-bar { background:#1a2332; border-bottom-color:rgba(255,255,255,0.05); }
            .top-bar .page-title { color:#ECF0F1; }
            .top-bar .top-actions .language-toggle { background:rgba(255,255,255,0.05); color:#ECF0F1; }
            .section-card { background:#1a2332; border-color:rgba(255,255,255,0.05); }
            .section-card .section-title { color:#ECF0F1; }
            .form-control, .form-select { background:rgba(255,255,255,0.05); border-color:rgba(255,255,255,0.1); color:#ECF0F1; }
            .form-group label { color:#ECF0F1; }
            .mobile-bottom-nav { background:#1a2332; border-top-color:rgba(255,255,255,0.05); }
            .mobile-bottom-nav .nav-item { color:rgba(255,255,255,0.4); }
            .mobile-bottom-nav .nav-item.active { color:var(--primary-color); }
            .accounts-table-wrapper .table th { background:#1a2332; color:#ECF0F1; }
            .table td { color:#ECF0F1; border-bottom-color:rgba(255,255,255,0.05); }
            .question-table-wrapper .table th { background:#1a2332; color:#ECF0F1; }
            .edit-form { background:rgba(255,255,255,0.05); border-color:rgba(255,255,255,0.1); }
            .badge-option.na { background:rgba(255,193,7,0.2); color:#ffc107; }
            .badge-option.noplan { background:rgba(198,40,40,0.2); color:#ef9a9a; }
            .badge-option.reverse { background:rgba(128,0,128,0.2); color:#c77dff; }
            .companies-table-wrapper .table th { background:#1a2332; color:#ECF0F1; }
            .company-row:hover { background:rgba(255,255,255,0.03); }
            .company-row .created-at { color:#A0A8B4; }
            .companies-count-badge { background:rgba(255,255,255,0.05); color:#A0A8B4; }
            .infinite-scroll-trigger { color:#A0A8B4; }
            .options-container { background:rgba(255,255,255,0.03); border-color:rgba(255,255,255,0.05); }
            .badge-type.likert { background:rgba(40,167,69,0.2); color:#75b798; }
            .badge-type.text { background:rgba(0,123,255,0.2); color:#6ea8fe; }
            .badge-type.choice { background:rgba(255,193,7,0.2); color:#ffc107; }
            .badge-type.ranking { background:rgba(220,53,69,0.2); color:#ea868f; }
            .badge-type.numeric { background:rgba(128,0,128,0.2); color:#c77dff; }
            .poll-type-badge.regular { background:rgba(40,167,69,0.2); color:#75b798; }
            .poll-type-badge.admin { background:rgba(220,53,69,0.2); color:#ea868f; }
            .poll-type-badge.personal { background:rgba(0,123,255,0.2); color:#6ea8fe; }
            .poll-type-badge.executive { background:rgba(128,0,128,0.2); color:#c77dff; }
            .btn-delete-section, .btn-delete-poll, .btn-delete-company {
                border-color:#e74c3c;
                color:#e74c3c;
            }
            .btn-delete-section:hover, .btn-delete-poll:hover, .btn-delete-company:hover {
                background:#e74c3c;
                color:white;
            }
            .btn-edit-company {
                border-color:var(--primary-color);
                color:var(--primary-light);
            }
            .btn-edit-company:hover {
                background:var(--primary-color);
                color:white;
            }
            .modal-content { background:#1a2332; }
            .modal-header { border-bottom-color:rgba(255,255,255,0.05); }
            .modal-header .modal-title { color:#ECF0F1; }
            .modal-footer { border-top-color:rgba(255,255,255,0.05); }
            .btn-close { filter: invert(1); }
            .logo-preview { background:#1a2332; border-color:rgba(255,255,255,0.1); }
            .alert-success-custom { background:rgba(212,237,218,0.15); border-color:rgba(195,230,203,0.2); color:#75b798; }
            .alert-danger-custom { background:rgba(248,215,218,0.15); border-color:rgba(245,198,203,0.2); color:#ea868f; }
            .alert-warning-custom { background:rgba(255,243,205,0.15); border-color:rgba(255,234,167,0.2); color:#ffc107; }
            .alert-info-custom { background:rgba(209,236,241,0.15); border-color:rgba(190,229,235,0.2); color:#6ea8fe; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ===== Sidebar ===== -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-chart-pie"></i></div>
        <div>
            <div class="brand-text"><?php echo $lang === 'en' ? 'Manage Surveys' : 'إدارة الاستبيانات'; ?></div>
            <div class="brand-sub"><?php echo $lang === 'en' ? 'Corporate Culture' : 'ثقافة الشركات'; ?></div>
        </div>
    </div>
    
    <ul class="sidebar-menu"> 
        <li style="color:white;"><a data-section="companies"><i class="fas fa-building"></i> <span><?php echo $lang === 'en' ? 'Companies' : 'الشركات'; ?></span></a></li> 
        <li style="color:white;"><a data-section="polls"><i class="fas fa-poll"></i> <span><?php echo $lang === 'en' ? 'Polls' : 'الاستبيانات'; ?></span></a></li>
        <li style="color:white;"><a data-section="questions"><i class="fas fa-question-circle"></i> <span><?php echo $lang === 'en' ? 'Questions' : 'الأسئلة'; ?></span></a></li>  
        <li style="color:white;"><a data-section="search"><i class="fas fa-search"></i> <span><?php echo $lang === 'en' ? 'Search' : 'بحث'; ?></span></a></li> 
        <li style="color:white;"><a data-section="accounts"><i class="fas fa-users"></i> <span><?php echo $lang === 'en' ? 'Accounts' : 'الحسابات'; ?></span></a></li>
        <li style="color:white;"><a data-section="settings"><i class="fas fa-cog"></i> <span><?php echo $lang === 'en' ? 'Settings' : 'الإعدادات'; ?></span></a></li>
    </ul> 
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($adminUsername, 0, 1)); ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($adminUsername); ?></div>
                <div class="user-role"><?php echo $lang === 'en' ? 'Administrator' : 'مشرف'; ?></div>
            </div>
        </div>
        <button class="change-password-btn" onclick="openChangePasswordModal()">
            <i class="fas fa-key"></i> <span><?php echo $jsChangePassword; ?></span>
        </button>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> <span><?php echo $lang === 'en' ? 'Logout' : 'تسجيل الخروج'; ?></span></a>
    </div>
</div>

<!-- ===== Main Content ===== -->
<div class="main-content">
    <div class="top-bar">
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> <span id="pageTitle"><?php echo $jsTitleCompanies; ?></span></h1>
        <div class="top-actions">
            <button class="language-toggle" onclick="switchLanguage()">
                <i class="fas fa-globe"></i> <span><?php echo $lang === 'ar' ? 'English' : 'العربية'; ?></span>
            </button>
            <button class="mobile-menu-toggle" id="mobileMenuToggle"><i class="fas fa-bars"></i></button>
        </div>
    </div>
    
    <div class="content-wrapper">
        
        <!-- ===== رسائل النجاح والخطأ ===== -->
        <div id="alertContainer">
            <?php if ($successMessage): ?>
            <div class="alert-custom alert-success-custom"><i class="fas fa-check-circle"></i> <span><?php echo $successMessage; ?></span></div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
            <div class="alert-custom alert-danger-custom"><i class="fas fa-exclamation-circle"></i> <span><?php echo $errorMessage; ?></span></div>
            <?php endif; ?>
            
            <?php if ($passwordMessage): ?>
            <div class="alert-custom <?php echo strpos($passwordMessage, 'success') !== false ? 'alert-success-custom' : 'alert-danger-custom'; ?>">
                <i class="fas fa-<?php echo strpos($passwordMessage, 'success') !== false ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $passwordMessage; ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($logoMessage): ?>
            <div class="alert-custom <?php echo strpos($logoMessage, 'success') !== false ? 'alert-success-custom' : 'alert-danger-custom'; ?>">
                <i class="fas fa-<?php echo strpos($logoMessage, 'success') !== false ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <span><?php echo $logoMessage; ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ============================================================ -->
        <!-- ===== القسم 1: إضافة شركة ===== -->
        <!-- ============================================================ -->
        <div class="section-card" id="section-companies" data-section="companies">
            <div class="section-title">
                <i class="fas fa-plus-circle"></i> 
                <span><?php echo $lang === 'en' ? 'Add New Company' : 'إضافة شركة جديدة'; ?></span>
                <span class="badge-count"><?php echo count($companies); ?></span>
            </div>
            <form method="POST" action="" class="row g-3">
                <div class="col-md-6">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Company Name' : 'اسم الشركة'; ?> <span class="required">*</span></label>
                        <input type="text" class="form-control" name="company_name" required placeholder="<?php echo $lang === 'en' ? 'Enter company name' : 'أدخل اسم الشركة'; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Industry' : 'مجال العمل'; ?></label>
                        <input type="text" class="form-control" name="industry" placeholder="<?php echo $lang === 'en' ? 'e.g., Technology, Healthcare' : 'مثال: تقنية، رعاية صحية'; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Address' : 'عنوان الشركة'; ?></label>
                        <input type="text" class="form-control" name="company_address" placeholder="<?php echo $lang === 'en' ? 'Enter company address' : 'أدخل عنوان الشركة'; ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Phone' : 'رقم الهاتف'; ?></label>
                        <input type="tel" class="form-control" name="phone" placeholder="<?php echo $lang === 'en' ? 'Enter phone number' : 'أدخل رقم الهاتف'; ?>">
                    </div>
                </div>
                <div class="col-12"><button type="submit" name="add_company" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Add Company' : 'إضافة الشركة'; ?></button></div>
            </form>
            
            <!-- ===== جدول الشركات مع Infinite Scroll ===== -->
            <div class="mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">
                        <i class="fas fa-list"></i> 
                        <?php echo $lang === 'en' ? 'Companies List' : 'قائمة الشركات'; ?>
                        <span class="companies-count-badge" id="companiesTotalCount">
                            <?php echo $companiesTotalCount; ?>
                        </span>
                    </h6>
                    <small class="text-muted">
                        <i class="fas fa-arrow-down"></i>
                        <?php echo $lang === 'en' ? 'Scroll down to load more' : 'انزل للأسفل لتحميل المزيد'; ?>
                    </small>
                </div>
                
                <div class="companies-table-wrapper" id="companiesTableWrapper">
                    <table class="table table-hover" id="companiesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?php echo $lang === 'en' ? 'Company Name' : 'اسم الشركة'; ?></th>
                                <th><?php echo $lang === 'en' ? 'Industry' : 'المجال'; ?></th>
                                <th><?php echo $lang === 'en' ? 'Added Date' : 'تاريخ الإضافة'; ?></th>
                                <th><?php echo $lang === 'en' ? 'Actions' : 'الإجراءات'; ?></th>
                            </tr>
                        </thead>
                        <tbody id="companiesTableBody">
                            <?php if (empty($companies)): ?>
                            <tr id="emptyCompaniesRow">
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fas fa-building fa-2x d-block mb-2 opacity-50"></i>
                                    <?php echo $lang === 'en' ? 'No companies found. Add your first company above!' : 'لا توجد شركات. أضف شركتك الأولى أعلاه!'; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php $counter = 1; foreach ($companies as $company): ?>
                                <tr class="company-row" data-id="<?php echo $company['id']; ?>">
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($company['name']); ?></strong>
                                        <?php if (!empty($company['address'])): ?>
                                        <br><small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($company['address']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($company['industry'] ?? '-'); ?></td>
                                    <td class="created-at">
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo date('Y-m-d H:i', strtotime($company['created_at'])); ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="viewCompany(<?php echo $company['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-edit-company" onclick="openEditCompanyModal(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['name']); ?>', '<?php echo htmlspecialchars($company['address'] ?? ''); ?>', '<?php echo htmlspecialchars($company['industry'] ?? ''); ?>', '<?php echo htmlspecialchars($company['phone'] ?? ''); ?>')">
                                            <i class="fas fa-edit"></i> <?php echo $jsEditCompany; ?>
                                        </button>
                                        <button class="btn btn-sm btn-delete-company" onclick="deleteCompany(<?php echo $company['id']; ?>)">
                                            <i class="fas fa-trash"></i> <?php echo $jsDeleteCompany; ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- ===== Infinite Scroll Trigger ===== -->
                    <div class="infinite-scroll-trigger" id="infiniteScrollTrigger">
                        <div id="scrollLoader" style="display:none;">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden"><?php echo $jsLoadingMore; ?></span>
                            </div>
                            <div class="mt-2"><?php echo $jsLoadingMore; ?></div>
                        </div>
                        <div id="scrollNoMore" style="display:none;">
                            <div class="no-more">
                                <i class="fas fa-check-circle text-success"></i>
                                <?php echo $jsNoMoreCompanies; ?>
                            </div>
                        </div>
                        <div id="scrollError" style="display:none;">
                            <div class="no-more error-text">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $jsLoadError; ?>
                                <button class="btn btn-sm btn-link" onclick="loadMoreCompanies()"><?php echo $lang === 'en' ? 'Retry' : 'إعادة المحاولة'; ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- ===== القسم 2: إضافة استبيان ===== -->
        <!-- ============================================================ -->
        <div class="section-card" id="section-polls" data-section="polls">
            <div class="section-title"><i class="fas fa-poll"></i> <span><?php echo $lang === 'en' ? 'Add New Poll' : 'إضافة استبيان جديد'; ?></span> <span class="badge-count"><?php echo count($polls); ?></span></div>           
            <form method="POST" action="" class="row g-3">
                <div class="col-md-6">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Company' : 'الشركة'; ?> <span class="required">*</span></label>
                        <input type="text" class="form-control" id="companyAutocomplete" placeholder="<?php echo $lang === 'en' ? 'Type company name...' : 'اكتب اسم الشركة...'; ?>" autocomplete="off">
                        <input type="hidden" name="company_id" id="selectedCompanyId" value="">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Poll Date' : 'تاريخ الاستبيان'; ?> <span class="required">*</span></label>
                        <input type="date" class="form-control" name="poll_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Departments Participating' : 'وصف الأقسام المشاركة'; ?></label>
                        <input type="text" class="form-control" name="departments" placeholder="<?php echo $lang === 'en' ? 'e.g., HR, IT, Marketing' : 'مثال: الموارد البشرية، تقنية المعلومات، التسويق'; ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Participants Count' : 'عدد المشاركين'; ?> <span class="required">*</span></label>
                        <input type="number" class="form-control" name="participant_count" required min="4" max="999" value="4" placeholder="4-999">
                        <small class="text-muted-light"><?php echo $lang === 'en' ? 'Min 4, Max 999' : 'الحد الأدنى 4، الحد الأقصى 999'; ?></small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Poll Type' : 'نوع الاستبيان'; ?> <span class="required">*</span></label>
                        <select class="form-select" name="poll_type" required>
                            <option value="regular"><?php echo $jsRegular; ?></option>
                            <option value="admin"><?php echo $jsAdmin; ?></option>
                            <option value="personal"><?php echo $jsPersonal; ?></option>
                            <option value="executive"><?php echo $jsExecutive; ?></option>
                        </select>
                    </div>
                </div>
                <div class="col-12"><button type="submit" name="add_poll" class="btn btn-primary"><i class="fas fa-plus-circle"></i> <?php echo $lang === 'en' ? 'Add Poll' : 'إضافة الاستبيان'; ?></button></div>
            </form>
            <br><br>        
            <!-- ===== عرض الاستبيانات الموجودة ===== -->
            <?php if (!empty($polls)): ?>
            <div class="mb-4">
                <h6 class="mb-2"><?php echo $lang === 'en' ? 'Existing Polls' : 'الاستبيانات الموجودة'; ?></h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th><?php echo $lang === 'en' ? 'Company' : 'الشركة'; ?></th>
                                <th><?php echo $lang === 'en' ? 'Date' : 'التاريخ'; ?></th>
                                <th><?php echo $lang === 'en' ? 'Type' : 'النوع'; ?></th>
                                <th><?php echo $lang === 'en' ? 'Participants' : 'المشاركون'; ?></th>
                                <th><?php echo $lang === 'en' ? 'Action' : 'الإجراء'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($polls as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['company_name']); ?></td>
                                <td><?php echo $p['date']; ?></td>
                                <td>
                                    <span class="poll-type-badge <?php echo $p['type']; ?>">
                                        <?php echo getPollTypeLabel($p['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $p['participant_count']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="viewPoll(<?php echo $p['id']; ?>)">
                                        <i class="fas fa-chart-bar"></i> <?php echo $jsView; ?>
                                    </button>
                                    <button class="btn-delete-poll ms-1" onclick="deletePoll(<?php echo $p['id']; ?>, <?php echo $p['company_id']; ?>)">
                                        <i class="fas fa-trash"></i> <?php echo $jsDeletePoll; ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ============================================================ -->
        <!-- ===== القسم 3: إدارة الأسئلة والمحاور ===== -->
        <!-- ============================================================ -->
        <div class="section-card" id="section-questions" data-section="questions">
            <div class="section-title">
                <i class="fas fa-question-circle"></i>
                <span><?php echo $lang === 'en' ? 'Questions & Sections Management' : 'إدارة الأسئلة والمحاور'; ?></span>
            </div>
            
            <!-- ===== تبويبات الأسئلة ===== -->
            <ul class="nav nav-tabs mb-4" id="questionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="regular-tab" data-bs-toggle="tab" data-bs-target="#regularQuestions" type="button" role="tab">
                        <i class="fas fa-file-alt"></i> <?php echo $lang === 'en' ? 'Regular' : 'عادي'; ?>
                        <span class="badge bg-primary ms-1"><?php echo count($regularQuestions); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#adminQuestions" type="button" role="tab">
                        <i class="fas fa-user-tie"></i> <?php echo $lang === 'en' ? 'Admin' : 'إداري'; ?>
                        <span class="badge bg-danger ms-1"><?php echo count($adminQuestions); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personalQuestions" type="button" role="tab">
                        <i class="fas fa-user"></i> <?php echo $lang === 'en' ? 'Personal' : 'شخصي'; ?>
                        <span class="badge bg-info ms-1"><?php echo count($personalQuestions); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="executive-tab" data-bs-toggle="tab" data-bs-target="#executiveQuestions" type="button" role="tab">
                        <i class="fas fa-user-graduate"></i> <?php echo $lang === 'en' ? 'Executive' : 'تنفيذي'; ?>
                        <span class="badge bg-success ms-1"><?php echo count($executiveQuestions); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sectionsManagement" type="button" role="tab">
                        <i class="fas fa-layer-group"></i> <?php echo $lang === 'en' ? 'Sections' : 'المحاور'; ?>
                        <span class="badge bg-secondary ms-1"><?php echo count($sections); ?></span>
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- ===== الأسئلة العادية ===== -->
                <div class="tab-pane fade show active" id="regularQuestions" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0"><?php echo $lang === 'en' ? 'Regular Questions List' : 'قائمة الأسئلة العادية'; ?> <span class="badge bg-primary"><?php echo count($regularQuestions); ?></span></h6>
                        <button class="btn btn-primary btn-sm" onclick="showAddQuestionForm('regular')">
                            <i class="fas fa-plus"></i> <?php echo $lang === 'en' ? 'Add Question' : 'إضافة سؤال'; ?>
                        </button>
                    </div>
                    
                    <!-- نموذج إضافة سؤال عادي (مع دعم النتيجة العكسية) -->
                    <div id="addRegularQuestionForm" style="display:none;" class="edit-form active">
                        <h6><?php echo $lang === 'en' ? 'Add New Regular Question' : 'إضافة سؤال عادي جديد'; ?></h6>
                        <div class="alert alert-info alert-sm mb-3">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $lang === 'en' ? 'Question number will be assigned automatically per section.' : 'سيتم تعيين رقم السؤال تلقائياً حسب المحور.'; ?>
                        </div>
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="question_poll_type" value="regular">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="question_section_id" required>
                                        <option value=""><?php echo $lang === 'en' ? 'Select section...' : 'اختر المحور...'; ?></option>
                                        <?php foreach ($sections as $s): if ($s['poll_type'] !== 'regular') continue; ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo $lang === 'en' ? $s['title_en'] : $s['title_ar']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Type' : 'نوع السؤال'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="question_type" id="addQuestionType" onchange="toggleQuestionTypeFields('add')" required>
                                        <option value="likert"><?php echo $jsLikert; ?></option>
                                        <option value="text"><?php echo $jsText; ?></option>
                                        <option value="choice"><?php echo $jsChoice; ?></option>
                                        <option value="ranking"><?php echo $jsRanking; ?></option>
                                        <option value="numeric"><?php echo $jsNumeric; ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_reverse" id="addIsReverse" value="1">
                                        <label class="form-check-label" for="addIsReverse">
                                            <?php echo $jsReverseCoded; ?>
                                            <small class="text-muted d-block" style="font-size:11px;"><?php echo $jsReverseCodedHelp; ?></small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" id="addSpecialOptions">
                                    <label><?php echo $lang === 'en' ? 'Special Options' : 'خيارات خاصة'; ?></label>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_na_option" id="addHasNa" value="1">
                                            <label class="form-check-label" for="addHasNa">N/A</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_no_plan_option" id="addHasNoPlan" value="1">
                                            <label class="form-check-label" for="addHasNoPlan">No Plan</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Text (Arabic)' : 'نص السؤال (عربي)'; ?> <span class="required">*</span></label>
                                    <textarea class="form-control" name="question_text_ar" required rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Text (English)' : 'نص السؤال (إنجليزي)'; ?> <span class="required">*</span></label>
                                    <textarea class="form-control" name="question_text_en" required rows="2"></textarea>
                                </div>
                            </div>
                            
                            <!-- خيارات الإجابة -->
                            <div class="col-12" id="addOptionsContainer" style="display:none;">
                                <div class="options-container">
                                    <label class="fw-bold mb-2"><?php echo $lang === 'en' ? 'Answer Options' : 'خيارات الإجابة'; ?> <span class="required">*</span></label>
                                    <div id="addOptionsList">
                                        <div class="option-row">
                                            <input type="text" class="form-control" name="option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                            <input type="text" class="form-control" name="option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                            <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                        <div class="option-row">
                                            <input type="text" class="form-control" name="option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                            <input type="text" class="form-control" name="option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                            <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm btn-add-option" onclick="addOption('add')">
                                        <i class="fas fa-plus"></i> <?php echo $jsAddOption; ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" name="add_question" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Add Question' : 'إضافة السؤال'; ?></button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addRegularQuestionForm').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- جدول الأسئلة العادية (مع عرض حالة النتيجة العكسية) -->
                    <div class="question-table-wrapper mt-3">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $lang === 'en' ? 'Question' : 'السؤال'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Type' : 'النوع'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Options' : 'الخيارات'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Actions' : 'الإجراءات'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $currentSectionId = 0;
                                $sectionCounter = 0;
                                foreach ($regularQuestions as $q): 
                                    if ($q['section_id'] != $currentSectionId) {
                                        $currentSectionId = $q['section_id'];
                                        $sectionCounter = 0;
                                    }
                                    $sectionCounter++;
                                ?>
                                <tr class="question-item">
                                    <td><?php echo $sectionCounter; ?></td>
                                    <td>
                                        <?php echo $lang === 'en' ? htmlspecialchars($q['question_text_en']) : htmlspecialchars($q['question_text_ar']); ?>
                                        <?php if (isset($q['is_reverse']) && $q['is_reverse'] == 1): ?>
                                        <span class="badge-option reverse"><i class="fas fa-exchange-alt"></i> <?php echo $jsReverseCoded; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $lang === 'en' ? htmlspecialchars($q['section_title_en'] ?? '-') : htmlspecialchars($q['section_title_ar'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        $typeLabels = [
                                            'likert' => $jsLikert,
                                            'text' => $jsText,
                                            'choice' => $jsChoice,
                                            'ranking' => $jsRanking,
                                            'numeric' => $jsNumeric
                                        ];
                                        $typeClasses = [
                                            'likert' => 'likert',
                                            'text' => 'text',
                                            'choice' => 'choice',
                                            'ranking' => 'ranking',
                                            'numeric' => 'numeric'
                                        ];
                                        ?>
                                        <span class="badge-option badge-type <?php echo $typeClasses[$q['question_type']] ?? 'likert'; ?>">
                                            <?php echo $typeLabels[$q['question_type']] ?? $jsLikert; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($q['has_na_option']): ?>
                                        <span class="badge-option na">N/A</span>
                                        <?php endif; ?>
                                        <?php if ($q['has_no_plan_option']): ?>
                                        <span class="badge-option noplan">No Plan</span>
                                        <?php endif; ?>
                                        <?php if (!empty($q['options'])): ?>
                                        <span class="badge bg-secondary"><?php echo count($q['options']); ?> <?php echo $lang === 'en' ? 'options' : 'خيارات'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="editQuestion(<?php echo $q['id']; ?>, 'regular')"><i class="fas fa-edit"></i></button>
                                        <a href="?delete_question=<?php echo $q['id']; ?>&poll_type=regular#section-questions" class="btn btn-danger-custom btn-sm" onclick="return confirm('<?php echo $jsConfirmDelete; ?>')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <!-- نموذج تعديل (مع دعم النتيجة العكسية) -->
                                <tr id="editForm_<?php echo $q['id']; ?>" style="display:none;">
                                    <td colspan="6">
                                        <div class="edit-form active">
                                            <form method="POST" action="" class="row g-3">
                                                <input type="hidden" name="edit_question_id" value="<?php echo $q['id']; ?>">
                                                <input type="hidden" name="edit_question_poll_type" value="regular">
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Number' : 'الرقم'; ?></label>
                                                        <input type="number" class="form-control" name="edit_question_number" value="<?php echo $q['question_number']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?></label>
                                                        <select class="form-select" name="edit_question_section_id" required>
                                                            <?php foreach ($sections as $s): if ($s['poll_type'] !== 'regular') continue; ?>
                                                            <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $q['section_id'] ? 'selected' : ''; ?>>
                                                                <?php echo $lang === 'en' ? $s['title_en'] : $s['title_ar']; ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Question Type' : 'نوع السؤال'; ?></label>
                                                        <select class="form-select" name="edit_question_type" id="editQuestionType_<?php echo $q['id']; ?>" onchange="toggleQuestionTypeFields('edit', <?php echo $q['id']; ?>)">
                                                            <option value="likert" <?php echo $q['question_type'] === 'likert' ? 'selected' : ''; ?>><?php echo $jsLikert; ?></option>
                                                            <option value="text" <?php echo $q['question_type'] === 'text' ? 'selected' : ''; ?>><?php echo $jsText; ?></option>
                                                            <option value="choice" <?php echo $q['question_type'] === 'choice' ? 'selected' : ''; ?>><?php echo $jsChoice; ?></option>
                                                            <option value="ranking" <?php echo $q['question_type'] === 'ranking' ? 'selected' : ''; ?>><?php echo $jsRanking; ?></option>
                                                            <option value="numeric" <?php echo $q['question_type'] === 'numeric' ? 'selected' : ''; ?>><?php echo $jsNumeric; ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="edit_is_reverse" value="1" <?php echo (isset($q['is_reverse']) && $q['is_reverse'] == 1) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label"><?php echo $jsReverseCoded; ?></label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group" id="editSpecialOptions_<?php echo $q['id']; ?>">
                                                        <label><?php echo $lang === 'en' ? 'Special Options' : 'خيارات خاصة'; ?></label>
                                                        <div class="d-flex gap-3 flex-wrap">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="edit_has_na_option" value="1" <?php echo $q['has_na_option'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">N/A</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="edit_has_no_plan_option" value="1" <?php echo $q['has_no_plan_option'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">No Plan</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Text (Ar)' : 'النص (عربي)'; ?></label>
                                                        <textarea class="form-control" name="edit_question_text_ar" rows="2" required><?php echo htmlspecialchars($q['question_text_ar']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Text (En)' : 'النص (إنجليزي)'; ?></label>
                                                        <textarea class="form-control" name="edit_question_text_en" rows="2" required><?php echo htmlspecialchars($q['question_text_en']); ?></textarea>
                                                    </div>
                                                </div>
                                                
                                                <!-- خيارات الإجابة للتعديل -->
                                                <div class="col-12" id="editOptionsContainer_<?php echo $q['id']; ?>" style="display:<?php echo ($q['question_type'] === 'choice' || $q['question_type'] === 'ranking') ? 'block' : 'none'; ?>;">
                                                    <div class="options-container">
                                                        <label class="fw-bold mb-2"><?php echo $lang === 'en' ? 'Answer Options' : 'خيارات الإجابة'; ?> <span class="required">*</span></label>
                                                        <div id="editOptionsList_<?php echo $q['id']; ?>">
                                                            <?php 
                                                            $options = getQuestionOptions($pdo, $q['id'], 'regular');
                                                            if (!empty($options)):
                                                                foreach ($options as $opt):
                                                            ?>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" value="<?php echo htmlspecialchars($opt['option_text_ar']); ?>" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" value="<?php echo htmlspecialchars($opt['option_text_en']); ?>" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <?php 
                                                                endforeach;
                                                            else:
                                                            ?>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button type="button" class="btn btn-secondary btn-sm btn-add-option" onclick="addOption('edit', <?php echo $q['id']; ?>)">
                                                            <i class="fas fa-plus"></i> <?php echo $jsAddOption; ?>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <button type="submit" name="edit_question" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Update' : 'تحديث'; ?></button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('editForm_<?php echo $q['id']; ?>').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($regularQuestions)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo $lang === 'en' ? 'No regular questions found' : 'لا توجد أسئلة عادية'; ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ===== الأسئلة الإدارية ===== -->
                <div class="tab-pane fade" id="adminQuestions" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0"><?php echo $lang === 'en' ? 'Admin Questions List' : 'قائمة الأسئلة الإدارية'; ?> <span class="badge bg-danger"><?php echo count($adminQuestions); ?></span></h6>
                        <button class="btn btn-danger btn-sm" onclick="showAddQuestionForm('admin')">
                            <i class="fas fa-plus"></i> <?php echo $lang === 'en' ? 'Add Question' : 'إضافة سؤال'; ?>
                        </button>
                    </div>
                    
                    <!-- نموذج إضافة سؤال إداري (مع دعم النتيجة العكسية) -->
                    <div id="addAdminQuestionForm" style="display:none;" class="edit-form active">
                        <h6><?php echo $lang === 'en' ? 'Add New Admin Question' : 'إضافة سؤال إداري جديد'; ?></h6>
                        <div class="alert alert-info alert-sm mb-3">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $lang === 'en' ? 'Question number will be assigned automatically per section.' : 'سيتم تعيين رقم السؤال تلقائياً حسب المحور.'; ?>
                        </div>
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="question_poll_type" value="admin">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="question_section_id" required>
                                        <option value=""><?php echo $lang === 'en' ? 'Select section...' : 'اختر المحور...'; ?></option>
                                        <?php foreach ($sections as $s): if ($s['poll_type'] !== 'admin') continue; ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo $lang === 'en' ? $s['title_en'] : $s['title_ar']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Type' : 'نوع السؤال'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="question_type" id="addAdminQuestionType" onchange="toggleQuestionTypeFields('addAdmin')" required>
                                        <option value="likert"><?php echo $jsLikert; ?></option>
                                        <option value="text"><?php echo $jsText; ?></option>
                                        <option value="choice"><?php echo $jsChoice; ?></option>
                                        <option value="ranking"><?php echo $jsRanking; ?></option>
                                        <option value="numeric"><?php echo $jsNumeric; ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_reverse" id="addAdminIsReverse" value="1">
                                        <label class="form-check-label" for="addAdminIsReverse">
                                            <?php echo $jsReverseCoded; ?>
                                            <small class="text-muted d-block" style="font-size:11px;"><?php echo $jsReverseCodedHelp; ?></small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" id="addAdminSpecialOptions">
                                    <label><?php echo $lang === 'en' ? 'Special Options' : 'خيارات خاصة'; ?></label>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_na_option" id="addAdminHasNa" value="1">
                                            <label class="form-check-label" for="addAdminHasNa">N/A</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_no_plan_option" id="addAdminHasNoPlan" value="1">
                                            <label class="form-check-label" for="addAdminHasNoPlan">No Plan</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Text (Arabic)' : 'نص السؤال (عربي)'; ?> <span class="required">*</span></label>
                                    <textarea class="form-control" name="question_text_ar" required rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Text (English)' : 'نص السؤال (إنجليزي)'; ?> <span class="required">*</span></label>
                                    <textarea class="form-control" name="question_text_en" required rows="2"></textarea>
                                </div>
                            </div>
                            
                            <!-- خيارات الإجابة -->
                            <div class="col-12" id="addAdminOptionsContainer" style="display:none;">
                                <div class="options-container">
                                    <label class="fw-bold mb-2"><?php echo $lang === 'en' ? 'Answer Options' : 'خيارات الإجابة'; ?> <span class="required">*</span></label>
                                    <div id="addAdminOptionsList">
                                        <div class="option-row">
                                            <input type="text" class="form-control" name="option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                            <input type="text" class="form-control" name="option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                            <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                        <div class="option-row">
                                            <input type="text" class="form-control" name="option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                            <input type="text" class="form-control" name="option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                            <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm btn-add-option" onclick="addOption('addAdmin')">
                                        <i class="fas fa-plus"></i> <?php echo $jsAddOption; ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" name="add_question" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Add Question' : 'إضافة السؤال'; ?></button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addAdminQuestionForm').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- جدول الأسئلة الإدارية -->
                    <div class="question-table-wrapper mt-3">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $lang === 'en' ? 'Question' : 'السؤال'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Type' : 'النوع'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Actions' : 'الإجراءات'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $currentSectionId = 0;
                                $sectionCounter = 0;
                                foreach ($adminQuestions as $q): 
                                    if ($q['section_id'] != $currentSectionId) {
                                        $currentSectionId = $q['section_id'];
                                        $sectionCounter = 0;
                                    }
                                    $sectionCounter++;
                                ?>
                                <tr class="question-item">
                                    <td><?php echo $sectionCounter; ?></td>
                                    <td>
                                        <?php echo $lang === 'en' ? htmlspecialchars($q['question_text_en']) : htmlspecialchars($q['question_text_ar']); ?>
                                        <?php if (isset($q['is_reverse']) && $q['is_reverse'] == 1): ?>
                                        <span class="badge-option reverse"><i class="fas fa-exchange-alt"></i> <?php echo $jsReverseCoded; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $lang === 'en' ? htmlspecialchars($q['section_title_en'] ?? '-') : htmlspecialchars($q['section_title_ar'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        $typeLabels = [
                                            'likert' => $jsLikert,
                                            'text' => $jsText,
                                            'choice' => $jsChoice,
                                            'ranking' => $jsRanking,
                                            'numeric' => $jsNumeric
                                        ];
                                        $typeClasses = [
                                            'likert' => 'likert',
                                            'text' => 'text',
                                            'choice' => 'choice',
                                            'ranking' => 'ranking',
                                            'numeric' => 'numeric'
                                        ];
                                        ?>
                                        <span class="badge-option badge-type <?php echo $typeClasses[$q['question_type']] ?? 'likert'; ?>">
                                            <?php echo $typeLabels[$q['question_type']] ?? $jsLikert; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="editQuestion(<?php echo $q['id']; ?>, 'admin')"><i class="fas fa-edit"></i></button>
                                        <a href="?delete_question=<?php echo $q['id']; ?>&poll_type=admin#section-questions" class="btn btn-danger-custom btn-sm" onclick="return confirm('<?php echo $jsConfirmDelete; ?>')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <!-- نموذج تعديل -->
                                <tr id="editAdminForm_<?php echo $q['id']; ?>" style="display:none;">
                                    <td colspan="5">
                                        <div class="edit-form active">
                                            <form method="POST" action="" class="row g-3">
                                                <input type="hidden" name="edit_question_id" value="<?php echo $q['id']; ?>">
                                                <input type="hidden" name="edit_question_poll_type" value="admin">
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Number' : 'الرقم'; ?></label>
                                                        <input type="number" class="form-control" name="edit_question_number" value="<?php echo $q['question_number']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?></label>
                                                        <select class="form-select" name="edit_question_section_id" required>
                                                            <?php foreach ($sections as $s): if ($s['poll_type'] !== 'admin') continue; ?>
                                                            <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $q['section_id'] ? 'selected' : ''; ?>>
                                                                <?php echo $lang === 'en' ? $s['title_en'] : $s['title_ar']; ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Question Type' : 'نوع السؤال'; ?></label>
                                                        <select class="form-select" name="edit_question_type" id="editAdminQuestionType_<?php echo $q['id']; ?>" onchange="toggleQuestionTypeFields('editAdmin', <?php echo $q['id']; ?>)">
                                                            <option value="likert" <?php echo $q['question_type'] === 'likert' ? 'selected' : ''; ?>><?php echo $jsLikert; ?></option>
                                                            <option value="text" <?php echo $q['question_type'] === 'text' ? 'selected' : ''; ?>><?php echo $jsText; ?></option>
                                                            <option value="choice" <?php echo $q['question_type'] === 'choice' ? 'selected' : ''; ?>><?php echo $jsChoice; ?></option>
                                                            <option value="ranking" <?php echo $q['question_type'] === 'ranking' ? 'selected' : ''; ?>><?php echo $jsRanking; ?></option>
                                                            <option value="numeric" <?php echo $q['question_type'] === 'numeric' ? 'selected' : ''; ?>><?php echo $jsNumeric; ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="edit_is_reverse" value="1" <?php echo (isset($q['is_reverse']) && $q['is_reverse'] == 1) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label"><?php echo $jsReverseCoded; ?></label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group" id="editAdminSpecialOptions_<?php echo $q['id']; ?>">
                                                        <label><?php echo $lang === 'en' ? 'Special Options' : 'خيارات خاصة'; ?></label>
                                                        <div class="d-flex gap-3 flex-wrap">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="edit_has_na_option" value="1" <?php echo $q['has_na_option'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">N/A</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="edit_has_no_plan_option" value="1" <?php echo $q['has_no_plan_option'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">No Plan</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Text (Ar)' : 'النص (عربي)'; ?></label>
                                                        <textarea class="form-control" name="edit_question_text_ar" rows="2" required><?php echo htmlspecialchars($q['question_text_ar']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Text (En)' : 'النص (إنجليزي)'; ?></label>
                                                        <textarea class="form-control" name="edit_question_text_en" rows="2" required><?php echo htmlspecialchars($q['question_text_en']); ?></textarea>
                                                    </div>
                                                </div>
                                                
                                                <!-- خيارات الإجابة للتعديل -->
                                                <div class="col-12" id="editAdminOptionsContainer_<?php echo $q['id']; ?>" style="display:<?php echo ($q['question_type'] === 'choice' || $q['question_type'] === 'ranking') ? 'block' : 'none'; ?>;">
                                                    <div class="options-container">
                                                        <label class="fw-bold mb-2"><?php echo $lang === 'en' ? 'Answer Options' : 'خيارات الإجابة'; ?> <span class="required">*</span></label>
                                                        <div id="editAdminOptionsList_<?php echo $q['id']; ?>">
                                                            <?php 
                                                            $options = getQuestionOptions($pdo, $q['id'], 'admin');
                                                            if (!empty($options)):
                                                                foreach ($options as $opt):
                                                            ?>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" value="<?php echo htmlspecialchars($opt['option_text_ar']); ?>" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" value="<?php echo htmlspecialchars($opt['option_text_en']); ?>" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <?php 
                                                                endforeach;
                                                            else:
                                                            ?>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button type="button" class="btn btn-secondary btn-sm btn-add-option" onclick="addOption('editAdmin', <?php echo $q['id']; ?>)">
                                                            <i class="fas fa-plus"></i> <?php echo $jsAddOption; ?>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <button type="submit" name="edit_question" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Update' : 'تحديث'; ?></button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('editAdminForm_<?php echo $q['id']; ?>').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($adminQuestions)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo $lang === 'en' ? 'No admin questions found' : 'لا توجد أسئلة إدارية'; ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ===== الأسئلة الشخصية ===== -->
                <div class="tab-pane fade" id="personalQuestions" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0"><?php echo $lang === 'en' ? 'Personal Questions List' : 'قائمة الأسئلة الشخصية'; ?> <span class="badge bg-info"><?php echo count($personalQuestions); ?></span></h6>
                        <button class="btn btn-info btn-sm" onclick="showAddQuestionForm('personal')">
                            <i class="fas fa-plus"></i> <?php echo $lang === 'en' ? 'Add Question' : 'إضافة سؤال'; ?>
                        </button>
                    </div>
                    
                    <!-- نموذج إضافة سؤال شخصي -->
                    <div id="addPersonalQuestionForm" style="display:none;" class="edit-form active">
                        <h6><?php echo $lang === 'en' ? 'Add New Personal Question' : 'إضافة سؤال شخصي جديد'; ?></h6>
                        <div class="alert alert-info alert-sm mb-3">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $lang === 'en' ? 'Question number will be assigned automatically per section.' : 'سيتم تعيين رقم السؤال تلقائياً حسب المحور.'; ?>
                        </div>
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="question_poll_type" value="personal">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="question_section_id" required>
                                        <option value=""><?php echo $lang === 'en' ? 'Select section...' : 'اختر المحور...'; ?></option>
                                        <?php foreach ($sections as $s): if ($s['poll_type'] !== 'personal') continue; ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo $lang === 'en' ? $s['title_en'] : $s['title_ar']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Type' : 'نوع السؤال'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="question_type" id="addPersonalQuestionType" onchange="toggleQuestionTypeFields('addPersonal')" required>
                                        <option value="likert"><?php echo $jsLikert; ?></option>
                                        <option value="text"><?php echo $jsText; ?></option>
                                        <option value="choice"><?php echo $jsChoice; ?></option>
                                        <option value="ranking"><?php echo $jsRanking; ?></option>
                                        <option value="numeric"><?php echo $jsNumeric; ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_reverse" id="addPersonalIsReverse" value="1">
                                        <label class="form-check-label" for="addPersonalIsReverse">
                                            <?php echo $jsReverseCoded; ?>
                                            <small class="text-muted d-block" style="font-size:11px;"><?php echo $jsReverseCodedHelp; ?></small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" id="addPersonalSpecialOptions">
                                    <label><?php echo $lang === 'en' ? 'Special Options' : 'خيارات خاصة'; ?></label>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_na_option" id="addPersonalHasNa" value="1">
                                            <label class="form-check-label" for="addPersonalHasNa">N/A</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_no_plan_option" id="addPersonalHasNoPlan" value="1">
                                            <label class="form-check-label" for="addPersonalHasNoPlan">No Plan</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Text (Arabic)' : 'نص السؤال (عربي)'; ?> <span class="required">*</span></label>
                                    <textarea class="form-control" name="question_text_ar" required rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Text (English)' : 'نص السؤال (إنجليزي)'; ?> <span class="required">*</span></label>
                                    <textarea class="form-control" name="question_text_en" required rows="2"></textarea>
                                </div>
                            </div>
                            
                            <!-- خيارات الإجابة -->
                            <div class="col-12" id="addPersonalOptionsContainer" style="display:none;">
                                <div class="options-container">
                                    <label class="fw-bold mb-2"><?php echo $lang === 'en' ? 'Answer Options' : 'خيارات الإجابة'; ?> <span class="required">*</span></label>
                                    <div id="addPersonalOptionsList">
                                        <div class="option-row">
                                            <input type="text" class="form-control" name="option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                            <input type="text" class="form-control" name="option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                            <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                        <div class="option-row">
                                            <input type="text" class="form-control" name="option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                            <input type="text" class="form-control" name="option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                            <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm btn-add-option" onclick="addOption('addPersonal')">
                                        <i class="fas fa-plus"></i> <?php echo $jsAddOption; ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" name="add_question" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Add Question' : 'إضافة السؤال'; ?></button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addPersonalQuestionForm').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- جدول الأسئلة الشخصية -->
                    <div class="question-table-wrapper mt-3">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $lang === 'en' ? 'Question' : 'السؤال'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Type' : 'النوع'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Options' : 'الخيارات'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Actions' : 'الإجراءات'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $currentSectionId = 0;
                                $sectionCounter = 0;
                                foreach ($personalQuestions as $q): 
                                    if ($q['section_id'] != $currentSectionId) {
                                        $currentSectionId = $q['section_id'];
                                        $sectionCounter = 0;
                                    }
                                    $sectionCounter++;
                                ?>
                                <tr class="question-item">
                                    <td><?php echo $sectionCounter; ?></td>
                                    <td>
                                        <?php echo $lang === 'en' ? htmlspecialchars($q['question_text_en']) : htmlspecialchars($q['question_text_ar']); ?>
                                        <?php if (isset($q['is_reverse']) && $q['is_reverse'] == 1): ?>
                                        <span class="badge-option reverse"><i class="fas fa-exchange-alt"></i> <?php echo $jsReverseCoded; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $lang === 'en' ? htmlspecialchars($q['section_title_en'] ?? '-') : htmlspecialchars($q['section_title_ar'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        $typeLabels = [
                                            'likert' => $jsLikert,
                                            'text' => $jsText,
                                            'choice' => $jsChoice,
                                            'ranking' => $jsRanking,
                                            'numeric' => $jsNumeric
                                        ];
                                        $typeClasses = [
                                            'likert' => 'likert',
                                            'text' => 'text',
                                            'choice' => 'choice',
                                            'ranking' => 'ranking',
                                            'numeric' => 'numeric'
                                        ];
                                        ?>
                                        <span class="badge-option badge-type <?php echo $typeClasses[$q['question_type']] ?? 'likert'; ?>">
                                            <?php echo $typeLabels[$q['question_type']] ?? $jsLikert; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($q['has_na_option']): ?>
                                        <span class="badge-option na">N/A</span>
                                        <?php endif; ?>
                                        <?php if ($q['has_no_plan_option']): ?>
                                        <span class="badge-option noplan">No Plan</span>
                                        <?php endif; ?>
                                        <?php if (!empty($q['options'])): ?>
                                        <span class="badge bg-secondary"><?php echo count($q['options']); ?> <?php echo $lang === 'en' ? 'options' : 'خيارات'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="editQuestion(<?php echo $q['id']; ?>, 'personal')"><i class="fas fa-edit"></i></button>
                                        <a href="?delete_question=<?php echo $q['id']; ?>&poll_type=personal#section-questions" class="btn btn-danger-custom btn-sm" onclick="return confirm('<?php echo $jsConfirmDelete; ?>')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <!-- نموذج تعديل -->
                                <tr id="editPersonalForm_<?php echo $q['id']; ?>" style="display:none;">
                                    <td colspan="6">
                                        <div class="edit-form active">
                                            <form method="POST" action="" class="row g-3">
                                                <input type="hidden" name="edit_question_id" value="<?php echo $q['id']; ?>">
                                                <input type="hidden" name="edit_question_poll_type" value="personal">
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Number' : 'الرقم'; ?></label>
                                                        <input type="number" class="form-control" name="edit_question_number" value="<?php echo $q['question_number']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?></label>
                                                        <select class="form-select" name="edit_question_section_id" required>
                                                            <?php foreach ($sections as $s): if ($s['poll_type'] !== 'personal') continue; ?>
                                                            <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $q['section_id'] ? 'selected' : ''; ?>>
                                                                <?php echo $lang === 'en' ? $s['title_en'] : $s['title_ar']; ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Question Type' : 'نوع السؤال'; ?></label>
                                                        <select class="form-select" name="edit_question_type" id="editPersonalQuestionType_<?php echo $q['id']; ?>" onchange="toggleQuestionTypeFields('editPersonal', <?php echo $q['id']; ?>)">
                                                            <option value="likert" <?php echo $q['question_type'] === 'likert' ? 'selected' : ''; ?>><?php echo $jsLikert; ?></option>
                                                            <option value="text" <?php echo $q['question_type'] === 'text' ? 'selected' : ''; ?>><?php echo $jsText; ?></option>
                                                            <option value="choice" <?php echo $q['question_type'] === 'choice' ? 'selected' : ''; ?>><?php echo $jsChoice; ?></option>
                                                            <option value="ranking" <?php echo $q['question_type'] === 'ranking' ? 'selected' : ''; ?>><?php echo $jsRanking; ?></option>
                                                            <option value="numeric" <?php echo $q['question_type'] === 'numeric' ? 'selected' : ''; ?>><?php echo $jsNumeric; ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="edit_is_reverse" value="1" <?php echo (isset($q['is_reverse']) && $q['is_reverse'] == 1) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label"><?php echo $jsReverseCoded; ?></label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group" id="editPersonalSpecialOptions_<?php echo $q['id']; ?>">
                                                        <label><?php echo $lang === 'en' ? 'Special Options' : 'خيارات خاصة'; ?></label>
                                                        <div class="d-flex gap-3 flex-wrap">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="edit_has_na_option" value="1" <?php echo $q['has_na_option'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">N/A</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="edit_has_no_plan_option" value="1" <?php echo $q['has_no_plan_option'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">No Plan</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Text (Ar)' : 'النص (عربي)'; ?></label>
                                                        <textarea class="form-control" name="edit_question_text_ar" rows="2" required><?php echo htmlspecialchars($q['question_text_ar']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Text (En)' : 'النص (إنجليزي)'; ?></label>
                                                        <textarea class="form-control" name="edit_question_text_en" rows="2" required><?php echo htmlspecialchars($q['question_text_en']); ?></textarea>
                                                    </div>
                                                </div>
                                                
                                                <!-- خيارات الإجابة للتعديل -->
                                                <div class="col-12" id="editPersonalOptionsContainer_<?php echo $q['id']; ?>" style="display:<?php echo ($q['question_type'] === 'choice' || $q['question_type'] === 'ranking') ? 'block' : 'none'; ?>;">
                                                    <div class="options-container">
                                                        <label class="fw-bold mb-2"><?php echo $lang === 'en' ? 'Answer Options' : 'خيارات الإجابة'; ?> <span class="required">*</span></label>
                                                        <div id="editPersonalOptionsList_<?php echo $q['id']; ?>">
                                                            <?php 
                                                            $options = getQuestionOptions($pdo, $q['id'], 'personal');
                                                            if (!empty($options)):
                                                                foreach ($options as $opt):
                                                            ?>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" value="<?php echo htmlspecialchars($opt['option_text_ar']); ?>" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" value="<?php echo htmlspecialchars($opt['option_text_en']); ?>" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <?php 
                                                                endforeach;
                                                            else:
                                                            ?>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button type="button" class="btn btn-secondary btn-sm btn-add-option" onclick="addOption('editPersonal', <?php echo $q['id']; ?>)">
                                                            <i class="fas fa-plus"></i> <?php echo $jsAddOption; ?>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <button type="submit" name="edit_question" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Update' : 'تحديث'; ?></button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('editPersonalForm_<?php echo $q['id']; ?>').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($personalQuestions)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo $lang === 'en' ? 'No personal questions found' : 'لا توجد أسئلة شخصية'; ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ===== الأسئلة التنفيذية ===== -->
                <div class="tab-pane fade" id="executiveQuestions" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0"><?php echo $lang === 'en' ? 'Executive Questions List' : 'قائمة الأسئلة التنفيذية'; ?> <span class="badge bg-success"><?php echo count($executiveQuestions); ?></span></h6>
                        <button class="btn btn-success btn-sm" onclick="showAddQuestionForm('executive')">
                            <i class="fas fa-plus"></i> <?php echo $lang === 'en' ? 'Add Question' : 'إضافة سؤال'; ?>
                        </button>
                    </div>
                    
                    <!-- نموذج إضافة سؤال تنفيذي -->
                    <div id="addExecutiveQuestionForm" style="display:none;" class="edit-form active">
                        <h6><?php echo $lang === 'en' ? 'Add New Executive Question' : 'إضافة سؤال تنفيذي جديد'; ?></h6>
                        <div class="alert alert-info alert-sm mb-3">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $lang === 'en' ? 'Question number will be assigned automatically per section.' : 'سيتم تعيين رقم السؤال تلقائياً حسب المحور.'; ?>
                        </div>
                        <form method="POST" action="" class="row g-3">
                            <input type="hidden" name="question_poll_type" value="executive">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="question_section_id" required>
                                        <option value=""><?php echo $lang === 'en' ? 'Select section...' : 'اختر المحور...'; ?></option>
                                        <?php foreach ($sections as $s): if ($s['poll_type'] !== 'executive') continue; ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo $lang === 'en' ? $s['title_en'] : $s['title_ar']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Type' : 'نوع السؤال'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="question_type" id="addExecutiveQuestionType" onchange="toggleQuestionTypeFields('addExecutive')" required>
                                        <option value="likert"><?php echo $jsLikert; ?></option>
                                        <option value="text"><?php echo $jsText; ?></option>
                                        <option value="choice"><?php echo $jsChoice; ?></option>
                                        <option value="ranking"><?php echo $jsRanking; ?></option>
                                        <option value="numeric"><?php echo $jsNumeric; ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_reverse" id="addExecutiveIsReverse" value="1">
                                        <label class="form-check-label" for="addExecutiveIsReverse">
                                            <?php echo $jsReverseCoded; ?>
                                            <small class="text-muted d-block" style="font-size:11px;"><?php echo $jsReverseCodedHelp; ?></small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" id="addExecutiveSpecialOptions">
                                    <label><?php echo $lang === 'en' ? 'Special Options' : 'خيارات خاصة'; ?></label>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_na_option" id="addExecutiveHasNa" value="1">
                                            <label class="form-check-label" for="addExecutiveHasNa">N/A</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="has_no_plan_option" id="addExecutiveHasNoPlan" value="1">
                                            <label class="form-check-label" for="addExecutiveHasNoPlan">No Plan</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Text (Arabic)' : 'نص السؤال (عربي)'; ?> <span class="required">*</span></label>
                                    <textarea class="form-control" name="question_text_ar" required rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Question Text (English)' : 'نص السؤال (إنجليزي)'; ?> <span class="required">*</span></label>
                                    <textarea class="form-control" name="question_text_en" required rows="2"></textarea>
                                </div>
                            </div>
                            
                            <!-- خيارات الإجابة -->
                            <div class="col-12" id="addExecutiveOptionsContainer" style="display:none;">
                                <div class="options-container">
                                    <label class="fw-bold mb-2"><?php echo $lang === 'en' ? 'Answer Options' : 'خيارات الإجابة'; ?> <span class="required">*</span></label>
                                    <div id="addExecutiveOptionsList">
                                        <div class="option-row">
                                            <input type="text" class="form-control" name="option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                            <input type="text" class="form-control" name="option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                            <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                        <div class="option-row">
                                            <input type="text" class="form-control" name="option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                            <input type="text" class="form-control" name="option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                            <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm btn-add-option" onclick="addOption('addExecutive')">
                                        <i class="fas fa-plus"></i> <?php echo $jsAddOption; ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" name="add_question" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Add Question' : 'إضافة السؤال'; ?></button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addExecutiveQuestionForm').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- جدول الأسئلة التنفيذية -->
                    <div class="question-table-wrapper mt-3">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $lang === 'en' ? 'Question' : 'السؤال'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Type' : 'النوع'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Options' : 'الخيارات'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Actions' : 'الإجراءات'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $currentSectionId = 0;
                                $sectionCounter = 0;
                                foreach ($executiveQuestions as $q): 
                                    if ($q['section_id'] != $currentSectionId) {
                                        $currentSectionId = $q['section_id'];
                                        $sectionCounter = 0;
                                    }
                                    $sectionCounter++;
                                ?>
                                <tr class="question-item">
                                    <td><?php echo $sectionCounter; ?></td>
                                    <td>
                                        <?php echo $lang === 'en' ? htmlspecialchars($q['question_text_en']) : htmlspecialchars($q['question_text_ar']); ?>
                                        <?php if (isset($q['is_reverse']) && $q['is_reverse'] == 1): ?>
                                        <span class="badge-option reverse"><i class="fas fa-exchange-alt"></i> <?php echo $jsReverseCoded; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $lang === 'en' ? htmlspecialchars($q['section_title_en'] ?? '-') : htmlspecialchars($q['section_title_ar'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        $typeLabels = [
                                            'likert' => $jsLikert,
                                            'text' => $jsText,
                                            'choice' => $jsChoice,
                                            'ranking' => $jsRanking,
                                            'numeric' => $jsNumeric
                                        ];
                                        $typeClasses = [
                                            'likert' => 'likert',
                                            'text' => 'text',
                                            'choice' => 'choice',
                                            'ranking' => 'ranking',
                                            'numeric' => 'numeric'
                                        ];
                                        ?>
                                        <span class="badge-option badge-type <?php echo $typeClasses[$q['question_type']] ?? 'likert'; ?>">
                                            <?php echo $typeLabels[$q['question_type']] ?? $jsLikert; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($q['has_na_option']): ?>
                                        <span class="badge-option na">N/A</span>
                                        <?php endif; ?>
                                        <?php if ($q['has_no_plan_option']): ?>
                                        <span class="badge-option noplan">No Plan</span>
                                        <?php endif; ?>
                                        <?php if (!empty($q['options'])): ?>
                                        <span class="badge bg-secondary"><?php echo count($q['options']); ?> <?php echo $lang === 'en' ? 'options' : 'خيارات'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="editQuestion(<?php echo $q['id']; ?>, 'executive')"><i class="fas fa-edit"></i></button>
                                        <a href="?delete_question=<?php echo $q['id']; ?>&poll_type=executive#section-questions" class="btn btn-danger-custom btn-sm" onclick="return confirm('<?php echo $jsConfirmDelete; ?>')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <!-- نموذج تعديل -->
                                <tr id="editExecutiveForm_<?php echo $q['id']; ?>" style="display:none;">
                                    <td colspan="6">
                                        <div class="edit-form active">
                                            <form method="POST" action="" class="row g-3">
                                                <input type="hidden" name="edit_question_id" value="<?php echo $q['id']; ?>">
                                                <input type="hidden" name="edit_question_poll_type" value="executive">
                                                <div class="col-md-2">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Number' : 'الرقم'; ?></label>
                                                        <input type="number" class="form-control" name="edit_question_number" value="<?php echo $q['question_number']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Section' : 'المحور'; ?></label>
                                                        <select class="form-select" name="edit_question_section_id" required>
                                                            <?php foreach ($sections as $s): if ($s['poll_type'] !== 'executive') continue; ?>
                                                            <option value="<?php echo $s['id']; ?>" <?php echo $s['id'] == $q['section_id'] ? 'selected' : ''; ?>>
                                                                <?php echo $lang === 'en' ? $s['title_en'] : $s['title_ar']; ?>
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Question Type' : 'نوع السؤال'; ?></label>
                                                        <select class="form-select" name="edit_question_type" id="editExecutiveQuestionType_<?php echo $q['id']; ?>" onchange="toggleQuestionTypeFields('editExecutive', <?php echo $q['id']; ?>)">
                                                            <option value="likert" <?php echo $q['question_type'] === 'likert' ? 'selected' : ''; ?>><?php echo $jsLikert; ?></option>
                                                            <option value="text" <?php echo $q['question_type'] === 'text' ? 'selected' : ''; ?>><?php echo $jsText; ?></option>
                                                            <option value="choice" <?php echo $q['question_type'] === 'choice' ? 'selected' : ''; ?>><?php echo $jsChoice; ?></option>
                                                            <option value="ranking" <?php echo $q['question_type'] === 'ranking' ? 'selected' : ''; ?>><?php echo $jsRanking; ?></option>
                                                            <option value="numeric" <?php echo $q['question_type'] === 'numeric' ? 'selected' : ''; ?>><?php echo $jsNumeric; ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Reverse Coded' : 'معكوس'; ?></label>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="edit_is_reverse" value="1" <?php echo (isset($q['is_reverse']) && $q['is_reverse'] == 1) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label"><?php echo $jsReverseCoded; ?></label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group" id="editExecutiveSpecialOptions_<?php echo $q['id']; ?>">
                                                        <label><?php echo $lang === 'en' ? 'Special Options' : 'خيارات خاصة'; ?></label>
                                                        <div class="d-flex gap-3 flex-wrap">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="edit_has_na_option" value="1" <?php echo $q['has_na_option'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">N/A</label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="edit_has_no_plan_option" value="1" <?php echo $q['has_no_plan_option'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label">No Plan</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Text (Ar)' : 'النص (عربي)'; ?></label>
                                                        <textarea class="form-control" name="edit_question_text_ar" rows="2" required><?php echo htmlspecialchars($q['question_text_ar']); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Text (En)' : 'النص (إنجليزي)'; ?></label>
                                                        <textarea class="form-control" name="edit_question_text_en" rows="2" required><?php echo htmlspecialchars($q['question_text_en']); ?></textarea>
                                                    </div>
                                                </div>
                                                
                                                <!-- خيارات الإجابة للتعديل -->
                                                <div class="col-12" id="editExecutiveOptionsContainer_<?php echo $q['id']; ?>" style="display:<?php echo ($q['question_type'] === 'choice' || $q['question_type'] === 'ranking') ? 'block' : 'none'; ?>;">
                                                    <div class="options-container">
                                                        <label class="fw-bold mb-2"><?php echo $lang === 'en' ? 'Answer Options' : 'خيارات الإجابة'; ?> <span class="required">*</span></label>
                                                        <div id="editExecutiveOptionsList_<?php echo $q['id']; ?>">
                                                            <?php 
                                                            $options = getQuestionOptions($pdo, $q['id'], 'executive');
                                                            if (!empty($options)):
                                                                foreach ($options as $opt):
                                                            ?>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" value="<?php echo htmlspecialchars($opt['option_text_ar']); ?>" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" value="<?php echo htmlspecialchars($opt['option_text_en']); ?>" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <?php 
                                                                endforeach;
                                                            else:
                                                            ?>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <div class="option-row">
                                                                <input type="text" class="form-control" name="edit_option_text_ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
                                                                <input type="text" class="form-control" name="edit_option_text_en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
                                                                <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button type="button" class="btn btn-secondary btn-sm btn-add-option" onclick="addOption('editExecutive', <?php echo $q['id']; ?>)">
                                                            <i class="fas fa-plus"></i> <?php echo $jsAddOption; ?>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-12">
                                                    <button type="submit" name="edit_question" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Update' : 'تحديث'; ?></button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('editExecutiveForm_<?php echo $q['id']; ?>').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($executiveQuestions)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo $lang === 'en' ? 'No executive questions found' : 'لا توجد أسئلة تنفيذية'; ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ===== إدارة المحاور ===== -->
                <div class="tab-pane fade" id="sectionsManagement" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h6 class="mb-0"><?php echo $lang === 'en' ? 'Sections List' : 'قائمة المحاور'; ?> <span class="badge bg-secondary"><?php echo count($sections); ?></span></h6>
                        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('addSectionForm').style.display='block'">
                            <i class="fas fa-plus"></i> <?php echo $lang === 'en' ? 'Add Section' : 'إضافة محور'; ?>
                        </button>
                    </div>
                    
                    <!-- نموذج إضافة محور -->
                    <div id="addSectionForm" style="display:none;" class="edit-form active">
                        <h6><?php echo $lang === 'en' ? 'Add New Section' : 'إضافة محور جديد'; ?></h6>
                        <form method="POST" action="" class="row g-3">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Poll Type' : 'نوع الاستبيان'; ?> <span class="required">*</span></label>
                                    <select class="form-select" name="section_poll_type" required>
                                        <option value="regular"><?php echo $jsRegular; ?></option>
                                        <option value="admin"><?php echo $jsAdmin; ?></option>
                                        <option value="personal"><?php echo $jsPersonal; ?></option>
                                        <option value="executive"><?php echo $jsExecutive; ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Section Number' : 'رقم المحور'; ?> <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="section_number" required min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Title (Arabic)' : 'العنوان (عربي)'; ?> <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="section_title_ar" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Title (English)' : 'العنوان (إنجليزي)'; ?> <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="section_title_en" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Description (Arabic)' : 'الوصف (عربي)'; ?></label>
                                    <textarea class="form-control" name="section_description_ar" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo $lang === 'en' ? 'Description (English)' : 'الوصف (إنجليزي)'; ?></label>
                                    <textarea class="form-control" name="section_description_en" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_section" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Add Section' : 'إضافة المحور'; ?></button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addSectionForm').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- جدول المحاور -->
                    <div class="question-table-wrapper mt-3">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $lang === 'en' ? 'Type' : 'النوع'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Title' : 'العنوان'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Description' : 'الوصف'; ?></th>
                                    <th><?php echo $lang === 'en' ? 'Actions' : 'الإجراءات'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sections as $s): ?>
                                <tr>
                                    <td><?php echo $s['section_number']; ?></td>
                                    <td>
                                        <span class="poll-type-badge <?php echo $s['poll_type']; ?>">
                                            <?php echo getPollTypeLabel($s['poll_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $lang === 'en' ? htmlspecialchars($s['title_en']) : htmlspecialchars($s['title_ar']); ?></td>
                                    <td><?php echo $lang === 'en' ? htmlspecialchars($s['description_en'] ?? '-') : htmlspecialchars($s['description_ar'] ?? '-'); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="editSection(<?php echo $s['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-delete-section" onclick="deleteSection(<?php echo $s['id']; ?>)">
                                            <i class="fas fa-trash"></i> <?php echo $jsDeleteSection; ?>
                                        </button>
                                    </td>
                                </tr>
                                <!-- نموذج تعديل المحور -->
                                <tr id="editSectionForm_<?php echo $s['id']; ?>" style="display:none;">
                                    <td colspan="5">
                                        <div class="edit-form active">
                                            <form method="POST" action="" class="row g-3">
                                                <input type="hidden" name="edit_section_id" value="<?php echo $s['id']; ?>">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Section Number' : 'رقم المحور'; ?></label>
                                                        <input type="number" class="form-control" name="edit_section_number" value="<?php echo $s['section_number']; ?>" required min="1">
                                                    </div>
                                                </div>
                                                <div class="col-md-9">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Title (Arabic)' : 'العنوان (عربي)'; ?></label>
                                                        <input type="text" class="form-control" name="edit_section_title_ar" value="<?php echo htmlspecialchars($s['title_ar']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Title (English)' : 'العنوان (إنجليزي)'; ?></label>
                                                        <input type="text" class="form-control" name="edit_section_title_en" value="<?php echo htmlspecialchars($s['title_en']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Description (Arabic)' : 'الوصف (عربي)'; ?></label>
                                                        <textarea class="form-control" name="edit_section_description_ar" rows="2"><?php echo htmlspecialchars($s['description_ar'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label><?php echo $lang === 'en' ? 'Description (English)' : 'الوصف (إنجليزي)'; ?></label>
                                                        <textarea class="form-control" name="edit_section_description_en" rows="2"><?php echo htmlspecialchars($s['description_en'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" name="edit_section" class="btn btn-success btn-sm"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Update' : 'تحديث'; ?></button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('editSectionForm_<?php echo $s['id']; ?>').style.display='none'"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($sections)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo $lang === 'en' ? 'No sections found' : 'لا توجد محاور'; ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- ===== القسم 4: البحث عن شركة ===== -->
        <!-- ============================================================ -->
        <div class="section-card" id="section-search" data-section="search">
            <div class="section-title"><i class="fas fa-search"></i> <span><?php echo $lang === 'en' ? 'Search Company' : 'البحث عن شركة'; ?></span></div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Company Name' : 'اسم الشركة'; ?></label>
                        <input type="text" class="form-control" id="searchCompanyAutocomplete" placeholder="<?php echo $lang === 'en' ? 'Type company name...' : 'اكتب اسم الشركة...'; ?>" autocomplete="off">
                        <input type="hidden" id="searchCompanyId" value="">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="searchCompany()"><i class="fas fa-search"></i> <?php echo $lang === 'en' ? 'Search' : 'بحث'; ?></button>
                    </div>
                </div>
            </div>
            <div id="searchResults" class="mt-3" style="display:none;"></div>
        </div>
        
        <!-- ============================================================ -->
        <!-- ===== القسم 5: حسابات الاستبيان ===== -->
        <!-- ============================================================ -->
        <div class="section-card" id="section-accounts" data-section="accounts">
            <div class="section-title"><i class="fas fa-user-plus"></i> <span><?php echo $lang === 'en' ? 'Poll Accounts' : 'حسابات الاستبيان'; ?></span></div>
            
            <div class="row g-3" id="accountsForm">
                <div class="col-md-5">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Select Company' : 'اختر الشركة'; ?></label>
                        <input type="text" class="form-control" id="accountCompanyAutocomplete" placeholder="<?php echo $lang === 'en' ? 'Type company name...' : 'اكتب اسم الشركة...'; ?>" autocomplete="off">
                        <input type="hidden" id="accountCompanyId" value="">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label><?php echo $lang === 'en' ? 'Select Poll' : 'اختر الاستبيان'; ?></label>
                        <select class="form-select" id="accountPollSelect" disabled>
                            <option value=""><?php echo $jsSelectCompanyFirst; ?></option>
                        </select>
                        <input type="hidden" name="poll_id" id="selectedPollId" value="">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success w-100" id="generateAccountsBtn" onclick="generateAccounts(event)">
                                <i class="fas fa-key"></i> <?php echo $jsGenerate; ?>
                            </button>
                            <button type="button" class="btn btn-warning-custom" id="viewAccountsBtn" style="display:none;" onclick="viewAccounts(event)">
                                <i class="fas fa-eye"></i> <?php echo $jsViewAccounts; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="accountsDisplayContainer">
                <?php if (!empty($generatedAccounts) && $generatedPollInfo): ?>
                <div id="accountsDisplay" class="mt-3">
                    <div class="card">
                        <div class="card-header bg-<?php echo $accountsAlreadyExist ? 'warning' : 'primary'; ?> text-white d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-users"></i> 
                                <?php echo $jsGeneratedAccounts; ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($generatedAccounts); ?></span>
                                <?php if ($accountsAlreadyExist): ?>
                                <span class="badge bg-warning text-dark ms-2">
                                    <i class="fas fa-info-circle"></i> <?php echo $jsAccountsExist; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <small>
                                <i class="fas fa-calendar"></i> <?php echo $generatedPollInfo['date']; ?>
                                <span class="badge poll-type-badge <?php echo $generatedPollInfo['type']; ?> ms-1">
                                    <?php echo getPollTypeLabel($generatedPollInfo['type']); ?>
                                </span>
                            </small>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6"><small class="text-muted"><?php echo $jsCompany; ?></small><div class="fw-bold"><?php echo htmlspecialchars($generatedPollInfo['company_name']); ?></div></div>
                                <div class="col-md-6"><small class="text-muted"><?php echo $jsParticipantsCount; ?></small><div class="fw-bold"><?php echo $generatedPollInfo['participant_count']; ?></div></div>
                                <?php if (!empty($generatedPollInfo['departments'])): ?>
                                <div class="col-md-12 mt-2"><small class="text-muted"><?php echo $jsDepartments; ?></small><div><?php echo htmlspecialchars($generatedPollInfo['departments']); ?></div></div>
                                <?php endif; ?>
                            </div>
                            <hr>
                            <div class="accounts-table-wrapper">
                                <table class="table table-hover" id="accountsTable">
                                    <thead><tr><th>#</th><th><?php echo $lang === 'en' ? 'Username' : 'اسم المستخدم'; ?></th><th><?php echo $lang === 'en' ? 'Password' : 'كلمة المرور'; ?></th></tr></thead>
                                    <tbody>
                                        <?php $counter = 1; foreach ($generatedAccounts as $account): ?>
                                        <tr><td><?php echo $counter++; ?></td><td><code><?php echo htmlspecialchars($account['username']); ?></code></td><td><code><?php echo htmlspecialchars($account['password']); ?></code></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <button class="btn btn-primary" onclick="copyAllAccounts()"><i class="fas fa-copy"></i> <?php echo $jsAllAccountsCopied; ?></button>
                                <button class="btn btn-success" onclick="printAccounts()"><i class="fas fa-print"></i> <?php echo $lang === 'en' ? 'Print' : 'طباعة'; ?></button>
                                <button class="btn btn-outline-secondary" onclick="clearAccounts()"><i class="fas fa-times"></i> <?php echo $lang === 'en' ? 'Clear' : 'مسح'; ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- ===== القسم 6: الإعدادات ===== -->
        <!-- ============================================================ -->
        <div class="section-card" id="section-settings" data-section="settings">
            <div class="section-title"><i class="fas fa-cog"></i> <span><?php echo $lang === 'en' ? 'Settings' : 'الإعدادات'; ?></span></div>
            
            <!-- ===== تغيير الشعار ===== -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-image"></i> <?php echo $jsChangeLogo; ?></h6>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <?php if (hasLogo()): ?>
                            <img src="<?php echo getLogoPath(); ?>" alt="Logo" class="logo-preview" id="logoPreview">
                            <?php else: ?>
                            <div class="logo-preview d-flex align-items-center justify-content-center text-muted" style="height:100px;">
                                <i class="fas fa-image fa-2x"></i>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label><?php echo $jsSelectLogoFile; ?></label>
                                    <input type="file" class="form-control" name="logo_file" accept="image/png,image/jpeg,image/gif,image/webp" onchange="previewLogo(this)">
                                    <small class="text-muted"><?php echo $lang === 'en' ? 'Maximum size: 5MB. Recommended format: PNG' : 'الحد الأقصى: 5 ميجابايت. الصيغة المفضلة: PNG'; ?></small>
                                </div>
                                <button type="submit" name="change_logo" class="btn btn-primary"><i class="fas fa-upload"></i> <?php echo $jsUploadLogo; ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ===== تغيير كلمة المرور ===== -->
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-key"></i> <?php echo $jsChangePassword; ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $jsCurrentPassword; ?> <span class="required">*</span></label>
                                    <input type="password" class="form-control" name="current_password" required placeholder="<?php echo $lang === 'en' ? 'Enter current password' : 'أدخل كلمة المرور الحالية'; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $jsNewPassword; ?> <span class="required">*</span></label>
                                    <input type="password" class="form-control" name="new_password" required placeholder="<?php echo $lang === 'en' ? 'Enter new password (min 8 chars)' : 'أدخل كلمة المرور الجديدة (8 أحرف على الأقل)'; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $jsConfirmPassword; ?> <span class="required">*</span></label>
                                    <input type="password" class="form-control" name="confirm_password" required placeholder="<?php echo $lang === 'en' ? 'Confirm new password' : 'تأكيد كلمة المرور الجديدة'; ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $jsChangePassword; ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- ===== Mobile Bottom Navigation ===== -->
<div class="mobile-bottom-nav">
    <button class="nav-item" data-section="companies"><i class="fas fa-building"></i> <span><?php echo $lang === 'en' ? 'Companies' : 'الشركات'; ?></span></button>
    <button class="nav-item" data-section="polls"><i class="fas fa-poll"></i> <span><?php echo $lang === 'en' ? 'Polls' : 'الاستبيانات'; ?></span></button>
    <button class="nav-item" data-section="questions"><i class="fas fa-question-circle"></i> <span><?php echo $lang === 'en' ? 'Questions' : 'الأسئلة'; ?></span></button>
    <button class="nav-item" data-section="search"><i class="fas fa-search"></i> <span><?php echo $lang === 'en' ? 'Search' : 'بحث'; ?></span></button>
    <button class="nav-item" data-section="accounts"><i class="fas fa-users"></i> <span><?php echo $lang === 'en' ? 'Accounts' : 'الحسابات'; ?></span></button>
    <button class="nav-item" data-section="settings"><i class="fas fa-cog"></i> <span><?php echo $lang === 'en' ? 'Settings' : 'الإعدادات'; ?></span></button>
</div>

<!-- ============================================================ -->
<!-- ===== Modals ===== -->
<!-- ============================================================ -->

<!-- ===== تغيير كلمة المرور Modal ===== -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key text-primary"></i> <?php echo $jsChangePassword; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?php echo $jsCurrentPassword; ?> <span class="required">*</span></label>
                        <input type="password" class="form-control" name="current_password" required placeholder="<?php echo $lang === 'en' ? 'Enter current password' : 'أدخل كلمة المرور الحالية'; ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo $jsNewPassword; ?> <span class="required">*</span></label>
                        <input type="password" class="form-control" name="new_password" required placeholder="<?php echo $lang === 'en' ? 'Enter new password (min 8 chars)' : 'أدخل كلمة المرور الجديدة (8 أحرف على الأقل)'; ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo $jsConfirmPassword; ?> <span class="required">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" required placeholder="<?php echo $lang === 'en' ? 'Confirm new password' : 'تأكيد كلمة المرور الجديدة'; ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                    <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $jsChangePassword; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== تعديل شركة Modal ===== -->
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit text-primary"></i> <?php echo $jsEditCompany; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="edit_company_id" id="editCompanyId" value="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo $jsCompanyName; ?> <span class="required">*</span></label>
                                <input type="text" class="form-control" name="edit_company_name" id="editCompanyName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo $jsCompanyIndustry; ?></label>
                                <input type="text" class="form-control" name="edit_industry" id="editCompanyIndustry">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo $jsCompanyAddress; ?></label>
                                <input type="text" class="form-control" name="edit_company_address" id="editCompanyAddress">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo $jsCompanyPhone; ?></label>
                                <input type="tel" class="form-control" name="edit_phone" id="editCompanyPhone">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $lang === 'en' ? 'Cancel' : 'إلغاء'; ?></button>
                    <button type="submit" name="edit_company" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $lang === 'en' ? 'Update' : 'تحديث'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Scripts ===== -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============================================================
// ===== البيانات من PHP =====
// ============================================================
var companies = <?php echo $companiesJson; ?>;
var allPolls = <?php echo $pollsJson; ?>;
var totalCompanies = <?php echo $companiesTotalCount; ?>;
var currentPage = 1;
var perPage = 20;
var isLoading = false;
var hasMoreCompanies = <?php echo ($companiesTotalCount > count($companies)) ? 'true' : 'false'; ?>;

// ============================================================
// ===== دوال عامة =====
// ============================================================

function switchLanguage() {
    var currentLang = document.documentElement.lang;
    var newLang = currentLang === 'ar' ? 'en' : 'ar';
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'set_language.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            window.location.reload();
        }
    };
    xhr.send('language=' + newLang);
}

function viewPoll(pollId) {
    window.open('poll.php?id=' + pollId, '_blank');
}

// ============================================================
// ===== إدارة الأقسام =====
// ============================================================

function navigateToSection(sectionId) {
    var allSections = document.querySelectorAll('.section-card');
    for (var i = 0; i < allSections.length; i++) {
        allSections[i].classList.remove('active');
    }
    
    var targetSection = document.getElementById('section-' + sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    
    var menuLinks = document.querySelectorAll('.sidebar-menu li a');
    for (var i = 0; i < menuLinks.length; i++) {
        menuLinks[i].classList.remove('active');
        if (menuLinks[i].getAttribute('data-section') === sectionId) {
            menuLinks[i].classList.add('active');
        }
    }
    
    var navItems = document.querySelectorAll('.mobile-bottom-nav .nav-item');
    for (var i = 0; i < navItems.length; i++) {
        navItems[i].classList.remove('active');
        if (navItems[i].getAttribute('data-section') === sectionId) {
            navItems[i].classList.add('active');
        }
    }
    
    var titles = {
        'companies': '<?php echo $jsTitleCompanies; ?>',
        'polls': '<?php echo $jsTitlePolls; ?>',
        'questions': '<?php echo $jsTitleQuestions; ?>',
        'search': '<?php echo $jsTitleSearch; ?>',
        'accounts': '<?php echo $jsTitleAccounts; ?>',
        'settings': '<?php echo $lang === 'en' ? 'Settings' : 'الإعدادات'; ?>'
    };
    var titleElement = document.getElementById('pageTitle');
    if (titleElement) {
        titleElement.textContent = titles[sectionId] || 'Dashboard';
    }
    
    try {
        sessionStorage.setItem('activeSection', sectionId);
    } catch(e) {}
}

// ============================================================
// ===== معرض الشعار =====
// ============================================================
function previewLogo(input) {
    var preview = document.getElementById('logoPreview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
            } else {
                var img = document.createElement('img');
                img.id = 'logoPreview';
                img.className = 'logo-preview';
                img.src = e.target.result;
                input.parentElement.parentElement.querySelector('.logo-preview').replaceWith(img);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// ============================================================
// ===== تغيير كلمة المرور =====
// ============================================================
function openChangePasswordModal() {
    var modal = new bootstrap.Modal(document.getElementById('changePasswordModal'));
    modal.show();
}

// ============================================================
// ===== تعديل شركة =====
// ============================================================
function openEditCompanyModal(id, name, address, industry, phone) {
    document.getElementById('editCompanyId').value = id;
    document.getElementById('editCompanyName').value = name;
    document.getElementById('editCompanyAddress').value = address || '';
    document.getElementById('editCompanyIndustry').value = industry || '';
    document.getElementById('editCompanyPhone').value = phone || '';
    
    var modal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
    modal.show();
}

// ============================================================
// ===== حذف شركة =====
// ============================================================
function deleteCompany(companyId) {
    if (confirm('<?php echo $jsConfirmDeleteCompany; ?>')) {
        window.location.href = 'admin.php?delete_company=' + companyId + '#section-companies';
    }
}

// ============================================================
// ===== Infinite Scroll - تحميل الشركات =====
// ============================================================
function loadMoreCompanies() {
    if (isLoading || !hasMoreCompanies) {
        return;
    }
    
    isLoading = true;
    var nextPage = currentPage + 1;
    
    var trigger = document.getElementById('infiniteScrollTrigger');
    document.getElementById('scrollLoader').style.display = 'block';
    document.getElementById('scrollNoMore').style.display = 'none';
    document.getElementById('scrollError').style.display = 'none';
    trigger.classList.add('active');
    
    var formData = new FormData();
    formData.append('ajax_load_companies', '1');
    formData.append('page', nextPage);
    formData.append('per_page', perPage);
    
    fetch('admin.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        isLoading = false;
        document.getElementById('scrollLoader').style.display = 'none';
        
        if (data.success) {
            var tableBody = document.getElementById('companiesTableBody');
            
            var emptyRow = document.getElementById('emptyCompaniesRow');
            if (emptyRow) {
                emptyRow.remove();
            }
            
            var startCounter = tableBody.querySelectorAll('tr').length + 1;
            data.companies.forEach(function(company, index) {
                var row = document.createElement('tr');
                row.className = 'company-row';
                row.setAttribute('data-id', company.id);
                row.innerHTML = `
                    <td>${startCounter + index}</td>
                    <td>
                        <strong>${escapeHtml(company.name)}</strong>
                        ${company.address ? `<br><small class="text-muted"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(company.address)}</small>` : ''}
                    </td>
                    <td>${company.industry ? escapeHtml(company.industry) : '-'}</td>
                    <td class="created-at">
                        <i class="far fa-calendar-alt"></i>
                        ${formatDate(company.created_at)}
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="viewCompany(${company.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-edit-company" onclick="openEditCompanyModal(${company.id}, '${escapeHtml(company.name)}', '${escapeHtml(company.address || '')}', '${escapeHtml(company.industry || '')}', '${escapeHtml(company.phone || '')}')">
                            <i class="fas fa-edit"></i> <?php echo $jsEditCompany; ?>
                        </button>
                        <button class="btn btn-sm btn-delete-company" onclick="deleteCompany(${company.id})">
                            <i class="fas fa-trash"></i> <?php echo $jsDeleteCompany; ?>
                        </button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
            
            currentPage = nextPage;
            hasMoreCompanies = data.has_more;
            document.getElementById('companiesTotalCount').textContent = data.total;
            
            if (!hasMoreCompanies) {
                document.getElementById('scrollNoMore').style.display = 'block';
                trigger.classList.remove('active');
            } else {
                trigger.classList.remove('active');
            }
        } else {
            document.getElementById('scrollError').style.display = 'block';
            setTimeout(function() {
                document.getElementById('scrollError').style.display = 'none';
                trigger.classList.remove('active');
            }, 5000);
        }
    })
    .catch(function(error) {
        isLoading = false;
        document.getElementById('scrollLoader').style.display = 'none';
        document.getElementById('scrollError').style.display = 'block';
        setTimeout(function() {
            document.getElementById('scrollError').style.display = 'none';
            var trigger = document.getElementById('infiniteScrollTrigger');
            trigger.classList.remove('active');
        }, 5000);
        console.error('Error loading companies:', error);
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    var date = new Date(dateString);
    var year = date.getFullYear();
    var month = String(date.getMonth() + 1).padStart(2, '0');
    var day = String(date.getDate()).padStart(2, '0');
    var hours = String(date.getHours()).padStart(2, '0');
    var minutes = String(date.getMinutes()).padStart(2, '0');
    return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes;
}

// ============================================================
// ===== مراقبة التمرير لتحميل المزيد =====
// ============================================================
function setupInfiniteScroll() {
    var wrapper = document.getElementById('companiesTableWrapper');
    if (!wrapper) return;
    
    wrapper.addEventListener('scroll', function() {
        if (isLoading || !hasMoreCompanies) return;
        
        var scrollTop = wrapper.scrollTop;
        var scrollHeight = wrapper.scrollHeight;
        var clientHeight = wrapper.clientHeight;
        
        if (scrollTop + clientHeight >= scrollHeight * 0.8) {
            loadMoreCompanies();
        }
    });
}

// ============================================================
// ===== إظهار نموذج إضافة سؤال =====
// ============================================================
function showAddQuestionForm(type) {
    // إخفاء جميع النماذج
    document.getElementById('addRegularQuestionForm').style.display = 'none';
    document.getElementById('addAdminQuestionForm').style.display = 'none';
    document.getElementById('addPersonalQuestionForm').style.display = 'none';
    document.getElementById('addExecutiveQuestionForm').style.display = 'none';
    
    // إظهار النموذج المطلوب
    if (type === 'regular') {
        document.getElementById('addRegularQuestionForm').style.display = 'block';
    } else if (type === 'admin') {
        document.getElementById('addAdminQuestionForm').style.display = 'block';
    } else if (type === 'personal') {
        document.getElementById('addPersonalQuestionForm').style.display = 'block';
    } else if (type === 'executive') {
        document.getElementById('addExecutiveQuestionForm').style.display = 'block';
    }
}

// ============================================================
// ===== تعديل سؤال =====
// ============================================================
function editQuestion(questionId, type) {
    var formId = '';
    if (type === 'regular') formId = 'editForm_' + questionId;
    else if (type === 'admin') formId = 'editAdminForm_' + questionId;
    else if (type === 'personal') formId = 'editPersonalForm_' + questionId;
    else if (type === 'executive') formId = 'editExecutiveForm_' + questionId;
    
    var form = document.getElementById(formId);
    if (form) {
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'table-row';
        } else {
            form.style.display = 'none';
        }
    }
}

// ============================================================
// ===== تعديل محور =====
// ============================================================
function editSection(sectionId) {
    var form = document.getElementById('editSectionForm_' + sectionId);
    if (form) {
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'table-row';
        } else {
            form.style.display = 'none';
        }
    }
}

// ============================================================
// ===== حذف محور =====
// ============================================================
function deleteSection(sectionId) {
    if (confirm('<?php echo $jsConfirmDeleteSection; ?>')) {
        window.location.href = 'admin.php?delete_section=' + sectionId + '#section-questions';
    }
}

// ============================================================
// ===== حذف استبيان =====
// ============================================================
function deletePoll(pollId, companyId) {
    if (confirm('<?php echo $jsConfirmDeletePoll; ?>')) {
        window.location.href = 'admin.php?delete_poll=' + pollId + '&company_id=' + companyId + '#section-search';
    }
}

// ============================================================
// ===== إدارة خيارات الإجابة =====
// ============================================================
function toggleQuestionTypeFields(mode, id) {
    var typeSelect;
    var optionsContainer;
    var specialOptions;
    
    if (mode === 'add') {
        typeSelect = document.getElementById('addQuestionType');
        optionsContainer = document.getElementById('addOptionsContainer');
        specialOptions = document.getElementById('addSpecialOptions');
    } else if (mode === 'addAdmin') {
        typeSelect = document.getElementById('addAdminQuestionType');
        optionsContainer = document.getElementById('addAdminOptionsContainer');
        specialOptions = document.getElementById('addAdminSpecialOptions');
    } else if (mode === 'addPersonal') {
        typeSelect = document.getElementById('addPersonalQuestionType');
        optionsContainer = document.getElementById('addPersonalOptionsContainer');
        specialOptions = document.getElementById('addPersonalSpecialOptions');
    } else if (mode === 'addExecutive') {
        typeSelect = document.getElementById('addExecutiveQuestionType');
        optionsContainer = document.getElementById('addExecutiveOptionsContainer');
        specialOptions = document.getElementById('addExecutiveSpecialOptions');
    } else if (mode === 'edit') {
        typeSelect = document.getElementById('editQuestionType_' + id);
        optionsContainer = document.getElementById('editOptionsContainer_' + id);
        specialOptions = document.getElementById('editSpecialOptions_' + id);
    } else if (mode === 'editAdmin') {
        typeSelect = document.getElementById('editAdminQuestionType_' + id);
        optionsContainer = document.getElementById('editAdminOptionsContainer_' + id);
        specialOptions = document.getElementById('editAdminSpecialOptions_' + id);
    } else if (mode === 'editPersonal') {
        typeSelect = document.getElementById('editPersonalQuestionType_' + id);
        optionsContainer = document.getElementById('editPersonalOptionsContainer_' + id);
        specialOptions = document.getElementById('editPersonalSpecialOptions_' + id);
    } else if (mode === 'editExecutive') {
        typeSelect = document.getElementById('editExecutiveQuestionType_' + id);
        optionsContainer = document.getElementById('editExecutiveOptionsContainer_' + id);
        specialOptions = document.getElementById('editExecutiveSpecialOptions_' + id);
    }
    
    if (!typeSelect) return;
    
    var selectedType = typeSelect.value;
    
    if (optionsContainer) {
        if (selectedType === 'choice' || selectedType === 'ranking') {
            optionsContainer.style.display = 'block';
        } else {
            optionsContainer.style.display = 'none';
        }
    }
    
    if (specialOptions) {
        if (selectedType === 'likert') {
            specialOptions.style.display = 'block';
        } else {
            specialOptions.style.display = 'none';
        }
    }
}

function addOption(mode, id) {
    var listId;
    
    if (mode === 'add') {
        listId = 'addOptionsList';
    } else if (mode === 'addAdmin') {
        listId = 'addAdminOptionsList';
    } else if (mode === 'addPersonal') {
        listId = 'addPersonalOptionsList';
    } else if (mode === 'addExecutive') {
        listId = 'addExecutiveOptionsList';
    } else if (mode === 'edit') {
        listId = 'editOptionsList_' + id;
    } else if (mode === 'editAdmin') {
        listId = 'editAdminOptionsList_' + id;
    } else if (mode === 'editPersonal') {
        listId = 'editPersonalOptionsList_' + id;
    } else if (mode === 'editExecutive') {
        listId = 'editExecutiveOptionsList_' + id;
    }
    
    var list = document.getElementById(listId);
    if (!list) return;
    
    var rows = list.querySelectorAll('.option-row');
    if (rows.length >= 5) {
        alert('<?php echo $jsMax5Options; ?>');
        return;
    }
    
    var row = document.createElement('div');
    row.className = 'option-row';
    var namePrefix = '';
    if (mode === 'add' || mode === 'addAdmin' || mode === 'addPersonal' || mode === 'addExecutive') {
        namePrefix = 'option_text_';
    } else {
        namePrefix = 'edit_option_text_';
    }
    row.innerHTML = `
        <input type="text" class="form-control" name="${namePrefix}ar[]" placeholder="<?php echo $lang === 'en' ? 'Option (Arabic)' : 'خيار (عربي)'; ?>">
        <input type="text" class="form-control" name="${namePrefix}en[]" placeholder="<?php echo $lang === 'en' ? 'Option (English)' : 'خيار (إنجليزي)'; ?>">
        <button type="button" class="btn btn-danger btn-sm btn-remove-option" onclick="removeOption(this)"><i class="fas fa-times"></i></button>
    `;
    list.appendChild(row);
}

function removeOption(btn) {
    var row = btn.closest('.option-row');
    var list = row.parentElement;
    var rows = list.querySelectorAll('.option-row');
    if (rows.length <= 2) {
        alert('<?php echo $jsAtLeast2Options; ?>');
        return;
    }
    row.remove();
}

// ============================================================
// ===== البحث عن شركة =====
// ============================================================
function searchCompany() {
    var companyId = document.getElementById('searchCompanyId').value;
    if (!companyId) {
        alert('<?php echo $jsPleaseSelectCompany; ?>');
        return;
    }
    
    document.getElementById('searchResults').style.display = 'block';
    document.getElementById('searchResults').innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>';
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'search_company.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    displaySearchResults(data);
                } catch(e) {
                    document.getElementById('searchResults').innerHTML = '<div class="alert alert-danger"><?php echo $jsErrorSearching; ?></div>';
                }
            } else {
                document.getElementById('searchResults').innerHTML = '<div class="alert alert-danger"><?php echo $jsErrorSearching; ?></div>';
            }
        }
    };
    xhr.send('company_id=' + encodeURIComponent(companyId));
}

function displaySearchResults(data) {
    var html = '';
    if (data.success && data.company) {
        var company = data.company;
        html += '<div class="card"><div class="card-body">';
        html += '<h5><i class="fas fa-building text-primary"></i> <?php echo $jsCompanyDetails; ?></h5>';
        html += '<div class="row mt-3">';
        html += '<div class="col-md-6"><strong><?php echo $jsName; ?>:</strong> ' + (company.name || '-') + '</div>';
        html += '<div class="col-md-6"><strong><?php echo $jsIndustry; ?>:</strong> ' + (company.industry || '-') + '</div>';
        html += '<div class="col-md-12 mt-2"><strong><?php echo $jsAddress; ?>:</strong> ' + (company.address || '-') + '</div>';
        html += '<div class="col-md-6 mt-2"><strong><?php echo $jsPhone; ?>:</strong> ' + (company.phone || '-') + '</div>';
        html += '<div class="col-md-6 mt-2"><strong><?php echo $lang === 'en' ? 'Created At' : 'تاريخ الإضافة'; ?>:</strong> ' + (company.created_at || '-') + '</div>';
        html += '</div>';
        
        if (data.polls && data.polls.length > 0) {
            html += '<hr><h6><i class="fas fa-poll text-primary"></i> <?php echo $lang === 'en' ? 'Polls' : 'الاستبيانات'; ?></h6>';
            html += '<div class="table-responsive"><table class="table table-hover">';
            html += '<thead><tr><th><?php echo $jsDateLabel; ?></th><th><?php echo $jsType; ?></th><th><?php echo $jsDepartmentsLabel; ?></th><th><?php echo $jsParticipantsLabel; ?></th><th><?php echo $jsAction; ?></th></tr></thead>';
            html += '<tbody>';
            data.polls.forEach(function(poll) {
                html += '<tr>';
                html += '<td>' + poll.date + '</td>';
                html += '<td><span class="poll-type-badge ' + poll.type + '">' + poll.type_label + '</span></td>';
                html += '<td>' + (poll.departments || '-') + '</td>';
                html += '<td>' + poll.participant_count + '</td>';
                html += '<td>';
                html += '<button class="btn btn-sm btn-primary" onclick="viewPoll(' + poll.id + ')"><i class="fas fa-chart-bar"></i> <?php echo $jsView; ?></button>';
                html += '<button class="btn-delete-poll ms-1" onclick="deletePoll(' + poll.id + ', ' + company.id + ')">';
                html += '<i class="fas fa-trash"></i> <?php echo $jsDeletePoll; ?></button>';
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        } else {
            html += '<hr><p class="text-muted"><?php echo $lang === 'en' ? 'No polls found for this company' : 'لا توجد استبيانات لهذه الشركة'; ?></p>';
        }
        
        html += '</div></div>';
    } else {
        html = '<div class="alert alert-warning"><?php echo $jsCompanyNotFound; ?></div>';
    }
    
    document.getElementById('searchResults').innerHTML = html;
    document.getElementById('searchResults').style.display = 'block';
}

// ============================================================
// ===== دوال التعامل مع الحسابات =====
// ============================================================
function copyAllAccounts() {
    var table = document.getElementById('accountsTable');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    var text = '';
    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].querySelectorAll('td');
        if (cells.length >= 3) {
            text += cells[1].textContent.trim() + ' : ' + cells[2].textContent.trim() + '\n';
        }
    }
    if (text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('<?php echo $jsAllAccountsCopied; ?>');
        }).catch(function() {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('<?php echo $jsAllAccountsCopied; ?>');
        });
    }
}

function printAccounts() {
    var content = document.getElementById('accountsDisplay');
    if (!content) return;
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write('<html><head><title><?php echo $jsPollAccounts; ?></title>');
    printWindow.document.write('<style>body{font-family:Arial;padding:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f5f5}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2><?php echo $jsPollAccounts; ?></h2>');
    printWindow.document.write(content.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

function viewAccounts(event) {
    if (event) event.preventDefault();
    var pollId = document.getElementById('selectedPollId').value;
    if (pollId) {
        window.location.href = 'admin.php?show_accounts=' + pollId + '#section-accounts';
    }
}

function clearAccounts() {
    if (confirm('<?php echo $lang === 'en' ? 'Clear all generated accounts?' : 'مسح جميع الحسابات المنشأة؟'; ?>')) {
        window.location.href = 'admin.php?clear_accounts=1';
    }
}

// ============================================================
// ===== إنشاء حسابات عبر AJAX =====
// ============================================================
function generateAccounts(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    var pollId = document.getElementById('selectedPollId').value;
    
    if (!pollId) {
        alert('<?php echo $lang === 'en' ? 'Please select a poll first' : 'الرجاء اختيار استبيان أولاً'; ?>');
        return;
    }
    
    var generateBtn = document.getElementById('generateAccountsBtn');
    var originalText = generateBtn.innerHTML;
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<span class="loading-spinner"></span> <?php echo $jsGenerating; ?>';
    
    fetch('admin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_generate_accounts=1&poll_id=' + encodeURIComponent(pollId)
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        generateBtn.disabled = false;
        
        if (data.success) {
            displayAccounts(data.accounts, data.poll_info, data.already_exist);
            showAlert('success', data.message);
            
            generateBtn.innerHTML = '<i class="fas fa-check-circle"></i> <?php echo $jsAlreadyGenerated; ?>';
            generateBtn.disabled = true;
            document.getElementById('viewAccountsBtn').style.display = 'none';
            
            updatePollList(pollId, true);
        } else {
            showAlert('danger', data.message);
            generateBtn.innerHTML = originalText;
        }
    })
    .catch(function(error) {
        generateBtn.disabled = false;
        generateBtn.innerHTML = originalText;
        showAlert('danger', '<?php echo $jsErrorGenerating; ?>');
        console.error('Error:', error);
    });
}

// ============================================================
// ===== عرض الحسابات =====
// ============================================================
function displayAccounts(accounts, pollInfo, alreadyExist) {
    var container = document.getElementById('accountsDisplayContainer');
    
    if (!accounts || accounts.length === 0) {
        container.innerHTML = '<div class="alert alert-info mt-3"><?php echo $jsNoAccounts; ?></div>';
        return;
    }
    
    var html = '<div id="accountsDisplay" class="mt-3">';
    html += '<div class="card">';
    html += '<div class="card-header bg-' + (alreadyExist ? 'warning' : 'primary') + ' text-white d-flex justify-content-between align-items-center">';
    html += '<div><i class="fas fa-users"></i> <?php echo $jsGeneratedAccounts; ?> <span class="badge bg-light text-dark ms-2">' + accounts.length + '</span>';
    if (alreadyExist) {
        html += ' <span class="badge bg-warning text-dark ms-2"><i class="fas fa-info-circle"></i> <?php echo $jsAccountsExist; ?></span>';
    }
    html += '</div>';
    html += '<small><i class="fas fa-calendar"></i> ' + pollInfo.date + ' <span class="badge poll-type-badge ' + pollInfo.type + ' ms-1">' + getPollTypeLabel(pollInfo.type) + '</span></small>';
    html += '</div>';
    html += '<div class="card-body">';
    html += '<div class="row mb-3">';
    html += '<div class="col-md-4"><small class="text-muted"><?php echo $jsCompany; ?></small><div class="fw-bold">' + pollInfo.company_name + '</div></div>';
    html += '<div class="col-md-4"><small class="text-muted"><?php echo $jsParticipantsCount; ?></small><div class="fw-bold">' + pollInfo.participant_count + '</div></div>';
    if (pollInfo.departments) {
        html += '<div class="col-md-4"><small class="text-muted"><?php echo $jsDepartments; ?></small><div>' + pollInfo.departments + '</div></div>';
    }
    html += '</div><hr>';
    html += '<div class="accounts-table-wrapper">';
    html += '<table class="table table-hover" id="accountsTable">';
    html += '<thead><tr><th>#</th><th><?php echo $lang === 'en' ? 'Username' : 'اسم المستخدم'; ?></th><th><?php echo $lang === 'en' ? 'Password' : 'كلمة المرور'; ?></th></tr></thead>';
    html += '<tbody>';
    for (var i = 0; i < accounts.length; i++) {
        html += '<tr><td>' + (i + 1) + '</td><td><code>' + escapeHtml(accounts[i].username) + '</code></td><td><code>' + escapeHtml(accounts[i].password) + '</code></td></tr>';
    }
    html += '</tbody></table></div>';
    html += '<div class="mt-3 d-flex gap-2 flex-wrap">';
    html += '<button class="btn btn-primary" onclick="copyAllAccounts()"><i class="fas fa-copy"></i> <?php echo $jsAllAccountsCopied; ?></button>';
    html += '<button class="btn btn-success" onclick="printAccounts()"><i class="fas fa-print"></i> <?php echo $lang === 'en' ? 'Print' : 'طباعة'; ?></button>';
    html += '<button class="btn btn-outline-secondary" onclick="clearAccounts()"><i class="fas fa-times"></i> <?php echo $lang === 'en' ? 'Clear' : 'مسح'; ?></button>';
    html += '</div></div></div></div>';
    
    container.innerHTML = html;
}

function getPollTypeLabel(type) {
    var labels = {
        'regular': '<?php echo $jsRegular; ?>',
        'admin': '<?php echo $jsAdmin; ?>',
        'personal': '<?php echo $jsPersonal; ?>',
        'executive': '<?php echo $jsExecutive; ?>'
    };
    return labels[type] || type;
}

function showAlert(type, message) {
    var container = document.getElementById('alertContainer');
    
    var alertClass = type === 'success' ? 'alert-success-custom' : 
                     type === 'warning' ? 'alert-warning-custom' : 'alert-danger-custom';
    var icon = type === 'success' ? 'fa-check-circle' : 
               type === 'warning' ? 'fa-info-circle' : 'fa-exclamation-circle';
    
    var html = '<div class="alert-custom ' + alertClass + '"><i class="fas ' + icon + '"></i> <span>' + message + '</span></div>';
    
    container.innerHTML = html;
    
    setTimeout(function() {
        var alerts = container.querySelectorAll('.alert-custom');
        alerts.forEach(function(el) {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.5s';
            setTimeout(function() { el.remove(); }, 500);
        });
    }, 5000);
}

function updatePollList(pollId, hasAccounts) {
    var select = document.getElementById('accountPollSelect');
    var options = select.options;
    for (var i = 0; i < options.length; i++) {
        if (options[i].value == pollId) {
            if (hasAccounts && !options[i].text.includes('✅')) {
                options[i].text = options[i].text + ' ✅ <?php echo $jsAccountsExist; ?>';
                options[i].setAttribute('data-has-accounts', 'true');
            }
            break;
        }
    }
}

// ============================================================
// ===== إعداد Autocomplete =====
// ============================================================
function setupAutocomplete(inputId, hiddenId, selectCallback) {
    jQuery('#' + inputId).autocomplete({
        source: companies,
        minLength: 1,
        select: function(event, ui) {
            jQuery('#' + hiddenId).val(ui.item.value);
            if (selectCallback) selectCallback(ui.item.value);
            jQuery(this).val(ui.item.label);
            return false;
        },
        change: function() {
            var value = jQuery(this).val();
            var found = false;
            for (var i = 0; i < companies.length; i++) {
                if (companies[i].label === value) {
                    jQuery('#' + hiddenId).val(companies[i].value);
                    found = true;
                    break;
                }
            }
            if (!found) {
                jQuery('#' + hiddenId).val('');
            }
        }
    });
}

// ============================================================
// ===== تحميل الاستبيانات مع حالة الحسابات =====
// ============================================================
function loadPollsForCompany(companyId) {
    if (!companyId) {
        jQuery('#accountPollSelect').prop('disabled', true)
            .html('<option value=""><?php echo $jsSelectCompanyFirst; ?></option>');
        jQuery('#selectedPollId').val('');
        jQuery('#generateAccountsBtn').prop('disabled', true);
        jQuery('#viewAccountsBtn').hide();
        return;
    }
    
    var filteredPolls = allPolls.filter(function(poll) {
        return poll.company_id == companyId;
    });
    
    var select = jQuery('#accountPollSelect');
    select.prop('disabled', false).empty();
    select.append('<option value=""><?php echo $jsSelectPoll; ?></option>');
    
    if (filteredPolls.length === 0) {
        select.append('<option value=""><?php echo $jsNoPolls; ?></option>');
        jQuery('#generateAccountsBtn').prop('disabled', true);
        jQuery('#viewAccountsBtn').hide();
    } else {
        jQuery.each(filteredPolls, function(index, poll) {
            var optionText = poll.date + ' - ' + poll.type_label;
            if (poll.departments) {
                optionText += ' (' + poll.departments + ')';
            }
            optionText += ' - ' + poll.participant_count + ' <?php echo $jsParticipants; ?>';
            
            if (poll.has_accounts) {
                optionText += ' ✅ ' + '<?php echo $jsAccountsExist; ?>';
            }
            
            select.append('<option value="' + poll.id + '" data-has-accounts="' + poll.has_accounts + '">' + optionText + '</option>');
        });
        
        checkSelectedPoll();
    }
}

// ============================================================
// ===== التحقق من الاستبيان المحدد =====
// ============================================================
function checkSelectedPoll() {
    var select = document.getElementById('accountPollSelect');
    var selectedOption = select.options[select.selectedIndex];
    var hasAccounts = selectedOption.getAttribute('data-has-accounts') === 'true';
    var pollId = select.value;
    
    document.getElementById('selectedPollId').value = pollId;
    
    var generateBtn = document.getElementById('generateAccountsBtn');
    var viewBtn = document.getElementById('viewAccountsBtn');
    
    if (hasAccounts && pollId) {
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<i class="fas fa-check-circle"></i> <?php echo $jsAlreadyGenerated; ?>';
        viewBtn.style.display = 'inline-block';
    } else if (pollId) {
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="fas fa-key"></i> <?php echo $jsGenerate; ?>';
        viewBtn.style.display = 'none';
    } else {
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<i class="fas fa-key"></i> <?php echo $jsGenerate; ?>';
        viewBtn.style.display = 'none';
    }
}

function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================================
// ===== دوال عرض وتعديل الشركات =====
// ============================================================
function viewCompany(companyId) {
    document.getElementById('searchCompanyId').value = companyId;
    document.getElementById('searchCompanyAutocomplete').value = '...';
    navigateToSection('search');
    searchCompany();
}

// ============================================================
// ===== تهيئة الصفحة =====
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    setupAutocomplete('companyAutocomplete', 'selectedCompanyId');
    setupAutocomplete('searchCompanyAutocomplete', 'searchCompanyId');
    setupAutocomplete('accountCompanyAutocomplete', 'accountCompanyId', function(companyId) {
        loadPollsForCompany(companyId);
    });
    
    document.getElementById('accountPollSelect').addEventListener('change', function() {
        checkSelectedPoll();
    });
    
    var menuLinks = document.querySelectorAll('.sidebar-menu li a');
    for (var i = 0; i < menuLinks.length; i++) {
        menuLinks[i].addEventListener('click', function(e) {
            e.preventDefault();
            var section = this.getAttribute('data-section');
            if (section) {
                navigateToSection(section);
                closeSidebar();
            }
        });
    }
    
    var navItems = document.querySelectorAll('.mobile-bottom-nav .nav-item');
    for (var i = 0; i < navItems.length; i++) {
        navItems[i].addEventListener('click', function() {
            var section = this.getAttribute('data-section');
            if (section) {
                navigateToSection(section);
            }
        });
    }
    
    document.getElementById('mobileMenuToggle').addEventListener('click', function() {
        if (document.getElementById('sidebar').classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });
    
    document.getElementById('sidebarOverlay').addEventListener('click', closeSidebar);
    
    setupInfiniteScroll();
    
    var activeSection = null;
    try {
        activeSection = sessionStorage.getItem('activeSection');
    } catch(e) {}
    
    if (activeSection && document.getElementById('section-' + activeSection)) {
        navigateToSection(activeSection);
    } else {
        navigateToSection('companies');
    }
    
    var trigger = document.getElementById('infiniteScrollTrigger');
    if (hasMoreCompanies && totalCompanies > perPage) {
        trigger.classList.add('active');
        document.getElementById('scrollLoader').style.display = 'block';
    } else if (!hasMoreCompanies && totalCompanies > perPage) {
        document.getElementById('scrollNoMore').style.display = 'block';
    }
    
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert-custom');
        for (var i = 0; i < alerts.length; i++) {
            setTimeout(function(el) {
                el.style.opacity = '0';
                el.style.transition = 'opacity 0.5s';
                setTimeout(function() { el.remove(); }, 500);
            }, 100, alerts[i]);
        }
    }, 5000);
});
</script>

</body>
</html>