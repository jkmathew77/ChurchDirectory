<?php
/**
 * Community Directory — Members-Only Directory View.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug   = get_option( 'cd_base_slug', 'community' );
$profile_url = home_url( $base_slug . '/profile/' );
$member_url_base  = home_url( $base_slug . '/member/' );
$login_url     = home_url( $base_slug . '/login/' );

if ( ! is_user_logged_in() ) {
    wp_redirect( $login_url );
    exit;
}

get_header();
?>

<div class="cd-wrap cd-directory" x-data="cdDirectory">
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
                    placeholder="<?php esc_attr_e( 'Search members by name...', 'community-directory' ); ?>"
                    x-model="searchQuery"
                    @input.debounce.300ms="search()"
                >
            </div>

            <!-- Loading State -->
            <div x-show="loading" class="cd-loading-state">
                <div class="cd-spinner"></div>
                <span><?php esc_html_e( 'Loading directory...', 'community-directory' ); ?></span>
            </div>

            <!-- Empty State -->
            <div x-show="!loading && members.length === 0" class="cd-empty-state" style="display: none;">
                <p><?php esc_html_e( 'No members found matching your search.', 'community-directory' ); ?></p>
            </div>

            <!-- Member Grid -->
            <div x-show="!loading && members.length > 0" class="cd-member-grid" style="display: none;">
                <template x-for="member in members" :key="member.uuid">
                    <a :href="'<?php echo esc_url( $member_url_base ); ?>' + member.uuid" class="cd-member-card">
                        <div class="cd-member-avatar-wrapper">
                            <template x-if="member.avatar_url">
                                <img 
                                    :src="member.avatar_url" 
                                    class="cd-member-avatar-img" 
                                    @error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'"
                                >
                            </template>
                            <!-- Use x-show instead of x-if for fallback to ensure error handler can find sibling -->
                            <div 
                                class="cd-member-avatar-fallback" 
                                :style="'background-color: ' + getAvatarColor(member.first_name + ' ' + member.last_name) + '; display: ' + (member.avatar_url ? 'none' : 'flex')"
                            >
                                <span x-text="getInitials(member.first_name, member.last_name)"></span>
                            </div>
                        </div>
                        
                        <div class="cd-member-info">
                            <h3 x-text="member.first_name + ' ' + member.last_name"></h3>
                            <div class="cd-member-meta" x-show="member.city">
                                <span x-text="member.city + (member.state ? ', ' + member.state : '')"></span>
                            </div>
                            
                            <!-- Ministry Tags -->
                            <div class="cd-member-tags" x-show="member.ministry_tags && member.ministry_tags.length > 0">
                                <template x-for="tag in member.ministry_tags.slice(0, 3)">
                                    <span class="cd-tag" x-text="tag"></span>
                                </template>
                                <span x-show="member.ministry_tags.length > 3" class="cd-tag-more" x-text="'+' + (member.ministry_tags.length - 3)"></span>
                            </div>
                        </div>
                    </a>
                </template>
            </div>

            <!-- Pagination -->
            <div class="cd-pagination" x-show="totalPages > 1 && !loading" style="display: none; margin-top: 2rem; justify-content: center; gap: 1rem;">
                <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" :disabled="page <= 1" @click="prevPage()">&laquo; <?php esc_html_e( 'Previous', 'community-directory' ); ?></button>
                <span style="align-self: center; font-size: 0.9em; color: #666;">
                    <?php esc_html_e( 'Page', 'community-directory' ); ?> <span x-text="page"></span> <?php esc_html_e( 'of', 'community-directory' ); ?> <span x-text="totalPages"></span>
                </span>
                <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" :disabled="page >= totalPages" @click="nextPage()"><?php esc_html_e( 'Next', 'community-directory' ); ?> &raquo;</button>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
