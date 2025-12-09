<?php
// api/delete_bill.php
//session_start();
header('Content-Type: application/json');
require_once '../db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['invoice_id'])) exit;

try {
    $pdo->beginTransaction();

    // 1. หา ID ของมิเตอร์ที่ผูกกับบิลนี้
    $stmt = $pdo->prepare("SELECT meter_reading_id, invoice_number FROM invoices WHERE id = ?");
    $stmt->execute([$input['invoice_id']]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if($inv) {
        // 2. ลบบิล (Invoices)
        $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$input['invoice_id']]);
        
        // 3. ลบข้อมูลมิเตอร์ (Meter Readings) เพื่อให้จดใหม่ได้
        if($inv['meter_reading_id']) {
            $pdo->prepare("DELETE FROM meter_readings WHERE id = ?")->execute([$inv['meter_reading_id']]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'ลบบิลเรียบร้อย คุณสามารถจดมิเตอร์ใหม่ได้ทันที']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>