<div class="tab-pane fade <?= $activeTab === 'roster' ? 'show active' : '' ?>" id="tabRoster">
    <div class="card-clean p-4">
        <div class="mb-3">
            <h5 class="fw-bold mb-0">Grocery Shopping Duty Schedule</h5>
            <small class="text-muted">Assign single dates or date ranges and cycle status (Upcoming → Running → Completed).</small>
        </div>

        <?php if ($role === 'manager'): ?>
        <form method="POST" class="row g-2 mb-4 p-3 bg-light rounded-3 border">
            <input type="hidden" name="action" value="add_roster">
            <input type="hidden" name="current_tab" value="roster">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Member</label>
                <select name="assigned_user_id" class="form-select">
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Start Date</label>
                <input type="date" name="duty_date" required value="<?= e($today) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">End Date (Range)</label>
                <input type="date" name="duty_end_date" value="<?= e($today) ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Shopping Note</label>
                <input type="text" name="items_note" placeholder="e.g., Weekly meat & fish" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-dark w-100 fw-semibold">Assign Duty</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Shopping Date / Range</th>
                        <th>Responsible Member</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <?php if ($role === 'manager'): ?><th class="text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rosters as $r):
                        $st = in_array($r['status'] ?? '', ['upcoming', 'running', 'completed'], true)
                            ? $r['status']
                            : 'upcoming';
                        $startDate = (string)$r['duty_date'];
                        $endDate   = !empty($r['duty_end_date']) ? (string)$r['duty_end_date'] : '';
                        $dateRangeText = ($endDate !== '' && $endDate !== $startDate)
                            ? e($startDate) . ' <span class="text-muted">→</span> ' . e($endDate)
                            : e($startDate);
                        $badgeClass = match ($st) {
                            'completed' => 'bg-success text-white',
                            'running'   => 'bg-primary text-white',
                            default     => 'bg-warning text-dark',
                        };
                    ?>
                    <tr data-roster-row="<?= (int)$r['id'] ?>">
                        <td class="fw-semibold text-nowrap"><?= $dateRangeText ?></td>
                        <td><?= e($r['full_name']) ?> <span class="badge bg-light text-dark border font-monospace"><?= e($r['member_code']) ?></span></td>
                        <td class="text-muted"><?= e($r['items_note'] ?: 'Regular grocery bazar') ?></td>
                        <td>
                            <span class="badge roster-status-badge <?= $badgeClass ?>" data-status="<?= e($st) ?>">
                                <?= ucfirst(e($st)) ?>
                            </span>
                        </td>
                        <?php if ($role === 'manager'): ?>
                        <td class="text-end text-nowrap">
                            <form method="POST" class="d-inline roster-toggle-form" onsubmit="return toggleRosterStatusAjax(this);">
                                <input type="hidden" name="action" value="cycle_roster_status">
                                <input type="hidden" name="current_tab" value="roster">
                                <input type="hidden" name="roster_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-outline-primary btn-sm py-1 px-2 roster-toggle-btn">
                                    <i class="bi bi-arrow-repeat"></i> Next Label
                                </button>
                            </form>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2"
                                onclick="openEditRoster(<?= (int)$r['id'] ?>, <?= (int)$r['assigned_user_id'] ?>, '<?= e($startDate) ?>', '<?= e($endDate ?: $startDate) ?>', '<?= e($r['items_note']) ?>', '<?= e($st) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this shopping duty?');">
                                <input type="hidden" name="action" value="delete_roster">
                                <input type="hidden" name="current_tab" value="roster">
                                <input type="hidden" name="roster_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>