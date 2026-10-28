<?php
/**
 * @var string $csrf
 * @var array<string, string> $assets
 * @var string|null $error
 * @var string|null $success
 * @var string $mobile
 */
?>

<h2 class="auth-form-heading">
    Sign In
</h2>
<p class="auth-form-subheading">
    Access your vehicle service history, track repairs, and manage bookings.
</p>

<!-- Feedback Messages -->
<?php if (!empty($error)): ?>
    <div class="alert-error" role="alert">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="font-medium"><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert-success" role="alert">
        <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="font-medium"><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<!-- Customer Login Form -->
<form method="POST" action="/customer/login" class="auth-form" id="customer-login-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <!-- Field 1: Mobile Number -->
    <div class="form-group">
        <label for="mobile" class="auth-form-label">
            Mobile Number
        </label>
        <input
            type="tel"
            id="mobile"
            name="mobile"
            required
            autocomplete="tel"
            placeholder="07XXXXXXXX"
            value="<?= htmlspecialchars($mobile) ?>"
            class="form-input"
        >
    </div>

    <!-- Field 2: Password with Integrated Forgot Password -->
    <div class="form-group">
        <div class="auth-form-label-row">
            <label for="password" class="auth-form-label">
                Password
            </label>
            <a href="/customer/forgot-password" class="auth-forgot-link">
                Forgot password?
            </a>
        </div>
        <div class="input-password-wrapper">
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Password"
                class="form-input-password"
            >
            <button
                type="button"
                id="toggle-password"
                aria-label="Toggle password visibility"
                class="btn-password-toggle">
                <svg id="eye-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg id="eye-off-icon" class="hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Submit CTA Button -->
    <div style="padding-top: 0.5rem;">
        <button type="submit" id="submit-login-btn" class="btn-primary">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
            </svg>
            <span>Sign In</span>
        </button>
    </div>

    <!-- Sign Up Switch Prompt -->
    <div class="auth-switch-prompt">
        <p>
            Don't have an account?
            <a href="/customer/register" class="auth-switch-link">
                Sign Up
            </a>
        </p>
    </div>
</form>
