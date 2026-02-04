<?php
// config/email.php
// NEVER commit this file to version control
// Add this file to .gitignore

// SMTP Configuration
define('SMTP_HOST', 'mail.salmanyahya.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'noreply@salmanyahya.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'Salman@162345');
define('SMTP_ENCRYPTION', 'ssl');

// Email Addresses
define('RECIPIENT_EMAIL', 'kontakt@salmanyahya.com');
define('FROM_EMAIL', 'noreply@salmanyahya.com');
define('REPLY_TO_EMAIL', 'salman.yahya.soc@outlook.com');

// reCAPTCHA Configuration
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '6LdJ1WAsAAAAABo_E11dQYR7WCVUR3mcqOcoBc2o');
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '6LdJ1WAsAAAAAFOECIxb8pH7fPvf1IglV59i0OWD');

// Rate Limiting Configuration (in seconds)
define('RATE_LIMIT_WINDOW', 3600); // 1 hour
define('RATE_LIMIT_MAX_REQUESTS', 5); // 5 requests per hour per IP

// Security Settings
define('ENABLE_CSRF', true);
define('ALLOWED_ORIGINS', [
    'https://salmanyahya.com',
    'https://www.salmanyahya.com',
    'http://localhost',
    'http://127.0.0.1'
]);

// Logging Configuration
define('LOG_ENABLED', true);
define('LOG_FILE', __DIR__ . '/../logs/email.log');

// Create logs directory if it doesn't exist
if (LOG_ENABLED && !file_exists(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

// Security Headers
function setSecurityHeaders()
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://www.google.com https://www.gstatic.com; style-src \'self\' \'unsafe-inline\';');
}

// Rate Limiting Function
class RateLimiter
{
    private static $storagePath = __DIR__ . '/../data/ratelimit/';

    public static function check($ip)
    {
        if (!file_exists(self::$storagePath)) {
            mkdir(self::$storagePath, 0755, true);
        }

        $ipHash = hash('sha256', $ip);
        $file = self::$storagePath . $ipHash . '.json';

        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }

        $now = time();
        $windowStart = $now - RATE_LIMIT_WINDOW;

        // Clean old requests
        if (isset($data['requests'])) {
            $data['requests'] = array_filter($data['requests'], function ($timestamp) use ($windowStart) {
                return $timestamp > $windowStart;
            });
        } else {
            $data['requests'] = [];
        }

        // Check if limit exceeded
        if (count($data['requests']) >= RATE_LIMIT_MAX_REQUESTS) {
            return false;
        }

        // Add current request
        $data['requests'][] = $now;
        $data['last_request'] = $now;

        // Save data
        file_put_contents($file, json_encode($data));

        return true;
    }

    public static function getRemainingTime($ip)
    {
        $ipHash = hash('sha256', $ip);
        $file = self::$storagePath . $ipHash . '.json';

        if (!file_exists($file)) {
            return 0;
        }

        $data = json_decode(file_get_contents($file), true);

        if (empty($data['requests'])) {
            return 0;
        }

        $oldestRequest = min($data['requests']);
        $nextAllowed = $oldestRequest + RATE_LIMIT_WINDOW;

        return max(0, $nextAllowed - time());
    }
}

// Logging Function
function logEvent($message, $level = 'INFO', $data = [])
{
    if (!LOG_ENABLED) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf(
        "[%s] [%s] %s %s\n",
        $timestamp,
        $level,
        $message,
        !empty($data) ? json_encode($data) : ''
    );

    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

// CSRF Token Generation
class CSRFProtection
{
    private static $tokenName = 'csrf_token';

    public static function generateToken()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::$tokenName])) {
            $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::$tokenName];
    }

    public static function validateToken($token)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::$tokenName]) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION[self::$tokenName], $token);
    }
}

// Input Sanitization
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $data;
}

// Email Validation
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) &&
        preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

// reCAPTCHA Validation
function verifyRecaptcha($token)
{
    if (empty($token)) {
        return false;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $token
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        logEvent('reCAPTCHA verification failed: Network error', 'ERROR');
        return false;
    }

    $result = json_decode($response);

    if (!$result || !isset($result->success)) {
        logEvent('reCAPTCHA verification failed: Invalid response', 'ERROR', ['response' => $response]);
        return false;
    }

    // Optional: Check score for reCAPTCHA v3
    if (isset($result->score) && $result->score < 0.5) {
        logEvent('reCAPTCHA score too low', 'WARNING', ['score' => $result->score]);
        return false;
    }

    return $result->success;
}
?>