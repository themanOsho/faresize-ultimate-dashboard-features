<?php
/**
 * Loyalty system functionality for Faresize Ultimate Dashboard Features.
 *
 * @package Faresize\UltimateDashboard
 */

namespace Faresize\UltimateDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Faresize_Loyalty
 *
 * Manages user loyalty levels and points calculation.
 */
class Faresize_Loyalty {
    /**
     * Loyalty levels and their point thresholds.
     *
     * @var array
     */
    private $levels = [
        'Subscriber' => 0,
        'Rookie' => 25,
        'Hustler' => 50,
        'Playmaker' => 100,
        'Maverick' => 200,
        'Trailblazer' => 350,
        'Legend' => 500,
        'Elite' => 700,
        'OG' => 1000,
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', function() {
            add_action( 'user_register', [ $this, 'set_default_loyalty_level' ], 10, 1 );
            add_action( 'user_register', [ $this, 'update_loyalty_level' ], 20, 1 );
            add_action( 'woocommerce_order_status_completed', [ $this, 'update_loyalty_after_order' ], 10, 1 );
            add_filter( 'manage_users_columns', [ $this, 'add_fs_loyalty_level_column' ] );
            add_filter( 'manage_users_custom_column', [ $this, 'show_fs_loyalty_level_column' ], 10, 3 );
            add_filter( 'acp/column/value', [ $this, 'display_user_loyalty_points_admin_columns' ], 10, 3 );
            add_filter( 'manage_users_columns', [ $this, 'add_points_column' ] );
            add_action( 'manage_users_custom_column', [ $this, 'show_points_in_column' ], 10, 3 );
            add_action( 'woocommerce_order_status_completed', [ $this, 'update_loyalty_level_with_new_check' ], 10, 1 );
        }, 40 );
        \Faresize_Ultimate_Dashboard::log( 'Loyalty class initialized' );
    }

    /**
     * Set default loyalty level for new users.
     *
     * @param int $user_id The user ID.
     */
    public function set_default_loyalty_level( int $user_id ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id for set_default_loyalty_level: $user_id" );
            return;
        }
        $result = update_user_meta( $user_id, 'fs_loyalty_level', 'Subscriber' );
        if ( false === $result ) {
            \Faresize_Ultimate_Dashboard::log( "Failed to set Subscriber level for user_id $user_id" );
        } else {
            \Faresize_Ultimate_Dashboard::log( "Set Subscriber level for user_id $user_id" );
        }
    }

    /**
     * Update user's loyalty level based on points.
     *
     * @param int $user_id The user ID.
     */
    public function update_loyalty_level( int $user_id ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id for update_loyalty_level: $user_id" );
            return;
        }

        $points = $this->calculate_points( $user_id );
        $current_level = 'Subscriber';
        foreach ( $this->levels as $level => $threshold ) {
            if ( $points >= $threshold ) {
                $current_level = $level;
            } else {
                break;
            }
        }

        // Check existing level to avoid unnecessary updates
        $existing_level = get_user_meta( $user_id, 'fs_loyalty_level', true ) ?: 'Subscriber';
        if ( $existing_level === $current_level ) {
            \Faresize_Ultimate_Dashboard::log( "No level change needed for user_id $user_id: $current_level, Points: $points" );
            return;
        }

        $result = update_user_meta( $user_id, 'fs_loyalty_level', $current_level );
        if ( false === $result ) {
            \Faresize_Ultimate_Dashboard::log( "Failed to update loyalty level for user_id $user_id to $current_level, Points: $points" );
        } else {
            \Faresize_Ultimate_Dashboard::log( "Updated loyalty level for user_id $user_id to $current_level, Points: $points" );
        }
    }

    /**
     * Calculate user's loyalty points.
     *
     * @param int $user_id The user ID.
     * @return int Total points.
     */
    private function calculate_points( int $user_id ) {
        $points = 0;

        // Referrals: 20 points each
        $referrals = (int) get_user_meta( $user_id, 'fs_referrals', true );
        $points += $referrals * 20;
        \Faresize_Ultimate_Dashboard::log( "Calculated $referrals referrals for user_id $user_id: " . ( $referrals * 20 ) . " points" );

        // Orders: 10 points per completed order
        $completed_orders = wc_get_orders( [
            'customer_id' => $user_id,
            'status' => 'completed',
            'limit' => -1,
            'return' => 'ids',
        ] );
        $order_count = count( $completed_orders );
        $points += $order_count * 10;
        \Faresize_Ultimate_Dashboard::log( "Calculated $order_count completed orders for user_id $user_id: " . ( $order_count * 10 ) . " points" );

        // Account Age: 5 points per month
        $user = get_userdata( $user_id );
        if ( $user ) {
            $registered = strtotime( $user->user_registered );
            $months = ( time() - $registered ) / ( 60 * 60 * 24 * 30 );
            $month_points = floor( $months ) * 5;
            $points += $month_points;
            \Faresize_Ultimate_Dashboard::log( "Calculated account age for user_id $user_id: $months months, $month_points points" );
        } else {
            \Faresize_Ultimate_Dashboard::log( "Failed to retrieve user data for user_id $user_id for account age calculation" );
        }

        \Faresize_Ultimate_Dashboard::log( "Total points for user_id $user_id: $points" );
        return $points;
    }

    /**
     * Update loyalty level after order completion.
     *
     * @param int $order_id The order ID.
     */
    public function update_loyalty_after_order( int $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid order_id $order_id for loyalty update" );
            return;
        }
        $user_id = $order->get_user_id();
        if ( $user_id ) {
            \Faresize_Ultimate_Dashboard::log( "Updating loyalty for user_id $user_id after order $order_id completed" );
            $this->update_loyalty_level( $user_id );
        } else {
            \Faresize_Ultimate_Dashboard::log( "No user_id found for order_id $order_id" );
        }
    }

    /**
     * Add loyalty level column to Users admin table.
     *
     * @param array $columns The existing columns.
     * @return array Modified columns.
     */
    public function add_fs_loyalty_level_column( array $columns ) {
        $columns['fs_loyalty_level'] = 'Loyalty Level';
        \Faresize_Ultimate_Dashboard::log( 'Added loyalty level column to Users admin table' );
        return $columns;
    }

    /**
     * Display loyalty level in Users admin table.
     *
     * @param string $value The column value.
     * @param string $column_name The column name.
     * @param int    $user_id The user ID.
     * @return string The column value.
     */
    public function show_fs_loyalty_level_column( string $value, string $column_name, int $user_id ) {
        if ( 'fs_loyalty_level' === $column_name ) {
            $level = get_user_meta( $user_id, 'fs_loyalty_level', true ) ?: 'Subscriber';
            \Faresize_Ultimate_Dashboard::log( "Displayed loyalty level $level for user_id $user_id in Users admin table" );
            return $level;
        }
        return $value;
    }

    /**
     * Display loyalty points in Admin Columns Pro.
     *
     * @param string $value The column value.
     * @param int    $id The user ID.
     * @param object $column The column object.
     * @return string The column value.
     */
    public function display_user_loyalty_points_admin_columns( string $value, int $id, $column ) {
        if ( $column->get_type() === 'column-meta' && $column->get_meta_key() === 'fs_loyalty_points' ) {
            $points = $this->calculate_points( $id );
            \Faresize_Ultimate_Dashboard::log( "Displayed $points loyalty points for user_id $id in Admin Columns Pro" );
            return $points;
        }
        return $value;
    }

    /**
     * Add points column to Users admin table.
     *
     * @param array $columns The existing columns.
     * @return array Modified columns.
     */
    public function add_points_column( array $columns ) {
        $columns['loyalty_points'] = 'Points';
        \Faresize_Ultimate_Dashboard::log( 'Added points column to Users admin table' );
        return $columns;
    }

    /**
     * Display points in Users admin table.
     *
     * @param string $value The column value.
     * @param string $column_name The column name.
     * @param int    $user_id The user ID.
     * @return string The column value.
     */
    public function show_points_in_column( string $value, string $column_name, int $user_id ) {
        if ( 'loyalty_points' === $column_name ) {
            $points = $this->calculate_points( $user_id );
            \Faresize_Ultimate_Dashboard::log( "Displayed $points points for user_id $user_id in Users admin table" );
            return $points;
        }
        return $value;
    }

    /**
     * Get the next loyalty level for a user.
     *
     * @param int $user_id The user ID.
     * @return string The next level and points required.
     */
    public function get_next_loyalty_level( int $user_id ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id for get_next_loyalty_level: $user_id" );
            return 'Invalid user';
        }

        $current_level = get_user_meta( $user_id, 'fs_loyalty_level', true ) ?: 'Subscriber';
        $level_keys = array_keys( $this->levels );
        $current_index = array_search( $current_level, $level_keys );

        if ( $current_index !== false && $current_index < count( $level_keys ) - 1 ) {
            $next_level = $level_keys[ $current_index + 1 ];
            $next_points = $this->levels[ $next_level ];
            \Faresize_Ultimate_Dashboard::log( "Next loyalty level for user_id $user_id: $next_level ($next_points pts)" );
            return "$next_level ($next_points pts)";
        }

        \Faresize_Ultimate_Dashboard::log( "User_id $user_id at max loyalty level: OG" );
        return 'OG (Max)';
    }

    /**
     * Update loyalty level and track new levels after order.
     *
     * @param int $order_id The order ID.
     */
    public function update_loyalty_level_with_new_check( int $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid order_id $order_id for loyalty level new check" );
            return;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            \Faresize_Ultimate_Dashboard::log( "No user_id found for order_id $order_id" );
            return;
        }

        $old_level = get_user_meta( $user_id, 'fs_loyalty_level', true ) ?: 'Subscriber';
        $this->update_loyalty_level( $user_id );
        $new_level = get_user_meta( $user_id, 'fs_loyalty_level', true ) ?: 'Subscriber';

        if ( $old_level !== $new_level ) {
            $result = update_user_meta( $user_id, 'fs_new_level_unlocked', $new_level );
            if ( $result ) {
                \Faresize_Ultimate_Dashboard::log( "Marked new loyalty level $new_level for user_id $user_id" );
            } else {
                \Faresize_Ultimate_Dashboard::log( "Failed to mark new loyalty level $new_level for user_id $user_id" );
            }
        } else {
            \Faresize_Ultimate_Dashboard::log( "No new loyalty level for user_id $user_id after order $order_id" );
        }
    }
}
?>