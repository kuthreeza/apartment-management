<?php
// api/login.php
//session_start(); // เริ่มต้น Session เพื่อจำว่าใคร Login
header('Content-Type: application/json'); // บอก Browser ว่าผลลัพธ์คือ JSON
require_once '../db.php'; // เรียกตัวเชื่อมต่อ Database

// 1. รับข้อมูลที่ส่งมาจากหน้าบ้าน (รับเป็น JSON)
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// ถ้าไม่มีข้อมูลส่งมา หรือส่งมาไม่ครบ
if (!isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
    exit;
}

$username = $input['username'];
$password = $input['password'];

try {
    // 2. ดึงข้อมูล User จาก Database
    // ใช้ Prepare Statement เพื่อป้องกัน SQL Injection (สำคัญมาก!)
    $stmt = $pdo->prepare("SELECT * FROM system_users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. ตรวจสอบรหัสผ่าน
    // password_verify จะเช็คว่า '123456' ตรงกับ Hash ยึกยือใน Database ไหม
    if ($user && password_verify($password, $user['password'])) {
        
        // --- Login สำเร็จ ---
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['fullname'] = $user['fullname'];

        echo json_encode([
            'status' => 'success',
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'user' => [
                'fullname' => $user['fullname'],
                'role' => $user['role']
            ]
        ]);

    } else {
        // --- Login ล้มเหลว ---
        echo json_encode(['status' => 'error', 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'ระบบเกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>