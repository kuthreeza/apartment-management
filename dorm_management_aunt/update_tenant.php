<?php
// api/update_tenant.php
//session_start();
header('Content-Type: application/json');
require_once '../db.php';

$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();

    // 1. อัปเดตข้อมูลส่วนตัว (ตาราง tenants)
    $sql_t = "UPDATE tenants SET fullname = :name, phone = :phone, id_card = :card 
              WHERE id = (SELECT tenant_id FROM contracts WHERE id = :cid)";
    $pdo->prepare($sql_t)->execute([
        'name' => $input['fullname'],
        'phone' => $input['phone'],
        'card' => $input['id_card'],
        'cid' => $input['contract_id']
    ]);

    // 2. อัปเดตวันเริ่มสัญญา (ตาราง contracts)
    $sql_c = "UPDATE contracts SET start_date = :start WHERE id = :cid";
    $pdo->prepare($sql_c)->execute([
        'start' => $input['start_date'],
        'cid' => $input['contract_id']
    ]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'แก้ไขข้อมูลเรียบร้อย']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>