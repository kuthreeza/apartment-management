<?php
// api/pay_bill.php
//session_start();
header('Content-Type: application/json');
require_once '../db.php';
require_once 'line_notify.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['invoice_id']) || !isset($input['payment_method'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']);
    exit;
}

try {
    $sql = "UPDATE invoices 
            SET status = 'paid', 
                payment_method = :method, 
                paid_at = NOW() 
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'method' => $input['payment_method'],
        'id' => $input['invoice_id']
    ]);
	
	// 1. ดึงข้อมูลให้ครบถ้วน (เพิ่ม invoice_number และ fullname)
    $stmt_info = $pdo->prepare("
        SELECT r.room_number, i.total_amount, i.invoice_number, 
               t.line_user_id, t.fullname
        FROM invoices i 
        JOIN contracts c ON i.contract_id = c.id
        JOIN rooms r ON c.room_id = r.id
        JOIN tenants t ON c.tenant_id = t.id
        WHERE i.id = :id
    ");
    $stmt_info->execute(['id' => $input['invoice_id']]);
    $bill_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if($bill_info) {
        // ตัวแปรสำหรับวันที่และคนกด
        $payDate = date("d/m/Y H:i:s"); // วันเวลาปัจจุบัน (เช่น 05/12/2025 14:30:00)
        $recorder = $_SESSION['fullname'] ?? 'Admin'; // ชื่อคนกดรับเงิน (จาก Login)

        // --- ส่วนที่ 1: ส่งหาผู้เช่า (ขอบคุณสั้นๆ) ---
        $msgTenant  = "✅ ได้รับยอดโอนค่าเช่าห้อง " . $bill_info['room_number'];
        $msgTenant .= "\nจำนวน " . number_format($bill_info['total_amount'], 2) . " บาท เรียบร้อยแล้วครับ";
        $msgTenant .= "\nขอบคุณครับ 🙏";

        if (!empty($bill_info['line_user_id'])) {
            sendLineNotify($msgTenant, null, $bill_info['line_user_id']);
        }

        // --- ส่วนที่ 2: ส่งหา Admin (ละเอียดยิบ เพื่อตรวจสอบ) ---
        $msgAdmin  = "💰 **บันทึกรับเงินสำเร็จ**";
        $msgAdmin .= "\n🏠 ห้อง: " . $bill_info['room_number'] . " (" . $bill_info['fullname'] . ")";
        $msgAdmin .= "\n🧾 เลขที่บิล: " . $bill_info['invoice_number'];
        $msgAdmin .= "\n-----------------------------";
        $msgAdmin .= "\n💸 ยอดเงิน: " . number_format($bill_info['total_amount'], 2) . " บาท";
        $msgAdmin .= "\n🏦 ช่องทาง: " . $input['payment_method']; // เงินสด/โอน
        $msgAdmin .= "\n-----------------------------";
        $msgAdmin .= "\n🕒 เวลาที่รับ: " . $payDate;
        $msgAdmin .= "\n🧑‍💼 ผู้บันทึก: " . $recorder;

        sendLineNotify($msgAdmin); // ส่งหา Admin
    }
    
    // =========================================================
	
	ob_clean();
	
    echo json_encode(['status' => 'success', 'message' => 'บันทึกการชำระเงินเรียบร้อย']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>