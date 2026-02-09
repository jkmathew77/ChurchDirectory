<?php
/**
 * Centralized email composition for all plugin notifications.
 * Each static method builds the subject + body and calls wp_mail().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CD_Email_Templates {

    /**
     * Send an invite email to an approved applicant.
     *
     * @param string $email     Recipient email.
     * @param string $first_name Recipient first name.
     * @param string $token     Raw invite token (unhashed).
     * @return bool Whether wp_mail succeeded.
     */
    public static function send_invite( $email, $first_name, $token ) {
        $base_slug  = get_option( 'cd_base_slug', 'community' );
        $invite_url = home_url( $base_slug . '/invite/' . rawurlencode( base64_encode( $email ) ) . '/?token=' . $token );
        $expiry     = (int) get_option( 'cd_invite_expiry', 14 );

        $subject = __( 'Welcome to St. Thekla Community Directory — Set Up Your Account', 'community-directory' );

        $message = sprintf(
            /* translators: 1: first name, 2: invite URL, 3: days until expiry */
            __(
                "Hello %1\$s,\n\n" .
                "Great news! Your membership application has been approved.\n\n" .
                "Please click the link below to create your account and access the community directory:\n\n" .
                "%2\$s\n\n" .
                "This invitation link will expire in %3\$d days.\n\n" .
                "If you have any questions, please contact the church office.\n\n" .
                "God bless,\n" .
                "St. Thekla Malankara Orthodox Church",
                'community-directory'
            ),
            esc_html( $first_name ),
            esc_url( $invite_url ),
            $expiry
        );

        return wp_mail( $email, $subject, $message );
    }

    /**
     * Send a rejection notification to an applicant.
     *
     * @param string $email          Recipient email.
     * @param string $first_name     Recipient first name.
     * @param string $rejection_reason One of: incomplete, not_recognized, duplicate, other.
     * @param string $notes          Optional notes from reviewer.
     * @return bool Whether wp_mail succeeded.
     */
    public static function send_rejection( $email, $first_name, $rejection_reason, $notes = '' ) {
        $subject = __( 'St. Thekla Community Directory — Application Update', 'community-directory' );

        $reason_labels = array(
            'incomplete'     => __( 'Your application was incomplete. Please resubmit with all required information.', 'community-directory' ),
            'not_recognized' => __( 'We were unable to verify your membership with the church. Please contact the church office for assistance.', 'community-directory' ),
            'duplicate'      => __( 'An account with your information already exists in the directory.', 'community-directory' ),
            'other'          => __( 'Please contact the church office for more information.', 'community-directory' ),
        );

        $reason_text = isset( $reason_labels[ $rejection_reason ] ) ? $reason_labels[ $rejection_reason ] : $reason_labels['other'];

        $message = sprintf(
            /* translators: 1: first name, 2: reason text */
            __(
                "Hello %1\$s,\n\n" .
                "Thank you for your interest in the St. Thekla Community Directory.\n\n" .
                "After review, we were unable to approve your application at this time.\n\n" .
                "%2\$s\n\n" .
                "If you believe this is an error or have questions, please reach out to the church office.\n\n" .
                "God bless,\n" .
                "St. Thekla Malankara Orthodox Church",
                'community-directory'
            ),
            esc_html( $first_name ),
            $reason_text
        );

        return wp_mail( $email, $subject, $message );
    }

    /**
     * Send a "more information needed" email to an applicant.
     *
     * @param string $email      Recipient email.
     * @param string $first_name Recipient first name.
     * @param string $message_text Custom message from the reviewer.
     * @return bool Whether wp_mail succeeded.
     */
    public static function send_info_request( $email, $first_name, $message_text ) {
        $subject = __( 'St. Thekla Community Directory — Additional Information Needed', 'community-directory' );

        $message = sprintf(
            /* translators: 1: first name, 2: custom message */
            __(
                "Hello %1\$s,\n\n" .
                "Thank you for applying to the St. Thekla Community Directory. We need a bit more information before we can process your application.\n\n" .
                "%2\$s\n\n" .
                "Please reply to this email or contact the church office with the requested information.\n\n" .
                "God bless,\n" .
                "St. Thekla Malankara Orthodox Church",
                'community-directory'
            ),
            esc_html( $first_name ),
            esc_html( $message_text )
        );

        return wp_mail( $email, $subject, $message );
    }

    /**
     * Send notification when someone is added as a church officer.
     *
     * @param string $email      Officer email.
     * @param string $first_name Officer first name.
     * @param string $title      Officer title/role (e.g., "Secretary").
     * @return bool Whether wp_mail succeeded.
     */
    public static function send_officer_added( $email, $first_name, $title = '' ) {
        $subject = __( 'St. Thekla Community Directory — Officer Role Assigned', 'community-directory' );

        $title_line = '';
        if ( ! empty( $title ) ) {
            $title_line = sprintf(
                __( "Role: %s\n", 'community-directory' ),
                esc_html( $title )
            );
        }

        $message = sprintf(
            /* translators: 1: first name, 2: title line (may be empty) */
            __(
                "Hello %1\$s,\n\n" .
                "You have been assigned as an officer of the St. Thekla Community Directory.\n\n" .
                "%2\$s" .
                "As an officer, you will:\n" .
                "- Receive notifications for new membership applications\n" .
                "- Have access to review and manage applications\n" .
                "- Be able to manage directory settings\n\n" .
                "Thank you for your service to the community.\n\n" .
                "God bless,\n" .
                "St. Thekla Malankara Orthodox Church",
                'community-directory'
            ),
            esc_html( $first_name ),
            $title_line
        );

        return wp_mail( $email, $subject, $message );
    }

    /**
     * Send notification when someone is removed as a church officer.
     *
     * @param string $email      Former officer email.
     * @param string $first_name Former officer first name.
     * @return bool Whether wp_mail succeeded.
     */
    public static function send_officer_removed( $email, $first_name ) {
        $subject = __( 'St. Thekla Community Directory — Officer Role Update', 'community-directory' );

        $message = sprintf(
            /* translators: 1: first name */
            __(
                "Hello %1\$s,\n\n" .
                "This is to let you know that your officer role in the St. Thekla Community Directory has been updated. " .
                "You are no longer listed as an active officer.\n\n" .
                "You will continue to have full access to the community directory as a member.\n\n" .
                "Thank you for your service.\n\n" .
                "God bless,\n" .
                "St. Thekla Malankara Orthodox Church",
                'community-directory'
            ),
            esc_html( $first_name )
        );

        return wp_mail( $email, $subject, $message );
    }

    /**
     * Send a password reset email.
     *
     * @param string $email Recipient email.
     * @param string $name  Recipient display name.
     * @param string $token Raw reset token (unhashed).
     * @return bool Whether wp_mail succeeded.
     */
    public static function send_password_reset( $email, $name, $token ) {
        $base_slug = get_option( 'cd_base_slug', 'community' );
        $reset_url = home_url( $base_slug . '/login/?reset_token=' . $token );

        $subject = __( 'Password Reset — St. Thekla Community Directory', 'community-directory' );

        $message = sprintf(
            __(
                "Hello %1\$s,\n\n" .
                "We received a request to reset your password for the St. Thekla Community Directory.\n\n" .
                "Click here to reset your password:\n%2\$s\n\n" .
                "This link will expire in 1 hour.\n\n" .
                "If you did not request this, you can safely ignore this email.\n\n" .
                "God bless,\n" .
                "St. Thekla Malankara Orthodox Church",
                'community-directory'
            ),
            esc_html( $name ),
            esc_url( $reset_url )
        );

        return wp_mail( $email, $subject, $message );
    }

    /**
     * Send an email hint to help a member recover their login email.
     *
     * @param string $email      Recipient email.
     * @param string $first_name Recipient first name.
     * @param string $masked     Masked email (e.g., "jo***@gmail.com").
     * @return bool Whether wp_mail succeeded.
     */
    public static function send_email_hint( $email, $first_name, $masked ) {
        $base_slug = get_option( 'cd_base_slug', 'community' );
        $login_url = home_url( $base_slug . '/login/' );

        $subject = __( 'Your Account Email — St. Thekla Community Directory', 'community-directory' );

        $message = sprintf(
            __(
                "Hello %1\$s,\n\n" .
                "You requested help finding your account email for the St. Thekla Community Directory.\n\n" .
                "Your account email is: %2\$s\n\n" .
                "You can log in here: %3\$s\n\n" .
                "If you did not request this, you can safely ignore this email.\n\n" .
                "God bless,\n" .
                "St. Thekla Malankara Orthodox Church",
                'community-directory'
            ),
            esc_html( $first_name ),
            $masked,
            esc_url( $login_url )
        );

        return wp_mail( $email, $subject, $message );
    }

    /**
     * Notify officers about a newly approved member (after invite is sent).
     *
     * @param array $member_data Member info: first_name, last_name, email.
     * @param string $approved_by Name of the approver.
     * @return void
     */
    public static function notify_officers_of_approval( $member_data, $approved_by ) {
        global $wpdb;

        $officers_table = CD_Database::table( 'officers' );
        $officer_emails = $wpdb->get_col(
            "SELECT email FROM {$officers_table} WHERE is_active = 1"
        );

        if ( empty( $officer_emails ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: 1: first name, 2: last name */
            __( 'Application Approved: %1$s %2$s', 'community-directory' ),
            $member_data['first_name'],
            $member_data['last_name']
        );

        $message = sprintf(
            /* translators: 1: first name, 2: last name, 3: email, 4: approved by */
            __(
                "A membership application has been approved.\n\n" .
                "Name: %1\$s %2\$s\n" .
                "Email: %3\$s\n" .
                "Approved by: %4\$s\n\n" .
                "An invitation email has been sent to the applicant.\n\n" .
                "St. Thekla Community Directory",
                'community-directory'
            ),
            $member_data['first_name'],
            $member_data['last_name'],
            $member_data['email'],
            $approved_by
        );

        foreach ( $officer_emails as $email ) {
            wp_mail( $email, $subject, $message );
        }
    }
}
