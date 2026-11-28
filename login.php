<?php
require 'db.php';

$error = '';
$activeLoginTab = 'manager';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = $_POST['login_type'] ?? 'manager';
    $activeLoginTab = in_array($loginType, ['manager', 'member', 'cook'], true) ? $loginType : 'manager';

    if ($loginType === 'manager') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($_SESSION['cook_mess_id']);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['mess_id'] = (int)$user['mess_id'];
            $_SESSION['role']    = $user['role'];
            header("Location: dashboard.php");
            exit;
        }
        $error = "Invalid email or password.";
    } elseif ($loginType === 'cook') {
        $messCode = strtoupper(trim($_POST['mess_code'] ?? ''));
        $cookPin  = trim($_POST['cook_pin'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM messes WHERE UPPER(mess_code) = ? LIMIT 1");
        $stmt->execute([$messCode]);
        $mess = $stmt->fetch();

        $expectedPin = (string)($mess['cook_pin'] ?? '1111');
        if ($mess && hash_equals($expectedPin, $cookPin)) {
            unset($_SESSION['user_id'], $_SESSION['mess_id'], $_SESSION['role']);
            $_SESSION['cook_mess_id'] = (int)$mess['id'];
            header("Location: cooking.php");
            exit;
        }
        $error = "Invalid Mess Code or Cook PIN.";
    } else {
        $messCode   = strtoupper(trim($_POST['mess_code'] ?? ''));
        $memberCode = strtoupper(trim($_POST['member_code'] ?? ''));
        $password   = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT u.* FROM users u JOIN messes m ON u.mess_id = m.id WHERE m.mess_code = ? AND u.member_code = ? AND u.is_active = 1 LIMIT 1");
        $stmt->execute([$messCode, $memberCode]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($_SESSION['cook_mess_id']);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['mess_id'] = (int)$user['mess_id'];
            $_SESSION['role']    = $user['role'];
            header("Location: dashboard.php");
            exit;
        }
        $error = "Invalid Mess Code, Member ID, or PIN.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — MessMate SaaS</title>
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
                        <h5 class="fw-bold mb-0">Sign in to MessMate</h5>
                        <small class="text-muted">Hostel & Shared Living Accounting</small>
                    </div>
                </div>

                <ul class="nav nav-pills nav-fill bg-light p-1 rounded-3 mb-4" id="loginTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeLoginTab === 'manager' ? 'active' : '' ?> fw-semibold small" data-bs-toggle="pill" data-bs-target="#mgrPane" type="button">Manager Login</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeLoginTab === 'member' ? 'active' : '' ?> fw-semibold small" data-bs-toggle="pill" data-bs-target="#memPane" type="button">Member ID Login</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeLoginTab === 'cook' ? 'active' : '' ?> fw-semibold small" data-bs-toggle="pill" data-bs-target="#cookPane" type="button">Cook Login</button>
                    </li>
                </ul>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <div class="tab-content">
                    <!-- Manager Tab -->
                    <div class="tab-pane fade <?= $activeLoginTab === 'manager' ? 'show active' : '' ?>" id="mgrPane">
                        <form method="POST">
                            <input type="hidden" name="login_type" value="manager">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-uppercase text-secondary">Manager Email</label>
                                <input type="email" name="email" class="form-control" required placeholder="manager@example.com">
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-uppercase text-secondary">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100 fw-semibold py-2">Sign In as Manager</button>
                        </form>
                    </div>

                    <!-- Member Tab -->
                    <div class="tab-pane fade <?= $activeLoginTab === 'member' ? 'show active' : '' ?>" id="memPane">
                        <form method="POST">
                            <input type="hidden" name="login_type" value="member">
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-uppercase text-secondary">Mess Code</label>
                                    <input type="text" name="mess_code" class="form-control" required placeholder="MESS-8511" value="MESS-">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold text-uppercase text-secondary">Member ID</label>
                                    <input type="text" name="member_code" class="form-control" required placeholder="M-102" value="M-">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-uppercase text-secondary">Password / PIN</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 fw-semibold py-2">Sign In as Member</button>
                        </form>
                    </div>

                    <!-- Cook Tab -->
                    <div class="tab-pane fade <?= $activeLoginTab === 'cook' ? 'show active' : '' ?>" id="cookPane">
                        <form method="POST">
                            <input type="hidden" name="login_type" value="cook">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-uppercase text-secondary">Mess Code</label>
                                <input type="text" name="mess_code" class="form-control" required placeholder="MESS-4821" value="MESS-">
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-uppercase text-secondary">Cook PIN</label>
                                <input type="password" name="cook_pin" class="form-control" required placeholder="Enter Cook PIN">
                            </div>
                            <button type="submit" class="btn btn-warning w-100 fw-semibold py-2 text-dark">Sign In as Cook</button>
                        </form>
                    </div>
                </div>

                <p class="text-center text-muted small mt-4 mb-0">
                    Starting a new mess? <a href="register.php" class="text-success fw-semibold text-decoration-none">Register free</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>