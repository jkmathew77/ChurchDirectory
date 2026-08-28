<?php
/**
 * Critical public styles that must render even when the hosting layer blocks
 * direct requests to the plugin stylesheet.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class STC_Critical_Styles {

    public static function init() {
        add_action( 'wp_head', array( __CLASS__, 'render' ), 100 );
    }

    public static function render() {
        if ( is_admin() ) {
            return;
        }
        ?>
        <style id="stc-critical-inline-css">
        .stc-visit-us-compact .stc-visit-image-wrap {
            text-align: center !important;
        }
        .stc-visit-us-compact .stc-visit-image {
            display: block !important;
            width: auto !important;
            max-width: 100% !important;
            height: auto !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }
        .stc-location a[href="tel:"],
        .stc-location a[href="tel:+"] {
            display: none !important;
        }
        </style>
        <?php
    }
}
