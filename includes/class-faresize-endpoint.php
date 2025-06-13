<?php
/**
 * Custom endpoint functionality for Faresize Ultimate Dashboard Features.
 *
 * @package Faresize\UltimateDashboard
 */

namespace Faresize\UltimateDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Faresize_Endpoint
 *
 * Manages custom endpoints for the My Account page.
 */
class Faresize_Endpoint {
    /**
     * Constructor.
     */
    public function __construct() {
        // Register endpoints on wp_loaded for early execution
        add_action( 'wp_loaded', [ $this, 'add_endpoints' ], 10 );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_items' ], 20 );
        // Handle template loading directly
        add_action( 'woocommerce_account_available-plans_endpoint', [ $this, 'load_available_plans_template' ] );
        add_action( 'woocommerce_account_notifications_endpoint', [ $this, 'load_notifications_template' ] );
        // Admin notice for manual flush
        add_action( 'admin_notices', [ $this, 'flush_rewrite_notice' ] );
        // One-time flush on plugin activation
        register_activation_hook( plugin_dir_path( __DIR__ ) . 'faresize-ultimate-dashboard-features.php', [ $this, 'flush_rewrite_rules' ] );
        \Faresize_Ultimate_Dashboard::log( 'Endpoint class initialized' );
    }

    /**
     * Register custom endpoints.
     */
    public function add_endpoints() {
        add_rewrite_endpoint( 'available-plans', EP_PAGES );
        add_rewrite_endpoint( 'notifications', EP_PAGES );
        \Faresize_Ultimate_Dashboard::log( 'Registered Available Plans and Notifications endpoints' );
    }

    /**
     * Flush rewrite rules on plugin activation or manual trigger.
     */
    public function flush_rewrite_rules() {
        $this->add_endpoints();
        flush_rewrite_rules();
        \Faresize_Ultimate_Dashboard::log( 'Flushed rewrite rules' );
    }

    /**
     * Display admin notice to manually flush rewrite rules.
     */
    public function flush_rewrite_notice() {
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['faresize_flush_rewrites'] ) ) {
            return;
        }
        $this->flush_rewrite_rules();
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Rewrite rules flushed successfully for Faresize endpoints.', 'faresize-ultimate-dashboard' ); ?></p>
        </div>
        <?php
    }

    /**
     * Add custom endpoints to My Account menu.
     *
     * @param array $items The existing menu items.
     * @return array Modified menu items.
     */
    public function add_menu_items( array $items ) {
        $new_items = [];
        foreach ( $items as $key => $item ) {
            $new_items[ $key ] = $item;
            if ( $key === 'dashboard' ) {
                $new_items['available-plans'] = __( 'Available Plans', 'woocommerce' );
                $new_items['notifications'] = __( 'Notifications', 'woocommerce' );
            }
        }
        \Faresize_Ultimate_Dashboard::log( 'Added Available Plans and Notifications to My Account menu' );
        return $new_items;
    }

    /**
     * Load Available Plans template.
     */
    public function load_available_plans_template() {
        $template = locate_template( 'woocommerce/myaccount/available-plans.php' );
        if ( $template ) {
            \Faresize_Ultimate_Dashboard::log( 'Loading Available Plans template: ' . $template );
            wc_get_template( 'myaccount/available-plans.php' );
        } else {
            \Faresize_Ultimate_Dashboard::log( 'Available Plans template not found' );
            wc_get_template_html( '<p>' . __( 'Available Plans template missing.', 'woocommerce' ) . '</p>' );
        }
    }

    /**
     * Load Notifications template.
     */
    public function load_notifications_template() {
        $template = locate_template( 'woocommerce/myaccount/notifications.php' );
        if ( $template ) {
            \Faresize_Ultimate_Dashboard::log( 'Loading Notifications template: ' . $template );
            wc_get_template( 'myaccount/notifications.php' );
        } else {
            \Faresize_Ultimate_Dashboard::log( 'Notifications template not found' );
            wc_get_template_html( '<p>' . __( 'Notifications template missing.', 'woocommerce' ) . '</p>' );
        }
    }
}
?>