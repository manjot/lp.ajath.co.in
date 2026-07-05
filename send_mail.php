<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$name   = isset($_POST['name'])   ? trim($_POST['name'])   : '';
$email  = isset($_POST['email'])  ? trim($_POST['email'])  : '';
$phone  = isset($_POST['phone'])  ? trim($_POST['phone'])  : '';
$budget  = isset($_POST['budget'])  ? trim($_POST['budget'])  : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($name) || empty($email) || empty($phone) || empty($budget)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

// SMTP configuration
$smtp_host = 'ssl://smtp.gmail.com';
$smtp_port = 465;
$smtp_user = 'info@ajath.us';
$smtp_pass = 'qyjezabchvvicodk'; // App Password

// All recipients — delivered in a single SMTP session
$to_emails = ['manjot2306@gmail.com'];

$subject = "New Consultation Request: " . $name;
$message_body = "You have received a new strategy session request from the Ajath Infotech landing page.\n\n" .
                "Name: $name\n" .
                "Email: $email\n" .
                "Phone: $phone\n" .
                "Budget Range: $budget\n" .
                "Message: $message\n";

/**
 * Sends one email to multiple recipients in a single SMTP session.
 * Issues one RCPT TO per recipient — all get the same message.
 */
function send_smtp_email($host, $port, $user, $pass, array $recipients, $subject, $body) {
    $socket = fsockopen($host, $port, $errno, $errstr, 15);
    if (!$socket) {
        return "Socket connection failed: $errstr ($errno)";
    }

    $expect = function($code) use ($socket) {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        if (substr($data, 0, 3) !== (string)$code) {
            throw new Exception("Expected code $code, got: $data");
        }
        return $data;
    };

    try {
        $expect(220);

        fwrite($socket, "EHLO localhost\r\n");
        $expect(250);

        fwrite($socket, "AUTH LOGIN\r\n");
        $expect(334);

        fwrite($socket, base64_encode($user) . "\r\n");
        $expect(334);

        fwrite($socket, base64_encode($pass) . "\r\n");
        $expect(235);

        fwrite($socket, "MAIL FROM: <$user>\r\n");
        $expect(250);

        // One RCPT TO per recipient — all in the same session
        foreach ($recipients as $recipient) {
            fwrite($socket, "RCPT TO: <$recipient>\r\n");
            $expect(250);
        }

        fwrite($socket, "DATA\r\n");
        $expect(354);

        $to_header = implode(', ', array_map(fn($r) => "<$r>", $recipients));
        $headers = "From: Ajath Infotech <$user>\r\n" .
                   "To: $to_header\r\n" .
                   "Subject: $subject\r\n" .
                   "MIME-Version: 1.0\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n" .
                   "Content-Transfer-Encoding: 8bit\r\n\r\n";

        fwrite($socket, $headers . $body . "\r\n.\r\n");
        $expect(250);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return true;

    } catch (Exception $e) {
        fclose($socket);
        return $e->getMessage();
    }
}

$result = send_smtp_email($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $to_emails, $subject, $message_body);

if ($result === true) {
    echo json_encode(['success' => true, 'message' => 'Thank you! Your strategy session request has been submitted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send email. Error: ' . $result]);
}
