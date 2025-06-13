<?php
/**
 * Referral system functionality for Faresize Ultimate Dashboard Features.
 *
 * @package Faresize\UltimateDashboard
 */

namespace Faresize\UltimateDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Faresize_Referral
 *
 * Manages referral code input and point awarding during registration.
 */
class Faresize_Referral {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', function() {
            add_action( 'user_register', [ $this, 'save_referral_code_from_registration' ], 30, 1 );
            add_action( 'woocommerce_order_status_completed', [ $this, 'award_referral_points_on_first_order' ], 20, 1 );
        }, 40 );
        \Faresize_Ultimate_Dashboard::log( 'Referral class initialized' );
    }

    /**
     * Save referral code from registration form.
     *
     * @param int $user_id The newly registered user ID.
     */
    public function save_referral_code_from_registration( int $user_id ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id for saving referral code: $user_id" );
            return;
        }

        if ( ! empty( $_POST['referral_code'] ) ) {
            $referral_code = sanitize_text_field( wp_unslash( $_POST['referral_code'] ) );
            $referrer = get_users( [
                'meta_key' => 'fs_affiliate_code',
                'meta_value' => $referral_code,
                'number' => 1,
                'fields' => 'ID',
            ] );

            if ( ! empty( $referrer ) && $referrer[0] != $user_id ) {
                $result = update_user_meta( $user_id, 'fs_referrer_id', $referrer[0] );
                if ( $result ) {
                    \Faresize_Ultimate_Dashboard::log( "Saved referrer_id {$referrer[0]} for user_id $user_id during registration" );
                } else {
                    \Faresize_Ultimate_Dashboard::log( "Failed to save referrer_id {$referrer[0]} for user_id $user_id during registration" );
                }
            } else {
                \Faresize_Ultimate_Dashboard::log( "Invalid or self-referral code $referral_code for user_id $user_id during registration" );
            }
        } else {
            \Faresize_Ultimate_Dashboard::log( "No referral code provided for user_id $user_id during registration" );
        }
    }

    /**
     * Award referral points on first order.
     *
     * @param int $order_id The order ID.
     */
    public function award_referral_points_on_first_order( int $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid order_id $order_id for referral points" );
            return;
        }

        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            \Faresize_Ultimate_Dashboard::log( "No user_id found for order_id $order_id" );
            return;
        }

        // Check if this is the user's first completed order
        $completed_orders = wc_get_orders( [
            'customer_id' => $user_id,
            'status' => 'completed',
            'limit' => -1,
            'return' => 'ids',
        ] );
        $order_count = count( $completed_orders );

        if ( $order_count === 1 ) {
            $referrer_id = get_user_meta( $user_id, 'fs_referrer_id', true );
            if ( $referrer_id ) {
                $current_referrals = (int) get_user_meta( $referrer_id, 'fs_referrals', true );
                $new_referrals = $current_referrals + 1;
                $result = update_user_meta( $referrer_id, 'fs_referrals', $new_referrals );
                if ( $result ) {
                    \Faresize_Ultimate_Dashboard::log( "Awarded referral point to referrer_id $referrer_id (new total: $new_referrals) for order_id $order_id" );
                    $loyalty = \Faresize_Ultimate_Dashboard::get_instance()->loyalty;
                    if ( $loyalty ) {
                        $loyalty->update_loyalty_level( $referrer_id );
                        \Faresize_Ultimate_Dashboard::log( "Updated loyalty level for referrer_id $referrer_id after referral point award" );
                    } else {
                        \Faresize_Ultimate_Dashboard::log( "Loyalty class not available for referrer_id $referrer_id" );
                    }
                } else {
                    \Faresize_Ultimate_Dashboard::log( "Failed to update fs_referrals for referrer_id $referrer_id on order_id $order_id" );
                }
            } else {
                \Faresize_Ultimate_Dashboard::log( "No referrer_id found for user_id $user_id on order_id $order_id" );
            }
        } else {
            \Faresize_Ultimate_Dashboard::log( "Order_id $order_id is not first completed order for user_id $user_id (order count: $order_count)" );
        }
    }
}
?>