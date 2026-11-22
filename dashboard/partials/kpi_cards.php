<?php if ($role === 'member' && isset($currSummary['members'][$currentUid])):
    $my = $currSummary['members'][$currentUid];
?>
<div class="card border-2 border-success rounded-4 p-4 mb-4 bg-white shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <span class="badge bg-success-subtle text-success px-3 py-2 mb-1">MY PERSONAL ACCOUNT (<?= e($billingMonth) ?>)</span>
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
                <div class="fs-6 fw-bold text-success"><?= e(fmtMoney($cur, $my['deposit'])) ?></div>
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

<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <div class="card-clean p-4 h-100" >
            <div class="kpi-label text-success">Total Funds Collected</div>
            <div class="kpi-value text-dark"><?= e(fmtMoney($cur, $currSummary['total_deposits'])) ?></div>
            <div class="small text-muted mt-1">Member deposits this month</div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label" style="color:#b45309;">Grocery Expenditure</div>
            <div class="kpi-value text-dark"><?= e(fmtMoney($cur, $currSummary['total_grocery'])) ?></div>
            <div class="small text-muted mt-1">Approved bazar &amp; food items</div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card-clean p-4 h-100">
            <div class="kpi-label text-primary">Misc &amp; Fixed Bills</div>
            <div class="kpi-value text-dark"><?= e(fmtMoney($cur, $currSummary['total_misc'])) ?></div>
            <div class="small text-muted mt-1"><?= e($miscSplitSub) ?></div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card-clean p-4 h-100" >
            <div class="kpi-label" style="color:<?= $currSummary['cash_held'] >= 0 ? '#0f766e' : '#b91c1c' ?>;">Cash With Manager</div>
            <div class="kpi-value <?= $currSummary['cash_held'] >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= e(fmtMoney($cur, $currSummary['cash_held'])) ?>
            </div>
            <div class="small text-muted mt-1"><?= e($cashFormulaSub) ?></div>
        </div>
    </div>
    <div class="col-12 col-lg">
        <div class="card border-0 rounded-4 p-4 h-100 text-white" style="background:#0f172a;">
            <div class="kpi-label" style="color: #34d399">Live Cost Per Meal</div>
            <div class="kpi-value"><?= e(fmtMoney($cur, $currSummary['cost_per_meal'])) ?></div>
            <div class="small text-white-50 mt-1">Total Meals: <?= number_format($currSummary['total_meals'], 1) ?></div>
        </div>
    </div>
</div>