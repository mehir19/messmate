<?php if (in_array($role, ['member', 'manager'], true) && isset($currSummary['members'][$currentUid], $currentUserRecord)):
    $my = $currSummary['members'][$currentUid];
?>
<div class="card border-2 border-success rounded-4 p-4 mb-4 bg-white shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <span class="badge bg-success-subtle text-success px-3 py-2 mb-1">
                MY PERSONAL ACCOUNT (<?= e($billingMonth) ?>)<?= $role === 'manager' ? ' • MANAGER' : '' ?>
            </span>
            <h6 class="fw-bold mt-2 mb-0"><?= e($currentUserRecord['full_name']) ?> <span class="text-muted fs-6">(<?= e($currentUserRecord['member_code']) ?>)</span></h6>
            <?php if ($role === 'manager'): ?>
                <div class="small text-muted mt-1">Personal ledger figures only — separate from mess-wide funds and cash held.</div>
            <?php endif; ?>
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