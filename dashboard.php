<?php
require 'db.php';

if (!isset($_SESSION['user_id'], $_SESSION['mess_id'])) {
    header("Location: login.php");
    exit;
}

if (!function_exists('fmtMoney')) {
    function fmtMoney(string $currencyCode, float $amount): string {
        $symbols = [
            'BDT' => '৳',
            '৳'   => '৳',
            'INR' => '₹',
            '₹'   => '₹',
            'USD' => '$',
            '$'   => '$',
        ];
        $sym = $symbols[$currencyCode] ?? $currencyCode;
        return $sym . number_format($amount, 2);
    }
}

$messId       = (int)$_SESSION['mess_id'];
$currentUid   = (int)$_SESSION['user_id'];
$role         = $_SESSION['role'];
$billingMonth = $_GET['month'] ?? date('Y-m');
$prevMonth    = date('Y-m', strtotime($billingMonth . '-01 -1 month'));
$today        = date('Y-m-d');
$activeTab    = $_GET['tab'] ?? 'overview';
$flash        = '';
$flashError   = '';

// Fetch Mess & Subscription
$stmt = $pdo->prepare("SELECT * FROM messes WHERE id = ?");
$stmt->execute([$messId]);
$mess = $stmt->fetch();
$subInfo = checkSubscriptionActive($mess);
$cur = (string)($mess['currency_symbol'] ?? 'BDT');

// Helper to compute full monthly financial summary for any given YYYY-MM
function getMonthSummary(PDO $pdo, int $messId, string $month, array $members): array {
    $memberCount = max(1, count($members));

    // Manager's carried-forward previous balance (if any)
    $managerPrevBal = 0.0;
    foreach ($members as $m) {
        if (($m['role'] ?? '') === 'manager') {
            $managerPrevBal += (float)($m['previous_balance'] ?? 0.0);
        }
    }

    // Approved Expenses
    // total_grocery includes both manager_cash and member_pocket grocery (for Cost Per Meal).
    // member_pocket grocery also counts as member own-shopping credit in Funds.
    $expStmt = $pdo->prepare("SELECT * FROM expenses WHERE mess_id = ? AND billing_month = ? AND status = 'approved'");
    $expStmt->execute([$messId, $month]);
    $totalGrocery = 0.0;
    $managerCashGrocery = 0.0;
    $memberPocketGroceryTotal = 0.0;
    $totalMisc = 0.0;
    $personalGrocery = [];
    foreach ($expStmt->fetchAll() as $ex) {
        $amt = (float)$ex['amount'];
        $source = $ex['funding_source'] ?? 'member_pocket';
        if ($ex['category'] === 'grocery') {
            $totalGrocery += $amt;
            if ($source === 'member_pocket') {
                $memberPocketGroceryTotal += $amt;
                $payerId = (int)$ex['paid_by_user_id'];
                $personalGrocery[$payerId] = ($personalGrocery[$payerId] ?? 0.0) + $amt;
            } else {
                $managerCashGrocery += $amt;
            }
        } else {
            $totalMisc += $amt;
        }
    }

    // Deposits & Corrections
    $depStmt = $pdo->prepare("SELECT user_id, SUM(amount) AS total_dep FROM deposits WHERE mess_id = ? AND billing_month = ? GROUP BY user_id");
    $depStmt->execute([$messId, $month]);
    $memberDeposits = [];
    $totalDeposits = 0.0;
    foreach ($depStmt->fetchAll() as $dp) {
        $memberDeposits[$dp['user_id']] = (float)$dp['total_dep'];
        $totalDeposits += (float)$dp['total_dep'];
    }

    // Meals
    $mealStmt = $pdo->prepare("SELECT user_id, SUM(breakfast + lunch + dinner) AS total_meals FROM meals WHERE mess_id = ? AND DATE_FORMAT(meal_date, '%Y-%m') = ? GROUP BY user_id");
    $mealStmt->execute([$messId, $month]);
    $memberMeals = [];
    $totalMeals = 0.0;
    foreach ($mealStmt->fetchAll() as $ml) {
        $memberMeals[$ml['user_id']] = (float)$ml['total_meals'];
        $totalMeals += (float)$ml['total_meals'];
    }

    $costPerMeal = $totalMeals > 0 ? ($totalGrocery / $totalMeals) : 0.0;
    $miscPerMember = $totalMisc / $memberCount;

    $perMember = [];
    foreach ($members as $m) {
        $uid = (int)$m['id'];
        $prev = (float)$m['previous_balance'];
        $dep  = $memberDeposits[$uid] ?? 0.0;
        $shop = $personalGrocery[$uid] ?? 0.0;
        $mls  = $memberMeals[$uid] ?? 0.0;
        $mCost = $mls * $costPerMeal;
        $net  = ($prev + $dep + $shop) - ($mCost + $miscPerMember);

        $perMember[$uid] = [
            'prev'      => $prev,
            'deposit'   => $dep,
            'grocery'   => $shop,
            'meals'     => $mls,
            'meal_cost' => $mCost,
            'misc'      => $miscPerMember,
            'net'       => $net,
        ];
    }

    // Total Funds = Deposits + Member Own Shopping (+ Manager Prev. Balance if carried forward)
    $totalFunds = $totalDeposits + $memberPocketGroceryTotal + $managerPrevBal;
    $totalExpenses = $totalGrocery + $totalMisc;

    return [
        'manager_prev_balance'        => $managerPrevBal,
        'total_deposits'              => $totalDeposits,
        'total_funds'                 => $totalFunds,
        'total_grocery'               => $totalGrocery,
        'total_expenses'              => $totalExpenses,
        'manager_cash_grocery'        => $managerCashGrocery,
        'member_pocket_grocery_total' => $memberPocketGroceryTotal,
        'total_misc'                  => $totalMisc,
        'total_meals'                 => $totalMeals,
        'cost_per_meal'               => $costPerMeal,
        'misc_per_member'             => $miscPerMember,
        'cash_held'                   => $totalFunds - $totalExpenses,
        'members'                     => $perMember,
    ];
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $activeTab = $_POST['current_tab'] ?? 'overview';

    // --- UPDATE PROFILE ---
    if ($action === 'update_mess_profile' && $role === 'manager' && $subInfo['active']) {
        $newMessName = trim($_POST['mess_name'] ?? '');
        $logoPath = $mess['mess_logo'] ?? null;

        if (!empty($_FILES['mess_logo']['name']) && is_uploaded_file($_FILES['mess_logo']['tmp_name'])) {
            $file = $_FILES['mess_logo'];
            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowedMimes[$mime])) {
                $flashError = "Invalid image format. Please upload a JPG, PNG, WEBP, or GIF file.";
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $flashError = "Mess image must be 2 MB or smaller.";
            } else {
                $uploadDir = __DIR__ . '/dashboard/uploads/messes';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                $ext = $allowedMimes[$mime];
                $filename = 'mess_' . $messId . '_' . time() . '.' . $ext;
                $dest = $uploadDir . '/' . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if (!empty($logoPath)) {
                        $oldFile = __DIR__ . '/' . ltrim($logoPath, '/');
                        if (is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                    $logoPath = 'dashboard/uploads/messes/' . $filename;
                } else {
                    $flashError = "Could not save uploaded mess image.";
                }
            }
        }

        if ($flashError === '') {
            if ($newMessName === '') {
                $flashError = "Mess name cannot be empty.";
            } else {
                $upd = $pdo->prepare("UPDATE messes SET mess_name = ?, mess_logo = ? WHERE id = ?");
                $upd->execute([$newMessName, $logoPath, $messId]);
                $mess['mess_name'] = $newMessName;
                $mess['mess_logo'] = $logoPath;
                $flash = "Mess profile updated.";
            }
        }
    }

    // --- MEMBER CRUD ---
    if ($action === 'add_member' && $role === 'manager' && $subInfo['active']) {
        $name    = trim($_POST['full_name']);
        $roomNo  = trim($_POST['room_no'] ?? '');
        $seatNo  = trim($_POST['seat_no'] ?? '');
        $prevBal = (float)$_POST['previous_balance'];
        $pin     = $_POST['pin'] ?: '123456';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE mess_id = ?");
        $countStmt->execute([$messId]);
        $nextCode = 'M-' . (101 + (int)$countStmt->fetchColumn());

        $ins = $pdo->prepare("
            INSERT INTO users (mess_id, member_code, room_no, seat_no, full_name, password_hash, role, previous_balance)
            VALUES (?, ?, ?, ?, ?, ?, 'member', ?)
        ");
        $ins->execute([$messId, $nextCode, $roomNo ?: null, $seatNo ?: null, $name, password_hash($pin, PASSWORD_BCRYPT), $prevBal]);
        $flash = "Added {$name} ({$nextCode}) with PIN: {$pin}";
    }

    if ($action === 'edit_member' && $role === 'manager' && $subInfo['active']) {
        $targetUid = (int)$_POST['user_id'];
        $name      = trim($_POST['full_name']);
        $roomNo    = trim($_POST['room_no'] ?? '');
        $seatNo    = trim($_POST['seat_no'] ?? '');
        $prevBal   = (float)$_POST['previous_balance'];
        $newPin    = trim($_POST['new_pin'] ?? '');

        if ($newPin !== '') {
            $upd = $pdo->prepare("
                UPDATE users
                SET full_name = ?, room_no = ?, seat_no = ?, previous_balance = ?, password_hash = ?
                WHERE id = ? AND mess_id = ?
            ");
            $upd->execute([$name, $roomNo ?: null, $seatNo ?: null, $prevBal, password_hash($newPin, PASSWORD_BCRYPT), $targetUid, $messId]);
        } else {
            $upd = $pdo->prepare("
                UPDATE users
                SET full_name = ?, room_no = ?, seat_no = ?, previous_balance = ?
                WHERE id = ? AND mess_id = ?
            ");
            $upd->execute([$name, $roomNo ?: null, $seatNo ?: null, $prevBal, $targetUid, $messId]);
        }
        $flash = "Member details updated.";
    }

    if ($action === 'delete_member' && $role === 'manager' && $subInfo['active']) {
        $targetUid = (int)$_POST['user_id'];
        if ($targetUid === $currentUid) {
            $flashError = "You cannot delete your own manager account.";
        } else {
            $chkDep = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE mess_id = ? AND user_id = ? AND billing_month = ?");
            $chkDep->execute([$messId, $targetUid, $billingMonth]);
            $depCount = (int)$chkDep->fetchColumn();

            $chkMeal = $pdo->prepare("SELECT COUNT(*) FROM meals WHERE mess_id = ? AND user_id = ? AND DATE_FORMAT(meal_date, '%Y-%m') = ?");
            $chkMeal->execute([$messId, $targetUid, $billingMonth]);
            $mealCount = (int)$chkMeal->fetchColumn();

            $chkExp = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE mess_id = ? AND paid_by_user_id = ? AND billing_month = ?");
            $chkExp->execute([$messId, $targetUid, $billingMonth]);
            $expCount = (int)$chkExp->fetchColumn();

            if ($depCount > 0 || $mealCount > 0 || $expCount > 0) {
                $reasons = [];
                if ($depCount > 0)  $reasons[] = "{$depCount} deposit(s)";
                if ($mealCount > 0) $reasons[] = "{$mealCount} meal day(s)";
                if ($expCount > 0)  $reasons[] = "{$expCount} expense(s)";

                $flashError = "Cannot delete this member for {$billingMonth}: they already have " . implode(', ', $reasons) . " in this month.";
            } else {
                $del = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND mess_id = ? AND role = 'member'");
                $del->execute([$targetUid, $messId]);
                $flash = "Member removed from active mess roster.";
            }
        }
    }

    // --- DEPOSIT & REPEAT-SAFE CORRECTION ---
    if ($action === 'add_deposit' && $role === 'manager' && $subInfo['active']) {
        $targetUid = (int)$_POST['user_id'];
        $amount    = (float)$_POST['amount'];
        $note      = trim($_POST['note']) ?: 'Monthly fund contribution';
        if ($amount > 0) {
            $ins = $pdo->prepare("INSERT INTO deposits (mess_id, user_id, amount, entry_type, note, recorded_by, billing_month) VALUES (?, ?, ?, 'deposit', ?, ?, ?)");
            $ins->execute([$messId, $targetUid, $amount, $note, $currentUid, $billingMonth]);
            $flash = "Deposit of " . fmtMoney($cur, $amount) . " recorded.";
        }
    }

    if ($action === 'correct_deposit' && $role === 'manager' && $subInfo['active']) {
        $depId         = (int)$_POST['deposit_id'];
        $correctAmount = (float)$_POST['correct_amount'];
        $reason        = trim($_POST['reason']) ?: 'Amount entry fix';

        $origStmt = $pdo->prepare("SELECT * FROM deposits WHERE id = ? AND mess_id = ?");
        $origStmt->execute([$depId, $messId]);
        $orig = $origStmt->fetch();

        if ($orig) {
            $corrStmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM deposits
                WHERE mess_id = ? AND billing_month = ? AND entry_type = 'correction'
                  AND note LIKE ?
            ");
            $corrStmt->execute([$messId, $billingMonth, "Fixed Deposit #{$depId}%"]);
            $priorAdj = (float)$corrStmt->fetchColumn();

            $currentEffectiveAmount = (float)$orig['amount'] + $priorAdj;
            $diff = round($correctAmount - $currentEffectiveAmount, 2);

            if ($diff != 0.0) {
                $auditNote = sprintf(
                    "Fixed Deposit #%d (Current %s -> New %s, Diff %s%s): %s",
                    $depId,
                    fmtMoney($cur, $currentEffectiveAmount),
                    fmtMoney($cur, $correctAmount),
                    $diff > 0 ? '+' : '',
                    fmtMoney($cur, $diff),
                    $reason
                );
                $ins = $pdo->prepare("
                    INSERT INTO deposits (mess_id, user_id, amount, entry_type, note, recorded_by, billing_month)
                    VALUES (?, ?, ?, 'correction', ?, ?, ?)
                ");
                $ins->execute([$messId, (int)$orig['user_id'], $diff, $auditNote, $currentUid, $billingMonth]);
                $flash = "Deposit updated to " . fmtMoney($cur, $correctAmount) . " (Adjustment: " . ($diff > 0 ? '+' : '') . fmtMoney($cur, $diff) . ").";
            } else {
                $flash = "No change needed — deposit is already " . fmtMoney($cur, $currentEffectiveAmount) . ".";
            }
        }
    }

    // --- EXPENSE (GROCERY & MISC) CRUD & AUTO-MERGE APPROVAL ---
    if ($action === 'add_expense' && $subInfo['active']) {
        $cat    = $_POST['category'] === 'misc' ? 'misc' : 'grocery';
        $desc   = trim($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $status = ($role === 'manager') ? 'approved' : 'pending';

        if ($role === 'manager' && $cat === 'grocery') {
            $fundingMode = ($_POST['funding_mode'] ?? 'manager_cash') === 'member_pocket' ? 'member_pocket' : 'manager_cash';
            $paidBy = ($fundingMode === 'member_pocket' && !empty($_POST['paid_by'])) ? (int)$_POST['paid_by'] : $currentUid;
        } else {
            $fundingMode = 'member_pocket';
            $paidBy = ($role === 'manager' && !empty($_POST['paid_by'])) ? (int)$_POST['paid_by'] : $currentUid;
        }

        if ($amount > 0 && $desc !== '') {
            $ins = $pdo->prepare("
                INSERT INTO expenses (mess_id, paid_by_user_id, funding_source, category, description, amount, expense_date, status, billing_month)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$messId, $paidBy, $fundingMode, $cat, $desc, $amount, $today, $status, $billingMonth]);
            $flash = $status === 'approved'
                ? ucfirst($cat) . " entry added (" . ($fundingMode === 'manager_cash' ? 'Manager Cash' : 'Member Pocket') . ")."
                : ucfirst($cat) . " request submitted for Manager approval & auto-merge.";
        }
    }

    if ($action === 'edit_expense' && $role === 'manager' && $subInfo['active']) {
        $expId       = (int)$_POST['expense_id'];
        $desc        = trim($_POST['description'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $fundingMode = ($_POST['funding_mode'] ?? 'member_pocket') === 'manager_cash' ? 'manager_cash' : 'member_pocket';
        $paidBy      = ($fundingMode === 'member_pocket' && !empty($_POST['paid_by'])) ? (int)$_POST['paid_by'] : $currentUid;

        if ($amount > 0 && $desc !== '') {
            $upd = $pdo->prepare("UPDATE expenses SET description = ?, amount = ?, funding_source = ?, paid_by_user_id = ? WHERE id = ? AND mess_id = ?");
            $upd->execute([$desc, $amount, $fundingMode, $paidBy, $expId, $messId]);
            $flash = "Expense entry updated.";
        }
    }

    if ($action === 'delete_expense' && $role === 'manager' && $subInfo['active']) {
        $expId = (int)$_POST['expense_id'];
        $del = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND mess_id = ?");
        $del->execute([$expId, $messId]);
        $flash = "Expense entry deleted.";
    }

    if ($action === 'decide_expense' && $role === 'manager') {
        $expId  = (int)$_POST['expense_id'];
        $status = $_POST['decision'] === 'approved' ? 'approved' : 'rejected';
        $upd = $pdo->prepare("UPDATE expenses SET status = ? WHERE id = ? AND mess_id = ?");
        $upd->execute([$status, $expId, $messId]);
        $flash = $status === 'approved'
            ? "Expense approved & auto-merged into mess totals and member ledger."
            : "Expense request rejected.";
    }

    // --- DAILY MEALS ---
    if ($action === 'save_meals' && $role === 'manager' && $subInfo['active']) {
        $mealDate = $_POST['meal_date'] ?? $today;
        foreach ($_POST['meals'] as $uid => $m) {
            $b = (float)($m['b'] ?? 0);
            $l = (float)($m['l'] ?? 0);
            $d = (float)($m['d'] ?? 0);
            $upsert = $pdo->prepare("
                INSERT INTO meals (mess_id, user_id, meal_date, breakfast, lunch, dinner)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE breakfast = VALUES(breakfast), lunch = VALUES(lunch), dinner = VALUES(dinner)
            ");
            $upsert->execute([$messId, (int)$uid, $mealDate, $b, $l, $d]);
        }
        $flash = "Daily meals saved for {$mealDate}.";
    }

    // --- SHOPPING ROSTER CRUD ---
    // --- SHOPPING ROSTER CRUD ---
    if ($action === 'add_roster' && $role === 'manager' && $subInfo['active']) {
        $assignedUid = (int)$_POST['assigned_user_id'];
        $dutyDate    = $_POST['duty_date'] ?: $today;
        $dutyEndDate = trim($_POST['duty_end_date'] ?? '');
        if ($dutyEndDate !== '' && $dutyEndDate < $dutyDate) {
            $dutyEndDate = $dutyDate;
        }
        $itemsNote   = trim($_POST['items_note']);

        $ins = $pdo->prepare("
            INSERT INTO shopping_roster (mess_id, assigned_user_id, duty_date, duty_end_date, items_note, status)
            VALUES (?, ?, ?, ?, ?, 'upcoming')
        ");
        $ins->execute([$messId, $assignedUid, $dutyDate, $dutyEndDate !== '' ? $dutyEndDate : null, $itemsNote]);
        $flash = "Shopping duty assigned.";
    }

    if ($action === 'edit_roster' && $role === 'manager' && $subInfo['active']) {
        $rosterId    = (int)$_POST['roster_id'];
        $assignedUid = (int)$_POST['assigned_user_id'];
        $dutyDate    = $_POST['duty_date'] ?: $today;
        $dutyEndDate = trim($_POST['duty_end_date'] ?? '');
        if ($dutyEndDate !== '' && $dutyEndDate < $dutyDate) {
            $dutyEndDate = $dutyDate;
        }
        $itemsNote   = trim($_POST['items_note']);
        $status      = in_array($_POST['status'] ?? '', ['upcoming', 'running', 'completed'], true)
            ? $_POST['status']
            : 'upcoming';

        $upd = $pdo->prepare("
            UPDATE shopping_roster
            SET assigned_user_id = ?, duty_date = ?, duty_end_date = ?, items_note = ?, status = ?
            WHERE id = ? AND mess_id = ?
        ");
        $upd->execute([$assignedUid, $dutyDate, $dutyEndDate !== '' ? $dutyEndDate : null, $itemsNote, $status, $rosterId, $messId]);
        $flash = "Shopping duty updated.";
    }

    if ($action === 'delete_roster' && $role === 'manager' && $subInfo['active']) {
        $rosterId = (int)$_POST['roster_id'];
        $del = $pdo->prepare("DELETE FROM shopping_roster WHERE id = ? AND mess_id = ?");
        $del->execute([$rosterId, $messId]);
        $flash = "Shopping duty removed.";
    }

    if ($action === 'cycle_roster_status' && $role === 'manager') {
        $rosterId = (int)$_POST['roster_id'];
        $isAjax   = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

        if (!$subInfo['active']) {
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Subscription is inactive.']);
                exit;
            }
            $flashError = "Subscription is inactive.";
        } else {
            $curStmt = $pdo->prepare("SELECT status FROM shopping_roster WHERE id = ? AND mess_id = ?");
            $curStmt->execute([$rosterId, $messId]);
            $row = $curStmt->fetch();

            if ($row) {
                $order = ['upcoming', 'running', 'completed'];
                $idx = array_search($row['status'], $order, true);
                $nextStatus = $order[(($idx === false ? -1 : $idx) + 1) % count($order)];

                $upd = $pdo->prepare("UPDATE shopping_roster SET status = ? WHERE id = ? AND mess_id = ?");
                $upd->execute([$nextStatus, $rosterId, $messId]);

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok'        => true,
                        'roster_id' => $rosterId,
                        'status'    => $nextStatus,
                        'label'     => ucfirst($nextStatus),
                    ]);
                    exit;
                }
                $flash = "Shopping duty status set to " . ucfirst($nextStatus) . ".";
            } elseif ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Roster entry not found.']);
                exit;
            }
        }
    }

    // --- CARRY FORWARD PREVIOUS MONTH BALANCES ---
    if ($action === 'carry_forward_prev' && $role === 'manager' && $subInfo['active']) {
        $membersTmp = $pdo->prepare("SELECT * FROM users WHERE mess_id = ? AND is_active = 1");
        $membersTmp->execute([$messId]);
        $prevSummary = getMonthSummary($pdo, $messId, $prevMonth, $membersTmp->fetchAll());

        foreach ($prevSummary['members'] as $uid => $data) {
            $upd = $pdo->prepare("UPDATE users SET previous_balance = ? WHERE id = ? AND mess_id = ?");
            $upd->execute([round($data['net'], 2), $uid, $messId]);
        }
        $flash = "Carried forward closing balances from {$prevMonth} into each member's Previous Balance!";
    }

    // --- CHAT, STRUCTURED MEAL REQUESTS, AMOUNT MERGE REQUESTS & AUTO-MERGE APPROVAL ---
    if ($action === 'send_chat' && $subInfo['active']) {
        $msg     = trim($_POST['message'] ?? '');
        $reqType = $_POST['request_type'] ?? 'chat';
        if (!in_array($reqType, ['chat', 'meal_request', 'amount_request'], true)) {
            $reqType = 'chat';
        }

        if ($reqType === 'meal_request') {
            $targetDate = $_POST['target_date'] ?: $today;
            $reqB = max(0, (float)($_POST['req_breakfast'] ?? 0));
            $reqL = max(0, (float)($_POST['req_lunch'] ?? 0));
            $reqD = max(0, (float)($_POST['req_dinner'] ?? 0));
            $summaryText = $msg !== ''
                ? $msg
                : sprintf("Meal request for %s: B=%.1f, L=%.1f, D=%.1f", $targetDate, $reqB, $reqL, $reqD);

            $ins = $pdo->prepare("
                INSERT INTO chat_messages (mess_id, sender_id, target_user_id, message, request_type, target_date, req_breakfast, req_lunch, req_dinner, is_processed)
                VALUES (?, ?, ?, ?, 'meal_request', ?, ?, ?, ?, 0)
            ");
            $ins->execute([$messId, $currentUid, $currentUid, $summaryText, $targetDate, $reqB, $reqL, $reqD]);
            $flash = "Meal request submitted for Manager approval & auto-merge.";
        } elseif ($reqType === 'amount_request') {
            $reqCat    = ($_POST['req_category'] ?? 'grocery') === 'misc' ? 'misc' : 'grocery';
            $reqAmount = (float)($_POST['req_amount'] ?? 0);
            $reqDesc   = trim($_POST['req_description'] ?? '');
            if ($reqDesc === '') {
                $reqDesc = $msg !== '' ? $msg : 'Chat bazar / amount request';
            }
            $summaryText = $msg !== ''
                ? $msg
                : sprintf("%s request: %s (%s)", strtoupper($reqCat), $reqDesc, fmtMoney($cur, $reqAmount));

            if ($reqAmount > 0) {
                $ins = $pdo->prepare("
                    INSERT INTO chat_messages (mess_id, sender_id, target_user_id, message, request_type, req_category, req_amount, req_description, is_processed)
                    VALUES (?, ?, ?, ?, 'amount_request', ?, ?, ?, 0)
                ");
                $ins->execute([$messId, $currentUid, $currentUid, $summaryText, $reqCat, $reqAmount, $reqDesc]);
                $flash = "Amount request (" . fmtMoney($cur, $reqAmount) . ") sent for Manager approval & auto-merge.";
            } else {
                $flashError = "Please enter a valid amount greater than 0 for an amount merge request.";
            }
        } elseif ($msg !== '') {
            $ins = $pdo->prepare("INSERT INTO chat_messages (mess_id, sender_id, message, request_type, is_processed) VALUES (?, ?, ?, 'chat', 1)");
            $ins->execute([$messId, $currentUid, $msg]);
        }
    }

    if ($action === 'approve_meal_request' && $role === 'manager' && $subInfo['active']) {
        $chatId = (int)$_POST['chat_id'];
        $reqStmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ? AND mess_id = ? AND request_type = 'meal_request' AND is_processed = 0");
        $reqStmt->execute([$chatId, $messId]);
        $req = $reqStmt->fetch();

        if ($req) {
            $targetUid  = (int)($req['target_user_id'] ?: $req['sender_id']);
            $targetDate = $req['target_date'] ?: $today;
            $b = (float)($req['req_breakfast'] ?? 0);
            $l = (float)($req['req_lunch'] ?? 0);
            $d = (float)($req['req_dinner'] ?? 0);

            $pdo->beginTransaction();
            $upsert = $pdo->prepare("
                INSERT INTO meals (mess_id, user_id, meal_date, breakfast, lunch, dinner)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    breakfast = VALUES(breakfast),
                    lunch     = VALUES(lunch),
                    dinner    = VALUES(dinner)
            ");
            $upsert->execute([$messId, $targetUid, $targetDate, $b, $l, $d]);

            $mark = $pdo->prepare("UPDATE chat_messages SET is_processed = 1 WHERE id = ? AND mess_id = ?");
            $mark->execute([$chatId, $messId]);
            $pdo->commit();

            $flash = "Meal request approved & auto-merged into {$targetDate} meal records (Total: " . number_format($b + $l + $d, 1) . ").";
        }
    }

    if ($action === 'approve_amount_request' && $role === 'manager' && $subInfo['active']) {
        $chatId = (int)$_POST['chat_id'];
        $reqStmt = $pdo->prepare("SELECT * FROM chat_messages WHERE id = ? AND mess_id = ? AND request_type = 'amount_request' AND is_processed = 0");
        $reqStmt->execute([$chatId, $messId]);
        $req = $reqStmt->fetch();

        if ($req) {
            $paidByUid = (int)($req['target_user_id'] ?: $req['sender_id']);
            $cat       = ($req['req_category'] ?? 'grocery') === 'misc' ? 'misc' : 'grocery';
            $amount    = (float)($req['req_amount'] ?? 0);
            $desc      = trim((string)($req['req_description'] ?? '')) ?: trim((string)$req['message']);

            if ($amount > 0) {
                $pdo->beginTransaction();
                $insExp = $pdo->prepare("
                    INSERT INTO expenses (mess_id, paid_by_user_id, funding_source, category, description, amount, expense_date, status, billing_month)
                    VALUES (?, ?, 'member_pocket', ?, ?, ?, ?, 'approved', ?)
                ");
                $insExp->execute([$messId, $paidByUid, $cat, $desc, $amount, $today, $billingMonth]);

                $mark = $pdo->prepare("UPDATE chat_messages SET is_processed = 1 WHERE id = ? AND mess_id = ?");
                $mark->execute([$chatId, $messId]);
                $pdo->commit();

                $flash = "Amount request (" . fmtMoney($cur, $amount) . ") approved & merged into " . strtoupper($cat) . " expenses.";
            }
        }
    }

    if ($action === 'activate_plan' && $role === 'manager') {
        $plan   = $_POST['plan'] === 'pro' ? 'pro' : 'starter';
        $newEnd = date('Y-m-d H:i:s', strtotime('+30 days'));
        $upd = $pdo->prepare("UPDATE messes SET subscription_plan = ?, subscription_status = 'active', subscription_ends_at = ? WHERE id = ?");
        $upd->execute([$plan, $newEnd, $messId]);
        header("Location: dashboard.php");
        exit;
    }
}

// --- FETCH MEMBERS & COMPUTE CURRENT + PREVIOUS MONTH SUMMARIES ---
$membersStmt = $pdo->prepare("SELECT * FROM users WHERE mess_id = ? AND is_active = 1 ORDER BY member_code ASC");
$membersStmt->execute([$messId]);
$members = $membersStmt->fetchAll();

$currentUserRecord = null;
foreach ($members as $m) {
    if ((int)$m['id'] === $currentUid) {
        $currentUserRecord = $m;
        break;
    }
}

$currSummary = getMonthSummary($pdo, $messId, $billingMonth, $members);
$prevSummary = getMonthSummary($pdo, $messId, $prevMonth, $members);

// Expenses Lists
$expStmt = $pdo->prepare("
    SELECT e.*, u.full_name, u.member_code
    FROM expenses e JOIN users u ON e.paid_by_user_id = u.id
    WHERE e.mess_id = ? AND e.billing_month = ?
    ORDER BY e.created_at DESC
");
$expStmt->execute([$messId, $billingMonth]);
$allExpenses = $expStmt->fetchAll();

$groceryLogs = array_filter($allExpenses, fn($e) => $e['category'] === 'grocery');
$miscLogs    = array_filter($allExpenses, fn($e) => $e['category'] === 'misc');
$pendingExpenses = array_filter($allExpenses, fn($e) => $e['status'] === 'pending');

// Deposits List + Repeat-Safe Effective Amount Map
$depStmt = $pdo->prepare("
    SELECT d.*, u.full_name, u.member_code
    FROM deposits d JOIN users u ON d.user_id = u.id
    WHERE d.mess_id = ? AND d.billing_month = ?
    ORDER BY d.created_at DESC
");
$depStmt->execute([$messId, $billingMonth]);
$allDeposits = $depStmt->fetchAll();

$correctionsByDeposit = [];
foreach ($allDeposits as $dp) {
    if ($dp['entry_type'] === 'correction' && preg_match('/Fixed Deposit #(\d+)/', (string)$dp['note'], $m)) {
        $parentId = (int)$m[1];
        $correctionsByDeposit[$parentId] = ($correctionsByDeposit[$parentId] ?? 0.0) + (float)$dp['amount'];
    }
}

// Selected Date Meals
$selectedMealDate = $_GET['meal_date'] ?? $today;
$todayMealStmt = $pdo->prepare("SELECT * FROM meals WHERE mess_id = ? AND meal_date = ?");
$todayMealStmt->execute([$messId, $selectedMealDate]);
$todayMealsMap = [];
foreach ($todayMealStmt->fetchAll() as $tm) {
    $todayMealsMap[$tm['user_id']] = $tm;
}

// Full Month Meal Chart Matrix (Day 1 to Days in Month)
$daysInMonth = (int)date('t', strtotime($billingMonth . '-01'));
$monthMealsStmt = $pdo->prepare("
    SELECT user_id, DAY(meal_date) AS day_num, (breakfast + lunch + dinner) AS day_total
    FROM meals WHERE mess_id = ? AND DATE_FORMAT(meal_date, '%Y-%m') = ?
");
$monthMealsStmt->execute([$messId, $billingMonth]);
$mealMatrix = [];
foreach ($monthMealsStmt->fetchAll() as $mm) {
    $mealMatrix[$mm['user_id']][(int)$mm['day_num']] = (float)$mm['day_total'];
}

// Shopping Roster
$rosterStmt = $pdo->prepare("
    SELECT r.*, u.full_name, u.member_code
    FROM shopping_roster r JOIN users u ON r.assigned_user_id = u.id
    WHERE r.mess_id = ? ORDER BY r.duty_date ASC
");
$rosterStmt->execute([$messId]);
$rosters = $rosterStmt->fetchAll();

// Chat & Unprocessed Notifications (meal_request + amount_request only)
$chatStmt = $pdo->prepare("
    SELECT c.*, u.full_name, u.member_code
    FROM chat_messages c JOIN users u ON c.sender_id = u.id
    WHERE c.mess_id = ? ORDER BY c.created_at DESC LIMIT 30
");
$chatStmt->execute([$messId]);
$chats = array_reverse($chatStmt->fetchAll());

$unreadActionRequests = array_filter(
    $chats,
    fn($c) => in_array($c['request_type'], ['meal_request', 'amount_request'], true) && (int)$c['is_processed'] === 0
);
$totalNotifications = count($pendingExpenses) + count($unreadActionRequests);

// Sub-labels for Top KPI Cards
$fundsFormulaSub = fmtMoney($cur, $currSummary['total_deposits']) . " Dep + " . fmtMoney($cur, $currSummary['member_pocket_grocery_total']) . " Own Shop"
    . ($currSummary['manager_prev_balance'] != 0 ? " + " . fmtMoney($cur, $currSummary['manager_prev_balance']) . " Mgr Prev" : "");
$cashFormulaSub  = "Funds - (Grocery + Misc)";
$miscSplitSub    = fmtMoney($cur, $currSummary['misc_per_member']) . " / member (" . count($members) . ")";

// Column Totals
$sumPrevBal  = 0.0;
$sumDeposit  = 0.0;
$sumOwnShop  = 0.0;
$sumMeals    = 0.0;
$sumMealCost = 0.0;
$sumMisc     = 0.0;
$sumNetBal   = 0.0;

$backupDir = __DIR__ . '/dashboard/backups';
$dailyBackupFile = $backupDir . "/backup_mess_{$messId}_{$billingMonth}_{$today}.json";
if (!is_file($dailyBackupFile)) {
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }
    $snapshotPayload = [
        'generated_at' => date('Y-m-d H:i:s'),
        'mess'         => ['id' => $messId, 'name' => $mess['mess_name'], 'code' => $mess['mess_code']],
        'month'        => $billingMonth,
        'summary'      => $currSummary,
        'members'      => $members,
        'deposits'     => $allDeposits,
        'expenses'     => $allExpenses,
        'meal_matrix'  => $mealMatrix,
        'roster'       => $rosters,
    ];
    @file_put_contents($dailyBackupFile, json_encode($snapshotPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($mess['mess_name']) ?> — MessMate</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="dashboard/assets/dashboard.css" rel="stylesheet">
</head>
<body>

<?php require __DIR__ . '/dashboard/partials/navbar.php'; ?>

<div class="mm-main-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <?php if (!$subInfo['active']): ?>
            <div class="alert alert-danger d-flex justify-content-between align-items-center rounded-4">
                <div><strong>Subscription Expired!</strong> Dashboard is in read-only mode until renewed.</div>
                <?php if ($role === 'manager'): ?>
                    <button class="btn btn-light btn-sm fw-bold text-danger" data-bs-toggle="modal" data-bs-target="#modalSub">Renew Plan</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($flash): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 py-2.5 fw-semibold" role="alert">
                <i class="bi bi-check-circle-fill me-1"></i> <?= e($flash) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($flashError !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2.5 fw-semibold" role="alert">
                <i class="bi bi-shield-exclamation me-1"></i> <?= e($flashError) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/dashboard/partials/kpi_cards.php'; ?>

        <ul class="nav nav-tabs nav-tabs-clean rounded  mb-4" role="tablist">
            <li class="nav-item">
                <button class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabOverview" type="button">
                    <i class="bi bi-table me-1"></i> Overview &amp; Ledger
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $activeTab === 'meals' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabMeals" type="button">
                    <i class="bi bi-egg-fried me-1"></i> Daily &amp; Full Month Meals
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $activeTab === 'expenses' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabExpenses" type="button">
                    <i class="bi bi-cart4 me-1"></i> Grocery &amp; Misc Bills
                    <?php if (count($pendingExpenses) > 0): ?>
                        <span class="badge bg-danger ms-1"><?= count($pendingExpenses) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $activeTab === 'roster' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabRoster" type="button">
                    <i class="bi bi-calendar-check me-1"></i> Shopping Roster
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link <?= $activeTab === 'prevmonth' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabPrevMonth" type="button">
                    <i class="bi bi-clock-history me-1"></i> Previous Month (<?= e($prevMonth) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <?php require __DIR__ . '/dashboard/partials/tab_overview.php'; ?>
            <?php require __DIR__ . '/dashboard/partials/tab_meals.php'; ?>
            <?php require __DIR__ . '/dashboard/partials/tab_expenses.php'; ?>
            <?php require __DIR__ . '/dashboard/partials/tab_roster.php'; ?>
            <?php require __DIR__ . '/dashboard/partials/tab_prevmonth.php'; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/dashboard/partials/drawer_chat.php'; ?>
<?php require __DIR__ . '/dashboard/partials/modals.php'; ?>

<script>
window.MESSMATE_CONFIG = {
    currency: <?= json_encode($cur) ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="dashboard/assets/dashboard.js"></script>
</body>
</html>