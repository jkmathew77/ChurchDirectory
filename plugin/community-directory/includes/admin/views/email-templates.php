<?php
/**
 * Admin page: Email Templates management.
 *
 * Allows admins to view and customize all email templates
 * used by the Community Directory plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Template definitions with defaults and variable docs.
$templates = array(
    'invite' => array(
        'label'       => __( 'Application Approval Invite', 'community-directory' ),
        'description' => __( 'Sent to approved applicants with their invite link to create an account.', 'community-directory' ),
        'subject_default' => __( 'Welcome to St. Thekla Community Directory — Set Up Your Account', 'community-directory' ),
        'body_default' => "Hello {first_name},\n\nGreat news! Your membership application has been approved.\n\nPlease click the link below to create your account and access the community directory:\n\n{invite_url}\n\nThis invitation link will expire in {expiry_days} days.\n\nIf you have any questions, please contact the church office.\n\nGod bless,\nSt. Thekla Malankara Orthodox Church",
        'variables' => array( '{first_name}', '{invite_url}', '{expiry_days}' ),
    ),
    'rejection' => array(
        'label'       => __( 'Application Rejection', 'community-directory' ),
        'description' => __( 'Sent when an application is rejected. Includes the rejection reason.', 'community-directory' ),
        'subject_default' => __( 'St. Thekla Community Directory — Application Update', 'community-directory' ),
        'body_default' => "Hello {first_name},\n\nThank you for your interest in the St. Thekla Community Directory.\n\nAfter review, we were unable to approve your application at this time.\n\n{reason}\n\nIf you believe this is an error or have questions, please reach out to the church office.\n\nGod bless,\nSt. Thekla Malankara Orthodox Church",
        'variables' => array( '{first_name}', '{reason}' ),
    ),
    'info_request' => array(
        'label'       => __( 'Additional Information Request', 'community-directory' ),
        'description' => __( 'Sent when an officer requests more information from an applicant.', 'community-directory' ),
        'subject_default' => __( 'St. Thekla Community Directory — Additional Information Needed', 'community-directory' ),
        'body_default' => "Hello {first_name},\n\nThank you for applying to the St. Thekla Community Directory. We need a bit more information before we can process your application.\n\n{message}\n\nPlease reply to this email or contact the church office with the requested information.\n\nGod bless,\nSt. Thekla Malankara Orthodox Church",
        'variables' => array( '{first_name}', '{message}' ),
    ),
    'officer_added' => array(
        'label'       => __( 'Officer Role Assigned', 'community-directory' ),
        'description' => __( 'Sent when a member is assigned as a church officer.', 'community-directory' ),
        'subject_default' => __( 'St. Thekla Community Directory — Officer Role Assigned', 'community-directory' ),
        'body_default' => "Hello {first_name},\n\nYou have been assigned as an officer of the St. Thekla Community Directory.\n\n{title_line}As an officer, you will:\n- Receive notifications for new membership applications\n- Have access to review and manage applications\n- Be able to manage directory settings\n\nThank you for your service to the community.\n\nGod bless,\nSt. Thekla Malankara Orthodox Church",
        'variables' => array( '{first_name}', '{title_line}' ),
    ),
    'officer_removed' => array(
        'label'       => __( 'Officer Role Removed', 'community-directory' ),
        'description' => __( 'Sent when an officer role is revoked.', 'community-directory' ),
        'subject_default' => __( 'St. Thekla Community Directory — Officer Role Update', 'community-directory' ),
        'body_default' => "Hello {first_name},\n\nThis is to let you know that your officer role in the St. Thekla Community Directory has been updated. You are no longer listed as an active officer.\n\nYou will continue to have full access to the community directory as a member.\n\nThank you for your service.\n\nGod bless,\nSt. Thekla Malankara Orthodox Church",
        'variables' => array( '{first_name}' ),
    ),
    'password_reset' => array(
        'label'       => __( 'Password Reset', 'community-directory' ),
        'description' => __( 'Sent when a member requests a password reset.', 'community-directory' ),
        'subject_default' => __( 'Password Reset — St. Thekla Community Directory', 'community-directory' ),
        'body_default' => "Hello {display_name},\n\nWe received a request to reset your password for the St. Thekla Community Directory.\n\nClick here to reset your password:\n{reset_url}\n\nThis link will expire in 1 hour.\n\nIf you did not request this, you can safely ignore this email.\n\nGod bless,\nSt. Thekla Malankara Orthodox Church",
        'variables' => array( '{display_name}', '{reset_url}' ),
    ),
    'email_hint' => array(
        'label'       => __( 'Account Email Hint', 'community-directory' ),
        'description' => __( 'Sent when a member requests help finding their login email.', 'community-directory' ),
        'subject_default' => __( 'Your Account Email — St. Thekla Community Directory', 'community-directory' ),
        'body_default' => "Hello {first_name},\n\nYou requested help finding your account email for the St. Thekla Community Directory.\n\nYour account email is: {masked_email}\n\nYou can log in here: {login_url}\n\nIf you did not request this, you can safely ignore this email.\n\nGod bless,\nSt. Thekla Malankara Orthodox Church",
        'variables' => array( '{first_name}', '{masked_email}', '{login_url}' ),
    ),
    'household_invite' => array(
        'label'       => __( 'Household Member Invite', 'community-directory' ),
        'description' => __( 'Sent when someone is added to a household with an email address.', 'community-directory' ),
        'subject_default' => __( 'You\'ve Been Added to a Household — St. Thekla Community Directory', 'community-directory' ),
        'body_default' => "Hello {first_name},\n\n{inviter_name} has added you to their household in the St. Thekla Community Directory.\n\nPlease click the link below to create your account and manage your own profile:\n\n{invite_url}\n\nThis invitation link will expire in {expiry_days} days.\n\nIf you have any questions, please contact the church office.\n\nGod bless,\nSt. Thekla Malankara Orthodox Church",
        'variables' => array( '{first_name}', '{inviter_name}', '{invite_url}', '{expiry_days}' ),
    ),
    'officer_notification' => array(
        'label'       => __( 'Officer Approval Notification', 'community-directory' ),
        'description' => __( 'Sent to all active officers when an application is approved.', 'community-directory' ),
        'subject_default' => __( 'Application Approved: {first_name} {last_name}', 'community-directory' ),
        'body_default' => "A membership application has been approved.\n\nName: {first_name} {last_name}\nEmail: {email}\nApproved by: {approved_by}\n\nAn invitation email has been sent to the applicant.\n\nSt. Thekla Community Directory",
        'variables' => array( '{first_name}', '{last_name}', '{email}', '{approved_by}' ),
    ),
);

// Handle form save.
if ( isset( $_POST['cd_save_email_template'] ) && check_admin_referer( 'cd_save_email_template' ) ) {
    $template_key = sanitize_key( $_POST['cd_template_key'] );
    if ( isset( $templates[ $template_key ] ) ) {
        $custom_subject = sanitize_text_field( wp_unslash( $_POST['cd_template_subject'] ?? '' ) );
        $custom_body    = sanitize_textarea_field( wp_unslash( $_POST['cd_template_body'] ?? '' ) );

        $saved = get_option( 'cd_email_templates', array() );

        if ( empty( $custom_subject ) && empty( $custom_body ) ) {
            // Reset to default — remove custom override.
            unset( $saved[ $template_key ] );
        } else {
            $saved[ $template_key ] = array(
                'subject' => $custom_subject,
                'body'    => $custom_body,
            );
        }

        update_option( 'cd_email_templates', $saved );

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email template saved.', 'community-directory' ) . '</p></div>';
    }
}

// Handle reset to default.
if ( isset( $_POST['cd_reset_email_template'] ) && check_admin_referer( 'cd_reset_email_template' ) ) {
    $template_key = sanitize_key( $_POST['cd_template_key'] );
    $saved = get_option( 'cd_email_templates', array() );
    unset( $saved[ $template_key ] );
    update_option( 'cd_email_templates', $saved );

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template reset to default.', 'community-directory' ) . '</p></div>';
}

$saved_templates = get_option( 'cd_email_templates', array() );
$editing         = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : '';
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Email Templates', 'community-directory' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Customize the email templates sent by the Community Directory plugin. Leave fields empty to use the default text.', 'community-directory' ); ?></p>

    <?php if ( $editing && isset( $templates[ $editing ] ) ) : ?>
        <?php
        $tpl        = $templates[ $editing ];
        $custom     = isset( $saved_templates[ $editing ] ) ? $saved_templates[ $editing ] : array();
        $cur_subj   = ! empty( $custom['subject'] ) ? $custom['subject'] : '';
        $cur_body   = ! empty( $custom['body'] ) ? $custom['body'] : '';
        $is_custom  = ! empty( $custom['subject'] ) || ! empty( $custom['body'] );
        ?>
        <div style="margin-top: 16px; max-width: 780px;">
            <h2><?php echo esc_html( $tpl['label'] ); ?></h2>
            <p class="description"><?php echo esc_html( $tpl['description'] ); ?></p>

            <form method="post">
                <?php wp_nonce_field( 'cd_save_email_template' ); ?>
                <input type="hidden" name="cd_template_key" value="<?php echo esc_attr( $editing ); ?>">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="cd_template_subject"><?php esc_html_e( 'Subject', 'community-directory' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="cd_template_subject" id="cd_template_subject" class="large-text"
                                   value="<?php echo esc_attr( $cur_subj ); ?>"
                                   placeholder="<?php echo esc_attr( $tpl['subject_default'] ); ?>">
                            <p class="description">
                                <?php esc_html_e( 'Default:', 'community-directory' ); ?>
                                <code><?php echo esc_html( $tpl['subject_default'] ); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cd_template_body"><?php esc_html_e( 'Body', 'community-directory' ); ?></label>
                        </th>
                        <td>
                            <textarea name="cd_template_body" id="cd_template_body" rows="14" class="large-text" style="font-family: monospace;"
                                      placeholder="<?php echo esc_attr( $tpl['body_default'] ); ?>"><?php echo esc_textarea( $cur_body ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'Available variables:', 'community-directory' ); ?>
                                <?php foreach ( $tpl['variables'] as $var ) : ?>
                                    <code><?php echo esc_html( $var ); ?></code>
                                <?php endforeach; ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="cd_save_email_template" class="button button-primary">
                        <?php esc_html_e( 'Save Template', 'community-directory' ); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cd-email-templates' ) ); ?>" class="button">
                        <?php esc_html_e( 'Back to List', 'community-directory' ); ?>
                    </a>
                </p>
            </form>

            <?php if ( $is_custom ) : ?>
                <hr>
                <form method="post" style="margin-top: 8px;">
                    <?php wp_nonce_field( 'cd_reset_email_template' ); ?>
                    <input type="hidden" name="cd_template_key" value="<?php echo esc_attr( $editing ); ?>">
                    <button type="submit" name="cd_reset_email_template" class="button button-link-delete"
                            onclick="return confirm('<?php echo esc_js( __( 'Reset this template to the default? Your customizations will be lost.', 'community-directory' ) ); ?>');">
                        <?php esc_html_e( 'Reset to Default', 'community-directory' ); ?>
                    </button>
                </form>
            <?php endif; ?>

            <div style="margin-top: 24px; padding: 12px 16px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h4 style="margin: 0 0 8px;"><?php esc_html_e( 'Default Template Preview', 'community-directory' ); ?></h4>
                <pre style="white-space: pre-wrap; font-size: 13px; margin: 0; background: #fff; padding: 12px; border: 1px solid #dcdcde;"><?php echo esc_html( $tpl['body_default'] ); ?></pre>
            </div>
        </div>

    <?php else : ?>
        <!-- Templates List -->
        <table class="widefat striped" style="margin-top: 16px; max-width: 900px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Template', 'community-directory' ); ?></th>
                    <th><?php esc_html_e( 'Subject', 'community-directory' ); ?></th>
                    <th style="width: 100px;"><?php esc_html_e( 'Status', 'community-directory' ); ?></th>
                    <th style="width: 80px;"><?php esc_html_e( 'Actions', 'community-directory' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $templates as $key => $tpl ) :
                    $is_custom = isset( $saved_templates[ $key ] ) && ( ! empty( $saved_templates[ $key ]['subject'] ) || ! empty( $saved_templates[ $key ]['body'] ) );
                    $edit_url  = admin_url( 'admin.php?page=cd-email-templates&edit=' . $key );
                ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $tpl['label'] ); ?></a></strong>
                            <br><span class="description"><?php echo esc_html( $tpl['description'] ); ?></span>
                        </td>
                        <td><code style="font-size: 12px;"><?php echo esc_html( $is_custom && ! empty( $saved_templates[ $key ]['subject'] ) ? $saved_templates[ $key ]['subject'] : $tpl['subject_default'] ); ?></code></td>
                        <td>
                            <?php if ( $is_custom ) : ?>
                                <span style="color: #2271b1; font-weight: 600;"><?php esc_html_e( 'Customized', 'community-directory' ); ?></span>
                            <?php else : ?>
                                <span style="color: #787c82;"><?php esc_html_e( 'Default', 'community-directory' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'community-directory' ); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 24px; padding: 12px 16px; background: #fcf9e8; border-left: 4px solid #dba617; max-width: 900px;">
            <h4 style="margin: 0 0 4px;"><?php esc_html_e( 'How It Works', 'community-directory' ); ?></h4>
            <p style="margin: 0;"><?php esc_html_e( 'Customize any template by clicking "Edit". Use the placeholder variables (shown in each template editor) to insert dynamic content. Leave fields empty to use the built-in default. All emails are sent as plain text.', 'community-directory' ); ?></p>
        </div>
    <?php endif; ?>
</div>
