<?php
// fix_password.php
require_once 'db.php';

// 1. สร้าง Hash ของจริงจากเลข "123456"
$new_password_hash = password_hash("123456", PASSWORD_DEFAULT);

try {
    // 2. อัปเดตลง Database ให้ทั้ง admin และ staff
    $stmt = $pdo->prepare("UPDATE system_users SET password = :pass WHERE username IN ('admin', 'staff')");
    $stmt->execute(['pass' => $new_password_hash]);

    echo "<h1>✅ แก้ไขรหัสผ่านเรียบร้อยแล้ว!</h1>";
    echo "<p>ตอนนี้ Hash ของจริงคือ: <strong>" . $new_password_hash . "</strong></p>";
    echo "<p>คุณสามารถกลับไป Login ด้วยรหัส 123456 ได้เลย</p>";
    echo "<a href='test_login.html'>ไปหน้า Login</a>";

} catch (PDOException $e) {
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}
?>