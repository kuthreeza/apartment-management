<?php
// print_bill.php (ระบบครอบครัว: แก้ไขวันที่ + สูตรถูกต้อง)
require_once 'db.php';

if (!isset($_GET['id'])) die("ไม่พบเลขที่บิล");
$invoice_id = $_GET['id'];

// ดึงข้อมูลบิล (period_end = วันที่จด, due_date = วันครบกำหนด)
$sql = "SELECT i.*, r.room_number, b.name as building_name, t.fullname,
               m.reading_date, m.previous_water, m.current_water, m.previous_electric, m.current_electric
        FROM invoices i
        JOIN contracts c ON i.contract_id = c.id
        JOIN rooms r ON c.room_id = r.id
        JOIN buildings b ON r.building_id = b.id
        JOIN tenants t ON c.tenant_id = t.id
        JOIN meter_readings m ON i.meter_reading_id = m.id
        WHERE i.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $invoice_id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) die("ไม่พบข้อมูลบิล");

// ฟังก์ชันแปลงวันที่
function thaiDate($date) {
    if(!$date || $date == '0000-00-00') return "-";
    // ตัดเอาเฉพาะวันที่ (เผื่อมีเวลาติดมา)
    $date = explode(' ', $date)[0];
    $months = [null, "ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
    $y = substr($date, 0, 4) + 543;
    $m = (int)substr($date, 5, 2);
    $d = substr($date, 8, 2);
    return "$d " . $months[$m] . " $y";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบแจ้งหนี้ <?php echo $inv['room_number']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; margin: 0; padding: 0; background: #eee; }
        .bill-container {
            width: 78mm; 
            background: #fff;
            margin: 0 auto;
            padding: 5px;
            font-size: 16px; 
            font-weight: 500;
            line-height: 1.4;
        }
        .header { text-align: center; margin-bottom: 10px; }
        .header h2 { margin: 0; font-size: 22px; font-weight: bold; }
        .line { border-bottom: 2px dashed #000; margin: 10px 0; }
        .flex { display: flex; justify-content: space-between; }
        .bold { font-weight: bold; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { text-align: left; padding: 4px 0; vertical-align: top; }
        .amt { text-align: right; white-space: nowrap; }

        @media print {
            body { background: #fff; }
            .no-print { display: none; }
            @page { margin: 0; size: 80mm auto; }
        }
    </style>
</head>
<body>

    <div class="bill-container">
        <div class="header">
            <h2><?php echo $inv['building_name']; ?></h2>
            <p>ใบแจ้งค่าเช่า / Invoice</p>
        </div>
        
        <div class="line"></div>
        
        <div class="flex">
            <span>ห้อง: <span class="bold"><?php echo $inv['room_number']; ?></span></span>
            <span>วันที่: <?php echo thaiDate($inv['reading_date']); ?></span>
        </div>
        <div>ผู้เช่า: <?php echo $inv['fullname']; ?></div>
        <div>ครบกำหนดชำระ: <?php echo isset($inv['due_date']) ? thaiDate($inv['due_date']) : "-"; ?></div>
        <div>เลขที่บิล: <?php echo $inv['invoice_number']; ?></div>

        <div class="line"></div>

        <table>
            <tr><td>รายการ</td><td class="amt">หน่วย</td><td class="amt">บาท</td></tr>
            <tr><td>ค่าเช่าห้อง</td><td class="amt">-</td><td class="amt"><?php echo number_format($inv['amount_rent']); ?></td></tr>
            
            <?php 
                // คำนวณค่าน้ำ (เพื่อเช็คขั้นต่ำตอนแสดงผล)
                $units_w = $inv['current_water'] - $inv['previous_water']; 
                $total_water_money = $inv['amount_water'] + $inv['amount_common_water'];
                $normal_water_calc = $units_w * (25 + 3); 
            ?>
            <tr>
                <td>
                    <?php if ($total_water_money == 50 && $normal_water_calc < 50): ?>
                        ค่าน้ำ <b>(ขั้นต่ำ)</b><br>
                    <?php else: ?>
                        ค่าน้ำ (<?php echo $units_w; ?> หน่วย x (25บ.+3บ.))<br>
                    <?php endif; ?>
                    <small style="color:#666;">(มิเตอร์: <?php echo $inv['previous_water']; ?>-<?php echo $inv['current_water']; ?>)</small>
                </td>
                <td class="amt"><?php echo $units_w; ?></td>
                <td class="amt bold"><?php echo number_format($total_water_money); ?></td>
            </tr>

            <?php 
                // คำนวณค่าไฟ (เพื่อเช็คขั้นต่ำตอนแสดงผล)
                $units_e = $inv['current_electric'] - $inv['previous_electric']; 
                $normal_elec_calc = $units_e * 5;
            ?>
            <tr>
                <td>
                    <?php if ($inv['amount_electric'] == 50 && $normal_elec_calc < 50): ?>
                        ค่าไฟ <b>(ขั้นต่ำ)</b><br>
                    <?php else: ?>
                        ค่าไฟ (<?php echo $units_e; ?> หน่วย x 5บ.)<br>
                    <?php endif; ?>
                    <small style="color:#666;">(มิเตอร์: <?php echo $inv['previous_electric']; ?>-<?php echo $inv['current_electric']; ?>)</small>
                </td>
                <td class="amt"><?php echo $units_e; ?></td>
                <td class="amt bold"><?php echo number_format($inv['amount_electric']); ?></td>
            </tr>

            <tr>
                <td>ค่าส่วนกลาง (&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;+&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;+&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</td>
                <td class="amt"></td>
                <td class="amt"><?php echo ($inv['amount_common_electric'] > 0) ? number_format($inv['amount_common_electric']) : '-'; ?></td>
            </tr>
            
            <?php if($inv['amount_wastewater'] > 0): ?>
            <tr>
                <td>ค่าบำบัดน้ำเสีย</td>
                <td class="amt"></td>
                <td class="amt"><?php echo number_format($inv['amount_wastewater']); ?></td>
            </tr>
            <?php endif; ?>

            <?php if($inv['amount_trash'] > 0): ?>
            <tr><td>ค่าขยะ</td><td class="amt"></td><td class="amt"><?php echo number_format($inv['amount_trash']); ?></td></tr>
            <?php endif; ?>
            
            <tr style="border-top: 1px solid #000; font-weight: bold; font-size: 14px;">
                <td colspan="2">ยอดรวมสุทธิ</td>
                <td class="amt"><?php echo number_format($inv['total_amount'], 2); ?></td>
            </tr>
        </table>

        <div class="line"></div>
        <div style="text-align: center; margin-top: 10px;">
            <p>ขอบคุณครับ/ค่ะ</p>
        </div>

        <button class="no-print" onclick="window.print()" style="width: 100%; padding: 10px; margin-top: 20px; cursor: pointer;">🖨️ พิมพ์ใบแจ้งหนี้</button>
        <button class="no-print" onclick="window.close()" style="width: 100%; padding: 10px; margin-top: 5px; cursor: pointer;">ปิดหน้าต่าง</button>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>