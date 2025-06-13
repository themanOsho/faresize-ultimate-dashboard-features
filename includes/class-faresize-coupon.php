<?php
/**
 * Coupon restriction functionality for Faresize Ultimate Dashboard Features.
 *
 * @package Faresize\UltimateDashboard
 */

namespace Faresize\UltimateDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Faresize_Coupon
 *
 * Restricts coupons based on user loyalty level and tracks usage.
 */
class Faresize_Coupon {
    /**
     * Coupon to loyalty level mapping.
     *
     * @var array
     */
    private $coupon_levels = [
        'ROOKIE10' => 'Rookie',
        'HUSTLER20' => 'Hustler',
        'PLAYMAKERFREE' => 'Playmaker',
        'MAVERICK30' => 'Maverick',
        'TRAILBLAZERHOT' => 'Trailblazer',
        'LEGENDARY' => 'Legend',
        'ELITE75' => 'Elite',
        'OGMAX' => 'OG',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', function() {
            add_filter( 'woocommerce_coupon_is_valid', [ $this, 'restrict_coupon_by_loyalty_level' ], 10, 3 );
            add_action( 'woocommerce_order_status_completed', [ $this, 'mark_coupon_as_used' ], 30, 1 );
        }, 40 );
        \Faresize_Ultimate_Dashboard::log( 'Coupon class initialized' );
    }

    /**
     * Restrict coupons based on user's loyalty level.
     *
     * @param bool        $valid Whether the coupon is valid.
     * @param WC_Coupon   $coupon The coupon object.
     * @param WC_Discount $wc_discount The discount object.
     * @return bool Modified validity.
     */
    public function restrict_coupon_by_loyalty_level( bool $valid, $coupon, $wc_discount ) {
        if ( ! is_user_logged_in() ) {
            \Faresize_Ultimate_Dashboard::log( 'Coupon validation skipped: User not logged in' );
            return $valid;
        }

        $user_id = get_current_user_id();
        $current_level = get_user_meta( $user_id, 'fs_loyalty_level', true ) ?: 'Subscriber';
        $coupon_code = strtoupper( $coupon->get_code() );

        if ( isset( $this->coupon_levels[ $coupon_code ] ) ) {
            $required_level = $this->coupon_levels[ $coupon_code ];
            if ( $current_level !== $required_level ) {
                $valid = false;
                wc_add_notice( sprintf( 'This coupon is only for %s level users (your level: %s).', $required_level, $current_level ), 'error' );
                \Faresize_Ultimate_Dashboard::log( "Coupon $coupon_code invalid for user_id $user_id: Required $required_level, Current $current_level" );
                return $valid;
            }

            $coupon_used_key = 'used_coupon_' . $coupon_code;
            if ( get_user_meta( $user_id, $coupon_used_key, true ) ) {
                $valid = false;
                wc_add_notice( 'You have already used this coupon.', 'error' );
                \Faresize_Ultimate_Dashboard::log( "Coupon $coupon_code already used by user_id $user_id" );
                return $valid;
            }

            \Faresize_Ultimate_Dashboard::log( "Coupon $coupon_code validated for user_id $user_id at level $current_level" );
        }

        return $valid;
    }

    /**
     * Mark coupon as used after order completion.
     *
     * @param int $order_id The order ID.
     */
    public function mark_coupon_as_used( int $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid order_id $order_id for marking coupon as used" );
            return;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            \Faresize_Ultimate_Dashboard::log( "No user_id found for order_id $order_id" );
            return;
        }

        $coupons = $order->get_coupon_codes();
        if ( empty( $coupons ) ) {
            \Faresize_Ultimate_Dashboard::log( "No coupons used in order_id $order_id" );
            return;
        }

        foreach ( $coupons as $coupon_code ) {
            $coupon_code = strtoupper( $coupon_code );
            $coupon_used_key = 'used_coupon_' . $coupon_code;
            if ( ! get_user_meta( $user_id, $coupon_used_key, true ) ) {
                $result = update_user_meta( $user_id, $coupon_used_key, true );
                if ( $result ) {
                    \Faresize_Ultimate_Dashboard::log( "Marked coupon $coupon_code as used for user_id $user_id on order_id $order_id" );
                } else {
                    \Faresize_Ultimate_Dashboard::log( "Failed to mark coupon $coupon_code as used for user_id $user_id on order_id $order_id" );
                }
            } else {
                \Faresize_Ultimate_Dashboard::log( "Coupon $coupon_code already marked as used for user_id $user_id" );
            }
        }
    }

    /**
     * Get a new, unused coupon for the user's loyalty level.
     *
     * @param int $user_id The user ID.
     * @return string The coupon code or empty string.
     */
    public function get_new_coupon_for_user( int $user_id ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id for get_new_coupon_for_user: $user_id" );
            return '';
        }

        $current_level = get_user_meta( $user_id, 'fs_loyalty_level', true ) ?: 'Subscriber';
        $coupon_map = array_flip( $this->coupon_levels );
        if ( isset( $coupon_map[ $current_level ] ) ) {
            $coupon_code = $coupon_map[ $current_level ];
            $coupon_used_key = 'used_coupon_' . $coupon_code;
            if ( ! get_user_meta( $user_id, $coupon_used_key, true ) ) {
                \Faresize_Ultimate_Dashboard::log( "Found new coupon $coupon_code for user_id $user_id at level $current_level" );
                return $coupon_code;
            }
            \Faresize_Ultimate_Dashboard::log( "No new coupon available for user_id $user_id: $coupon_code already used" );
        } else {
            \Faresize_Ultimate_Dashboard::log( "No coupon available for user_id $user_id at level $current_level" );
        }
        return '';
    }
}
?>