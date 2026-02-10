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
                            <template x-if="form.avatar_url && !showCamera">
                                <img :src="form.avatar_url" alt="Avatar" class="cd-avatar-preview">
                            </template>
                            <template x-if="!form.avatar_url && !showCamera">
                                <div class="cd-avatar-preview cd-avatar-initials" :style="'background-color: ' + getAvatarColor(form.first_name + ' ' + form.last_name)">
                                    <span x-text="getInitials(form.first_name, form.last_name)"></span>
                                </div>
                            </template>

                            <!-- Camera capture view -->
                            <template x-if="showCamera">
                                <div class="cd-camera-wrap">
                                    <video x-ref="cameraVideo" autoplay playsinline class="cd-camera-video"></video>
                                    <canvas x-ref="cameraCanvas" style="display:none;"></canvas>
                                </div>
                            </template>

                            <div class="cd-avatar-actions">
                                <template x-if="!showCamera">
                                    <div class="cd-avatar-btn-row">
                                        <label class="cd-btn cd-btn-sm cd-btn-secondary">
                                            <?php esc_html_e( 'Upload Photo', 'community-directory' ); ?>
                                            <input type="file" @change="uploadAvatar" accept="image/*" style="display:none;">
                                        </label>
                                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="startCamera()">
                                            &#128247; <?php esc_html_e( 'Take Photo', 'community-directory' ); ?>
                                        </button>
                                        <button type="button" class="cd-btn cd-btn-sm cd-btn-danger" @click="deleteAvatar" x-show="form.avatar_url" title="<?php esc_attr_e( 'Remove', 'community-directory' ); ?>">
                                            &#128465;
                                        </button>
                                    </div>
                                </template>
                                <template x-if="showCamera">
                                    <div class="cd-avatar-btn-row">
                                        <button type="button" class="cd-btn cd-btn-sm cd-btn-primary" @click="capturePhoto()">
                                            &#128247; <?php esc_html_e( 'Capture', 'community-directory' ); ?>
                                        </button>
                                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="stopCamera()">
                                            <?php esc_html_e( 'Cancel', 'community-directory' ); ?>
                                        </button>
                                    </div>
                                </template>
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

            <!-- ──── My Household Section ──── -->
            <div class="cd-card cd-household-section" x-show="!loading">
                <div class="cd-form-section">
                    <h3><?php esc_html_e( 'My Household', 'community-directory' ); ?></h3>

                    <!-- Loading household -->
                    <div x-show="householdLoading" class="cd-loading-state">
                        <div class="cd-spinner-sm"></div>
                        <span class="cd-text-muted"><?php esc_html_e( 'Loading household...', 'community-directory' ); ?></span>
                    </div>

                    <!-- Household message -->
                    <div x-show="householdMessage" class="cd-alert cd-alert-success" x-text="householdMessage" style="display:none;"></div>
                    <div x-show="householdError" class="cd-alert cd-alert-error" x-text="householdError" style="display:none;"></div>

                    <!-- No household → Create one -->
                    <template x-if="!householdLoading && !household">
                        <div>
                            <p class="cd-text-muted"><?php esc_html_e( 'You are not part of a household yet.', 'community-directory' ); ?></p>
                            <button type="button" class="cd-btn cd-btn-primary cd-btn-sm" @click="showCreateHousehold = true">
                                + <?php esc_html_e( 'Start a Household', 'community-directory' ); ?>
                            </button>
                        </div>
                    </template>

                    <!-- Has household → Show details -->
                    <template x-if="!householdLoading && household">
                        <div>
                            <!-- Household info bar -->
                            <div class="cd-hh-header">
                                <div class="cd-hh-info">
                                    <strong x-text="household.name"></strong>
                                    <span class="cd-badge cd-badge-role" x-text="household.my_role_label"></span>
                                </div>
                                <template x-if="household.can_manage">
                                    <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="showEditHousehold = true">
                                        <?php esc_html_e( 'Edit', 'community-directory' ); ?>
                                    </button>
                                </template>
                            </div>

                            <template x-if="household.address && (household.address.line_1 || household.address.city)">
                                <p class="cd-hh-address cd-text-muted" x-text="[household.address.line_1, household.address.line_2, [household.address.city, household.address.state, household.address.zip].filter(Boolean).join(', ')].filter(Boolean).join(', ')"></p>
                            </template>

                            <!-- Household members list -->
                            <div class="cd-hh-members">
                                <template x-for="m in household.members" :key="m.member_id">
                                    <div class="cd-hh-member-card">
                                        <div class="cd-hh-member-info">
                                            <div class="cd-avatar-sm" :style="'background-color: ' + getAvatarColor((m.first_name || '') + ' ' + (m.last_name || ''))">
                                                <span x-text="getInitials(m.first_name || '', m.last_name || '')"></span>
                                            </div>
                                            <div>
                                                <span class="cd-hh-member-name" x-text="(m.first_name || '') + ' ' + (m.last_name || '')"></span>
                                                <span class="cd-badge cd-badge-role-sm" x-text="m.role_label"></span>
                                                <template x-if="m.has_login">
                                                    <span class="cd-badge cd-badge-login" title="<?php esc_attr_e( 'Has own login', 'community-directory' ); ?>">&#10003;</span>
                                                </template>
                                                <template x-if="!m.has_login && m.primary_email">
                                                    <span class="cd-badge cd-badge-invited" title="<?php esc_attr_e( 'Invited', 'community-directory' ); ?>"><?php esc_html_e( 'Invited', 'community-directory' ); ?></span>
                                                </template>
                                            </div>
                                        </div>
                                        <template x-if="household.can_manage && m.role !== 'head'">
                                            <button type="button" class="cd-btn cd-btn-icon cd-btn-danger-icon" @click="removeHouseholdMember(m.member_id, m.first_name)" title="<?php esc_attr_e( 'Remove', 'community-directory' ); ?>">&times;</button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Add member button (head/spouse only) -->
                            <template x-if="household.can_manage">
                                <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="showAddMember = true" style="margin-top: 12px;">
                                    + <?php esc_html_e( 'Add Household Member', 'community-directory' ); ?>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- ──── Create Household Modal ──── -->
    <div x-show="showCreateHousehold" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Start a Household', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showCreateHousehold = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'Household Name', 'community-directory' ); ?></label>
                    <input type="text" class="cd-input" x-model="newHouseholdName" placeholder="<?php esc_attr_e( 'e.g. The Smith Family', 'community-directory' ); ?>">
                </div>

                <label class="cd-label" style="margin-top: 12px;"><?php esc_html_e( 'Home Address (optional)', 'community-directory' ); ?></label>
                <label class="cd-checkbox" style="margin-bottom: 8px;">
                    <input type="checkbox" x-model="hhInheritAddress">
                    <span><?php esc_html_e( 'Use the address from my profile', 'community-directory' ); ?></span>
                </label>

                <template x-if="!hhInheritAddress">
                    <div>
                        <div class="cd-form-group">
                            <input type="text" class="cd-input" x-model="newHouseholdAddr.line_1" placeholder="<?php esc_attr_e( 'Address Line 1', 'community-directory' ); ?>">
                        </div>
                        <div class="cd-form-group">
                            <input type="text" class="cd-input" x-model="newHouseholdAddr.line_2" placeholder="<?php esc_attr_e( 'Address Line 2 (optional)', 'community-directory' ); ?>">
                        </div>
                        <div class="cd-grid-address">
                            <div class="cd-form-group">
                                <input type="text" class="cd-input" x-model="newHouseholdAddr.city" placeholder="<?php esc_attr_e( 'City', 'community-directory' ); ?>">
                            </div>
                            <div class="cd-form-group">
                                <input type="text" class="cd-input" x-model="newHouseholdAddr.state" placeholder="<?php esc_attr_e( 'State', 'community-directory' ); ?>">
                            </div>
                            <div class="cd-form-group">
                                <input type="text" class="cd-input" x-model="newHouseholdAddr.zip" placeholder="<?php esc_attr_e( 'ZIP', 'community-directory' ); ?>">
                            </div>
                        </div>
                    </div>
                </template>
                <template x-if="hhInheritAddress">
                    <p class="cd-text-muted" style="font-size: 0.9em;" x-text="[form.address_line_1, form.address_line_2, [form.city, form.state, form.zip].filter(Boolean).join(', ')].filter(Boolean).join(', ') || 'No address on your profile yet.'"></p>
                </template>

                <p class="cd-text-muted" style="font-size: 0.85em; margin-top: 8px;"><?php esc_html_e( 'You will be set as the primary membership holder.', 'community-directory' ); ?></p>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showCreateHousehold = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-primary" @click="createHousehold" :disabled="householdSaving">
                    <span x-show="!householdSaving"><?php esc_html_e( 'Create Household', 'community-directory' ); ?></span>
                    <span x-show="householdSaving"><?php esc_html_e( 'Creating...', 'community-directory' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ──── Edit Household Modal ──── -->
    <div x-show="showEditHousehold" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Edit Household', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showEditHousehold = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'Household Name', 'community-directory' ); ?></label>
                    <input type="text" class="cd-input" x-model="editHouseholdName">
                </div>
                <label class="cd-label" style="margin-top: 12px;"><?php esc_html_e( 'Home Address', 'community-directory' ); ?></label>
                <div class="cd-form-group">
                    <input type="text" class="cd-input" x-model="editHouseholdAddr.line_1" placeholder="<?php esc_attr_e( 'Address Line 1', 'community-directory' ); ?>">
                </div>
                <div class="cd-form-group">
                    <input type="text" class="cd-input" x-model="editHouseholdAddr.line_2" placeholder="<?php esc_attr_e( 'Address Line 2 (optional)', 'community-directory' ); ?>">
                </div>
                <div class="cd-grid-address">
                    <div class="cd-form-group">
                        <input type="text" class="cd-input" x-model="editHouseholdAddr.city" placeholder="<?php esc_attr_e( 'City', 'community-directory' ); ?>">
                    </div>
                    <div class="cd-form-group">
                        <input type="text" class="cd-input" x-model="editHouseholdAddr.state" placeholder="<?php esc_attr_e( 'State', 'community-directory' ); ?>">
                    </div>
                    <div class="cd-form-group">
                        <input type="text" class="cd-input" x-model="editHouseholdAddr.zip" placeholder="<?php esc_attr_e( 'ZIP', 'community-directory' ); ?>">
                    </div>
                </div>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showEditHousehold = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-primary" @click="updateHousehold" :disabled="householdSaving">
                    <span x-show="!householdSaving"><?php esc_html_e( 'Save', 'community-directory' ); ?></span>
                    <span x-show="householdSaving"><?php esc_html_e( 'Saving...', 'community-directory' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ──── Add Household Member Modal ──── -->
    <div x-show="showAddMember" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Add Household Member', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showAddMember = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <div class="cd-grid-2">
                    <div class="cd-form-group">
                        <label class="cd-label"><?php esc_html_e( 'First Name', 'community-directory' ); ?> *</label>
                        <input type="text" class="cd-input" x-model="addMemberForm.first_name" required>
                    </div>
                    <div class="cd-form-group">
                        <label class="cd-label"><?php esc_html_e( 'Last Name', 'community-directory' ); ?> *</label>
                        <input type="text" class="cd-input" x-model="addMemberForm.last_name" required>
                    </div>
                </div>
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'Email Address (optional)', 'community-directory' ); ?></label>
                    <input type="email" class="cd-input" x-model="addMemberForm.email" placeholder="<?php esc_attr_e( 'If provided, they will receive an invite to create their own login', 'community-directory' ); ?>">
                </div>
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'Relationship', 'community-directory' ); ?></label>
                    <select class="cd-select" x-model="addMemberForm.role">
                        <option value="spouse"><?php esc_html_e( 'Spouse', 'community-directory' ); ?></option>
                        <option value="child"><?php esc_html_e( 'Child', 'community-directory' ); ?></option>
                        <option value="other"><?php esc_html_e( 'Other', 'community-directory' ); ?></option>
                    </select>
                </div>
                <div class="cd-hh-add-info cd-text-muted" style="font-size: 0.85em; margin-top: 8px;">
                    <template x-if="addMemberForm.email">
                        <p><?php esc_html_e( 'An invitation email will be sent. They can create their own login and manage their profile.', 'community-directory' ); ?></p>
                    </template>
                    <template x-if="!addMemberForm.email">
                        <p><?php esc_html_e( 'Without an email, you will manage this person\'s information in the directory.', 'community-directory' ); ?></p>
                    </template>
                </div>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showAddMember = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-primary" @click="addHouseholdMember" :disabled="householdSaving">
                    <span x-show="!householdSaving"><?php esc_html_e( 'Add Member', 'community-directory' ); ?></span>
                    <span x-show="householdSaving"><?php esc_html_e( 'Adding...', 'community-directory' ); ?></span>
                </button>
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
