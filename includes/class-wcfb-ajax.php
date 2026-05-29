<?php
/**
 * WCFB_Ajax — handles admin AJAX requests for adding and removing
 * entries from the blocklist.
 *
 * Both actions require:
 *   - A valid nonce  (wcfb_nonce)
 *   - manage_woocommerce capability
 *
 * @package WC_Fraud_Blocker
 */

defined( 'ABSPATH' ) || exit;

final class WCFB_Ajax {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function register(): void {
        add_action( 'wp_ajax_wcfb_add',    [ $this, 'handle_add' ] );
        add_action( 'wp_ajax_wcfb_remove', [ $this, 'handle_remove' ] );
    }

    // ------------------------------------------------------------------ //
    //  Handlers
    // ------------------------------------------------------------------ //

    public function handle_add(): void {
        $this->verify_request();

        $type  = $this->get_type();
        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

        if ( '' === trim( $value ) ) {
            wp_send_json_error( [ 'message' => __( 'Value cannot be empty.', 'wc-fraud-blocker' ) ], 400 );
        }

        // Extra validation for emails
        if ( 'email' === $type && ! is_email( $value ) ) {
            wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'wc-fraud-blocker' ) ], 400 );
        }

        $added = WCFB_Store::add( $type, $value );

        if ( ! $added ) {
            wp_send_json_error( [ 'message' => __( 'This entry is already on the blocklist.', 'wc-fraud-blocker' ) ], 409 );
        }

        wp_send_json_success( [
            'message' => __( 'Entry added to the blocklist.', 'wc-fraud-blocker' ),
            'value'   => strtolower( sanitize_text_field( trim( $value ) ) ),
            'type'    => $type,
        ] );
    }

    public function handle_remove(): void {
        $this->verify_request();

        $type  = $this->get_type();
        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

        if ( '' === trim( $value ) ) {
            wp_send_json_error( [ 'message' => __( 'Value cannot be empty.', 'wc-fraud-blocker' ) ], 400 );
        }

        $removed = WCFB_Store::remove( $type, $value );

        if ( ! $removed ) {
            wp_send_json_error( [ 'message' => __( 'Entry not found on the blocklist.', 'wc-fraud-blocker' ) ], 404 );
        }

        wp_send_json_success( [
            'message' => __( 'Entry removed from the blocklist.', 'wc-fraud-blocker' ),
            'value'   => $value,
            'type'    => $type,
        ] );
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    private function verify_request(): void {
        if ( ! check_ajax_referer( 'wcfb_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'wc-fraud-blocker' ) ], 403 );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wc-fraud-blocker' ) ], 403 );
        }
    }

    private function get_type(): string {
        $type = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
        if ( ! in_array( $type, [ 'email', 'address' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid type.', 'wc-fraud-blocker' ) ], 400 );
        }
        return $type;
    }
}
