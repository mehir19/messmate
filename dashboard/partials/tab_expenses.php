<div class="tab-pane fade <?= $activeTab === 'expenses' ? 'show active' : '' ?>" id="tabExpenses">
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card-clean p-4 mb-4">
                <h6 class="fw-bold mb-1"><i class="bi bi-bag-plus-fill text-warning me-1"></i> Log Grocery / Bazar Purchase</h6>
                <p class="small text-muted mb-3">Choose <strong>Manager Cash</strong> to deduct from mess funds without crediting a member, or <strong>Member Pocket</strong> to credit that member's Own Grocery.</p>
                <form method="POST" class="row g-2">
                    <input type="hidden" name="action" value="add_expense">
                    <input type="hidden" name="current_tab" value="expenses">
                    <input type="hidden" name="category" value="grocery">
                    <?php if ($role === 'manager'): ?>
                    <div class="col-6">
                        <select name="funding_mode" id="addGroceryFundingMode" onchange="toggleAddGroceryPayer()" class="form-select">
                            <option value="manager_cash" selected>From: Manager Cash (No Member Credit)</option>
                            <option value="member_pocket">From: Member Own Pocket</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <input type="number" step="0.01" name="amount" required placeholder="Amount" class="form-control">
                    </div>
                    <div class="col-12 d-none" id="addGroceryPayerWrap">
                        <select name="paid_by" class="form-select">
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $m['id'] == $currentUid ? 'selected' : '' ?>>Member Paid: <?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="col-12">
                        <input type="number" step="0.01" name="amount" required placeholder="Amount Spent (From Your Pocket)" class="form-control">
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <input type="text" name="description" required placeholder="Items purchased (e.g., Rice 10kg, Chicken, Vegetables)" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-warning fw-semibold w-100">
                            <?= $role === 'manager' ? 'Add & Approve Grocery Purchase' : 'Submit Grocery Expense for Approval & Auto-Merge' ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="card-clean p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0">Grocery Expenditure Log</h6>
                    <span class="badge bg-warning-subtle text-dark fs-6">Total: <?= e(fmtMoney($cur, $currSummary['total_grocery'])) ?></span>
                </div>
                <div class="table-responsive" style="max-height:360px;">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Items</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                                <?php if ($role === 'manager'): ?><th class="text-end">Edit/Del</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groceryLogs as $g):
                                $src = $g['funding_source'] ?? 'member_pocket';
                            ?>
                            <tr>
                                <td><?= e($g['description']) ?><br><span class="text-muted small"><?= e(fmtDateDmy($g['expense_date'])) ?></span></td>
                                <td>
                                    <?php if ($src === 'manager_cash'): ?>
                                        <span class="badge bg-dark-subtle text-dark">Manager Cash</span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-dark"><?= e($g['full_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $g['status'] === 'approved' ? 'bg-success-subtle text-success' : ($g['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-danger-subtle text-danger') ?>">
                                        <?= e($g['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold"><?= e(fmtMoney($cur, (float)$g['amount'])) ?></td>
                                <?php if ($role === 'manager'): ?>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1.5"
                                        onclick="openEditExpense(<?= $g['id'] ?>, '<?= e($g['description']) ?>', <?= (float)$g['amount'] ?>, <?= (int)$g['paid_by_user_id'] ?>, '<?= e($src) ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this grocery entry?');">
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="current_tab" value="expenses">
                                        <input type="hidden" name="expense_id" value="<?= $g['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1.5"><i class="bi bi-trash"></i></button>
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

        <div class="col-lg-6">
            <div class="card-clean p-4">
                <h6 class="fw-bold mb-1"><i class="bi bi-receipt text-primary me-1"></i> Miscellaneous &amp; Fixed Bills</h6>
                <p class="small text-muted mb-3">Cook salary, Wi-Fi, gas, electricity — split equally across all <?= count($members) ?> members (<?= e(fmtMoney($cur, $currSummary['misc_per_member'])) ?> each).</p>
                <form method="POST" class="row g-2 mb-4">
                    <input type="hidden" name="action" value="add_expense">
                    <input type="hidden" name="current_tab" value="expenses">
                    <input type="hidden" name="category" value="misc">
                    <div class="col-7">
                        <input type="text" name="description" required placeholder="Bill name (e.g., Cook Salary, Wi-Fi)" class="form-control">
                    </div>
                    <div class="col-5">
                        <input type="number" step="0.01" name="amount" required placeholder="Amount" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary fw-semibold w-100">Add Misc / Fixed Bill</button>
                    </div>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-bold text-uppercase text-muted">Misc Bills Log</span>
                    <span class="badge bg-primary-subtle text-primary fs-6">Total: <?= e(fmtMoney($cur, $currSummary['total_misc'])) ?></span>
                </div>
                <div class="table-responsive" style="max-height:360px;">
                    <table class="table align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Bill Description</th>
                                <th>Paid By</th>
                                <th class="text-end">Amount</th>
                                <?php if ($role === 'manager'): ?><th class="text-end">Edit/Del</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($miscLogs as $ms): ?>
                            <tr>
                                <td><?= e($ms['description']) ?></td>
                                <td><?= e($ms['full_name']) ?></td>
                                <td class="text-end fw-bold"><?= e(fmtMoney($cur, (float)$ms['amount'])) ?></td>
                                <?php if ($role === 'manager'): ?>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1.5"
                                        onclick="openEditExpense(<?= $ms['id'] ?>, '<?= e($ms['description']) ?>', <?= (float)$ms['amount'] ?>, <?= (int)$ms['paid_by_user_id'] ?>, '<?= e($ms['funding_source'] ?? 'member_pocket') ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this misc bill?');">
                                        <input type="hidden" name="action" value="delete_expense">
                                        <input type="hidden" name="current_tab" value="expenses">
                                        <input type="hidden" name="expense_id" value="<?= $ms['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1.5"><i class="bi bi-trash"></i></button>
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
    </div>
</div>