<?php
// send-email.php
header('Content-Type: application/json');

// Include configuration
require_once __DIR__ . '/config/email.php';

// Set security headers
setSecurityHeaders();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

// Check origin
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!in_array($origin, ALLOWED_ORIGINS)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Forbidden',
        'code' => 'ORIGIN_NOT_ALLOWED'
    ]);
    exit;
}

// Set CORS headers
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Get client IP
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 
      $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
      $_SERVER['REMOTE_ADDR'] ?? '';

// Apply rate limiting
if (!RateLimiter::check($ip)) {
    $remainingTime = RateLimiter::getRemainingTime($ip);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many requests. Please try again later.',
        'retry_after' => $remainingTime,
        'code' => 'RATE_LIMIT_EXCEEDED'
    ]);
    logEvent('Rate limit exceeded', 'WARNING', ['ip' => $ip]);
    exit;
}

// Get and validate input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    $data = $_POST;
}

// Validate required fields
$required = ['name', 'email', 'subject', 'message', 'csrf_token', 'recaptcha_token'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required',
            'code' => 'MISSING_FIELDS',
            'field' => $field
        ]);
        logEvent('Missing required field', 'WARNING', ['field' => $field]);
        exit;
    }
}

// Validate CSRF token
if (ENABLE_CSRF && !CSRFProtection::validateToken($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token',
        'code' => 'INVALID_CSRF_TOKEN'
    ]);
    logEvent('Invalid CSRF token', 'WARNING', ['ip' => $ip]);
    exit;
}

// Verify reCAPTCHA
if (!verifyRecaptcha($data['recaptcha_token'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please complete the security verification',
        'code' => 'INVALID_RECAPTCHA'
    ]);
    logEvent('reCAPTCHA verification failed', 'WARNING', ['ip' => $ip]);
    exit;
}

// Sanitize input
$name = sanitizeInput($data['name']);
$email = sanitizeInput($data['email']);
$subject = sanitizeInput($data['subject']);
$message = sanitizeInput($data['message']);

// Validate email
if (!validateEmail($email)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email address',
        'code' => 'INVALID_EMAIL'
    ]);
    logEvent('Invalid email address', 'WARNING', ['email' => $email]);
    exit;
}

// Validate message length
if (strlen($message) > 5000) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Message is too long',
        'code' => 'MESSAGE_TOO_LONG'
    ]);
    exit;
}

// Prevent email injection
if (preg_match('/(\r|\n)/', $name) || preg_match('/(\r|\n)/', $subject)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input detected',
        'code' => 'INVALID_INPUT'
    ]);
    logEvent('Email injection attempt detected', 'WARNING', ['ip' => $ip]);
    exit;
}

try {
    // Send main email
    $mainEmailSent = sendMainEmail($name, $email, $subject, $message);
    
    // Send auto-reply
    $autoReplySent = sendAutoReply($name, $email, $subject);
    
    if ($mainEmailSent && $autoReplySent) {
        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully'
        ]);
        logEvent('Email sent successfully', 'INFO', [
            'to' => $email,
            'subject' => $subject
        ]);
    } else {
        throw new Exception('Failed to send one or more emails');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email. Please try again later.',
        'code' => 'EMAIL_SEND_FAILED'
    ]);
    logEvent('Email sending failed', 'ERROR', [
        'error' => $e->getMessage(),
        'ip' => $ip,
        'email' => $email
    ]);
}

function sendMainEmail($name, $email, $subject, $message) {
    require_once __DIR__ . '/config/email.php';
    
    $to = RECIPIENT_EMAIL;
    $from = FROM_EMAIL;
    
    $headers = [
        'From' => "Salman Yahya Website <$from>",
        'Reply-To' => "$name <$email>",
        'Return-Path' => $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion(),
        'X-Priority' => '1',
    ];
    
    $emailSubject = "📧 Website Contact: " . substr($subject, 0, 100);
    
    // Create email body using template
    $body = createEmailTemplate($name, $email, $subject, $message, false);
    
    // Use PHPMailer or similar for better SMTP support
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendWithPHPMailer($to, $emailSubject, $body, $headers);
    }
    
    // Fallback to PHP mail()
    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }
    
    return mail($to, $emailSubject, $body, $headerString);
}

function sendAutoReply($name, $email, $subject) {
    require_once __DIR__ . '/config/email.php';
    
    $firstName = explode(' ', $name)[0];
    $isSwedish = detectLanguage($subject);
    
    $template = $isSwedish ? 'swedish' : 'english';
    $emailData = getAutoReplyTemplate($firstName, $subject, $template);
    
    $headers = [
        'From' => "Salman Yahya <" . FROM_EMAIL . ">",
        'Reply-To' => REPLY_TO_EMAIL,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Auto-Response-Suppress' => 'OOF, AutoReply',
    ];
    
    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }
    
    return mail($email, $emailData['subject'], $emailData['body'], $headerString);
}

function detectLanguage($text) {
    $swedishWords = ['tack', 'hej', 'med', 'för', 'att', 'och', 'på', 'i', 'är', 'som'];
    $textLower = strtolower($text);
    
    $swedishCount = 0;
    foreach ($swedishWords as $word) {
        if (strpos($textLower, $word) !== false) {
            $swedishCount++;
        }
    }
    
    return $swedishCount >= 2;
}

function createEmailTemplate($name, $email, $subject, $message, $isAutoReply = false) {
    $template = $isAutoReply ? 'auto-reply' : 'contact';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    if ($template === 'contact') {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Form Submission</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f7fa; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .header p { margin: 10px 0 0; opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .field-group { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eaeaea; }
        .field-label { font-weight: 600; color: #667eea; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .field-value { color: #333; font-size: 16px; }
        .message-box { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; margin: 20px 0; white-space: pre-wrap; }
        .meta-info { background: #f0f7ff; padding: 15px; border-radius: 8px; margin-top: 25px; font-size: 12px; color: #666; }
        .meta-info p { margin: 5px 0; }
        .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; background: #f8f9fa; border-top: 1px solid #eaeaea; }
        .alert { background: #fff3cd; border: 1px solid #ffecb5; color: #856404; padding: 12px; border-radius: 6px; margin: 20px 0; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📨 New Contact Form Submission</h1>
            <p>From salmanyahya.com</p>
        </div>
        
        <div class="content">
            <div class="alert">
                ⚠️ This is an automated message. Please do not reply to this email.
                Reply to: <strong>$email</strong>
            </div>
            
            <div class="field-group">
                <div class="field-label">From</div>
                <div class="field-value">$name</div>
            </div>
            
            <div class="field-group">
                <div class="field-label">Email</div>
                <div class="field-value">$email</div>
            </div>
            
            <div class="field-group">
                <div class="field-label">Subject</div>
                <div class="field-value">$subject</div>
            </div>
            
            <div class="field-group">
                <div class="field-label">Message</div>
                <div class="message-box">$message</div>
            </div>
            
            <div class="meta-info">
                <p><strong>Submission Details:</strong></p>
                <p>Time: $timestamp</p>
                <p>IP Address: $ip</p>
                <p>User Agent: {$_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'}</p>
            </div>
        </div>
        
        <div class="footer">
            <p>This message was automatically generated from the contact form.</p>
            <p>© " . date('Y') . " Salman Yahya. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    return ''; // Auto-reply template handled separately
}

function getAutoReplyTemplate($firstName, $subject, $language = 'english') {
    $templates = [
        'swedish' => [
            'subject' => 'Tack för ditt meddelande – Salman Yahya',
            'body' => getSwedishAutoReplyBody($firstName, $subject)
        ],
        'english' => [
            'subject' => 'Thank you for your message – Salman Yahya',
            'body' => getEnglishAutoReplyBody($firstName, $subject)
        ]
    ];
    
    return $templates[$language] ?? $templates['english'];
}

function getSwedishAutoReplyBody($firstName, $subject) {
    $timestamp = date('Y-m-d H:i:s');
    $year = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f7fa; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; text-align: center; color: white; }
        .logo { font-size: 32px; font-weight: bold; margin-bottom: 10px; letter-spacing: 1px; }
        .tagline { font-size: 16px; opacity: 0.9; }
        .content { padding: 40px; }
        .greeting { color: #2d3748; font-size: 28px; margin-bottom: 20px; }
        .message { color: #4a5568; margin-bottom: 25px; font-size: 16px; }
        .summary { background: #f7fafc; padding: 25px; border-radius: 10px; margin: 30px 0; border-left: 5px solid #667eea; }
        .summary h3 { color: #2d3748; margin-top: 0; font-size: 18px; }
        .summary-item { margin-bottom: 12px; }
        .summary-label { font-weight: 600; color: #4a5568; }
        .summary-value { color: #718096; }
        .tips { background: #f0f9ff; padding: 20px; border-radius: 10px; margin: 25px 0; border: 1px solid #bee3f8; }
        .tip-item { margin-bottom: 10px; color: #2c5282; }
        .cta { text-align: center; margin: 30px 0; }
        .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 0 10px; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .contact-info { background: #fffaf0; padding: 25px; border-radius: 10px; margin: 30px 0; border: 1px solid #feebc8; }
        .footer { text-align: center; padding: 25px; color: #718096; font-size: 12px; background: #f8f9fa; border-top: 1px solid #e2e8f0; }
        .signature { margin-top: 30px; color: #4a5568; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Salman Yahya</div>
            <div class="tagline">Automationsingenjör & Mjukvaruutvecklare</div>
        </div>
        
        <div class="content">
            <h1 class="greeting">Hej $firstName!</h1>
            
            <p class="message">
                Tack så mycket för ditt meddelande! Jag har mottagit din förfrågan och kommer att 
                återkomma till dig så snart som möjligt. Jag strävar efter att svara inom 24 timmar 
                under vardagar.
            </p>
            
            <div class="summary">
                <h3>📋 Din förfrågan:</h3>
                <div class="summary-item">
                    <span class="summary-label">Ämne:</span>
                    <span class="summary-value">$subject</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Skickat:</span>
                    <span class="summary-value">$timestamp</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Referens:</span>
                    <span class="summary-value">#" . substr(md5($subject . $timestamp), 0, 8) . "</span>
                </div>
            </div>
            
            <div class="tips">
                <h3>💡 Under tiden du väntar kan du:</h3>
                <div class="tip-item">• 👨‍💻 Besöka min portfolio för att se mina projekt</div>
                <div class="tip-item">• 📚 Kolla in min GitHub för tekniska exempel</div>
                <div class="tip-item">• 💼 Följa mig på LinkedIn för uppdateringar</div>
            </div>
            
            <div class="cta">
                <a href="https://salmancth.github.io/info" class="btn">Besök Portfolio</a>
                <a href="https://linkedin.com/in/salman-yahya/" class="btn">LinkedIn Profil</a>
            </div>
            
            <div class="contact-info">
                <h3>📞 Mina kontaktuppgifter:</h3>
                <div class="summary-item">
                    <span class="summary-label">E-post för svar:</span>
                    <span class="summary-value">salman.yahya.soc@outlook.com</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Telefon:</span>
                    <span class="summary-value">+46 76 188 36 83</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Plats:</span>
                    <span class="summary-value">Västra Frölunda, Göteborg</span>
                </div>
            </div>
            
            <div class="signature">
                <p>Med vänliga hälsningar,<br>
                <strong>Salman Yahya</strong><br>
                <em>Automationsingenjör & Mjukvaruutvecklare</em></p>
            </div>
        </div>
        
        <div class="footer">
            <p>Detta är ett automatiskt svar från Salman Yahya's kontaktformulär.</p>
            <p>Om du inte har skickat något meddelande, vänligen ignorera detta mail.</p>
            <p>© $year Salman Yahya. Alla rättigheter förbehållna.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

function getEnglishAutoReplyBody($firstName, $subject) {
    $timestamp = date('Y-m-d H:i:s');
    $year = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f7fa; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; text-align: center; color: white; }
        .logo { font-size: 32px; font-weight: bold; margin-bottom: 10px; letter-spacing: 1px; }
        .tagline { font-size: 16px; opacity: 0.9; }
        .content { padding: 40px; }
        .greeting { color: #2d3748; font-size: 28px; margin-bottom: 20px; }
        .message { color: #4a5568; margin-bottom: 25px; font-size: 16px; }
        .summary { background: #f7fafc; padding: 25px; border-radius: 10px; margin: 30px 0; border-left: 5px solid #667eea; }
        .summary h3 { color: #2d3748; margin-top: 0; font-size: 18px; }
        .summary-item { margin-bottom: 12px; }
        .summary-label { font-weight: 600; color: #4a5568; }
        .summary-value { color: #718096; }
        .tips { background: #f0f9ff; padding: 20px; border-radius: 10px; margin: 25px 0; border: 1px solid #bee3f8; }
        .tip-item { margin-bottom: 10px; color: #2c5282; }
        .cta { text-align: center; margin: 30px 0; }
        .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 0 10px; transition: transform 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .contact-info { background: #fffaf0; padding: 25px; border-radius: 10px; margin: 30px 0; border: 1px solid #feebc8; }
        .footer { text-align: center; padding: 25px; color: #718096; font-size: 12px; background: #f8f9fa; border-top: 1px solid #e2e8f0; }
        .signature { margin-top: 30px; color: #4a5568; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Salman Yahya</div>
            <div class="tagline">Automation Engineer & Software Developer</div>
        </div>
        
        <div class="content">
            <h1 class="greeting">Hello $firstName!</h1>
            
            <p class="message">
                Thank you for your message! I have received your inquiry and will get back to you 
                as soon as possible. I aim to respond within 24 hours on business days.
            </p>
            
            <div class="summary">
                <h3>📋 Your Inquiry:</h3>
                <div class="summary-item">
                    <span class="summary-label">Subject:</span>
                    <span class="summary-value">$subject</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Sent:</span>
                    <span class="summary-value">$timestamp</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Reference:</span>
                    <span class="summary-value">#" . substr(md5($subject . $timestamp), 0, 8) . "</span>
                </div>
            </div>
            
            <div class="tips">
                <h3>💡 While you're waiting, you can:</h3>
                <div class="tip-item">• 👨‍💻 Visit my portfolio to see my projects</div>
                <div class="tip-item">• 📚 Check out my GitHub for technical examples</div>
                <div class="tip-item">• 💼 Follow me on LinkedIn for updates</div>
            </div>
            
            <div class="cta">
                <a href="https://salmancth.github.io/info" class="btn">Visit Portfolio</a>
                <a href="https://linkedin.com/in/salman-yahya/" class="btn">LinkedIn Profile</a>
            </div>
            
            <div class="contact-info">
                <h3>📞 My Contact Details:</h3>
                <div class="summary-item">
                    <span class="summary-label">Reply email:</span>
                    <span class="summary-value">salman.yahya.soc@outlook.com</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Phone:</span>
                    <span class="summary-value">+46 76 188 36 83</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Location:</span>
                    <span class="summary-value">Västra Frölunda, Gothenburg</span>
                </div>
            </div>
            
            <div class="signature">
                <p>Best regards,<br>
                <strong>Salman Yahya</strong><br>
                <em>Automation Engineer & Software Developer</em></p>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated response from Salman Yahya's contact form.</p>
            <p>If you did not send any message, please ignore this email.</p>
            <p>© $year Salman Yahya. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

function sendWithPHPMailer($to, $subject, $body, $headers) {
    // Optional: Implement PHPMailer for better SMTP support
    // This requires installing PHPMailer via composer
    return false;
}
?>