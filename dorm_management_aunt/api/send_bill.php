<?php
// api/send_bill.php (แก้ไขแล้ว: ระบบน้า)
header('Content-Type: application/json');
require_once '../db.php';
require_once 'line_notify.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['invoice_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบ Invoice ID']);
    exit;
}

try {
    // 1. ดึงข้อมูล
    $sql = "SELECT i.*, i.due_date, i.created_at, m.reading_date, r.room_number, t.fullname, t.line_user_id,
                    m.previous_water, m.current_water, m.previous_electric, m.current_electric
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

    // 2. ฟังก์ชันแปลงวันที่ (ต้องใส่ไว้ในไฟล์นี้ด้วย หรือ include มา)
    function thaiDateShort($d) {
        if(!$d) return "-";
        $ex = explode('-', $d);
        // เช็คก่อนว่ามีข้อมูลครบไหม ป้องกัน Error
        if(count($ex) < 3) return $d; 
        return intval($ex[2]) . "/" . intval($ex[1]) . "/" . ($ex[0]+543);
    }

    // 3. สร้างข้อความ (แก้ชื่อตัวแปรให้เป็น $msg ทั้งหมด)
    $msg  = "🧾 **ใบแจ้งหนี้ประจำเดือน** (ยืนยันแล้ว)";
    
    // แก้ไข: ใช้ $msg ต่อเนื่องกัน (ของเดิมใช้ $lineMsg ผสม $msg)
    $msg .= "\n🗓️ วันที่จด: " . thaiDateShort($inv['reading_date']);
    $msg .= "\n📅 ครบกำหนด: " . thaiDateShort($inv['due_date']);
    $msg .= "\n🏠 ห้อง: " . $inv['room_number'] . " (" . $inv['fullname'] . ")";
    $msg .= "\n-----------------------------";
    $msg .= "\n1️⃣ ค่าห้อง: " . number_format($inv['amount_rent']) . " บ.";
    
    $units_w = $inv['current_water'] - $inv['previous_water'];
    $units_e = $inv['current_electric'] - $inv['previous_electric'];

    // --- ค่าน้ำ (30บ. ขั้นต่ำ 100) ---
    $calc_water_normal = $units_w * 30;
    
    if ($inv['amount_water'] == 100 && $calc_water_normal < 100) {
        $msg .= "\n2️⃣ ค่าน้ำ: 100 บ. (ขั้นต่ำ)";
    } else {
        $msg .= "\n2️⃣ ค่าน้ำ (" . $units_w . "หน่วย x 30บ.): " . number_format($inv['amount_water']) . " บ.";
    }
    $msg .= "\n    (" . $inv['previous_water'] . " ➜ " . $inv['current_water'] . ")";
    
    // --- ค่าไฟ (10บ. ขั้นต่ำ 100) ---
    $calc_elec_normal = $units_e * 10;
    
    if ($inv['amount_electric'] == 100 && $calc_elec_normal < 100) {
        $msg .= "\n3️⃣ ค่าไฟ: 100 บ. (ขั้นต่ำ)";
    } else {
        $msg .= "\n3️⃣ ค่าไฟ (" . $units_e . "หน่วย x 10บ.): " . number_format($inv['amount_electric']) . " บ.";
    }
    $msg .= "\n    (" . $inv['previous_electric'] . " ➜ " . $inv['current_electric'] . ")";
    
    // --- ส่วนกลาง ---
    $central_val = $inv['amount_common_electric'];
    $show_central = ($central_val > 0) ? number_format($central_val) : "-";
    $msg .= "\n4️⃣ ค่าส่วนกลาง (   +   +   ): " . $show_central . " บ.";
    
    // --- ค่าขยะ ---
    if ($inv['amount_trash'] > 0) {
        $msg .= "\n5️⃣ ค่าขยะ: " . number_format($inv['amount_trash']) . " บ.";
    }

    $msg .= "\n-----------------------------";
    $msg .= "\n💰 **ยอดรวมสุทธิ: " . number_format($inv['total_amount'], 2) . " บาท**";
    $msg .= "\n-----------------------------";
    $msg .= "\nขอบคุณครับ/ค่ะ 🙏";

    // QR Code (เปลี่ยน Link ได้ตามต้องการ)
    $qrUrl = "https://sv1.img.in.th/7fa7yt.jpeg"; 

    $sent = false;
    // ส่งไลน์หาผู้เช่า
    if (!empty($inv['line_user_id'])) {
        $res = sendLineNotify($msg, $qrUrl, $inv['line_user_id']);
        $sent = true;
    }
    
    // ส่งไลน์แจ้ง Admin (แจ้งตัวเองว่าส่งแล้ว)
    // ใช้ Token ของ Admin (ถ้าไม่ได้ระบุ user_id จะไปเข้า Token Default ในไฟล์ line_notify.php)
    sendLineNotify("✅ ส่งบิลห้อง " . $inv['room_number'] . " ให้ผู้เช่าเรียบร้อยแล้วครับ");

    // อัปเดตสถานะว่าส่งแล้ว
    $pdo->prepare("UPDATE invoices SET line_sent_at = NOW() WHERE id = ?")->execute([$input['invoice_id']]);

    ob_clean();
    if($sent) {
        echo json_encode(['status' => 'success', 'message' => 'ส่ง LINE เรียบร้อย!']);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'ผู้เช่าไม่มี LINE (แต่บันทึกสถานะแล้ว)']);
    }

} catch (Exception $e) {
    ob_clean(); // ล้าง Buffer ก่อนส่ง error
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>