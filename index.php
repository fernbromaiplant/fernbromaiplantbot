<?php
/**
 * AI 植物醫生 v4.6 - 終極穩定路徑版
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- 資訊確認 ---
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU='; 
$api_key = "AIzaSyCo6w2_SXVWkP0YBtReaQo9YoNyBAyZYRE"; 

// 接收 LINE 訊息
$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!empty($events['events'])) {
    foreach ($events['events'] as $event) {
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $replyToken = $event['replyToken'];
            $messageId = $event['message']['id'];

            // 1. 下載 LINE 圖片
            $url = 'https://api-data.line.me/v2/bot/message/' . $messageId . '/content';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imgData = curl_exec($ch);
            curl_close($ch);

            // 2. 呼叫 Gemini API (使用 v1beta 的標準格式)
            // 注意：如果這個路徑再不行，就代表你的 API Key 權限需要重新在 Google AI Studio 產生一張新的
            $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
            
            $prompt = "你是一位資深植物病理學家。第一行請直接寫出植物名，之後請針對健康狀況與處方給予簡短建議（請使用繁體中文）。";

            $payload = [
                "contents" => [["parts" => [
                    ["text" => $prompt],
                    ["inline_data" => ["mime_type" => "image/jpeg", "data" => base64_encode($imgData)]]
                ]]]
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $res_arr = json_decode($response, true);
            curl_close($ch);
            
            // 3. 解析與回傳
            if (isset($res_arr['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = $res_arr['candidates'][0]['content']['parts'][0]['text'];
            } else {
                $errorMsg = $res_arr['error']['message'] ?? "API 目前不回應，請檢查 Google AI Studio 設定";
                $replyText = "❌ 診斷失敗：\n" . $errorMsg;
            }

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
    echo "<h2>Plant Doctor: Online</h2>";
    echo "Ready for image diagnosis.";
}
   
        
