<?php
/**
 * Community Directory Login Page.
 * Email/password login + Google OAuth.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug   = get_option( 'cd_base_slug', 'community' );
$landing_url = home_url( $base_slug . '/' );
$apply_url   = home_url( $base_slug . '/apply/' );

get_header();
?>

<div class="cd-wrap cd-login" x-data="cdLogin">
    <div class="cd-container">
        <div class="cd-page-header">
            <a href="<?php echo esc_url( $landing_url ); ?>" class="cd-back-link">
                &larr; <?php esc_html_e( 'Back', 'community-directory' ); ?>
            </a>
            <h1 class="cd-title"><?php esc_html_e( 'Member Login', 'community-directory' ); ?></h1>
        </div>

        <div class="cd-main">
            <div class="cd-card">
                <!-- Success message -->
                <template x-if="successMessage">
                    <div class="cd-alert cd-alert-success" x-text="successMessage"></div>
                </template>

                <!-- Error message -->
                <template x-if="errorMessage">
                    <div class="cd-alert cd-alert-error" x-text="errorMessage"></div>
                </template>

                <!-- Google OAuth -->
                <button
                    type="button"
                    class="cd-btn cd-btn-google"
                    @click="loginWithGoogle()"
                    :disabled="loading"
                >
                    <svg class="cd-icon-google" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18A10.96 10.96 0 0 0 1 12c0 1.77.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    <?php esc_html_e( 'Sign in with Google', 'community-directory' ); ?>
                </button>

                <div class="cd-divider">
                    <span><?php esc_html_e( 'or', 'community-directory' ); ?></span>
                </div>

                <!-- Email/Password Login Form -->
                <form @submit.prevent="loginWithEmail()" novalidate>
                    <div class="cd-form-group">
                        <label for="cd-email"><?php esc_html_e( 'Email Address', 'community-directory' ); ?></label>
                        <input
                            type="email"
                            id="cd-email"
                            x-model="email"
                            required
                            autocomplete="email"
                            :disabled="loading"
                            placeholder="you@example.com"
                        >
                    </div>

                    <div class="cd-form-group">
                        <label for="cd-password"><?php esc_html_e( 'Password', 'community-directory' ); ?></label>
                        <input
                            type="password"
                            id="cd-password"
                            x-model="password"
                            required
                            autocomplete="current-password"
                            :disabled="loading"
                        >
                    </div>

                    <button type="submit" class="cd-btn cd-btn-primary cd-btn-full" :disabled="loading">
                        <span x-show="!loading"><?php esc_html_e( 'Sign In', 'community-directory' ); ?></span>
                        <span x-show="loading" class="cd-spinner"></span>
                    </button>
                </form>

                <div class="cd-form-links">
                    <a href="#" @click.prevent="showForgotPassword = true">
                        <?php esc_html_e( 'Forgot password?', 'community-directory' ); ?>
                    </a>
                    <span class="cd-separator">|</span>
                    <a href="#" @click.prevent="showForgotEmail = true">
                        <?php esc_html_e( "Can't remember your email?", 'community-directory' ); ?>
                    </a>
                </div>

                <div class="cd-form-footer">
                    <p>
                        <?php esc_html_e( "Don't have an account?", 'community-directory' ); ?>
                        <a href="<?php echo esc_url( $apply_url ); ?>"><?php esc_html_e( 'Become a Member', 'community-directory' ); ?></a>
                    </p>
                </div>
            </div>

            <!-- Forgot Password Modal -->
            <template x-if="showForgotPassword">
                <div class="cd-modal-backdrop" @click.self="showForgotPassword = false">
                    <div class="cd-card cd-modal">
                        <h2><?php esc_html_e( 'Reset Password', 'community-directory' ); ?></h2>
                        <p><?php esc_html_e( 'Enter your email address and we will send you a link to reset your password.', 'community-directory' ); ?></p>

                        <template x-if="resetSent">
                            <div class="cd-alert cd-alert-success">
                                <?php esc_html_e( 'If an account exists with that email, a reset link has been sent.', 'community-directory' ); ?>
                            </div>
                        </template>

                        <form @submit.prevent="requestPasswordReset()" x-show="!resetSent">
                            <div class="cd-form-group">
                                <label for="cd-reset-email"><?php esc_html_e( 'Email Address', 'community-directory' ); ?></label>
                                <input
                                    type="email"
                                    id="cd-reset-email"
                                    x-model="resetEmail"
                                    required
                                    autocomplete="email"
                                >
                            </div>
                            <button type="submit" class="cd-btn cd-btn-primary cd-btn-full" :disabled="loading">
                                <?php esc_html_e( 'Send Reset Link', 'community-directory' ); ?>
                            </button>
                        </form>

                        <button class="cd-btn cd-btn-text" @click="showForgotPassword = false; resetSent = false">
                            <?php esc_html_e( 'Close', 'community-directory' ); ?>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Password Reset Confirmation Modal (from email link) -->
            <template x-if="showResetConfirm">
                <div class="cd-modal-backdrop">
                    <div class="cd-card cd-modal">
                        <h2><?php esc_html_e( 'Set New Password', 'community-directory' ); ?></h2>
                        <p><?php esc_html_e( 'Enter your new password below.', 'community-directory' ); ?></p>

                        <template x-if="errorMessage">
                            <div class="cd-alert cd-alert-error" x-text="errorMessage"></div>
                        </template>

                        <form @submit.prevent="confirmPasswordReset()">
                            <div class="cd-form-group">
                                <label for="cd-new-password"><?php esc_html_e( 'New Password', 'community-directory' ); ?></label>
                                <input
                                    type="password"
                                    id="cd-new-password"
                                    x-model="newPassword"
                                    required
                                    autocomplete="new-password"
                                    minlength="8"
                                    :disabled="loading"
                                >
                                <small class="cd-form-hint"><?php esc_html_e( 'Must be at least 8 characters.', 'community-directory' ); ?></small>
                            </div>
                            <div class="cd-form-group">
                                <label for="cd-new-password-confirm"><?php esc_html_e( 'Confirm Password', 'community-directory' ); ?></label>
                                <input
                                    type="password"
                                    id="cd-new-password-confirm"
                                    x-model="newPasswordConfirm"
                                    required
                                    autocomplete="new-password"
                                    :disabled="loading"
                                >
                            </div>
                            <button type="submit" class="cd-btn cd-btn-primary cd-btn-full" :disabled="loading">
                                <span x-show="!loading"><?php esc_html_e( 'Reset Password', 'community-directory' ); ?></span>
                                <span x-show="loading" class="cd-spinner"></span>
                            </button>
                        </form>

                        <button class="cd-btn cd-btn-text" @click="showResetConfirm = false; window.history.replaceState({}, '', window.location.pathname)">
                            <?php esc_html_e( 'Cancel', 'community-directory' ); ?>
                        </button>
                    </div>
                </div>
            </template>

            <!-- Forgot Email Modal -->
            <template x-if="showForgotEmail">
                <div class="cd-modal-backdrop" @click.self="showForgotEmail = false">
                    <div class="cd-card cd-modal">
                        <h2><?php esc_html_e( "Can't Remember Your Email?", 'community-directory' ); ?></h2>
                        <p><?php esc_html_e( 'Enter your full name and phone number. If we find a matching account, we will send a hint to the phone number on file.', 'community-directory' ); ?></p>

                        <template x-if="emailLookupSent">
                            <div class="cd-alert cd-alert-success">
                                <?php esc_html_e( 'If a matching account was found, a hint has been sent to your phone number on file.', 'community-directory' ); ?>
                            </div>
                        </template>

                        <form @submit.prevent="lookupEmail()" x-show="!emailLookupSent">
                            <div class="cd-form-group">
                                <label for="cd-lookup-name"><?php esc_html_e( 'Full Name', 'community-directory' ); ?></label>
                                <input
                                    type="text"
                                    id="cd-lookup-name"
                                    x-model="lookupName"
                                    required
                                >
                            </div>
                            <div class="cd-form-group">
                                <label for="cd-lookup-phone"><?php esc_html_e( 'Phone Number', 'community-directory' ); ?></label>
                                <input
                                    type="tel"
                                    id="cd-lookup-phone"
                                    x-model="lookupPhone"
                                    required
                                >
                            </div>
                            <button type="submit" class="cd-btn cd-btn-primary cd-btn-full" :disabled="loading">
                                <?php esc_html_e( 'Find My Account', 'community-directory' ); ?>
                            </button>
                        </form>

                        <button class="cd-btn cd-btn-text" @click="showForgotEmail = false; emailLookupSent = false">
                            <?php esc_html_e( 'Close', 'community-directory' ); ?>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<?php get_footer(); ?>
