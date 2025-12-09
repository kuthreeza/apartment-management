<?php
// api/webhook.php
require_once '../db.php';
require_once 'line_notify.php'; // เรียกใช้ฟังก์ชันส่งตอบกลับ

// 1. รับข้อมูลที่ LINE ส่งมา (เมื่อมีคนทักแชท)
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        
        // เราสนใจเฉพาะข้อความ (Text Message)
        if ($event['type'] == 'message' && $event['message']['type'] == 'text') {
            
            $userMessage = trim($event['message']['text']); // ข้อความที่เขาพิมพ์
            $lineUserId = $event['source']['userId'];       // ID ของเขา (ที่เราอยากได้)
            $replyToken = $event['replyToken'];             // (ใช้สำหรับ Reply API แต่เราใช้ Push แทนเพื่อง่าย)

            // --- Logic การลงทะเบียน ---
            // ถ้าพิมพ์ว่า "ลงทะเบียน A101" (เว้นวรรคด้วย)
            $parts = explode(' ', $userMessage);
            
            if ($parts[0] == 'ลงทะเบียน' && isset($parts[1])) {
                $roomNumber = $parts[1];

                // 1. หาว่าห้องนี้ใครเช่าอยู่ (ดึง id ผู้เช่าจากสัญญาที่ Active)
                $sql = "SELECT c.tenant_id, t.fullname 
                        FROM contracts c
                        JOIN rooms r ON c.room_id = r.id
                        JOIN tenants t ON c.tenant_id = t.id
                        WHERE r.room_number = :r_num AND c.is_active = 1";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['r_num' => $roomNumber]);
                $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($tenant) {
                    // 2. อัปเดต line_user_id ลงในตาราง tenants
                    $update = $pdo->prepare("UPDATE tenants SET line_user_id = :lid WHERE id = :tid");
                    $update->execute(['lid' => $lineUserId, 'tid' => $tenant['tenant_id']]);

                    // 3. ส่งข้อความตอบกลับ
                    $msg = "✅ ลงทะเบียนสำเร็จ!\nยินดีต้อนรับคุณ " . $tenant['fullname'] . "\nจากนี้บิลค่าเช่าห้อง " . $roomNumber . " จะถูกส่งมาที่นี่ครับ";
                    sendLineNotify($msg, null, $lineUserId);
                } else {
                    $msg = "❌ ไม่พบข้อมูลห้อง " . $roomNumber . " หรือห้องนี้ยังไม่มีผู้เช่าในระบบ";
                    sendLineNotify($msg, null, $lineUserId);
                }
            } 
            // กรณีพิมพ์อย่างอื่น (อาจจะทำเป็น Auto Reply ธรรมดา)
            else {
                // $msg = "พิมพ์ 'ลงทะเบียน [เลขห้อง]' เพื่อรับแจ้งเตือนบิลครับ";
                // sendLineNotify($msg, null, $lineUserId);
            }
        }
    }
}
echo "OK";
?>