<?php
/**
 * الصفحة الرئيسية للموقع
 * index.php
 * تعرض مقدمة عن الموقع وروابط لتسجيل الدخول
 */

require_once 'config.php';

// التحقق من تسجيل الدخول
startSession();
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === ROLE_ADMIN) {
        header('Location: admin.php');
        exit;
    } else {
        header('Location: join.php');
        exit;
    }
}

// الحصول على اللغة
$lang = getPreferredLanguage();
$isRtl = IS_RTL;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $isRtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="theme-color" content="#4A90E2">
    <meta name="description" content="<?php echo $lang === 'en' ? 'Corporate Culture Survey Platform' : 'منصة استبيانات تقييم ثقافة الشركات'; ?>">
    <title><?php echo $lang === 'en' ? 'Corporate Culture Survey Platform' : 'منصة استبيانات تقييم ثقافة الشركات'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    --mobile-nav-height: 70px;
    --mobile-nav-bg: rgba(255, 255, 255, 0.98);
    --mobile-nav-shadow: 0 -5px 30px rgba(0, 0, 0, 0.08);
}

* {
    margin: 0;    
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
    min-height: 100vh;
    background: var(--bg-light);
    overflow-x: hidden;
    padding-bottom: calc(var(--mobile-nav-height) + 20px);
}

/* ===== Mobile Bottom Navigation ===== */
.mobile-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--mobile-nav-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    padding: 8px 0 calc(8px + env(safe-area-inset-bottom));
    z-index: 9999;
    justify-content: space-around;
    align-items: center;
    box-shadow: var(--mobile-nav-shadow);
    border-radius: 20px 20px 0 0;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
    height: var(--mobile-nav-height);
    min-height: var(--mobile-nav-height);
    max-height: var(--mobile-nav-height);
    will-change: transform, opacity;
    /* ضمان الثبات في الأسفل */
    transform: translateY(0);
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

/* منع إخفاء القائمة عند التمرير */
.mobile-bottom-nav.hide-on-scroll {
    /* تم تعطيل خاصية الإخفاء */
    transform: translateY(0) !important;
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

.mobile-bottom-nav .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: var(--text-light);
    font-size: 10px;
    padding: 4px 12px;
    transition: var(--transition);
    border: none;
    background: none;
    font-family: <?php echo $isRtl ? "'Cairo', sans-serif" : "'Inter', sans-serif"; ?>;
    position: relative;
    min-width: 60px;
    height: 100%;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}

.mobile-bottom-nav .nav-item i {
    font-size: 22px;
    margin-bottom: 2px;
    transition: var(--transition);
    line-height: 1;
}

.mobile-bottom-nav .nav-item span {
    font-weight: 600;
    font-size: 10px;
    letter-spacing: 0.3px;
    line-height: 1.2;
    text-align: center;
}

.mobile-bottom-nav .nav-item.active {
    color: var(--primary-color);
}

.mobile-bottom-nav .nav-item.active i {
    transform: translateY(-2px);
    color: var(--primary-color);
}

.mobile-bottom-nav .nav-item.active::before {
    content: '';
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 30px;
    height: 3px;
    background: var(--primary-color);
    border-radius: 3px;
    box-shadow: 0 0 10px rgba(74, 144, 226, 0.3);
}

.mobile-bottom-nav .nav-item:hover {
    color: var(--primary-color);
}

.mobile-bottom-nav .nav-item:hover i {
    transform: scale(1.1);
}

.mobile-bottom-nav .nav-item:active {
    transform: scale(0.95);
}

.mobile-bottom-nav .nav-item .badge-dot {
    position: absolute;
    top: 2px;
    right: 5px;
    width: 8px;
    height: 8px;
    background: #e74c3c;
    border-radius: 50%;
    border: 2px solid white;
    animation: pulse-dot 2s ease-in-out infinite;
}

@keyframes pulse-dot {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.3);
        opacity: 0.7;
    }
}

/* ===== Navigation ===== */
.navbar-custom {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
    padding: 12px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    transition: var(--transition);
}

.navbar-custom .navbar-brand {
    font-weight: 800;
    font-size: 22px;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    transition: var(--transition);
}

.navbar-custom .navbar-brand:hover {
    color: var(--primary-color);
}

.navbar-custom .navbar-brand i {
    color: var(--primary-color);
    font-size: 28px;
    transition: var(--transition);
}

.navbar-custom .navbar-brand:hover i {
    transform: rotate(-10deg) scale(1.1);
}

.navbar-custom .navbar-nav .nav-link {
    font-weight: 600;
    color: var(--text-light);
    transition: var(--transition);
    padding: 8px 16px;
    border-radius: 10px;
    position: relative;
}

.navbar-custom .navbar-nav .nav-link::after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 2px;
    background: var(--primary-color);
    transition: var(--transition);
    border-radius: 2px;
}

.navbar-custom .navbar-nav .nav-link:hover::after {
    width: 60%;
}

.navbar-custom .navbar-nav .nav-link:hover {
    color: var(--primary-color);
    background: rgba(74, 144, 226, 0.05);
}

.navbar-custom .btn-login-nav {
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    color: white;
    padding: 10px 24px;
    border-radius: 12px;
    font-weight: 600;
    border: none;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(74, 144, 226, 0.2);
}

.navbar-custom .btn-login-nav:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
    color: white;
}

.navbar-custom .btn-login-nav:active {
    transform: translateY(0);
}

.navbar-custom .language-toggle {
    background: var(--bg-light);
    border: none;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    color: var(--text-dark);
    transition: var(--transition);
    cursor: pointer;
    margin-<?php echo $isRtl ? 'left' : 'right'; ?>: 10px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.navbar-custom .language-toggle:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(74, 144, 226, 0.2);
}

.navbar-custom .language-toggle:active {
    transform: translateY(0);
}

.navbar-custom .navbar-toggler {
    border: none;
    padding: 8px 12px;
    border-radius: 10px;
    transition: var(--transition);
}

.navbar-custom .navbar-toggler:hover {
    background: var(--bg-light);
}

.navbar-custom .navbar-toggler i {
    font-size: 24px;
    color: var(--text-dark);
}

/* ===== Hero Section ===== */
.hero-section {
    padding: 80px 0 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
    min-height: 600px;
    display: flex;
    align-items: center;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.5;
}

.hero-section .bg-animation {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    overflow: hidden;
}

.hero-section .bg-animation .circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    animation: float 20s infinite ease-in-out;
}

.hero-section .bg-animation .circle:nth-child(1) {
    width: 400px;
    height: 400px;
    top: -150px;
    right: -150px;
    animation-delay: 0s;
}

.hero-section .bg-animation .circle:nth-child(2) {
    width: 300px;
    height: 300px;
    bottom: -100px;
    left: -100px;
    animation-delay: 5s;
}

.hero-section .bg-animation .circle:nth-child(3) {
    width: 200px;
    height: 200px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation-delay: 10s;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-30px) rotate(180deg);
    }
}

.hero-content {
    position: relative;
    z-index: 1;
    color: white;
}

.hero-content .badge-feature {
    display: inline-block;
    background: rgba(255, 255, 255, 0.15);
    padding: 6px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    animation: fadeInDown 1s ease-out;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero-content h1 {
    font-size: 48px;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 20px;
    animation: fadeInLeft 1s ease-out 0.2s both;
}

@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.hero-content h1 .highlight {
    color: #FFD700;
    text-shadow: 0 2px 20px rgba(255, 215, 0, 0.3);
    position: relative;
}

.hero-content h1 .highlight::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: #FFD700;
    border-radius: 2px;
    opacity: 0.3;
}

.hero-content p {
    font-size: 18px;
    opacity: 0.9;
    max-width: 500px;
    line-height: 1.7;
    margin-bottom: 30px;
    animation: fadeInLeft 1s ease-out 0.4s both;
}

.hero-content .hero-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    animation: fadeInUp 1s ease-out 0.6s both;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero-content .btn-hero-primary {
    background: white;
    color: var(--primary-color);
    padding: 14px 35px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    border: none;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.hero-content .btn-hero-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    color: var(--primary-color);
}

.hero-content .btn-hero-primary:active {
    transform: translateY(0);
}

.hero-content .btn-hero-secondary {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    padding: 14px 35px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
}

.hero-content .btn-hero-secondary:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: white;
    color: white;
    transform: translateY(-3px);
}

.hero-content .btn-hero-secondary:active {
    transform: translateY(0);
}

.hero-image {
    position: relative;
    z-index: 1;
    text-align: center;
    animation: fadeInRight 1s ease-out 0.4s both;
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.hero-image .floating-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 30px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    animation: floatY 3s ease-in-out infinite;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
}

@keyframes floatY {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-15px);
    }
}

.hero-image .floating-card i {
    font-size: 80px;
    color: rgba(255, 255, 255, 0.3);
    display: block;
    margin-bottom: 15px;
}

.hero-image .floating-card .stat-number {
    font-size: 48px;
    font-weight: 800;
}

.hero-image .floating-card .stat-label {
    font-size: 14px;
    opacity: 0.8;
}

/* ===== Features Section ===== */
.features-section {
    padding: 80px 0;
    background: white;
    position: relative;
}

.section-header {
    text-align: center;
    margin-bottom: 50px;
}

.section-header h2 {
    font-size: 36px;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 10px;
}

.section-header p {
    color: var(--text-light);
    font-size: 18px;
    max-width: 600px;
    margin: 0 auto;
}

.feature-card {
    background: var(--bg-light);
    border-radius: var(--border-radius);
    padding: 30px 25px;
    transition: var(--transition);
    height: 100%;
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
}

.feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(74, 144, 226, 0.02), rgba(74, 144, 226, 0.05));
    opacity: 0;
    transition: var(--transition);
}

.feature-card:hover::before {
    opacity: 1;
}

.feature-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--card-shadow);
    border-color: rgba(74, 144, 226, 0.1);
}

.feature-card .icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    transition: var(--transition);
    box-shadow: 0 4px 15px rgba(74, 144, 226, 0.2);
}

.feature-card:hover .icon-wrapper {
    transform: rotate(-10deg) scale(1.05);
    box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
}

.feature-card .icon-wrapper i {
    font-size: 28px;
    color: white;
    transition: var(--transition);
}

.feature-card:hover .icon-wrapper i {
    transform: scale(1.1);
}

.feature-card h5 {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 10px;
    transition: var(--transition);
}

.feature-card:hover h5 {
    color: var(--primary-color);
}

.feature-card p {
    color: var(--text-light);
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}

/* ===== Stats Section ===== */
.stats-section {
    padding: 60px 0;
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    color: white;
    position: relative;
    overflow: hidden;
}

.stats-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.1;
}

.stats-section .stat-item {
    text-align: center;
    padding: 20px;
    position: relative;
    z-index: 1;
}

.stats-section .stat-item .number {
    font-size: 42px;
    font-weight: 800;
    display: block;
    text-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
}

.stats-section .stat-item .label {
    font-size: 16px;
    opacity: 0.8;
    margin-top: 5px;
    font-weight: 500;
}

/* ===== CTA Section ===== */
.cta-section {
    padding: 80px 0;
    background: white;
    text-align: center;
    position: relative;
}

.cta-section h2 {
    font-size: 36px;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 15px;
}

.cta-section p {
    color: var(--text-light);
    font-size: 18px;
    max-width: 600px;
    margin: 0 auto 30px;
}

.cta-section .btn-cta {
    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    color: white;
    padding: 16px 40px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 18px;
    border: none;
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 20px rgba(74, 144, 226, 0.3);
}

.cta-section .btn-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(74, 144, 226, 0.3);
    color: white;
}

.cta-section .btn-cta:active {
    transform: translateY(0);
}

/* ===== Footer ===== */
.footer {
    background: #1a2332;
    color: rgba(255, 255, 255, 0.7);
    padding: 40px 0 20px;
    position: relative;
}

.footer .footer-brand {
    font-size: 22px;
    font-weight: 800;
    color: white;
    display: flex;
    align-items: center;
    gap: 10px;
}

.footer .footer-brand i {
    color: var(--primary-color);
}

.footer p {
    font-size: 14px;
    line-height: 1.8;
    margin-top: 10px;
}

.footer .footer-links a {
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    transition: var(--transition);
    display: block;
    padding: 4px 0;
    font-size: 14px;
}

.footer .footer-links a:hover {
    color: white;
    padding-<?php echo $isRtl ? 'right' : 'left'; ?>: 5px;
}

.footer .footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    padding-top: 20px;
    margin-top: 30px;
    text-align: center;
    font-size: 13px;
}

/* ===== Responsive ===== */
@media (max-width: 991px) {
    .hero-section {
        padding: 60px 0 40px;
        min-height: auto;
    }

    .hero-content h1 {
        font-size: 36px;
    }

    .hero-content p {
        font-size: 16px;
    }

    .hero-image .floating-card {
        margin-top: 30px;
        padding: 20px;
    }

    .hero-image .floating-card i {
        font-size: 60px;
    }

    .hero-image .floating-card .stat-number {
        font-size: 36px;
    }

    .section-header h2 {
        font-size: 32px;
    }
}

@media (max-width: 768px) {
    /* إظهار القائمة السفلية للجوال مع الثبات في الأسفل */
    .mobile-bottom-nav {
        display: flex !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        transform: translateY(0) !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        /* منع الإخفاء تماماً */
        transition: none !important;
    }

    body {
        padding-bottom: calc(var(--mobile-nav-height) + 20px);
    }

    .hero-content h1 {
        font-size: 28px;
    }

    .hero-content .hero-buttons {
        flex-direction: column;
        align-items: stretch;
    }

    .hero-content .btn-hero-primary,
    .hero-content .btn-hero-secondary {
        justify-content: center;
        padding: 12px 25px;
    }

    .section-header h2 {
        font-size: 28px;
    }

    .feature-card {
        padding: 20px;
    }

    .stats-section .stat-item .number {
        font-size: 32px;
    }

    .cta-section h2 {
        font-size: 28px;
    }

    .navbar-custom .navbar-brand {
        font-size: 18px;
    }

    .navbar-custom .btn-login-nav {
        padding: 8px 16px;
        font-size: 14px;
    }

    .navbar-custom .language-toggle span {
        display: none;
    }

    .navbar-custom .language-toggle i {
        font-size: 18px;
    }

    .hero-section {
        padding: 40px 0 30px;
        min-height: auto;
    }
}

@media (max-width: 576px) {
    .hero-section {
        padding: 30px 0 20px;
    }

    .hero-content h1 {
        font-size: 24px;
    }

    .hero-content .badge-feature {
        font-size: 11px;
        padding: 4px 14px;
    }

    .hero-content p {
        font-size: 14px;
    }

    .section-header h2 {
        font-size: 24px;
    }

    .section-header p {
        font-size: 14px;
    }

    .feature-card .icon-wrapper {
        width: 50px;
        height: 50px;
    }

    .feature-card .icon-wrapper i {
        font-size: 22px;
    }

    .stats-section .stat-item .number {
        font-size: 28px;
    }

    .stats-section .stat-item .label {
        font-size: 14px;
    }

    .cta-section h2 {
        font-size: 24px;
    }

    .cta-section p {
        font-size: 16px;
    }

    .cta-section .btn-cta {
        padding: 14px 30px;
        font-size: 16px;
    }

    .footer .footer-brand {
        font-size: 18px;
    }

    /* تحسين القائمة السفلية للجوال */
    .mobile-bottom-nav {
        padding: 6px 0 calc(6px + env(safe-area-inset-bottom));
        height: calc(var(--mobile-nav-height) - 10px);
        min-height: calc(var(--mobile-nav-height) - 10px);
        max-height: calc(var(--mobile-nav-height) - 10px);
    }

    .mobile-bottom-nav .nav-item {
        padding: 2px 8px;
        font-size: 9px;
        min-width: 50px;
    }

    .mobile-bottom-nav .nav-item i {
        font-size: 18px;
    }

    .mobile-bottom-nav .nav-item span {
        font-size: 9px;
    }

    .mobile-bottom-nav .nav-item .badge-dot {
        width: 6px;
        height: 6px;
        top: 0;
        right: 3px;
    }
}

@media (max-width: 400px) {
    .hero-content h1 {
        font-size: 20px;
    }

    .section-header h2 {
        font-size: 20px;
    }

    .feature-card {
        padding: 15px;
    }

    .feature-card .icon-wrapper {
        width: 40px;
        height: 40px;
    }

    .feature-card .icon-wrapper i {
        font-size: 18px;
    }

    .feature-card h5 {
        font-size: 16px;
    }

    .feature-card p {
        font-size: 13px;
    }

    .mobile-bottom-nav .nav-item {
        min-width: 40px;
        padding: 2px 6px;
    }

    .mobile-bottom-nav .nav-item i {
        font-size: 16px;
    }

    .mobile-bottom-nav .nav-item span {
        font-size: 8px;
    }

    .mobile-bottom-nav {
        height: calc(var(--mobile-nav-height) - 15px);
        min-height: calc(var(--mobile-nav-height) - 15px);
        max-height: calc(var(--mobile-nav-height) - 15px);
        padding: 4px 0 calc(4px + env(safe-area-inset-bottom));
    }

    body {
        padding-bottom: calc(var(--mobile-nav-height) + 10px);
    }
}

/* ===== Dark Mode ===== */
@media (prefers-color-scheme: dark) {
    :root {
        --mobile-nav-bg: rgba(26, 35, 50, 0.98);
        --mobile-nav-shadow: 0 -5px 30px rgba(0, 0, 0, 0.2);
    }

    .navbar-custom {
        background: rgba(26, 35, 50, 0.95);
        border-bottom-color: rgba(255, 255, 255, 0.05);
    }

    .navbar-custom .navbar-brand {
        color: #ECF0F1;
    }

    .navbar-custom .navbar-nav .nav-link {
        color: rgba(255, 255, 255, 0.6);
    }

    .navbar-custom .navbar-nav .nav-link:hover {
        color: white;
        background: rgba(255, 255, 255, 0.05);
    }

    .navbar-custom .language-toggle {
        background: rgba(255, 255, 255, 0.05);
        color: #ECF0F1;
    }

    .navbar-custom .language-toggle:hover {
        background: var(--primary-color);
        color: white;
    }

    .navbar-custom .navbar-toggler i {
        color: #ECF0F1;
    }

    .navbar-custom .navbar-toggler:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .mobile-bottom-nav {
        background: var(--mobile-nav-bg);
        border-top-color: rgba(255, 255, 255, 0.05);
    }

    .mobile-bottom-nav .nav-item {
        color: rgba(255, 255, 255, 0.4);
    }

    .mobile-bottom-nav .nav-item.active {
        color: var(--primary-color);
    }

    .mobile-bottom-nav .nav-item .badge-dot {
        border-color: #1a2332;
    }

    .features-section {
        background: #1a2332;
    }

    .section-header h2 {
        color: #ECF0F1;
    }

    .section-header p {
        color: #A0A8B4;
    }

    .feature-card {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.03);
    }

    .feature-card:hover {
        border-color: rgba(74, 144, 226, 0.2);
        background: rgba(255, 255, 255, 0.05);
    }

    .feature-card h5 {
        color: #ECF0F1;
    }

    .feature-card p {
        color: #A0A8B4;
    }

    .cta-section {
        background: #1a2332;
    }

    .cta-section h2 {
        color: #ECF0F1;
    }

    .cta-section p {
        color: #A0A8B4;
    }

    .footer {
        background: #0d1117;
    }
}

/* ===== AOS Animation ===== */
.aos-animate {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.8s ease-out;
}

.aos-animate.aos-visible {
    opacity: 1;
    transform: translateY(0);
}

/* ===== Scrollbar Styling ===== */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-light);
}

::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 4px;
    transition: var(--transition);
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-color);
}

/* ===== Selection Styling ===== */
::selection {
    background: var(--primary-color);
    color: white;
}

::-moz-selection {
    background: var(--primary-color);
    color: white;
}

/* ===== Print Styles ===== */
@media print {
    .mobile-bottom-nav {
        display: none !important;
    }

    .navbar-custom {
        position: relative !important;
    }

    .hero-section {
        min-height: auto !important;
        padding: 40px 0 !important;
    }

    .hero-section .bg-animation {
        display: none !important;
    }

    .feature-card:hover {
        transform: none !important;
    }

    .footer {
        background: #f5f5f5 !important;
        color: #333 !important;
    }
}

/* ===== Safe Area Support ===== */
@supports (padding: max(0px)) {
    .mobile-bottom-nav {
        padding-bottom: max(8px, env(safe-area-inset-bottom));
    }
}

/* ===== Reduced Motion ===== */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }

    .hero-image .floating-card {
        animation: none !important;
    }

    .hero-section .bg-animation .circle {
        animation: none !important;
    }

    .mobile-bottom-nav .nav-item .badge-dot {
        animation: none !important;
    }

    .aos-animate {
        opacity: 1 !important;
        transform: none !important;
    }

    .aos-animate.aos-visible {
        opacity: 1 !important;
        transform: none !important;
    }
}
</style>
</head>
<body>

<!-- ===== Mobile Bottom Navigation ===== -->
<div class="mobile-bottom-nav" id="mobileBottomNav">
    <a href="#home" class="nav-item active" data-section="home">
        <i class="fas fa-home"></i>
        <span><?php echo $lang === 'en' ? 'Home' : 'الرئيسية'; ?></span>
    </a>
    <a href="#features" class="nav-item" data-section="features">
        <i class="fas fa-star"></i>
        <span><?php echo $lang === 'en' ? 'Features' : 'المميزات'; ?></span>
    </a>
    <a href="#about" class="nav-item" data-section="about">
        <i class="fas fa-info-circle"></i>
        <span><?php echo $lang === 'en' ? 'About' : 'عن الموقع'; ?></span>
    </a>
    <a href="login.php" class="nav-item" data-section="login">
        <i class="fas fa-sign-in-alt"></i>
        <span><?php echo $lang === 'en' ? 'Login' : 'تسجيل الدخول'; ?></span>
        <span class="badge-dot"></span>
    </a>
</div>

<!-- ===== Navigation ===== -->
<nav class="navbar-custom navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="/images/logo.png" style="height: 50px;width: 100%;"alt="OrgFitSA">
            <span><?php echo $lang === 'en' ? 'OrgFit' : 'أورج فت'; ?></span>
        </a>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars" style="font-size: 24px; color: var(--text-dark);"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="#features">
                        <i class="fas fa-star"></i>
                        <?php echo $lang === 'en' ? 'Features' : 'المميزات'; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#about">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $lang === 'en' ? 'About' : 'عن الموقع'; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <button class="language-toggle" onclick="switchLanguage('<?php echo $lang === 'ar' ? 'en' : 'ar'; ?>')">
                        <i class="fas fa-globe"></i>
                        <span><?php echo $lang === 'ar' ? 'English' : 'العربية'; ?></span>
                    </button>
                </li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a href="login.php" class="btn-login-nav">
                        <i class="fas fa-sign-in-alt"></i>
                        <?php echo $lang === 'en' ? 'Login' : 'تسجيل الدخول'; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ===== Hero Section ===== -->
<section class="hero-section" id="home">
    <div class="bg-animation">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>
    
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 hero-content">
                <div class="badge-feature">
                    <i class="fas fa-rocket"></i>
                    <?php echo $lang === 'en' ? 'v2.0 - Latest Features' : 'الإصدار 2.0 - أحدث المميزات'; ?>
                </div>
                <h1>
                    <?php echo $lang === 'en' 
                        ? 'Transform Your <span class="highlight">Company Culture</span> with Data' 
                        : 'حوّل <span class="highlight">ثقافة شركتك</span> بالبيانات'; ?>
                </h1>
                <p>
                    <?php echo $lang === 'en' 
                        ? 'Comprehensive survey platform designed to measure, analyze, and improve your organizational culture with actionable insights.'
                        : 'منصة استبيانات شاملة مصممة لقياس وتحليل وتحسين ثقافة مؤسستك برؤى قابلة للتنفيذ.'; ?>
                </p>
                <div class="hero-buttons">
                    <a href="login.php" class="btn-hero-primary">
                        <i class="fas fa-arrow-<?php echo $isRtl ? 'left' : 'right'; ?>"></i>
                        <?php echo $lang === 'en' ? 'Get Started' : 'ابدأ الآن'; ?>
                    </a>
                    <a href="#features" class="btn-hero-secondary">
                        <i class="fas fa-play-circle"></i>
                        <?php echo $lang === 'en' ? 'Learn More' : 'اعرف المزيد'; ?>
                    </a>
                </div>
            </div>
            <div class="col-lg-6 hero-image">
                <div class="floating-card">
                    <i class="fas fa-poll"></i>
                    <div class="stat-number">50+</div>
                    <div class="stat-label"><?php echo $lang === 'en' ? 'Investigative Questions' : 'سؤال إستقصائي'; ?></div>
                    <hr style="border-color: rgba(255,255,255,0.1);">
                    <div class="row">
						<!--
                        <div class="col-6">
                            <div class="stat-number" style="font-size: 28px;">18</div>
                            <div class="stat-label"><?php echo $lang === 'en' ? 'Admin Questions' : 'سؤال إداري'; ?></div>
                        </div>
						-->
						<!--
                        <div class="col-6">
                            <div class="stat-number" style="font-size: 28px;">10+</div>
                            <div class="stat-label"><?php echo $lang === 'en' ? 'Analysis Metrics' : 'مقياس تحليلي'; ?></div>
                        </div>
						-->
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== Features Section ===== -->
<section class="features-section" id="features">
    <div class="container">
        <div class="section-header aos-animate">
            <span style="color: var(--primary-color); font-weight: 700; text-transform: uppercase; letter-spacing: 2px; font-size: 13px;">
                <?php echo $lang === 'en' ? 'Why Choose Us' : 'لماذا تختارنا'; ?>
            </span>
            <h2>
                <?php echo $lang === 'en' 
                    ? 'Powerful Features for <br>Culture Assessment' 
                    : 'مميزات قوية لتقييم <br>الثقافة المؤسسية'; ?>
            </h2>
            <p>
                <?php echo $lang === 'en' 
                    ? 'Everything you need to understand and improve your company culture in one place.'
                    : 'كل ما تحتاجه لفهم وتحسين ثقافة شركتك في مكان واحد.'; ?>
            </p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4 aos-animate">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-building"></i>
                    </div>
                    <h5><?php echo $lang === 'en' ? 'Company Management' : 'إدارة الشركات'; ?></h5>
                    <p>
                        <?php echo $lang === 'en' 
                            ? 'Easily add and manage multiple companies with detailed profiles and history.'
                            : 'أضف وأدر شركات متعددة بسهولة مع ملفات تعريف وتاريخ مفصل.'; ?>
                    </p>
                </div>
            </div>
            
            <div class="col-md-4 aos-animate">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-poll"></i>
                    </div>
                    <h5><?php echo $lang === 'en' ? 'Custom Surveys' : 'استبيانات مخصصة'; ?></h5>
<p> <?php echo $lang === 'en' ? 'Create specialized surveys for different groups.' : 'انشئ استبيانات متخصصه لفئات مختلفة'; ?> </p>
                </div>
            </div>
            
            <div class="col-md-4 aos-animate">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5><?php echo $lang === 'en' ? 'Secured Accounts' : 'حسابات مؤمنة'; ?></h5>
                    <p>
                        <?php echo $lang === 'en' 
                            ? 'Generate secured accounts for participants with secure passwords for anonymous feedback.'
                            : 'أنشئ حسابات مؤمنة للمشاركين بكلمات مرور آمنة لتقديم ملاحظات مجهولة.'; ?>
                    </p>
                </div>
            </div>
            
            <div class="col-md-4 aos-animate">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5><?php echo $lang === 'en' ? 'Advanced Analytics' : 'تحليلات متقدمة'; ?></h5>
<p> <?php echo $lang === 'en' ? 'Get detailed statistics that put you on the right track.' : 'احصل على احصائيات مفصلة تضعك في المسار الصحيح'; ?> </p>
                </div>
            </div>
            
            <div class="col-md-4 aos-animate">
                <div class="feature-card">
                    <div class="icon-wrapper">
						<i class="fas fa-truck-fast"></i>
                    </div>
                    <h5><?php echo $lang === 'en' ? 'Immediate Results' : 'نتائج فورية'; ?></h5>
                    <p>
                        <?php echo $lang === 'en' 
                            ? 'Get the survey results immediately upon completion.'
                            : 'أحصل على نتائج الإستبيان فوراً بمجرد الإنهاء'; ?>
                    </p>
                </div>
            </div>
            
            <div class="col-md-4 aos-animate">
                <div class="feature-card">
                    <div class="icon-wrapper">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h5><?php echo $lang === 'en' ? 'Responsive Design' : 'تصميم متجاوب'; ?></h5>
                    <p>
                        <?php echo $lang === 'en' 
                            ? 'Works perfectly on all devices with a mobile-first approach and app-like experience.'
                            : 'يعمل بشكل مثالي على جميع الأجهزة مع نهج الجوال أولاً وتجربة تشبه التطبيق.'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== Stats Section ===== -->
<section class="stats-section" id="about">
    <div class="container">
        <div class="row">
            <div class="col-12 col-md-4 stat-item aos-animate">
                <span class="number" data-count="50">+50</span>
                <span class="label"><?php echo $lang === 'en' ? 'Investigative Question' : 'سؤال إستقصائي'; ?></span>
            </div>
           <!-- <div class="col-6 col-md-3 stat-item aos-animate">
                <span class="number" data-count="10">+10</span>
                <span class="label"><?php echo $lang === 'en' ? 'Admin Questions' : 'سؤال إداري'; ?></span>
            </div>-->
            <div class="col-12 col-md-4 stat-item aos-animate">
                <span class="number" data-count="10">0</span>
                <span class="label"><?php echo $lang === 'en' ? 'Analysis Metrics' : 'مقياس تحليلي'; ?></span>
            </div>
            <div class="col-12 col-md-4 stat-item aos-animate">
                <span class="number" data-count="999">0</span>
                <span class="label"><?php echo $lang === 'en' ? 'Max Participants' : 'الحد الأقصى للمشاركين'; ?></span>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA Section ===== -->
<section class="cta-section">
    <div class="container">
        <div class="aos-animate">
            <h2>
                <?php echo $lang === 'en' 
                    ? 'Discover how your company culture impacts your teams performance and make data-driven decisions to improve it.' 
                    : ' اكتشف كيف تؤثر ثقافة شركتك في أداء فريقك واتخذ قرارات مبنية على بيانات مؤكده لتحسينها'; ?>
            </h2>
            <p>
                <?php echo $lang === 'en' 
                    ? 'Join us today and start measuring, analyzing, and improving your organizational culture with data-driven insights.'
                    : 'انضم إلينا اليوم وابدأ في قياس وتحليل وتحسين ثقافة مؤسستك برؤى قائمة على البيانات.'; ?>
            </p>
            <a href="login.php" class="btn-cta">
                <i class="fas fa-arrow-<?php echo $isRtl ? 'left' : 'right'; ?>"></i>
                <?php echo $lang === 'en' ? 'Login to Dashboard' : 'تسجيل الدخول للوحة التحكم'; ?>
            </a>
        </div>
    </div>
</section>

<!-- ===== Footer ===== -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="footer-brand">
                    <img src="/images/logo.png" style="height: 50px;width: 100px;" alt="OrgFitSA">
                  
                    <?php echo $lang === 'en' ? 'OrgFit' : 'أورج فت'; ?>
                </div>
                <p>
                    <?php echo $lang === 'en' 
                        ? 'A comprehensive platform for assessing and improving corporate culture through data-driven surveys and analytics.'
                        : 'منصة شاملة لتقييم وتحسين ثقافة الشركات من خلال الاستبيانات والتحليلات القائمة على البيانات.'; ?>
                </p>
            </div>
            <div class="col-md-4">
                <h6 style="color: white; font-weight: 700; margin-bottom: 15px;">
                    <?php echo $lang === 'en' ? 'Quick Links' : 'روابط سريعة'; ?>
                </h6>
                <div class="footer-links">
                    <a href="login.php"><i class="fas fa-chevron-<?php echo $isRtl ? 'left' : 'right'; ?>"></i> <?php echo $lang === 'en' ? 'Login' : 'تسجيل الدخول'; ?></a>
                    <a href="#features"><i class="fas fa-chevron-<?php echo $isRtl ? 'left' : 'right'; ?>"></i> <?php echo $lang === 'en' ? 'Features' : 'المميزات'; ?></a>
                    <a href="#about"><i class="fas fa-chevron-<?php echo $isRtl ? 'left' : 'right'; ?>"></i> <?php echo $lang === 'en' ? 'About' : 'عن الموقع'; ?></a>
                </div>
            </div>
            <div class="col-md-4">
                <h6 style="color: white; font-weight: 700; margin-bottom: 15px;">
                    <?php echo $lang === 'en' ? 'Contact' : 'اتصل بنا'; ?>
                </h6>
                <p style="font-size: 14px;">
                    <i class="fas fa-envelope"></i> contact@orgfitsa.com<br>
                    <i class="fas fa-phone"></i> +966 568617949<br>
                    <i class="fas fa-map-marker-alt"></i> Riyadh, Saudi Arabia
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            <p style="margin: 0;">
                &copy; <?php echo date('Y'); ?> <?php echo $lang === 'en' ? 'OrgFit Platform. All rights reserved.' : 'منصة أورج فت. جميع الحقوق محفوظة.'; ?>
            </p>
        </div>
    </div>
</footer>

<!-- ===== Scripts ===== -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    'use strict';

    // ===== AOS Animation =====
    function handleAOS() {
        var elements = document.querySelectorAll('.aos-animate');
        var windowHeight = window.innerHeight;
        var windowWidth = window.innerWidth;
        
        elements.forEach(function(element) {
            var rect = element.getBoundingClientRect();
            var threshold = windowWidth < 768 ? 100 : 150;
            
            if (rect.top < windowHeight - threshold) {
                element.classList.add('aos-visible');
            }
        });
    }

    // تشغيل AOS على التحميل والتمرير
    setTimeout(handleAOS, 100);
    window.addEventListener('scroll', handleAOS);
    window.addEventListener('resize', handleAOS);

    // ===== Counter Animation =====
    function animateCounters() {
        var counters = document.querySelectorAll('.stat-item .number');
        var windowHeight = window.innerHeight;
        
        counters.forEach(function(counter) {
            var rect = counter.getBoundingClientRect();
            if (rect.top < windowHeight - 100 && rect.bottom > 0) {
                var target = parseInt(counter.getAttribute('data-count'));
                var current = parseInt(counter.textContent);
                if (current === 0) {
                    animateCounter(counter, target);
                }
            }
        });
    }

    function animateCounter(element, target) {
        var duration = 2000;
        var start = 0;
        var startTime = null;
        
        function updateCounter(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            var current = Math.round(eased * target);
            element.textContent = current;
            
            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent = target;
            }
        }
        
        requestAnimationFrame(updateCounter);
    }

    // تشغيل عدادات الأرقام
    setTimeout(animateCounters, 200);
    window.addEventListener('scroll', animateCounters);
    window.addEventListener('resize', animateCounters);

    // ===== Mobile Bottom Navigation =====
    var mobileNavItems = document.querySelectorAll('.mobile-bottom-nav .nav-item');
    
    mobileNavItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
            // إزالة الفعالية من جميع العناصر
            mobileNavItems.forEach(function(navItem) {
                navItem.classList.remove('active');
            });
            
            // إضافة الفعالية للعنصر المضغوط
            this.classList.add('active');
            
            // التمرير إلى القسم المحدد
            var target = this.getAttribute('data-section');
            if (target && target !== 'login') {
                e.preventDefault();
                var targetElement = document.getElementById(target);
                if (targetElement) {
                    var offset = 80;
                    var targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    // ===== تحديث القائمة السفلية عند التمرير =====
    function updateActiveNavItem() {
        var sections = ['home', 'features', 'about'];
        var scrollPosition = window.pageYOffset + 150;
        
        sections.forEach(function(sectionId) {
            var section = document.getElementById(sectionId);
            if (section) {
                var rect = section.getBoundingClientRect();
                var sectionTop = rect.top + window.pageYOffset;
                var sectionBottom = sectionTop + rect.height;
                
                if (scrollPosition >= sectionTop && scrollPosition < sectionBottom) {
                    mobileNavItems.forEach(function(item) {
                        item.classList.remove('active');
                        if (item.getAttribute('data-section') === sectionId) {
                            item.classList.add('active');
                        }
                    });
                }
            }
        });
    }

    window.addEventListener('scroll', updateActiveNavItem);

    // ===== Smooth Scroll =====
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this.hash);
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 80
            }, 800, 'easeInOutCubic');
        }
    });

    // ===== Navbar Scroll Effect =====
    var navbar = document.querySelector('.navbar-custom');
    var lastScroll = 0;
    
    window.addEventListener('scroll', function() {
        var currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        
        if (currentScroll > 50) {
            navbar.style.boxShadow = '0 4px 30px rgba(0, 0, 0, 0.1)';
        } else {
            navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.06)';
        }
        
        lastScroll = currentScroll;
    });

    // ===== Preloader =====
    $(window).on('load', function() {
        $('body').addClass('loaded');
    });

    // ===== منع تمرير القائمة السفلية عند النقر =====
    $('.mobile-bottom-nav .nav-item').on('click', function() {
        var target = $(this).attr('data-section');
        if (target === 'login') {
            return true;
        }
        return false;
    });

    // ===== إخفاء القائمة السفلية عند التمرير لأعلى =====
    var lastScrollTop = 0;
    var bottomNav = document.querySelector('.mobile-bottom-nav');
    
    window.addEventListener('scroll', function() {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var windowWidth = window.innerWidth;
        
        if (windowWidth <= 768) {
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // التمرير للأسفل - إخفاء القائمة
                bottomNav.style.transform = 'translateY(100%)';
                bottomNav.style.opacity = '0';
            } else {
                // التمرير للأعلى - إظهار القائمة
                bottomNav.style.transform = 'translateY(0)';
                bottomNav.style.opacity = '1';
            }
        }
        
        lastScrollTop = scrollTop;
    });
});

// ===== Switch Language =====
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

// ===== Easing function for smooth scroll =====
jQuery.easing.easeInOutCubic = function(x, t, b, c, d) {
    if ((t/=d/2) < 1) return c/2*t*t*t + b;
    return c/2*((t-=2)*t*t + 2) + b;
};
</script>

</body>
</html>