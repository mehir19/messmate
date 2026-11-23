<div class="tab-pane fade <?= $activeTab === 'overview' ? 'show active' : '' ?>" id="tabOverview">
    <div class="card-clean overflow-hidden mb-4">
        <div class="p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0">Member Accounts &amp; Meal Ledger <span class="badge bg-warning-subtle text-dark">(<?= e($billingMonth) ?>)</span></h5>
                <small class="text-muted">Manager-cash bazar is deducted from Manager Cash only; member-pocket bazar credits that member's Own Grocery.</small>
            </div>
            <span class="badge bg-warning-subtle text-dark border py-2 px-3">Misc Split: <?= e(fmtMoney($cur, $currSummary['misc_per_member'])) ?> / member</span>
        </div>
        <div class="table-responsive">
            <table class="table table-ledger mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Member ID &amp; name</th>
                        <th class="text-end">Prev. Month Balance</th>
                        <th class="text-end">Monthly Deposit</th>
                        <th class="text-end">Grocery Spent</th>
                        <th class="text-end">Meals</th>
                        <th class="text-end">Meal Cost</th>
                        <th class="text-end">Misc Share</th>
                        <th class="text-end">Net Balance</th>
                        <?php if ($role === 'manager'): ?><th class="text-end pe-4">Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m):
                        $uid   = (int)$m['id'];
                        $isYou = ($uid === $currentUid);
                        $d     = $currSummary['members'][$uid];

                        $hasMonthActivity = (
                            ($d['deposit'] ?? 0) != 0 ||
                            ($d['meals'] ?? 0) > 0 ||
                            ($d['grocery'] ?? 0) > 0
                        );

                        $sumPrevBal  += $d['prev'];
                        $sumDeposit  += $d['deposit'];
                        $sumOwnShop  += $d['grocery'];
                        $sumMeals    += $d['meals'];
                        $sumMealCost += $d['meal_cost'];
                        $sumMisc     += $d['misc'];
                        $sumNetBal   += $d['net'];
                    ?>
                    <tr class="<?= $isYou ? 'row-you' : '' ?>">
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge bg-white text-dark border"><?= e($m['member_code']) ?></span>
                                <div>
                                    <span class="fw-semibold text-dark"><?= e($m['full_name']) ?></span>
                                    <?php if ($isYou): ?>
                                        <span class="badge bg-success text-white">You</span>
                                    <?php endif; ?>
                                    <?php
                                        $roomLabel = trim((string)($m['room_no'] ?? '')) !== '' ? 'Room: ' . e($m['room_no']) : 'Room: —';
                                        $seatLabel = trim((string)($m['seat_no'] ?? '')) !== '' ? 'Seat: ' . e($m['seat_no']) : 'Seat: —';
                                    ?>
                                    <div class="small text-muted mt-0.5"><?= $roomLabel ?> • <?= $seatLabel ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-end <?= $d['prev'] < 0 ? 'text-danger' : ($d['prev'] > 0 ? 'text-success' : 'text-secondary') ?>">
                            <?= ($d['prev'] > 0 ? '+' : '') . number_format($d['prev'], 2) ?>
                        </td>

                        <td class="text-end">
                            <?= number_format($d['deposit'], 2) ?>
                        </td>
                        <td class="text-end" style="color:#b45309;"><?= number_format($d['grocery'], 2) ?></td>
                        <td class="text-end fw-bold"><?= number_format($d['meals'], 1) ?></td>
                        <td class="text-end"><?= number_format($d['meal_cost'], 2) ?></td>
                        <td class="text-end text-primary"><?= number_format($d['misc'], 2) ?></td>
                        <td class="text-end">
                            <span class="net-pill <?= $d['net'] >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                <?= ($d['net'] >= 0 ? '+' : '') . e(fmtMoney($cur, $d['net'])) ?>
                            </span>
                        </td>
                        <?php if ($role === 'manager'): ?>
                        <td class="text-end pe-4">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2"
                                onclick="openEditMember(<?= $uid ?>, '<?= e($m['full_name']) ?>', '<?= e($m['room_no'] ?? '') ?>', '<?= e($m['seat_no'] ?? '') ?>', <?= (float)$m['previous_balance'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($m['role'] !== 'manager'): ?>
                                <?php if ($hasMonthActivity): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 disabled"
                                        title="Has meals, deposits, or expenses in <?= e($billingMonth) ?>" aria-disabled="true">
                                        <i class="bi bi-lock-fill"></i>
                                    </button>
                                <?php else: ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove <?= e($m['full_name']) ?> from active mess?');">
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="fw-bold">
                    <tr>
                        <td class="ps-4 text-uppercase text-secondary">Total (<?= count($members) ?> Members)</td>
                        <td class="text-end"><?= ($sumPrevBal >= 0 ? '+' : '') . number_format($sumPrevBal, 2) ?></td>
                        <td class="text-end"><?= number_format($sumDeposit, 2) ?></td>
                        <td class="text-end"><?= number_format($sumOwnShop, 2) ?></td>
                        <td class="text-end"><?= number_format($sumMeals, 1) ?></td>
                        <td class="text-end"><?= number_format($sumMealCost, 2) ?></td>
                        <td class="text-end"><?= number_format($sumMisc, 2) ?></td>
                        <td class="text-end">
                            <span class="net-pill <?= $sumNetBal >= 0 ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                                <?= ($sumNetBal >= 0 ? '+' : '') . e(fmtMoney($cur, $sumNetBal)) ?>
                            </span>
                        </td>
                        <?php if ($role === 'manager'): ?><td></td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card-clean card-history p-4">
        <div class="mb-3">
            <h6 class="fw-bold mb-0">Deposit History &amp; Repeat-Safe Balance Correction Ledger</h6>
            <small class="text-muted">Click <strong>Fix</strong> next to any deposit to see its current effective total and adjust safely multiple times.</small>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light small text-uppercase text-muted">
                    <tr>
                        <th>Date</th>
                        <th>Member</th>
                        <th>Type</th>
                        <th>Note / Audit Reason</th>
                        <th class="text-end">Amount</th>
                        <?php if ($role === 'manager'): ?><th class="text-end">Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allDeposits as $dp):
                        $isCorr = $dp['entry_type'] === 'correction';
                        $origAmt = (float)$dp['amount'];
                        $adjAmt = $correctionsByDeposit[(int)$dp['id']] ?? 0.0;
                        $currEffAmt = $origAmt + $adjAmt;
                    ?>
                    <tr class="<?= $isCorr ? 'table-warning' : '' ?>">
                        <td class="text-muted small"><?= date('M d, H:i', strtotime($dp['created_at'])) ?></td>
                        <td class="fw-semibold"><?= e($dp['full_name']) ?> <span class="text-muted">(<?= e($dp['member_code']) ?>)</span></td>
                        <td>
                            <span class="badge <?= $isCorr ? 'bg-warning text-dark' : 'bg-success-subtle text-success' ?>">
                                <?= $isCorr ? 'Correction' : 'Deposit' ?>
                            </span>
                        </td>
                        <td><?= e($dp['note']) ?></td>
                        <td class="text-end fw-bold <?= $currEffAmt < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= ($currEffAmt >= 0 ? '+' : '') . e(fmtMoney($cur, $currEffAmt)) ?>
                            <?php if (!$isCorr && $adjAmt != 0): ?>
                                <div class="small text-muted fw-normal">Orig: <?= e(fmtMoney($cur, $origAmt)) ?></div>
                            <?php endif; ?>
                        </td>
                        <?php if ($role === 'manager'): ?>
                        <td class="text-end">
                            <?php if (!$isCorr): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2"
                                    onclick="openFixModal(<?= (int)$dp['id'] ?>, '<?= e($dp['full_name']) ?>', <?= $currEffAmt ?>)">
                                    <i class="bi bi-pencil-square"></i> Fix
                                </button>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>