<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clean_text(string $value, bool $keepNewlines = false): string
{
    $pattern = $keepNewlines
        ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'
        : '/[\x00-\x1F\x7F]/u';
    $cleaned = preg_replace($pattern, '', $value);
    return trim(str_replace(["\r\n", "\r"], "\n", $cleaned ?? ''));
}

function allow_submission(string $ipAddress): bool
{
    $filePath = sys_get_temp_dir() . '/swe-contact-' . hash('sha256', __DIR__ . '|' . $ipAddress) . '.json';
    $file = @fopen($filePath, 'c+');
    if ($file === false || !flock($file, LOCK_EX)) {
        if (is_resource($file)) {
            fclose($file);
        }
        return false;
    }

    @chmod($filePath, 0600);
    $contents = stream_get_contents($file);
    $state = $contents === false ? [] : json_decode($contents, true);
    if (!is_array($state) || !isset($state['started'], $state['count']) || time() - (int) $state['started'] >= 900) {
        $state = ['started' => time(), 'count' => 0];
    }

    if ((int) $state['count'] >= 5) {
        flock($file, LOCK_UN);
        fclose($file);
        return false;
    }

    $state['count'] = (int) $state['count'] + 1;
    $encoded = json_encode($state);
    $saved = $encoded !== false && rewind($file) && ftruncate($file, 0)
        && fwrite($file, $encoded) === strlen($encoded) && fflush($file);
    flock($file, LOCK_UN);
    fclose($file);

    return $saved;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'This method is not supported.']);
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType !== 'application/json') {
    respond(415, ['error' => 'Please submit the contact form using this page.']);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 12000) {
    respond(413, ['error' => 'Your message is too long.']);
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false || strlen($rawInput) > 12000) {
    respond(413, ['error' => 'Your message is too long.']);
}

$input = json_decode($rawInput, true);
if (!is_array($input)) {
    respond(400, ['error' => 'Please submit a valid message.']);
}

$honeypot = isset($input['website']) && is_string($input['website']) ? trim($input['website']) : '';
if ($honeypot !== '') {
    respond(400, ['error' => 'Please submit a valid message.']);
}

$name = isset($input['name']) && is_string($input['name']) ? clean_text($input['name']) : '';
$email = isset($input['email']) && is_string($input['email']) ? clean_text($input['email']) : '';
$subject = isset($input['subject']) && is_string($input['subject']) ? clean_text($input['subject']) : '';
$message = isset($input['message']) && is_string($input['message']) ? clean_text($input['message'], true) : '';

if ($name === '' || strlen($name) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || strlen($email) > 254 || $subject === '' || strlen($subject) > 160
    || $message === '' || strlen($message) > 6000) {
    respond(422, ['error' => 'Please check your name, email, subject, and message, then try again.']);
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!allow_submission($ipAddress)) {
    respond(429, ['error' => 'Too many messages were sent from this connection. Please try again later.']);
}

$smtpUsername = getenv('SWE_SMTP_USERNAME') ?: '';
$smtpPassword = getenv('SWE_SMTP_PASSWORD') ?: '';
if ($smtpUsername === '' || $smtpPassword === '' || filter_var($smtpUsername, FILTER_VALIDATE_EMAIL) === false) {
    error_log('Contact form is not configured: SMTP environment variables are missing or invalid.');
    respond(503, ['error' => 'The contact form is temporarily unavailable. Please try again later.']);
}

$mailer = new PHPMailer(true);

try {
    $mailer->isSMTP();
    $mailer->Host = 'smtp.gmail.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = $smtpUsername;
    $mailer->Password = $smtpPassword;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = 587;
    $mailer->Timeout = 10;
    $mailer->SMTPDebug = 0;
    $mailer->CharSet = PHPMailer::CHARSET_UTF8;
    $mailer->setFrom($smtpUsername, 'Website Contact Form');
    $mailer->addAddress('steamcleaningsp@gmail.com');
    $mailer->addReplyTo($email, $name);
    $mailer->isHTML(false);
    $mailer->Subject = 'Website contact form: ' . $subject;
    $mailer->Body = "Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage:\n{$message}";
    $mailer->send();
    respond(200, ['message' => 'Message has been sent.']);
} catch (Exception $error) {
    error_log('Contact form delivery failed: ' . $mailer->ErrorInfo);
    respond(502, ['error' => 'Your message could not be sent right now. Please try again later.']);
} catch (Throwable $error) {
    error_log('Contact form delivery failed: ' . $error->getMessage());
    respond(502, ['error' => 'Your message could not be sent right now. Please try again later.']);
}
