<?php
// api/get_due_rooms.php
header('Content-Type: application/json');
require_once '../db.php';

try {
    $currentMonth = date('Y-m'); // เดือนปัจจุบัน (เช่น 2025-12)
    
    // Logic: ดึงห้องที่มีคนอยู่ + วันที่ใกล้ถึง + (ต้องไม่มีบิลในเดือนนี้)
    $sql = "SELECT r.room_number, c.start_date, t.fullname
            FROM contracts c
            JOIN rooms r ON c.room_id = r.id
            JOIN tenants t ON c.tenant_id = t.id
            WHERE c.is_active = 1 
            AND (
                -- เงื่อนไขวันที่ (ล่วงหน้า 3 วัน / ย้อนหลัง 5 วัน)
                DAY(c.start_date) BETWEEN (DAY(CURDATE()) - 5) AND (DAY(CURDATE()) + 3)
            )
            -- 🔥 [เพิ่ม] เงื่อนไข: ต้องยังไม่จดบิลในเดือนนี้
            AND NOT EXISTS (
                SELECT 1 FROM invoices i 
                WHERE i.contract_id = c.id 
                AND DATE_FORMAT(i.created_at, '%Y-%m') = :cur_month
            )
            ORDER BY r.room_number ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cur_month' => $currentMonth]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $rooms]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>