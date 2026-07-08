<?php
$page = $_GET['page'] ?? 'dashboard';
header('Location: admin_dashboard.php?page=' . urlencode($page));
exit;