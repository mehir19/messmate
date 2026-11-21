<?php
require 'db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messName = trim($_POST['mess_name'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $currency = trim($_POST['currency'] ?? '৳');
    $password = $_POST['password'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        try {
            $pdo->beginTransaction();
            $messCode = 'MESS-' . random_int(1000, 9999);
            $trialEnd = date('Y-m-d H:i:s', strtotime('+14 days'));

            $stmt = $pdo->prepare("INSERT INTO messes (mess_code, mess_name, currency_symbol, subscription_plan, subscription_status, trial_ends_at) VALUES (?, ?, ?, 'free_trial', 'active', ?)");
            $stmt->execute([$messCode, $messName, $currency, $trialEnd]);
            $messId = (int)$pdo->lastInsertId();

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtUser = $pdo->prepare("INSERT INTO users (mess_id, member_code, full_name, email, password_hash, role) VALUES (?, 'M-101', ?, ?, ?, 'manager')");
            $stmtUser->execute([$messId, $fullName, $email, $hash]);
            $userId = (int)$pdo->lastInsertId();

            $pdo->commit();
            $_SESSION['user_id'] = $userId;
            $_SESSION['mess_id'] = $messId;
            $_SESSION['role']    = 'manager';
            header("Location: dashboard.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Registration failed. Email may already be registered.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Mess — MessMate SaaS</title>
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
                        <h5 class="fw-bold mb-0">Create Mess Workspace</h5>
                        <small class="text-muted">14-day free trial • No credit card required</small>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-uppercase text-secondary">Hostel / Mess Name</label>
                        <input type="text" name="mess_name" class="form-control" required placeholder="e.g., Green View Bachelor Mess">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">Manager Name</label>
                            <input type="text" name="full_name" class="form-control" required placeholder="Tariqul Islam">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold text-uppercase text-secondary">Currency</label>
                            <select name="currency" class="form-select">
                                <option value="৳">৳ (BDT)</option>
                                <option value="₹">₹ (INR)</option>
                                <option value="$">$ (USD)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-uppercase text-secondary">Manager Email</label>
                        <input type="email" name="email" class="form-control" required placeholder="manager@example.com">
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-uppercase text-secondary">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-semibold py-2">Launch Mess Dashboard</button>
                </form>

                <p class="text-center text-muted small mt-4 mb-0">
                    Already registered? <a href="login.php" class="text-success fw-semibold text-decoration-none">Sign in here</a>
                </p>
            </div>
        </div>
    </div>
</div>
</body>
</html>