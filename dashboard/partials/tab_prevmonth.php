<div class="tab-pane fade <?= $activeTab === 'prevmonth' ? 'show active' : '' ?>" id="tabPrevMonth">
    <div class="card-clean overflow-hidden">
        <div class="p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="fw-bold mb-1">Previous Month Calculation (<?= e($prevMonth) ?>)</h5>
                <p class="small text-muted mb-0">
                    Meal Rate: <strong><?= e(fmtMoney($cur, $prevSummary['cost_per_meal'])) ?></strong> •
                    Total Meals: <strong><?= number_format($prevSummary['total_meals'], 1) ?></strong> •
                    Misc Share: <strong><?= e(fmtMoney($cur, $prevSummary['misc_per_member'])) ?></strong>
                </p>
            </div>
            <?php if ($role === 'manager'): ?>
            <form method="POST" onsubmit="return confirm('Copy all closing net balances from <?= e($prevMonth) ?> into each member\'s Previous Balance?');">
                <input type="hidden" name="action" value="carry_forward_prev">
                <input type="hidden" name="current_tab" value="overview">
                <button type="submit" class="btn btn-success fw-semibold">
                    <i class="bi bi-arrow-right-circle me-1"></i> Carry Forward Closing Balances to Current Month
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-ledger mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Member</th>
                        <th class="text-end">Opening Bal</th>
                        <th class="text-end">Deposited</th>
                        <th class="text-end">Own Grocery</th>
                        <th class="text-end">Meals</th>
                        <th class="text-end">Meal Cost</th>
                        <th class="text-end">Misc Share</th>
                        <th class="text-end pe-4">Closing Net Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m):
                        $uid = (int)$m['id'];
                        $pd  = $prevSummary['members'][$uid];
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold"><?= e($m['full_name']) ?> <span class="badge bg-white text-secondary border font-monospace"><?= e($m['member_code']) ?></span></td>
                        <td class="text-end font-monospace"><?= ($pd['prev'] >= 0 ? '+' : '') . number_format($pd['prev'], 2) ?></td>
                        <td class="text-end font-monospace text-success"><?= number_format($pd['deposit'], 2) ?></td>
                        <td class="text-end font-monospace"><?= number_format($pd['grocery'], 2) ?></td>
                        <td class="text-end fw-bold"><?= number_format($pd['meals'], 1) ?></td>
                        <td class="text-end font-monospace"><?= number_format($pd['meal_cost'], 2) ?></td>
                        <td class="text-end font-monospace text-muted"><?= number_format($pd['misc'], 2) ?></td>
                        <td class="text-end pe-4">
                            <span class="net-pill <?= $pd['net'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                <?= ($pd['net'] >= 0 ? '+' : '') . e(fmtMoney($cur, $pd['net'])) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>