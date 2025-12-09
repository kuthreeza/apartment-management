<?php
//session_start();
session_destroy(); // ล้างข้อมูล Session ทั้งหมด
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'ออกจากระบบแล้ว']);
?>