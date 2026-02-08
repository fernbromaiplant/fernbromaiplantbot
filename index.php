<?php
$access_token = 'zBjmdLPs6hhz0JKcrGTjfRTWBTYSSVxeR8YTHJFGatPDfuNu4i/9GwQ5YL3hFQWm9gN3EorIBc78X5tFpsg467e2Wh9Zy2Nx14DEgeUnEw7ycJ103VqtpEVEBw1RL4xkbdT+lyTStxBhEbix/k+FQwdB04t89/1O/w1cDnyilFU=';
$api_key = "AIzaSyBF3MoPf24LL7fY0kuvSqmEBQ2fso0v3jU"; 

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

            // 2. 呼叫 Gemini 1.5 Flash (使用最標準的 v1beta 路徑)
            $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $api_key;
            
            $payload = [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => "你是一位資深植物專家。請辨識此植物並給予繁體中文建議。"],
                            [
                                "inline_data" => [
                                    "mime_type" => "image/jpeg",
                                    "data" => base64_encode($imgData)
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $ch = curl_init($gemini_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $res = json_decode($response, true);
            curl_close($ch);
            
            if (isset($res['candidates'][0]['content']['parts'][0]['text'])) {
                $replyText = $res['candidates'][0]['content']['parts'][0]['text'];
            } else {
                // 如果失敗，抓取最底層的錯誤原因
                $errorMsg = $res['error']['message'] ?? "模型連結異常";
                $replyText = "❌ 系統訊息：$errorMsg";
            }

            // 3. 回傳訊息給 LINE
            $post_data = ['replyToken' => $replyToken, 'messages' => [['type' => 'text', 'text' => $replyText]]];
            $ch = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            curl_exec($ch);
            curl_close($ch);
        }
    }
} else {
    echo "Bot is OK! Final Version 2.0";
}
