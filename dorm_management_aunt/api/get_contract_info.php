<?php
// api/get_contract_info.php
header('Content-Type: application/json');
require_once '../db.php';

if (!isset($_GET['contract_id'])) exit;

try {
    // 1. ดึงข้อมูลสัญญา + ผู้เช่า
    $sql = "SELECT c.*, t.fullname, t.phone, t.id_card, t.line_user_id
            FROM contracts c
            JOIN tenants t ON c.tenant_id = t.id
            WHERE c.id = :cid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cid' => $_GET['contract_id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. ดึงเลขมิเตอร์ "ครั้งแรกสุด" (Initial Reading) ของสัญญานี้
    $sql_meter = "SELECT * FROM meter_readings 
                  WHERE contract_id = :cid 
                  ORDER BY id DESC LIMIT 1"; // <-- แก้เป็น DESC
    $stmt_m = $pdo->prepare($sql_meter);
    $stmt_m->execute(['cid' => $_GET['contract_id']]);
    $meter = $stmt_m->fetch(PDO::FETCH_ASSOC);

    // เอาเลขมิเตอร์ยัดใส่ข้อมูลที่จะส่งกลับ
    $data['initial_water'] = $meter ? $meter['current_water'] : 0;
    $data['initial_electric'] = $meter ? $meter['current_electric'] : 0;
    $data['meter_id'] = $meter ? $meter['id'] : 0; // ส่ง ID ไปด้วย เพื่อเอาไปสั่ง Update ถูกบรรทัด

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>