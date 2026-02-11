<?php
/**
 * Community Directory — Invite Acceptance Page.
 * Approved applicants use this page to create their account.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 * Invite email is passed via URL path (base64), token via query param.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug   = get_option( 'cd_base_slug', 'community' );
$landing_url = home_url( $base_slug . '/' );

get_header();
?>

<div class="cd-wrap cd-invite" x-data="cdInvite">
    <div class="cd-container">
        <div class="cd-page-header">
            <h1 class="cd-title"><?php esc_html_e( 'Accept Your Invitation', 'community-directory' ); ?></h1>
        </div>

        <div class="cd-main">
            <div class="cd-card">
                <!-- Loading state -->
                <div x-show="loading" class="cd-text-center">
                    <div class="cd-spinner cd-spinner-lg"></div>
                    <p><?php esc_html_e( 'Validating your invitation...', 'community-directory' ); ?></p>
                </div>

                <!-- Token error state -->
                <div x-show="!loading && !tokenValid && errorMessage" x-cloak x-transition style="display:none">
                    <div class="cd-error-icon">&#10007;</div>
                    <h2><?php esc_html_e( 'Invalid Invitation', 'community-directory' ); ?></h2>
                    <p x-text="errorMessage"></p>
                    <div class="cd-actions">
                        <a href="<?php echo esc_url( $landing_url ); ?>" class="cd-btn cd-btn-primary">
                            <?php esc_html_e( 'Return Home', 'community-directory' ); ?>
                        </a>
                    </div>
                </div>

                <!-- Account creation form -->
                <div x-show="!loading && tokenValid && !success" x-cloak x-transition style="display:none">
                    <h2><?php esc_html_e( 'Create Your Account', 'community-directory' ); ?></h2>
                    <p>
                        <?php esc_html_e( 'Welcome', 'community-directory' ); ?>
                        <strong x-text="applicantName"></strong>!
                        <?php esc_html_e( 'Choose how you would like to sign in to the community directory.', 'community-directory' ); ?>
                    </p>

                    <template x-if="errorMessage">
                        <div class="cd-alert cd-alert-error" x-text="errorMessage"></div>
                    </template>

                    <!-- Google sign-up option -->
                    <button
                        type="button"
                        class="cd-btn cd-btn-google"
                        @click="signUpWithGoogle()"
                        :disabled="creating"
                    >
                        <svg class="cd-icon-google" viewBox="0 0 24 24" width="20" height="20">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18A10.96 10.96 0 0 0 1 12c0 1.77.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        <?php esc_html_e( 'Sign up with Google', 'community-directory' ); ?>
                    </button>

                    <div class="cd-divider">
                        <span><?php esc_html_e( 'or create a password', 'community-directory' ); ?></span>
                    </div>

                    <div class="cd-form-group">
                        <label for="cd-invite-email"><?php esc_html_e( 'Email', 'community-directory' ); ?></label>
                        <input
                            type="email"
                            id="cd-invite-email"
                            x-model="email"
                            readonly
                            class="cd-input-readonly"
                        >
                    </div>

                    <div class="cd-form-group">
                        <label for="cd-invite-password"><?php esc_html_e( 'Password', 'community-directory' ); ?> *</label>
                        <input
                            type="password"
                            id="cd-invite-password"
                            x-model="password"
                            required
                            autocomplete="new-password"
                        >
                        <p class="cd-password-requirements"><?php esc_html_e( 'Minimum 8 characters', 'community-directory' ); ?></p>
                    </div>

                    <div class="cd-form-group">
                        <label for="cd-invite-password-confirm"><?php esc_html_e( 'Confirm Password', 'community-directory' ); ?> *</label>
                        <input
                            type="password"
                            id="cd-invite-password-confirm"
                            x-model="passwordConfirm"
                            required
                            autocomplete="new-password"
                        >
                    </div>

                    <button
                        type="button"
                        class="cd-btn cd-btn-primary cd-btn-block"
                        @click="createAccount()"
                        :disabled="creating"
                    >
                        <span x-show="!creating"><?php esc_html_e( 'Create Account with Password', 'community-directory' ); ?></span>
                        <span x-show="creating" class="cd-spinner"></span>
                    </button>
                </div>

                <!-- Success state -->
                <div x-show="success" x-cloak x-transition style="display:none">
                    <div class="cd-success-icon">&#10003;</div>
                    <h2><?php esc_html_e( 'Account Created!', 'community-directory' ); ?></h2>
                    <p><?php esc_html_e( 'Your account has been created and you are now logged in. Redirecting to the directory...', 'community-directory' ); ?></p>
                    <div class="cd-spinner cd-spinner-lg"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
