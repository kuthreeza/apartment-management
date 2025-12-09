<?php
// api/test_line.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'line_notify.php';

echo "<h1>กำลังทดสอบส่งไลน์...</h1>";
$result = sendLineNotify("ทดสอบจากระบบหอพัก (Test Script)");
echo "<h2>ผลลัพธ์จาก LINE Server:</h2>";
echo "<pre>";
print_r($result);
echo "</pre>";
?>