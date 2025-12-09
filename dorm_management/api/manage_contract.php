<?php
// api/manage_contract.php
//session_start();
header('Content-Type: application/json');
require_once '../db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    $pdo->beginTransaction();

    // --- กรณี 1: แจ้งเข้าพัก (Move In) ---
    if ($action === 'move_in') {
        // 1. เพิ่มข้อมูลผู้เช่า (Tenants)
        $sql_tenant = "INSERT INTO tenants (fullname, phone, id_card) VALUES (:name, :phone, :id_card)";
        $stmt = $pdo->prepare($sql_tenant);
        $stmt->execute([
            'name' => $input['fullname'],
            'phone' => $input['phone'],
            'id_card' => $input['id_card'] ?? ''
        ]);
        $tenant_id = $pdo->lastInsertId();

        // 2. สร้างสัญญาเช่า (Contracts)
        // หมายเหตุ: ตรงนี้เราใส่ราคา Default ค่าน้ำ/ไฟ ตามมาตรฐาน (แก้ได้ถ้าต้องการ)
		// ระบุชื่อคอลัมน์ให้ชัดเจน (รวมถึงคอลัมน์ใหม่ๆ ที่เราเพิ่งเพิ่ม เช่น fixed_electric_fee)
        $sql_contract = "INSERT INTO contracts 
            (room_id, tenant_id, start_date, rent_price, deposit, 
             water_unit_price, electric_unit_price, common_fee_water_unit, common_fee_electric_unit, 
             trash_fee, wastewater_fee, fixed_electric_fee, is_active) 
            VALUES 
            (:rid, :tid, :start, :rent, :deposit, 
             25, 5, 3, 0, 
             0, 30, 0, 1)"; // ใส่ Default Value ให้ครบ (น้ำ25, ไฟ5, ปั๊ม3, ไฟหน่วย0, ขยะ30, บำบัด0, ไฟทาง10)
        
        $stmt = $pdo->prepare($sql_contract);
        $stmt->execute([
            'rid' => $input['room_id'],
            'tid' => $tenant_id,
            'start' => $input['start_date'], // วันที่เลือกมาจากหน้าเว็บ
            'rent' => $input['rent_price'],
            'deposit' => $input['deposit']
        ]);
        $contract_id = $pdo->lastInsertId();

        // 3. อัปเดตสถานะห้องเป็น 'occupied'
        $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?")->execute([$input['room_id']]);

        // 4. บันทึกเลขมิเตอร์เริ่มต้น (Meter Readings) เพื่อใช้เป็นฐานของเดือนแรก
        // เทคนิค: ใส่เป็นเลขปัจจุบันทั้ง previous และ current เพื่อให้ระบบรู้จุดเริ่มต้น
        $sql_meter = "INSERT INTO meter_readings 
            (contract_id, reading_date, previous_water, current_water, previous_electric, current_electric, recorder_id)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql_meter);
        $stmt->execute([
            $contract_id,
            $input['init_water'], $input['init_water'], // น้ำเริ่มต้น
            $input['init_electric'], $input['init_electric'], // ไฟเริ่มต้น
            $_SESSION['user_id']
        ]);

        $message = "✅ ลงทะเบียนผู้เช่าเรียบร้อย!";
    }

    // --- กรณี 2: แจ้งย้ายออก (Move Out) ---
    elseif ($action === 'move_out') {
        $room_id = $input['room_id'];

        // 1. ปิดสัญญาเช่าเดิม (Set Active = False)
        $sql_close = "UPDATE contracts SET is_active = 0, end_date = CURDATE() WHERE room_id = ? AND is_active = 1";
        $pdo->prepare($sql_close)->execute([$room_id]);

        // 2. อัปเดตสถานะห้องเป็น 'available'
        $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?")->execute([$room_id]);

        $message = "✅ แจ้งย้ายออกเรียบร้อย ห้องกลับสู่สถานะว่าง";
    } 
    
    else {
        throw new Exception("ไม่พบคำสั่งที่ต้องการ");
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => $message]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
?>