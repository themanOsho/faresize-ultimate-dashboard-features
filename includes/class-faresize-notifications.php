<?php
/**
 * Notifications functionality for Faresize Ultimate Dashboard Features.
 *
 * @package Faresize\UltimateDashboard
 */

namespace Faresize\UltimateDashboard;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class Faresize_Notifications
 *
 * Manages user notifications and unread count.
 */
class Faresize_Notifications {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', function() {
            add_action( 'template_redirect', [ $this, 'reset_unread_count' ] );
        }, 40 );
        \Faresize_Ultimate_Dashboard::log( 'Notifications class initialized' );
    }

    /**
     * Add a notification for a user.
     *
     * @param int    $user_id The user ID.
     * @param string $message The notification message.
     */
    public function add_notification( int $user_id, string $message ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id $user_id for adding notification" );
            return;
        }

        $notifications = get_user_meta( $user_id, 'fs_notifications', true );
        if ( ! is_array( $notifications ) ) {
            $notifications = [];
        }

        $notification = [
            'message' => sanitize_text_field( $message ),
            'timestamp' => current_time( 'Y-m-d H:i:s' ),
        ];
        $notifications[] = $notification;

        $result = update_user_meta( $user_id, 'fs_notifications', $array );
        if ( $result ) {
            $unread_count = (int) get_user_meta( $user_id, 'fs_unread_notifications', true ) + 1;
            update_user_meta( $user_id, 'fs_unread_notifications', $unread_count );
            \Faresize_Ultimate_Dashboard::log( "Added notification for user_id $user_id: $message (Unread count: $unread_count)" );
        } else {
            \Faresize_Ultimate_Dashboard::log( "Failed to add notification for user_id $user_id: $message" );
        }
    }

    /**
     * Get all notifications for a user.
     *
     * @param int $user_id The user ID.
     * @return array List of notifications.
     */
    public function get_notifications( int $user_id ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id $user_id for getting notifications" );
            return [];
        }

        $notifications = get_user_meta( $user_id, 'fs_notifications', true );
        if ( ! is_array( $notifications ) ) {
            $notifications = [];
        }
        \Faresize_Ultimate_Dashboard::log( "Retrieved " . count( $notifications ) . " notifications for user_id $user_id" );
        return array_reverse( $notifications ); // Newest first
    }

    /**
     * Get unread notification count for a user.
     *
     * @param int $user_id The user ID.
     * @return int Unread count.
     */
    public function get_unread_count( int $user_id ) {
        if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
            \Faresize_Ultimate_Dashboard::log( "Invalid user_id $user_id for getting unread count" );
            return 0;
        }

        $unread_count = (int) get_user_meta( $user_id, 'fs_unread_notifications', true );
        \Faresize_Ultimate_Dashboard::log( "Unread notification count for user_id $user_id: $unread_count" );
        return $unread_count;
    }

    /**
     * Reset unread notification count when visiting notifications page.
     */
    public function reset_unread_count() {
        global $wp;
        if ( is_user_logged_in() && isset( $wp->query_vars['notifications'] ) ) {
            $user_id = get_current_user_id();
            $result = update_user_meta( $user_id, 'fs_unread_notifications', 0 );
            if ( $result ) {
                \Faresize_Ultimate_Dashboard::log( "Reset unread notification count for user_id $user_id" );
            } else {
                \Faresize_Ultimate_Dashboard::log( "Failed to reset unread notification count for user_id $user_id" );
            }
        }
    }

    /**
     * Get HTML for bell icon with unread count, linked to notifications page.
     *
     * @return string HTML for linked bell icon.
     */
    public function get_bell_icon() {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user_id = get_current_user_id();
        $unread_count = $this->get_unread_count( $user_id );
        $notifications_url = wc_get_endpoint_url( 'notifications', '', wc_get_page_permalink( 'myaccount' ) );
        $output = '<a href="' . esc_url( $notifications_url ) . '" class="xoo-el-notice-bell">🔔';
        if ( $unread_count > 0 ) {
            $output .= '<sup>' . esc_html( $unread_count ) . '</sup>';
        }
        $output .= '</a>';
        \Faresize_Ultimate_Dashboard::log( "Rendered linked bell icon for user_id $user_id with unread count: $unread_count" );
        return $output;
    }
}
?>