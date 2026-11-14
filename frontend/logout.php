<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';

unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);
unset($_SESSION['customer_email']);
unset($_SESSION['customer_avatar']);
unset($_SESSION['customer_phone']);

header("Location: " . app_url('login.php?logged_out=1'));
exit;
