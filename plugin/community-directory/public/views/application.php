<?php
/**
 * Community Directory Application Form.
 * Multi-step wizard for new member applications.
 * Based on the St. Thekla Membership Application paper form.
 * Rendered inside the active WordPress theme via get_header/get_footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug   = get_option( 'cd_base_slug', 'community' );
$landing_url = home_url( $base_slug . '/' );
$login_url   = home_url( $base_slug . '/login/' );

get_header();
?>

<div class="cd-wrap cd-application" x-data="cdApplication">
    <div class="cd-container">
        <div class="cd-page-header">
            <a href="<?php echo esc_url( $landing_url ); ?>" class="cd-back-link">
                &larr; <?php esc_html_e( 'Back', 'community-directory' ); ?>
            </a>
            <h1 class="cd-title"><?php esc_html_e( 'Membership Application', 'community-directory' ); ?></h1>
            <p class="cd-subtitle-text"><?php esc_html_e( 'St. Thekla Malankara Orthodox Church, Inc.', 'community-directory' ); ?></p>
        </div>

        <div class="cd-main">
            <!-- Success state — application submitted -->
            <template x-if="submitted">
                <div class="cd-card">
                    <div class="cd-success-icon">&#10003;</div>
                    <h2><?php esc_html_e( 'Application Submitted!', 'community-directory' ); ?></h2>
                    <p><?php esc_html_e( 'Thank you for your interest in joining our community directory.', 'community-directory' ); ?></p>
                    <p><?php esc_html_e( 'We have sent a verification email to the address you provided. Please check your inbox (and spam folder) and click the verification link to complete your application.', 'community-directory' ); ?></p>
                    <p class="cd-text-muted"><?php esc_html_e( 'The verification link expires in 48 hours.', 'community-directory' ); ?></p>
                    <a href="<?php echo esc_url( $landing_url ); ?>" class="cd-btn cd-btn-primary">
                        <?php esc_html_e( 'Return Home', 'community-directory' ); ?>
                    </a>
                </div>
            </template>

            <!-- Application form -->
            <template x-if="!submitted">
                <div>
                    <!-- Step indicator -->
                    <div class="cd-steps">
                        <template x-for="(stepLabel, index) in steps" :key="index">
                            <div
                                class="cd-step"
                                :class="{
                                    'cd-step-active': step === index,
                                    'cd-step-done': step > index
                                }"
                            >
                                <span class="cd-step-number" x-text="index + 1"></span>
                                <span class="cd-step-label" x-text="stepLabel"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Error message -->
                    <template x-if="errorMessage">
                        <div class="cd-alert cd-alert-error" x-text="errorMessage"></div>
                    </template>

                    <div class="cd-card">
                        <!-- Step 1: Personal Information -->
                        <div x-show="step === 0">
                            <h2><?php esc_html_e( 'Personal Information', 'community-directory' ); ?></h2>

                            <div class="cd-form-row">
                                <div class="cd-form-group cd-form-grow">
                                    <label for="cd-first-name"><?php esc_html_e( 'First Name', 'community-directory' ); ?> *</label>
                                    <input
                                        type="text"
                                        id="cd-first-name"
                                        x-model="form.first_name"
                                        required
                                        autocomplete="given-name"
                                    >
                                </div>
                                <div class="cd-form-group cd-form-narrow">
                                    <label for="cd-middle-initial"><?php esc_html_e( 'M.I.', 'community-directory' ); ?></label>
                                    <input
                                        type="text"
                                        id="cd-middle-initial"
                                        x-model="form.middle_initial"
                                        maxlength="1"
                                        autocomplete="additional-name"
                                    >
                                </div>
                                <div class="cd-form-group cd-form-grow">
                                    <label for="cd-last-name"><?php esc_html_e( 'Last Name', 'community-directory' ); ?> *</label>
                                    <input
                                        type="text"
                                        id="cd-last-name"
                                        x-model="form.last_name"
                                        required
                                        autocomplete="family-name"
                                    >
                                </div>
                            </div>

                            <div class="cd-form-group">
                                <label for="cd-email"><?php esc_html_e( 'Email Address', 'community-directory' ); ?> *</label>
                                <input
                                    type="email"
                                    id="cd-email"
                                    x-model="form.email"
                                    required
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                >
                                <p class="cd-help-text"><?php esc_html_e( 'A verification email will be sent to this address.', 'community-directory' ); ?></p>
                            </div>

                            <div class="cd-form-row">
                                <div class="cd-form-group cd-form-half">
                                    <label for="cd-phone"><?php esc_html_e( 'Phone Number (Mobile)', 'community-directory' ); ?> *</label>
                                    <input
                                        type="tel"
                                        id="cd-phone"
                                        x-model="form.phone"
                                        required
                                        autocomplete="tel"
                                    >
                                </div>
                                <div class="cd-form-group cd-form-half">
                                    <label for="cd-dob"><?php esc_html_e( 'Date of Birth', 'community-directory' ); ?></label>
                                    <input
                                        type="date"
                                        id="cd-dob"
                                        x-model="form.date_of_birth"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Address & Church Background -->
                        <div x-show="step === 1" x-cloak>
                            <h2><?php esc_html_e( 'Address & Church Background', 'community-directory' ); ?></h2>

                            <div class="cd-form-group">
                                <label for="cd-address"><?php esc_html_e( 'Street Address', 'community-directory' ); ?></label>
                                <input
                                    type="text"
                                    id="cd-address"
                                    x-model="form.address_line_1"
                                    autocomplete="address-line1"
                                >
                            </div>

                            <div class="cd-form-row">
                                <div class="cd-form-group cd-form-half">
                                    <label for="cd-city"><?php esc_html_e( 'City', 'community-directory' ); ?></label>
                                    <input
                                        type="text"
                                        id="cd-city"
                                        x-model="form.city"
                                        autocomplete="address-level2"
                                    >
                                </div>
                                <div class="cd-form-group cd-form-quarter">
                                    <label for="cd-state"><?php esc_html_e( 'State', 'community-directory' ); ?></label>
                                    <input
                                        type="text"
                                        id="cd-state"
                                        x-model="form.state"
                                        autocomplete="address-level1"
                                        maxlength="2"
                                    >
                                </div>
                                <div class="cd-form-group cd-form-quarter">
                                    <label for="cd-zip"><?php esc_html_e( 'ZIP Code', 'community-directory' ); ?></label>
                                    <input
                                        type="text"
                                        id="cd-zip"
                                        x-model="form.zip"
                                        autocomplete="postal-code"
                                        maxlength="10"
                                    >
                                </div>
                            </div>

                            <div class="cd-form-row">
                                <div class="cd-form-group cd-form-half">
                                    <label for="cd-baptism"><?php esc_html_e( 'Date of Baptism (Malankara Orthodox Church)', 'community-directory' ); ?></label>
                                    <input
                                        type="date"
                                        id="cd-baptism"
                                        x-model="form.date_of_baptism"
                                    >
                                </div>
                                <div class="cd-form-group cd-form-half">
                                    <label for="cd-profession"><?php esc_html_e( 'Profession', 'community-directory' ); ?></label>
                                    <input
                                        type="text"
                                        id="cd-profession"
                                        x-model="form.profession"
                                    >
                                </div>
                            </div>

                            <div class="cd-form-group">
                                <label for="cd-prior-parishes"><?php esc_html_e( 'List of Prior Parishes', 'community-directory' ); ?></label>
                                <textarea
                                    id="cd-prior-parishes"
                                    x-model="form.prior_parishes"
                                    rows="2"
                                    placeholder="<?php esc_attr_e( 'Enter parish names, separated by commas', 'community-directory' ); ?>"
                                ></textarea>
                            </div>

                            <div class="cd-form-section">
                                <h3><?php esc_html_e( 'Marital Status', 'community-directory' ); ?></h3>
                                <div class="cd-radio-group">
                                    <label class="cd-radio">
                                        <input type="radio" name="marital_status" value="single" x-model="form.marital_status">
                                        <?php esc_html_e( 'Single', 'community-directory' ); ?>
                                    </label>
                                    <label class="cd-radio">
                                        <input type="radio" name="marital_status" value="married" x-model="form.marital_status">
                                        <?php esc_html_e( 'Married', 'community-directory' ); ?>
                                    </label>
                                </div>
                                <p class="cd-help-text"><?php esc_html_e( 'If widowed or divorced, please disclose to the Priest.', 'community-directory' ); ?></p>

                                <div x-show="form.marital_status === 'married'" x-cloak x-transition class="cd-nested-form">
                                    <div class="cd-form-row">
                                        <div class="cd-form-group cd-form-half">
                                            <label for="cd-marriage-date"><?php esc_html_e( 'Date of Marriage', 'community-directory' ); ?></label>
                                            <input
                                                type="date"
                                                id="cd-marriage-date"
                                                x-model="form.date_of_marriage"
                                            >
                                        </div>
                                        <div class="cd-form-group cd-form-half">
                                            <label for="cd-marriage-church"><?php esc_html_e( 'Marriage Registered At (Church Name, City)', 'community-directory' ); ?></label>
                                            <input
                                                type="text"
                                                id="cd-marriage-church"
                                                x-model="form.marriage_registered_at"
                                                placeholder="<?php esc_attr_e( 'Church Name, City/Town', 'community-directory' ); ?>"
                                            >
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Family Members -->
                        <div x-show="step === 2" x-cloak>
                            <h2><?php esc_html_e( 'Family Members', 'community-directory' ); ?></h2>
                            <p class="cd-text-muted"><?php esc_html_e( 'Optionally add your spouse and children. They can be added or updated later.', 'community-directory' ); ?></p>

                            <!-- Spouse -->
                            <div class="cd-form-section">
                                <h3><?php esc_html_e( 'Spouse', 'community-directory' ); ?></h3>
                                <label class="cd-checkbox">
                                    <input type="checkbox" x-model="hasSpouse">
                                    <?php esc_html_e( 'I would like to add my spouse', 'community-directory' ); ?>
                                </label>

                                <div x-show="hasSpouse" x-cloak x-transition class="cd-nested-form">
                                    <div class="cd-form-row">
                                        <div class="cd-form-group cd-form-grow">
                                            <label><?php esc_html_e( 'First Name', 'community-directory' ); ?></label>
                                            <input type="text" x-model="form.spouse_first_name">
                                        </div>
                                        <div class="cd-form-group cd-form-narrow">
                                            <label><?php esc_html_e( 'M.I.', 'community-directory' ); ?></label>
                                            <input type="text" x-model="form.spouse_middle_initial" maxlength="1">
                                        </div>
                                        <div class="cd-form-group cd-form-grow">
                                            <label><?php esc_html_e( 'Last Name', 'community-directory' ); ?></label>
                                            <input type="text" x-model="form.spouse_last_name">
                                        </div>
                                    </div>
                                    <div class="cd-form-row">
                                        <div class="cd-form-group cd-form-half">
                                            <label><?php esc_html_e( 'Relationship', 'community-directory' ); ?></label>
                                            <select x-model="form.spouse_relationship">
                                                <option value=""><?php esc_html_e( 'Select...', 'community-directory' ); ?></option>
                                                <option value="husband"><?php esc_html_e( 'Husband', 'community-directory' ); ?></option>
                                                <option value="wife"><?php esc_html_e( 'Wife', 'community-directory' ); ?></option>
                                            </select>
                                        </div>
                                        <div class="cd-form-group cd-form-half">
                                            <label><?php esc_html_e( 'Phone Number', 'community-directory' ); ?></label>
                                            <input type="tel" x-model="form.spouse_phone">
                                        </div>
                                    </div>
                                    <div class="cd-form-group">
                                        <label><?php esc_html_e( 'Email', 'community-directory' ); ?></label>
                                        <input type="email" x-model="form.spouse_email">
                                        <p class="cd-help-text"><?php esc_html_e( 'Your spouse will receive their own invitation to join.', 'community-directory' ); ?></p>
                                    </div>
                                    <div class="cd-form-row">
                                        <div class="cd-form-group cd-form-half">
                                            <label><?php esc_html_e( 'Date of Birth', 'community-directory' ); ?></label>
                                            <input type="date" x-model="form.spouse_date_of_birth">
                                        </div>
                                        <div class="cd-form-group cd-form-half">
                                            <label><?php esc_html_e( 'Date of Baptism', 'community-directory' ); ?></label>
                                            <input type="date" x-model="form.spouse_date_of_baptism">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Children / Other Family -->
                            <div class="cd-form-section">
                                <h3><?php esc_html_e( 'Children / Other Family Members', 'community-directory' ); ?></h3>
                                <template x-for="(child, index) in form.children" :key="index">
                                    <div class="cd-nested-form cd-child-entry">
                                        <div class="cd-child-header">
                                            <span class="cd-child-label" x-text="'Family Member ' + (index + 1)"></span>
                                            <button
                                                type="button"
                                                class="cd-btn cd-btn-icon cd-btn-danger"
                                                @click="removeChild(index)"
                                                :aria-label="'<?php esc_attr_e( 'Remove', 'community-directory' ); ?>'"
                                            >&times;</button>
                                        </div>
                                        <div class="cd-form-row">
                                            <div class="cd-form-group cd-form-grow">
                                                <label><?php esc_html_e( 'First Name', 'community-directory' ); ?></label>
                                                <input type="text" x-model="child.first_name">
                                            </div>
                                            <div class="cd-form-group cd-form-narrow">
                                                <label><?php esc_html_e( 'M.I.', 'community-directory' ); ?></label>
                                                <input type="text" x-model="child.middle_initial" maxlength="1">
                                            </div>
                                            <div class="cd-form-group cd-form-grow">
                                                <label><?php esc_html_e( 'Last Name', 'community-directory' ); ?></label>
                                                <input type="text" x-model="child.last_name">
                                            </div>
                                        </div>
                                        <div class="cd-form-row">
                                            <div class="cd-form-group cd-form-third">
                                                <label><?php esc_html_e( 'Relationship', 'community-directory' ); ?></label>
                                                <select x-model="child.relationship">
                                                    <option value=""><?php esc_html_e( 'Select...', 'community-directory' ); ?></option>
                                                    <option value="son"><?php esc_html_e( 'Son', 'community-directory' ); ?></option>
                                                    <option value="daughter"><?php esc_html_e( 'Daughter', 'community-directory' ); ?></option>
                                                    <option value="other"><?php esc_html_e( 'Other', 'community-directory' ); ?></option>
                                                </select>
                                            </div>
                                            <div class="cd-form-group cd-form-third">
                                                <label><?php esc_html_e( 'Date of Birth', 'community-directory' ); ?></label>
                                                <input type="date" x-model="child.date_of_birth">
                                            </div>
                                            <div class="cd-form-group cd-form-third">
                                                <label><?php esc_html_e( 'Date of Baptism', 'community-directory' ); ?></label>
                                                <input type="date" x-model="child.date_of_baptism">
                                            </div>
                                        </div>
                                        <div class="cd-form-group">
                                            <label><?php esc_html_e( 'Email (if applicable)', 'community-directory' ); ?></label>
                                            <input type="email" x-model="child.email">
                                        </div>
                                    </div>
                                </template>
                                <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="addChild()">
                                    + <?php esc_html_e( 'Add Family Member', 'community-directory' ); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Step 4: Interests & Review -->
                        <div x-show="step === 3" x-cloak>
                            <h2><?php esc_html_e( 'Talents, Interests & Review', 'community-directory' ); ?></h2>

                            <!-- Ministry Interests -->
                            <div class="cd-form-section">
                                <h3><?php esc_html_e( 'Ministry Interests', 'community-directory' ); ?></h3>
                                <p class="cd-text-muted"><?php esc_html_e( 'Please indicate which ministries you would be interested in being involved with:', 'community-directory' ); ?></p>

                                <div class="cd-checkbox-grid">
                                    <label class="cd-checkbox">
                                        <input type="checkbox" @change="toggleMinistry('worship')" :checked="form.ministry_interests.includes('worship')">
                                        <?php esc_html_e( 'Worship / Altar Service', 'community-directory' ); ?>
                                    </label>
                                    <label class="cd-checkbox">
                                        <input type="checkbox" @change="toggleMinistry('choir')" :checked="form.ministry_interests.includes('choir')">
                                        <?php esc_html_e( 'Choir', 'community-directory' ); ?>
                                    </label>
                                    <label class="cd-checkbox">
                                        <input type="checkbox" @change="toggleMinistry('sunday_school')" :checked="form.ministry_interests.includes('sunday_school')">
                                        <?php esc_html_e( 'Sunday School', 'community-directory' ); ?>
                                    </label>
                                    <label class="cd-checkbox">
                                        <input type="checkbox" @change="toggleMinistry('adult_education')" :checked="form.ministry_interests.includes('adult_education')">
                                        <?php esc_html_e( 'Adult Education', 'community-directory' ); ?>
                                    </label>
                                    <label class="cd-checkbox">
                                        <input type="checkbox" @change="toggleMinistry('community_service')" :checked="form.ministry_interests.includes('community_service')">
                                        <?php esc_html_e( 'Community Service', 'community-directory' ); ?>
                                    </label>
                                    <label class="cd-checkbox">
                                        <input type="checkbox" @change="toggleMinistry('administration')" :checked="form.ministry_interests.includes('administration')">
                                        <?php esc_html_e( 'Administration', 'community-directory' ); ?>
                                    </label>
                                </div>
                                <div class="cd-form-group">
                                    <label for="cd-ministry-other"><?php esc_html_e( 'Other', 'community-directory' ); ?></label>
                                    <input type="text" id="cd-ministry-other" x-model="form.ministry_other" placeholder="<?php esc_attr_e( 'Please specify...', 'community-directory' ); ?>">
                                </div>
                            </div>

                            <!-- Review Summary -->
                            <div class="cd-form-section">
                                <h3><?php esc_html_e( 'Review Your Information', 'community-directory' ); ?></h3>
                                <dl class="cd-review-list">
                                    <dt><?php esc_html_e( 'Name', 'community-directory' ); ?></dt>
                                    <dd x-text="[form.first_name, form.middle_initial, form.last_name].filter(Boolean).join(' ')"></dd>

                                    <dt><?php esc_html_e( 'Email', 'community-directory' ); ?></dt>
                                    <dd x-text="form.email"></dd>

                                    <dt><?php esc_html_e( 'Phone', 'community-directory' ); ?></dt>
                                    <dd x-text="form.phone"></dd>

                                    <template x-if="form.date_of_birth">
                                        <div>
                                            <dt><?php esc_html_e( 'Date of Birth', 'community-directory' ); ?></dt>
                                            <dd x-text="form.date_of_birth"></dd>
                                        </div>
                                    </template>

                                    <template x-if="form.address_line_1">
                                        <div>
                                            <dt><?php esc_html_e( 'Address', 'community-directory' ); ?></dt>
                                            <dd x-text="[form.address_line_1, form.city, form.state, form.zip].filter(Boolean).join(', ')"></dd>
                                        </div>
                                    </template>

                                    <template x-if="form.date_of_baptism">
                                        <div>
                                            <dt><?php esc_html_e( 'Date of Baptism', 'community-directory' ); ?></dt>
                                            <dd x-text="form.date_of_baptism"></dd>
                                        </div>
                                    </template>

                                    <template x-if="form.profession">
                                        <div>
                                            <dt><?php esc_html_e( 'Profession', 'community-directory' ); ?></dt>
                                            <dd x-text="form.profession"></dd>
                                        </div>
                                    </template>

                                    <template x-if="form.marital_status">
                                        <div>
                                            <dt><?php esc_html_e( 'Marital Status', 'community-directory' ); ?></dt>
                                            <dd x-text="form.marital_status === 'married' ? 'Married' : 'Single'"></dd>
                                        </div>
                                    </template>

                                    <template x-if="hasSpouse && form.spouse_first_name">
                                        <div>
                                            <dt><?php esc_html_e( 'Spouse', 'community-directory' ); ?></dt>
                                            <dd x-text="[form.spouse_first_name, form.spouse_middle_initial, form.spouse_last_name].filter(Boolean).join(' ')"></dd>
                                        </div>
                                    </template>

                                    <template x-if="form.children.length > 0">
                                        <div>
                                            <dt><?php esc_html_e( 'Family Members', 'community-directory' ); ?></dt>
                                            <dd>
                                                <ul>
                                                    <template x-for="child in form.children" :key="child.first_name">
                                                        <li x-text="[child.first_name, child.last_name].filter(Boolean).join(' ') + (child.relationship ? ' (' + child.relationship + ')' : '')"></li>
                                                    </template>
                                                </ul>
                                            </dd>
                                        </div>
                                    </template>

                                    <template x-if="form.ministry_interests.length > 0 || form.ministry_other">
                                        <div>
                                            <dt><?php esc_html_e( 'Ministry Interests', 'community-directory' ); ?></dt>
                                            <dd x-text="[...form.ministry_interests.map(m => m.replace(/_/g, ' ')), form.ministry_other].filter(Boolean).join(', ')"></dd>
                                        </div>
                                    </template>
                                </dl>
                            </div>

                            <!-- Responsibilities notice -->
                            <div class="cd-notice">
                                <p><strong><?php esc_html_e( 'Member Responsibilities:', 'community-directory' ); ?></strong></p>
                                <ul>
                                    <li><?php esc_html_e( 'Act in accordance with and support St. Thekla Church\'s Mission Statement', 'community-directory' ); ?></li>
                                    <li><?php esc_html_e( 'Support the financial obligations of St. Thekla Church within your financial means', 'community-directory' ); ?></li>
                                    <li><?php esc_html_e( 'Comply with the rules of St. Thekla Church and the Northeast American Malankara Orthodox Diocese', 'community-directory' ); ?></li>
                                </ul>
                            </div>

                            <label class="cd-checkbox">
                                <input type="checkbox" x-model="agreedToTerms">
                                <?php esc_html_e( 'I hereby certify that the information contained herein is true and correct to the best of my knowledge and belief. I agree that my information will be shared with approved members of the church community directory.', 'community-directory' ); ?>
                            </label>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="cd-form-nav">
                            <button
                                type="button"
                                class="cd-btn cd-btn-secondary"
                                x-show="step > 0"
                                x-cloak
                                @click="prevStep()"
                            >
                                <?php esc_html_e( 'Back', 'community-directory' ); ?>
                            </button>
                            <div class="cd-spacer"></div>
                            <button
                                type="button"
                                class="cd-btn cd-btn-primary"
                                x-show="step < steps.length - 1"
                                @click="nextStep()"
                            >
                                <?php esc_html_e( 'Continue', 'community-directory' ); ?>
                            </button>
                            <button
                                type="button"
                                class="cd-btn cd-btn-primary"
                                x-show="step === steps.length - 1"
                                x-cloak
                                @click="submitApplication()"
                                :disabled="!agreedToTerms || loading"
                            >
                                <span x-show="!loading"><?php esc_html_e( 'Submit Application', 'community-directory' ); ?></span>
                                <span x-show="loading" class="cd-spinner"></span>
                            </button>
                        </div>
                    </div>

                    <div class="cd-form-footer">
                        <p>
                            <?php esc_html_e( 'Already a member?', 'community-directory' ); ?>
                            <a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in', 'community-directory' ); ?></a>
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<?php get_footer(); ?>
