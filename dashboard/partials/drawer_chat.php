<div class="offcanvas offcanvas-end" tabindex="-1" id="drawerNotify" style="width: 440px;">
    <div class="offcanvas-header border-bottom">
        <h6 class="offcanvas-title fw-bold"><i class="bi bi-chat-dots-fill text-success me-1"></i> Mess Chat &amp; Auto-Merge Approvals</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column justify-content-between">
        <div class="overflow-y-auto pe-1">
            <?php if ($role === 'manager' && count($pendingExpenses) > 0): ?>
                <div class="mb-4">
                    <div class="small fw-bold text-uppercase text-danger mb-2">Pending Expense Requests (<?= count($pendingExpenses) ?>)</div>
                    <?php foreach ($pendingExpenses as $pe): ?>
                    <div class="p-3 mb-2 rounded-3 border border-warning bg-warning-subtle small">
                        <div class="d-flex justify-content-between fw-bold">
                            <span><?= e($pe['full_name']) ?> (<?= strtoupper($pe['category']) ?>)</span>
                            <span><?= e(fmtMoney($cur, (float)$pe['amount'])) ?></span>
                        </div>
                        <div class="text-dark my-1"><?= e($pe['description']) ?></div>
                        <form method="POST" class="d-flex gap-2 mt-2">
                            <input type="hidden" name="action" value="decide_expense">
                            <input type="hidden" name="expense_id" value="<?= $pe['id'] ?>">
                            <button name="decision" value="approved" class="btn btn-success btn-sm py-0.5 px-2 fw-semibold">Approve &amp; Merge</button>
                            <button name="decision" value="rejected" class="btn btn-outline-danger btn-sm py-0.5 px-2">Reject</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="small fw-bold text-uppercase text-muted mb-2">Mess Chat &amp; Requests</div>
            <?php foreach ($chats as $c):
                $isMealReq   = ($c['request_type'] ?? '') === 'meal_request';
                $isAmountReq = ($c['request_type'] ?? '') === 'amount_request';
                $isActionReq = $isMealReq || $isAmountReq;
                $unprocessed = $isActionReq && (int)($c['is_processed'] ?? 0) === 0;
            ?>
                <div class="p-3 mb-2 rounded-3 border <?= $unprocessed ? 'border-primary bg-primary-subtle' : 'bg-light' ?> small">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong><?= e($c['full_name']) ?> <span class="text-muted fw-normal">(<?= e($c['member_code']) ?>)</span></strong>
                        <?php if ($isMealReq): ?>
                            <span class="badge <?= (int)($c['is_processed'] ?? 0) ? 'bg-success' : 'bg-primary' ?>">
                                <?= (int)($c['is_processed'] ?? 0) ? 'Merged' : 'Meal Request' ?>
                            </span>
                        <?php elseif ($isAmountReq): ?>
                            <span class="badge <?= (int)($c['is_processed'] ?? 0) ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= (int)($c['is_processed'] ?? 0) ? 'Merged' : 'Amount Request' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary">Chat</span>
                        <?php endif; ?>
                    </div>

                    <p class="mb-1 mt-1"><?= e($c['message']) ?></p>

                    <?php if ($isMealReq && !empty($c['target_date'])): ?>
                        <div class="small text-primary fw-semibold">
                            Date: <?= e($c['target_date']) ?> •
                            B: <?= number_format((float)($c['req_breakfast'] ?? 0), 1) ?> /
                            L: <?= number_format((float)($c['req_lunch'] ?? 0), 1) ?> /
                            D: <?= number_format((float)($c['req_dinner'] ?? 0), 1) ?>
                        </div>
                    <?php elseif ($isAmountReq && isset($c['req_amount'])): ?>
                        <div class="small fw-semibold text-dark">
                            Type: <span class="text-uppercase"><?= e($c['req_category'] ?? 'grocery') ?></span> •
                            Amount: <span class="font-monospace text-success"><?= e(fmtMoney($cur, (float)$c['req_amount'])) ?></span>
                            <?php if (!empty($c['req_description'])): ?>• <?= e($c['req_description']) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($role === 'manager' && $unprocessed): ?>
                        <form method="POST" class="d-flex gap-2 mt-2">
                            <input type="hidden" name="action" value="<?= $isMealReq ? 'approve_meal_request' : 'approve_amount_request' ?>">
                            <input type="hidden" name="chat_id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn btn-success btn-sm py-0.5 px-2 fw-semibold">Approve &amp; Merge</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" class="pt-3 border-top mt-3">
            <input type="hidden" name="action" value="send_chat">
            <div class="input-group input-group-sm mb-2">
                <select name="request_type" id="chatReqType" onchange="toggleChatReqFields()" class="form-select" style="max-width: 145px;">
                    <option value="meal_request">Meal Request</option>
                    <option value="amount_request">Amount Merge</option>
                    <option value="chat">General Chat</option>
                </select>
                <input type="text" name="message" class="form-control" placeholder="Message or note...">
                <button type="submit" class="btn btn-success fw-semibold">Send</button>
            </div>

            <div id="mealReqFields" class="row g-1 small bg-light p-2 rounded-3 border mb-1">
                <div class="col-5">
                    <label class="form-label mb-0 text-muted" style="font-size:0.7rem;">Date</label>
                    <input type="date" name="target_date" value="<?= e($today) ?>" class="form-control form-control-sm">
                </div>
                <div class="col-2">
                    <label class="form-label mb-0 text-muted" style="font-size:0.7rem;">B (0.5)</label>
                    <input type="number" step="0.5" min="0" max="5" name="req_breakfast" value="0.5" class="form-control form-control-sm px-1 text-center">
                </div>
                <div class="col-2">
                    <label class="form-label mb-0 text-muted" style="font-size:0.7rem;">L (1.0)</label>
                    <input type="number" step="0.5" min="0" max="5" name="req_lunch" value="1.0" class="form-control form-control-sm px-1 text-center">
                </div>
                <div class="col-3">
                    <label class="form-label mb-0 text-muted" style="font-size:0.7rem;">D (1.0)</label>
                    <input type="number" step="0.5" min="0" max="5" name="req_dinner" value="0.0" class="form-control form-control-sm px-1 text-center">
                </div>
            </div>

            <div id="amountReqFields" class="row g-1 small bg-light p-2 rounded-3 border d-none">
                <div class="col-4">
                    <label class="form-label mb-0 text-muted" style="font-size:0.7rem;">Category</label>
                    <select name="req_category" class="form-select form-select-sm">
                        <option value="grocery">Grocery</option>
                        <option value="misc">Misc Bill</option>
                    </select>
                </div>
                <div class="col-4">
                    <label class="form-label mb-0 text-muted" style="font-size:0.7rem;">Amount</label>
                    <input type="number" step="0.01" min="0.01" name="req_amount" placeholder="0.00" class="form-control form-control-sm">
                </div>
                <div class="col-4">
                    <label class="form-label mb-0 text-muted" style="font-size:0.7rem;">Items / Reason</label>
                    <input type="text" name="req_description" placeholder="e.g., Rice &amp; Oil" class="form-control form-control-sm">
                </div>
            </div>
        </form>
    </div>
</div>