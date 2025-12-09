<?php
// api/save_reading.php (ระบบครอบครัว: น้ำ25+3/ไฟ5/มีค่าบำบัด)
header('Content-Type: application/json');
require_once '../db.php';
require_once 'line_notify.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['contract_id']) || !isset($input['current_water']) || !isset($input['current_electric'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. ดึงข้อมูล
    $sql_info = "SELECT c.*, r.room_number, t.fullname 
                 FROM contracts c
                 JOIN rooms r ON c.room_id = r.id
                 JOIN tenants t ON c.tenant_id = t.id
                 WHERE c.id = :id";
    $stmt = $pdo->prepare($sql_info);
    $stmt->execute(['id' => $input['contract_id']]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. ดึงเลขมิเตอร์เก่า
    $stmt = $pdo->prepare("SELECT current_water, current_electric FROM meter_readings WHERE contract_id = :id ORDER BY id DESC LIMIT 1");
    $stmt->execute(['id' => $input['contract_id']]);
    $last_reading = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last_reading) {
        $prev_water = $last_reading['current_water'];
        $prev_electric = $last_reading['current_electric'];
    } else {
        $stmt_init = $pdo->prepare("SELECT current_water, current_electric FROM meter_readings WHERE contract_id = :id ORDER BY id ASC LIMIT 1");
        $stmt_init->execute(['id' => $input['contract_id']]);
        $init_reading = $stmt_init->fetch(PDO::FETCH_ASSOC);
        
        $prev_water = $init_reading ? $init_reading['current_water'] : 0;
        $prev_electric = $init_reading ? $init_reading['current_electric'] : 0;
    }

    // 3. คำนวณหน่วย
    $unit_water = $input['current_water'] - $prev_water;
    $unit_electric = $input['current_electric'] - $prev_electric;

    if ($unit_water < 0 || $unit_electric < 0) throw new Exception("เลขมิเตอร์ใหม่ต้องมากกว่าเลขมิเตอร์เก่า!");

    // 4. คำนวณราคา (ระบบครอบครัว)
    // ค่าน้ำ
    $calc_water_raw = $unit_water * 25;
    $calc_pump_raw  = $unit_water * 3;
    $total_water_check = $calc_water_raw + $calc_pump_raw;

    if ($total_water_check < 50) {
        $price_water = 50; 
        $price_common_water = 0; 
    } else {
        $price_water = $calc_water_raw;
        $price_common_water = $calc_pump_raw;
    }

    // ค่าไฟ
    $raw_electric = $unit_electric * 5; 
    if ($raw_electric < 50) {
        $price_electric = 50;
    } else {
        $price_electric = $raw_electric;
    }

    // ค่าอื่นๆ
    $price_central = isset($contract['fixed_electric_fee']) ? $contract['fixed_electric_fee'] : 0;
    $price_trash = 0; 
    $price_wastewater = $contract['wastewater_fee']; 
    if ($price_wastewater == 0 && $contract['trash_fee'] > 0) $price_wastewater = $contract['trash_fee'];
    $price_rent = $contract['rent_price'];

    $total = $price_rent + $price_water + $price_electric + $price_common_water + $price_central + $price_trash + $price_wastewater;

    // 🔥 รับค่าวันที่
    $reading_date = $input['reading_date'] ?? date('Y-m-d');
    $due_date     = $input['due_date'] ?? $reading_date;

    // 6. บันทึก Meter
    $sql_meter = "INSERT INTO meter_readings (contract_id, reading_date, previous_water, current_water, previous_electric, current_electric, recorder_id) 
                  VALUES (:cid, :rdate, :pw, :cw, :pe, :ce, :rid)";
    $stmt = $pdo->prepare($sql_meter);
    $stmt->execute([
        'cid' => $contract['id'],
        'rdate' => $reading_date,
        'pw' => $prev_water, 
        'cw' => $input['current_water'],
        'pe' => $prev_electric, 
        'ce' => $input['current_electric'],
        'rid' => $_SESSION['user_id'] ?? 0
    ]);
    $meter_id = $pdo->lastInsertId();

    // 7. บันทึก Invoice
    $inv_number = 'INV-' . date('Ymd-His') . '-' . str_pad($contract['id'], 4, '0', STR_PAD_LEFT);
    $sql_inv = "INSERT INTO invoices 
                (invoice_number, contract_id, meter_reading_id, period_end, due_date, created_at,
                 amount_rent, amount_water, amount_electric, amount_common_water, amount_common_electric, amount_trash, amount_wastewater, total_amount, status)
                VALUES 
                (:inv, :cid, :mid, :pend, :due, :created, 
                 :rent, :water, :elec, :cw, :ce, :trash, :waste, :total, 'pending')";
    
    $stmt = $pdo->prepare($sql_inv);
    $stmt->execute([
        'inv' => $inv_number, 'cid' => $contract['id'], 'mid' => $meter_id,
        'pend' => $reading_date, 
        'due' => $due_date,
        'created' => $reading_date . ' ' . date('H:i:s'),
        'rent' => $price_rent, 'water' => $price_water, 'elec' => $price_electric,
        'cw' => $price_common_water, 'ce' => $price_central, 
        'trash' => 0, 'waste' => $price_wastewater,
        'total' => $total
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    $pdo->commit();
    
    // ---------------------------------------------------------
    // 💬 ข้อความ LINE (Admin)
    // ---------------------------------------------------------
    
    // ✅ เพิ่มฟังก์ชันแปลงวันที่ตรงนี้
    function thaiDateShort($d) {
        if(!$d) return "-";
        $ex = explode('-', $d);
        return intval($ex[2]) . "/" . intval($ex[1]) . "/" . ($ex[0]+543);
    }

    $lineMsg  = "🧾 **ใบแจ้งหนี้ประจำเดือน** (รอตรวจสอบ)";
    // ✅ เพิ่ม 2 บรรทัดนี้ เพื่อแสดงวันที่
    $lineMsg .= "\n🗓️ วันที่จด: " . thaiDateShort($reading_date);
    $lineMsg .= "\n📅 ครบกำหนด: " . thaiDateShort($due_date);
    
    $lineMsg .= "\n🏠 ห้อง: " . $contract['room_number'] . " (" . $contract['fullname'] . ")";
    $lineMsg .= "\n-----------------------------";
    $lineMsg .= "\n1️⃣ ค่าห้อง: " . number_format($price_rent) . " บ.";
    
    // ค่าน้ำ
    $total_water_show = $price_water + $price_common_water;
    if ($total_water_show == 50 && ($unit_water * 28) < 50) {
        $lineMsg .= "\n2️⃣ ค่าน้ำ: 50 บ. (ขั้นต่ำ)";
    } else {
        $lineMsg .= "\n2️⃣ ค่าน้ำ (" . $unit_water . "หน่วย x (25บ.+3บ.): " . number_format($total_water_show) . " บ.";
    }
    $lineMsg .= "\n    (" . $prev_water . " ➜ " . $input['current_water'] . ")";

    // ค่าไฟ
    if ($price_electric == 50 && ($unit_electric * 5) < 50) {
        $lineMsg .= "\n3️⃣ ค่าไฟ: 50 บ. (ขั้นต่ำ)";
    } else {
        $lineMsg .= "\n3️⃣ ค่าไฟ (" . $unit_electric . "หน่วย x 5บ.): " . number_format($price_electric) . " บ.";
    }
    $lineMsg .= "\n    (" . $prev_electric . " ➜ " . $input['current_electric'] . ")";

    // ส่วนกลาง
    $show_central = ($price_central > 0) ? number_format($price_central) : "-";
    $lineMsg .= "\n4️⃣ ค่าส่วนกลาง (   +   +   ): " . $show_central . " บ.";
    
    // ค่าบำบัดน้ำเสีย
    if ($price_wastewater > 0) {
        $lineMsg .= "\n5️⃣ ค่าบำบัดน้ำเสีย: " . number_format($price_wastewater) . " บ.";
    }

    if ($price_trash > 0) $lineMsg .= "\n5️⃣ ค่าขยะ: " . number_format($price_trash) . " บ.";

    $lineMsg .= "\n-----------------------------";
    $lineMsg .= "\n💰 **ยอดรวมสุทธิ: " . number_format($total, 2) . " บาท**";
    $lineMsg .= "\n-----------------------------";
    
    $adminMsg = "📝 [บันทึกแล้ว/รอตรวจสอบ]\n" . $lineMsg;
    sendLineNotify($adminMsg, $myQrCodeUrl);
    
    ob_clean();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกเรียบร้อย', 'total' => $total, 'invoice_id' => $invoice_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>