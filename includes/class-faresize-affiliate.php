<?php
/**
 * Affiliate code functionality for Faresize Ultimate Dashboard Features.
 *
 * @package Faresize\UltimateDashboard
 */

namespace Faresize\UltimateDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Faresize_Affiliate
 *
 * Handles generation and display of unique affiliate codes for users.
 */
class Faresize_Affiliate {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', function() {
            add_action( 'user_register', [ $this, 'generate_unique_fs_affiliate_code' ], 10, 1 );
            add_filter( 'manage_users_columns', [ $this, 'add_fs_affiliate_code_column' ] );
            add_filter( 'manage_users_custom_column', [ $this, 'show_fs_affiliate_code_column' ], 10, 3 );
            add_shortcode( 'fs_affiliate_code', [ $this, 'display_fs_affiliate_code_shortcode' ] );
        }, 40 );
        \Faresize_Ultimate_Dashboard::log( 'Affiliate class initialized' );
    }

    /**
     * Generate a unique affiliate code for a new user.
     *
     * @param int $user_id The ID of the newly registered user.
     */
    public function generate_unique_fs_affiliate_code( int $user_id ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id for affiliate code generation: $user_id" );
            return;
        }

        $date = current_time( 'my' ); // e.g., "0425" for April 2025
        $prefix = 'FS' . $date; // FSMMYY
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $random_id = '';
        for ( $i = 0; $i < 3; $i++ ) {
            $random_id .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
        }
        $fs_affiliate_code = $prefix . $random_id; // e.g., FS0425XYZ

        // Check for duplicates
        $existing_codes = get_users( [
            'meta_key' => 'fs_affiliate_code',
            'meta_value' => $fs_affiliate_code,
            'number' => 1,
            'count_total' => false,
        ] );

        $attempts = 0;
        while ( ! empty( $existing_codes ) && $attempts < 10 ) {
            $random_id = '';
            for ( $i = 0; $i < 3; $i++ ) {
                $random_id .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
            }
            $fs_affiliate_code = $prefix . $random_id;
            $existing_codes = get_users( [
                'meta_key' => 'fs_affiliate_code',
                'meta_value' => $fs_affiliate_code,
                'number' => 1,
                'count_total' => false,
            ] );
            $attempts++;
        }

        if ( $attempts >= 10 ) {
            \Faresize_Ultimate_Dashboard::log( "Failed to generate unique affiliate code for user_id $user_id after 10 attempts" );
            return;
        }

        $result = update_user_meta( $user_id, 'fs_affiliate_code', $fs_affiliate_code );
        if ( $result ) {
            \Faresize_Ultimate_Dashboard::log( "Generated affiliate code $fs_affiliate_code for user_id $user_id" );
        } else {
            \Faresize_Ultimate_Dashboard::log( "Failed to save affiliate code $fs_affiliate_code for user_id $user_id" );
        }
    }

    /**
     * Add affiliate code column to Users admin table.
     *
     * @param array $columns The existing columns.
     * @return array Modified columns.
     */
    public function add_fs_affiliate_code_column( array $columns ) {
        $columns['fs_affiliate_code'] = 'Affiliate Code';
        \Faresize_Ultimate_Dashboard::log( 'Added affiliate code column to Users admin table' );
        return $columns;
    }

    /**
     * Display affiliate code in Users admin table.
     *
     * @param string $value The column value.
     * @param string $column_name The column name.
     * @param int    $user_id The user ID.
     * @return string The column value.
     */
    public function show_fs_affiliate_code_column( string $value, string $column_name, int $user_id ) {
        if ( 'fs_affiliate_code' === $column_name ) {
            $fs_affiliate_code = get_user_meta( $user_id, 'fs_affiliate_code', true );
            $code = $fs_affiliate_code ?: 'N/A';
            \Faresize_Ultimate_Dashboard::log( "Displayed affiliate code $code for user_id $user_id in Users admin table" );
            return $code;
        }
        return $value;
    }

    /**
     * Shortcode to display user's affiliate code.
     *
     * @return string The affiliate code HTML or login prompt.
     */
    public function display_fs_affiliate_code_shortcode() {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $fs_affiliate_code = get_user_meta( $user_id, 'fs_affiliate_code', true );
            if ( $fs_affiliate_code ) {
                \Faresize_Ultimate_Dashboard::log( "Displayed affiliate code $fs_affiliate_code for user_id $user_id via shortcode" );
                return '<span class="code" id="referralCode">' . esc_html( $fs_affiliate_code ) . '</span>';
            }
            \Faresize_Ultimate_Dashboard::log( "No affiliate code found for user_id $user_id via shortcode" );
            return 'No code assigned.';
        }
        \Faresize_Ultimate_Dashboard::log( 'User not logged in for fs_affiliate_code shortcode' );
        return 'Please log in to see your affiliate code.';
    }
}
?>