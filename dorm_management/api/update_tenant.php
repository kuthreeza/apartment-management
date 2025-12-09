<?php
// api/update_tenant.php
header('Content-Type: application/json');
require_once '../db.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['contract_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. อัปเดตข้อมูลผู้เช่า
    $sql_t = "UPDATE tenants SET fullname = :name, phone = :phone, id_card = :card 
              WHERE id = (SELECT tenant_id FROM contracts WHERE id = :cid)";
    $pdo->prepare($sql_t)->execute([
        'name' => $input['fullname'],
        'phone' => $input['phone'],
        'card' => $input['id_card'],
        'cid' => $input['contract_id']
    ]);

    // 2. อัปเดตสัญญา
    $sql_c = "UPDATE contracts SET 
                start_date = :start,
                rent_price = :rent,
                deposit = :deposit
              WHERE id = :cid";
    $pdo->prepare($sql_c)->execute([
        'start' => $input['start_date'],
        'rent' => $input['rent_price'],
        'deposit' => $input['deposit'],
        'cid' => $input['contract_id']
    ]);

    // 3. อัปเดตมิเตอร์ (ถ้ามีการส่ง ID มา)
    if (isset($input['meter_id']) && $input['meter_id'] > 0) {
        $sql_m = "UPDATE meter_readings SET 
                    current_water = :cw, previous_water = :cw,
                    current_electric = :ce, previous_electric = :ce
                  WHERE id = :mid";
        $pdo->prepare($sql_m)->execute([
            'cw' => $input['initial_water'],
            'ce' => $input['initial_electric'],
            'mid' => $input['meter_id']
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => '✅ บันทึกการแก้ไขเรียบร้อย']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>