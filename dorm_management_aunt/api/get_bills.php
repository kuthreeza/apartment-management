<?php
// api/get_bills.php
//session_start();
header('Content-Type: application/json');
require_once '../db.php';

if (!isset($_GET['room_id'])) exit;

try {
    // ดึงบิลทั้งหมดของห้องนี้ (เชื่อมจาก rooms -> contracts -> invoices)
    $sql = "SELECT i.*, c.tenant_id, i.line_sent_at, t.line_user_id
            FROM invoices i
            JOIN contracts c ON i.contract_id = c.id
			JOIN tenants t ON c.tenant_id = t.id
            WHERE c.room_id = :rid
            ORDER BY i.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['rid' => $_GET['room_id']]);
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // จัดรูปแบบข้อมูลให้สวยงามก่อนส่งกลับ
    foreach ($bills as &$bill) {
        $bill['formatted_date'] = date('d/m/Y', strtotime($bill['created_at']));
        $bill['is_paid'] = ($bill['status'] === 'paid');
    }

    echo json_encode(['status' => 'success', 'data' => $bills]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>