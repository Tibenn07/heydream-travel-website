<?php
session_start();
$_SESSION["admin_logged_in"] = true;
$_SESSION["admin_username"] = "superadmin";
$_SESSION["admin_role"] = "super_admin";
$_POST["action"] = "approve_partner_package_submission";
$_POST["package_id"] = 1;
$_SERVER["REQUEST_METHOD"] = "POST";
include "admin/admin-api.php";
