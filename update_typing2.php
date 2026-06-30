<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'heydream_travel');
$db->query("ALTER TABLE customer_conversations ADD COLUMN IF NOT EXISTS customer_last_typing DATETIME NULL");
$db->query("ALTER TABLE customer_conversations ADD COLUMN IF NOT EXISTS admin_last_typing DATETIME NULL");
echo 'Done';
