<?php
/**
 * Community Directory — View Member Profile.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 * Member UUID is passed to JS via wp_add_inline_script in class-plugin.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug     = get_option( 'cd_base_slug', 'community' );
$directory_url = home_url( $base_slug . '/directory/' );
$profile_url   = home_url( $base_slug . '/profile/' );
$login_url     = home_url( $base_slug . '/login/' );

if ( ! is_user_logged_in() ) {
    wp_redirect( $login_url );
    exit;
}

get_header();
?>

<div class="cd-wrap cd-member-profile" x-data="cdMemberProfile" x-cloak>
    <div class="cd-container">
        <div class="cd-page-header">
            <a href="<?php echo esc_url( $directory_url ); ?>" class="cd-back-link">
                &larr; <?php esc_html_e( 'Back to Directory', 'community-directory' ); ?>
            </a>
        </div>

        <!-- Loading State -->
        <div x-show="loading" class="cd-text-center" style="padding: 3rem 0;">
            <div class="cd-spinner cd-spinner-lg"></div>
            <p class="cd-text-muted"><?php esc_html_e( 'Loading profile...', 'community-directory' ); ?></p>
        </div>

        <!-- Error State -->
        <template x-if="!loading && errorMessage">
            <div class="cd-card">
                <div class="cd-alert cd-alert-error" x-text="errorMessage"></div>
                <div class="cd-text-center">
                    <a href="<?php echo esc_url( $directory_url ); ?>" class="cd-btn cd-btn-primary">
                        <?php esc_html_e( 'Return to Directory', 'community-directory' ); ?>
                    </a>
                </div>
            </div>
        </template>

        <!-- Profile Content -->
        <template x-if="!loading && member">
            <div>
                <!-- Profile Header Card -->
                <div class="cd-card cd-profile-card-header">
                    <div class="cd-profile-top">
                        <!-- Avatar -->
                        <div class="cd-profile-avatar-lg">
                            <template x-if="member.avatar_url">
                                <img
                                    :src="member.avatar_url"
                                    :alt="member.first_name + ' ' + member.last_name"
                                    class="cd-avatar-img-lg"
                                    @error="member.avatar_url = ''"
                                >
                            </template>
                            <template x-if="!member.avatar_url">
                                <div
                                    class="cd-avatar-initials-lg"
                                    :style="'background-color: ' + getAvatarColor(member.first_name + ' ' + member.last_name)"
                                >
                                    <span x-text="getInitials(member.first_name, member.last_name)"></span>
                                </div>
                            </template>
                        </div>

                        <!-- Name & Meta -->
                        <div class="cd-profile-name-block">
                            <h1 class="cd-profile-name" x-text="displayName()"></h1>
                            <p class="cd-text-muted cd-profile-meta" x-show="member.city || member.state">
                                <span x-text="(member.city || '') + (member.city && member.state ? ', ' : '') + (member.state || '')"></span>
                            </p>
                            <p class="cd-text-muted cd-profile-meta" x-show="member.member_since">
                                <?php esc_html_e( 'Member since', 'community-directory' ); ?>
                                <span x-text="member.member_since ? new Date(member.member_since).toLocaleDateString('en-US', {year: 'numeric', month: 'long'}) : ''"></span>
                            </p>
                        </div>
                    </div>

                    <!-- Edit Profile button (own profile only) -->
                    <div x-show="isOwnProfile" class="cd-profile-actions" style="margin-top: 1rem;">
                        <a href="<?php echo esc_url( $profile_url ); ?>" class="cd-btn cd-btn-sm cd-btn-secondary">
                            <?php esc_html_e( 'Edit My Profile', 'community-directory' ); ?>
                        </a>
                    </div>
                </div>

                <!-- Contact Actions -->
                <div class="cd-card" x-show="(member.emails && member.emails.length > 0) || (member.phones && member.phones.length > 0)">
                    <h3 class="cd-section-title"><?php esc_html_e( 'Contact', 'community-directory' ); ?></h3>

                    <!-- Emails -->
                    <template x-if="member.emails && member.emails.length > 0">
                        <div class="cd-contact-group">
                            <template x-for="(email, idx) in member.emails" :key="'email-' + idx">
                                <div class="cd-contact-item">
                                    <span class="cd-contact-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></span>
                                    <div>
                                        <a :href="'mailto:' + email.value" class="cd-contact-link" x-text="email.value"></a>
                                        <small class="cd-text-muted" x-text="email.type || 'personal'" style="text-transform: capitalize;"></small>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Phones -->
                    <template x-if="member.phones && member.phones.length > 0">
                        <div class="cd-contact-group">
                            <template x-for="(phone, idx) in member.phones" :key="'phone-' + idx">
                                <div class="cd-contact-item">
                                    <span class="cd-contact-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                                    <div>
                                        <a :href="'tel:' + phone.value.replace(/\D/g, '')" class="cd-contact-link" x-text="formatPhone(phone.value)"></a>
                                        <small class="cd-text-muted" x-text="phone.type || 'mobile'" style="text-transform: capitalize;"></small>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Address -->
                <div class="cd-card" x-show="member.address_home || member.city">
                    <h3 class="cd-section-title"><?php esc_html_e( 'Address', 'community-directory' ); ?></h3>
                    <div class="cd-detail-block">
                        <p x-show="member.address_home" x-text="member.address_home" style="white-space: pre-line;"></p>
                        <p x-show="member.city || member.state || member.zip">
                            <span x-text="(member.city || '') + (member.city && member.state ? ', ' : '') + (member.state || '') + (member.zip ? ' ' + member.zip : '')"></span>
                        </p>
                    </div>
                </div>

                <!-- Household -->
                <div class="cd-card" x-show="household">
                    <h3 class="cd-section-title"><?php esc_html_e( 'Household', 'community-directory' ); ?></h3>
                    <div class="cd-hh-profile-header">
                        <span class="cd-hh-profile-name" x-text="household ? household.name : ''"></span>
                    </div>
                    <div class="cd-hh-profile-members">
                        <template x-for="hm in (household ? household.members : [])" :key="hm.uuid">
                            <a
                                :href="hm.is_self ? 'javascript:void(0)' : ('<?php echo esc_url( home_url( $base_slug . '/member/' ) ); ?>' + hm.uuid + '/')"
                                class="cd-hh-profile-card"
                                :class="{ 'cd-hh-profile-card-self': hm.is_self }"
                            >
                                <div class="cd-hh-profile-card-avatar">
                                    <template x-if="hm.avatar_url">
                                        <img :src="hm.avatar_url" :alt="hm.first_name" class="cd-avatar-sm-img">
                                    </template>
                                    <template x-if="!hm.avatar_url">
                                        <div
                                            class="cd-avatar-sm"
                                            :style="'background-color: ' + getAvatarColor((hm.first_name || '') + ' ' + (hm.last_name || ''))"
                                        >
                                            <span x-text="getInitials(hm.first_name, hm.last_name)"></span>
                                        </div>
                                    </template>
                                </div>
                                <div class="cd-hh-profile-card-info">
                                    <span class="cd-hh-profile-card-name" x-text="hm.first_name + ' ' + hm.last_name"></span>
                                    <span class="cd-badge cd-badge-role-sm" x-text="hm.role_label"></span>
                                </div>
                            </a>
                        </template>
                    </div>
                </div>

                <!-- Bio -->
                <div class="cd-card" x-show="member.bio">
                    <h3 class="cd-section-title"><?php esc_html_e( 'About', 'community-directory' ); ?></h3>
                    <p x-text="member.bio" style="white-space: pre-line;"></p>
                </div>

                <!-- Ministry Tags -->
                <div class="cd-card" x-show="member.ministry_tags && member.ministry_tags.length > 0">
                    <h3 class="cd-section-title"><?php esc_html_e( 'Ministry Involvement', 'community-directory' ); ?></h3>
                    <div class="cd-tag-list">
                        <template x-for="tag in member.ministry_tags" :key="tag">
                            <span class="cd-tag" x-text="tag"></span>
                        </template>
                    </div>
                </div>

                <!-- Work -->
                <div class="cd-card" x-show="member.occupation || member.employer">
                    <h3 class="cd-section-title"><?php esc_html_e( 'Work', 'community-directory' ); ?></h3>
                    <p x-show="member.occupation">
                        <span class="cd-detail-label"><?php esc_html_e( 'Occupation:', 'community-directory' ); ?></span>
                        <span x-text="member.occupation"></span>
                    </p>
                    <p x-show="member.employer">
                        <span class="cd-detail-label"><?php esc_html_e( 'Employer:', 'community-directory' ); ?></span>
                        <span x-text="member.employer"></span>
                    </p>
                </div>

                <!-- Social Links -->
                <div class="cd-card" x-show="member.social_links && member.social_links.length > 0">
                    <h3 class="cd-section-title"><?php esc_html_e( 'Social', 'community-directory' ); ?></h3>
                    <div class="cd-social-list">
                        <template x-for="(link, idx) in member.social_links" :key="'social-' + idx">
                            <a :href="link.url" target="_blank" rel="noopener noreferrer" class="cd-social-item">
                                <span class="cd-social-platform" x-text="link.platform" style="text-transform: capitalize;"></span>
                                <span class="cd-text-muted" style="font-size: 0.85rem;">&nearr;</span>
                            </a>
                        </template>
                    </div>
                </div>

                <!-- Personal Dates (own profile or admin only) -->
                <div class="cd-card" x-show="isOwnProfile && (member.date_of_birth || member.baptism_date || member.name_day || member.wedding_anniversary)">
                    <h3 class="cd-section-title"><?php esc_html_e( 'Personal Dates', 'community-directory' ); ?></h3>
                    <p x-show="member.date_of_birth">
                        <span class="cd-detail-label"><?php esc_html_e( 'Birthday:', 'community-directory' ); ?></span>
                        <span x-text="member.date_of_birth"></span>
                    </p>
                    <p x-show="member.name_day">
                        <span class="cd-detail-label"><?php esc_html_e( 'Name Day:', 'community-directory' ); ?></span>
                        <span x-text="member.name_day"></span>
                    </p>
                    <p x-show="member.baptism_date">
                        <span class="cd-detail-label"><?php esc_html_e( 'Baptism Date:', 'community-directory' ); ?></span>
                        <span x-text="member.baptism_date"></span>
                    </p>
                    <p x-show="member.wedding_anniversary">
                        <span class="cd-detail-label"><?php esc_html_e( 'Wedding Anniversary:', 'community-directory' ); ?></span>
                        <span x-text="member.wedding_anniversary"></span>
                    </p>
                </div>
            </div>
        </template>
    </div>
</div>

<?php get_footer(); ?>
