<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
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

        <?php submit_button(); ?>
    </form>
</div>
