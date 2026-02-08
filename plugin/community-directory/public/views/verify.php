<?php
/**
 * Community Directory Email Verification Page.
 * Handles the email verification callback.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug   = get_option( 'cd_base_slug', 'community' );
$landing_url = home_url( $base_slug . '/' );
$login_url   = home_url( $base_slug . '/login/' );
$token       = get_query_var( 'cd_token', '' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — <?php esc_html_e( 'Email Verification', 'community-directory' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cd-page cd-verify" x-data="cdVerify()">

    <div class="cd-container">
        <header class="cd-header">
            <h1 class="cd-title"><?php esc_html_e( 'Email Verification', 'community-directory' ); ?></h1>
        </header>

        <main class="cd-main">
            <div class="cd-card">
                <!-- Loading state -->
                <div x-show="loading" class="cd-text-center">
                    <div class="cd-spinner cd-spinner-lg"></div>
                    <p><?php esc_html_e( 'Verifying your email address...', 'community-directory' ); ?></p>
                </div>

                <!-- Success state -->
                <div x-show="!loading && success" x-transition>
                    <div class="cd-success-icon">&#10003;</div>
                    <h2><?php esc_html_e( 'Email Verified!', 'community-directory' ); ?></h2>
                    <p><?php esc_html_e( 'Your email address has been verified and your application is now under review. A church officer will review your application and you will be notified by email once a decision has been made.', 'community-directory' ); ?></p>
                    <a href="<?php echo esc_url( $landing_url ); ?>" class="cd-btn cd-btn-primary">
                        <?php esc_html_e( 'Return Home', 'community-directory' ); ?>
                    </a>
                </div>

                <!-- Error state -->
                <div x-show="!loading && !success && errorMessage" x-transition>
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
        </main>

        <footer class="cd-footer">
            <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
        </footer>
    </div>

    <!-- Pass token to Alpine.js -->
    <script>
        window.cdVerifyToken = <?php echo wp_json_encode( $token ); ?>;
    </script>
    <?php wp_footer(); ?>
</body>
</html>
