<div class="tab-pane fade <?= $activeTab === 'meals' ? 'show active' : '' ?>" id="tabMeals">
    <div class="card-clean p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-0">Daily Meal Entry <span class="badge bg-warning-subtle text-dark">(<?= e($selectedMealDate) ?>)</span></h5>
                <small class="text-muted">Breakfast = 0.5 • Lunch = 1.0 • Dinner = 1.0</small>
            </div>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="meals">
                <input type="hidden" name="month" value="<?= e($billingMonth) ?>">
                <input type="date" name="meal_date" value="<?= e($selectedMealDate) ?>" class="form-control form-control-sm">
                <button type="submit" class="btn btn-outline-secondary btn-sm">Load Date</button>
            </form>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_meals">
            <input type="hidden" name="current_tab" value="meals">
            <input type="hidden" name="meal_date" value="<?= e($selectedMealDate) ?>">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Member</th>
                            <th class="text-center" style="width:140px;">Breakfast (0.5)</th>
                            <th class="text-center" style="width:140px;">Lunch (1.0)</th>
                            <th class="text-center" style="width:140px;">Dinner (1.0)</th>
                            <th class="text-end">Day Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m):
                            $uid = (int)$m['id'];
                            $tm  = $todayMealsMap[$uid] ?? ['breakfast' => 0.0, 'lunch' => 1.0, 'dinner' => 1.0];
                            $daySum = (float)$tm['breakfast'] + (float)$tm['lunch'] + (float)$tm['dinner'];
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= e($m['full_name']) ?> <span class="badge bg-light text-dark border"><?= e($m['member_code']) ?></span></td>
                            <td><input type="number" step="0.5" min="0" max="5" name="meals[<?= $uid ?>][b]" value="<?= $tm['breakfast'] ?>" <?= $role !== 'manager' ? 'disabled' : '' ?> class="form-control text-center"></td>
                            <td><input type="number" step="0.5" min="0" max="5" name="meals[<?= $uid ?>][l]" value="<?= $tm['lunch'] ?>" <?= $role !== 'manager' ? 'disabled' : '' ?> class="form-control text-center"></td>
                            <td><input type="number" step="0.5" min="0" max="5" name="meals[<?= $uid ?>][d]" value="<?= $tm['dinner'] ?>" <?= $role !== 'manager' ? 'disabled' : '' ?> class="form-control text-center"></td>
                            <td class="text-end fw-bold"><?= number_format($daySum, 1) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($role === 'manager'): ?>
                <button type="submit" class="btn btn-success fw-semibold px-4">Save Meal Records</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-clean p-4">
        <div class="mb-3">
            <h5 class="fw-bold mb-0">Full Month Meal Chart (<?= e($billingMonth) ?>)</h5>
            <small class="text-muted">Complete day-by-day meal breakdown (Day 1 to <?= $daysInMonth ?>) for every member.</small>
        </div>
        <div class="table-responsive">
            <table class="table meal-matrix align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="sticky-col bg-dark text-white">Member</th>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <th><?= $d ?></th>
                        <?php endfor; ?>
                        <th class="bg-success text-white">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $dailyColTotals = array_fill(1, $daysInMonth, 0.0);
                    foreach ($members as $m):
                        $uid = (int)$m['id'];
                        $rowSum = 0.0;
                    ?>
                    <tr>
                        <td class="sticky-col fw-semibold"><?= e($m['full_name']) ?></td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++):
                            $val = $mealMatrix[$uid][$d] ?? 0.0;
                            $rowSum += $val;
                            $dailyColTotals[$d] += $val;
                        ?>
                            <td class="<?= $val > 0 ? 'fw-semibold text-dark' : 'text-muted opacity-50' ?>">
                                <?= $val > 0 ? number_format($val, 1) : '0' ?>
                            </td>
                        <?php endfor; ?>
                        <td class="fw-bold bg-success-subtle text-success"><?= number_format($rowSum, 1) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td class="sticky-col bg-secondary text-white">Daily Total</td>
                        <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                            <td><?= number_format($dailyColTotals[$d], 1) ?></td>
                        <?php endfor; ?>
                        <td class="bg-dark text-white"><?= number_format(array_sum($dailyColTotals), 1) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>