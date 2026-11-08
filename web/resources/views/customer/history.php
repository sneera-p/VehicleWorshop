<?php
/**
 * Customer Repair History
 *
 * @var array<string, string> $assets
 * @var list<array{
 *     id: int,
 *     date: string,
 *     vehicle_model: string,
 *     license_plate: string,
 *     service: string,
 *     amount: int,
 *     mechanic: string,
 *     details: string
 * }> $repairs
 */

$escape = static fn (string|int|float|null $value): string => htmlspecialchars(
    (string) ($value ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8',
);

$total_work = count($repairs);
$amount_spent = array_sum(array_column($repairs, 'amount'));
$last_service = $repairs[0]['date'] ?? '—';
?>

<div class="dashboard-wrapper repair-history-page">
    <header class="repair-history-header">
        <div>
            <h1 class="dashboard-heading">Repair History</h1>
            <p class="dashboard-subheading">Review the completed repairs and services for your vehicles.</p>
        </div>
    </header>

    <section class="repair-history-stats" aria-label="Repair History Summary">
        <article class="repair-history-stat">
            <span class="repair-history-stat-label">TOTAL WORK</span>
            <span class="repair-history-stat-value"><?= $total_work ?></span>
        </article>
        <article class="repair-history-stat">
            <span class="repair-history-stat-label">AMOUNT SPENT</span>
            <span class="repair-history-stat-value">LKR <?= number_format($amount_spent) ?></span>
        </article>
        <article class="repair-history-stat">
            <span class="repair-history-stat-label">LAST SERVICE</span>
            <span class="repair-history-stat-value"><?= $escape($last_service) ?></span>
        </article>
    </section>

    <section class="repair-history-card" aria-label="Repair History List">
        <h2 class="repair-history-list-heading">Repair History</h2>
        <div class="repair-history-scroll" role="region" aria-label="Repair records" tabindex="0">
            <table class="repair-history-table">
                <thead>
                    <tr>
                        <th scope="col">DATE</th>
                        <th scope="col">VEHICLE</th>
                        <th scope="col">REPAIR</th>
                        <th scope="col">MECHANIC</th>
                        <th scope="col">AMOUNT</th>
                        <th scope="col">DETAILS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($repairs === []): ?>
                        <tr>
                            <td colspan="6" class="repair-history-empty">No repair history found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($repairs as $repair): ?>
                            <tr>
                                <td class="repair-history-date"><?= $escape($repair['date']) ?></td>
                                <td>
                                    <div class="repair-history-vehicle">
                                        <span class="repair-history-vehicle-model"><?= $escape($repair['vehicle_model']) ?></span>
                                        <span class="repair-history-plate"><?= $escape($repair['license_plate']) ?></span>
                                    </div>
                                </td>
                                <td><?= $escape($repair['service']) ?></td>
                                <td><?= $escape($repair['mechanic']) ?></td>
                                <td class="repair-history-amount">LKR <?= number_format($repair['amount']) ?></td>
                                <td>
                                    <details class="repair-history-details">
                                        <summary class="btn-repair-details">View Details</summary>
                                        <p><?= $escape($repair['details']) ?></p>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
