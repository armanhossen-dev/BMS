<?php
// send_verification.php - Handle real SMS/Telegram sending
require_once 'config/db.php';

function sendViaTelegram($phoneNumber, $code) {
    // You need to create a Telegram Bot first
    // Go to Telegram -> Search @BotFather -> Create new bot
    // Get your bot token and chat ID
    
    $botToken = "YOUR_BOT_TOKEN"; // Replace with your bot token
    $chatId = "YOUR_CHAT_ID"; // Replace with your chat ID
    
    $message = "🔐 *Asha Bank Verification*\n\nYour verification code is: `$code`\n\nValid for 10 minutes. Do not share with anyone.";
    
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return $result !== false;
}

function sendViaEmailSMS($phoneNumber, $code) {
    // For Grameenphone users
    $carriers = [
        '017' => '@sms.grameenphone.com',
        '018' => '@robi.com.bd',
        '019' => '@banglalink.com.bd',
        '015' => '@teletalk.com.bd'
    ];
    
    $prefix = substr($phoneNumber, 0, 3);
    $domain = $carriers[$prefix] ?? '@sms.grameenphone.com';
    
    $email = $phoneNumber . $domain;
    $subject = "Asha Bank Verification Code";
    $message = "Your verification code is: $code\n\nValid for 10 minutes.";
    
    return mail($email, $subject, $message, "From: verification@ashabank.bd\r\n");
}

function sendViaEmail($email, $code) {
    $subject = "Asha Bank KYC Verification Code";
    $message = "
    <html>
    <head><title>KYC Verification</title></head>
    <body>
        <h2>Asha Bank Verification</h2>
        <p>Your verification code is: <strong style='font-size: 20px;'>$code</strong></p>
        <p>Valid for 10 minutes.</p>
        <p>Do not share this code with anyone.</p>
    </body>
    </html>";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: verification@ashabank.bd" . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

// Handle the request
if(isset($_POST['phone_number'])) {
    $phoneNumber = $_POST['phone_number'];
    $method = $_POST['delivery_method'] ?? 'demo';
    $code = rand(100000, 999999);
    
    $sent = false;
    $message = "";
    
    switch($method) {
        case 'telegram':
            $sent = sendViaTelegram($phoneNumber, $code);
            $message = $sent ? "Verification code sent via Telegram!" : "Failed to send via Telegram. Please try demo mode.";
            break;
        case 'sms_gateway':
            $sent = sendViaEmailSMS($phoneNumber, $code);
            $message = $sent ? "Verification code sent via SMS!" : "Failed to send SMS. Please try demo mode.";
            break;
        case 'email':
            $email = $_POST['email'] ?? '';
            $sent = sendViaEmail($email, $code);
            $message = $sent ? "Verification code sent to your email!" : "Failed to send email. Please try demo mode.";
            break;
        default:
            // Demo mode - show code on screen
            $message = "Demo Mode: Your verification code is: $code";
            $sent = true;
    }
    
    // Store code in database
    $pdo->prepare("UPDATE kyc_verifications SET verification_code = ? WHERE customer_id = ?")->execute([$code, $_SESSION['user_id']]);
    
    echo json_encode(['success' => $sent, 'message' => $message, 'code' => $method == 'demo' ? $code : null]);
    exit();
}
?>