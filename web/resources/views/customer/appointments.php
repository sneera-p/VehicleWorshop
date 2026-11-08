<?php
/**
 * Customer Appointments View
 *
 * @var string $csrf
 * @var array<string, string> $assets
 * @var array{first_name?: string, last_name?: string, mobile?: string}|null $user
 * @var array{
 *     scheduled_today?: int,
 *     upcoming_bookings?: int,
 *     serviced_this_year?: int
 * }|null $stats
 * @var list<array{
 *     id: int|string,
 *     date: string,
 *     time: string,
 *     vehicle_model: string,
 *     license_plate: string,
 *     service_type: string,
 *     service_color?: string,
 *     status: 'Upcoming'|'Completed'|'Confirmed'|'Cancelled',
 *     notes?: string
 * }> $appointments
 */

$escape = static fn (string|int|float|null $value): string => htmlspecialchars(
    (string) ($value ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8',
);

$stats = $stats ?? [];
$total_count = count($appointments);
$upcoming_count = count(array_filter(
    $appointments,
    static fn (array $a): bool => in_array($a['status'], ['Upcoming', 'Confirmed'], true)
));
$completed_count = count(array_filter(
    $appointments,
    static fn (array $a): bool => $a['status'] === 'Completed'
));

$scheduled_today = $stats['scheduled_today'] ?? 0;
$upcoming_bookings = $stats['upcoming_bookings'] ?? $upcoming_count;
$serviced_this_year = $stats['serviced_this_year'] ?? $completed_count;
?>

<div class="dashboard-wrapper">

    <!-- ======================================================== -->
    <!-- 1. APPOINTMENTS PAGE HEADER                              -->
    <!-- ======================================================== -->
    <section class="appointments-page-header">
        <div class="appointments-title-group">
            <h1 class="dashboard-heading">Appointments</h1>
            <p class="dashboard-subheading">
                View, manage, and book upcoming or past maintenance appointments.
            </p>
        </div>

        <a href="/customer/appointments/book" class="btn-book-appointment" id="btn-book-appointment">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>Book Appointment</span>
        </a>
    </section>

    <!-- ======================================================== -->
    <!-- 2. KPI SUMMARY METRIC CARDS                              -->
    <!-- ======================================================== -->
    <section class="stats-grid" aria-label="Appointment Statistics">
        <!-- Card 1: Scheduled Today -->
        <article class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">SCHEDULED TODAY</span>
                <div class="appointment-stat-icon icon-blue-subtle">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= $scheduled_today ?></span>
            </div>
        </article>

        <!-- Card 2: Upcoming Bookings -->
        <article class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">UPCOMING BOOKINGS</span>
                <div class="appointment-stat-icon icon-blue-primary">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= $upcoming_bookings ?></span>
            </div>
        </article>

        <!-- Card 3: Serviced This Year -->
        <article class="stat-card">
            <div class="stat-card-header">
                <span class="stat-card-label">SERVICED THIS YEAR</span>
                <div class="appointment-stat-icon icon-green-success">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= $serviced_this_year ?></span>
            </div>
        </article>
    </section>

    <!-- ======================================================== -->
    <!-- 3. TABS & FILTER BAR                                     -->
    <!-- ======================================================== -->
    <section class="appointments-filter-strip" aria-label="Appointment Filters">
        <div class="appointments-tabs" role="tablist">
            <button
                type="button"
                class="appointment-tab-btn active"
                data-tab="all"
                role="tab"
                aria-selected="true"
            >
                <span>All Appointments</span>
                <span class="tab-count">(<?= $total_count ?>)</span>
            </button>
            <button
                type="button"
                class="appointment-tab-btn"
                data-tab="upcoming"
                role="tab"
                aria-selected="false"
            >
                <span>Upcoming</span>
                <span class="tab-count">(<?= $upcoming_count ?>)</span>
            </button>
            <button
                type="button"
                class="appointment-tab-btn"
                data-tab="completed"
                role="tab"
                aria-selected="false"
            >
                <span>Completed</span>
                <span class="tab-count">(<?= $completed_count ?>)</span>
            </button>
        </div>

        <div class="appointments-tools">
            <div class="appointments-search-wrapper">
                <svg class="appointments-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <input
                    type="search"
                    id="appointments-search-input"
                    class="appointments-search-input"
                    placeholder="Filter by vehicle or code..."
                    aria-label="Filter appointments"
                />
            </div>

            <button type="button" class="btn-appointments-export" title="Export appointments" aria-label="Export appointments">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
            </button>
        </div>
    </section>

    <!-- ======================================================== -->
    <!-- 4. APPOINTMENTS TABLE CARD                               -->
    <!-- ======================================================== -->
    <section class="appointments-table-card" aria-label="Appointments Table">
        <div class="table-responsive" role="region" aria-label="Appointments list" tabindex="0">
            <table class="appointments-table" id="appointments-table">
                <thead>
                    <tr>
                        <th scope="col">DATE & TIME</th>
                        <th scope="col">VEHICLE</th>
                        <th scope="col">SERVICE TYPE</th>
                        <th scope="col">STATUS</th>
                        <th scope="col" class="th-action">ACTION</th>
                    </tr>
                </thead>
                <tbody id="appointments-tbody">
                    <?php if ($appointments === []): ?>
                        <tr>
                            <td colspan="5" class="empty-state-cell">
                                <p class="empty-state-text">No appointments found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $apt): ?>
                            <?php
                            $status_class = match ($apt['status']) {
                                'Upcoming', 'Confirmed' => 'status-badge-upcoming',
                                'Completed' => 'status-badge-completed',
                                'Cancelled' => 'status-badge-cancelled',
                            };
                            $tab_category = in_array($apt['status'], ['Upcoming', 'Confirmed'], true)
                                ? 'upcoming'
                                : ($apt['status'] === 'Completed' ? 'completed' : 'other');
                            $service_color = $apt['service_color'] ?? ($apt['status'] === 'Completed' ? '#3b82f6' : '#f97316');
                            ?>
                            <tr
                                class="appointment-row"
                                data-tab-category="<?= $tab_category ?>"
                                data-search="<?= strtolower($escape($apt['vehicle_model'] . ' ' . $apt['license_plate'] . ' ' . $apt['service_type'])) ?>"
                            >
                                <!-- DATE & TIME -->
                                <td class="td-datetime">
                                    <div class="datetime-cell-group">
                                        <div class="datetime-icon-box">
                                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div class="datetime-text">
                                            <span class="appointment-date"><?= $escape($apt['date']) ?></span>
                                            <span class="appointment-time"><?= $escape($apt['time']) ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- VEHICLE -->
                                <td class="td-vehicle">
                                    <div class="vehicle-cell-group">
                                        <span class="vehicle-model-label"><?= $escape($apt['vehicle_model']) ?></span>
                                        <span class="license-plate-badge-soft">
                                            <?= $escape($apt['license_plate']) ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- SERVICE TYPE -->
                                <td class="td-service">
                                    <div class="service-type-cell">
                                        <span class="service-dot" style="background-color: <?= $escape($service_color) ?>;"></span>
                                        <span class="service-type-label"><?= $escape($apt['service_type']) ?></span>
                                    </div>
                                </td>

                                <!-- STATUS -->
                                <td class="td-status">
                                    <span class="appointment-status-badge <?= $status_class ?>">
                                        <span class="status-dot"></span>
                                        <span><?= $escape($apt['status']) ?></span>
                                    </span>
                                </td>

                                <!-- ACTION -->
                                <td class="td-action">
                                    <button type="button" class="vehicle-kebab-btn" aria-label="Appointment actions">
                                        <svg fill="currentColor" viewBox="0 0 24 24" width="18" height="18">
                                            <circle cx="12" cy="5" r="1.75" />
                                            <circle cx="12" cy="12" r="1.75" />
                                            <circle cx="12" cy="19" r="1.75" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Table Footer -->
        <div class="appointments-table-footer">
            <span class="footer-count-text">
                <?= $total_count ?> appointments
            </span>
        </div>
    </section>

    <!-- ======================================================== -->
    <!-- 5. EMERGENCY / ROADSIDE RECOVERY BANNER                  -->
    <!-- ======================================================== -->
    <section class="roadside-assistance-card">
        <div class="roadside-left">
            <div class="roadside-icon-circle">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="roadside-message">Need urgent inspection or roadside recovery?</span>
        </div>

        <a href="tel:+94112345678" class="btn-contact-workshop">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
            </svg>
            <span>Contact Workshop</span>
        </a>
    </section>

</div>
