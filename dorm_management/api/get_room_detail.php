<?php
// api/get_room_detail.php
//session_start();
header('Content-Type: application/json');
require_once '../db.php';

if (!isset($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่ระบุ ID ห้อง']);
    exit;
}

$room_id = $_GET['id'];

try {
    // 1. ดึงข้อมูลสัญญาปัจจุบันของห้องนั้น
	$sql = "SELECT 
                c.*, 
                t.fullname, t.phone, t.id_card, r.room_number,
                (SELECT current_water FROM meter_readings WHERE contract_id = c.id ORDER BY id DESC LIMIT 1) as last_water,
                (SELECT current_electric FROM meter_readings WHERE contract_id = c.id ORDER BY id DESC LIMIT 1) as last_electric
            FROM contracts c
            JOIN tenants t ON c.tenant_id = t.id
            JOIN rooms r ON c.room_id = r.id
            WHERE c.room_id = :room_id AND c.is_active = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['room_id' => $room_id]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($contract) {
        // ถ้าไม่เคยจดมิเตอร์เลย (เพิ่งเข้าอยู่) ให้ใช้เลข 0 หรือเลขตั้งต้นที่ต้องการ
        $contract['last_water'] = $contract['last_water'] ?? 0;
        $contract['last_electric'] = $contract['last_electric'] ?? 0;
        
        echo json_encode(['status' => 'success', 'data' => $contract]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ห้องนี้ว่าง สามารถลงทะเบียนผู้เช่าได้']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>