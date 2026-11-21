<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$DB_HOST = '127.0.0.1';
$DB_NAME = 'messmate_db';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function checkSubscriptionActive(array $mess): array {
    $now = time();
    if ($mess['subscription_plan'] === 'free_trial') {
        $expiry = strtotime($mess['trial_ends_at'] ?? '');
        $daysLeft = max(0, (int)ceil(($expiry - $now) / 86400));
        return ['active' => ($expiry > $now), 'label' => "Free Trial ({$daysLeft}d left)"];
    }
    $expiry = strtotime($mess['subscription_ends_at'] ?? '');
    $daysLeft = max(0, (int)ceil(($expiry - $now) / 86400));
    return ['active' => ($mess['subscription_status'] === 'active' && $expiry > $now), 'label' => ucfirst($mess['subscription_plan']) . " Plan ({$daysLeft}d left)"];
}