<?php
/**
 * WP-CLI eval-file script: reconcile WordPress users with Community Directory.
 * Output is CSV. Run only from the command line and redirect to a private path.
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "This script must be run with WP-CLI.\n" );
    exit( 1 );
}

global $wpdb;

$members_table = $wpdb->prefix . 'cd_members';
$table_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $members_table ) ) === $members_table;

$handle = fopen( 'php://output', 'w' );
fputcsv(
    $handle,
    array(
        'user_id',
        'user_login',
        'user_email',
        'registered_utc',
        'roles',
        'cd_member',
        'cd_officer',
        'cd_secretary',
        'cd_admin',
        'directory_member_id',
        'directory_status',
    )
);

$user_ids = get_users(
    array(
        'fields'  => 'ID',
        'orderby' => 'ID',
        'order'   => 'ASC',
    )
);

foreach ( $user_ids as $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        continue;
    }

    $member_id     = '';
    $member_status = '';

    if ( $table_exists ) {
        $member = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status FROM {$members_table} WHERE wp_user_id = %d LIMIT 1",
                $user_id
            )
        );

        if ( $member ) {
            $member_id     = (string) $member->id;
            $member_status = (string) $member->status;
        }
    }

    fputcsv(
        $handle,
        array(
            $user->ID,
            $user->user_login,
            $user->user_email,
            $user->user_registered,
            implode( '|', (array) $user->roles ),
            user_can( $user, 'cd_member' ) ? '1' : '0',
            user_can( $user, 'cd_officer' ) ? '1' : '0',
            user_can( $user, 'cd_secretary' ) ? '1' : '0',
            user_can( $user, 'cd_admin' ) ? '1' : '0',
            $member_id,
            $member_status,
        )
    );
}

fclose( $handle );
