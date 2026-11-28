<!-- Mobile Top Header (visible below lg) -->
<div class="d-lg-none bg-white border-bottom sticky-top px-3 py-2 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2 min-w-0">
        <?php if (!empty($mess['mess_logo'])): ?>
            <img src="<?= e($mess['mess_logo']) ?>" alt="<?= e($mess['mess_name']) ?> icon"
                 class="rounded-3 border object-fit-cover flex-shrink-0" style="width:36px;height:36px;">
        <?php else: ?>
            <div class="bg-success text-white rounded-3 d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:36px;height:36px;">M</div>
        <?php endif; ?>
        <div class="text-truncate">
            <div class="fw-bold text-dark small lh-1 text-truncate"><?= e($mess['mess_name']) ?></div>
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
        <h6 class="offcanvas-title fw-bold mb-0"><?= e($mess['mess_name']) ?></h6>
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
                <div>
                    Signed In: <strong><?= e($currentUserRecord['full_name'] ?? '') ?></strong>
                    <?php if (!empty($currentUserRecord['member_code'])): ?>
                        <span class="badge bg-success-subtle text-success font-monospace"><?= e($currentUserRecord['member_code']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="<?= $subInfo['active'] ? 'text-success' : 'text-danger fw-bold' ?> mt-0.5">
                    <i class="bi bi-shield-check me-1"></i><?= e($subInfo['label']) ?>
                </div>
            </div>

            <!-- Navigation Tabs in Left Sidebar -->
            <div class="small fw-bold text-uppercase text-muted mb-2 px-1" style="font-size:0.7rem;">Navigation</div>
            <div class="nav flex-column nav-pills mm-sidebar-nav gap-1 mb-3" role="tablist">
                <button class="nav-link text-start py-2 px-3 rounded-3 d-flex align-items-center <?= $activeTab === 'overview' ? 'active' : '' ?>"
                        data-bs-toggle="tab" data-bs-target="#tabOverview" type="button" role="tab">
                    <i class="bi bi-table me-2"></i><span>Overview &amp; Ledger</span>
                </button>
                <button class="nav-link text-start py-2 px-3 rounded-3 d-flex align-items-center <?= $activeTab === 'meals' ? 'active' : '' ?>"
                        data-bs-toggle="tab" data-bs-target="#tabMeals" type="button" role="tab">
                    <i class="bi bi-egg-fried me-2"></i><span>Daily &amp; Full Meals</span>
                </button>
                <button class="nav-link text-start py-2 px-3 rounded-3 d-flex align-items-center <?= $activeTab === 'expenses' ? 'active' : '' ?>"
                        data-bs-toggle="tab" data-bs-target="#tabExpenses" type="button" role="tab">
                    <i class="bi bi-cart4 me-2"></i><span>Grocery &amp; Misc Bills</span>
                    <?php if (count($pendingExpenses) > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= count($pendingExpenses) ?></span>
                    <?php endif; ?>
                </button>
                <button class="nav-link text-start py-2 px-3 rounded-3 d-flex align-items-center <?= $activeTab === 'roster' ? 'active' : '' ?>"
                        data-bs-toggle="tab" data-bs-target="#tabRoster" type="button" role="tab">
                    <i class="bi bi-calendar-check me-2"></i><span>Shopping Roster</span>
                </button>
                <button class="nav-link text-start py-2 px-3 rounded-3 d-flex align-items-center <?= $activeTab === 'prevmonth' ? 'active' : '' ?>"
                        data-bs-toggle="tab" data-bs-target="#tabPrevMonth" type="button" role="tab">
                    <i class="bi bi-clock-history me-2"></i><span>Prev. Month (<?= e($prevMonth) ?>)</span>
                </button>
            </div>

            <!-- Backup & Settings -->
            <div class="small fw-bold text-uppercase text-muted mb-2 px-1" style="font-size:0.7rem;">Reports &amp; Settings</div>
            <div class="d-grid gap-2">
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
            <a href="logout.php" class="btn btn-danger btn-sm w-100 fw-semibold py-2">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</aside>

<!-- Top Right Utility Header (Main Workspace) -->
<header class="mm-topbar bg-white border-bottom px-3 px-lg-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <span class="fw-bold text-dark"><?= e($mess['mess_name']) ?></span>
        <span class="badge bg-light text-dark border font-monospace"><?= e($billingMonth) ?></span>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap p-2 rounded-3 bg-light border">
        <form method="GET" class="d-flex align-items-center mb-0">
            <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
            <input type="month" name="month" value="<?= e($billingMonth) ?>" onchange="this.form.submit()"
                   class="form-control form-control-sm btn btn-outline-primary btn-sm fw-semibold" style="width:155px;" title="Select Billing Month">
        </form>

        <button class="btn btn-outline-info btn-sm position-relative fw-semibold"
                type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerNotify">
            <i class="bi bi-bell-fill"></i> <span class="d-none d-sm-inline">Chat &amp; Approvals</span>
            <?php if ($totalNotifications > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= $totalNotifications ?>
                </span>
            <?php endif; ?>
        </button>

        <?php if ($role === 'manager'): ?>
            <button class="btn btn-success btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalDeposit">
                <i class="bi bi-plus-lg"></i> Deposit
            </button>
            <button class="btn btn-dark btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalMember">
                <i class="bi bi-person-plus-fill"></i> Member
            </button>
        <?php endif; ?>
    </div>
</header>