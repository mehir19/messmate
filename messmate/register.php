<?php
require 'db.php';
require 'otp_helper.php';
date_default_timezone_set('Asia/Dhaka');

if (isset($_SESSION['user_id'], $_SESSION['mess_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$info  = '';

$pending = $_SESSION['pending_reg'] ?? [
    'mess_name'    => '',
    'manager_name' => '',
    'email'        => '',
    'phone'        => '',
    'channel'      => 'email',
    'password'     => '',
];

$step = !empty($_SESSION['pending_reg']) ? 'verify' : 'details';

if (isset($_GET['reset'])) {
    unset($_SESSION['pending_reg']);
    header("Location: register.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['reg_action'] ?? 'send_otp';

    if ($postAction === 'send_otp') {
        $messName    = trim($_POST['mess_name'] ?? '');
        $managerName = trim($_POST['manager_name'] ?? '');
        $email       = strtolower(trim($_POST['email'] ?? ''));
        $phone       = trim($_POST['phone'] ?? '');
        $channel     = ($_POST['otp_channel'] ?? 'email') === 'phone' ? 'phone' : 'email';
        $password    = $_POST['password'] ?? '';

        $pending = [
            'mess_name'    => $messName,
            'manager_name' => $managerName,
            'email'        => $email,
            'phone'        => $phone,
            'channel'      => $channel,
            'password'     => $password,
        ];

        if ($messName === '' || $managerName === '' || $password === '') {
            $error = "Please fill in Mess Name, Manager Name, and Password.";
        } elseif (strlen($password) < 4) {
            $error = "Password / PIN must be at least 4 characters.";
        } elseif ($channel === 'email') {
            $emailCheck = isValidEmailAddress($email);
            if (!$emailCheck['valid']) {
                $error = $emailCheck['reason'];
            } else {
                $dupStmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
                $dupStmt->execute([$email]);
                if ($dupStmt->fetch()) {
                    $error = "An account with this email already exists.";
                }
            }
        } else {
            $cleanPhone = normalizePhoneNumber($phone);
            if ($cleanPhone === '' || strlen($cleanPhone) < 10) {
                $error = "Please enter a valid phone number to receive SMS OTP.";
            }
        }

        if ($error === '') {
            $recipient = $channel === 'email' ? $email : normalizePhoneNumber($phone);
            $code = createVerificationCode($pdo, $channel, $recipient, 10);

            $sent = ($channel === 'email')
                ? sendEmailOtp($email, $code)
                : sendPhoneOtp($phone, $code);

            $_SESSION['pending_reg'] = $pending;
            $step = 'verify';

            if ($sent) {
                $info = "A 6-digit verification code was sent via " . strtoupper($channel) . " to " . e($recipient) . ".";
            } else {
                // Helpful fallback on local dev servers where mail/SMS isn't configured
                $info = "Verification code generated for " . e($recipient) . " (local dev code: " . e($code) . ").";
            }
        }
    } elseif ($postAction === 'verify_and_register') {
        $step = 'verify';
        $otpCode = trim($_POST['otp_code'] ?? '');

        if (empty($_SESSION['pending_reg'])) {
            $error = "Registration session expired. Please enter your details again.";
            $step = 'details';
        } elseif ($otpCode === '') {
            $error = "Please enter the 6-digit verification code.";
        } else {
            $data = $_SESSION['pending_reg'];
            $recipient = $data['channel'] === 'email'
                ? $data['email']
                : normalizePhoneNumber($data['phone']);

            if (!verifyOtpCode($pdo, $data['channel'], $recipient, $otpCode)) {
                $error = "Invalid or expired verification code.";
            } else {
                try {
                    $pdo->beginTransaction();

                    $messCode = 'MESS-' . random_int(1000, 9999);
                    $trialEndsAt = date('Y-m-d H:i:s', strtotime('+14 days'));

                    $messIns = $pdo->prepare("
                        INSERT INTO messes (mess_name, mess_code, subscription_plan, subscription_status, trial_ends_at)
                        VALUES (?, ?, 'free_trial', 'active', ?)
                    ");
                    $messIns->execute([$data['mess_name'], $messCode, $trialEndsAt]);
                    $newMessId = (int)$pdo->lastInsertId();

                    $userIns = $pdo->prepare("
                        INSERT INTO users (mess_id, member_code, full_name, email, password_hash, role, is_active)
                        VALUES (?, 'M-001', ?, ?, ?, 'manager', 1)
                    ");
                    $userIns->execute([
                        $newMessId,
                        $data['manager_name'],
                        $data['email'] !== '' ? $data['email'] : null,
                        password_hash($data['password'], PASSWORD_DEFAULT),
                    ]);
                    $newUserId = (int)$pdo->lastInsertId();

                    $pdo->commit();
                    unset($_SESSION['pending_reg']);

                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['mess_id'] = $newMessId;
                    $_SESSION['role']    = 'manager';

                    header("Location: dashboard.php");
                    exit;
                } catch (Throwable $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Could not complete registration: " . $ex->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Mess — MessMate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100 py-4">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="bg-success text-white rounded-3 d-flex align-items-center justify-content-center fw-bold fs-4" style="width:44px;height:44px;">M</div>
                    <div>
                        <h5 class="fw-bold mb-0">Create Your Mess</h5>
                        <small class="text-muted">Verify via Email or Phone OTP</small>
                    </div>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2 small fw-semibold"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($info !== ''): ?>
                    <div class="alert alert-info py-2 small fw-semibold"><?= $info ?></div>
                <?php endif; ?>

                <?php if ($step === 'details'): ?>
                    <form method="POST">
                        <input type="hidden" name="reg_action" value="send_otp">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">Mess Name</label>
                            <input type="text" name="mess_name" value="<?= e($pending['mess_name']) ?>" class="form-control" required placeholder="e.g., Green Hostel Mess">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">Manager Name</label>
                            <input type="text" name="manager_name" value="<?= e($pending['manager_name']) ?>" class="form-control" required placeholder="Your full name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">Email Address</label>
                            <input type="email" name="email" value="<?= e($pending['email']) ?>" class="form-control" placeholder="manager@example.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">Phone Number</label>
                            <input type="text" name="phone" value="<?= e($pending['phone']) ?>" class="form-control" placeholder="017XXXXXXXX">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">Send OTP Via</label>
                            <select name="otp_channel" class="form-select">
                                <option value="email" <?= ($pending['channel'] ?? 'email') === 'email' ? 'selected' : '' ?>>Email OTP</option>
                                <option value="phone" <?= ($pending['channel'] ?? '') === 'phone' ? 'selected' : '' ?>>Phone SMS OTP</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">Password / PIN</label>
                            <input type="password" name="password" class="form-control" required placeholder="At least 4 characters">
                        </div>

                        <button type="submit" class="btn btn-success w-100 fw-semibold py-2">Send Verification Code</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="reg_action" value="verify_and_register">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">6-Digit Verification Code</label>
                            <input type="text" name="otp_code" maxlength="6" pattern="\d{6}" required
                                   placeholder="123456" class="form-control form-control-lg text-center font-monospace fw-bold">
                        </div>

                        <button type="submit" class="btn btn-success w-100 fw-semibold py-2 mb-2">Verify &amp; Create Mess</button>
                        <a href="register.php?reset=1" class="btn btn-outline-secondary btn-sm w-100">Change Details / Resend</a>
                    </form>
                <?php endif; ?>

                <p class="text-center text-muted small mt-4 mb-0">
                    Already have a mess? <a href="login.php" class="text-success fw-semibold text-decoration-none">Sign in</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>