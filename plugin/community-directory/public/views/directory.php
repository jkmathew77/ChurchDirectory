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
    CD_Logger::warn( 'directory.php: fallback auth check triggered (should have been caught by template_redirect)' );
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

        <!-- Officer Tab Switcher -->
        <template x-if="isOfficer"><div class="cd-officer-tabs">
            <button type="button" class="cd-officer-tab" :class="{ 'cd-officer-tab-active': activeTab === 'directory' }" @click="switchTab('directory')">
                <?php esc_html_e( 'Directory', 'community-directory' ); ?>
            </button>
            <button type="button" class="cd-officer-tab" :class="{ 'cd-officer-tab-active': activeTab === 'admin' }" @click="switchTab('admin')">
                <?php esc_html_e( 'Member Administration', 'community-directory' ); ?>
                <template x-if="pendingAppCount > 0"><span class="cd-officer-badge" x-text="pendingAppCount"></span></template>
            </button>
        </div></template>

        <!-- ═══════════════════════════════════════════ -->
        <!-- DIRECTORY TAB (default view for all users) -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="cd-main" x-show="activeTab === 'directory'">
            <!-- Search -->
            <div class="cd-search-bar">
                <input
                    type="search"
                    class="cd-search-input"
                    placeholder="<?php esc_attr_e( 'Search members or households...', 'community-directory' ); ?>"
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
            <template x-if="!loading && members.length === 0 && households.length === 0">
                <div class="cd-empty-state">
                    <p><?php esc_html_e( 'No members or households found matching your search.', 'community-directory' ); ?></p>
                </div>
            </template>

            <!-- Member Grid (shown first) -->
            <template x-if="!loading && members.length > 0"><div>
                <template x-if="households.length > 0">
                    <h2 class="cd-section-title" style="margin-top: 0;"><?php esc_html_e( 'Members', 'community-directory' ); ?></h2>
                </template>
                <div class="cd-member-grid">
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
            </div></div></template>

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

            <!-- Household Results (shown below members) -->
            <template x-if="!loading && households.length > 0"><div class="cd-household-results">
                <h2 class="cd-section-title" style="margin-top: 1.5rem;"><?php esc_html_e( 'Households', 'community-directory' ); ?></h2>
                <div class="cd-household-grid">
                    <template x-for="hh in households" :key="'hh-'+hh.id">
                        <div class="cd-household-card">
                            <div class="cd-household-card-photo" x-show="hh.photo_url">
                                <img :src="hh.photo_url" :alt="hh.name" class="cd-household-card-img">
                            </div>
                            <div class="cd-household-card-body">
                                <h3 class="cd-household-card-name" x-text="hh.name"></h3>
                                <p class="cd-text-muted cd-household-card-addr" x-show="hh.address && (hh.address.city || hh.address.line_1)" x-text="[hh.address.line_1, [hh.address.city, hh.address.state].filter(Boolean).join(', ')].filter(Boolean).join(', ')"></p>
                                <div class="cd-household-card-members">
                                    <template x-for="hm in hh.members" :key="hm.uuid">
                                        <a :href="'<?php echo esc_url( $member_url_base ); ?>' + hm.uuid" class="cd-household-card-member" :title="hm.first_name + ' ' + hm.last_name">
                                            <template x-if="hm.avatar_url">
                                                <img :src="hm.avatar_url" :alt="hm.first_name" class="cd-avatar-xs-img">
                                            </template>
                                            <template x-if="!hm.avatar_url">
                                                <div class="cd-avatar-xs" :style="'background-color: ' + getAvatarColor((hm.first_name||'') + ' ' + (hm.last_name||''))">
                                                    <span x-text="getInitials(hm.first_name, hm.last_name)"></span>
                                                </div>
                                            </template>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div></template>

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

        <!-- ═══════════════════════════════════════════════ -->
        <!-- MEMBER ADMINISTRATION TAB (officers only)      -->
        <!-- ═══════════════════════════════════════════════ -->
        <template x-if="isOfficer && activeTab === 'admin'"><div class="cd-admin-panel">

            <!-- Applications Section -->
            <div class="cd-admin-section">
                <h2 class="cd-section-title" style="margin-top: 0;">
                    <?php esc_html_e( 'Pending Applications', 'community-directory' ); ?>
                    <template x-if="appCounts.all > 0"><span class="cd-badge cd-badge-role" x-text="appCounts.all + ' total'" style="margin-left: 8px; font-size: 0.75rem;"></span></template>
                </h2>

                <!-- Application Status Filter Tabs -->
                <div class="cd-app-filters">
                    <button type="button" class="cd-app-filter-btn" :class="{ 'active': appStatusFilter === '' }" @click="appStatusFilter = ''; loadApplications()">
                        <?php esc_html_e( 'All', 'community-directory' ); ?> <span class="cd-filter-count" x-text="appCounts.all || 0"></span>
                    </button>
                    <button type="button" class="cd-app-filter-btn" :class="{ 'active': appStatusFilter === 'new' }" @click="appStatusFilter = 'new'; loadApplications()">
                        <?php esc_html_e( 'New', 'community-directory' ); ?> <span class="cd-filter-count" x-text="appCounts.new || 0"></span>
                    </button>
                    <button type="button" class="cd-app-filter-btn" :class="{ 'active': appStatusFilter === 'under_review' }" @click="appStatusFilter = 'under_review'; loadApplications()">
                        <?php esc_html_e( 'Under Review', 'community-directory' ); ?> <span class="cd-filter-count" x-text="appCounts.under_review || 0"></span>
                    </button>
                    <button type="button" class="cd-app-filter-btn" :class="{ 'active': appStatusFilter === 'on_hold' }" @click="appStatusFilter = 'on_hold'; loadApplications()">
                        <?php esc_html_e( 'On Hold', 'community-directory' ); ?> <span class="cd-filter-count" x-text="appCounts.on_hold || 0"></span>
                    </button>
                    <button type="button" class="cd-app-filter-btn" :class="{ 'active': appStatusFilter === 'approved' }" @click="appStatusFilter = 'approved'; loadApplications()">
                        <?php esc_html_e( 'Approved', 'community-directory' ); ?> <span class="cd-filter-count" x-text="appCounts.approved || 0"></span>
                    </button>
                    <button type="button" class="cd-app-filter-btn" :class="{ 'active': appStatusFilter === 'not_approved' }" @click="appStatusFilter = 'not_approved'; loadApplications()">
                        <?php esc_html_e( 'Rejected', 'community-directory' ); ?> <span class="cd-filter-count" x-text="appCounts.not_approved || 0"></span>
                    </button>
                </div>

                <!-- Loading -->
                <div x-show="appsLoading" class="cd-loading-state" style="padding: 2rem 0;">
                    <div class="cd-spinner cd-spinner-lg"></div>
                    <span><?php esc_html_e( 'Loading applications...', 'community-directory' ); ?></span>
                </div>

                <!-- Error -->
                <template x-if="appsError"><div class="cd-alert cd-alert-error" x-text="appsError"></div></template>

                <!-- Empty state -->
                <template x-if="!appsLoading && !appsError && applications.length === 0">
                    <div class="cd-empty-state" style="padding: 2rem 0;">
                        <p><?php esc_html_e( 'No applications found for this filter.', 'community-directory' ); ?></p>
                    </div>
                </template>

                <!-- Applications List -->
                <template x-if="!appsLoading && applications.length > 0"><div class="cd-apps-list">
                    <template x-for="app in applications" :key="app.id">
                        <div class="cd-app-card" :class="{ 'cd-app-card-expanded': expandedAppId === app.id }">
                            <!-- Summary Row -->
                            <div class="cd-app-summary" @click="toggleAppDetail(app.id)">
                                <div class="cd-app-summary-info">
                                    <strong x-text="app.first_name + ' ' + app.last_name"></strong>
                                    <span class="cd-text-muted" x-text="app.email" style="font-size: 0.85rem;"></span>
                                </div>
                                <div class="cd-app-summary-meta">
                                    <span class="cd-app-status-badge" :class="'cd-app-status-' + app.status" x-text="formatAppStatus(app.status)"></span>
                                    <span class="cd-text-muted" style="font-size: 0.8rem;" x-text="formatDate(app.submitted_at)"></span>
                                    <span class="cd-app-expand-icon" x-text="expandedAppId === app.id ? '\u25B2' : '\u25BC'"></span>
                                </div>
                            </div>

                            <!-- Expanded Detail -->
                            <template x-if="expandedAppId === app.id"><div class="cd-app-detail">
                                <div class="cd-app-detail-grid">
                                    <div>
                                        <span class="cd-detail-label"><?php esc_html_e( 'Phone:', 'community-directory' ); ?></span>
                                        <span x-text="app.phone || '—'"></span>
                                    </div>
                                    <template x-if="app.form_data && app.form_data.city"><div>
                                        <span class="cd-detail-label"><?php esc_html_e( 'Location:', 'community-directory' ); ?></span>
                                        <span x-text="[app.form_data.city, app.form_data.state].filter(Boolean).join(', ')"></span>
                                    </div></template>
                                    <template x-if="app.form_data && app.form_data.address_line_1"><div>
                                        <span class="cd-detail-label"><?php esc_html_e( 'Address:', 'community-directory' ); ?></span>
                                        <span x-text="app.form_data.address_line_1"></span>
                                    </div></template>
                                    <template x-if="app.form_data && app.form_data.profession"><div>
                                        <span class="cd-detail-label"><?php esc_html_e( 'Profession:', 'community-directory' ); ?></span>
                                        <span x-text="app.form_data.profession"></span>
                                    </div></template>
                                    <template x-if="app.form_data && app.form_data.prior_parishes"><div>
                                        <span class="cd-detail-label"><?php esc_html_e( 'Prior Parishes:', 'community-directory' ); ?></span>
                                        <span x-text="app.form_data.prior_parishes"></span>
                                    </div></template>
                                    <template x-if="app.form_data && app.form_data.date_of_birth"><div>
                                        <span class="cd-detail-label"><?php esc_html_e( 'Date of Birth:', 'community-directory' ); ?></span>
                                        <span x-text="app.form_data.date_of_birth"></span>
                                    </div></template>
                                    <template x-if="app.form_data && app.form_data.date_of_baptism"><div>
                                        <span class="cd-detail-label"><?php esc_html_e( 'Date of Baptism:', 'community-directory' ); ?></span>
                                        <span x-text="app.form_data.date_of_baptism"></span>
                                    </div></template>
                                    <template x-if="app.form_data && app.form_data.marital_status"><div>
                                        <span class="cd-detail-label"><?php esc_html_e( 'Marital Status:', 'community-directory' ); ?></span>
                                        <span x-text="app.form_data.marital_status"></span>
                                    </div></template>
                                </div>

                                <!-- Spouse info -->
                                <template x-if="app.form_data && app.form_data.spouse"><div class="cd-app-detail-sub">
                                    <h4><?php esc_html_e( 'Spouse', 'community-directory' ); ?></h4>
                                    <p>
                                        <span x-text="app.form_data.spouse.first_name + ' ' + (app.form_data.spouse.last_name || '')"></span>
                                        <template x-if="app.form_data.spouse.email"><span class="cd-text-muted" x-text="' — ' + app.form_data.spouse.email"></span></template>
                                    </p>
                                </div></template>

                                <!-- Children info -->
                                <template x-if="app.form_data && app.form_data.children && app.form_data.children.length > 0"><div class="cd-app-detail-sub">
                                    <h4><?php esc_html_e( 'Children', 'community-directory' ); ?></h4>
                                    <template x-for="child in app.form_data.children" :key="child.first_name">
                                        <p x-text="child.first_name + ' ' + (child.last_name || '') + (child.relationship ? ' (' + child.relationship + ')' : '')"></p>
                                    </template>
                                </div></template>

                                <!-- Ministry interests -->
                                <template x-if="app.form_data && app.form_data.ministry_interests && app.form_data.ministry_interests.length > 0"><div class="cd-app-detail-sub">
                                    <h4><?php esc_html_e( 'Ministry Interests', 'community-directory' ); ?></h4>
                                    <div class="cd-member-tags">
                                        <template x-for="interest in app.form_data.ministry_interests">
                                            <span class="cd-tag" x-text="interest"></span>
                                        </template>
                                    </div>
                                </div></template>

                                <!-- Reviewer info -->
                                <template x-if="app.reviewer_name"><div class="cd-app-detail-sub">
                                    <span class="cd-detail-label"><?php esc_html_e( 'Reviewed by:', 'community-directory' ); ?></span>
                                    <span x-text="app.reviewer_name"></span>
                                    <template x-if="app.reviewed_at"><span class="cd-text-muted" x-text="' on ' + formatDate(app.reviewed_at)"></span></template>
                                </div></template>

                                <!-- Existing notes -->
                                <template x-if="app.notes"><div class="cd-app-detail-sub">
                                    <span class="cd-detail-label"><?php esc_html_e( 'Notes:', 'community-directory' ); ?></span>
                                    <p class="cd-text-muted" x-text="app.notes" style="margin: 4px 0 0; white-space: pre-wrap;"></p>
                                </div></template>

                                <!-- Internal Notes Input -->
                                <template x-if="app.status === 'new' || app.status === 'under_review' || app.status === 'on_hold'"><div class="cd-app-notes-input">
                                    <label class="cd-label" style="font-size: 0.8rem;"><?php esc_html_e( 'Internal Notes (optional)', 'community-directory' ); ?></label>
                                    <textarea rows="2" class="cd-input" x-model="appActionNotes" placeholder="<?php esc_attr_e( 'Add notes about this application...', 'community-directory' ); ?>" style="font-size: 0.85rem;"></textarea>
                                </div></template>

                                <!-- Action Buttons -->
                                <template x-if="app.status === 'new' || app.status === 'under_review' || app.status === 'on_hold'"><div class="cd-app-actions">
                                    <button type="button" class="cd-btn cd-btn-sm cd-btn-primary" :disabled="appActioning" @click="appAction(app.id, 'approve')">
                                        <?php esc_html_e( 'Approve', 'community-directory' ); ?>
                                    </button>
                                    <button type="button" class="cd-btn cd-btn-sm cd-btn-danger-outline" :disabled="appActioning" @click="appAction(app.id, 'reject')">
                                        <?php esc_html_e( 'Reject', 'community-directory' ); ?>
                                    </button>
                                    <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" :disabled="appActioning" @click="appAction(app.id, 'hold')">
                                        <?php esc_html_e( 'Hold', 'community-directory' ); ?>
                                    </button>
                                    <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" :disabled="appActioning" @click="appAction(app.id, 'request_info')">
                                        <?php esc_html_e( 'Request Info', 'community-directory' ); ?>
                                    </button>
                                    <span x-show="appActioning" class="cd-spinner-sm"></span>
                                </div></template>

                                <!-- Action Success -->
                                <template x-if="appActionSuccess"><div class="cd-alert cd-alert-success" x-text="appActionSuccess" style="margin-top: 12px;"></div></template>
                                <template x-if="appActionError"><div class="cd-alert cd-alert-error" x-text="appActionError" style="margin-top: 12px;"></div></template>

                            </div></template>
                        </div>
                    </template>
                </div></template>

                <!-- Applications Pagination -->
                <template x-if="appsTotalPages > 1 && !appsLoading">
                    <div class="cd-pagination" style="margin-top: 1.5rem; justify-content: center; gap: 1rem;">
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" :disabled="appsPage <= 1" @click="appsPage--; loadApplications()">&laquo; <?php esc_html_e( 'Previous', 'community-directory' ); ?></button>
                        <span style="align-self: center; font-size: 0.9em; color: #666;">
                            <?php esc_html_e( 'Page', 'community-directory' ); ?> <span x-text="appsPage"></span> <?php esc_html_e( 'of', 'community-directory' ); ?> <span x-text="appsTotalPages"></span>
                        </span>
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" :disabled="appsPage >= appsTotalPages" @click="appsPage++; loadApplications()"><?php esc_html_e( 'Next', 'community-directory' ); ?> &raquo;</button>
                    </div>
                </template>
            </div>

        </div></template>

    </div>
</div>

<?php get_footer(); ?>
