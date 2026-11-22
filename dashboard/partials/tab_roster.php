<div class="tab-pane fade <?= $activeTab === 'roster' ? 'show active' : '' ?>" id="tabRoster">
    <div class="card-clean p-4">
        <div class="mb-3">
            <h5 class="fw-bold mb-0">Grocery Shopping Duty Schedule</h5>
            <small class="text-muted">Assigned dates and responsible members for market shopping.</small>
        </div>

        <?php if ($role === 'manager'): ?>
        <form method="POST" class="row g-2 mb-4 p-3 bg-light rounded-3 border">
            <input type="hidden" name="action" value="add_roster">
            <input type="hidden" name="current_tab" value="roster">
            <div class="col-md-3">
                <select name="assigned_user_id" class="form-select">
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" name="duty_date" required value="<?= e($today) ?>" class="form-control">
            </div>
            <div class="col-md-4">
                <input type="text" name="items_note" placeholder="Shopping note (e.g., Weekly meat & fish)" class="form-control">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100 fw-semibold">Assign Duty</button>
            </div>
        </form>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th>Date</th>
                        <th>Responsible Member</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <?php if ($role === 'manager'): ?><th class="text-end">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rosters as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($r['duty_date']) ?></td>
                        <td><?= e($r['full_name']) ?> <span class="badge bg-light text-dark border"><?= e($r['member_code']) ?></span></td>
                        <td class="text-muted"><?= e($r['items_note'] ?: 'Regular grocery bazar') ?></td>
                        <td>
                            <span class="badge <?= $r['status'] === 'completed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= ucfirst(e($r['status'])) ?>
                            </span>
                        </td>
                        <?php if ($role === 'manager'): ?>
                        <td class="text-end text-nowrap">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_roster">
                                <input type="hidden" name="current_tab" value="roster">
                                <input type="hidden" name="roster_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-outline-success btn-sm py-1 px-2">Toggle Done</button>
                            </form>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2"
                                onclick="openEditRoster(<?= $r['id'] ?>, <?= (int)$r['assigned_user_id'] ?>, '<?= e($r['duty_date']) ?>', '<?= e($r['items_note']) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this shopping duty?');">
                                <input type="hidden" name="action" value="delete_roster">
                                <input type="hidden" name="current_tab" value="roster">
                                <input type="hidden" name="roster_id" value="<?= $r['id'] ?>">
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