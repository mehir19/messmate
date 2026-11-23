<!-- Mobile Top Header (visible below lg) -->
<div class="d-lg-none bg-white border-bottom sticky-top px-3 py-2 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <?php if (!empty($mess['mess_logo'])): ?>
            <img src="<?= e($mess['mess_logo']) ?>" alt="<?= e($mess['mess_name']) ?> icon"
                 class="rounded-3 border object-fit-cover" style="width:36px;height:36px;">
        <?php else: ?>
            <div class="bg-success text-white rounded-3 d-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;">M</div>
        <?php endif; ?>
        <div>
            <div class="fw-bold text-dark small lh-1"><?= e($mess['mess_name']) ?></div>
            <span class="text-muted font-monospace" style="font-size:0.72rem;"><?= e($mess['mess_code']) ?></span>
        </div>
    </div>
    <button class="btn btn-outline-dark btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
        <i class="bi bi-list fs-5"></i>
    </button>
</div>

<!-- Desktop Left Vertical Sidebar + Mobile Offcanvas -->
<aside class="mm-sidebar offcanvas-lg offcanvas-start bg-white border-end" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header border-bottom d-lg-none">
        <h6 class="offcanvas-title fw-bold"><?= e($mess['mess_name']) ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#mobileSidebar"></button>
    </div>

    <div class="mm-sidebar-inner d-flex flex-column justify-content-between p-3">
        <div>
            <!-- Mess Brand & User Info -->
            <div class="d-flex align-items-center gap-2 pb-3 mb-3 border-bottom">
                <?php if (!empty($mess['mess_logo'])): ?>
                    <img src="<?= e($mess['mess_logo']) ?>" alt="<?= e($mess['mess_name']) ?> icon"
                         class="rounded-3 border object-fit-cover flex-shrink-0" style="width:42px;height:42px;">
                <?php else: ?>
                    <div class="bg-success text-white rounded-3 d-flex align-items-center justify-content-center fw-bold fs-5 flex-shrink-0" style="width:42px;height:42px;">M</div>
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="fw-bold text-dark text-truncate"><?= e($mess['mess_name']) ?></div>
                    <div class="d-flex align-items-center gap-1 mt-0.5">
                        <span class="badge bg-light text-dark border font-monospace"><?= e($mess['mess_code']) ?></span>
                        <span class="badge bg-success-subtle text-success text-uppercase"><?= e($role) ?></span>
                    </div>
                </div>
            </div>

            <div class="small text-muted mb-3 px-1">
                <div>User: <strong><?= e($currentUserRecord['full_name'] ?? '') ?></strong> (<span class="text-success text-uppercase fw-bold"><?= e($role) ?></span>)</div>
                <div class="<?= $subInfo['active'] ? 'text-success' : 'text-danger fw-bold' ?>">
                    <i class="bi bi-shield-check me-1"></i><?= e($subInfo['label']) ?>
                </div>
            </div>

            <!-- Billing Month Selector -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-uppercase text-muted mb-1" style="font-size:0.7rem;">Billing Month</label>
                <form method="GET">
                    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
                    <input type="month" name="month" value="<?= e($billingMonth) ?>" onchange="this.form.submit()" class="form-control form-control-sm fw-semibold">
                </form>
            </div>

            <!-- Primary Actions -->
            <div class="d-grid gap-2 mb-3">
                <button class="btn btn-outline-secondary btn-sm text-start d-flex justify-content-between align-items-center fw-semibold py-2"
                        type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerNotify">
                    <span><i class="bi bi-bell-fill me-2 text-primary"></i>Chat &amp; Approvals</span>
                    <?php if ($totalNotifications > 0): ?>
                        <span class="badge rounded-pill bg-danger"><?= $totalNotifications ?></span>
                    <?php endif; ?>
                </button>

                <?php if ($role === 'manager'): ?>
                    <button class="btn btn-success btn-sm text-start fw-semibold py-2" data-bs-toggle="modal" data-bs-target="#modalDeposit">
                        <i class="bi bi-plus-circle-fill me-2"></i>Record Deposit
                    </button>
                    <button class="btn btn-dark btn-sm text-start fw-semibold py-2" data-bs-toggle="modal" data-bs-target="#modalMember">
                        <i class="bi bi-person-plus-fill me-2"></i>Add Member
                    </button>
                <?php endif; ?>
            </div>

            <!-- Backup & Settings -->
            <div class="small fw-bold text-uppercase text-muted mb-1 px-1" style="font-size:0.7rem;">Reports &amp; Settings</div>
            <div class="d-grid gap-1.5">
                <a href="export_backup.php?month=<?= urlencode($billingMonth) ?>&auto_pdf=1"
                   target="_blank"
                   class="btn btn-outline-danger btn-sm text-start fw-semibold py-1.5">
                    <i class="bi bi-file-earmark-pdf-fill me-2"></i>Daily PDF Backup
                </a>
                <a href="view_backup.php" class="btn btn-light border btn-sm text-start fw-semibold py-1.5 text-dark">
                    <i class="bi bi-filetype-json me-2 text-secondary"></i>JSON Snapshots
                </a>

                <?php if ($role === 'manager'): ?>
                    <button class="btn btn-light border btn-sm text-start fw-semibold py-1.5 text-dark" data-bs-toggle="modal" data-bs-target="#modalMessProfile">
                        <i class="bi bi-gear-fill me-2 text-secondary"></i>Mess Profile
                    </button>
                    <button class="btn btn-light border btn-sm text-start fw-semibold py-1.5 text-dark" data-bs-toggle="modal" data-bs-target="#modalSub">
                        <i class="bi bi-star-fill me-2 text-warning"></i>Subscription Plan
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom Logout -->
        <div class="pt-3 mt-3 border-top">
            <a href="logout.php" class="btn btn-outline-secondary btn-sm w-100 fw-semibold py-2">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</aside>