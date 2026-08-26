<?php
/**
 * صفحة تسجيل الدخول - نسخة مبسطة
 * login.php
 */

// ============================================================
// ===== تشغيل عرض الأخطاء للتصحيح =====
// ============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// ============================================================
// ===== تحديد اللغة =====
// ============================================================
$lang = getPreferredLanguage();
$isRtl = IS_RTL;

// ============================================================
// ===== بدء الجلسة =====
// ============================================================
startSession();

// التحقق من تسجيل الدخول مسبقاً
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === ROLE_ADMIN) {
        header('Location: admin.php');
    } else {
        header('Location: join.php');
    }
    exit;
}

$error = '';
$username = '';

// ============================================================
// ===== معالجة تسجيل الدخول - بطريقة مبسطة جداً =====
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error = $lang === 'en' ? 'Please enter both username and password' : 'الرجاء إدخال اسم المستخدم وكلمة المرور';
    } else {
        try {
            // ============================================================
            // ===== الاتصال بقاعدة البيانات =====
            // ============================================================
            $pdo = getDBConnection();
            
            if (!$pdo) {
                $error = 'Database connection failed';
                throw new Exception('Database connection failed');
            }
            
            // ============================================================
            // ===== 1. جلب المستخدم =====
            // ============================================================
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // ============================================================
            // ===== 2. التحقق من وجود المستخدم =====
            // ============================================================
            if (!$user) {
                $error = $lang === 'en' ? 'Invalid username or password' : 'اسم المستخدم أو كلمة المرور غير صحيحة';
                logError("محاولة تسجيل دخول فاشلة: المستخدم غير موجود - $username");
            } else {
                // ============================================================
                // ===== 3. التحقق من كلمة المرور - بطريقة مباشرة =====
                // ============================================================
                $storedPassword = $user['password'];
                $passwordValid = false;
                
                // الطريقة 1: password_verify
                if (function_exists('password_verify')) {
                    $passwordValid = password_verify($password, $storedPassword);
                }
                
                // الطريقة 2: MD5 (للتوافق)
                if (!$passwordValid && md5($password) === $storedPassword) {
                    $passwordValid = true;
                }
                
                // الطريقة 3: مقارنة مباشرة (إذا كانت كلمة المرور غير مشفرة)
                if (!$passwordValid && $password === $storedPassword) {
                    $passwordValid = true;
                }
                
                // ============================================================
                // ===== 4. إذا كانت كلمة المرور صحيحة =====
                // ============================================================
                if ($passwordValid) {
                    // التحقق من نشاط الحساب
                    if ($user['is_active'] == 0) {
                        $error = $lang === 'en' ? 'This account is inactive' : 'هذا الحساب غير نشط';
                    } else {
                        // ===== تسجيل الدخول =====
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['poll_id'] = $user['poll_id'];
                        $_SESSION['login_time'] = time();
                        
                        // تحديث آخر تسجيل دخول
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                        
                        // توجيه المستخدم
                        if ($user['role'] === ROLE_ADMIN) {
                            header('Location: admin.php');
                        } else {
                            header('Location: join.php');
                        }
                        exit;
                    }
                } else {
                    $error = $lang === 'en' ? 'Invalid username or password' : 'اسم المستخدم أو كلمة المرور غير صحيحة';
                    logError("محاولة تسجيل دخول فاشلة: كلمة مرور خاطئة - $username");
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            logError("خطأ في قاعدة البيانات: " . $e->getMessage(), __FILE__, __LINE__);
        } catch (Exception $e) {
            $error = 'System error: ' . $e->getMessage();
            logError("خطأ عام: " . $e->getMessage(), __FILE__, __LINE__);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $isRtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#4A90E2">
    <title><?php echo $lang === 'en' ? 'Login - Corporate Culture Survey' : 'تسجيل الدخول - استبيانات تقييم ثقافة الشركات'; ?></title>
    
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Background Animation */
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
            animation: float 15s infinite ease-in-out;
        }

        .bg-animation .circle:nth-child(1) { width: 300px; height: 300px; top: -100px; right: -100px; animation-delay: 0s; }
        .bg-animation .circle:nth-child(2) { width: 200px; height: 200px; bottom: -50px; left: -50px; animation-delay: 2s; }
        .bg-animation .circle:nth-child(3) { width: 150px; height: 150px; top: 50%; left: 50%; transform: translate(-50%, -50%); animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(180deg); }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 40px 70px rgba(0, 0, 0, 0.25);
        }

        .logo-container { text-align: center; margin-bottom: 35px; }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(74, 144, 226, 0.3);
            transition: transform 0.3s ease;
        }

        .logo-icon:hover { transform: rotate(10deg) scale(1.05); }
        .logo-icon i { font-size: 40px; color: white; }

        .logo-title { font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0; line-height: 1.3; }
        .logo-subtitle { font-size: 14px; color: var(--text-light); margin-top: 5px; }

        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; font-size: 14px; }

        .form-group .input-group {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            border: 2px solid #E8ECF1;
            transition: all 0.3s ease;
        }

        .form-group .input-group:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
        }

        .form-group .input-group .input-group-text {
            background: transparent;
            border: none;
            padding: 0 15px;
            color: var(--text-light);
            font-size: 18px;
        }

        .form-group .form-control {
            border: none;
            padding: 15px 0;
            font-size: 16px;
            background: transparent;
            color: var(--text-dark);
            font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            height: 55px;
        }

        .form-group .form-control:focus { box-shadow: none; }
        .form-group .form-control::placeholder { color: #B0B8C4; font-size: 14px; }

        .password-toggle {
            cursor: pointer;
            padding: 0 15px;
            color: var(--text-light);
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            background: transparent;
            border: none;
            font-size: 18px;
        }

        .password-toggle:hover { color: var(--primary-color); }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(74, 144, 226, 0.35);
            color: white;
        }

        .btn-login:active { transform: translateY(0); }
        .btn-login i { margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 10px; }

        .btn-login .spinner { display: none; animation: spin 1s linear infinite; }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert-error {
            background: #FEE2E2;
            border: 1px solid #FECACA;
            color: #991B1B;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .alert-error i { font-size: 18px; flex-shrink: 0; }

        .login-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #E8ECF1;
        }

        .login-footer p { color: var(--text-light); font-size: 14px; margin: 0; }
        .login-footer a { color: var(--primary-color); text-decoration: none; font-weight: 600; transition: color 0.3s ease; }
        .login-footer a:hover { color: var(--primary-dark); text-decoration: underline; }

        .language-selector {
            position: fixed;
            top: 20px;
            <?php echo $isRtl ? 'left' : 'right'; ?>: 20px;
            z-index: 100;
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

        .language-selector button.active { background: var(--primary-color); color: white; }
        .language-selector button:hover:not(.active) { background: #F0F4F8; }

        @media (max-width: 576px) {
            .login-card { padding: 30px 20px; border-radius: 20px; }
            .logo-title { font-size: 20px; }
            .logo-icon { width: 60px; height: 60px; }
            .logo-icon i { font-size: 30px; }
            .form-group .form-control { height: 48px; font-size: 15px; }
            .btn-login { font-size: 16px; padding: 14px; }
            .language-selector { top: 10px; <?php echo $isRtl ? 'left' : 'right'; ?>: 10px; padding: 5px; }
            .language-selector button { padding: 6px 12px; font-size: 12px; }
            body { padding: 10px; }
        }

        @media (max-width: 400px) {
            .login-card { padding: 20px 15px; }
            .logo-title { font-size: 18px; }
            .form-group { margin-bottom: 18px; }
        }

        @media (min-width: 768px) {
            .login-card { padding: 60px 50px; }
        }

        @media (prefers-color-scheme: dark) {
            .login-card {
                background: rgba(30, 30, 40, 0.95);
                border-color: rgba(255, 255, 255, 0.05);
            }
            .logo-title { color: #ECF0F1; }
            .form-group label { color: #ECF0F1; }
            .form-group .form-control { color: #ECF0F1; }
            .form-group .input-group { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); }
            .login-footer { border-top-color: rgba(255, 255, 255, 0.1); }
            .login-footer p { color: #A0A8B4; }
            .language-selector { background: rgba(30, 30, 40, 0.95); }
            .language-selector button { color: #A0A8B4; }
            .language-selector button:hover:not(.active) { background: rgba(255, 255, 255, 0.05); }
        }
    </style>
</head>
<body>

<!-- Background Animation -->
<div class="bg-animation">
    <div class="circle"></div>
    <div class="circle"></div>
    <div class="circle"></div>
</div>

<!-- Language Selector -->
<div class="language-selector">
    <button class="<?php echo $lang === 'ar' ? 'active' : ''; ?>" onclick="switchLanguage('ar')"><i class="fas fa-flag"></i> العربية </button>
    <button class="<?php echo $lang === 'en' ? 'active' : ''; ?>" onclick="switchLanguage('en')"><i class="fas fa-flag"></i> English </button>
</div>

<!-- Login Container -->
<div class="login-wrapper">
    <div class="login-card">
        <!-- Logo -->
        <div class="logo-container">
            <div class="logo-icon"><img src="/images/logo.png" style="height: 50px;width: 100px;" alt="OrgFitSA"></div>
            <h1 class="logo-title"><?php echo $lang === 'en' ? 'Welcome Back' : 'مرحباً بعودتك'; ?></h1>
            <p class="logo-subtitle"><?php echo $lang === 'en' ? 'Sign in to access your dashboard' : 'سجل الدخول للوصول إلى لوحة التحكم'; ?></p>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
        <div class="alert-error" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm" autocomplete="off">
            <div class="form-group">
                <label for="username"><i class="fas fa-user input-icon"></i> <?php echo $lang === 'en' ? 'Username' : 'اسم المستخدم'; ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user input-icon"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="<?php echo $lang === 'en' ? 'Enter your username' : 'أدخل اسم المستخدم'; ?>" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock input-icon"></i> <?php echo $lang === 'en' ? 'Password' : 'كلمة المرور'; ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock input-icon"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="<?php echo $lang === 'en' ? 'Enter your password' : 'أدخل كلمة المرور'; ?>" required autocomplete="current-password">
                    <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <span id="loginText"><i class="fas fa-sign-in-alt"></i> <?php echo $lang === 'en' ? 'Sign In' : 'تسجيل الدخول'; ?></span>
                <span id="loginSpinner" class="spinner"><i class="fas fa-spinner"></i></span>
            </button>
        </form>

        <!-- Footer -->
        <div class="login-footer">
            <p><?php echo $lang === 'en' ? 'Need help? Contact your system administrator' : 'بحاجة للمساعدة؟ تواصل مع مسؤول النظام'; ?></p>
        </div>
    </div>
</div>

<!-- jQuery 3 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    'use strict';
    
    // ===== تبديل إظهار كلمة المرور =====
    $('#togglePassword').on('click', function() {
        const passwordInput = $('#password');
        const icon = $(this).find('i');
        
        if (passwordInput.attr('type') === 'password') {
            passwordInput.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            passwordInput.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // ===== منع إرسال النموذج الفارغ =====
    $('#loginForm').on('submit', function(e) {
        const username = $('#username').val().trim();
        const password = $('#password').val().trim();
        
        if (!username || !password) {
            e.preventDefault();
            let errorMessage = '<?php echo $lang === "en" ? "Please enter both username and password" : "الرجاء إدخال اسم المستخدم وكلمة المرور"; ?>';
            
            const alertDiv = $('<div class="alert-error" role="alert">')
                .html('<i class="fas fa-exclamation-circle"></i><span>' + errorMessage + '</span>');
            
            $('.alert-error').remove();
            $('#loginForm').before(alertDiv);
            
            $('html, body').animate({
                scrollTop: alertDiv.offset().top - 20
            }, 300);
            
            setTimeout(function() {
                alertDiv.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
            
            return false;
        }
        
        // ===== إظهار مؤشر التحميل =====
        $('#loginText').hide();
        $('#loginSpinner').show();
        $('#loginBtn').prop('disabled', true);
        
        // ===== إزالة رسائل الخطأ السابقة =====
        $('.alert-error').remove();
        
        return true;
    });

    // ===== التحقق من صحة اسم المستخدم =====
    $('#username').on('keyup', function() {
        const value = $(this).val();
        $(this).val(value.replace(/[^a-zA-Z0-9_]/g, ''));
    });

    // ===== التركيز على حقل اسم المستخدم =====
    setTimeout(function() { $('#username').focus(); }, 500);

    // ===== دعم مفتاح Enter =====
    $('#password').on('keypress', function(e) {
        if (e.which === 13) { $('#loginForm').submit(); }
    });

    // ===== منع النقر المزدوج =====
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('loginBtn');
                if (btn) {
                    btn.disabled = true;
                    setTimeout(function() { btn.disabled = false; }, 5000);
                }
            });
        }
    });
});

// ===== تبديل اللغة =====
function switchLanguage(lang) {
    $.ajax({
        url: 'set_language.php',
        method: 'POST',
        data: { language: lang },
        success: function() { location.reload(); },
        error: function() { window.location.href = window.location.pathname + '?lang=' + lang; }
    });
}
</script>

</body>
</html>