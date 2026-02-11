<?php
/**
 * Community Directory Email Verification Page.
 * Handles the email verification callback.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 * Token is passed to JS via wp_add_inline_script in class-plugin.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug   = get_option( 'cd_base_slug', 'community' );
$landing_url = home_url( $base_slug . '/' );

get_header();
?>

<div class="cd-wrap cd-verify" x-data="cdVerify">
    <div class="cd-container">
        <div class="cd-page-header">
            <h1 class="cd-title"><?php esc_html_e( 'Email Verification', 'community-directory' ); ?></h1>
        </div>

        <div class="cd-main">
            <div class="cd-card">
                <!-- Loading state -->
                <div x-show="loading" class="cd-text-center">
                    <div class="cd-spinner cd-spinner-lg"></div>
                    <p><?php esc_html_e( 'Verifying your email address...', 'community-directory' ); ?></p>
                </div>

                <!-- Success state -->
                <div x-show="!loading && success" x-cloak x-transition>
                    <div class="cd-success-icon">&#10003;</div>
                    <h2><?php esc_html_e( 'Email Verified!', 'community-directory' ); ?></h2>
                    <p><?php esc_html_e( 'Your email address has been verified and your application is now under review. A church officer will review your application and you will be notified by email once a decision has been made.', 'community-directory' ); ?></p>
                    <a href="<?php echo esc_url( $landing_url ); ?>" class="cd-btn cd-btn-primary">
                        <?php esc_html_e( 'Return Home', 'community-directory' ); ?>
                    </a>
                </div>

                <!-- Error state -->
                <div x-show="!loading && !success && errorMessage" x-cloak x-transition>
                    <div class="cd-error-icon">&#10007;</div>
                    <h2><?php esc_html_e( 'Verification Failed', 'community-directory' ); ?></h2>
                    <p x-text="errorMessage"></p>
                    <div class="cd-actions">
                        <a href="<?php echo esc_url( $landing_url ); ?>" class="cd-btn cd-btn-primary">
                            <?php esc_html_e( 'Return Home', 'community-directory' ); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
