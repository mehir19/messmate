<!-- MODAL: ADD MEMBER -->
<div class="modal fade" id="modalMember" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Add New Mess Member</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_member">
                <div class="mb-3"><label class="form-label small fw-semibold">Full Name</label><input type="text" name="full_name" required class="form-control"></div>
                <div class="mb-3"><label class="form-label small fw-semibold">Previous Month Balance (+ Credit / - Due)</label><input type="number" step="0.01" name="previous_balance" value="0" class="form-control"></div>
                <div><label class="form-label small fw-semibold">Temporary Login PIN</label><input type="text" name="pin" value="123456" required class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark fw-semibold">Create Member</button></div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT MEMBER -->
<div class="modal fade" id="modalEditMember" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Edit Member Details</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_member">
                <input type="hidden" name="user_id" id="editMemberId">
                <div class="mb-3"><label class="form-label small fw-semibold">Full Name</label><input type="text" name="full_name" id="editMemberName" required class="form-control"></div>
                <div class="mb-3"><label class="form-label small fw-semibold">Previous Balance (+ Credit / - Due)</label><input type="number" step="0.01" name="previous_balance" id="editMemberPrev" required class="form-control"></div>
                <div><label class="form-label small fw-semibold">Reset PIN / Password (Leave blank to keep current)</label><input type="text" name="new_pin" placeholder="Optional new PIN" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary fw-semibold">Save Changes</button></div>
        </form>
    </div>
</div>

<!-- MODAL: RECORD DEPOSIT -->
<div class="modal fade" id="modalDeposit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Record Member Deposit</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add_deposit">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Select Member</label>
                    <select name="user_id" class="form-select">
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label small fw-semibold">Deposit Amount</label><input type="number" step="0.01" name="amount" required class="form-control"></div>
                <div><label class="form-label small fw-semibold">Payment Note</label><input type="text" name="note" placeholder="e.g., Cash / bKash" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success fw-semibold">Save Deposit</button></div>
        </form>
    </div>
</div>

<!-- MODAL: REPEAT-SAFE DEPOSIT CORRECTION -->
<div class="modal fade" id="modalFixDeposit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-danger">Fix Deposit Amount</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="correct_deposit">
                <input type="hidden" name="deposit_id" id="fixDepositId">

                <div class="p-3 rounded-3 bg-light border mb-3 small">
                    <div>Member: <strong id="fixMemberName"></strong></div>
                    <div>Current Effective Amount: <strong id="fixCurrentDisplay" class="text-success font-monospace"></strong></div>
                    <div class="mt-1">Calculated Adjustment: <strong id="fixDiffPreview" class="font-monospace">0.00</strong></div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">New Total Amount</label>
                        <input type="number" step="0.01" name="correct_amount" id="fixCorrectInput" oninput="syncFromNewTotal()" required class="form-control fw-bold">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Or Adjust By (+/-)</label>
                        <input type="number" step="0.01" id="fixDeltaInput" oninput="syncFromDelta()" placeholder="e.g. -1000 or +500" class="form-control">
                    </div>
                </div>

                <div>
                    <label class="form-label small fw-semibold">Reason for Correction</label>
                    <input type="text" name="reason" required placeholder="e.g., Returned 1000 to member" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-danger fw-semibold">Apply Fix Automatically</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT EXPENSE (GROCERY / MISC) -->
<div class="modal fade" id="modalEditExpense" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Edit Expense Entry</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_expense">
                <input type="hidden" name="current_tab" value="expenses">
                <input type="hidden" name="expense_id" id="editExpId">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Cash Source</label>
                    <select name="funding_mode" id="editExpFundingMode" onchange="toggleEditExpensePayer()" class="form-select">
                        <option value="manager_cash">Manager Cash (No Member Credit)</option>
                        <option value="member_pocket">Member Own Pocket</option>
                    </select>
                </div>
                <div class="mb-3" id="editExpPayerWrap">
                    <label class="form-label small fw-semibold">Member Paid</label>
                    <select name="paid_by" id="editExpPaidBy" class="form-select">
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label small fw-semibold">Description</label><input type="text" name="description" id="editExpDesc" required class="form-control"></div>
                <div><label class="form-label small fw-semibold">Amount</label><input type="number" step="0.01" name="amount" id="editExpAmount" required class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary fw-semibold">Update Entry</button></div>
        </form>
    </div>
</div>

<!-- MODAL: EDIT SHOPPING ROSTER -->
<div class="modal fade" id="modalEditRoster" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content rounded-4">
            <div class="modal-header"><h6 class="modal-title fw-bold">Edit Shopping Duty</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="edit_roster">
                <input type="hidden" name="current_tab" value="roster">
                <input type="hidden" name="roster_id" id="editRosterId">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Assigned Member</label>
                    <select name="assigned_user_id" id="editRosterUid" class="form-select">
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['full_name']) ?> (<?= e($m['member_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label small fw-semibold">Duty Date</label><input type="date" name="duty_date" id="editRosterDate" required class="form-control"></div>
                <div><label class="form-label small fw-semibold">Notes</label><input type="text" name="items_note" id="editRosterNote" class="form-control"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark fw-semibold">Save Duty</button></div>
        </form>
    </div>
</div>

<!-- MODAL: SUBSCRIPTION PLANS -->
<div class="modal fade" id="modalSub" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 p-3">
            <div class="modal-header border-0"><h6 class="modal-title fw-bold">Subscription Plans</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <form method="POST" class="border rounded-3 p-3 h-100 d-flex flex-column justify-content-between">
                            <input type="hidden" name="action" value="activate_plan"><input type="hidden" name="plan" value="starter">
                            <div><h6 class="fw-bold">Starter Mess</h6><div class="fs-4 fw-bold text-success">৳199<small class="fs-6 text-muted">/mo</small></div><p class="small text-muted mt-1">Up to 12 members</p></div>
                            <button type="submit" class="btn btn-dark btn-sm w-100">Activate Starter</button>
                        </form>
                    </div>
                    <div class="col-6">
                        <form method="POST" class="border border-2 border-success rounded-3 p-3 h-100 d-flex flex-column justify-content-between">
                            <input type="hidden" name="action" value="activate_plan"><input type="hidden" name="plan" value="pro">
                            <div><h6 class="fw-bold">Hostel Pro</h6><div class="fs-4 fw-bold text-success">৳499<small class="fs-6 text-muted">/mo</small></div><p class="small text-muted mt-1">Up to 50 members</p></div>
                            <button type="submit" class="btn btn-success btn-sm w-100">Activate Pro</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>