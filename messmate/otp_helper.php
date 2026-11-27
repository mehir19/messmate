<?php
date_default_timezone_set('Asia/Dhaka');

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
    // Convert 11-digit BD mobile (01XXXXXXXXX) to 8801XXXXXXXXX
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
    $subject = "MessMate Verification Code: {$code}";
    $body    = "Your MessMate verification code is {$code}.\nThis code expires in 10 minutes.";
    $headers = "From: noreply@messmate.local\r\nContent-Type: text/plain; charset=UTF-8";

    // Use PHPMailer + SMTP in production for reliable delivery
    return @mail($toEmail, $subject, $body, $headers);
}

function sendPhoneOtp(string $phoneNumber, string $code): bool {
    $cleanPhone = normalizePhoneNumber($phoneNumber);
    if ($cleanPhone === '' || strlen($cleanPhone) < 10) {
        return false;
    }

    $smsText = "MessMate OTP: {$code} (valid for 10 mins).";

    // Connect your SMS gateway API here (e.g., BulkSMSBD, SSLWireless, or Twilio)
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