<?php
/**
 * Community Directory — Edit Own Profile.
 * Stub for Phase 3.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug     = get_option( 'cd_base_slug', 'community' );
$directory_url = home_url( $base_slug . '/directory/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — <?php esc_html_e( 'Edit Profile', 'community-directory' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cd-page cd-edit-profile" x-data="cdEditProfile()">

    <div class="cd-container">
        <header class="cd-header">
            <a href="<?php echo esc_url( $directory_url ); ?>" class="cd-back-link">
                &larr; <?php esc_html_e( 'Directory', 'community-directory' ); ?>
            </a>
            <h1 class="cd-title"><?php esc_html_e( 'Edit Profile', 'community-directory' ); ?></h1>
        </header>

        <main class="cd-main">
            <div class="cd-card">
                <p class="cd-text-muted cd-text-center">
                    <?php esc_html_e( 'Profile editing will be available in Phase 3.', 'community-directory' ); ?>
                </p>
            </div>
        </main>

        <footer class="cd-footer">
            <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
        </footer>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
