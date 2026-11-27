<?php if (in_array($role, ['member', 'manager'], true) && isset($currSummary['members'][$currentUid], $currentUserRecord)):
    $my = $currSummary['members'][$currentUid];
    $myPrev         = (float)($my['prev'] ?? 0);
    $myDeposit      = (float)($my['deposit'] ?? 0);
    $myOwnGrocery   = (float)($my['grocery'] ?? 0);
    $myTotalDeposit = $myPrev + $myDeposit + $myOwnGrocery;

    $myMealCost      = (float)($my['meal_cost'] ?? 0);
    $myMiscShare   = (float)($my['misc'] ?? 0);
    $myTotalCost = $myMealCost + $myMiscShare;
?>
<div class="d-flex justify-content-between align-items-center mb-2">
    <span class="small fw-bold text-uppercase text-muted">Individual Account Summary</span>
    <button type="button" id="btnTogglePersonalStats" class="btn btn-outline-success btn-sm py-1 px-2.5 fw-semibold" onclick="togglePersonalStatsCard()">
        <i class="bi bi-eye-slash me-1"></i> <span id="togglePersonalStatsLabel">Hide My Stats</span>
    </button>
</div>

<div id="mmPersonalStatsCard" class="card border-2 border-success rounded-4 p-4 mb-4 bg-white shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex gap-1 align-items-center">
            <h5 class="fw-bold mb-0"><?= e($currentUserRecord['full_name']) ?></h5>
            <span class="badge bg-success-subtle text-success px-2 py-1 small">(<?= e($currentUserRecord['member_code']) ?>) <?= $role === 'manager' ? ' • Manager' : '' ?></span>
        </div>
        <div class="text-end fw-medium">
            Account Summary for <span class="bg-warning-subtle text-dark fw-bold rounded-3 px-3 py-1">
                 <?= e($billingMonth) ?>
            </span>
        </div>
    </div>
    <div class="row g-3 text-center">
        <div class="col-6 col-md-3">
            <div class="p-3 py-4 rounded-3 bg-light h-100 border">
                <div class="text-muted">Meal count</div>
                <div class="fs-5 fw-bold mt-2"><?= number_format($my['meals'], 1) ?></div>
                <div class="small text-muted">
                    My Total Meals
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 py-4 rounded-3 bg-light h-100 border">
                <div class="text-muted">My Total Deposit</div>
                <div class="fs-5 fw-bold mt-2 text-success"><?= e(fmtMoney($cur, $myTotalDeposit)) ?></div>
                <div class="small text-muted">
                    Prev. Balance + Deposit + Own Shopping
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 py-4 rounded-3 bg-light h-100 border">
                <div class="text-muted">My Total Cost</div>
                <div class="fs-5 fw-bold mt-2 text-danger"><?= e(fmtMoney($cur, $myTotalCost)) ?></div>
                <div class="small text-muted">
                    Total Meal Cost + Misc Share
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="p-3 py-4 rounded-3 h-100 border <?= $my['net'] >= 0 ? 'bg-success-subtle' : 'bg-danger-subtle' ?>">
                <div class="text-dark">My Net Balance</div>
                <div class="fs-5 fw-bold mt-2 <?= $my['net'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($my['net'] >= 0 ? '+' : '') . e(fmtMoney($cur, $my['net'])) ?></div>
                <div class="small text-dark">
                    Total Deposit - Total Cost
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ROW 1: FUNDS INFLOW -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label">Prev. Balance</div>
            <div class="kpi-value <?= $currSummary['manager_prev_balance'] >= 0 ? 'text-dark' : 'text-danger' ?>">
                <?= ($currSummary['manager_prev_balance'] >= 0 ? '+' : '') . e(fmtMoney($cur, $currSummary['manager_prev_balance'])) ?>
            </div>
            <div class="small text-muted mt-1">Manager carried forward</div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label">Deposits This Month</div>
            <div class="kpi-value text-dark"><?= e(fmtMoney($cur, $currSummary['total_deposits'])) ?></div>
            <div class="small text-muted mt-1">Cash deposited by members</div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label">Member Own Shop</div>
            <div class="kpi-value text-dark"><?= e(fmtMoney($cur, $currSummary['member_pocket_grocery_total'])) ?></div>
            <div class="small text-muted mt-1">Shop by members</div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label text-success">Total Funds</div>
            <div class="kpi-value text-success"><?= e(fmtMoney($cur, $currSummary['total_funds'])) ?></div>
            <div class="small text-muted mt-1">Monthly deposits + Member shop</div>
        </div>
    </div>
</div>

<!-- ROW 2: EXPENSES BREAKDOWN -->
<div class="row g-3 mb-3">
    <div class="col-6 col-xl">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label">Manager Cash Grocery</div>
            <div class="kpi-value text-dark"><?= e(fmtMoney($cur, $currSummary['manager_cash_grocery'])) ?></div>
            <div class="small text-muted mt-1">Paid from manager cash</div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label">Own Pocket Grocery</div>
            <div class="kpi-value text-dark"><?= e(fmtMoney($cur, $currSummary['member_pocket_grocery_total'])) ?></div>
            <div class="small text-muted mt-1">Paid by members directly</div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label">Misc &amp; Fixed Bills</div>
            <div class="kpi-value text-dark"><?= e(fmtMoney($cur, $currSummary['total_misc'])) ?></div>
            <div class="small text-muted mt-1"><?= e($miscSplitSub) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label text-danger">Total Expenses</div>
            <div class="kpi-value text-danger"><?= e(fmtMoney($cur, $currSummary['total_expenses'])) ?></div>
            <div class="small text-muted mt-1">All Grocery + Misc.</div>
        </div>
    </div>
</div>

<!-- ROW 3: KEY SUMMARY METRICS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl">
        <div class="card border-0 rounded-4 p-4 h-100" style="background: #f1ece5;">
            <div class="kpi-label text-dark">Total Grocery Cost</div>
            <div class="kpi-value" style="color: #ab5d00;"><?= e(fmtMoney($cur, $currSummary['total_grocery'])) ?></div>
            <div class="small mt-1">Paid by Manager + Paid by Member</div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <?php
        $mealRate = (float)($currSummary['cost_per_meal'] ?? 0);
        $mealThreshold = isset($targetMealCost) ? max(0.0, (float)$targetMealCost) : 60.0;
        $dangerThreshold = $mealThreshold > 0 ? ($mealThreshold * 1.2) : 100.0;

        $mealRateClass = $mealRate > $dangerThreshold ? 'text-danger' : ($mealRate > $mealThreshold ? 'text-warning' : 'text-success');

        $mealRateBgClass = $mealRate > $dangerThreshold ? '#f5e8eb' : ($mealRate > $mealThreshold ? '#fff9e9' : '#e4f0ed');
        ?>
        <div class="card border-0 rounded-4 p-4 h-100" style="background: <?= $mealRateBgClass ?>;">
            <div class="kpi-label text-dark">Live Cost Per Meal</div>
            <div class="kpi-value <?= $mealRateClass ?>">
                <?= e(fmtMoney($cur, $currSummary['cost_per_meal'])) ?>
            </div>
            <div class="small mt-1">Total Grocery Cost / Total Meals: <?= number_format($currSummary['total_meals'], 1) ?></div>
        </div>
    </div>
    <div class="col-12 col-xl">
        <div class="card border-0 rounded-4 p-4 h-100" style="background: <?= $currSummary['cash_held'] >= 0 ? '#e4f0ed' : '#f5e8eb' ?>;">
            <div class="kpi-label text-dark">Cash With Manager</div>
            <div class="kpi-value <?= $currSummary['cash_held'] >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= e(fmtMoney($cur, $currSummary['cash_held'])) ?>
            </div>
            <div class="small mt-1">Total Funds - Total Expenses</div>
        </div>
    </div>
</div>