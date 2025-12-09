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

    // 2. ดึงเลขมิเตอร์ "ล่าสุด" (เพื่อแก้ไขให้ตรงกับหน้าจดมิเตอร์)
    $sql_meter = "SELECT * FROM meter_readings 
                  WHERE contract_id = :cid 
                  ORDER BY id DESC LIMIT 1"; // 🔥 ใช้ DESC ตามที่แก้แล้ว
    $stmt_m = $pdo->prepare($sql_meter);
    $stmt_m->execute(['cid' => $_GET['contract_id']]);
    $meter = $stmt_m->fetch(PDO::FETCH_ASSOC);

    $data['initial_water'] = $meter ? $meter['current_water'] : 0;
    $data['initial_electric'] = $meter ? $meter['current_electric'] : 0;
    $data['meter_id'] = $meter ? $meter['id'] : 0;

    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>