<?php
/**
 * صفحة تسجيل الخروج
 * logout.php
 */

require_once 'config.php';

// بدء الجلسة
startSession();

// تدمير الجلسة
session_destroy();

// إعادة التوجيه إلى صفحة تسجيل الدخول
header('Location: login.php');
exit;
?>