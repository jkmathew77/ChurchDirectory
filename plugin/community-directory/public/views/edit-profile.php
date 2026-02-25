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
                <div x-show="errorMessage" x-cloak class="cd-alert cd-alert-error" x-text="errorMessage"></div>

                <!-- Success Message -->
                <div x-show="successMessage" x-cloak class="cd-alert cd-alert-success" x-text="successMessage"></div>

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

                            <!-- Camera capture view (use x-show so refs persist for capture) -->
                            <div class="cd-camera-wrap" x-show="showCamera" x-cloak>
                                <video x-ref="cameraVideo" autoplay playsinline muted class="cd-camera-video"></video>
                            </div>
                            <canvas x-ref="cameraCanvas" style="display:none;"></canvas>

                            <div class="cd-avatar-actions">
                                <div class="cd-avatar-btn-row" x-show="!showCamera">
                                    <label class="cd-btn cd-btn-sm cd-btn-secondary">
                                        <?php esc_html_e( 'Upload Photo', 'community-directory' ); ?>
                                        <input type="file" @change="uploadAvatar" accept="image/*" style="display:none;">
                                    </label>
                                    <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="startCamera()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                        <?php esc_html_e( 'Take Photo', 'community-directory' ); ?>
                                    </button>
                                    <button type="button" class="cd-btn cd-btn-sm cd-btn-danger" @click="deleteAvatar" x-show="form.avatar_url" title="<?php esc_attr_e( 'Remove', 'community-directory' ); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </div>
                                <div class="cd-avatar-btn-row" x-show="showCamera" x-cloak>
                                    <button type="button" class="cd-btn cd-btn-sm cd-btn-primary" @click="capturePhoto()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                        <?php esc_html_e( 'Capture', 'community-directory' ); ?>
                                    </button>
                                    <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="stopCamera()">
                                        <?php esc_html_e( 'Cancel', 'community-directory' ); ?>
                                    </button>
                                </div>
                                <span x-show="uploadingAvatar" class="cd-spinner-sm"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Basic Info -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Basic Information', 'community-directory' ); ?></h3>
                        <div class="cd-form-group" style="max-width: 160px;">
                            <label class="cd-label"><?php esc_html_e( 'Salutation', 'community-directory' ); ?></label>
                            <select class="cd-select" x-model="form.salutation">
                                <option value=""><?php esc_html_e( '— None —', 'community-directory' ); ?></option>
                                <option value="Mr">Mr</option>
                                <option value="Mrs">Mrs</option>
                                <option value="Ms">Ms</option>
                                <option value="Dr">Dr</option>
                                <option value="Fr.">Fr.</option>
                                <option value="Dn.">Dn.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="Rev.">Rev.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                        </div>
                        <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'First Name', 'community-directory' ); ?> *</label>
                                <input type="text" class="cd-input" x-model="form.first_name" required>
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Last Name', 'community-directory' ); ?> *</label>
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
                            <label class="cd-label">
                                <?php esc_html_e( 'Email Addresses', 'community-directory' ); ?>
                                <template x-if="householdRole === 'head' || householdRole === 'spouse'"> <span class="cd-required"> *</span></template>
                            </label>
                            <template x-if="householdRole === 'head' || householdRole === 'spouse'">
                                <p class="cd-field-hint"><?php esc_html_e( 'Required for primary members and spouses.', 'community-directory' ); ?></p>
                            </template>
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
                            <label class="cd-label">
                                <?php esc_html_e( 'Phone Numbers', 'community-directory' ); ?>
                                <template x-if="householdRole === 'head' || householdRole === 'spouse'"> <span class="cd-required"> *</span></template>
                            </label>
                            <template x-if="householdRole === 'head' || householdRole === 'spouse'">
                                <p class="cd-field-hint"><?php esc_html_e( 'Required for primary members and spouses.', 'community-directory' ); ?></p>
                            </template>
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

                        <!-- Household address inheritance (non-head members with a household) -->
                        <template x-if="household && householdRole !== 'head'">
                            <div>
                                <label class="cd-address-inherit">
                                    <input type="checkbox" :checked="!hasDifferentAddress" @change="hasDifferentAddress = !$event.target.checked">
                                    <span><?php esc_html_e( 'Same as household address', 'community-directory' ); ?></span>
                                </label>
                                <template x-if="!hasDifferentAddress && household.address && (household.address.line_1 || household.address.city)">
                                    <div class="cd-address-inherited-display">
                                        <span x-text="[household.address.line_1, household.address.line_2, [household.address.city, household.address.state, household.address.zip].filter(Boolean).join(', ')].filter(Boolean).join(', ')"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Show address fields if: no household, OR head of household, OR has different address -->
                        <div x-show="!household || householdRole === 'head' || hasDifferentAddress">
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
                    </div>

                    <!-- Section: Dates & Details -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Dates & Details', 'community-directory' ); ?></h3>
                         <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Date of Birth', 'community-directory' ); ?></label>
                                <input type="date" class="cd-input" x-model="form.date_of_birth">
                            </div>
                            <div class="cd-form-group" x-show="householdRole !== 'child'">
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

                    <!-- Section: Education & Youth (for children / students) -->
                    <template x-if="householdRole === 'child'">
                        <div class="cd-form-section">
                            <h3><?php esc_html_e( 'Education & Youth', 'community-directory' ); ?></h3>
                            <div class="cd-grid-2">
                                <div class="cd-form-group">
                                    <label class="cd-label"><?php esc_html_e( 'School Type', 'community-directory' ); ?></label>
                                    <select class="cd-select" x-model="form.school_type">
                                        <option value=""><?php esc_html_e( '— Not in school —', 'community-directory' ); ?></option>
                                        <option value="high_school"><?php esc_html_e( 'High School or Below', 'community-directory' ); ?></option>
                                        <option value="college"><?php esc_html_e( 'College', 'community-directory' ); ?></option>
                                        <option value="university"><?php esc_html_e( 'University', 'community-directory' ); ?></option>
                                    </select>
                                </div>
                                <div class="cd-form-group" x-show="form.school_type">
                                    <label class="cd-label"><?php esc_html_e( 'School Name', 'community-directory' ); ?></label>
                                    <input type="text" class="cd-input" x-model="form.school_name" placeholder="<?php esc_attr_e( 'e.g. Lincoln High School', 'community-directory' ); ?>">
                                </div>
                            </div>

                            <!-- Sunday School Teacher — only for high school or below -->
                            <div class="cd-form-group" x-show="form.school_type === 'high_school' || form.school_type === ''">
                                <label class="cd-label"><?php esc_html_e( 'Sunday School Teacher', 'community-directory' ); ?></label>
                                <div class="cd-teacher-search">
                                    <input type="text" class="cd-input" x-model="teacherSearchQuery" @input.debounce.400ms="searchTeachers()" placeholder="<?php esc_attr_e( 'Search by name...', 'community-directory' ); ?>">
                                    <template x-if="form.sunday_school_teacher_name && !teacherSearchQuery">
                                        <div class="cd-teacher-selected">
                                            <span x-text="form.sunday_school_teacher_name"></span>
                                            <button type="button" class="cd-btn cd-btn-icon" @click="form.sunday_school_teacher_id = null; form.sunday_school_teacher_name = ''" title="<?php esc_attr_e( 'Clear', 'community-directory' ); ?>">&times;</button>
                                        </div>
                                    </template>
                                    <template x-if="teacherResults.length > 0">
                                        <ul class="cd-search-dropdown">
                                            <template x-for="t in teacherResults" :key="t.member_id">
                                                <li @click="selectTeacher(t)" class="cd-search-dropdown-item" x-text="t.first_name + ' ' + t.last_name"></li>
                                            </template>
                                        </ul>
                                    </template>
                                </div>
                            </div>

                            <!-- Graduation date (month + year) — all school types -->
                            <div class="cd-form-group" x-show="form.school_type">
                                <label class="cd-label"><?php esc_html_e( 'Expected Graduation', 'community-directory' ); ?></label>
                                <input type="month" class="cd-input" x-model="form.graduation_date" placeholder="YYYY-MM">
                            </div>

                            <!-- Major/Minor — only for college / university -->
                            <div class="cd-grid-2" x-show="form.school_type === 'college' || form.school_type === 'university'">
                                <div class="cd-form-group">
                                    <label class="cd-label"><?php esc_html_e( 'Major / Field of Study', 'community-directory' ); ?></label>
                                    <input type="text" class="cd-input" x-model="form.major_studies" placeholder="<?php esc_attr_e( 'e.g. Computer Science', 'community-directory' ); ?>">
                                </div>
                                <div class="cd-form-group">
                                    <label class="cd-label"><?php esc_html_e( 'Minor (optional)', 'community-directory' ); ?></label>
                                    <input type="text" class="cd-input" x-model="form.minor_studies">
                                </div>
                            </div>
                        </div>
                    </template>

                     <!-- Section: Emergency Contact -->
                    <div class="cd-form-section">
                        <h3><?php esc_html_e( 'Emergency Contact', 'community-directory' ); ?> <small class="cd-text-muted" style="font-weight:normal; font-size:0.8em;">(Admin only)</small></h3>
                         <div class="cd-grid-2">
                             <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Contact Name', 'community-directory' ); ?></label>
                                <!-- Smart lookup: search directory members -->
                                <div class="cd-teacher-search">
                                    <input type="text" class="cd-input" x-model="ecSearchQuery"
                                        @input.debounce.400ms="searchEmergencyContact()"
                                        @focus="if(form.emergency_contact_member_id && !ecSearchQuery) ecSearchQuery = form.emergency_contact_name"
                                        :placeholder="form.emergency_contact_member_id ? '' : '<?php echo esc_js( __( 'Search directory or type name...', 'community-directory' ) ); ?>'">
                                    <template x-if="form.emergency_contact_name && !ecSearchQuery">
                                        <div class="cd-teacher-selected">
                                            <span x-text="form.emergency_contact_name"></span>
                                            <template x-if="form.emergency_contact_member_id">
                                                <small class="cd-text-muted" style="margin-left: 4px;">(<?php esc_html_e( 'linked', 'community-directory' ); ?>)</small>
                                            </template>
                                            <button type="button" class="cd-btn cd-btn-icon" @click="clearEmergencyContact()" title="<?php esc_attr_e( 'Clear', 'community-directory' ); ?>">&times;</button>
                                        </div>
                                    </template>
                                    <template x-if="ecResults.length > 0">
                                        <ul class="cd-search-dropdown">
                                            <template x-for="ec in ecResults" :key="ec.member_id">
                                                <li @click="selectEmergencyContact(ec)" class="cd-search-dropdown-item">
                                                    <span x-text="ec.first_name + ' ' + ec.last_name"></span>
                                                    <small class="cd-text-muted" x-show="ec.phone" x-text="' — ' + ec.phone" style="margin-left: 4px;"></small>
                                                </li>
                                            </template>
                                            <li class="cd-search-dropdown-item cd-text-muted" @click="setManualEmergencyContact()">
                                                <?php esc_html_e( 'Use as typed (not linked)', 'community-directory' ); ?>
                                            </li>
                                        </ul>
                                    </template>
                                </div>
                            </div>
                             <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Contact Phone', 'community-directory' ); ?></label>
                                <input type="tel" class="cd-input" x-model="form.emergency_contact_phone"
                                    :readonly="!!form.emergency_contact_member_id"
                                    :class="{ 'cd-input-readonly': !!form.emergency_contact_member_id }">
                                <small x-show="form.emergency_contact_member_id" class="cd-text-muted">
                                    <?php esc_html_e( 'Auto-synced from linked member', 'community-directory' ); ?>
                                </small>
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

                    <!-- Section: Work & Personal (hidden for children) -->
                    <div class="cd-form-section" x-show="householdRole !== 'child'">
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

                            <!-- Family Photos Gallery (head/spouse can manage, up to 10) -->
                            <div class="cd-hh-photo-section" style="margin: 12px 0;">
                                <template x-if="household.photos && household.photos.length > 0">
                                    <div class="cd-hh-photo-grid">
                                        <template x-for="(photo, idx) in household.photos" :key="'hp-'+idx">
                                            <div class="cd-hh-photo-thumb">
                                                <img :src="photo.url" alt="<?php esc_attr_e( 'Family Photo', 'community-directory' ); ?>" class="cd-hh-photo-thumb-img"
                                                     :style="'object-position: ' + (photo.fx ?? 50) + '% ' + (photo.fy ?? 50) + '%; transform: scale(' + (photo.zoom ?? 1) + '); transform-origin: ' + (photo.fx ?? 50) + '% ' + (photo.fy ?? 50) + '%'">
                                                <template x-if="household.can_manage">
                                                    <div class="cd-hh-photo-thumb-actions">
                                                        <button type="button" class="cd-hh-photo-adjust" @click.prevent="openPhotoEditor(photo)" title="<?php esc_attr_e( 'Adjust Position', 'community-directory' ); ?>">&#9998;</button>
                                                        <button type="button" class="cd-hh-photo-delete" @click.prevent="deleteHouseholdPhoto(photo.url)" title="<?php esc_attr_e( 'Remove', 'community-directory' ); ?>">&#10005;</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="household.can_manage">
                                    <div class="cd-hh-photo-actions" style="margin-top: 8px;">
                                        <template x-if="!household.photos || household.photos.length < 10">
                                            <label class="cd-btn cd-btn-sm cd-btn-secondary">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                                <?php esc_html_e( 'Add Family Photo', 'community-directory' ); ?>
                                                <input type="file" @change="uploadHouseholdPhoto" accept="image/*" style="display:none;">
                                            </label>
                                        </template>
                                        <span class="cd-text-muted" style="font-size: 0.8em;" x-text="(household.photos ? household.photos.length : 0) + ' / 10'"></span>
                                        <span x-show="uploadingHouseholdPhoto" class="cd-spinner-sm"></span>
                                    </div>
                                </template>
                            </div>

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
                                        <div class="cd-hh-member-actions">
                                            <template x-if="household.can_manage && !m.has_login && m.role !== 'head'">
                                                <button type="button" class="cd-btn cd-btn-icon cd-btn-secondary-icon" @click="openEditMember(m.member_id)" title="<?php esc_attr_e( 'Edit Details', 'community-directory' ); ?>">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                            </template>
                                            <template x-if="household.can_manage && m.role !== 'head'">
                                                <button type="button" class="cd-btn cd-btn-icon cd-btn-danger-icon" @click="removeHouseholdMember(m.member_id, m.first_name)" title="<?php esc_attr_e( 'Remove', 'community-directory' ); ?>">&times;</button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Add member button (head/spouse only) -->
                            <template x-if="household.can_manage">
                                <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="showAddMember = true" style="margin-top: 12px;">
                                    + <?php esc_html_e( 'Add Household Member', 'community-directory' ); ?>
                                </button>
                            </template>

                            <!-- Lifecycle action buttons (context-sensitive by role) -->
                            <div class="cd-hh-lifecycle-actions" style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #eee;">
                                <!-- Head sees: Transfer Primary + Request Merge -->
                                <template x-if="household.my_role === 'head'">
                                    <div class="cd-btn-row">
                                        <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="showTransferHead = true">
                                            <?php esc_html_e( 'Transfer Primary', 'community-directory' ); ?>
                                        </button>
                                        <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="showMergeRequest = true">
                                            <?php esc_html_e( 'Request Merge', 'community-directory' ); ?>
                                        </button>
                                    </div>
                                </template>

                                <!-- Spouse/Other sees: Spin-Off + Leave -->
                                <template x-if="household.my_role === 'spouse' || household.my_role === 'other'">
                                    <div class="cd-btn-row">
                                        <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="showSpinOff = true">
                                            <?php esc_html_e( 'Start My Own Household', 'community-directory' ); ?>
                                        </button>
                                        <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm cd-btn-danger-outline" @click="showLeaveConfirm = true">
                                            <?php esc_html_e( 'Leave Household', 'community-directory' ); ?>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- ──── Danger Zone ──── -->
        <div class="cd-card cd-danger-zone" x-show="!loading" style="border: 1px solid #f5c6cb; margin-top: 24px;">
            <div class="cd-form-section">
                <h3 style="color: #cc1818;"><?php esc_html_e( 'Danger Zone', 'community-directory' ); ?></h3>
                <p class="cd-text-muted"><?php esc_html_e( 'Request permanent removal of your account from the church directory.', 'community-directory' ); ?></p>
                <button type="button" class="cd-btn cd-btn-danger cd-btn-sm" @click="showDeletionRequest = true">
                    <?php esc_html_e( 'Request Account Deletion', 'community-directory' ); ?>
                </button>
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

    <!-- ──── Edit Managed Member Modal ──── -->
    <div x-show="showEditMember" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Edit Member Details', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showEditMember = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <template x-if="editMemberLoading">
                    <div style="text-align: center; padding: 20px;"><span class="cd-spinner-sm"></span> <?php esc_html_e( 'Loading...', 'community-directory' ); ?></div>
                </template>
                <template x-if="!editMemberLoading">
                    <div>
                        <!-- Member Photo -->
                        <div class="cd-form-group" style="text-align: center; margin-bottom: 16px;">
                            <template x-if="editMemberForm.avatar_url">
                                <img :src="editMemberForm.avatar_url" alt="Photo" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 8px;">
                            </template>
                            <template x-if="!editMemberForm.avatar_url">
                                <div class="cd-avatar-preview cd-avatar-initials" style="width: 100px; height: 100px; margin: 0 auto 8px; font-size: 2rem;" :style="'background-color: ' + getAvatarColor(editMemberForm.first_name + ' ' + editMemberForm.last_name)">
                                    <span x-text="getInitials(editMemberForm.first_name, editMemberForm.last_name)"></span>
                                </div>
                            </template>
                            <div>
                                <label class="cd-btn cd-btn-sm cd-btn-secondary" :class="{ 'cd-btn-disabled': editMemberPhotoUploading }" style="cursor: pointer;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                    <?php esc_html_e( 'Upload Photo', 'community-directory' ); ?>
                                    <input type="file" @change="uploadEditMemberPhoto" accept="image/*" style="display:none;" :disabled="editMemberPhotoUploading">
                                </label>
                                <span x-show="editMemberPhotoUploading" class="cd-spinner-sm" style="margin-left: 6px;"></span>
                            </div>
                        </div>

                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Salutation', 'community-directory' ); ?></label>
                            <select class="cd-select" x-model="editMemberForm.salutation">
                                <option value=""><?php esc_html_e( 'None', 'community-directory' ); ?></option>
                                <option value="Mr."><?php esc_html_e( 'Mr.', 'community-directory' ); ?></option>
                                <option value="Mrs."><?php esc_html_e( 'Mrs.', 'community-directory' ); ?></option>
                                <option value="Ms."><?php esc_html_e( 'Ms.', 'community-directory' ); ?></option>
                                <option value="Dr."><?php esc_html_e( 'Dr.', 'community-directory' ); ?></option>
                                <option value="Rev."><?php esc_html_e( 'Rev.', 'community-directory' ); ?></option>
                                <option value="Fr."><?php esc_html_e( 'Fr.', 'community-directory' ); ?></option>
                            </select>
                        </div>
                        <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'First Name', 'community-directory' ); ?> *</label>
                                <input type="text" class="cd-input" x-model="editMemberForm.first_name" required>
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Last Name', 'community-directory' ); ?> *</label>
                                <input type="text" class="cd-input" x-model="editMemberForm.last_name" required>
                            </div>
                        </div>
                        <div class="cd-form-group">
                            <label class="cd-label"><?php esc_html_e( 'Email Address', 'community-directory' ); ?></label>
                            <input type="email" class="cd-input" x-model="editMemberForm.email" placeholder="<?php esc_attr_e( 'Optional — adds to their directory listing', 'community-directory' ); ?>">
                        </div>
                        <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Phone Number', 'community-directory' ); ?></label>
                                <input type="tel" class="cd-input" x-model="editMemberForm.phone" placeholder="<?php esc_attr_e( '555-123-4567', 'community-directory' ); ?>">
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Date of Birth', 'community-directory' ); ?></label>
                                <input type="date" class="cd-input" x-model="editMemberForm.date_of_birth">
                            </div>
                        </div>
                        <div class="cd-grid-2">
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Occupation', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="editMemberForm.occupation">
                            </div>
                            <div class="cd-form-group">
                                <label class="cd-label"><?php esc_html_e( 'Employer', 'community-directory' ); ?></label>
                                <input type="text" class="cd-input" x-model="editMemberForm.employer">
                            </div>
                        </div>
                        <p class="cd-text-muted" style="font-size: 0.85em; margin-top: 8px;">
                            <?php esc_html_e( 'You are managing this member\'s details because they do not have their own login.', 'community-directory' ); ?>
                        </p>
                    </div>
                </template>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showEditMember = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-primary" @click="saveEditMember" :disabled="editMemberSaving || editMemberLoading">
                    <span x-show="!editMemberSaving"><?php esc_html_e( 'Save Changes', 'community-directory' ); ?></span>
                    <span x-show="editMemberSaving"><?php esc_html_e( 'Saving...', 'community-directory' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ──── Leave Household Confirm ──── -->
    <div x-show="showLeaveConfirm" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Leave Household', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showLeaveConfirm = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <p><?php esc_html_e( 'Are you sure you want to leave this household? You can join or create a new one later.', 'community-directory' ); ?></p>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showLeaveConfirm = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-danger" @click="leaveHousehold" :disabled="householdSaving">
                    <span x-show="!householdSaving"><?php esc_html_e( 'Leave Household', 'community-directory' ); ?></span>
                    <span x-show="householdSaving"><?php esc_html_e( 'Leaving...', 'community-directory' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ──── Transfer Head Modal ──── -->
    <div x-show="showTransferHead" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Transfer Primary Membership', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showTransferHead = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <p class="cd-text-muted"><?php esc_html_e( 'Select which household member should become the new primary membership holder. Your role will change to "Other".', 'community-directory' ); ?></p>
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'New Primary Member', 'community-directory' ); ?></label>
                    <select class="cd-select" x-model="transferTargetId">
                        <option value=""><?php esc_html_e( '— Select —', 'community-directory' ); ?></option>
                        <template x-for="m in (household ? household.members : []).filter(m => m.role !== 'head' && m.role !== 'child')" :key="m.member_id">
                            <option :value="m.member_id" x-text="(m.first_name || '') + ' ' + (m.last_name || '') + ' (' + m.role_label + ')'"></option>
                        </template>
                    </select>
                </div>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showTransferHead = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-primary" @click="transferHead" :disabled="householdSaving || !transferTargetId">
                    <span x-show="!householdSaving"><?php esc_html_e( 'Transfer', 'community-directory' ); ?></span>
                    <span x-show="householdSaving"><?php esc_html_e( 'Transferring...', 'community-directory' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ──── Spin-Off Modal ──── -->
    <div x-show="showSpinOff" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Start My Own Household', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showSpinOff = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <p class="cd-text-muted"><?php esc_html_e( 'You will leave the current household and start a new one as the primary member.', 'community-directory' ); ?></p>
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'New Household Name', 'community-directory' ); ?> *</label>
                    <input type="text" class="cd-input" x-model="spinOffForm.name" placeholder="<?php esc_attr_e( 'e.g. The Smith Family', 'community-directory' ); ?>">
                </div>
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'Address (optional)', 'community-directory' ); ?></label>
                    <input type="text" class="cd-input" x-model="spinOffForm.line_1" placeholder="<?php esc_attr_e( 'Street address', 'community-directory' ); ?>">
                </div>
                <div class="cd-grid-address">
                    <div class="cd-form-group">
                        <input type="text" class="cd-input" x-model="spinOffForm.city" placeholder="<?php esc_attr_e( 'City', 'community-directory' ); ?>">
                    </div>
                    <div class="cd-form-group">
                        <input type="text" class="cd-input" x-model="spinOffForm.state" placeholder="<?php esc_attr_e( 'State', 'community-directory' ); ?>">
                    </div>
                    <div class="cd-form-group">
                        <input type="text" class="cd-input" x-model="spinOffForm.zip" placeholder="<?php esc_attr_e( 'ZIP', 'community-directory' ); ?>">
                    </div>
                </div>

                <!-- Children to bring along -->
                <template x-if="household && household.members.filter(m => m.role === 'child').length > 0">
                    <div class="cd-form-group" style="margin-top: 12px;">
                        <label class="cd-label"><?php esc_html_e( 'Bring children with you?', 'community-directory' ); ?></label>
                        <template x-for="child in household.members.filter(m => m.role === 'child')" :key="child.member_id">
                            <label class="cd-checkbox" style="display: block; margin-bottom: 4px;">
                                <input type="checkbox" :value="child.member_id" x-model="spinOffForm.bring_children">
                                <span x-text="(child.first_name || '') + ' ' + (child.last_name || '')"></span>
                            </label>
                        </template>
                    </div>
                </template>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showSpinOff = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-primary" @click="submitSpinOff" :disabled="householdSaving || !spinOffForm.name.trim()">
                    <span x-show="!householdSaving"><?php esc_html_e( 'Create & Leave', 'community-directory' ); ?></span>
                    <span x-show="householdSaving"><?php esc_html_e( 'Creating...', 'community-directory' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ──── Merge Request Modal ──── -->
    <div x-show="showMergeRequest" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Request Household Merge', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showMergeRequest = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <p class="cd-text-muted"><?php esc_html_e( 'Search for the household you want to merge into. All members from your household will be moved to the target. An admin must approve this request.', 'community-directory' ); ?></p>
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'Search Households', 'community-directory' ); ?></label>
                    <input type="text" class="cd-input" x-model="mergeSearchQuery" @input.debounce.400ms="searchHouseholds()" placeholder="<?php esc_attr_e( 'Type at least 2 characters...', 'community-directory' ); ?>">
                    <span x-show="mergeSearching" class="cd-spinner-sm" style="margin-left: 8px;"></span>
                </div>

                <template x-if="mergeSearchResults.length > 0">
                    <div class="cd-form-group">
                        <label class="cd-label"><?php esc_html_e( 'Select Target Household', 'community-directory' ); ?></label>
                        <template x-for="h in mergeSearchResults" :key="h.id">
                            <label class="cd-radio-label" style="display: block; margin-bottom: 6px; cursor: pointer;">
                                <input type="radio" name="merge_target" :value="h.id" x-model="mergeTargetHouseholdId">
                                <span x-text="h.name"></span>
                            </label>
                        </template>
                    </div>
                </template>

                <template x-if="mergeSearchQuery.length >= 2 && mergeSearchResults.length === 0 && !mergeSearching">
                    <p class="cd-text-muted"><?php esc_html_e( 'No matching households found.', 'community-directory' ); ?></p>
                </template>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showMergeRequest = false; mergeSearchQuery = ''; mergeSearchResults = []; mergeTargetHouseholdId = '';"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-primary" @click="submitMergeRequest" :disabled="householdSaving || !mergeTargetHouseholdId">
                    <span x-show="!householdSaving"><?php esc_html_e( 'Submit Request', 'community-directory' ); ?></span>
                    <span x-show="householdSaving"><?php esc_html_e( 'Submitting...', 'community-directory' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ──── Deletion Request Modal ──── -->
    <div x-show="showDeletionRequest" class="cd-modal-overlay" x-cloak x-transition>
        <div class="cd-modal">
            <div class="cd-modal-header">
                <h3 style="color: #cc1818;"><?php esc_html_e( 'Request Account Deletion', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="showDeletionRequest = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <p><?php esc_html_e( 'This will submit a request to the church administrator to remove your account from the directory. This action cannot be undone once processed.', 'community-directory' ); ?></p>
                <div class="cd-form-group">
                    <label class="cd-label"><?php esc_html_e( 'Reason (optional)', 'community-directory' ); ?></label>
                    <textarea class="cd-input" x-model="deletionReason" rows="3" placeholder="<?php esc_attr_e( 'Please let us know why you are leaving...', 'community-directory' ); ?>"></textarea>
                </div>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="showDeletionRequest = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-danger" @click="submitDeletionRequest" :disabled="saving">
                    <span x-show="!saving"><?php esc_html_e( 'Submit Deletion Request', 'community-directory' ); ?></span>
                    <span x-show="saving"><?php esc_html_e( 'Submitting...', 'community-directory' ); ?></span>
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
                    <!-- Only show privacy options for fields that have data AND are relevant to this member type -->
                    <label class="cd-checkbox-label" x-show="form.emails && form.emails.some(e => e.value && e.value.trim())">
                        <input type="checkbox" :checked="form.privacy_settings.email === 'visible'" @change="togglePrivacy('email')">
                        <?php esc_html_e( 'Email Addresses', 'community-directory' ); ?>
                    </label>

                    <label class="cd-checkbox-label" x-show="form.phones && form.phones.some(p => p.value && p.value.trim())">
                        <input type="checkbox" :checked="form.privacy_settings.phone === 'visible'" @change="togglePrivacy('phone')">
                        <?php esc_html_e( 'Phone Numbers', 'community-directory' ); ?>
                    </label>

                    <label class="cd-checkbox-label" x-show="form.address_home || form.city">
                        <input type="checkbox" :checked="form.privacy_settings.address === 'visible'" @change="togglePrivacy('address')">
                        <?php esc_html_e( 'Home Address', 'community-directory' ); ?>
                    </label>

                    <label class="cd-checkbox-label" x-show="form.social_links && form.social_links.some(s => s.url && s.url.trim())">
                        <input type="checkbox" :checked="form.privacy_settings.social === 'visible'" @change="togglePrivacy('social')">
                        <?php esc_html_e( 'Social Media Links', 'community-directory' ); ?>
                    </label>

                    <label class="cd-checkbox-label" x-show="form.date_of_birth">
                        <input type="checkbox" :checked="form.privacy_settings.date_of_birth === 'visible'" @change="togglePrivacy('date_of_birth')">
                        <?php esc_html_e( 'Date of Birth', 'community-directory' ); ?>
                    </label>

                    <!-- Wedding Anniversary: adults only + has data -->
                    <label class="cd-checkbox-label" x-show="householdRole !== 'child' && form.wedding_anniversary">
                        <input type="checkbox" :checked="form.privacy_settings.wedding_anniversary === 'visible'" @change="togglePrivacy('wedding_anniversary')">
                        <?php esc_html_e( 'Wedding Anniversary', 'community-directory' ); ?>
                    </label>

                    <label class="cd-checkbox-label" x-show="form.baptism_date">
                        <input type="checkbox" :checked="form.privacy_settings.baptism_date === 'visible'" @change="togglePrivacy('baptism_date')">
                        <?php esc_html_e( 'Baptism Date', 'community-directory' ); ?>
                    </label>

                    <label class="cd-checkbox-label" x-show="form.name_day">
                        <input type="checkbox" :checked="form.privacy_settings.name_day === 'visible'" @change="togglePrivacy('name_day')">
                        <?php esc_html_e( 'Name Day', 'community-directory' ); ?>
                    </label>

                    <!-- Occupation: adults only + has data -->
                    <label class="cd-checkbox-label" x-show="householdRole !== 'child' && (form.occupation || form.employer)">
                        <input type="checkbox" :checked="form.privacy_settings.occupation === 'visible'" @change="togglePrivacy('occupation')">
                        <?php esc_html_e( 'Occupation / Employer', 'community-directory' ); ?>
                    </label>

                    <!-- Education: children only + has data -->
                    <label class="cd-checkbox-label" x-show="householdRole === 'child' && (form.school_type || form.school_name)">
                        <input type="checkbox" :checked="form.privacy_settings.education === 'visible'" @change="togglePrivacy('education')">
                        <?php esc_html_e( 'Education', 'community-directory' ); ?>
                    </label>

                    <!-- Empty state when no fields have data yet -->
                    <p class="cd-text-muted" x-show="!(form.emails && form.emails.some(e => e.value && e.value.trim())) && !(form.phones && form.phones.some(p => p.value && p.value.trim())) && !form.address_home && !form.city && !form.date_of_birth && !form.baptism_date && !form.name_day && !(form.social_links && form.social_links.some(s => s.url && s.url.trim())) && (householdRole === 'child' || (!form.wedding_anniversary && !form.occupation && !form.employer)) && (householdRole !== 'child' || (!form.school_type && !form.school_name))" style="font-style: italic;">
                        <?php esc_html_e( 'Add information to your profile to manage its visibility here.', 'community-directory' ); ?>
                    </p>
                </div>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-primary" @click="showPrivacyModal = false"><?php esc_html_e( 'Done', 'community-directory' ); ?></button>
            </div>
        </div>
    </div>

    <!-- ──── Image Crop Modal ──── -->
    <template x-if="cropModal.show">
        <div class="cd-modal-overlay" x-transition>
            <div class="cd-modal cd-modal-crop">
                <div class="cd-modal-header">
                    <h3><?php esc_html_e( 'Adjust Photo', 'community-directory' ); ?></h3>
                    <button type="button" class="cd-btn-icon" @click="cancelCrop()">&times;</button>
                </div>
                <div class="cd-modal-body cd-crop-body">
                    <div class="cd-crop-container" :class="{ 'cd-crop-circle': cropModal.aspectRatio === 1 }">
                        <img x-ref="cropImage" :src="cropModal.imageUrl" @load="initCropper()" alt="Crop preview">
                    </div>
                    <div class="cd-crop-error" x-show="cropModal.error" x-text="cropModal.error"></div>
                </div>
                <div class="cd-crop-controls">
                    <div class="cd-crop-zoom-row">
                        <button type="button" class="cd-btn cd-btn-icon cd-btn-secondary-icon" @click="cropZoom(-0.1)" title="<?php esc_attr_e( 'Zoom out', 'community-directory' ); ?>">&minus;</button>
                        <input type="range" class="cd-crop-slider" min="-1" max="2" step="0.05" x-model.number="cropModal.zoomLevel" @input="cropZoomTo($event.target.value)">
                        <button type="button" class="cd-btn cd-btn-icon cd-btn-secondary-icon" @click="cropZoom(0.1)" title="<?php esc_attr_e( 'Zoom in', 'community-directory' ); ?>">+</button>
                    </div>
                    <div class="cd-crop-action-row">
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="cropRotate(-90)" title="<?php esc_attr_e( 'Rotate left', 'community-directory' ); ?>">&#x21BA;</button>
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="cropRotate(90)" title="<?php esc_attr_e( 'Rotate right', 'community-directory' ); ?>">&#x21BB;</button>
                        <button type="button" class="cd-btn cd-btn-sm cd-btn-secondary" @click="cropReset()"><?php esc_html_e( 'Reset', 'community-directory' ); ?></button>
                    </div>
                </div>
                <div class="cd-modal-footer">
                    <button type="button" class="cd-btn cd-btn-secondary" @click="cancelCrop()"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                    <button type="button" class="cd-btn cd-btn-primary" @click="confirmCrop()" :disabled="cropModal.uploading">
                        <span x-show="!cropModal.uploading"><?php esc_html_e( 'Use This Photo', 'community-directory' ); ?></span>
                        <span x-show="cropModal.uploading"><?php esc_html_e( 'Uploading...', 'community-directory' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
    <!-- ──── Photo Position Editor Modal ──── -->
    <div x-show="hhPhotoEditor.open" class="cd-modal-overlay cd-photo-editor-overlay" x-cloak x-transition
         @mouseup="hhEditorDragEnd()" @touchend="hhEditorDragEnd()">
        <div class="cd-modal cd-photo-editor-modal">
            <div class="cd-modal-header">
                <h3><?php esc_html_e( 'Adjust Photo Position', 'community-directory' ); ?></h3>
                <button type="button" class="cd-btn-icon" @click="hhPhotoEditor.open = false">&times;</button>
            </div>
            <div class="cd-modal-body">
                <p class="cd-photo-editor-hint"><?php esc_html_e( 'Drag the photo to reposition. Use the slider to zoom.', 'community-directory' ); ?></p>

                <!-- Live card preview: 300×140 matching directory card ratio -->
                <div class="cd-photo-editor-preview"
                     id="cd-photo-editor-preview"
                     @mousedown="hhEditorDragStart($event)"
                     @mousemove="hhEditorDragMove($event)"
                     @touchstart.prevent="hhEditorDragStart($event)"
                     @touchmove.prevent="hhEditorDragMove($event)">
                    <img :src="hhPhotoEditor.url"
                         class="cd-photo-editor-img"
                         :style="'object-position: ' + hhPhotoEditor.fx + '% ' + hhPhotoEditor.fy + '%; transform: scale(' + hhPhotoEditor.zoom + '); transform-origin: ' + hhPhotoEditor.fx + '% ' + hhPhotoEditor.fy + '%'"
                         draggable="false">
                    <div class="cd-photo-editor-crosshair"
                         :style="'left: ' + hhPhotoEditor.fx + '%; top: ' + hhPhotoEditor.fy + '%'"></div>
                </div>

                <!-- Zoom control -->
                <div class="cd-photo-editor-zoom-row">
                    <label class="cd-photo-editor-zoom-label"><?php esc_html_e( 'Zoom', 'community-directory' ); ?></label>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:0.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35M11 8v6M8 11h6"/></svg>
                    <input type="range" min="1" max="3" step="0.05" x-model="hhPhotoEditor.zoom"
                           class="cd-photo-editor-zoom-slider">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35M11 8v6M8 11h6"/></svg>
                    <span class="cd-photo-editor-zoom-val" x-text="parseFloat(hhPhotoEditor.zoom).toFixed(1) + '×'"></span>
                </div>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="cd-btn cd-btn-secondary" @click="hhPhotoEditor.open = false"><?php esc_html_e( 'Cancel', 'community-directory' ); ?></button>
                <button type="button" class="cd-btn cd-btn-primary" @click="savePhotoPosition()" :disabled="hhPhotoEditor.saving">
                    <span x-show="!hhPhotoEditor.saving"><?php esc_html_e( 'Save Position', 'community-directory' ); ?></span>
                    <span x-show="hhPhotoEditor.saving"><?php esc_html_e( 'Saving...', 'community-directory' ); ?></span>
                </button>
            </div>
        </div>
    </div>

</div>

<?php get_footer(); ?>
