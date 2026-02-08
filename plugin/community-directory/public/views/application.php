<?php
/**
 * Community Directory Application Form.
 * Multi-step wizard for new member applications.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_slug   = get_option( 'cd_base_slug', 'community' );
$landing_url = home_url( $base_slug . '/' );
$login_url   = home_url( $base_slug . '/login/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — <?php esc_html_e( 'Become a Member', 'community-directory' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cd-page cd-application" x-data="cdApplication()">

    <div class="cd-container">
        <header class="cd-header">
            <a href="<?php echo esc_url( $landing_url ); ?>" class="cd-back-link">
                &larr; <?php esc_html_e( 'Back', 'community-directory' ); ?>
            </a>
            <h1 class="cd-title"><?php esc_html_e( 'Become a Member', 'community-directory' ); ?></h1>
        </header>

        <main class="cd-main">
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
                        <!-- Step 1: Personal Info -->
                        <div x-show="step === 0">
                            <h2><?php esc_html_e( 'Personal Information', 'community-directory' ); ?></h2>

                            <div class="cd-form-row">
                                <div class="cd-form-group cd-form-half">
                                    <label for="cd-first-name"><?php esc_html_e( 'First Name', 'community-directory' ); ?> *</label>
                                    <input
                                        type="text"
                                        id="cd-first-name"
                                        x-model="form.first_name"
                                        required
                                        autocomplete="given-name"
                                    >
                                </div>
                                <div class="cd-form-group cd-form-half">
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

                            <div class="cd-form-group">
                                <label for="cd-phone"><?php esc_html_e( 'Phone Number', 'community-directory' ); ?> *</label>
                                <input
                                    type="tel"
                                    id="cd-phone"
                                    x-model="form.phone"
                                    required
                                    autocomplete="tel"
                                >
                            </div>
                        </div>

                        <!-- Step 2: Additional Details -->
                        <div x-show="step === 1">
                            <h2><?php esc_html_e( 'Additional Details', 'community-directory' ); ?></h2>

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
                                    <label for="cd-zip"><?php esc_html_e( 'ZIP', 'community-directory' ); ?></label>
                                    <input
                                        type="text"
                                        id="cd-zip"
                                        x-model="form.zip"
                                        autocomplete="postal-code"
                                        maxlength="10"
                                    >
                                </div>
                            </div>

                            <div class="cd-form-group">
                                <label for="cd-dob"><?php esc_html_e( 'Date of Birth', 'community-directory' ); ?></label>
                                <input
                                    type="date"
                                    id="cd-dob"
                                    x-model="form.date_of_birth"
                                >
                            </div>
                        </div>

                        <!-- Step 3: Family (Optional) -->
                        <div x-show="step === 2">
                            <h2><?php esc_html_e( 'Family Members', 'community-directory' ); ?></h2>
                            <p class="cd-text-muted"><?php esc_html_e( 'Optionally add your spouse and children. They can be added or updated later.', 'community-directory' ); ?></p>

                            <!-- Spouse -->
                            <div class="cd-form-section">
                                <h3><?php esc_html_e( 'Spouse', 'community-directory' ); ?></h3>
                                <label class="cd-checkbox">
                                    <input type="checkbox" x-model="hasSpouse">
                                    <?php esc_html_e( 'I would like to add my spouse', 'community-directory' ); ?>
                                </label>

                                <div x-show="hasSpouse" x-transition class="cd-nested-form">
                                    <div class="cd-form-row">
                                        <div class="cd-form-group cd-form-half">
                                            <label><?php esc_html_e( 'First Name', 'community-directory' ); ?></label>
                                            <input type="text" x-model="form.spouse_first_name">
                                        </div>
                                        <div class="cd-form-group cd-form-half">
                                            <label><?php esc_html_e( 'Last Name', 'community-directory' ); ?></label>
                                            <input type="text" x-model="form.spouse_last_name">
                                        </div>
                                    </div>
                                    <div class="cd-form-group">
                                        <label><?php esc_html_e( 'Email', 'community-directory' ); ?></label>
                                        <input type="email" x-model="form.spouse_email">
                                        <p class="cd-help-text"><?php esc_html_e( 'Your spouse will receive their own invitation to join.', 'community-directory' ); ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Children -->
                            <div class="cd-form-section">
                                <h3><?php esc_html_e( 'Children', 'community-directory' ); ?></h3>
                                <template x-for="(child, index) in form.children" :key="index">
                                    <div class="cd-nested-form cd-child-entry">
                                        <div class="cd-form-row cd-form-row-with-action">
                                            <div class="cd-form-group cd-form-half">
                                                <label x-text="'<?php esc_attr_e( 'First Name', 'community-directory' ); ?>'"></label>
                                                <input type="text" x-model="child.first_name">
                                            </div>
                                            <div class="cd-form-group cd-form-third">
                                                <label x-text="'<?php esc_attr_e( 'Date of Birth', 'community-directory' ); ?>'"></label>
                                                <input type="date" x-model="child.date_of_birth">
                                            </div>
                                            <button
                                                type="button"
                                                class="cd-btn cd-btn-icon cd-btn-danger"
                                                @click="removeChild(index)"
                                                :aria-label="'<?php esc_attr_e( 'Remove child', 'community-directory' ); ?>'"
                                            >&times;</button>
                                        </div>
                                    </div>
                                </template>
                                <button type="button" class="cd-btn cd-btn-secondary cd-btn-sm" @click="addChild()">
                                    + <?php esc_html_e( 'Add Child', 'community-directory' ); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Step 4: Review & Submit -->
                        <div x-show="step === 3">
                            <h2><?php esc_html_e( 'Review & Submit', 'community-directory' ); ?></h2>

                            <dl class="cd-review-list">
                                <dt><?php esc_html_e( 'Name', 'community-directory' ); ?></dt>
                                <dd x-text="form.first_name + ' ' + form.last_name"></dd>

                                <dt><?php esc_html_e( 'Email', 'community-directory' ); ?></dt>
                                <dd x-text="form.email"></dd>

                                <dt><?php esc_html_e( 'Phone', 'community-directory' ); ?></dt>
                                <dd x-text="form.phone"></dd>

                                <template x-if="form.address_line_1">
                                    <div>
                                        <dt><?php esc_html_e( 'Address', 'community-directory' ); ?></dt>
                                        <dd x-text="[form.address_line_1, form.city, form.state, form.zip].filter(Boolean).join(', ')"></dd>
                                    </div>
                                </template>

                                <template x-if="hasSpouse && form.spouse_first_name">
                                    <div>
                                        <dt><?php esc_html_e( 'Spouse', 'community-directory' ); ?></dt>
                                        <dd x-text="form.spouse_first_name + ' ' + (form.spouse_last_name || '')"></dd>
                                    </div>
                                </template>

                                <template x-if="form.children.length > 0">
                                    <div>
                                        <dt><?php esc_html_e( 'Children', 'community-directory' ); ?></dt>
                                        <dd>
                                            <ul>
                                                <template x-for="child in form.children" :key="child.first_name">
                                                    <li x-text="child.first_name"></li>
                                                </template>
                                            </ul>
                                        </dd>
                                    </div>
                                </template>
                            </dl>

                            <label class="cd-checkbox">
                                <input type="checkbox" x-model="agreedToTerms">
                                <?php esc_html_e( 'I agree that my information will be shared with approved members of the church community directory.', 'community-directory' ); ?>
                            </label>
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="cd-form-nav">
                            <button
                                type="button"
                                class="cd-btn cd-btn-secondary"
                                x-show="step > 0"
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
        </main>

        <footer class="cd-footer">
            <p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
        </footer>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
