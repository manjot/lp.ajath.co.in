<?php
session_start();
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// 1. Honeypot anti-bot check
$honeypot = isset($_POST['website_url']) ? trim($_POST['website_url']) : '';
if ($honeypot !== '') {
    echo json_encode(['success' => false, 'message' => 'Spam submission blocked.']);
    exit;
}

// 2. Sanitize and extract inputs
$name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
$email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
$phone = isset($_POST['phone']) ? trim(strip_tags($_POST['phone'])) : '';
$budget = isset($_POST['budget']) ? trim(strip_tags($_POST['budget'])) : '';
$message = isset($_POST['message']) ? trim(strip_tags($_POST['message'])) : '';

// 3. Require all fields including Budget and Project Message
if (empty($name) || empty($email) || empty($phone) || empty($budget) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please complete all required fields, including your budget range and project details.']);
    exit;
}

// 4. Validate Name (at least 3 chars, must contain letters)
if (mb_strlen($name) < 3 || !preg_match('/[\p{L}]/u', $name)) {
    echo json_encode(['success' => false, 'message' => 'Please enter your full name.']);
    exit;
}

// 5. Validate Email format & block obvious gibberish/throwaway patterns (e.g. vj2222333@gmail.com)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid business email address.']);
    exit;
}
$emailParts = explode('@', strtolower($email));
$localPart = $emailParts[0] ?? '';
$domainPart = $emailParts[1] ?? '';
$blockedLocalPrefixes = ['test', 'testing', 'example', 'asdf', 'qwerty', 'demo', 'fake', 'none', 'null', 'admin'];
if (
    in_array($localPart, $blockedLocalPrefixes, true) ||
    preg_match('/(.)\1{3,}/', $localPart) || // 4+ identical consecutive chars/digits like vj2222333
    preg_match('/^[0-9]+$/', $localPart)
) {
    echo json_encode(['success' => false, 'message' => 'Please enter an active corporate or personal email address (avoid placeholder addresses).']);
    exit;
}

// 6. Validate Phone Number (10 to 15 digits, not repeating dummy digits)
$digitsOnly = preg_replace('/\D+/', '', $phone);
if (
    strlen($digitsOnly) < 10 ||
    strlen($digitsOnly) > 15 ||
    preg_match('/^(\d)\1{7,}$/', $digitsOnly) ||
    strpos($digitsOnly, '12345678') !== false ||
    strpos($digitsOnly, '00000000') !== false
) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid reachable 10-digit phone number.']);
    exit;
}

// 7. Validate Project Message (at least 1 non-whitespace character)
if (mb_strlen($message) < 1) {
    echo json_encode(['success' => false, 'message' => 'Please enter your project requirements.']);
    exit;
}

// SMTP Configuration
$smtp_host = 'ssl://smtp.gmail.com';
$smtp_port = 465;
$smtp_user = 'info@ajath.us';
$smtp_pass = 'qyjezabchvvicodk'; // App Password

$to = 'manjot2306@gmail.com';
$subject = 'New Consultation Request: ' . $name;

// Construct Email Body
$body = "You have received a new verified strategy session request from the Ajath Infotech landing page.\r\n\r\n";
$body .= "Name: " . $name . "\r\n";
$body .= "Email: " . $email . "\r\n";
$body .= "Phone: " . $phone . "\r\n";
$body .= "Budget Range: " . $budget . "\r\n";
$body .= "Message:\r\n" . $message . "\r\n";

// Helper function to send SMTP command and check response
function send_smtp_email($host, $port, $user, $pass, $to, $subject, $body, $reply_to, $from_name) {
    $socket = @fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        return ['success' => false, 'error' => "Connection failed: $errstr ($errno)"];
    }

    $response = fgets($socket, 515);

    $commands = [
        "EHLO " . $_SERVER['SERVER_NAME'],
        "AUTH LOGIN",
        base64_encode($user),
        base64_encode($pass),
        "MAIL FROM: <$user>",
        "RCPT TO: <$to>",
        "DATA"
    ];

    foreach ($commands as $cmd) {
        fputs($socket, $cmd . "\r\n");
        $res = '';
        while ($line = fgets($socket, 515)) {
            $res .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        $code = substr($res, 0, 3);
        if (!in_array($code, ['220', '250', '235', '334', '354'])) {
            fclose($socket);
            return ['success' => false, 'error' => "SMTP Error on '$cmd': $res"];
        }
    }

    // Prepare Headers & Message
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=utf-8\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "From: $from_name <$user>\r\n";
    $headers .= "Reply-To: <$reply_to>\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: " . date("r") . "\r\n";

    $email_content = $headers . "\r\n" . $body . "\r\n.\r\n";
    fputs($socket, $email_content);

    $res = '';
    while ($line = fgets($socket, 515)) {
        $res .= $line;
        if (substr($line, 3, 1) == ' ') break;
    }

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    if (substr($res, 0, 3) != '250') {
        return ['success' => false, 'error' => "Failed to send message data: $res"];
    }

    return ['success' => true];
}

$result = send_smtp_email($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $to, $subject, $body, $email, 'Ajath Infotech');

if ($result['success']) {
    $ts = (string)time();
    $nonce = bin2hex(random_bytes(8));
    $sig = hash_hmac('sha256', $ts . '.' . $nonce, 'ajath_lp_verified_lead_2026');
    $token = $ts . '.' . $nonce . '.' . substr($sig, 0, 32);
    $_SESSION['verified_conversion_token'] = $token;
    $_SESSION['verified_conversion_time'] = (int)$ts;
    echo json_encode([
        'success' => true,
        'token' => $token,
        'message' => 'Thank you! Your request has been sent successfully. Our team will contact you shortly.'
    ]);
} else {
    error_log("Mailer Error: " . $result['error']);
    echo json_encode(['success' => false, 'message' => 'Unable to send your request right now. Please try again or reach us on WhatsApp (+91 78385 18049).']);
}
?>
