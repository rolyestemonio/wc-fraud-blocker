<?php
/**
 * Plugin Name:       WC Fraud Blocker
 * Plugin URI:        https://github.com/rolyestemonio/wc-fraud-blocker
 * Description:       Block fraudulent customers by email and shipping/billing address in WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Roly Estemonio
 * Author URI:        https://rolyestemonio.website
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-fraud-blocker
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WCFB_VERSION',     '1.0.0' );
define( 'WCFB_PLUGIN_FILE', __FILE__ );
define( 'WCFB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WCFB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/**
 * Check WooCommerce is active before loading the plugin.
 */
function wcfb_check_woocommerce(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'WC Fraud Blocker requires WooCommerce to be installed and active.', 'wc-fraud-blocker' )
                . '</p></div>';
        } );
        deactivate_plugins( plugin_basename( WCFB_PLUGIN_FILE ) );
    }
}
add_action( 'admin_init', 'wcfb_check_woocommerce' );

/**
 * Boot the plugin after all plugins are loaded.
 */
function wcfb_init(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    require_once WCFB_PLUGIN_DIR . 'includes/class-wcfb-store.php';
    require_once WCFB_PLUGIN_DIR . 'includes/class-wcfb-blocker.php';
    require_once WCFB_PLUGIN_DIR . 'includes/class-wcfb-admin.php';
    require_once WCFB_PLUGIN_DIR . 'includes/class-wcfb-ajax.php';

    WCFB_Blocker::instance()->register();
    WCFB_Admin::instance()->register();
    WCFB_Ajax::instance()->register();
}
add_action( 'plugins_loaded', 'wcfb_init' );

/**
 * Create option defaults on activation.
 */
register_activation_hook( WCFB_PLUGIN_FILE, function () {
    if ( false === get_option( 'wcfb_blocked_emails' ) ) {
        add_option( 'wcfb_blocked_emails', [], '', 'no' );
    }
    if ( false === get_option( 'wcfb_blocked_addresses' ) ) {
        add_option( 'wcfb_blocked_addresses', [], '', 'no' );
    }
} );
