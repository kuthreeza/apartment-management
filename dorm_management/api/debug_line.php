<?php
// api/debug_line.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🕵️ กำลังตรวจสอบการเชื่อมต่อ LINE API...</h2>";

// ================= ใส่ข้อมูลของคุณตรงนี้ =================
$access_token = 'zwjs4b3gTWBX7MDpTKIlu3h6rUL4nVCN9I6NZNOL9mS33uCbIZ9mjlsKVOPZW7e1mZ+eyKNanVqheJM/dVYpL3Cu2Yu3+/GTrF4L7tSIVU3K4JdBMS0oHuKDYysQZcrNWOneKymIV3oMyRg5XzkqFgdB04t89/1O/w1cDnyilFU='; 
$user_id = 'Uef60c12cdee162dff003c014bbaa8c40'; 
// ===================================================

$messages = [ 'type' => 'text', 'text' => 'Test connection from VPS' ];
$data = [ 'to' => $user_id, 'messages' => [$messages] ];

$ch = curl_init('https://api.line.me/v2/bot/message/push');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $access_token
]);

// -----------------------------------------------------------
// ❌ ลองปิดบรรทัดนี้ดูก่อน (เพื่อดู Error จริง)
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
// -----------------------------------------------------------

echo "กำลังส่งข้อมูล...<br>";

$result = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error_msg = curl_error($ch);

curl_close($ch);

echo "<h3>ผลลัพธ์:</h3>";

if ($error_msg) {
    echo "<div style='color:red; font-weight:bold;'>❌ เกิดข้อผิดพลาด (cURL Error): " . $error_msg . "</div>";
    echo "<p>คำแนะนำ: ถ้า Error ฟ้องว่า 'SSL certificate problem', ให้ทำตามขั้นตอนที่ 2 ด้านล่าง</p>";
} else {
    echo "<div style='color:green; font-weight:bold;'>✅ ส่งข้อมูลสำเร็จ! (HTTP Status: $http_status)</div>";
    echo "<div>Response จาก LINE: <pre>" . htmlspecialchars($result) . "</pre></div>";
}
?>