<?php
// api/send_bill.php (ระบบครอบครัว: วันที่ถูกต้อง + สูตรถูกต้อง)
header('Content-Type: application/json');
require_once '../db.php';
require_once 'line_notify.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['invoice_id'])) exit;

try {
	// 1. ดึงข้อมูล (เพิ่ม m.reading_date เข้าไป)
    $sql = "SELECT i.*, i.period_end, i.due_date, r.room_number, t.fullname, t.line_user_id,
                    m.reading_date, m.previous_water, m.current_water, m.previous_electric, m.current_electric
            FROM invoices i
            JOIN contracts c ON i.contract_id = c.id
            JOIN rooms r ON c.room_id = r.id
            JOIN tenants t ON c.tenant_id = t.id
            JOIN meter_readings m ON i.meter_reading_id = m.id
            WHERE i.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $input['invoice_id']]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) throw new Exception("ไม่พบข้อมูลบิล");

    // ฟังก์ชันแปลงวันที่ (Helper Function)
    function thaiDateShort($d) { 
        if(!$d || $d == '0000-00-00') return "-";
        $ex = explode('-', explode(' ', $d)[0]); // ตัดเวลาออกก่อน split วันที่
        return intval($ex[2])."/".intval($ex[1])."/".($ex[0]+543); 
    }

    // สร้างข้อความ
    $msg  = "🧾 **ใบแจ้งหนี้ประจำเดือน** (ยืนยันแล้ว)";
    
// ใช้วันที่จดจริงจาก Meter (m.reading_date) ส่วนวันครบกำหนดยังใช้จาก Invoice (i.due_date) เหมือนเดิม
    $msg .= "\n🗓️ วันที่จด: " . thaiDateShort($inv['reading_date']);
    $msg .= "\n📅 ครบกำหนด: " . thaiDateShort($inv['due_date']);
    
    $msg .= "\n🏠 ห้อง: " . $inv['room_number'] . " (" . $inv['fullname'] . ")";
    $msg .= "\n-----------------------------";
    $msg .= "\n1️⃣ ค่าห้อง: " . number_format($inv['amount_rent']) . " บ.";
    
    $units_w = $inv['current_water'] - $inv['previous_water'];
    $units_e = $inv['current_electric'] - $inv['previous_electric'];

    // --- ค่าน้ำ (25+3) ---
    $total_water_money = $inv['amount_water'] + $inv['amount_common_water'];
    $calc_water_normal = $units_w * (25 + 3);

    if ($total_water_money == 50 && $calc_water_normal < 50) {
        $msg .= "\n2️⃣ ค่าน้ำ: 50 บ. (ขั้นต่ำ)";
    } else {
        $msg .= "\n2️⃣ ค่าน้ำ (" . $units_w . "หน่วย x (25บ.+3บ.)): " . number_format($total_water_money) . " บ.";
    }
    $msg .= "\n    (" . $inv['previous_water'] . " ➜ " . $inv['current_water'] . ")";
    
    // --- ค่าไฟ (5) ---
    $calc_elec_normal = $units_e * 5;
    
    if ($inv['amount_electric'] == 50 && $calc_elec_normal < 50) {
        $msg .= "\n3️⃣ ค่าไฟ: 50 บ. (ขั้นต่ำ)";
    } else {
        $msg .= "\n3️⃣ ค่าไฟ (" . $units_e . "หน่วย x 5บ.): " . number_format($inv['amount_electric']) . " บ.";
    }
    $msg .= "\n    (" . $inv['previous_electric'] . " ➜ " . $inv['current_electric'] . ")";
    
    // --- ส่วนกลาง ---
    $central_val = $inv['amount_common_electric'];
    $show_central = ($central_val > 0) ? number_format($central_val) : "-";
    $msg .= "\n4️⃣ ค่าส่วนกลาง (   +   +   ): " . $show_central . " บ.";
    
    // --- บำบัดน้ำเสีย ---
    if ($inv['amount_wastewater'] > 0) {
        $msg .= "\n5️⃣ ค่าบำบัดน้ำเสีย: " . number_format($inv['amount_wastewater']) . " บ.";
    }
    
    // --- ค่าขยะ ---
    if ($inv['amount_trash'] > 0) {
        $msg .= "\n5️⃣ ค่าขยะ: " . number_format($inv['amount_trash']) . " บ.";
    }

    $msg .= "\n-----------------------------";
    $msg .= "\n💰 **ยอดรวมสุทธิ: " . number_format($inv['total_amount'], 2) . " บาท**";
    $msg .= "\n-----------------------------";
    $msg .= "\nขอบคุณครับ/ค่ะ 🙏";

    // URL QR Code (เปลี่ยนได้)
    $qrUrl = "https://sv1.img.in.th/7faink.jpeg";

    // ส่ง Line หาผู้เช่า
    $sent = false;
    if (!empty($inv['line_user_id'])) {
        sendLineNotify($msg, $qrUrl, $inv['line_user_id']);
        $sent = true;
    }
    
    // ส่ง Line หา Admin
    sendLineNotify("✅ ส่งบิลห้อง " . $inv['room_number'] . " ให้ผู้เช่าเรียบร้อยแล้วครับ");

    // อัปเดตสถานะ
    $pdo->prepare("UPDATE invoices SET line_sent_at = NOW() WHERE id = ?")->execute([$input['invoice_id']]);

    ob_clean();
    if($sent) {
        echo json_encode(['status' => 'success', 'message' => 'ส่ง LINE เรียบร้อย!']);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'ผู้เช่าไม่มี LINE (บันทึกแล้ว)']);
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>