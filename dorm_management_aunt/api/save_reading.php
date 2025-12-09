<?php
// api/save_reading.php (ระบบน้า: น้ำ30/ไฟ10 ขั้นต่ำ100)
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
	
	// รับค่าวันที่ (ถ้าไม่ส่งมา ให้ใช้วันนี้เป็นค่า Default)
    $reading_date = $input['reading_date'] ?? date('Y-m-d');
    $due_date     = $input['due_date'] ?? date('Y-m-d', strtotime('+5 days'));

    // 2. ดึงเลขมิเตอร์เก่า
    $stmt = $pdo->prepare("SELECT current_water, current_electric FROM meter_readings WHERE contract_id = :id ORDER BY id DESC LIMIT 1");
    $stmt->execute(['id' => $input['contract_id']]);
    $last_reading = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($last_reading) {
        $prev_water = $last_reading['current_water'];
        $prev_electric = $last_reading['current_electric'];
    } else {
        // 🔥 ถ้าไม่เจอเลย (เช่น เพิ่งลบบิลทิ้งหมด) -> ให้ไปดึง "ค่าเริ่มต้น" จากการลงทะเบียน
        // (ซึ่งปกติคือ meter_reading แถวแรกสุดของ contract นี้)
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

    // 4. --- คำนวณราคา (สูตรน้า: น้ำ30 / ไฟ10 / ขั้นต่ำ 100) ---
    
    // --------------------------------------------------------
    // 💧 4.1 ค่าน้ำ (30 บาท/หน่วย, ขั้นต่ำ 100)
    // --------------------------------------------------------
    $calc_water_raw = $unit_water * 30; // เรทน้า 30 บาท

    if ($calc_water_raw < 100) {
        // โดนขั้นต่ำ
        $price_water = 100;
    } else {
        // ปกติ
        $price_water = $calc_water_raw;
    }
    // ของน้าไม่มีค่าปั๊มแยก (เป็น 0)
    $price_common_water = 0; 

    // --------------------------------------------------------
    // ⚡ 4.2 ค่าไฟ (10 บาท/หน่วย, ขั้นต่ำ 100)
    // --------------------------------------------------------
    $calc_elec_raw = $unit_electric * 10; // เรทน้า 10 บาท
    
    if ($calc_elec_raw < 100) {
        $price_electric = 100;
    } else {
        $price_electric = $calc_elec_raw;
    }

    // 4.3 ค่าอื่นๆ
    $price_central = isset($contract['fixed_electric_fee']) ? $contract['fixed_electric_fee'] : 0; // ค่าส่วนกลาง
    $price_trash = $contract['trash_fee']; // ค่าขยะ (30 บาท)
    $price_rent = $contract['rent_price'];

    // 5. รวมยอด
    $total = $price_rent + $price_water + $price_electric + $price_common_water + $price_central + $price_trash;

    // 6. บันทึก Meter
    $sql_meter = "INSERT INTO meter_readings (contract_id, reading_date, previous_water, current_water, previous_electric, current_electric, recorder_id) 
                  VALUES (:cid, :rdate, :pw, :cw, :pe, :ce, :rid)";
    $stmt = $pdo->prepare($sql_meter);
    $stmt->execute([
        'cid' => $contract['id'],
		'rdate' => $reading_date, // 🔥 ใช้วันที่ที่เลือก
        'pw' => $prev_water, 'cw' => $input['current_water'],
        'pe' => $prev_electric, 'ce' => $input['current_electric'],
        'rid' => $_SESSION['user_id'] ?? 0
    ]);
    $meter_id = $pdo->lastInsertId();

    // 7. บันทึก Invoice
    $inv_number = 'INV-' . date('Ymd-His') . '-' . str_pad($contract['id'], 4, '0', STR_PAD_LEFT);
    $sql_inv = "INSERT INTO invoices 
                (invoice_number, due_date, created_at, contract_id, meter_reading_id, period_end, 
                 amount_rent, amount_water, amount_electric, amount_common_water, amount_common_electric, amount_trash, amount_wastewater, total_amount, status)
                VALUES 
                (:inv, :due, :create, :cid, :mid, CURDATE(), :rent, :water, :elec, :cw, :ce, :trash, 0, :total, 'pending')";
    
    $stmt = $pdo->prepare($sql_inv);
    $stmt->execute([
        'inv' => $inv_number, 'cid' => $contract['id'], 'mid' => $meter_id,
		'due' => $due_date,       // 🔥 วันครบกำหนด
        'create' => $reading_date . ' ' . date('H:i:s'), // 🔥 วันที่ออกบิล (ใช้วันที่เลือก + เวลาปัจจุบัน)
        'rent' => $price_rent, 'water' => $price_water, 'elec' => $price_electric,
        'cw' => 0,
        'ce' => $price_central, 
        'trash' => $price_trash, 
        'total' => $total
    ]);
    
    $invoice_id = $pdo->lastInsertId();
    $pdo->commit();

    // ---------------------------------------------------------
    // 💬 ข้อความ LINE (Admin) - สูตรน้า
    // ---------------------------------------------------------
	// แปลงวันที่เป็นไทย
    function thaiDateShort($d) {
        $ex = explode('-', $d);
        return intval($ex[2]) . "/" . intval($ex[1]) . "/" . ($ex[0]+543);
    }
	
    $lineMsg  = "🧾 **ใบแจ้งหนี้ประจำเดือน** (รอตรวจสอบ)";
	$lineMsg .= "\n🗓️ วันที่จด: " . thaiDateShort($reading_date);
    $lineMsg .= "\n📅 ครบกำหนด: " . thaiDateShort($due_date);
    $lineMsg .= "\n🏠 ห้อง: " . $contract['room_number'] . " (" . $contract['fullname'] . ")";
    $lineMsg .= "\n-----------------------------";
    $lineMsg .= "\n1️⃣ ค่าห้อง: " . number_format($price_rent) . " บ.";
    
    // ค่าน้ำ (เช็คขั้นต่ำ 100)
    if ($price_water == 100 && ($unit_water * 30) < 100) {
        $lineMsg .= "\n2️⃣ ค่าน้ำ: 100 บ. (ขั้นต่ำ)";
    } else {
        $lineMsg .= "\n2️⃣ ค่าน้ำ (" . $unit_water . "หน่วย x 30บ.): " . number_format($price_water) . " บ.";
    }
    $lineMsg .= "\n    (" . $prev_water . " ➜ " . $input['current_water'] . ")";

    // ค่าไฟ (เช็คขั้นต่ำ 100)
    if ($price_electric == 100 && ($unit_electric * 10) < 100) {
        $lineMsg .= "\n3️⃣ ค่าไฟ: 100 บ. (ขั้นต่ำ)";
    } else {
        $lineMsg .= "\n3️⃣ ค่าไฟ (" . $unit_electric . "หน่วย x 10บ.): " . number_format($price_electric) . " บ.";
    }
    $lineMsg .= "\n    (" . $prev_electric . " ➜ " . $input['current_electric'] . ")";

    // ส่วนกลาง
    $show_central = ($price_central > 0) ? number_format($price_central) : "-";
    $lineMsg .= "\n4️⃣ ค่าส่วนกลาง (   +   +   ): " . $show_central . " บ.";

    // ขยะ
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