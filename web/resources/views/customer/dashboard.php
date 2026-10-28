<?php
/**
 * Customer Overview Dashboard View
 *
 * @var string $csrf
 * @var array<string, string> $assets
 * @var array{first_name?: string, last_name?: string, mobile?: string}|null $user
 * @var array{
 *     active_vehicles?: int|null,
 *     vehicles_in_shop?: int|null,
 *     upcoming_appointment?: array{date_label: string, relative_label?: string}|null,
 *     unpaid_invoice_total?: float|null,
 *     unpaid_invoice_count?: int|null,
 *     currency?: string
 * } $stats
 * @var array{
 *     status: string,
 *     vehicle_name: string,
 *     license_plate: string,
 *     completion_percent: int,
 *     milestones: list<array{label: string, status: 'done'|'active'|'pending'}>
 * }|null $current_service
 * @var list<array{title: string, time_label: string}> $recent_activity
 */

$escape = static fn (string|int|float|null $value): string => htmlspecialchars(
    (string) ($value ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8',
);
$user_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$active_vehicles = $stats['active_vehicles'] ?? null;
$vehicles_in_shop = $stats['vehicles_in_shop'] ?? null;
$upcoming_appointment = $stats['upcoming_appointment'] ?? null;
$unpaid_invoice_total = $stats['unpaid_invoice_total'] ?? null;
$unpaid_invoice_count = $stats['unpaid_invoice_count'] ?? null;
$currency = $stats['currency'] ?? 'LKR';
$completion_percent = $current_service === null
    ? 0
    : min(100, max(0, $current_service['completion_percent']));
?>

<div class="dashboard-wrapper">

    <!-- ======================================================== -->
    <!-- 1. GREETING & PRIMARY ACTION                             -->
    <!-- ======================================================== -->
    <section class="dashboard-header">
        <div class="dashboard-title-group">
            <h1 class="dashboard-heading">
                Welcome back<?= $user_name !== '' ? ', ' . $escape($user_name) : '' ?>.
            </h1>
            <p class="dashboard-subheading">
                Here is what's happening with your vehicles and upcoming workshop milestones today.
            </p>
        </div>

        <a href="/customer/appointments/book" class="btn-book-service" id="btn-book-service">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>Book New Service</span>
        </a>
    </section>

    <!-- ======================================================== -->
    <!-- 2. KPI SUMMARY METRIC CARDS                              -->
    <!-- ======================================================== -->
    <section class="stats-grid" aria-label="Summary Statistics">
        <!-- Card 1: Active Vehicles -->
        <article class="stat-card stat-card-orange">
            <div class="stat-card-header">
                <span class="stat-card-label">Active Vehicles</span>
                <div class="stat-card-corner-icon">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 17a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h1m16 0h1M5 13l1.5-5.5a2 2 0 011.92-1.5h7.16a2 2 0 011.92 1.5L19 13M5 13v4a1 1 0 001 1h1m10 0h1a1 1 0 001-1v-4M5 13h14" />
                    </svg>
                </div>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= $active_vehicles === null ? 'N/A' : $escape($active_vehicles) ?></span>
                <span class="stat-badge stat-badge-orange">
                    <span class="dot-indicator"></span>
                    <span><?= $vehicles_in_shop === null ? 'N/A' : $escape($vehicles_in_shop) ?> in shop</span>
                </span>
            </div>
        </article>

        <!-- Card 2: Upcoming Appointment -->
        <article class="stat-card stat-card-blue">
            <div class="stat-card-header">
                <span class="stat-card-label">Upcoming Appointment</span>
                <div class="stat-card-corner-icon">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value">
                    <?= $upcoming_appointment === null ? 'None scheduled' : $escape($upcoming_appointment['date_label']) ?>
                </span>
                <span class="stat-badge stat-badge-blue">
                    <?= $upcoming_appointment === null
                        ? 'No upcoming appointment'
                        : $escape($upcoming_appointment['relative_label'] ?? '') ?>
                </span>
            </div>
        </article>

        <!-- Card 3: Unpaid Invoices -->
        <article class="stat-card stat-card-green">
            <div class="stat-card-header">
                <span class="stat-card-label">Unpaid Invoices</span>
                <div class="stat-card-corner-icon">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                </div>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value">
                    <?= $unpaid_invoice_total === null
                        ? '—'
                        : $escape($currency . ' ' . number_format($unpaid_invoice_total, 2)) ?>
                </span>
                <?php if ($unpaid_invoice_count === null): ?>
                    <span class="stat-badge stat-badge-green">Invoice data unavailable</span>
                <?php elseif ($unpaid_invoice_count === 0): ?>
                    <span class="stat-badge stat-badge-green">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>All Paid</span>
                    </span>
                <?php else: ?>
                    <span class="stat-badge stat-badge-green">
                        <?= $escape($unpaid_invoice_count) ?> unpaid
                    </span>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <!-- ======================================================== -->
    <!-- 3. LIVE VEHICLE SERVICE TRACKER & RECENT ACTIVITY        -->
    <!-- ======================================================== -->
    <div class="dashboard-main-grid">

        <!-- Left Column: Service Status & Progress Timeline -->
        <article class="dashboard-card">
            <!-- Card Header -->
            <div class="card-header-bar">
                <div class="card-header-left">
                    <div class="card-icon-badge">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <div class="card-title-group">
                        <h2 class="card-main-title">Current Service Status</h2>
                        <span class="card-sub-title">Job Status & Progress timeline</span>
                    </div>
                </div>

                <?php if ($current_service !== null): ?>
                    <span class="status-chip-progress">
                        <span class="dot-indicator"></span>
                        <span><?= $escape($current_service['status']) ?></span>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($current_service === null): ?>
                <p class="card-sub-title">There is no active service for your vehicles.</p>
            <?php else: ?>
                <div class="vehicle-info-bar">
                    <svg class="vehicle-info-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 17a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h1m16 0h1M5 13l1.5-5.5a2 2 0 011.92-1.5h7.16a2 2 0 011.92 1.5L19 13M5 13v4a1 1 0 001 1h1m10 0h1a1 1 0 001-1v-4M5 13h14" />
                    </svg>
                    <span class="vehicle-name-label"><?= $escape($current_service['vehicle_name']) ?></span>
                    <span class="license-plate-badge" title="Vehicle License Plate">
                        <?= $escape($current_service['license_plate']) ?>
                    </span>
                </div>

                <div class="completion-section">
                    <div class="completion-header">
                        <span class="completion-label">Overall Completion</span>
                        <span class="completion-percentage"><?= $completion_percent ?>%</span>
                    </div>
                    <div class="progress-track" role="progressbar" aria-valuenow="<?= $completion_percent ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-fill" style="width: <?= $completion_percent ?>%;"></div>
                    </div>
                </div>

                <div class="milestone-stepper" aria-label="Service Milestone Progress">
                    <?php foreach ($current_service['milestones'] as $milestone): ?>
                        <?php $milestone_status = in_array($milestone['status'], ['done', 'active'], true)
                            ? $milestone['status']
                            : 'pending'; ?>
                        <div class="milestone-item milestone-<?= $milestone_status ?>" title="<?= $escape($milestone['label']) ?>: <?= $milestone_status ?>">
                            <?php if ($milestone_status === 'done'): ?>
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            <?php elseif ($milestone_status === 'active'): ?>
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            <?php else: ?>
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" />
                                </svg>
                            <?php endif; ?>
                            <span><?= $escape($milestone['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <!-- Right Column: Recent Activity Timeline -->
        <article class="dashboard-card">
            <!-- Card Header -->
            <div class="card-header-bar">
                <div class="card-header-left">
                    <div class="card-icon-badge" style="background-color: #f1f5f9; color: #475569;">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="card-main-title">Recent Activity</h2>
                </div>

                <a href="/customer/history" class="activity-header-link">
                    <span>View All History</span>
                    <span>→</span>
                </a>
            </div>

            <!-- Timeline Event Feed -->
            <div class="activity-timeline">
                <?php if ($recent_activity === []): ?>
                    <p class="card-sub-title">No recent activity.</p>
                <?php else: ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon-circle activity-icon-gray">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="activity-content">
                                <span class="activity-title"><?= $escape($activity['title']) ?></span>
                                <span class="activity-time"><?= $escape($activity['time_label']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </article>

    </div>

</div>
