<?php if (in_array($role, ['member', 'manager'], true) && isset($currSummary['members'][$currentUid], $currentUserRecord)):
    $my = $currSummary['members'][$currentUid];
?>
<div class="d-flex justify-content-between align-items-center mb-2">
    <span class="small fw-bold text-uppercase text-muted">Toggle to Show Individual Account Summary</span>
    <button type="button" id="btnTogglePersonalStats" class="btn btn-outline-success btn-sm py-1 px-2.5 fw-semibold" onclick="togglePersonalStatsCard()">
        <i class="bi bi-eye-slash me-1"></i> <span id="togglePersonalStatsLabel">Hide My Stats</span>
    </button>
</div>

<div id="mmPersonalStatsCard" class="card border-2 border-success rounded-4 p-4 mb-4 bg-white shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <span class="badge bg-success-subtle text-success px-3 py-2 mb-1">
                MY PERSONAL ACCOUNT (<?= e($billingMonth) ?>)<?= $role === 'manager' ? ' • MANAGER' : '' ?>
            </span>
            <h6 class="fw-bold mt-2 mb-0"><?= e($currentUserRecord['full_name']) ?> <span class="text-muted fs-6">(<?= e($currentUserRecord['member_code']) ?>)</span></h6>
        </div>
        <div class="text-end">
            <div class="small text-muted fw-semibold text-uppercase">My Net Balance</div>
            <span class="net-pill fs-6 mt-2 <?= $my['net'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                <?= ($my['net'] >= 0 ? '+' : '') . e(fmtMoney($cur, $my['net'])) ?>
            </span>
        </div>
    </div>
    <div class="row g-3 text-center">
        <div class="col-6 col-md-2">
            <div class="p-2 rounded-3 bg-light">
                <div class="small text-muted">Prev. Balance</div>
                <div class="fs-6 fw-bold"><?= ($my['prev'] >= 0 ? '+' : '') . e(fmtMoney($cur, $my['prev'])) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-2 rounded-3 bg-light">
                <div class="small text-muted">My Deposits</div>
                <div class="fs-6 fw-bold"><?= e(fmtMoney($cur, $my['deposit'])) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-2 rounded-3 bg-light">
                <div class="small text-muted">Own Grocery</div>
                <div class="fs-6 fw-bold"><?= e(fmtMoney($cur, $my['grocery'])) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-2 rounded-3 bg-light">
                <div class="small text-muted">My Meals</div>
                <div class="fs-6 fw-bold"><?= number_format($my['meals'], 1) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-2 rounded-3 bg-light">
                <div class="small text-muted">My Meal Cost</div>
                <div class="fs-6 fw-bold"><?= e(fmtMoney($cur, $my['meal_cost'])) ?></div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="p-2 rounded-3 bg-light">
                <div class="small text-muted">My Misc Share</div>
                <div class="fs-6 fw-bold"><?= e(fmtMoney($cur, $my['misc'])) ?></div>
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
        <div class="card border-0 rounded-4 p-4 h-100 text-white" style="background:#b45309;">
            <div class="kpi-label text-white">Total Grocery Cost</div>
            <div class="kpi-value text-white"><?= e(fmtMoney($cur, $currSummary['total_grocery'])) ?></div>
            <div class="small text-white mt-1">Paid by Manager + Paid by Member</div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card border-0 rounded-4 p-4 h-100 text-white" style="background:#0f172a;">
            <div class="kpi-label" style="color:#34d399;">Live Cost Per Meal</div>
            <div class="kpi-value"><?= e(fmtMoney($cur, $currSummary['cost_per_meal'])) ?></div>
            <div class="small text-white mt-1">Total Grocery Cost / Total Meals: <?= number_format($currSummary['total_meals'], 1) ?></div>
        </div>
    </div>
    <div class="col-12 col-xl">
        <div class="card border-0 rounded-4 p-4 h-100 text-white bg-success">
            <div class="kpi-label" style="color:<?= $currSummary['cash_held'] >= 0 ? '#ffffff' : '#fecaca' ?>;">Cash With Manager</div>
            <div class="kpi-value <?= $currSummary['cash_held'] >= 0 ? 'text-white' : 'text-warning' ?>">
                <?= e(fmtMoney($cur, $currSummary['cash_held'])) ?>
            </div>
            <div class="small text-white mt-1">Total Funds - Total Expenses</div>
        </div>
    </div>
</div>