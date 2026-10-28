<?php
/**
 * Authentication Layout
 *
 * @var string $title
 * @var string $csrf
 * @var string $content
 * @var string $content_name
 * @var array<string, string> $assets
 * @var \Closure(string|false|null $href): string $renderStylesheet
 * @var \Closure(string|false|null $href, bool ...$flags): string $renderScript
 */

$__filename__ = 'layouts/' . basename(__FILE__, '.php');

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

    <?= $renderStylesheet($assets["css/$__filename__.css"] ?? null) ?>
    <?= $renderStylesheet($assets["css/$content_name.css"] ?? null) ?>

    <?= $renderScript($assets["ts/$__filename__.ts"] ?? null) ?>
    <?= $renderScript($assets["ts/$content_name.ts"] ?? null) ?>
</head>
<body>
    <div class="auth-page-wrapper">
        <!-- 3D Car Perspective Background Canvas -->
        <canvas id="car-bg-canvas"></canvas>

        <!-- Main Card Container -->
        <div class="auth-card">

            <!-- ======================================================== -->
            <!-- LEFT PANEL: Dark Brand Showcase                          -->
            <!-- ======================================================== -->
            <div class="auth-left-panel">
                <!-- Decorative concentric orbit rings -->
                <div class="orbit-ring orbit-ring-1"></div>
                <div class="orbit-ring orbit-ring-2"></div>
                <div class="orbit-ring orbit-ring-3"></div>
                <div class="ambient-glow"></div>

                <!-- Top Tagline -->
                <div class="relative z-10">
                    <p class="auth-tagline">
                        Vehicle care made effortless — smart workshop solutions for you.
                    </p>
                </div>

                <!-- Showcase Content: Headline + Subtitle + Animation Slot -->
                <div class="auth-left-content">
                    <h1 class="auth-left-heading">
                        Welcome to<br>
                        <span class="auth-highlight-text">AutoCare</span>
                    </h1>
                    <p class="auth-left-subheading">
                        Vehicle Workshop Management System
                    </p>
                </div>

                <!-- Bottom Spacer -->
                <div class="relative"></div>
            </div>

            <!-- ======================================================== -->
            <!-- RIGHT PANEL: Clean Form Panel                            -->
            <!-- ======================================================== -->
            <div class="auth-right-panel">

                <!-- Top Bar: Brand Logo -->
                <div class="auth-brand-bar">
                    <a href="/" class="auth-brand-link">
                        <div class="auth-brand-icon-box">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#ffffff" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <span class="auth-brand-title">AutoCare</span>
                    </a>
                </div>

                <!-- Form Body Area -->
                <div class="auth-form-container">
                    <?= $content ?>
                </div>

                <!-- Footer: Copyright & Links -->
                <div class="auth-panel-footer">
                    <div class="auth-footer-links">
                        <a href="#">Contact Us</a>
                        <div class="flex items-center gap-1 cursor-pointer">
                            <span>English</span>
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

</body>
</html>
