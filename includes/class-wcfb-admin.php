<?php
/**
 * WCFB_Admin — registers the settings page under WooCommerce menu.
 *
 * @package WC_Fraud_Blocker
 */

defined( 'ABSPATH' ) || exit;

final class WCFB_Admin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function register(): void {
        add_action( 'admin_menu',            [ $this, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    // ------------------------------------------------------------------ //
    //  Menu
    // ------------------------------------------------------------------ //

    public function add_menu_page(): void {
        add_submenu_page(
            'woocommerce',
            __( 'Fraud Blocker', 'wc-fraud-blocker' ),
            __( 'Fraud Blocker', 'wc-fraud-blocker' ),
            'manage_woocommerce',
            'wc-fraud-blocker',
            [ $this, 'render_page' ]
        );
    }

    // ------------------------------------------------------------------ //
    //  Assets
    // ------------------------------------------------------------------ //

    public function enqueue_assets( string $hook ): void {
        if ( 'woocommerce_page_wc-fraud-blocker' !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'wcfb-admin',
            WCFB_PLUGIN_URL . 'assets/admin.css',
            [],
            WCFB_VERSION
        );
        wp_enqueue_script(
            'wcfb-admin',
            WCFB_PLUGIN_URL . 'assets/admin.js',
            [ 'jquery' ],
            WCFB_VERSION,
            true
        );
        wp_localize_script( 'wcfb-admin', 'wcfb', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wcfb_nonce' ),
            'i18n'     => [
                'confirm_remove' => __( 'Remove this entry from the blocklist?', 'wc-fraud-blocker' ),
                'adding'         => __( 'Adding…', 'wc-fraud-blocker' ),
                'removing'       => __( 'Removing…', 'wc-fraud-blocker' ),
                'empty_field'    => __( 'Please enter a value before adding.', 'wc-fraud-blocker' ),
            ],
        ] );
    }

    // ------------------------------------------------------------------ //
    //  Page render
    // ------------------------------------------------------------------ //

    public function render_page(): void {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wc-fraud-blocker' ) );
        }

        $data = WCFB_Store::all();
        ?>
        <div class="wcfb-wrap">

            <div class="wcfb-header">
                <div class="wcfb-header__inner">
                    <span class="wcfb-header__icon">🛡️</span>
                    <div>
                        <h1><?php esc_html_e( 'WC Fraud Blocker', 'wc-fraud-blocker' ); ?></h1>
                        <p><?php esc_html_e( 'Manage blocked emails and shipping addresses. Blocked customers cannot complete checkout or log in.', 'wc-fraud-blocker' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="wcfb-notice" id="wcfb-notice" hidden></div>

            <div class="wcfb-grid">

                <!-- ── Blocked Emails ── -->
                <div class="wcfb-card" data-type="email">
                    <div class="wcfb-card__head">
                        <h2><?php esc_html_e( 'Blocked Email Addresses', 'wc-fraud-blocker' ); ?></h2>
                        <span class="wcfb-badge" id="wcfb-count-email"><?php echo count( $data['emails'] ); ?></span>
                    </div>

                    <div class="wcfb-add-row">
                        <input
                            type="email"
                            id="wcfb-input-email"
                            class="wcfb-input"
                            placeholder="fraudster@example.com"
                            autocomplete="off"
                        />
                        <button class="wcfb-btn wcfb-btn--add" data-type="email">
                            <?php esc_html_e( '+ Block Email', 'wc-fraud-blocker' ); ?>
                        </button>
                    </div>

                    <ul class="wcfb-list" id="wcfb-list-email">
                        <?php if ( empty( $data['emails'] ) ) : ?>
                            <li class="wcfb-list__empty" id="wcfb-empty-email">
                                <?php esc_html_e( 'No blocked emails yet.', 'wc-fraud-blocker' ); ?>
                            </li>
                        <?php else : ?>
                            <?php foreach ( $data['emails'] as $email ) : ?>
                                <?php $this->render_list_item( $email, 'email' ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- ── Blocked Addresses ── -->
                <div class="wcfb-card" data-type="address">
                    <div class="wcfb-card__head">
                        <h2><?php esc_html_e( 'Blocked Shipping Addresses', 'wc-fraud-blocker' ); ?></h2>
                        <span class="wcfb-badge" id="wcfb-count-address"><?php echo count( $data['addresses'] ); ?></span>
                    </div>

                    <div class="wcfb-add-row">
                        <input
                            type="text"
                            id="wcfb-input-address"
                            class="wcfb-input"
                            placeholder="2526 Delray St, Kalamazoo, MI 49004"
                            autocomplete="off"
                        />
                        <button class="wcfb-btn wcfb-btn--add" data-type="address">
                            <?php esc_html_e( '+ Block Address', 'wc-fraud-blocker' ); ?>
                        </button>
                    </div>

                    <ul class="wcfb-list" id="wcfb-list-address">
                        <?php if ( empty( $data['addresses'] ) ) : ?>
                            <li class="wcfb-list__empty" id="wcfb-empty-address">
                                <?php esc_html_e( 'No blocked addresses yet.', 'wc-fraud-blocker' ); ?>
                            </li>
                        <?php else : ?>
                            <?php foreach ( $data['addresses'] as $address ) : ?>
                                <?php $this->render_list_item( $address, 'address' ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

            </div><!-- .wcfb-grid -->

            <div class="wcfb-info-box">
                <strong><?php esc_html_e( 'How blocking works:', 'wc-fraud-blocker' ); ?></strong>
                <ul>
                    <li><?php esc_html_e( '✅ Blocked emails are checked at checkout and login — the customer cannot proceed.', 'wc-fraud-blocker' ); ?></li>
                    <li><?php esc_html_e( '✅ Blocked addresses are matched against the full combined address (street, city, state, postcode) — punctuation and casing are ignored, so "2526 Delray St, Kalamazoo, MI 49004" and "2526 delray st kalamazoo mi 49004" are treated as the same.', 'wc-fraud-blocker' ); ?></li>
                    <li><?php esc_html_e( '✅ Any order that slips through is automatically cancelled and you receive an admin email alert.', 'wc-fraud-blocker' ); ?></li>
                    <li><?php esc_html_e( '✅ All values are stored in lowercase — matching is case-insensitive.', 'wc-fraud-blocker' ); ?></li>
                </ul>
            </div>

        </div><!-- .wcfb-wrap -->
        <?php
    }

    private function render_list_item( string $value, string $type ): void {
        printf(
            '<li class="wcfb-list__item" data-value="%1$s" data-type="%2$s">
                <span class="wcfb-list__value">%1$s</span>
                <button class="wcfb-btn wcfb-btn--remove" data-value="%1$s" data-type="%2$s" title="%3$s">✕</button>
            </li>',
            esc_attr( $value ),
            esc_attr( $type ),
            esc_attr__( 'Remove', 'wc-fraud-blocker' )
        );
    }
}
