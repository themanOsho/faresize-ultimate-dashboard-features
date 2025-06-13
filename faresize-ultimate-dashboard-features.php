<?php
/**
 * Plugin Name: Faresize Ultimate Dashboard Features
 * Plugin URI: https://faresize.com
 * Description: Custom My Account page features for Faresize, including affiliate codes, loyalty levels, referral system, custom endpoints, and coupon restrictions.
 * Version: 1.0.5
 * Author: Faresize
 * Author URI: https://faresize.com
 * License: GPL-2.0+
 * Text Domain: faresize-ultimate-dashboard
 * Domain Path: /languages
 *
 * @package Faresize\UltimateDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class to initialize features.
 */
class Faresize_Ultimate_Dashboard {
    /**
     * Plugin instance.
     *
     * @var Faresize_Ultimate_Dashboard
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Faresize_Ultimate_Dashboard
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'init', [ $this, 'load_classes' ], 20 );
        add_action( 'init', [ $this, 'init_classes' ], 30 );
    }

    /**
     * Load feature classes.
     */
    public function load_classes() {
        $class_files = [
            'class-faresize-affiliate.php',
            'class-faresize-loyalty.php',
            'class-faresize-endpoint.php',
            'class-faresize-referral.php',
            'class-faresize-coupon.php',
        ];

        foreach ( $class_files as $file ) {
            $path = plugin_dir_path( __FILE__ ) . 'includes/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            } else {
                self::log( sprintf( 'Failed to load class file: %s', $file ) );
            }
        }
        self::log( 'All class files loaded' );
    }

    /**
     * Initialize feature classes.
     */
    public function init_classes() {
        $this->affiliate = new Faresize\UltimateDashboard\Faresize_Affiliate();
        $this->loyalty = new Faresize\UltimateDashboard\Faresize_Loyalty();
        $this->endpoint = new Faresize\UltimateDashboard\Faresize_Endpoint();
        $this->referral = new Faresize\UltimateDashboard\Faresize_Referral();
        $this->coupon = new Faresize\UltimateDashboard\Faresize_Coupon();
        self::log( 'All feature classes initialized' );
    }

    /**
     * Log messages to debug.log.
     *
     * @param string $message The message to log.
     */
    public static function log( $message ) {
        error_log( sprintf( '[Faresize Ultimate Dashboard] %s at %s', $message, date( 'Y-m-d H:i:s' ) ) );
    }
}

// Initialize plugin.
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        Faresize_Ultimate_Dashboard::get_instance();
        Faresize_Ultimate_Dashboard::log( 'Plugin initialized successfully' );
    } else {
        Faresize_Ultimate_Dashboard::log( 'WooCommerce not found, plugin not initialized' );
    }
}, 10 );
?>