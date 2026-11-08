<?php
/**
 * Customer Vehicles Page View
 *
 * @var string $csrf
 * @var array<string, string> $assets
 * @var array{first_name?: string, last_name?: string, mobile?: string}|null $user
 * @var array{registered_count?: int, active_service_count?: int}|null $stats
 * @var list<array{
 *     id: int|string,
 *     license_plate: string,
 *     model: string,
 *     trim_badge: string,
 *     image: string,
 *     color_name?: string,
 *     color_hex?: string,
 *     status: 'active_service'|'idle',
 *     bay_status_label?: string
 * }> $vehicles
 */

$escape = static fn (string|int|float|null $value): string => htmlspecialchars(
    (string) ($value ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8',
);

$stats = $stats ?? null;
$registered_count = $stats['registered_count'] ?? count($vehicles);
$active_service_count = $stats['active_service_count'] ?? count(array_filter(
    $vehicles,
    static fn (array $v): bool => $v['status'] === 'active_service'
));
?>

<div class="dashboard-wrapper">

    <!-- ======================================================== -->
    <!-- MY VEHICLES SECTION HEADER                               -->
    <!-- ======================================================== -->
    <section class="vehicles-page-header">
        <div class="vehicles-title-group">
            <h1 class="dashboard-heading">My Vehicles</h1>
            <p class="dashboard-subheading">
                Manage your registered garage vehicles, view inspection intervals, and schedule bookings.
            </p>
        </div>
    </section>

    <!-- ======================================================== -->
    <!-- METRIC SUMMARY CHIPS                                     -->
    <!-- ======================================================== -->
    <section class="vehicles-metrics-bar" aria-label="Vehicle Statistics">
        <div class="vehicle-metric-chip">
            <div class="metric-chip-icon">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 17a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h1m16 0h1M5 13l1.5-5.5a2 2 0 011.92-1.5h7.16a2 2 0 011.92 1.5L19 13M5 13v4a1 1 0 001 1h1m10 0h1a1 1 0 001-1v-4M5 13h14" />
                </svg>
            </div>
            <div class="metric-chip-text">
                <span class="metric-chip-label">REGISTERED</span>
                <span class="metric-chip-val"><?= $registered_count ?> Units</span>
            </div>
        </div>

        <div class="vehicle-metric-chip">
            <div class="metric-chip-icon">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="metric-chip-text">
                <span class="metric-chip-label">ACTIVE SERVICE</span>
                <span class="metric-chip-val metric-val-active"><?= $active_service_count ?> Active</span>
            </div>
        </div>
    </section>

    <!-- ======================================================== -->
    <!-- VEHICLES CARDS GRID                                      -->
    <!-- Mileage removed, "View History" orange button instead    -->
    <!-- ======================================================== -->
    <section class="vehicles-grid" aria-label="My Vehicles List">
        <?php foreach ($vehicles as $vehicle): ?>
            <article class="vehicle-card">
                <!-- Card Topbar -->
                <div class="vehicle-card-topbar">
                    <span class="license-plate-badge"><?= $escape($vehicle['license_plate']) ?></span>
                    <button type="button" class="vehicle-kebab-btn" aria-label="Vehicle options">
                        <svg fill="currentColor" viewBox="0 0 24 24" width="18" height="18">
                            <circle cx="12" cy="5" r="1.75" />
                            <circle cx="12" cy="12" r="1.75" />
                            <circle cx="12" cy="19" r="1.75" />
                        </svg>
                    </button>
                </div>

                <!-- Vehicle Title -->
                <h2 class="vehicle-card-title"><?= $escape($vehicle['model']) ?></h2>

                <!-- Vehicle Image Container -->
                <div class="vehicle-card-media">
                    <img
                        src="<?= $escape($vehicle['image']) ?>"
                        alt="<?= $escape($vehicle['model']) ?>"
                        class="vehicle-image"
                        loading="lazy"
                    />
                    <span class="vehicle-badge-overlay"><?= $escape($vehicle['trim_badge']) ?></span>
                </div>

                <!-- Vehicle Card Footer Action Button -->
                <div class="vehicle-card-footer">
                    <?php if ($vehicle['status'] === 'active_service'): ?>
                        <a href="/customer/overview#current-service" class="btn-bay-status btn-view-progress">
                            <span class="dot-indicator dot-green"></span>
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span><?= $escape($vehicle['bay_status_label'] ?? 'View Progress') ?></span>
                        </a>
                    <?php else: ?>
                        <a href="/customer/history?plate=<?= urlencode((string) $vehicle['license_plate']) ?>" class="btn-view-history">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>View History</span>
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

</div>
