<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top py-3 gap-4">
    <div class="container-xl">
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($mess['mess_logo'])): ?>
                <img src="<?= e($mess['mess_logo']) ?>" alt="<?= e($mess['mess_name']) ?> icon"
                     class="rounded-3 border object-fit-cover" style="width:40px;height:40px;">
            <?php else: ?>
                <div class="bg-success text-white rounded-3 d-flex align-items-center justify-content-center fw-bold fs-5" style="width:40px;height:40px;">M</div>
            <?php endif; ?>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark fs-6"><?= e($mess['mess_name']) ?></span>
                    <span class="badge bg-light text-dark border"><?= e($mess['mess_code']) ?></span>
                </div>
                <div class="small text-muted">
                    User: <strong><?= e($currentUserRecord['full_name'] ?? '') ?></strong> (<span class="text-success text-uppercase fw-bold"><?= e($role) ?></span>) •
                    <span class="<?= $subInfo['active'] ? 'text-success' : 'text-danger fw-bold' ?>"><?= e($subInfo['label']) ?></span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" class="d-flex align-items-center">
                <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
                <input type="month" name="month" value="<?= e($billingMonth) ?>" onchange="this.form.submit()" class="form-control form-control-sm fw-semibold">
            </form>

            <button class="btn btn-outline-secondary btn-sm position-relative fw-semibold py-1.5 px-2.5" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerNotify">
                <i class="bi bi-bell-fill"></i> Chat &amp; Approvals
                <?php if ($totalNotifications > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $totalNotifications ?>
                    </span>
                <?php endif; ?>
            </button>

            <?php if ($role === 'manager'): ?>
                <button class="btn btn-outline-dark btn-sm fw-semibold py-1.5" data-bs-toggle="modal" data-bs-target="#modalMessProfile">
                    <i class="bi bi-gear-fill"></i> Mess Profile
                </button>
                <button class="btn btn-outline-warning text-dark btn-sm fw-semibold py-1.5" data-bs-toggle="modal" data-bs-target="#modalSub">
                    <i class="bi bi-star-fill text-warning"></i> Plan
                </button>
                <button class="btn btn-success btn-sm fw-semibold py-1.5" data-bs-toggle="modal" data-bs-target="#modalDeposit">
                    <i class="bi bi-plus-lg"></i> Deposit
                </button>
                <button class="btn btn-dark btn-sm fw-semibold py-1.5" data-bs-toggle="modal" data-bs-target="#modalMember">
                    <i class="bi bi-person-plus-fill"></i> Member
                </button>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-light border btn-sm text-secondary fw-semibold py-1.5">Logout</a>
        </div>
    </div>
</nav>