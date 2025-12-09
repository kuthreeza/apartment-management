<?php
// db.php - ไฟล์สำหรับเชื่อมต่อ Database
$host = 'localhost';
$dbname = 'dorm_management';
$username = 'root'; // ปกติ Laragon ใช้ root
$password = '';     // ปกติ Laragon ไม่มีรหัสผ่าน (ถ้ามีให้ใส่ตรงนี้)

session_name('SESSION_MANAGEMENT'); 
session_start();

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // ตั้งค่าให้แจ้งเตือน Error ทันทีถ้า SQL ผิด
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	// 🔥🔥🔥 [เพิ่ม 2 บรรทัดนี้ครับ] ตั้งเวลาให้เป็นไทย (Thailand Time) 🔥🔥🔥
    // 1. บอก PHP ให้ใช้เวลาไทย (สำหรับ date() และเลขบิล)
    date_default_timezone_set('Asia/Bangkok');
    // 2. บอก MySQL Database ให้ใช้เวลาไทย (สำหรับ NOW() ในฐานข้อมูล)
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    // ถ้าเชื่อมต่อไม่ได้ ให้แจ้ง Error และหยุดทำงาน
    die("Connection failed: " . $e->getMessage());
}
?>