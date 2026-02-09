<?php
/**
 * Community Directory — Members-Only Directory View.
 * Stub for Phase 3.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug   = get_option( 'cd_base_slug', 'community' );
$profile_url = home_url( $base_slug . '/profile/' );

get_header();
?>

<div class="cd-wrap cd-directory" x-data="cdDirectory()">
    <div class="cd-container">
        <div class="cd-page-header cd-page-header-row">
            <h1 class="cd-title"><?php esc_html_e( 'Community Directory', 'community-directory' ); ?></h1>
            <nav class="cd-nav">
                <a href="<?php echo esc_url( $profile_url ); ?>" class="cd-btn cd-btn-sm cd-btn-secondary">
                    <?php esc_html_e( 'My Profile', 'community-directory' ); ?>
                </a>
                <button type="button" class="cd-btn cd-btn-sm cd-btn-text" @click="logout()">
                    <?php esc_html_e( 'Log Out', 'community-directory' ); ?>
                </button>
            </nav>
        </div>

        <div class="cd-main">
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
        </div>
    </div>
</div>

<?php get_footer(); ?>
