<?php
/**
 * WCFB_Store — reads/writes blocked emails and addresses.
 *
 * Data is stored in two wp_options rows and cached in a static
 * property for the lifetime of the request so we never hit the DB
 * more than once per page load.
 *
 * @package WC_Fraud_Blocker
 */

defined( 'ABSPATH' ) || exit;

final class WCFB_Store {

    private static ?array $cache = null;

    // ------------------------------------------------------------------ //
    //  Public API
    // ------------------------------------------------------------------ //

    /**
     * Return all blocked entries as ['emails'=>[], 'addresses'=>[]].
     */
    public static function all(): array {
        if ( null === self::$cache ) {
            self::$cache = [
                'emails'    => self::sanitize_list( (array) get_option( 'wcfb_blocked_emails',    [] ) ),
                'addresses' => self::sanitize_list( (array) get_option( 'wcfb_blocked_addresses', [] ) ),
            ];
        }
        return self::$cache;
    }

    /** Blocked emails as a plain array of lowercase strings. */
    public static function emails(): array {
        return self::all()['emails'];
    }

    /** Blocked addresses as a plain array of lowercase strings. */
    public static function addresses(): array {
        return self::all()['addresses'];
    }

    /**
     * Add a new entry.
     *
     * @param string $type  'email' | 'address'
     * @param string $value Raw value from the user.
     * @return bool  True if added, false if already present or invalid.
     */
    public static function add( string $type, string $value ): bool {
        $value = self::normalize( $value );
        if ( '' === $value ) {
            return false;
        }

        $key  = self::option_key( $type );
        $list = (array) get_option( $key, [] );

        if ( in_array( $value, $list, true ) ) {
            return false; // duplicate — no DB write needed
        }

        $list[] = $value;
        $result = update_option( $key, $list, false ); // autoload = no
        self::$cache = null; // bust static cache
        return $result;
    }

    /**
     * Remove an entry by value.
     *
     * @param string $type  'email' | 'address'
     * @param string $value Exact stored value (already normalized).
     * @return bool
     */
    public static function remove( string $type, string $value ): bool {
        $value = self::normalize( $value );
        $key   = self::option_key( $type );
        $list  = (array) get_option( $key, [] );
        $new   = array_values( array_diff( $list, [ $value ] ) );

        if ( count( $new ) === count( $list ) ) {
            return false; // nothing removed
        }

        $result = update_option( $key, $new, false );
        self::$cache = null;
        return $result;
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    private static function option_key( string $type ): string {
        return 'address' === $type ? 'wcfb_blocked_addresses' : 'wcfb_blocked_emails';
    }

    private static function normalize( string $value ): string {
        return strtolower( sanitize_text_field( trim( $value ) ) );
    }

    private static function sanitize_list( array $list ): array {
        return array_values( array_filter( array_map( [ self::class, 'normalize' ], $list ) ) );
    }
}
