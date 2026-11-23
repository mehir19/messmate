const CURRENCY_MAP = {
    'BDT': '৳',
    '৳': '৳',
    'INR': '₹',
    '₹': '₹',
    'USD': '$',
    '$': '$'
};
const CUR_CODE = window.MESSMATE_CONFIG?.currency || 'BDT';
const CUR_SYM = CURRENCY_MAP[CUR_CODE] || CUR_CODE;

let currentFixBase = 0;

function formatCurJs(val) {
    const num = Number(val) || 0;
    return (num < 0 ? '-' : '') + CUR_SYM + Math.abs(num).toFixed(2);
}

function updateFixPreview(newTotal) {
    const diff = Number((newTotal - currentFixBase).toFixed(2));
    const el = document.getElementById('fixDiffPreview');
    if (!el) return;
    el.textContent = (diff > 0 ? '+' : '') + formatCurJs(diff);
    el.className = 'font-monospace ' + (diff > 0 ? 'text-success' : (diff < 0 ? 'text-danger' : 'text-secondary'));
}

function syncFromNewTotal() {
    const newTotal = parseFloat(document.getElementById('fixCorrectInput').value);
    if (isNaN(newTotal)) return;
    const diff = Number((newTotal - currentFixBase).toFixed(2));
    document.getElementById('fixDeltaInput').value = diff;
    updateFixPreview(newTotal);
}

function syncFromDelta() {
    const delta = parseFloat(document.getElementById('fixDeltaInput').value);
    if (isNaN(delta)) return;
    const newTotal = Number((currentFixBase + delta).toFixed(2));
    document.getElementById('fixCorrectInput').value = newTotal;
    updateFixPreview(newTotal);
}

function openFixModal(depositId, memberName, currentEffectiveAmount) {
    currentFixBase = Number(currentEffectiveAmount) || 0;
    document.getElementById('fixDepositId').value = depositId;
    document.getElementById('fixMemberName').textContent = memberName;
    document.getElementById('fixCurrentDisplay').textContent = formatCurJs(currentFixBase);
    document.getElementById('fixCorrectInput').value = currentFixBase;
    document.getElementById('fixDeltaInput').value = 0;
    updateFixPreview(currentFixBase);
    new bootstrap.Modal(document.getElementById('modalFixDeposit')).show();
}

function openEditMember(uid, name, roomNo, seatNo, prevBal) {
    document.getElementById('editMemberId').value = uid;
    document.getElementById('editMemberName').value = name;
    const roomEl = document.getElementById('editMemberRoom');
    const seatEl = document.getElementById('editMemberSeat');
    if (roomEl) roomEl.value = roomNo || '';
    if (seatEl) seatEl.value = seatNo || '';
    document.getElementById('editMemberPrev').value = prevBal;
    new bootstrap.Modal(document.getElementById('modalEditMember')).show();
}

function toggleAddGroceryPayer() {
    const sel = document.getElementById('addGroceryFundingMode');
    const wrap = document.getElementById('addGroceryPayerWrap');
    if (!sel || !wrap) return;
    wrap.classList.toggle('d-none', sel.value !== 'member_pocket');
}

function toggleEditExpensePayer() {
    const sel = document.getElementById('editExpFundingMode');
    const wrap = document.getElementById('editExpPayerWrap');
    if (!sel || !wrap) return;
    wrap.classList.toggle('d-none', sel.value !== 'member_pocket');
}

function openEditExpense(expId, desc, amount, paidBy, fundingMode) {
    document.getElementById('editExpId').value = expId;
    document.getElementById('editExpDesc').value = desc;
    document.getElementById('editExpAmount').value = amount;
    document.getElementById('editExpPaidBy').value = paidBy;
    const modeSelect = document.getElementById('editExpFundingMode');
    if (modeSelect) {
        modeSelect.value = fundingMode || 'member_pocket';
        toggleEditExpensePayer();
    }
    new bootstrap.Modal(document.getElementById('modalEditExpense')).show();
}

const ROSTER_BADGE_CLASSES = {
    upcoming: 'badge roster-status-badge bg-warning text-dark',
    running: 'badge roster-status-badge bg-primary text-white',
    completed: 'badge roster-status-badge bg-success text-white',
};

function toggleRosterStatusAjax(formEl) {
    const btn = formEl.querySelector('.roster-toggle-btn');
    const rosterIdInput = formEl.querySelector('input[name="roster_id"]');
    if (!rosterIdInput || !window.fetch) return true;

    const formData = new FormData(formEl);
    formData.set('ajax', '1');

    if (btn) btn.disabled = true;

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
    })
        .then(function (res) {
            if (!res.ok) throw new Error('Request failed');
            return res.json();
        })
        .then(function (data) {
            if (!data || !data.ok) throw new Error(data?.error || 'Invalid response');
            const row = document.querySelector('tr[data-roster-row="' + data.roster_id + '"]');
            const badge = row ? row.querySelector('.roster-status-badge') : null;
            if (badge) {
                badge.dataset.status = data.status;
                badge.className = ROSTER_BADGE_CLASSES[data.status] || ROSTER_BADGE_CLASSES.upcoming;
                badge.textContent = data.label;
            }
        })
        .catch(function () {
            formEl.submit();
        })
        .finally(function () {
            if (btn) btn.disabled = false;
        });

    return false;
}

function openEditRoster(rosterId, uid, dutyDate, dutyEndDate, note, status) {
    document.getElementById('editRosterId').value = rosterId;
    document.getElementById('editRosterUid').value = uid;
    document.getElementById('editRosterDate').value = dutyDate;
    const endEl = document.getElementById('editRosterEndDate');
    if (endEl) endEl.value = dutyEndDate || dutyDate;
    document.getElementById('editRosterNote').value = note || '';
    const statusEl = document.getElementById('editRosterStatus');
    if (statusEl) statusEl.value = status || 'upcoming';
    new bootstrap.Modal(document.getElementById('modalEditRoster')).show();
}

function toggleChatReqFields() {
    const mode = document.getElementById('chatReqType').value;
    document.getElementById('mealReqFields').classList.toggle('d-none', mode !== 'meal_request');
    document.getElementById('amountReqFields').classList.toggle('d-none', mode !== 'amount_request');
}