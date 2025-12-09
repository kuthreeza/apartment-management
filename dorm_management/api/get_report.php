<?php
// api/get_report.php
header('Content-Type: application/json');
require_once '../db.php';

// รับค่าวันที่จากหน้าเว็บ (ถ้าไม่ส่งมา ให้ใช้วันที่ 1 ถึง สิ้นเดือนปัจจุบัน)
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end'] ?? date('Y-m-t');

try {
    // ----------------------------------------------------
    // 1. สรุปยอดรวม (Overview)
    // ----------------------------------------------------
    $sql_summary = "SELECT 
                        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_income,
                        SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as total_pending,
                        COUNT(*) as total_bills,
                        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_bills
                    FROM invoices 
                    WHERE (DATE(created_at) BETWEEN :s1 AND :e1)"; // ดูจากวันที่ออกบิล
    
    $stmt = $pdo->prepare($sql_summary);
    $stmt->execute(['s1' => $start, 'e1' => $end]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // ----------------------------------------------------
    // 2. แยกตามตึก (By Building)
    // ----------------------------------------------------
    $sql_building = "SELECT b.name, 
                            SUM(i.total_amount) as amount
                     FROM invoices i
                     JOIN contracts c ON i.contract_id = c.id
                     JOIN rooms r ON c.room_id = r.id
                     JOIN buildings b ON r.building_id = b.id
                     WHERE i.status = 'paid' 
                     AND (DATE(i.paid_at) BETWEEN :s2 AND :e2) -- ดูจากวันที่จ่ายจริง
                     GROUP BY b.id";
    
    $stmt = $pdo->prepare($sql_building);
    $stmt->execute(['s2' => $start, 'e2' => $end]);
    $by_building = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------------------------------
    // 3. กราฟรายวัน (Daily Chart)
    // ----------------------------------------------------
    $sql_daily = "SELECT DATE(paid_at) as pay_date, SUM(total_amount) as total
                  FROM invoices
                  WHERE status = 'paid' 
                  AND (DATE(paid_at) BETWEEN :s3 AND :e3)
                  GROUP BY DATE(paid_at)
                  ORDER BY pay_date";
    
    $stmt = $pdo->prepare($sql_daily);
    $stmt->execute(['s3' => $start, 'e3' => $end]);
    $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------------------------------
    // 4. รายการละเอียด (Transaction List)
    // ----------------------------------------------------
    $sql_list = "SELECT i.invoice_number, r.room_number, t.fullname, 
                        i.total_amount, i.status, i.paid_at, i.created_at
                 FROM invoices i
                 JOIN contracts c ON i.contract_id = c.id
                 JOIN rooms r ON c.room_id = r.id
                 JOIN tenants t ON c.tenant_id = t.id
                 WHERE (DATE(i.created_at) BETWEEN :s4 AND :e4)
                 ORDER BY i.invoice_number DESC";
                 
    $stmt = $pdo->prepare($sql_list);
    $stmt->execute(['s4' => $start, 'e4' => $end]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'summary' => $summary,
        'by_building' => $by_building,
        'daily' => $daily,
        'list' => $list
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>