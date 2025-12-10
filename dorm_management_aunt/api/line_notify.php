<?php
// api/line_notify.php

function sendLineNotify($message, $imageUrl = null, $targetUserId = null) {
    
    // ==========================================================
    // 1. ตั้งค่า Token
    $access_token = ''; 

    // 2. ตั้งค่ารายการ Admin (ใส่ได้มากกว่า 1 คน)
	$admin_list = [
        '' // Admin 1 (คุณ)
        //'Uxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', // Admin 2 (...)
        //'Uyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy'  // Admin 3 (...)
    ];
    // ==========================================================

    // 3. กำหนดรายชื่อคนที่จะส่ง (Recipients)
    $recipients = [];

    if ($targetUserId !== null && $targetUserId !== "") {
        // กรณีระบุเจาะจง (เช่น ส่งหาผู้เช่า) -> ส่งคนเดียว
        $recipients[] = $targetUserId;
    } else {
        // กรณีไม่ระบุ (แจ้งเตือนระบบ) -> ส่งหา Admin ทุกคนในลิสต์
        $recipients = $admin_list;
    }

    // 4. วนลูปส่งทีละคน (Messaging API ต้องยิงแยก)
    foreach ($recipients as $uid) {
        
        // เตรียมข้อความ
        $messages = [];
        $messages[] = ['type' => 'text', 'text' => $message];

        if ($imageUrl !== null && $imageUrl !== "") {
            $messages[] = [
                'type' => 'image',
                'originalContentUrl' => $imageUrl,
                'previewImageUrl' => $imageUrl
            ];
        }

        $data = [
            'to' => $uid,
            'messages' => $messages
        ];

        // ตั้งค่า cURL
        $ch = curl_init('https://api.line.me/v2/bot/message/push');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        
        // 🔥 ปิด SSL (แก้ปัญหา VPS)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // ยิงข้อมูล
        $result = curl_exec($ch);
        
        // 🔥 [เช็ค Error ตามที่คุณต้องการ]
        if ($result === false) {
            // ถ้าส่งไม่ผ่าน ให้บันทึก Error ลงไฟล์ log ของ Server (หรือแสดงผลถ้าเปิด debug)
            error_log("LINE API Error (To: $uid): " . curl_error($ch));
        }

        curl_close($ch);
    }

    return true;
}
?>