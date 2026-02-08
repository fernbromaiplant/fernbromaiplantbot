<?php
/**
 * AI 植物醫生 v4.5 - 最終穩定版 (Render + LINE + Gemini V1)
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- 已經填入你的 LINE Token ---
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyBF3MoPf24LL7fY0kuvSqmEBQ2fso0v3jU"; 

// 接收 LINE 傳來的訊息
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        // 只處理圖片訊息
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 從 LINE 伺服器下載圖片
            $url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 準備呼叫 Google Gemini API (使用最穩定的 V1 正式版路徑)
            $api_url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $api_key;
            
            // 採用你 Byethost 版本的專業 Prompt
            $prompt = "你是一位資深植物病理學家。第一行請直接寫出植物名，之後請針對健康狀況與處方給予簡短建議（請使用繁體中文）。";

            $payload = [
                "contents" => [["parts" => [
                    ["text" => $prompt],
                    ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                ]]]
            ];

            // 3. 執行 Gemini API 請求
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $res_arr = json_decode($response, true);
            curl_close($ch);
            
            // 4. 解析 AI 回傳結果
            if (isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
            } else {
                // 如果失敗，抓取具體原因
                $errorDetail = $res_arr['error']['message'] ?? "原因不明 (可能是頻率限制或圖片問題)";
                $replyText = "❌ 診斷暫時失敗：\n" . $errorDetail . "\n\n(建議等 10 秒後再傳一次試試看)";
            }

            // 5. 將結果回傳給 LINE 使用者
            $post_data = [
                'replyToken' => $replyToken,
                'messages' => [['type' => 'text', 'text' => $replyText]]
            ];
            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_exec($ch);
            curl_close($ch);
        }
    }
} else {
    // 網頁端顯示狀態
    echo "<h2>Plant Doctor Bot: Active</h2>";
    echo "Model: Gemini 1.5 Flash (V1 Stable)";
}
       
