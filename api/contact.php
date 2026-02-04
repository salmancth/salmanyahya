<?php
/**
 * Contact Form Email Handler with SMTP
 * Handles contact form submissions and sends emails via SMTP
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data || !isset($data['name']) || !isset($data['email']) || !isset($data['message'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$name = trim($data['name']);
$email = trim($data['email']);
$subject = isset($data['subject']) ? trim($data['subject']) : 'Nytt meddelande från kontaktformulär';
$message = trim($data['message']);
$language = isset($data['language']) ? $data['language'] : 'sv'; // sv or en

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

// Sanitize inputs
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// SMTP Configuration
$smtp_config = [
    'host' => 'mail.salmanyahya.com',
    'port' => 465,
    'username' => 'noreply@salmanyahya.com',
    'password' => 'Salman@162345',
    'encryption' => 'ssl',
    'from_email' => 'noreply@salmanyahya.com',
    'from_name' => 'Salman Yahya - Portfolio',
    'to_email' => 'kontakt@salmanyahya.com',
    'reply_to' => 'salman.yahya.soc@outlook.com'
];

try {
    // Send notification to admin
    $adminEmailSent = sendEmailSMTP(
        $smtp_config,
        $smtp_config['to_email'],
        "Nytt meddelande från {$name}",
        getAdminEmailTemplate($name, $email, $subject, $message),
        $email // Reply-To sender's email
    );

    // Send auto-reply to sender
    $autoReplySent = sendEmailSMTP(
        $smtp_config,
        $email,
        getAutoReplySubject($language),
        getAutoReplyTemplate($name, $language),
        $smtp_config['reply_to'] // Reply-To Salman's email
    );

    if ($adminEmailSent) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $language === 'sv' 
                ? 'Tack för ditt meddelande! Jag återkommer inom kort.' 
                : 'Thank you for your message! I will get back to you soon.'
        ]);
    } else {
        throw new Exception('Failed to send email');
    }

} catch (Exception $e) {
    error_log('Email error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $language === 'sv' 
            ? 'Ett fel uppstod vid skickandet. Försök igen senare.' 
            : 'An error occurred while sending. Please try again later.'
    ]);
}

/**
 * Send email via SMTP using fsockopen
 */
function sendEmailSMTP($config, $to, $subject, $htmlBody, $replyTo = null) {
    $boundary = md5(time());
    
    // Prepare headers
    $headers = [
        "From: {$config['from_name']} <{$config['from_email']}>",
        "Reply-To: " . ($replyTo ?: $config['reply_to']),
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "X-Mailer: PHP/" . phpversion()
    ];

    // Prepare body
    $textBody = strip_tags($htmlBody);
    
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";
    
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    
    $body .= "--{$boundary}--";

    try {
        // Connect to SMTP server
        $smtpConnect = $config['encryption'] === 'ssl' 
            ? "ssl://{$config['host']}" 
            : $config['host'];
        
        $smtp = fsockopen($smtpConnect, $config['port'], $errno, $errstr, 30);
        
        if (!$smtp) {
            throw new Exception("Failed to connect to SMTP server: {$errstr} ({$errno})");
        }

        // Read response
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '220') {
            throw new Exception("SMTP connection failed: {$response}");
        }

        // Send EHLO
        fputs($smtp, "EHLO {$config['host']}\r\n");
        $response = fgets($smtp, 515);

        // Authenticate
        fputs($smtp, "AUTH LOGIN\r\n");
        fgets($smtp, 515);
        
        fputs($smtp, base64_encode($config['username']) . "\r\n");
        fgets($smtp, 515);
        
        fputs($smtp, base64_encode($config['password']) . "\r\n");
        $response = fgets($smtp, 515);
        
        if (substr($response, 0, 3) != '235') {
            throw new Exception("SMTP authentication failed: {$response}");
        }

        // Send MAIL FROM
        fputs($smtp, "MAIL FROM: <{$config['from_email']}>\r\n");
        fgets($smtp, 515);

        // Send RCPT TO
        fputs($smtp, "RCPT TO: <{$to}>\r\n");
        fgets($smtp, 515);

        // Send DATA
        fputs($smtp, "DATA\r\n");
        fgets($smtp, 515);

        // Send headers and body
        $emailData = implode("\r\n", $headers) . "\r\n";
        $emailData .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $emailData .= "To: {$to}\r\n\r\n";
        $emailData .= $body;
        $emailData .= "\r\n.\r\n";

        fputs($smtp, $emailData);
        $response = fgets($smtp, 515);

        // Quit
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);

        return substr($response, 0, 3) == '250';

    } catch (Exception $e) {
        error_log('SMTP Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Admin notification email template
 */
function getAdminEmailTemplate($name, $email, $subject, $message) {
    return <<<HTML
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nytt meddelande</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 24px;">📬 Nytt Meddelande från Kontaktformulär</h1>
    </div>
    
    <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #ddd;">
        <h2 style="color: #667eea; margin-top: 0;">Kontaktinformation</h2>
        
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold; width: 30%;">👤 Namn:</td>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;">{$name}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;">📧 E-post:</td>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;"><a href="mailto:{$email}" style="color: #667eea;">{$email}</a></td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;">📋 Ämne:</td>
                <td style="padding: 10px; border-bottom: 1px solid #ddd;">{$subject}</td>
            </tr>
            <tr>
                <td style="padding: 10px; font-weight: bold;">🕐 Datum:</td>
                <td style="padding: 10px;">{date('Y-m-d H:i:s')}</td>
            </tr>
        </table>
        
        <h2 style="color: #667eea;">💬 Meddelande</h2>
        <div style="background: white; padding: 20px; border-radius: 5px; border-left: 4px solid #667eea; white-space: pre-wrap;">
{$message}
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #777; font-size: 14px;">
            <p>Detta meddelande skickades från kontaktformuläret på <a href="https://salmanyahya.com" style="color: #667eea;">salmanyahya.com</a></p>
            <p>Svara direkt på e-postadressen ovan för att kontakta avsändaren.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Auto-reply subject based on language
 */
function getAutoReplySubject($language) {
    return $language === 'sv' 
        ? 'Tack för ditt meddelande - Salman Yahya'
        : 'Thank you for your message - Salman Yahya';
}

/**
 * Auto-reply email template (multilingual)
 */
function getAutoReplyTemplate($name, $language) {
    if ($language === 'sv') {
        return getSwedishAutoReply($name);
    } else {
        return getEnglishAutoReply($name);
    }
}

/**
 * Swedish auto-reply template
 */
function getSwedishAutoReply($name) {
    return <<<HTML
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tack för ditt meddelande</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #00d4ff 0%, #7c3aed 100%); padding: 40px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 28px;">✅ Meddelande mottaget!</h1>
    </div>
    
    <div style="background: #f9f9f9; padding: 40px; border-radius: 0 0 10px 10px; border: 1px solid #ddd;">
        <p style="font-size: 18px; margin-top: 0;">Hej {$name}! 👋</p>
        
        <p>Tack för att du kontaktade mig! Jag har mottagit ditt meddelande och uppskattar verkligen att du tog dig tid att höra av dig.</p>
        
        <div style="background: white; padding: 20px; border-radius: 5px; border-left: 4px solid #00d4ff; margin: 20px 0;">
            <p style="margin: 0; font-weight: bold; color: #00d4ff;">📧 Vad händer nu?</p>
            <p style="margin: 10px 0 0 0;">Jag granskar alla meddelanden personligen och återkommer vanligtvis inom 24-48 timmar under vardagar. Om ditt ärende är brådskande, vänligen ange det i ditt meddelande.</p>
        </div>
        
        <h3 style="color: #7c3aed; margin-top: 30px;">Under tiden kan du:</h3>
        <ul style="line-height: 2;">
            <li>📚 Utforska mina <a href="https://salmanyahya.com/alla-projekt" style="color: #00d4ff; text-decoration: none;">projekt och artiklar</a></li>
            <li>💼 Se min <a href="https://salmanyahya.com/erfarenhet.html" style="color: #00d4ff; text-decoration: none;">professionella erfarenhet</a></li>
            <li>🔗 Följ mig på <a href="https://linkedin.com/in/salman-yahya/" style="color: #00d4ff; text-decoration: none;">LinkedIn</a></li>
            <li>🌐 Besök min <a href="https://salmancth.github.io/info" style="color: #00d4ff; text-decoration: none;">portfolio</a></li>
        </ul>
        
        <div style="background: #e8f4f8; padding: 20px; border-radius: 5px; margin: 30px 0;">
            <p style="margin: 0; font-size: 14px; color: #555;">
                <strong>💡 Tips:</strong> Om du vill diskutera ett projekt eller samarbete, 
                inkludera gärna information om tidsram, budget och tekniska krav så kan jag 
                ge dig ett mer detaljerat svar.
            </p>
        </div>
        
        <p style="margin-top: 30px;">Med vänliga hälsningar,<br>
        <strong style="color: #7c3aed; font-size: 18px;">Salman Yahya</strong><br>
        <span style="color: #777; font-size: 14px;">Automationsingenjör & Mjukvaruutvecklare</span></p>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center;">
            <p style="color: #777; font-size: 14px; margin: 5px 0;">
                📧 <a href="mailto:salman.yahya.soc@outlook.com" style="color: #00d4ff; text-decoration: none;">salman.yahya.soc@outlook.com</a><br>
                📞 <a href="tel:+46761883683" style="color: #00d4ff; text-decoration: none;">+46 76 188 36 83</a><br>
                📍 Göteborg, Sverige
            </p>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
            <p style="margin: 0; font-size: 12px; color: #856404;">
                ⚠️ Detta är ett automatiskt svar. Vänligen svara inte på detta e-postmeddelande. 
                Om du behöver kontakta mig direkt, använd <a href="mailto:salman.yahya.soc@outlook.com" style="color: #856404;">salman.yahya.soc@outlook.com</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * English auto-reply template
 */
function getEnglishAutoReply($name) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank you for your message</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #00d4ff 0%, #7c3aed 100%); padding: 40px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 28px;">✅ Message Received!</h1>
    </div>
    
    <div style="background: #f9f9f9; padding: 40px; border-radius: 0 0 10px 10px; border: 1px solid #ddd;">
        <p style="font-size: 18px; margin-top: 0;">Hello {$name}! 👋</p>
        
        <p>Thank you for reaching out! I have received your message and truly appreciate you taking the time to contact me.</p>
        
        <div style="background: white; padding: 20px; border-radius: 5px; border-left: 4px solid #00d4ff; margin: 20px 0;">
            <p style="margin: 0; font-weight: bold; color: #00d4ff;">📧 What happens next?</p>
            <p style="margin: 10px 0 0 0;">I personally review all messages and typically respond within 24-48 hours on business days. If your matter is urgent, please indicate this in your message.</p>
        </div>
        
        <h3 style="color: #7c3aed; margin-top: 30px;">In the meantime, you can:</h3>
        <ul style="line-height: 2;">
            <li>📚 Explore my <a href="https://salmanyahya.com/alla-projekt" style="color: #00d4ff; text-decoration: none;">projects and articles</a></li>
            <li>💼 View my <a href="https://salmanyahya.com/en/experience.html" style="color: #00d4ff; text-decoration: none;">professional experience</a></li>
            <li>🔗 Connect with me on <a href="https://linkedin.com/in/salman-yahya/" style="color: #00d4ff; text-decoration: none;">LinkedIn</a></li>
            <li>🌐 Visit my <a href="https://salmancth.github.io/info" style="color: #00d4ff; text-decoration: none;">portfolio</a></li>
        </ul>
        
        <div style="background: #e8f4f8; padding: 20px; border-radius: 5px; margin: 30px 0;">
            <p style="margin: 0; font-size: 14px; color: #555;">
                <strong>💡 Tip:</strong> If you'd like to discuss a project or collaboration, 
                please include information about timeline, budget, and technical requirements 
                so I can provide you with a more detailed response.
            </p>
        </div>
        
        <p style="margin-top: 30px;">Best regards,<br>
        <strong style="color: #7c3aed; font-size: 18px;">Salman Yahya</strong><br>
        <span style="color: #777; font-size: 14px;">Automation Engineer & Software Developer</span></p>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd; text-align: center;">
            <p style="color: #777; font-size: 14px; margin: 5px 0;">
                📧 <a href="mailto:salman.yahya.soc@outlook.com" style="color: #00d4ff; text-decoration: none;">salman.yahya.soc@outlook.com</a><br>
                📞 <a href="tel:+46761883683" style="color: #00d4ff; text-decoration: none;">+46 76 188 36 83</a><br>
                📍 Gothenburg, Sweden
            </p>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
            <p style="margin: 0; font-size: 12px; color: #856404;">
                ⚠️ This is an automated response. Please do not reply to this email. 
                To contact me directly, use <a href="mailto:salman.yahya.soc@outlook.com" style="color: #856404;">salman.yahya.soc@outlook.com</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}
?>