<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Google OAuth feedback messages
if ( isset( $_GET['google_connected'] ) ) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Google account connected successfully!', 'community-directory' ) . '</p></div>';
}
if ( isset( $_GET['google_error'] ) ) {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Google connection error: ', 'community-directory' ) . esc_html( sanitize_text_field( $_GET['google_error'] ) ) . '</p></div>';
}

$google_status = class_exists( 'CD_Google_Contacts' ) ? CD_Google_Contacts::get_status() : array( 'status' => 'not_configured' );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Community Directory Settings', 'community-directory' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'cd_general_settings' ); ?>

        <h2><?php esc_html_e( 'General', 'community-directory' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cd_menu_label"><?php esc_html_e( 'Menu Label', 'community-directory' ); ?></label>
                </th>
                <td>
                    <input type="text" id="cd_menu_label" name="cd_menu_label"
                           value="<?php echo esc_attr( get_option( 'cd_menu_label', 'Community' ) ); ?>"
                           class="regular-text">
                    <p class="description"><?php esc_html_e( 'The label shown in the site navigation menu.', 'community-directory' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cd_base_slug"><?php esc_html_e( 'URL Slug', 'community-directory' ); ?></label>
                </th>
                <td>
                    <code><?php echo esc_html( home_url( '/' ) ); ?></code>
                    <input type="text" id="cd_base_slug" name="cd_base_slug"
                           value="<?php echo esc_attr( get_option( 'cd_base_slug', 'community' ) ); ?>"
                           class="regular-text" style="width: 150px;">
                    <code>/</code>
                    <p class="description"><?php esc_html_e( 'The base URL path for the directory pages. Changing this requires saving permalinks.', 'community-directory' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e( 'Menu Visibility', 'community-directory' ); ?>
                </th>
                <td>
                    <label class="cd-toggle-switch">
                        <input type="hidden" name="cd_menu_visible" value="0">
                        <input type="checkbox" name="cd_menu_visible" value="1"
                            <?php checked( get_option( 'cd_menu_visible', '1' ), '1' ); ?>>
                        <span><?php esc_html_e( 'Show Community menu in site navigation', 'community-directory' ); ?></span>
                    </label>
                    <p class="description">
                        <?php
                        $is_visible = get_option( 'cd_menu_visible', '1' ) === '1';
                        printf(
                            '<strong>%s</strong> %s',
                            esc_html__( 'Status:', 'community-directory' ),
                            $is_visible
                                ? esc_html__( 'Community menu is Visible on your site.', 'community-directory' )
                                : esc_html__( 'Community menu is Hidden. Members can still access it directly via URL.', 'community-directory' )
                        );
                        ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Google Contacts Integration', 'community-directory' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Connect to Google to automatically sync approved members as workspace contacts visible to all accounts.', 'community-directory' ); ?></p>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Connection Status', 'community-directory' ); ?></th>
                <td>
                    <?php
                    $status_labels = array(
                        'connected'      => '<span style="color:#16a34a;font-weight:600;">&#9679; ' . esc_html__( 'Connected', 'community-directory' ) . '</span>',
                        'needs_auth'     => '<span style="color:#d97706;font-weight:600;">&#9679; ' . esc_html__( 'Credentials saved — authorization needed', 'community-directory' ) . '</span>',
                        'error'          => '<span style="color:#dc2626;font-weight:600;">&#9679; ' . esc_html__( 'Error — check credentials', 'community-directory' ) . '</span>',
                        'not_configured' => '<span style="color:#64748b;">&#9679; ' . esc_html__( 'Not configured', 'community-directory' ) . '</span>',
                    );
                    echo $status_labels[ $google_status['status'] ] ?? $status_labels['not_configured'];

                    if ( $google_status['pending_retries'] > 0 ) {
                        printf(
                            ' &mdash; <span style="color:#d97706;">%d %s</span>',
                            $google_status['pending_retries'],
                            esc_html__( 'pending retries', 'community-directory' )
                        );
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cd_google_client_id"><?php esc_html_e( 'Client ID', 'community-directory' ); ?></label>
                </th>
                <td>
                    <input type="text" id="cd_google_client_id" name="cd_google_client_id"
                           value="<?php echo esc_attr( get_option( 'cd_google_client_id', '' ) ); ?>"
                           class="large-text"
                           placeholder="xxxx.apps.googleusercontent.com">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cd_google_client_secret"><?php esc_html_e( 'Client Secret', 'community-directory' ); ?></label>
                </th>
                <td>
                    <?php $has_secret = ! empty( get_option( 'cd_google_client_secret', '' ) ); ?>
                    <input type="password" id="cd_google_client_secret" name="cd_google_client_secret_raw"
                           value=""
                           class="regular-text"
                           placeholder="<?php echo $has_secret ? esc_attr__( '(saved — enter new value to change)', 'community-directory' ) : ''; ?>">
                    <p class="description"><?php esc_html_e( 'The client secret is encrypted before storage. Leave blank to keep existing value.', 'community-directory' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Authorize', 'community-directory' ); ?></th>
                <td>
                    <?php if ( $google_status['has_credentials'] ) : ?>
                        <?php if ( 'connected' === $google_status['status'] ) : ?>
                            <p>
                                <span style="color:#16a34a;">&#10003;</span>
                                <?php esc_html_e( 'Google account is authorized and connected.', 'community-directory' ); ?>
                            </p>
                            <a href="<?php echo esc_url( CD_Google_Contacts::get_auth_url() ); ?>" class="button">
                                <?php esc_html_e( 'Re-authorize', 'community-directory' ); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url( CD_Google_Contacts::get_auth_url() ); ?>" class="button button-primary">
                                <?php esc_html_e( 'Connect to Google', 'community-directory' ); ?>
                            </a>
                            <p class="description">
                                <?php esc_html_e( 'Use a shared workspace account (e.g., sainttheklachurch@gmail.com) so contacts are visible to all workspace users.', 'community-directory' ); ?>
                            </p>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'Enter Client ID and Client Secret above, save settings, then authorize.', 'community-directory' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Sync Options', 'community-directory' ); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="hidden" name="cd_google_sync_enabled" value="0">
                            <input type="checkbox" name="cd_google_sync_enabled" value="1"
                                <?php checked( get_option( 'cd_google_sync_enabled', '0' ), '1' ); ?>>
                            <?php esc_html_e( 'Enable Google Contacts sync', 'community-directory' ); ?>
                        </label>
                        <br>
                        <label>
                            <input type="hidden" name="cd_google_export_on_approval" value="0">
                            <input type="checkbox" name="cd_google_export_on_approval" value="1"
                                <?php checked( get_option( 'cd_google_export_on_approval', '1' ), '1' ); ?>>
                            <?php esc_html_e( 'Automatically create Google Contact when a member is approved', 'community-directory' ); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cd_google_contact_group"><?php esc_html_e( 'Contact Group', 'community-directory' ); ?></label>
                </th>
                <td>
                    <input type="text" id="cd_google_contact_group" name="cd_google_contact_group"
                           value="<?php echo esc_attr( get_option( 'cd_google_contact_group', 'St. Thekla Members' ) ); ?>"
                           class="regular-text">
                    <p class="description"><?php esc_html_e( 'The label/group to tag contacts with in Google.', 'community-directory' ); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'PWA / App Install', 'community-directory' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Allow members to install the directory as a home-screen app on their phone. Requires HTTPS.', 'community-directory' ); ?></p>

        <?php
        $pwa_enabled  = get_option( 'cd_pwa_enabled', '0' );
        $pwa_icon_id  = (int) get_option( 'cd_pwa_icon_id', 0 );
        $pwa_icons    = class_exists( 'CD_PWA' ) ? CD_PWA::get_icon_urls() : array();
        $is_ssl       = is_ssl();
        ?>

        <?php if ( ! $is_ssl ) : ?>
            <div class="notice notice-warning inline" style="margin:0 0 16px;">
                <p><strong><?php esc_html_e( 'HTTPS Required:', 'community-directory' ); ?></strong>
                <?php esc_html_e( 'PWA features (service worker, install prompt) require your site to be served over HTTPS. Enable SSL first.', 'community-directory' ); ?></p>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable PWA', 'community-directory' ); ?></th>
                <td>
                    <label class="cd-toggle-switch">
                        <input type="hidden" name="cd_pwa_enabled" value="0">
                        <input type="checkbox" name="cd_pwa_enabled" value="1"
                            <?php checked( $pwa_enabled, '1' ); ?>>
                        <span><?php esc_html_e( 'Enable Progressive Web App features', 'community-directory' ); ?></span>
                    </label>
                    <p class="description">
                        <?php
                        if ( '1' === $pwa_enabled ) {
                            echo '<span style="color:#16a34a;font-weight:600;">&#9679; ' . esc_html__( 'PWA is active', 'community-directory' ) . '</span>';
                            echo ' &mdash; ' . esc_html__( 'Version', 'community-directory' ) . ' ' . esc_html( CD_VERSION );
                        } else {
                            echo '<span style="color:#64748b;">&#9679; ' . esc_html__( 'PWA is disabled', 'community-directory' ) . '</span>';
                        }
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cd_pwa_app_name"><?php esc_html_e( 'App Name', 'community-directory' ); ?></label>
                </th>
                <td>
                    <input type="text" id="cd_pwa_app_name" name="cd_pwa_app_name"
                           value="<?php echo esc_attr( get_option( 'cd_pwa_app_name', 'St. Thekla Directory' ) ); ?>"
                           class="regular-text">
                    <p class="description"><?php esc_html_e( 'Full name shown on the splash screen and install dialog.', 'community-directory' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cd_pwa_short_name"><?php esc_html_e( 'Short Name', 'community-directory' ); ?></label>
                </th>
                <td>
                    <input type="text" id="cd_pwa_short_name" name="cd_pwa_short_name"
                           value="<?php echo esc_attr( get_option( 'cd_pwa_short_name', 'St. Thekla' ) ); ?>"
                           class="regular-text" style="width: 200px;" maxlength="12">
                    <p class="description"><?php esc_html_e( 'Short label shown below the home screen icon (max 12 characters).', 'community-directory' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cd_pwa_theme_color"><?php esc_html_e( 'Theme Color', 'community-directory' ); ?></label>
                </th>
                <td>
                    <input type="color" id="cd_pwa_theme_color" name="cd_pwa_theme_color"
                           value="<?php echo esc_attr( get_option( 'cd_pwa_theme_color', '#8B0000' ) ); ?>"
                           style="width: 60px; height: 36px; padding: 2px; cursor: pointer;">
                    <code id="cd-pwa-theme-color-hex"><?php echo esc_html( get_option( 'cd_pwa_theme_color', '#8B0000' ) ); ?></code>
                    <p class="description"><?php esc_html_e( 'Browser address bar and status bar color. Matches your church brand.', 'community-directory' ); ?></p>
                    <script>
                    document.getElementById('cd_pwa_theme_color').addEventListener('input', function(e) {
                        document.getElementById('cd-pwa-theme-color-hex').textContent = e.target.value;
                    });
                    </script>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cd_pwa_background_color"><?php esc_html_e( 'Background Color', 'community-directory' ); ?></label>
                </th>
                <td>
                    <input type="color" id="cd_pwa_background_color" name="cd_pwa_background_color"
                           value="<?php echo esc_attr( get_option( 'cd_pwa_background_color', '#FFFFFF' ) ); ?>"
                           style="width: 60px; height: 36px; padding: 2px; cursor: pointer;">
                    <code id="cd-pwa-bg-color-hex"><?php echo esc_html( get_option( 'cd_pwa_background_color', '#FFFFFF' ) ); ?></code>
                    <p class="description"><?php esc_html_e( 'Splash screen background color while the app loads.', 'community-directory' ); ?></p>
                    <script>
                    document.getElementById('cd_pwa_background_color').addEventListener('input', function(e) {
                        document.getElementById('cd-pwa-bg-color-hex').textContent = e.target.value;
                    });
                    </script>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'App Icon', 'community-directory' ); ?></th>
                <td>
                    <div id="cd-pwa-icon-preview" style="margin-bottom: 12px;">
                        <?php if ( ! empty( $pwa_icons[192] ) ) : ?>
                            <div style="display:inline-flex; align-items:center; gap:16px;">
                                <img src="<?php echo esc_url( $pwa_icons[192] ); ?>" alt="App icon" style="width:96px;height:96px;border-radius:20px;box-shadow:0 2px 8px rgba(0,0,0,0.15);">
                                <div>
                                    <strong><?php echo esc_html( get_option( 'cd_pwa_short_name', 'St. Thekla' ) ); ?></strong><br>
                                    <span style="color:#666;font-size:0.85em;"><?php echo count( $pwa_icons ); ?> <?php esc_html_e( 'sizes generated', 'community-directory' ); ?></span>
                                </div>
                            </div>
                        <?php else : ?>
                            <p style="color:#999;"><?php esc_html_e( 'No icon uploaded yet. Upload a PNG image (minimum 512x512 pixels).', 'community-directory' ); ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="cd-pwa-upload-btn" class="button">
                        <?php echo ! empty( $pwa_icons ) ? esc_html__( 'Change Icon', 'community-directory' ) : esc_html__( 'Upload Icon', 'community-directory' ); ?>
                    </button>
                    <span id="cd-pwa-upload-status" style="margin-left:8px;"></span>
                    <p class="description"><?php esc_html_e( 'Upload your church logo (PNG, minimum 512x512 pixels). All required sizes will be generated automatically.', 'community-directory' ); ?></p>

                    <script>
                    jQuery(function($) {
                        var frame;
                        $('#cd-pwa-upload-btn').on('click', function(e) {
                            e.preventDefault();
                            if (frame) { frame.open(); return; }
                            frame = wp.media({
                                title: '<?php echo esc_js( __( 'Select App Icon', 'community-directory' ) ); ?>',
                                button: { text: '<?php echo esc_js( __( 'Use as App Icon', 'community-directory' ) ); ?>' },
                                library: { type: 'image' },
                                multiple: false
                            });
                            frame.on('select', function() {
                                var attachment = frame.state().get('selection').first().toJSON();
                                $('#cd-pwa-upload-status').text('Generating icon sizes...');
                                $.post(ajaxurl, {
                                    action: 'cd_pwa_upload_icon',
                                    attachment_id: attachment.id,
                                    nonce: '<?php echo wp_create_nonce( 'cd_pwa_icon_upload' ); ?>'
                                }, function(response) {
                                    if (response.success) {
                                        $('#cd-pwa-upload-status').text(response.data.message).css('color', '#16a34a');
                                        // Refresh preview
                                        var urls = response.data.urls;
                                        var previewUrl = urls['192'] || urls['512'] || '';
                                        if (previewUrl) {
                                            $('#cd-pwa-icon-preview').html(
                                                '<div style="display:inline-flex;align-items:center;gap:16px;">' +
                                                '<img src="' + previewUrl + '?t=' + Date.now() + '" style="width:96px;height:96px;border-radius:20px;box-shadow:0 2px 8px rgba(0,0,0,0.15);">' +
                                                '<div><strong>' + $('#cd_pwa_short_name').val() + '</strong><br><span style="color:#666;font-size:0.85em;">' + response.data.sizes.length + ' sizes generated</span></div>' +
                                                '</div>'
                                            );
                                        }
                                    } else {
                                        $('#cd-pwa-upload-status').text(response.data.message).css('color', '#dc2626');
                                    }
                                });
                            });
                            frame.open();
                        });
                    });
                    </script>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
