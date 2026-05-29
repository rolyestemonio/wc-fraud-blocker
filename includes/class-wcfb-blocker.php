<?php
/**
 * WCFB_Blocker — attaches to WooCommerce hooks to prevent blocked
 * customers from checking out, placing orders, or logging in.
 *
 * All list reads go through WCFB_Store which caches the DB look-up
 * in a static property, so we touch the database exactly once per
 * request regardless of how many hook callbacks fire.
 *
 * @package WC_Fraud_Blocker
 */

defined( 'ABSPATH' ) || exit;

final class WCFB_Blocker {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ------------------------------------------------------------------ //
    //  Registration
    // ------------------------------------------------------------------ //

    public function register(): void {
        // Block at checkout form validation (before order is created)
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_checkout' ] );

        // Safety net: auto-cancel any order that slipped through
        add_action( 'woocommerce_checkout_order_created', [ $this, 'maybe_cancel_order' ] );

        // Block login for flagged accounts
        add_filter( 'authenticate', [ $this, 'block_login' ], 30, 3 );
    }

    // ------------------------------------------------------------------ //
    //  Hook Callbacks
    // ------------------------------------------------------------------ //

    /**
     * Block checkout if the submitted email or address is on the blocklist.
     * Runs during form validation — no order is written yet.
     */
    public function validate_checkout(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $email = strtolower( sanitize_email( wp_unslash( $_POST['billing_email'] ?? '' ) ) );

        $ship = $this->build_full_address( [
            sanitize_text_field( wp_unslash( $_POST['shipping_address_1'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['shipping_address_2'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['shipping_city']      ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['shipping_state']     ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['shipping_postcode']  ?? '' ) ),
        ] );

        $bill = $this->build_full_address( [
            sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['billing_address_2'] ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['billing_city']      ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['billing_state']     ?? '' ) ),
            sanitize_text_field( wp_unslash( $_POST['billing_postcode']  ?? '' ) ),
        ] );
        // phpcs:enable

        if ( $this->is_email_blocked( $email ) || $this->is_address_blocked( $ship ) || $this->is_address_blocked( $bill ) ) {
            wc_add_notice(
                __( 'We are unable to process your order. Please contact support for assistance.', 'wc-fraud-blocker' ),
                'error'
            );
        }
    }

    /**
     * Auto-cancel and flag an order whose billing details match the blocklist.
     * Acts as a safety net for orders created via REST or non-standard flows.
     *
     * @param \WC_Order $order
     */
    public function maybe_cancel_order( \WC_Order $order ): void {
        $email = strtolower( $order->get_billing_email() );

        $ship = $this->build_full_address( [
            $order->get_shipping_address_1(),
            $order->get_shipping_address_2(),
            $order->get_shipping_city(),
            $order->get_shipping_state(),
            $order->get_shipping_postcode(),
        ] );

        $bill = $this->build_full_address( [
            $order->get_billing_address_1(),
            $order->get_billing_address_2(),
            $order->get_billing_city(),
            $order->get_billing_state(),
            $order->get_billing_postcode(),
        ] );

        if ( ! $this->is_email_blocked( $email ) && ! $this->is_address_blocked( $ship ) && ! $this->is_address_blocked( $bill ) ) {
            return;
        }

        $order->update_status(
            'cancelled',
            __( 'WC Fraud Blocker: Order auto-cancelled — customer is on the blocklist.', 'wc-fraud-blocker' )
        );

        // Notify shop admin
        $admin_email = get_option( 'admin_email' );
        $subject     = sprintf(
            /* translators: %s: order ID */
            __( '🚨 Fraud Alert: Order #%s auto-cancelled', 'wc-fraud-blocker' ),
            $order->get_id()
        );
        $message = sprintf(
            /* translators: 1: order ID, 2: email, 3: address */
            __( "Order #%1\$s was automatically cancelled by WC Fraud Blocker.\n\nEmail: %2\$s\nAddress: %3\$s\n\nPlease review this order in WooCommerce.", 'wc-fraud-blocker' ),
            $order->get_id(),
            $email,
            $ship ?: $bill
        );
        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Prevent blocked email addresses from authenticating.
     *
     * @param \WP_User|\WP_Error|null $user
     * @param string                  $username
     * @param string                  $password
     * @return \WP_User|\WP_Error|null
     */
    public function block_login( $user, string $username, string $password ) {
        if ( ! ( $user instanceof \WP_User ) ) {
            return $user; // already an error or not resolved yet
        }

        if ( $this->is_email_blocked( strtolower( $user->user_email ) ) ) {
            return new \WP_Error(
                'wcfb_blocked',
                __( 'This account has been suspended. Please contact support.', 'wc-fraud-blocker' )
            );
        }

        return $user;
    }

    // ------------------------------------------------------------------ //
    //  Internal helpers
    // ------------------------------------------------------------------ //

    private function is_email_blocked( string $email ): bool {
        return '' !== $email && in_array( $email, WCFB_Store::emails(), true );
    }

    /**
     * Normalize and compare the full submitted address against every
     * blocked entry. Strips punctuation so "2526 Delray St, Kalamazoo, MI 49004"
     * and "2526 delray st kalamazoo mi 49004" both match the stored value.
     */
    private function is_address_blocked( string $address ): bool {
        if ( '' === $address ) {
            return false;
        }
        $normalized = $this->normalize_address( $address );
        foreach ( WCFB_Store::addresses() as $blocked ) {
            if ( str_contains( $normalized, $this->normalize_address( $blocked ) ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Combine address parts into a single comparable string.
     * Filters empty parts so we never have double spaces or trailing commas.
     *
     * @param string[] $parts  [address_1, address_2, city, state, postcode]
     * @return string
     */
    private function build_full_address( array $parts ): string {
        return implode( ' ', array_filter( array_map( 'trim', $parts ) ) );
    }

    /**
     * Lowercase and strip all punctuation/extra whitespace so that
     * "2526 Delray St, Kalamazoo, MI 49004" ≡ "2526 delray st kalamazoo mi 49004".
     */
    private function normalize_address( string $value ): string {
        $value = strtolower( $value );
        $value = preg_replace( '/[,.\-#]+/', ' ', $value ); // strip punctuation
        $value = preg_replace( '/\s+/', ' ', $value );      // collapse whitespace
        return trim( $value );
    }
}
