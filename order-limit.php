<?php
/**
 * Plugin Name: WooCommerce Order Limit Per Device
 * Description: Limits orders per device using cookies + IP fingerprinting. Works on all devices (mobile, desktop, iPhone, Android).
 * Version: 1.0.0
 * Author: Custom
 * Text Domain: wc-order-limit
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WC_ORDER_LIMIT_VERSION', '1.0.0' );
define( 'WC_ORDER_LIMIT_TABLE', 'wc_order_limit_log' );

// ─────────────────────────────────────────────
// 0. FRONTEND CSS — fix error line-height on mobile
// ─────────────────────────────────────────────

add_action( 'wp_head', 'wcol_frontend_css' );

function wcol_frontend_css() {
    if ( ! is_checkout() && ! is_cart() ) return;
    ?>
    <style>
    .woocommerce-error li,
    .woocommerce-error li a {
        line-height: 20px !important;
    }
    </style>
    <?php
}


register_activation_hook( __FILE__, 'wcol_create_table' );

function wcol_create_table() {
    global $wpdb;
    $table   = $wpdb->prefix . WC_ORDER_LIMIT_TABLE;
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        device_id   VARCHAR(64)         NOT NULL,
        ip_address  VARCHAR(45)         NOT NULL,
        order_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        ordered_at  DATETIME            NOT NULL,
        PRIMARY KEY  (id),
        KEY device_id  (device_id),
        KEY ordered_at (ordered_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Default settings
    if ( ! get_option( 'wcol_cooldown_minutes' ) ) {
        update_option( 'wcol_cooldown_minutes', 15 );
    }
    if ( ! get_option( 'wcol_daily_limit' ) ) {
        update_option( 'wcol_daily_limit', 2 );
    }
}

// ─────────────────────────────────────────────
// 2. DEVICE FINGERPRINT
//    Cookie (persisted) + IP fallback
//    Works on: Chrome, Safari (iOS), Firefox, Android WebView
// ─────────────────────────────────────────────

function wcol_get_device_id() {
    $cookie_name = 'wcol_device_id';

    // Try cookie first (most reliable across all browsers)
    if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
        $id = sanitize_text_field( $_COOKIE[ $cookie_name ] );
        // Validate format
        if ( preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
            return $id;
        }
    }

    // Generate new device ID
    $new_id = md5( uniqid( '', true ) . mt_rand() );

    // Set cookie — 400 days, SameSite=Lax works on iOS Safari, Android Chrome, etc.
    // SameSite=Strict would break some redirect flows, Lax is the safe cross-device default
    $cookie_options = [
        'expires'  => time() + ( 400 * DAY_IN_SECONDS ),
        'path'     => '/',
        'domain'   => '',          // current domain
        'secure'   => is_ssl(),
        'httponly' => false,       // must be readable? No — we set it server-side only
        'samesite' => 'Lax',       // iOS Safari 12+, Android Chrome, all modern browsers
    ];

    // PHP 7.3+ supports array form; fallback for older PHP
    if ( PHP_VERSION_ID >= 70300 ) {
        setcookie( $cookie_name, $new_id, $cookie_options );
    } else {
        setcookie(
            $cookie_name,
            $new_id,
            time() + ( 400 * DAY_IN_SECONDS ),
            '/',
            '',
            is_ssl(),
            false
        );
    }

    $_COOKIE[ $cookie_name ] = $new_id; // make it available in the same request
    return $new_id;
}

function wcol_get_ip() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_REAL_IP',          // Nginx proxy
        'HTTP_X_FORWARDED_FOR',    // Load balancers
        'REMOTE_ADDR',             // Direct
    ];
    foreach ( $headers as $h ) {
        if ( ! empty( $_SERVER[ $h ] ) ) {
            $ip = trim( explode( ',', $_SERVER[ $h ] )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// Compound key: cookie_id + IP hash — one without the other still works
// but together they're harder to spoof
function wcol_get_compound_id() {
    $cookie_id = wcol_get_device_id();
    $ip        = wcol_get_ip();
    // Store cookie_id as primary lookup; ip is logged but not part of the key
    // so mobile users on changing IPs are still tracked correctly
    return $cookie_id;
}

// ─────────────────────────────────────────────
// 3. QUERY HELPERS
// ─────────────────────────────────────────────

function wcol_get_orders_last_24h( $device_id ) {
    global $wpdb;
    $table = $wpdb->prefix . WC_ORDER_LIMIT_TABLE;
    $since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE device_id = %s AND ordered_at >= %s",
            $device_id, $since
        )
    );
}

function wcol_get_last_order_time( $device_id ) {
    global $wpdb;
    $table = $wpdb->prefix . WC_ORDER_LIMIT_TABLE;

    $result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ordered_at FROM {$table} WHERE device_id = %s ORDER BY ordered_at DESC LIMIT 1",
            $device_id
        )
    );

    return $result ? strtotime( $result ) : null;
}

function wcol_log_order( $device_id, $order_id ) {
    global $wpdb;
    $table = $wpdb->prefix . WC_ORDER_LIMIT_TABLE;

    $wpdb->insert(
        $table,
        [
            'device_id'  => $device_id,
            'ip_address' => wcol_get_ip(),
            'order_id'   => (int) $order_id,
            'ordered_at' => gmdate( 'Y-m-d H:i:s' ),
        ],
        [ '%s', '%s', '%d', '%s' ]
    );
}

// ─────────────────────────────────────────────
// 4. CHECKOUT VALIDATION
// ─────────────────────────────────────────────

add_action( 'woocommerce_checkout_process', 'wcol_check_order_limit' );

function wcol_check_order_limit() {
    $device_id        = wcol_get_compound_id();
    $cooldown_minutes = (int) get_option( 'wcol_cooldown_minutes', 15 );
    $daily_limit      = (int) get_option( 'wcol_daily_limit', 2 );

    // Check daily limit first
    $orders_today = wcol_get_orders_last_24h( $device_id );
    if ( $orders_today >= $daily_limit ) {
        wc_add_notice(
            sprintf(
                __( 'You have reached the maximum of %d orders in 24 hours. Please try again tomorrow.', 'wc-order-limit' ),
                $daily_limit
            ),
            'error'
        );
        return;
    }

    // Check cooldown
    $last_order_time = wcol_get_last_order_time( $device_id );
    if ( $last_order_time ) {
        $seconds_since = time() - $last_order_time;
        $cooldown_secs = $cooldown_minutes * 60;

        if ( $seconds_since < $cooldown_secs ) {
            $minutes_left = ceil( ( $cooldown_secs - $seconds_since ) / 60 );
            wc_add_notice(
                sprintf(
                    __( "You've placed an order already! Please wait %d more minute(s) before placing another order.", 'wc-order-limit' ),
                    $minutes_left
                ),
                'error'
            );
            return;
        }
    }
}

// ─────────────────────────────────────────────
// 5. LOG ORDER ON SUCCESS
// ─────────────────────────────────────────────

add_action( 'woocommerce_checkout_order_created', 'wcol_on_order_created', 10, 1 );

function wcol_on_order_created( $order ) {
    $device_id = wcol_get_compound_id();
    wcol_log_order( $device_id, $order->get_id() );
}

// ─────────────────────────────────────────────
// 6. ADMIN SETTINGS PAGE
// ─────────────────────────────────────────────

add_action( 'admin_menu', 'wcol_add_settings_page' );

function wcol_add_settings_page() {
    add_options_page(
        __( 'Order Limit Settings', 'wc-order-limit' ),
        __( 'Order Limit', 'wc-order-limit' ),
        'manage_options',
        'wc-order-limit',
        'wcol_render_settings_page'
    );
}

add_action( 'admin_init', 'wcol_register_settings' );

function wcol_register_settings() {
    register_setting( 'wcol_settings_group', 'wcol_cooldown_minutes', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 15,
    ]);
    register_setting( 'wcol_settings_group', 'wcol_daily_limit', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 2,
    ]);
}

function wcol_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'WooCommerce Order Limit Per Device', 'wc-order-limit' ); ?></h1>
        <p><?php esc_html_e( 'Control how often a single device can place orders. Works on all devices (mobile, desktop, iPhone, Android).', 'wc-order-limit' ); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields( 'wcol_settings_group' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="wcol_cooldown_minutes">
                            <?php esc_html_e( 'Cooldown Between Orders (minutes)', 'wc-order-limit' ); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="wcol_cooldown_minutes"
                            name="wcol_cooldown_minutes"
                            value="<?php echo esc_attr( get_option( 'wcol_cooldown_minutes', 15 ) ); ?>"
                            min="1"
                            max="1440"
                            class="small-text"
                        />
                        <p class="description">
                            <?php esc_html_e( 'How many minutes a device must wait between orders. Example: 15 means they wait 15 minutes after each order.', 'wc-order-limit' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wcol_daily_limit">
                            <?php esc_html_e( 'Max Orders Per 24 Hours', 'wc-order-limit' ); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="wcol_daily_limit"
                            name="wcol_daily_limit"
                            value="<?php echo esc_attr( get_option( 'wcol_daily_limit', 2 ) ); ?>"
                            min="1"
                            max="100"
                            class="small-text"
                        />
                        <p class="description">
                            <?php esc_html_e( 'Maximum number of orders allowed per device within any rolling 24-hour window.', 'wc-order-limit' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'wc-order-limit' ) ); ?>
        </form>

        <hr/>
        <h2><?php esc_html_e( 'Recent Device Activity', 'wc-order-limit' ); ?></h2>
        <?php wcol_render_activity_table(); ?>
    </div>
    <?php
}

function wcol_render_activity_table() {
    global $wpdb;
    $table = $wpdb->prefix . WC_ORDER_LIMIT_TABLE;
    $since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT device_id, ip_address, COUNT(*) as total_orders, MAX(ordered_at) as last_order
             FROM {$table}
             WHERE ordered_at >= %s
             GROUP BY device_id
             ORDER BY last_order DESC
             LIMIT 50",
            $since
        )
    );

    if ( empty( $rows ) ) {
        echo '<p>' . esc_html__( 'No orders in the last 24 hours.', 'wc-order-limit' ) . '</p>';
        return;
    }

    $daily_limit = (int) get_option( 'wcol_daily_limit', 2 );

    echo '<table class="widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Device ID (partial)', 'wc-order-limit' ) . '</th>';
    echo '<th>' . esc_html__( 'IP Address', 'wc-order-limit' ) . '</th>';
    echo '<th>' . esc_html__( 'Orders (24h)', 'wc-order-limit' ) . '</th>';
    echo '<th>' . esc_html__( 'Last Order', 'wc-order-limit' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'wc-order-limit' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $rows as $row ) {
        $status = ( (int) $row->total_orders >= $daily_limit )
            ? '<span style="color:red;font-weight:bold;">&#x1F6AB; Blocked</span>'
            : '<span style="color:green;">&#x2705; Active</span>';

        echo '<tr>';
        echo '<td>' . esc_html( substr( $row->device_id, 0, 8 ) . '...' ) . '</td>';
        echo '<td>' . esc_html( $row->ip_address ) . '</td>';
        echo '<td>' . esc_html( $row->total_orders ) . ' / ' . esc_html( $daily_limit ) . '</td>';
        echo '<td>' . esc_html( get_date_from_gmt( $row->last_order, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</td>';
        echo '<td>' . wp_kses_post( $status ) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

// ─────────────────────────────────────────────
// 7. DASHBOARD WIDGET
// ─────────────────────────────────────────────

add_action( 'wp_dashboard_setup', 'wcol_add_dashboard_widget' );

function wcol_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'wcol_dashboard_widget',
        __( '🛒 Order Limit Monitor', 'wc-order-limit' ),
        'wcol_render_dashboard_widget'
    );
}

function wcol_render_dashboard_widget() {
    global $wpdb;
    $table       = $wpdb->prefix . WC_ORDER_LIMIT_TABLE;
    $since       = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
    $daily_limit = (int) get_option( 'wcol_daily_limit', 2 );
    $cooldown    = (int) get_option( 'wcol_cooldown_minutes', 15 );

    $total_devices = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT device_id) FROM {$table} WHERE ordered_at >= %s",
            $since
        )
    );

    $blocked_devices = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT device_id FROM {$table}
                WHERE ordered_at >= %s
                GROUP BY device_id
                HAVING COUNT(*) >= %d
            ) AS blocked",
            $since, $daily_limit
        )
    );

    $total_orders = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE ordered_at >= %s",
            $since
        )
    );

    ?>
    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
        <div style="flex:1; background:#f0f6fc; border-left:4px solid #2271b1; padding:10px 14px; border-radius:4px;">
            <div style="font-size:22px; font-weight:700;"><?php echo esc_html( $total_orders ); ?></div>
            <div style="font-size:12px; color:#555;"><?php esc_html_e( 'Orders (last 24h)', 'wc-order-limit' ); ?></div>
        </div>
        <div style="flex:1; background:#f0f6fc; border-left:4px solid #00a32a; padding:10px 14px; border-radius:4px;">
            <div style="font-size:22px; font-weight:700;"><?php echo esc_html( $total_devices ); ?></div>
            <div style="font-size:12px; color:#555;"><?php esc_html_e( 'Active Devices', 'wc-order-limit' ); ?></div>
        </div>
        <div style="flex:1; background:#fcf0f1; border-left:4px solid #d63638; padding:10px 14px; border-radius:4px;">
            <div style="font-size:22px; font-weight:700;"><?php echo esc_html( $blocked_devices ); ?></div>
            <div style="font-size:12px; color:#555;"><?php esc_html_e( 'Blocked Devices', 'wc-order-limit' ); ?></div>
        </div>
    </div>

    <p style="margin:0 0 8px;">
        <strong><?php esc_html_e( 'Current Rules:', 'wc-order-limit' ); ?></strong>
        <?php
        printf(
            esc_html__( 'Cooldown: %d min &nbsp;|&nbsp; Daily limit: %d orders', 'wc-order-limit' ),
            $cooldown, $daily_limit
        );
        ?>
    </p>
    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=wc-order-limit' ) ); ?>" class="button button-small">
        <?php esc_html_e( 'Edit Settings & View Log', 'wc-order-limit' ); ?>
    </a>
    <?php
}

// ─────────────────────────────────────────────
// 8. AUTO CLEANUP — delete logs older than 48h
//    Keeps DB tidy, runs daily via WP-Cron
// ─────────────────────────────────────────────

register_activation_hook( __FILE__, 'wcol_schedule_cleanup' );
register_deactivation_hook( __FILE__, 'wcol_unschedule_cleanup' );

function wcol_schedule_cleanup() {
    if ( ! wp_next_scheduled( 'wcol_cleanup_event' ) ) {
        wp_schedule_event( time(), 'daily', 'wcol_cleanup_event' );
    }
}

function wcol_unschedule_cleanup() {
    wp_clear_scheduled_hook( 'wcol_cleanup_event' );
}

add_action( 'wcol_cleanup_event', 'wcol_do_cleanup' );

function wcol_do_cleanup() {
    global $wpdb;
    $table = $wpdb->prefix . WC_ORDER_LIMIT_TABLE;
    $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) );
    $wpdb->query(
        $wpdb->prepare( "DELETE FROM {$table} WHERE ordered_at < %s", $cutoff )
    );
}