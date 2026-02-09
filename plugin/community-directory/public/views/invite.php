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

<div class="cd-wrap cd-invite" x-data="cdInvite()">
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
                <div x-show="!loading && !tokenValid && errorMessage" x-transition>
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
                <div x-show="!loading && tokenValid && !success" x-transition>
                    <h2><?php esc_html_e( 'Create Your Account', 'community-directory' ); ?></h2>
                    <p>
                        <?php esc_html_e( 'Welcome', 'community-directory' ); ?>
                        <strong x-text="applicantName"></strong>!
                        <?php esc_html_e( 'Set a password to access the community directory.', 'community-directory' ); ?>
                    </p>

                    <template x-if="errorMessage">
                        <div class="cd-alert cd-alert-error" x-text="errorMessage"></div>
                    </template>

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
                        <span x-show="!creating"><?php esc_html_e( 'Create Account', 'community-directory' ); ?></span>
                        <span x-show="creating" class="cd-spinner"></span>
                    </button>
                </div>

                <!-- Success state -->
                <div x-show="success" x-transition>
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
