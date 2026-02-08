<?php
/**
 * Community Directory — Members-Only Directory View.
 * Stub for Phase 3.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug  = get_option( 'cd_base_slug', 'community' );
$profile_url = home_url( $base_slug . '/profile/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — <?php esc_html_e( 'Directory', 'community-directory' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cd-page cd-directory" x-data="cdDirectory()">

    <div class="cd-container">
        <header class="cd-header cd-header-app">
            <h1 class="cd-title"><?php esc_html_e( 'Community Directory', 'community-directory' ); ?></h1>
            <nav class="cd-nav">
                <a href="<?php echo esc_url( $profile_url ); ?>" class="cd-btn cd-btn-sm cd-btn-secondary">
                    <?php esc_html_e( 'My Profile', 'community-directory' ); ?>
                </a>
                <button type="button" class="cd-btn cd-btn-sm cd-btn-text" @click="logout()">
                    <?php esc_html_e( 'Log Out', 'community-directory' ); ?>
                </button>
            </nav>
        </header>

        <main class="cd-main">
            <!-- Search -->
            <div class="cd-search-bar">
                <input
                    type="search"
                    class="cd-search-input"
                    placeholder="<?php esc_attr_e( 'Search by name...', 'community-directory' ); ?>"
                    x-model="searchQuery"
                    @input.debounce.300ms="search()"
                >
            </div>

            <!-- Directory listing — Phase 3 implementation -->
            <div class="cd-card">
                <p class="cd-text-muted cd-text-center">
                    <?php esc_html_e( 'The directory will be available once member profiles are set up. Check back soon!', 'community-directory' ); ?>
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
