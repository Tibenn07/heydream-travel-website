<?php
require_once __DIR__ . '/../../config/database.php';

unset($_SESSION['partner_id'], $_SESSION['partner_company'], $_SESSION['partner_contact']);
header('Location: partner-login.php');
exit;
