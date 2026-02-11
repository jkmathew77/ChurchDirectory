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

            <!-- Advanced Filters Toggle -->
            <div class="cd-filter-toggle-wrap" style="margin-bottom: 1rem;">
                <button type="button" class="cd-filter-toggle" @click="showFilters = !showFilters" :class="{ 'active': showFilters || hasActiveFilters() }">
                    <?php esc_html_e( 'Advanced Filters', 'community-directory' ); ?> <span x-text="showFilters ? '\u25B2' : '\u25BC'"></span>
                    <template x-if="hasActiveFilters() && !showFilters"><span class="cd-filter-badge">&#8226;</span></template>
                </button>
            </div>

            <!-- Advanced Filters Panel -->
            <template x-if="showFilters"><div class="cd-filter-panel">
                <div class="cd-grid-2">
                    <div class="cd-form-group">
                        <label class="cd-label"><?php esc_html_e( 'City', 'community-directory' ); ?></label>
                        <input type="text" class="cd-input" x-model="filterCity" placeholder="<?php esc_attr_e( 'e.g. Dallas', 'community-directory' ); ?>">
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-label"><?php esc_html_e( 'State', 'community-directory' ); ?></label>
                        <input type="text" class="cd-input" x-model="filterState" placeholder="<?php esc_attr_e( 'e.g. TX', 'community-directory' ); ?>">
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-label"><?php esc_html_e( 'Occupation', 'community-directory' ); ?></label>
                        <input type="text" class="cd-input" x-model="filterOccupation" placeholder="<?php esc_attr_e( 'e.g. Engineer', 'community-directory' ); ?>">
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-label"><?php esc_html_e( 'Employer', 'community-directory' ); ?></label>
                        <input type="text" class="cd-input" x-model="filterEmployer" placeholder="<?php esc_attr_e( 'e.g. Google', 'community-directory' ); ?>">
                    </div>
                </div>
                <div class="cd-filter-actions">
                    <button type="button" class="cd-btn cd-btn-sm cd-btn-primary" @click="applyFilters()">
                        <?php esc_html_e( 'Apply Filters', 'community-directory' ); ?>
                    </button>
                    <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="clearFilters()" x-show="hasActiveFilters()">
                        <?php esc_html_e( 'Clear Filters', 'community-directory' ); ?>
                    </button>
                </div>
            </div></template>

            <!-- Loading State -->
            <div x-show="loading" class="cd-loading-state">
                <div class="cd-spinner"></div>
                <span><?php esc_html_e( 'Loading directory...', 'community-directory' ); ?></span>
            </div>

            <!-- Empty State -->
            <template x-if="!loading && members.length === 0">
                <div class="cd-empty-state">
                    <p><?php esc_html_e( 'No members found matching your search.', 'community-directory' ); ?></p>
                </div>
            </template>

            <!-- Member Grid -->
            <template x-if="!loading && members.length > 0"><div class="cd-member-grid">
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
                            <div
                                class="cd-member-avatar-fallback"
                                :style="'background-color: ' + getAvatarColor(member.first_name + ' ' + member.last_name) + '; display: ' + (member.avatar_url ? 'none' : 'flex')"
                            >
                                <span x-text="getInitials(member.first_name, member.last_name)"></span>
                            </div>
                        </div>

                        <div class="cd-member-info">
                            <h3 x-text="displayName(member)"></h3>
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
            </div></template>

            <!-- Pagination -->
            <template x-if="totalPages > 1 && !loading">
                <div class="cd-pagination" style="margin-top: 2rem; justify-content: center; gap: 1rem;">
                    <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" :disabled="page <= 1" @click="prevPage()">&laquo; <?php esc_html_e( 'Previous', 'community-directory' ); ?></button>
                    <span style="align-self: center; font-size: 0.9em; color: #666;">
                        <?php esc_html_e( 'Page', 'community-directory' ); ?> <span x-text="page"></span> <?php esc_html_e( 'of', 'community-directory' ); ?> <span x-text="totalPages"></span>
                    </span>
                    <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" :disabled="page >= totalPages" @click="nextPage()"><?php esc_html_e( 'Next', 'community-directory' ); ?> &raquo;</button>
                </div>
            </template>

            <!-- WhatsApp Groups Section -->
            <template x-if="whatsappGroups.length > 0"><div class="cd-whatsapp-section">
                <h2 class="cd-section-title"><?php esc_html_e( 'WhatsApp Groups', 'community-directory' ); ?></h2>
                <p class="cd-text-muted" style="margin-bottom: 1rem;"><?php esc_html_e( 'Stay connected with the community through our WhatsApp groups.', 'community-directory' ); ?></p>
                <div class="cd-whatsapp-grid">
                    <template x-for="group in whatsappGroups" :key="group.id">
                        <div class="cd-whatsapp-card">
                            <div class="cd-whatsapp-icon" x-text="group.icon || '&#128172;'"></div>
                            <div class="cd-whatsapp-info">
                                <h4 x-text="group.name"></h4>
                                <p x-show="group.description" x-text="group.description" class="cd-text-muted"></p>
                            </div>
                            <a :href="group.invite_url" target="_blank" rel="noopener noreferrer" class="cd-whatsapp-join">
                                <?php esc_html_e( 'Join', 'community-directory' ); ?>
                            </a>
                        </div>
                    </template>
                </div>
            </div></template>
        </div>
    </div>
</div>

<?php get_footer(); ?>
