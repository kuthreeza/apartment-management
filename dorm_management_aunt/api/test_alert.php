<?php
// api/test_alert.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'line_notify.php';

echo "<h2>🚀 กำลังทดสอบส่งไลน์...</h2>";

// ลองส่งแบบไม่ระบุคนรับ (ต้องเด้งหา Admin)
$result = sendLineNotify("ทดสอบระบบ: Admin ต้องได้รับข้อความนี้!");

echo "ผลลัพธ์จาก LINE: <pre>" . htmlspecialchars($result) . "</pre>";
?>