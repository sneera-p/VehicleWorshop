<?php
/**
 * Customer Portal Shell Layout
 *
 * @var string $title
 * @var string $csrf
 * @var string $content
 * @var string $content_name
 * @var array<string, string> $assets
 * @var string|null $active_route
 * @var array{first_name?: string, last_name?: string, mobile?: string}|null $user
 */

$__filename__ = 'layouts/' . basename(__FILE__, '.php');
$__layout_ts__ = $assets["ts/$__filename__.ts"] ?? false;
$__layout_css__ = $assets["css/$__filename__.css"] ?? false;
$__content_ts__ = $assets["ts/$content_name.ts"] ?? false;
$__content_css__ = $assets["css/$content_name.css"] ?? false;

$user_name = isset($user['first_name']) ? htmlspecialchars($user['first_name']) : 'Sachin';
$user_role = 'Client';
$current_route = $active_route ?? 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title><?= htmlspecialchars($title) ?></title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <?php if ($__layout_css__): ?>
        <link rel="stylesheet" href="<?= $__layout_css__ ?>">
    <?php endif; ?>

    <?php if ($__layout_ts__): ?>
        <script src="<?= $__layout_ts__ ?>" defer></script>
    <?php endif; ?>

    <?php if ($__content_css__): ?>
        <link rel="stylesheet" href="<?= $__content_css__ ?>">
    <?php endif; ?>

    <?php if ($__content_ts__): ?>
        <script src="<?= $__content_ts__ ?>" defer></script>
    <?php endif; ?>
</head>
<body>
    <div class="customer-layout-wrapper">
        <!-- Mobile Drawer Backdrop -->
        <div id="mobile-backdrop" class="mobile-backdrop"></div>

        <!-- ======================================================== -->
        <!-- LEFT SIDEBAR: Dark Industrial Brand Nav                  -->
        <!-- ======================================================== -->
        <aside id="portal-sidebar" class="portal-sidebar" aria-label="Customer Navigation">
            <!-- Sidebar Header -->
            <div class="sidebar-brand-header">
                <a href="/customer/overview" class="sidebar-brand">
                    <div class="sidebar-logo-icon">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#ffffff" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="sidebar-brand-meta">
                        <span class="sidebar-brand-name">AutoCare</span>
                        <span class="sidebar-brand-badge">CUSTOMER PORTAL</span>
                    </div>
                </a>

                <!-- Mobile Close Button -->
                <button type="button" id="sidebar-close-btn" class="sidebar-close-btn" aria-label="Close sidebar">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Sidebar Navigation Links -->
            <nav class="sidebar-nav-container">
                <div class="sidebar-nav-section">
                    <span class="sidebar-section-title">OPERATIONS</span>

                    <!-- 1. Overview -->
                    <a href="/customer/overview" class="sidebar-nav-link <?= $current_route === 'overview' ? 'active' : '' ?>">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                        <span>Overview</span>
                    </a>

                    <!-- 2. My Vehicles -->
                    <a href="/customer/vehicles" class="sidebar-nav-link <?= $current_route === 'vehicles' ? 'active' : '' ?>">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 17a2 2 0 100-4 2 2 0 000 4zm8 0a2 2 0 100-4 2 2 0 000 4z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h1m16 0h1M5 13l1.5-5.5a2 2 0 011.92-1.5h7.16a2 2 0 011.92 1.5L19 13M5 13v4a1 1 0 001 1h1m10 0h1a1 1 0 001-1v-4M5 13h14" />
                        </svg>
                        <span>My Vehicles</span>
                    </a>

                    <!-- 3. Appointments -->
                    <a href="/customer/appointments" class="sidebar-nav-link <?= $current_route === 'appointments' ? 'active' : '' ?>">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Appointments</span>
                    </a>

                    <!-- 4. Repair History -->
                    <a href="/customer/history" class="sidebar-nav-link <?= $current_route === 'history' ? 'active' : '' ?>">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Repair History</span>
                    </a>

                    <!-- 5. Payments -->
                    <a href="/customer/payments" class="sidebar-nav-link <?= $current_route === 'payments' ? 'active' : '' ?>">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                        </svg>
                        <span>Payments</span>
                    </a>
                </div>
            </nav>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="sidebar-avatar">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
                <div class="sidebar-user-info">
                    <span class="sidebar-user-name"><?= $user_name ?></span>
                    <span class="sidebar-user-role"><?= $user_role ?></span>
                </div>
            </div>
        </aside>

        <!-- ======================================================== -->
        <!-- MAIN AREA: Topbar + Sub-Navbar Strip + Content           -->
        <!-- ======================================================== -->
        <div class="portal-main-area">

            <!-- Topbar Navbar -->
            <header class="portal-topbar">
                <div class="topbar-left">
                    <button type="button" id="sidebar-toggle-btn" class="sidebar-toggle-btn" aria-label="Open sidebar">
                        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="topbar-breadcrumb">
                        <span>Dashboard</span>
                        <span>›</span>
                        <span class="topbar-breadcrumb-active"><?= ucfirst($current_route) ?></span>
                    </div>
                </div>

                <!-- Quick Search -->
                <div class="topbar-center">
                    <form action="/customer/search" method="GET" class="topbar-search-form">
                        <svg class="topbar-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            type="search"
                            name="plate"
                            class="topbar-search-input"
                            placeholder="Search license plate"
                            aria-label="Search license plate"
                        >
                    </form>
                </div>

                <!-- Right Actions & Profile -->
                <div class="topbar-right">
                    <!-- Notification Bell -->
                    <button type="button" class="topbar-action-btn" aria-label="Notifications">
                        <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span class="notification-badge"></span>
                    </button>

                    <!-- User Profile Chip -->
                    <div class="topbar-user-chip">
                        <div class="topbar-user-avatar">
                            <?= strtoupper(substr($user_name, 0, 1)) ?>
                        </div>
                        <div class="topbar-user-meta">
                            <span class="topbar-user-title"><?= $user_name ?></span>
                            <span class="topbar-user-subtitle"><?= $user_role ?></span>
                        </div>
                        <svg class="topbar-user-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
            </header>

            <!-- ==================================================== -->
            <!-- SUB-NAVBAR UTILITY STRIP: Below Navbar with Logout   -->
            <!-- ==================================================== -->
            <div class="portal-subnav-strip">
                <div class="subnav-status-indicator">
                    <span class="status-ping-dot"></span>
                    <span>Workshop Open: 8:00 AM – 6:00 PM</span>
                </div>

                <div class="subnav-actions">
                    <!-- Log Out Form with CSRF Protection -->
                    <form method="POST" action="/customer/logout" id="customer-logout-form">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <button type="submit" class="subnav-logout-btn" title="Sign out of your account">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Log Out</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- ==================================================== -->
            <!-- MAIN CONTENT CONTAINER                               -->
            <!-- ==================================================== -->
            <main class="portal-content-body">
                <?= $content ?>
            </main>

        </div>
    </div>
</body>
</html>
