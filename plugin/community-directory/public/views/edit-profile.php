<?php
/**
 * Community Directory — Edit Own Profile.
 * Stub for Phase 3.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug     = get_option( 'cd_base_slug', 'community' );
$directory_url = home_url( $base_slug . '/directory/' );
$login_url     = home_url( $base_slug . '/login/' );

if ( ! is_user_logged_in() ) {
    wp_redirect( $login_url );
    exit;
}

get_header();
?>

<div class="cd-wrap cd-edit-profile" x-data="cdEditProfile">
    <div class="cd-container">
        <div class="cd-page-header">
            <a href="<?php echo esc_url( $directory_url ); ?>" class="cd-back-link">
                &larr; <?php esc_html_e( 'Directory', 'community-directory' ); ?>
            </a>
            <h1 class="cd-title"><?php esc_html_e( 'Edit Profile', 'community-directory' ); ?></h1>
            <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="showPrivacyModal = true">
                <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Manage Privacy', 'community-directory' ); ?>
            </button>
        </div>

        <div class="cd-main">
            <div class="cd-card">
                <!-- Loading State -->
                <div x-show="loading" class="cd-loading-state">
                    <div class="cd-spinner"></div>
                    <p><?php esc_html_e( 'Loading profile...', 'community-directory' ); ?></p>
                </div>

                <!-- Error Message -->
                <div x-show="errorMessage" class="cd-alert cd-alert-error" x-text="errorMessage" style="display:none;"></div>

                <!-- Success Message -->
                <div x-show="successMessage" class="cd-alert cd-alert-success" x-text="successMessage" style="display:none;"></div>

                <!-- Edit Form -->
                <form x-show="!loading" @submit.prevent="saveProfile" class="cd-form-responsive">
                    
                    <!-- Avatar Upload -->
                    <div class="cd-profile-header">
                        <div class="cd-avatar-upload">
                            <template x-if="form.avatar_url">
                                <img :src="form.avatar_url" alt="Avatar" class="cd-avatar-preview">
                            </template>
                            <template x-if="!form.avatar_url">
                                <div class="cd-avatar-preview cd-avatar-initials" :style="'background-color: ' + getAvatarColor(form.first_name + ' ' + form.last_name)">
                                    <span x-text="getInitials(form.first_name, form.last_name)"></span>
                                </div>
                            </template>
                            <div class="cd-avatar-actions">
                                <label class="cd-btn cd-btn-sm cd-btn-secondary">
                                    <?php esc_html_e( 'Change Photo', 'community-directory' ); ?>
                                    <input type="file" @change="uploadAvatar" accept="image/*" style="display:none;">
                                </label>
                                <button type="button" class="cd-btn cd-btn-sm cd-btn-danger" @click="deleteAvatar" x-show="form.avatar_url" style="margin-left: 8px;" title="<?php esc_attr_e( 'Remove Photo', 'community-directory' ); ?>">
                                    &#128465; <?php esc_html_e( 'Remove', 'community-directory' ); ?>
                                </button>
                                <span x-show="uploadingAvatar" class="cd-spinner-sm"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Basic Info -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Basic Information', 'community-directory' ); ?></h3>
                        <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'First Name', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.first_name" required>
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Last Name', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.last_name" required>
                            </div>
                        </div>

                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Bio / About Me', 'community-directory' ); ?></label>
                            <textarea class="cd-input" x-model="form.bio" rows="3" placeholder="<?php esc_attr_e( 'Share a little about yourself...', 'community-directory' ); ?>"></textarea>
                        </div>
                    </div>

                    <!-- Section: Contact Info -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Contact Information', 'community-directory' ); ?></h3>

                        <!-- Emails -->
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Email Addresses', 'community-directory' ); ?></label>
                            <template x-for="(email, index) in form.emails" :key="index">
                                <div class="cd-dynamic-row">
                                    <input type="email" class="cd-input" x-model="email.value" placeholder="email@example.com">
                                    <select class="cd-select" x-model="email.type">
                                        <option value="personal">Personal</option>
                                        <option value="work">Work</option>
                                    </select>
                                    <button type="button" class="cd-btn cd-btn-icon" @click="removeEmail(index)" x-show="form.emails.length > 1">&times;</button>
                                </div>
                            </template>
                            <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="addEmail()">
                                + <?php esc_html_e( 'Add Email', 'community-directory' ); ?>
                            </button>
                        </div>

                        <!-- Phones -->
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Phone Numbers', 'community-directory' ); ?></label>
                            <template x-for="(phone, index) in form.phones" :key="index">
                                <div class="cd-dynamic-row">
                                    <input type="tel" class="cd-input" x-model="phone.value" placeholder="(555) 123-4567">
                                    <select class="cd-select" x-model="phone.type">
                                        <option value="mobile">Mobile</option>
                                        <option value="home">Home</option>
                                        <option value="work">Work</option>
                                    </select>
                                    <button type="button" class="cd-btn cd-btn-icon" @click="removePhone(index)" x-show="form.phones.length > 1">&times;</button>
                                </div>
                            </template>
                            <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="addPhone()">
                                + <?php esc_html_e( 'Add Phone', 'community-directory' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Section: Social Media -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Social Media', 'community-directory' ); ?></h3>
                        <template x-for="(link, index) in form.social_links" :key="index">
                            <div class="cd-dynamic-row">
                                <select class="cd-select" x-model="link.platform">
                                    <option value="facebook">Facebook</option>
                                    <option value="twitter">X (Twitter)</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="website">Website</option>
                                </select>
                                <input type="url" class="cd-input" x-model="link.url" placeholder="https://...">
                                <button type="button" class="cd-btn cd-btn-icon" @click="removeSocial(index)">&times;</button>
                            </div>
                        </template>
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="addSocial()">
                            + <?php esc_html_e( 'Add Social Link', 'community-directory' ); ?>
                        </button>
                    </div>

                    <!-- Section: Address -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Address', 'community-directory' ); ?></h3>
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Address Line 1', 'community-directory' ); ?></label>
                            <input type="text" class="cd-input" x-model="form.address_line_1" placeholder="Street address, P.O. box, company name, c/o">
                        </div>
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Address Line 2', 'community-directory' ); ?></label>
                            <input type="text" class="cd-input" x-model="form.address_line_2" placeholder="Apartment, suite, unit, building, floor, etc.">
                        </div>
                         <div class="cd-grid-address">
                            <div class="cd-form-group cd-span-city">
                                <label class="cd-label"><?php esc_html_e( 'City', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.city">
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'State', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.state">
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Zip', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.zip">
                            </div>
                        </div>
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Mailing Address (if different)', 'community-directory' ); ?></label>
                            <textarea class="cd-input" x-model="form.address_mailing" rows="2" placeholder="Leave blank if same as home address"></textarea>
                        </div>
                    </div>

                    <!-- Section: Dates & Details -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Dates & Details', 'community-directory' ); ?></h3>
                         <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Date of Birth', 'community-directory' ); ?></label>
                                <input type="date" class="cd-input" x-model="form.date_of_birth">
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Wedding Anniversary', 'community-directory' ); ?></label>
                                <input type="date" class="cd-input" x-model="form.wedding_anniversary">
                            </div>
                        </div>
                         <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Baptism / Chrismation Date', 'community-directory' ); ?></label>
                                <input type="date" class="cd-input" x-model="form.baptism_date">
                            </div>
                             <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Name Day / Patron Saint', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.name_day">
                            </div>
                        </div>
                    </div>

                     <!-- Section: Emergency Contact -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Emergency Contact', 'community-directory' ); ?> <small class="cd-text-muted" style="font-weight:normal; font-size:0.8em;">(Admin only)</small></h3>
                         <div class="cd-grid-2">
                             <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Contact Name', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.emergency_contact_name">
                            </div>
                             <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Contact Phone', 'community-directory' ); ?></label>
                                <input type="tel" class="cd-input" x-model="form.emergency_contact_phone">
                            </div>
                        </div>
                    </div>

                     <!-- Section: Preferences -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Preferences', 'community-directory' ); ?></h3>
                         <div class="cd-grid-2">
                             <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Preferred Contact Method', 'community-directory' ); ?></label>
                                <select class="cd-select" x-model="form.preferred_contact_method">
                                    <option value="email">Email</option>
                                    <option value="phone">Phone Call</option>
                                    <option value="text">Text Message</option>
                                </select>
                            </div>
                             <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Preferred Language', 'community-directory' ); ?></label>
                                <select class="cd-select" x-model="form.preferred_language">
                                    <option value="en">English</option>
                                    <option value="ar">Arabic</option>
                                    <option value="el">Greek</option>
                                    <option value="es">Spanish</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Work & Personal -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Work & Personal', 'community-directory' ); ?></h3>
                        <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Occupation', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.occupation">
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Employer', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="form.employer">
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="cd-form-actions">
                        <button type="submit" class="cd-btn cd-btn-primary" :disabled="saving">
                            <span x-show="!saving"><?php esc_html_e( 'Save Profile', 'community-directory' ); ?></span>
                            <span x-show="saving"><?php esc_html_e( 'Saving...', 'community-directory' ); ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Privacy Modal -->
    <div x-show="showPrivacyModal" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Manage Privacy', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showPrivacyModal = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <p class="cd-text-muted"><?php esc_html_e( 'Select which information is visible to other members. Checked = Visible.', 'community-directory' ); ?></p>
                
                <div class="cd-privacy-options">
                    <label class="cd-checkbox-label">
                        <input type="checkbox" :checked="form.privacy_settings.email === 'visible'" @change="togglePrivacy('email')">
                        <?php esc_html_e( 'Email Addresses', 'community-directory' ); ?>
                    </label>
                    
                    <label class="cd-checkbox-label">
                        <input type="checkbox" :checked="form.privacy_settings.phone === 'visible'" @change="togglePrivacy('phone')">
                        <?php esc_html_e( 'Phone Numbers', 'community-directory' ); ?>
                    </label>
                    
                    <label class="cd-checkbox-label">
                        <input type="checkbox" :checked="form.privacy_settings.address === 'visible'" @change="togglePrivacy('address')">
                        <?php esc_html_e( 'Home Address', 'community-directory' ); ?>
                    </label>
                    
                    <label class="cd-checkbox-label">
                        <input type="checkbox" :checked="form.privacy_settings.social === 'visible'" @change="togglePrivacy('social')">
                        <?php esc_html_e( 'Social Media Links', 'community-directory' ); ?>
                    </label>

                    <label class="cd-checkbox-label">
                        <input type="checkbox" :checked="form.privacy_settings.date_of_birth === 'visible'" @change="togglePrivacy('date_of_birth')">
                        <?php esc_html_e( 'Date of Birth', 'community-directory' ); ?>
                    </label>

                    <label class="cd-checkbox-label">
                        <input type="checkbox" :checked="form.privacy_settings.wedding_anniversary === 'visible'" @change="togglePrivacy('wedding_anniversary')">
                        <?php esc_html_e( 'Wedding Anniversary', 'community-directory' ); ?>
                    </label>
                </div>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-primary" @click="showPrivacyModal = false"><?php esc_html_e( 'Done', 'community-directory' ); ?></button>
            </div>
        </div>
    </div>

</div>

<?php get_footer(); ?>
