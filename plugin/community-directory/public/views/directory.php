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
            <!-- Search + Settings Gear -->
            <div class="cd-search-bar">
                <input
                    type="search"
                    class="cd-search-input"
                    placeholder="<?php esc_attr_e( 'Search members or households...', 'community-directory' ); ?>"
                    x-model="searchQuery"
                    @input.debounce.300ms="search()"
                >
                <button type="button" class="cd-settings-gear" @click="openSettings()" :title="'<?php echo esc_js( __( 'Directory Settings', 'community-directory' ) ); ?>'" aria-label="<?php esc_attr_e( 'Directory Settings', 'community-directory' ); ?>">&#9881;&#65039;</button>
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

            <!-- Active View Label -->
            <div class="cd-view-label" x-show="!loading && !searchQuery">
                <span class="cd-text-muted" x-text="viewLabel()"></span>
            </div>

            <!-- Empty State -->
            <template x-if="!loading && members.length === 0 && households.length === 0">
                <div class="cd-empty-state">
                    <p><?php esc_html_e( 'No members or households found matching your search.', 'community-directory' ); ?></p>
                </div>
            </template>

            <!-- ── Results rendered by section order ── -->
            <template x-for="section in getVisibleSections()" :key="section">
                <div>
                    <!-- Member Grid Section -->
                    <template x-if="section !== 'households' && getMembersForSection(section).length > 0"><div>
                        <template x-if="getVisibleSections().length > 1">
                            <h2 class="cd-section-title" style="margin-top: 0.5rem;" x-text="sectionTitle(section)"></h2>
                        </template>
                        <div class="cd-member-grid">
                        <template x-for="member in getMembersForSection(section)" :key="member.uuid">
                            <a :href="'<?php echo esc_url( $member_url_base ); ?>' + member.uuid" class="cd-member-card">
                                <div class="cd-member-avatar-wrapper">
                                    <template x-if="member.avatar_url">
                                        <img :src="member.avatar_url" class="cd-member-avatar-img"
                                            @error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'">
                                    </template>
                                    <div class="cd-member-avatar-fallback"
                                        :style="'background-color: ' + getAvatarColor(member.first_name + ' ' + member.last_name) + '; display: ' + (member.avatar_url ? 'none' : 'flex')">
                                        <span x-text="getInitials(member.first_name, member.last_name)"></span>
                                    </div>
                                </div>
                                <div class="cd-member-info">
                                    <h3 x-text="displayName(member)"></h3>
                                    <div class="cd-member-meta" x-show="member.city">
                                        <span x-text="member.city + (member.state ? ', ' + member.state : '')"></span>
                                    </div>
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
                    </div></template>

                    <!-- Household Grid Section -->
                    <template x-if="section === 'households' && households.length > 0"><div class="cd-household-results">
                        <template x-if="getVisibleSections().length > 1">
                            <h2 class="cd-section-title" style="margin-top: 1.5rem;"><?php esc_html_e( 'Households', 'community-directory' ); ?></h2>
                        </template>
                        <div class="cd-household-grid">
                            <template x-for="hh in households" :key="'hh-'+hh.id">
                                <a :href="hh.owner_uuid ? '<?php echo esc_url( $member_url_base ); ?>' + hh.owner_uuid : '#'" class="cd-household-card cd-household-card-link">
                                    <div class="cd-household-card-photo" x-show="hh.photo_url">
                                        <img :src="hh.photo_url" :alt="hh.name" class="cd-household-card-img"
                                             :style="'object-position: ' + (hh.photo_fx ?? 50) + '% ' + (hh.photo_fy ?? 50) + '%; transform: scale(' + (hh.photo_zoom ?? 1) + '); transform-origin: ' + (hh.photo_fx ?? 50) + '% ' + (hh.photo_fy ?? 50) + '%'">
                                    </div>
                                    <div class="cd-household-card-body">
                                        <h3 class="cd-household-card-name" x-text="hh.name"></h3>
                                        <template x-if="hh.owner_first_name">
                                            <p class="cd-household-card-owner cd-text-muted" x-text="hh.owner_first_name + ' ' + hh.owner_last_name" style="font-size: 0.85em; margin: 2px 0 4px;"></p>
                                        </template>
                                        <p class="cd-text-muted cd-household-card-addr" x-show="hh.address && (hh.address.city || hh.address.line_1)" x-text="[hh.address.line_1, [hh.address.city, hh.address.state].filter(Boolean).join(', ')].filter(Boolean).join(', ')"></p>
                                        <div class="cd-household-card-members">
                                            <template x-for="hm in hh.members" :key="hm.uuid">
                                                <span class="cd-household-card-member cd-household-avatar-link" :title="hm.first_name + ' ' + hm.last_name" @click.prevent.stop="window.location.href = '<?php echo esc_url( $member_url_base ); ?>' + hm.uuid">
                                                    <template x-if="hm.avatar_url">
                                                        <img :src="hm.avatar_url" :alt="hm.first_name" class="cd-avatar-xs-img">
                                                    </template>
                                                    <template x-if="!hm.avatar_url">
                                                        <div class="cd-avatar-xs" :style="'background-color: ' + getAvatarColor((hm.first_name||'') + ' ' + (hm.last_name||''))">
                                                            <span x-text="getInitials(hm.first_name, hm.last_name)"></span>
                                                        </div>
                                                    </template>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </a>
                            </template>
                        </div>
                    </div></template>
                </div>
            </template>

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

            <!-- ═══ Settings Modal ═══ -->
            <template x-if="showSettingsModal"><div class="cd-modal-overlay" @click.self="showSettingsModal = false">
                <div class="cd-modal cd-modal-settings">
                    <div class="cd-modal-header">
                        <h3><?php esc_html_e( 'Directory Settings', 'community-directory' ); ?></h3>
                        <button type="button" class="cd-btn cd-btn-text" @click="showSettingsModal = false" style="padding: 4px 8px; font-size: 1.25rem; line-height: 1;">&times;</button>
                    </div>
                    <div class="cd-modal-body">
                        <!-- Default View -->
                        <div class="cd-settings-group">
                            <label class="cd-label"><?php esc_html_e( 'Default View', 'community-directory' ); ?></label>
                            <p class="cd-help-text" style="margin: 0 0 8px;"><?php esc_html_e( 'Choose what you see when you first open the directory.', 'community-directory' ); ?></p>
                            <div class="cd-settings-radios">
                                <label class="cd-radio-label">
                                    <input type="radio" name="cd_default_view" value="all" x-model="settingsForm.default_view">
                                    <?php esc_html_e( 'All members', 'community-directory' ); ?>
                                </label>
                                <label class="cd-radio-label">
                                    <input type="radio" name="cd_default_view" value="adults_only" x-model="settingsForm.default_view">
                                    <?php esc_html_e( 'Adults only', 'community-directory' ); ?>
                                </label>
                                <label class="cd-radio-label">
                                    <input type="radio" name="cd_default_view" value="children_only" x-model="settingsForm.default_view">
                                    <?php esc_html_e( 'Children & others only', 'community-directory' ); ?>
                                </label>
                                <label class="cd-radio-label">
                                    <input type="radio" name="cd_default_view" value="primary_only" x-model="settingsForm.default_view">
                                    <?php esc_html_e( 'Primary members only (heads of household)', 'community-directory' ); ?>
                                </label>
                                <label class="cd-radio-label">
                                    <input type="radio" name="cd_default_view" value="household_view" x-model="settingsForm.default_view">
                                    <?php esc_html_e( 'Household cards', 'community-directory' ); ?>
                                </label>
                            </div>
                        </div>

                        <!-- Sort Order -->
                        <div class="cd-settings-group" style="margin-top: 1.25rem;">
                            <label class="cd-label"><?php esc_html_e( 'Sort Order', 'community-directory' ); ?></label>
                            <p class="cd-help-text" style="margin: 0 0 8px;"><?php esc_html_e( 'How members are sorted in all views and search results.', 'community-directory' ); ?></p>
                            <div class="cd-settings-radios">
                                <label class="cd-radio-label">
                                    <input type="radio" name="cd_sort_order" value="first_name" x-model="settingsForm.sort_order">
                                    <?php esc_html_e( 'First name A-Z', 'community-directory' ); ?>
                                </label>
                                <label class="cd-radio-label">
                                    <input type="radio" name="cd_sort_order" value="last_name" x-model="settingsForm.sort_order">
                                    <?php esc_html_e( 'Last name A-Z', 'community-directory' ); ?>
                                </label>
                            </div>
                        </div>

                        <!-- Search Result Sections -->
                        <div class="cd-settings-group" style="margin-top: 1.25rem;">
                            <label class="cd-label"><?php esc_html_e( 'Search Result Sections', 'community-directory' ); ?></label>
                            <p class="cd-help-text" style="margin: 0 0 8px;"><?php esc_html_e( 'Toggle and reorder which sections appear in search results.', 'community-directory' ); ?></p>
                            <div class="cd-settings-sections">
                                <template x-for="(sec, idx) in settingsForm.search_sections" :key="sec">
                                    <div class="cd-settings-section-row">
                                        <div class="cd-settings-section-arrows">
                                            <button type="button" class="cd-settings-arrow" :disabled="idx === 0" @click="moveSection(idx, -1)" title="Move up">&uarr;</button>
                                            <button type="button" class="cd-settings-arrow" :disabled="idx === settingsForm.search_sections.length - 1" @click="moveSection(idx, 1)" title="Move down">&darr;</button>
                                        </div>
                                        <span class="cd-settings-section-label" x-text="sectionLabel(sec)"></span>
                                        <button type="button" class="cd-settings-section-remove" @click="removeSection(sec)" title="Remove">&times;</button>
                                    </div>
                                </template>
                            </div>
                            <!-- Add section dropdown -->
                            <template x-if="availableSections().length > 0">
                                <div style="margin-top: 8px;">
                                    <select class="cd-input" style="font-size: 0.85rem; padding: 6px 10px;" @change="addSection($event.target.value); $event.target.value = ''">
                                        <option value=""><?php esc_html_e( '+ Add section...', 'community-directory' ); ?></option>
                                        <template x-for="s in availableSections()" :key="s">
                                            <option :value="s" x-text="sectionLabel(s)"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="cd-modal-footer" style="display: flex; gap: 8px; justify-content: flex-end;">
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="showSettingsModal = false">
                            <?php esc_html_e( 'Cancel', 'community-directory' ); ?>
                        </button>
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-primary" :disabled="prefsSaving" @click="savePreferences()">
                            <span x-show="!prefsSaving"><?php esc_html_e( 'Save', 'community-directory' ); ?></span>
                            <span x-show="prefsSaving"><?php esc_html_e( 'Saving...', 'community-directory' ); ?></span>
                        </button>
                    </div>
                </div>
            </div></template>

            <!-- WhatsApp Groups Section -->
            <template x-if="whatsappGroups.length > 0"><div class="cd-whatsapp-section">
                <h2 class="cd-section-title"><?php esc_html_e( 'WhatsApp Groups', 'community-directory' ); ?></h2>
                <p class="cd-text-muted" style="margin-bottom: 1rem;"><?php esc_html_e( 'Stay connected with the community through our WhatsApp groups.', 'community-directory' ); ?></p>
                <div class="cd-whatsapp-grid">
                    <template x-for="group in whatsappGroups" :key="group.id">
                        <div class="cd-whatsapp-card">
                            <div class="cd-whatsapp-icon">
                                <template x-if="group.icon"><span x-text="group.icon"></span></template>
                                <template x-if="!group.icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></template>
                            </div>
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

            <!-- Admin Sub-Navigation -->
            <div class="cd-admin-nav">
                <button type="button" class="cd-admin-nav-btn" :class="{ 'active': adminSection === 'dashboard' }" @click="switchAdminSection('dashboard')">
                    <?php esc_html_e( 'Dashboard', 'community-directory' ); ?>
                </button>
                <button type="button" class="cd-admin-nav-btn" :class="{ 'active': adminSection === 'applications' }" @click="switchAdminSection('applications')">
                    <?php esc_html_e( 'Applications', 'community-directory' ); ?>
                    <template x-if="pendingAppCount > 0"><span class="cd-admin-nav-badge" x-text="pendingAppCount"></span></template>
                </button>
                <button type="button" class="cd-admin-nav-btn" :class="{ 'active': adminSection === 'registrations' }" @click="switchAdminSection('registrations')">
                    <?php esc_html_e( 'Registrations', 'community-directory' ); ?>
                </button>
                <button type="button" class="cd-admin-nav-btn" :class="{ 'active': adminSection === 'requests' }" @click="switchAdminSection('requests')">
                    <?php esc_html_e( 'Requests', 'community-directory' ); ?>
                </button>
                <button type="button" class="cd-admin-nav-btn" :class="{ 'active': adminSection === 'whatsapp' }" @click="switchAdminSection('whatsapp')">
                    <?php esc_html_e( 'WhatsApp', 'community-directory' ); ?>
                </button>
            </div>

            <!-- ═══ DASHBOARD SECTION ═══ -->
            <div x-show="adminSection === 'dashboard'" class="cd-admin-section">
                <div x-show="dashLoading" class="cd-loading-state" style="padding: 2rem 0;">
                    <div class="cd-spinner"></div>
                    <span><?php esc_html_e( 'Loading stats...', 'community-directory' ); ?></span>
                </div>
                <template x-if="dashError && !dashLoading"><div class="cd-alert cd-alert-danger" style="margin: 1rem 0;">
                    <strong><?php esc_html_e( 'Error loading dashboard:', 'community-directory' ); ?></strong> <span x-text="dashError"></span>
                    <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="loadDashboardStats()" style="margin-left: 12px;"><?php esc_html_e( 'Retry', 'community-directory' ); ?></button>
                </div></template>
                <template x-if="dashStats && !dashLoading"><div>
                    <div class="cd-dash-grid">
                        <div class="cd-dash-card cd-dash-card-primary">
                            <div class="cd-dash-card-value" x-text="dashStats.status_counts ? dashStats.status_counts.active || 0 : 0"></div>
                            <div class="cd-dash-card-label"><?php esc_html_e( 'Active Members', 'community-directory' ); ?></div>
                        </div>
                        <div class="cd-dash-card cd-dash-card-warning">
                            <div class="cd-dash-card-value" x-text="dashStats.pending_applications || 0"></div>
                            <div class="cd-dash-card-label"><?php esc_html_e( 'Pending Applications', 'community-directory' ); ?></div>
                        </div>
                        <div class="cd-dash-card cd-dash-card-info">
                            <div class="cd-dash-card-value" x-text="dashStats.status_counts ? dashStats.status_counts.awaiting_verification || 0 : 0"></div>
                            <div class="cd-dash-card-label"><?php esc_html_e( 'Awaiting Verification', 'community-directory' ); ?></div>
                        </div>
                        <div class="cd-dash-card cd-dash-card-danger">
                            <div class="cd-dash-card-value" x-text="dashStats.status_counts ? dashStats.status_counts.deletion_requests || 0 : 0"></div>
                            <div class="cd-dash-card-label"><?php esc_html_e( 'Deletion Requests', 'community-directory' ); ?></div>
                        </div>
                        <div class="cd-dash-card cd-dash-card-success">
                            <div class="cd-dash-card-value" x-text="dashStats.household_stats ? dashStats.household_stats.total || 0 : 0"></div>
                            <div class="cd-dash-card-label"><?php esc_html_e( 'Households', 'community-directory' ); ?></div>
                        </div>
                        <div class="cd-dash-card cd-dash-card-muted">
                            <div class="cd-dash-card-value" x-text="dashStats.status_counts ? dashStats.status_counts.incomplete_profiles || 0 : 0"></div>
                            <div class="cd-dash-card-label"><?php esc_html_e( 'Incomplete Profiles', 'community-directory' ); ?></div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <template x-if="dashStats.recent_activity && dashStats.recent_activity.length > 0"><div style="margin-top: 1.5rem;">
                        <h3 class="cd-section-title" style="font-size: 1rem;"><?php esc_html_e( 'Recent Activity', 'community-directory' ); ?></h3>
                        <div class="cd-activity-list">
                            <template x-for="activity in dashStats.recent_activity.slice(0, 10)" :key="activity.id || activity.created_at">
                                <div class="cd-activity-item">
                                    <span class="cd-activity-action" x-text="activity.action"></span>
                                    <span class="cd-activity-detail cd-text-muted" x-text="activity.details || ''"></span>
                                    <span class="cd-activity-date cd-text-muted" x-text="formatDate(activity.created_at)"></span>
                                </div>
                            </template>
                        </div>
                    </div></template>
                </div></template>
            </div>

            <!-- ═══ APPLICATIONS SECTION ═══ -->
            <div x-show="adminSection === 'applications'" class="cd-admin-section">
                <h2 class="cd-section-title" style="margin-top: 0;">
                    <?php esc_html_e( 'Applications', 'community-directory' ); ?>
                    <template x-if="appCounts.all > 0"><span class="cd-badge cd-badge-role" x-text="appCounts.all + ' total'" style="margin-left: 8px; font-size: 0.75rem;"></span></template>
                </h2>

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

                <div x-show="appsLoading" class="cd-loading-state" style="padding: 2rem 0;">
                    <div class="cd-spinner"></div>
                    <span><?php esc_html_e( 'Loading applications...', 'community-directory' ); ?></span>
                </div>
                <template x-if="appsError"><div class="cd-alert cd-alert-error" x-text="appsError"></div></template>
                <template x-if="!appsLoading && !appsError && applications.length === 0">
                    <div class="cd-empty-state" style="padding: 2rem 0;">
                        <p><?php esc_html_e( 'No applications found for this filter.', 'community-directory' ); ?></p>
                    </div>
                </template>

                <template x-if="!appsLoading && applications.length > 0"><div class="cd-apps-list">
                    <template x-for="app in applications" :key="app.id">
                        <div class="cd-app-card" :class="{ 'cd-app-card-expanded': expandedAppId === app.id }">
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
                                <template x-if="app.form_data && app.form_data.spouse"><div class="cd-app-detail-sub">
                                    <h4><?php esc_html_e( 'Spouse', 'community-directory' ); ?></h4>
                                    <p>
                                        <span x-text="app.form_data.spouse.first_name + ' ' + (app.form_data.spouse.last_name || '')"></span>
                                        <template x-if="app.form_data.spouse.email"><span class="cd-text-muted" x-text="' — ' + app.form_data.spouse.email"></span></template>
                                    </p>
                                </div></template>
                                <template x-if="app.form_data && app.form_data.children && app.form_data.children.length > 0"><div class="cd-app-detail-sub">
                                    <h4><?php esc_html_e( 'Children', 'community-directory' ); ?></h4>
                                    <template x-for="child in app.form_data.children" :key="child.first_name">
                                        <p x-text="child.first_name + ' ' + (child.last_name || '') + (child.relationship ? ' (' + child.relationship + ')' : '')"></p>
                                    </template>
                                </div></template>
                                <template x-if="app.form_data && app.form_data.ministry_interests && app.form_data.ministry_interests.length > 0"><div class="cd-app-detail-sub">
                                    <h4><?php esc_html_e( 'Ministry Interests', 'community-directory' ); ?></h4>
                                    <div class="cd-member-tags">
                                        <template x-for="interest in app.form_data.ministry_interests">
                                            <span class="cd-tag" x-text="interest"></span>
                                        </template>
                                    </div>
                                </div></template>
                                <template x-if="app.reviewer_name"><div class="cd-app-detail-sub">
                                    <span class="cd-detail-label"><?php esc_html_e( 'Reviewed by:', 'community-directory' ); ?></span>
                                    <span x-text="app.reviewer_name"></span>
                                    <template x-if="app.reviewed_at"><span class="cd-text-muted" x-text="' on ' + formatDate(app.reviewed_at)"></span></template>
                                </div></template>
                                <template x-if="app.notes"><div class="cd-app-detail-sub">
                                    <span class="cd-detail-label"><?php esc_html_e( 'Notes:', 'community-directory' ); ?></span>
                                    <p class="cd-text-muted" x-text="app.notes" style="margin: 4px 0 0; white-space: pre-wrap;"></p>
                                </div></template>
                                <template x-if="app.status === 'new' || app.status === 'under_review' || app.status === 'on_hold'"><div class="cd-app-notes-input">
                                    <label class="cd-label" style="font-size: 0.8rem;"><?php esc_html_e( 'Internal Notes (optional)', 'community-directory' ); ?></label>
                                    <textarea rows="2" class="cd-input" x-model="appActionNotes" placeholder="<?php esc_attr_e( 'Add notes about this application...', 'community-directory' ); ?>" style="font-size: 0.85rem;"></textarea>
                                </div></template>
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
                                <template x-if="appActionSuccess"><div class="cd-alert cd-alert-success" x-text="appActionSuccess" style="margin-top: 12px;"></div></template>
                                <template x-if="appActionError"><div class="cd-alert cd-alert-error" x-text="appActionError" style="margin-top: 12px;"></div></template>
                            </div></template>
                        </div>
                    </template>
                </div></template>

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

            <!-- ═══ REGISTRATIONS SECTION ═══ -->
            <div x-show="adminSection === 'registrations'" class="cd-admin-section">
                <h2 class="cd-section-title" style="margin-top: 0;"><?php esc_html_e( 'Registration Pipeline', 'community-directory' ); ?></h2>

                <div x-show="regsLoading" class="cd-loading-state" style="padding: 2rem 0;">
                    <div class="cd-spinner"></div>
                    <span><?php esc_html_e( 'Loading registrations...', 'community-directory' ); ?></span>
                </div>
                <template x-if="regsError"><div class="cd-alert cd-alert-error" x-text="regsError"></div></template>
                <template x-if="!regsLoading && !regsError && registrations.length === 0">
                    <div class="cd-empty-state" style="padding: 2rem 0;">
                        <p><?php esc_html_e( 'No registrations in the pipeline.', 'community-directory' ); ?></p>
                    </div>
                </template>

                <template x-if="!regsLoading && registrations.length > 0"><div>
                    <!-- Counts summary -->
                    <template x-if="regCounts"><div class="cd-reg-counts">
                        <span class="cd-reg-count-item"><strong x-text="regCounts.total || 0"></strong> <?php esc_html_e( 'total', 'community-directory' ); ?></span>
                        <template x-if="regCounts.unverified > 0"><span class="cd-reg-count-item cd-status-warning"><strong x-text="regCounts.unverified"></strong> <?php esc_html_e( 'unverified', 'community-directory' ); ?></span></template>
                        <template x-if="regCounts.pending > 0"><span class="cd-reg-count-item cd-status-info"><strong x-text="regCounts.pending"></strong> <?php esc_html_e( 'pending review', 'community-directory' ); ?></span></template>
                        <template x-if="regCounts.approved > 0"><span class="cd-reg-count-item cd-status-success"><strong x-text="regCounts.approved"></strong> <?php esc_html_e( 'approved', 'community-directory' ); ?></span></template>
                    </div></template>

                    <div class="cd-reg-list">
                        <template x-for="reg in registrations" :key="reg.id">
                            <div class="cd-reg-card">
                                <div class="cd-reg-card-info">
                                    <strong x-text="(reg.first_name || '') + ' ' + (reg.last_name || '')"></strong>
                                    <span class="cd-text-muted" x-text="reg.email" style="font-size: 0.85rem;"></span>
                                </div>
                                <div class="cd-reg-card-meta">
                                    <span class="cd-reg-status-badge" :class="regStatusClass(reg.status)" x-text="regStatusLabel(reg.status)"></span>
                                    <span class="cd-text-muted" style="font-size: 0.8rem;" x-text="reg.type === 'member_invite' ? 'Invite' : 'Application'"></span>
                                    <span class="cd-text-muted" style="font-size: 0.8rem;" x-text="formatDate(reg.submitted_at || reg.created_at)"></span>
                                </div>
                                <div class="cd-reg-card-actions">
                                    <template x-if="reg.status === 'unverified'">
                                        <button type="button" class="cd-btn cd-btn-xs cd-btn-secondary" :disabled="regActioning === reg.id" @click="regResendVerification(reg.id)">
                                            <?php esc_html_e( 'Resend Verification', 'community-directory' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="reg.type === 'member_invite' || reg.status === 'approved' || reg.status === 'invited'">
                                        <button type="button" class="cd-btn cd-btn-xs cd-btn-secondary" :disabled="regActioning === reg.id" @click="regResendInvite(reg.id)">
                                            <?php esc_html_e( 'Resend Invite', 'community-directory' ); ?>
                                        </button>
                                    </template>
                                    <button type="button" class="cd-btn cd-btn-xs cd-btn-danger-outline" :disabled="regActioning === reg.id" @click="regRemove(reg.id)">
                                        <?php esc_html_e( 'Remove', 'community-directory' ); ?>
                                    </button>
                                    <span x-show="regActioning === reg.id" class="cd-spinner-sm"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div></template>
            </div>

            <!-- ═══ REQUESTS SECTION (Deletion + Household Merge) ═══ -->
            <div x-show="adminSection === 'requests'" class="cd-admin-section">

                <!-- Deletion Requests -->
                <h2 class="cd-section-title" style="margin-top: 0;"><?php esc_html_e( 'Deletion Requests', 'community-directory' ); ?></h2>
                <div x-show="delReqLoading" class="cd-loading-state" style="padding: 1rem 0;">
                    <div class="cd-spinner"></div>
                </div>
                <template x-if="delReqError"><div class="cd-alert cd-alert-error" x-text="delReqError"></div></template>
                <template x-if="!delReqLoading && !delReqError && deletionRequests.length === 0">
                    <div class="cd-empty-state" style="padding: 1rem 0;">
                        <p><?php esc_html_e( 'No pending deletion requests.', 'community-directory' ); ?></p>
                    </div>
                </template>
                <template x-if="!delReqLoading && deletionRequests.length > 0"><div class="cd-req-list">
                    <template x-for="req in deletionRequests" :key="req.id">
                        <div class="cd-req-card">
                            <div class="cd-req-card-info">
                                <strong x-text="(req.member_name || req.first_name + ' ' + req.last_name)"></strong>
                                <span class="cd-text-muted" x-text="req.reason || ''" style="font-size: 0.85rem;"></span>
                            </div>
                            <div class="cd-req-card-meta">
                                <span class="cd-text-muted" x-text="formatDate(req.created_at)"></span>
                            </div>
                            <template x-if="req.status === 'pending'"><div class="cd-req-card-actions">
                                <button type="button" class="cd-btn cd-btn-xs cd-btn-primary" :disabled="delReqActioning === req.id" @click="delReqAction(req.id, 'approve')">
                                    <?php esc_html_e( 'Approve', 'community-directory' ); ?>
                                </button>
                                <button type="button" class="cd-btn cd-btn-xs cd-btn-secondary" :disabled="delReqActioning === req.id" @click="delReqAction(req.id, 'deny')">
                                    <?php esc_html_e( 'Deny', 'community-directory' ); ?>
                                </button>
                                <span x-show="delReqActioning === req.id" class="cd-spinner-sm"></span>
                            </div></template>
                            <template x-if="req.status !== 'pending'">
                                <span class="cd-reg-status-badge" :class="req.status === 'approved' ? 'cd-status-success' : 'cd-status-muted'" x-text="req.status"></span>
                            </template>
                        </div>
                    </template>
                </div></template>

                <!-- Household Merge Requests -->
                <h2 class="cd-section-title" style="margin-top: 2rem;"><?php esc_html_e( 'Household Merge Requests', 'community-directory' ); ?></h2>
                <div x-show="hhReqLoading" class="cd-loading-state" style="padding: 1rem 0;">
                    <div class="cd-spinner"></div>
                </div>
                <template x-if="hhReqError"><div class="cd-alert cd-alert-error" x-text="hhReqError"></div></template>
                <template x-if="!hhReqLoading && !hhReqError && householdRequests.length === 0">
                    <div class="cd-empty-state" style="padding: 1rem 0;">
                        <p><?php esc_html_e( 'No pending household merge requests.', 'community-directory' ); ?></p>
                    </div>
                </template>
                <template x-if="!hhReqLoading && householdRequests.length > 0"><div class="cd-req-list">
                    <template x-for="req in householdRequests" :key="req.id">
                        <div class="cd-req-card">
                            <div class="cd-req-card-info">
                                <strong x-text="(req.source_household_name || 'Source') + ' → ' + (req.target_household_name || 'Target')"></strong>
                                <span class="cd-text-muted" x-text="'Requested by ' + (req.requester_name || 'Unknown')" style="font-size: 0.85rem;"></span>
                            </div>
                            <div class="cd-req-card-meta">
                                <span class="cd-text-muted" x-text="formatDate(req.created_at)"></span>
                            </div>
                            <template x-if="req.status === 'pending'"><div class="cd-req-card-actions">
                                <button type="button" class="cd-btn cd-btn-xs cd-btn-primary" :disabled="hhReqActioning === req.id" @click="hhReqAction(req.id, 'approve')">
                                    <?php esc_html_e( 'Approve', 'community-directory' ); ?>
                                </button>
                                <button type="button" class="cd-btn cd-btn-xs cd-btn-secondary" :disabled="hhReqActioning === req.id" @click="hhReqAction(req.id, 'deny')">
                                    <?php esc_html_e( 'Deny', 'community-directory' ); ?>
                                </button>
                                <span x-show="hhReqActioning === req.id" class="cd-spinner-sm"></span>
                            </div></template>
                            <template x-if="req.status !== 'pending'">
                                <span class="cd-reg-status-badge" :class="req.status === 'approved' ? 'cd-status-success' : 'cd-status-muted'" x-text="req.status"></span>
                            </template>
                        </div>
                    </template>
                </div></template>
            </div>

            <!-- ═══ WHATSAPP MANAGEMENT SECTION ═══ -->
            <div x-show="adminSection === 'whatsapp'" class="cd-admin-section">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h2 class="cd-section-title" style="margin: 0;"><?php esc_html_e( 'WhatsApp Groups', 'community-directory' ); ?></h2>
                    <button type="button" class="cd-btn cd-btn-sm cd-btn-primary" @click="waNewGroup()">
                        + <?php esc_html_e( 'Add Group', 'community-directory' ); ?>
                    </button>
                </div>

                <!-- Add/Edit Form -->
                <template x-if="waShowForm"><div class="cd-wa-form-card">
                    <h3 x-text="waEditing ? '<?php echo esc_js( __( 'Edit Group', 'community-directory' ) ); ?>' : '<?php echo esc_js( __( 'New Group', 'community-directory' ) ); ?>'" style="margin: 0 0 12px; font-size: 1rem;"></h3>
                    <div class="cd-grid-2">
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Name *', 'community-directory' ); ?></label>
                            <input type="text" class="cd-input" x-model="waForm.name">
                        </div>
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Invite URL *', 'community-directory' ); ?></label>
                            <input type="url" class="cd-input" x-model="waForm.invite_url" placeholder="https://chat.whatsapp.com/...">
                        </div>
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Description', 'community-directory' ); ?></label>
                            <input type="text" class="cd-input" x-model="waForm.description">
                        </div>
                        <div class="cd-form-group" style="display: flex; gap: 12px;">
                            <div style="flex: 0 0 80px;">
                                <label class="cd-label"><?php esc_html_e( 'Icon', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="waForm.icon" placeholder="emoji" style="text-align: center;">
                            </div>
                            <div style="flex: 0 0 80px;">
                                <label class="cd-label"><?php esc_html_e( 'Order', 'community-directory' ); ?></label>
                                <input type="number" class="cd-input" x-model.number="waForm.display_order" min="0">
                            </div>
                            <div style="flex: 1;">
                                <label class="cd-label"><?php esc_html_e( 'Visibility', 'community-directory' ); ?></label>
                                <select class="cd-input" x-model="waForm.visibility">
                                    <option value="all"><?php esc_html_e( 'All Members', 'community-directory' ); ?></option>
                                    <option value="tag"><?php esc_html_e( 'By Tag', 'community-directory' ); ?></option>
                                </select>
                            </div>
                        </div>
                        <template x-if="waForm.visibility === 'tag'"><div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Visibility Tag', 'community-directory' ); ?></label>
                            <input type="text" class="cd-input" x-model="waForm.visibility_tag">
                        </div></template>
                    </div>
                    <div class="cd-form-group" style="margin-top: 8px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 0.875rem;">
                            <input type="checkbox" x-model="waForm.is_active"> <?php esc_html_e( 'Active', 'community-directory' ); ?>
                        </label>
                    </div>
                    <div style="display: flex; gap: 8px; margin-top: 12px;">
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-primary" :disabled="waSaving" @click="waSaveGroup()">
                            <?php esc_html_e( 'Save', 'community-directory' ); ?>
                        </button>
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="waCancelForm()">
                            <?php esc_html_e( 'Cancel', 'community-directory' ); ?>
                        </button>
                        <span x-show="waSaving" class="cd-spinner-sm"></span>
                    </div>
                </div></template>

                <div x-show="waLoading" class="cd-loading-state" style="padding: 2rem 0;">
                    <div class="cd-spinner"></div>
                </div>
                <template x-if="waError"><div class="cd-alert cd-alert-error" x-text="waError"></div></template>
                <template x-if="!waLoading && !waError && waGroups.length === 0 && !waShowForm">
                    <div class="cd-empty-state" style="padding: 2rem 0;">
                        <p><?php esc_html_e( 'No WhatsApp groups. Click "Add Group" to create one.', 'community-directory' ); ?></p>
                    </div>
                </template>

                <template x-if="!waLoading && waGroups.length > 0"><div class="cd-wa-list">
                    <template x-for="group in waGroups" :key="group.id">
                        <div class="cd-wa-card" :class="{ 'cd-wa-inactive': !group.is_active }">
                            <div class="cd-wa-card-info">
                                <strong x-text="(group.icon ? group.icon + ' ' : '') + group.name"></strong>
                                <span class="cd-text-muted" x-text="group.description" style="font-size: 0.85rem;"></span>
                            </div>
                            <div class="cd-wa-card-meta">
                                <span class="cd-text-muted" x-text="group.visibility === 'tag' ? 'Tag: ' + group.visibility_tag : 'All Members'" style="font-size: 0.8rem;"></span>
                                <template x-if="!group.is_active"><span class="cd-reg-status-badge cd-status-muted"><?php esc_html_e( 'Inactive', 'community-directory' ); ?></span></template>
                            </div>
                            <div class="cd-wa-card-actions">
                                <button type="button" class="cd-btn cd-btn-xs cd-btn-secondary" @click="waEditGroup(group)">
                                    <?php esc_html_e( 'Edit', 'community-directory' ); ?>
                                </button>
                                <button type="button" class="cd-btn cd-btn-xs cd-btn-danger-outline" @click="waDeleteGroup(group.id)">
                                    <?php esc_html_e( 'Delete', 'community-directory' ); ?>
                                </button>
                            </div>
                        </div>
                    </template>
                </div></template>
            </div>

        </div></template>

    </div>
</div>

<?php get_footer(); ?>
