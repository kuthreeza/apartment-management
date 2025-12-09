<?php
// api/dashboard.php
//session_start();
header('Content-Type: application/json');
require_once '../db.php';

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // กำหนดเดือนปัจจุบัน (เช่น 2025-12) เพื่อเช็คว่าเดือนนี้จดหรือยัง
    $currentMonth = date('Y-m'); 

    $sql = "SELECT 
                r.id, r.room_number, r.floor, r.status, 
                b.name AS building_name, b.code AS building_code,
                t.fullname AS tenant_name,
                c.start_date, -- วันที่เริ่มสัญญา (ใช้นับรอบบิล)
                
                -- เช็คสถานะบิลล่าสุด ของเดือนนี้
                (SELECT status FROM invoices i 
                 WHERE i.contract_id = c.id 
                 AND DATE_FORMAT(i.created_at, '%Y-%m') = :cur_month
                 ORDER BY i.id DESC LIMIT 1) as bill_status

            FROM rooms r
            LEFT JOIN buildings b ON r.building_id = b.id
            LEFT JOIN contracts c ON r.id = c.room_id AND c.is_active = 1
            LEFT JOIN tenants t ON c.tenant_id = t.id
			ORDER BY b.id, r.floor, LENGTH(r.room_number), r.room_number";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cur_month' => $currentMonth]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // จัดกลุ่มตามตึก
    $data_by_building = [];
    foreach ($rooms as $room) {
        $b_name = $room['building_name'];
        if (!isset($data_by_building[$b_name])) {
            $data_by_building[$b_name] = [];
        }
        $data_by_building[$b_name][] = $room;
    }

    echo json_encode(['status' => 'success', 'data' => $data_by_building]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>