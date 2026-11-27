<?php
date_default_timezone_set('Asia/Dhaka');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/phpmailer/src/Exception.php';
    require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/phpmailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function isValidEmailAddress(string $email): array {
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'reason' => 'Please enter a valid email address.'];
    }

    $domain = substr(strrchr($email, '@'), 1) ?: '';
    $disposableDomains = [
        'mailinator.com',
        'tempmail.com',
        '10minutemail.com',
        'guerrillamail.com',
        'yopmail.com',
        'trashmail.com',
        'getnada.com',
    ];

    if (in_array($domain, $disposableDomains, true)) {
        return ['valid' => false, 'reason' => 'Disposable or temporary email addresses are not allowed.'];
    }

    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return ['valid' => false, 'reason' => 'Email domain could not be verified for mail delivery.'];
    }

    return ['valid' => true, 'reason' => 'OK'];
}

function normalizePhoneNumber(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null) {
        return '';
    }
    if (strlen($digits) === 11 && str_starts_with($digits, '01')) {
        return '880' . substr($digits, 1);
    }
    return $digits;
}

function createVerificationCode(PDO $pdo, string $channel, string $recipient, int $ttlMinutes = 10): string {
    $channel   = $channel === 'phone' ? 'phone' : 'email';
    $recipient = $channel === 'email' ? strtolower(trim($recipient)) : normalizePhoneNumber($recipient);
    $code      = (string)random_int(100000, 999999);
    $codeHash  = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

    $stmt = $pdo->prepare("
        INSERT INTO verification_codes (channel, recipient, code_hash, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$channel, $recipient, $codeHash, $expiresAt]);

    return $code;
}

function sendEmailOtp(string $toEmail, string $code): bool {
    $toEmail = strtolower(trim($toEmail));
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Replace with your live SMTP credentials
    $smtpHost  = 'smtp.gmail.com';
    $smtpPort  = 587;
    $smtpUser  = 'your-smtp-email@example.com';
    $smtpPass  = 'your-smtp-app-password';
    $fromEmail = 'noreply@codepedant.net';
    $fromName  = 'MessMate';

    if (!class_exists(PHPMailer::class)) {
        error_log("PHPMailer not found; cannot send SMTP OTP to {$toEmail}");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->Port       = $smtpPort;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);

        $mail->isHTML(false);
        $mail->Subject = "MessMate Verification Code: {$code}";
        $mail->Body    = "Your MessMate verification code is {$code}.\nThis code expires in 10 minutes.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("SMTP OTP error for {$toEmail}: " . $mail->ErrorInfo);
        return false;
    }
}

function sendPhoneOtp(string $phoneNumber, string $code): bool {
    $cleanPhone = normalizePhoneNumber($phoneNumber);
    if ($cleanPhone === '' || strlen($cleanPhone) < 10) {
        return false;
    }

    $smsText = "MessMate OTP: {$code} (valid for 10 mins).";

    // Connect your SMS gateway HTTP request here
    return strlen($smsText) > 0;
}

function verifyOtpCode(PDO $pdo, string $channel, string $recipient, string $submittedCode): bool {
    $channel   = $channel === 'phone' ? 'phone' : 'email';
    $recipient = $channel === 'email' ? strtolower(trim($recipient)) : normalizePhoneNumber($recipient);
    $now       = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT id, code_hash
        FROM verification_codes
        WHERE channel = ?
          AND recipient = ?
          AND verified_at IS NULL
          AND expires_at >= ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$channel, $recipient, $now]);
    $row = $stmt->fetch();

    if (!$row || !password_verify(trim($submittedCode), $row['code_hash'])) {
        return false;
    }

    $upd = $pdo->prepare("UPDATE verification_codes SET verified_at = ? WHERE id = ?");
    $upd->execute([$now, (int)$row['id']]);

    return true;
}
