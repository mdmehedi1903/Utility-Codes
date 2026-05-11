<?php
/**
 * Plugin Name: Importent Info
 * Plugin URI:  https://codexmart.app/bn
 * Description: Automatically adds a customizable copyright footer to your WordPress site. Manage it under Tools > Info.
 * Version:     1.0.0
 * Author:      Codex Mart
 * Author URI:  https://codexmart.app/bn
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// ─────────────────────────────────────────────
// 1. DEFAULT FOOTER TEXT (uses placeholders)
// ─────────────────────────────────────────────
function cmfi_default_text() {
    // Store with placeholders so year & site name stay dynamic forever
    return '&copy; Copyright {year} {site_name}, All Rights Reserved. Design by <a href="https://codexmart.app/bn" target="_blank" rel="noopener noreferrer">Codex Mart</a>';
}

// ─────────────────────────────────────────────
// 1b. RESOLVE PLACEHOLDERS AT RENDER TIME
//     {year}      → current year (always live)
//     {site_name} → WP site name, .ext removed, ucwords
// ─────────────────────────────────────────────
function cmfi_resolve( $text ) {
    // Dynamic year
    $year = date( 'Y' );

    // Dynamic site name
    $raw       = get_bloginfo( 'name' );
    $site_name = preg_replace( '/\.\w+$/', '', $raw );   // strip .com / .net / .bd etc.
    $site_name = ucwords( strtolower( $site_name ) );    // Title Case

    $text = str_replace( '{year}',      $year,                     $text );
    $text = str_replace( '{site_name}', esc_html( $site_name ),    $text );

    return $text;
}

// ─────────────────────────────────────────────
// 2. SET DEFAULTS ON ACTIVATION
// ─────────────────────────────────────────────
register_activation_hook( __FILE__, function() {
    if ( get_option( 'cmfi_enabled' ) === false ) {
        update_option( 'cmfi_enabled', '1' );
    }
    if ( get_option( 'cmfi_text' ) === false ) {
        update_option( 'cmfi_text', cmfi_default_text() );
    }
});

// ─────────────────────────────────────────────
// 3. INJECT FOOTER HTML + CSS
// ─────────────────────────────────────────────
add_action( 'wp_footer', function() {
    if ( get_option( 'cmfi_enabled' ) !== '1' ) return;

    $text = cmfi_resolve( get_option( 'cmfi_text', cmfi_default_text() ) );
    // Allow basic HTML tags only
    $allowed = array(
        'a'      => array( 'href' => true, 'target' => true, 'rel' => true, 'style' => true, 'class' => true ),
        'strong' => array(),
        'em'     => array(),
        'span'   => array( 'style' => true, 'class' => true ),
        'br'     => array(),
    );
    ?>
    <style id="cmfi-styles">
        #cmfi-footer-bar {
            width: 100%;
            background: #e5e8e5;
            color: #02172c;
            text-align: center;
            font-weight:bold;
            padding: 14px 16px;
            font-size: 16px;
            font-family: "Tahoma" !important;
            box-sizing: border-box;
            line-height: 1.6;
            clear: both;
        }
        #cmfi-footer-bar a {
            color: #1c69d7;
            text-decoration: none;
            font-weight: 600;
            text-decoration:underline;
            white-space: nowrap;
            transition: color 0.2s;
        }
        @media (max-width: 600px) {
            #cmfi-footer-bar {
                font-size: 14px;
                padding: 12px 10px;
                word-break: keep-all;
                overflow-wrap: normal;
            }
        }
    </style>
    <div id="cmfi-footer-bar" role="contentinfo">
        <?php echo wp_kses( $text, $allowed ); ?>
    </div>
    <?php
});

// ─────────────────────────────────────────────
// 4. ADD MENU UNDER TOOLS
// ─────────────────────────────────────────────
add_action( 'admin_menu', function() {
    add_management_page(
        'Footer Info Settings',   // Page title
        'Info',                   // Menu label (Tools > Info)
        'manage_options',
        'cmfi-settings',
        'cmfi_settings_page'
    );
});

// ─────────────────────────────────────────────
// 5. HANDLE FORM SAVE
// ─────────────────────────────────────────────
function cmfi_handle_save() {
    if (
        isset( $_POST['cmfi_nonce'] ) &&
        wp_verify_nonce( $_POST['cmfi_nonce'], 'cmfi_save_settings' ) &&
        current_user_can( 'manage_options' )
    ) {
        // Switcher
        $enabled = isset( $_POST['cmfi_enabled'] ) ? '1' : '0';
        update_option( 'cmfi_enabled', $enabled );

        // Text (allow some HTML)
        $raw_text = isset( $_POST['cmfi_text'] ) ? $_POST['cmfi_text'] : cmfi_default_text();
        $allowed  = array(
            'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
            'strong' => array(),
            'em'     => array(),
            'span'   => array( 'style' => true ),
            'br'     => array(),
        );
        update_option( 'cmfi_text', wp_kses( $raw_text, $allowed ) );

        // Reset to default?
        if ( isset( $_POST['cmfi_reset'] ) ) {
            update_option( 'cmfi_text', cmfi_default_text() );
        }

        return true;
    }
    return false;
}

// ─────────────────────────────────────────────
// 6. SETTINGS PAGE HTML
// ─────────────────────────────────────────────
function cmfi_settings_page() {
    $saved   = false;
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $saved = cmfi_handle_save();
    }

    $enabled  = get_option( 'cmfi_enabled', '1' );
    $text     = get_option( 'cmfi_text', cmfi_default_text() );
    $is_on    = $enabled === '1';
    ?>
    <div class="wrap" id="cmfi-wrap">
        <h1 style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:22px;">🔧</span> Footer Info Settings
        </h1>
        <p style="color:#666;">Manage the global footer copyright bar. Find it under <strong>Tools &rarr; Info</strong>.</p>
        <p style="color:#888;font-size:13px;">💡 Use <code>{year}</code> and <code>{site_name}</code> as dynamic placeholders — they auto-update every year and when your site name changes.</p>

        <?php if ( $saved ): ?>
            <div class="notice notice-success is-dismissible"><p>✅ Settings saved successfully!</p></div>
        <?php endif; ?>

        <div id="cmfi-card" style="
            background:#fff;
            border:1px solid #e0e0e0;
            border-radius:10px;
            padding:28px 32px;
            max-width:700px;
            margin-top:20px;
            box-shadow:0 2px 12px rgba(0,0,0,.06);
        ">
            <form method="post" action="">
                <?php wp_nonce_field( 'cmfi_save_settings', 'cmfi_nonce' ); ?>

                <!-- SWITCHER -->
                <table class="form-table" style="margin-bottom:0;">
                    <tr>
                        <th scope="row" style="padding-left:0;width:180px;">
                            <label for="cmfi_enabled"><strong>Show Footer Bar</strong></label>
                        </th>
                        <td>
                            <label class="cmfi-switch">
                                <input type="checkbox" name="cmfi_enabled" id="cmfi_enabled" value="1" <?php checked( $is_on ); ?>>
                                <span class="cmfi-slider"></span>
                            </label>
                            <span style="margin-left:10px;color:#555;font-size:13px;">
                                <?php echo $is_on ? '<span style="color:#27ae60;font-weight:600;">Enabled</span>' : '<span style="color:#e74c3c;font-weight:600;">Disabled</span>'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding-left:0;vertical-align:top;padding-top:14px;">
                            <label for="cmfi_text"><strong>Footer Text</strong></label>
                            <p style="font-weight:400;color:#888;font-size:12px;margin-top:4px;">HTML allowed (a, strong, em, span, br)</p>
                        </th>
                        <td>
                            <textarea
                                name="cmfi_text"
                                id="cmfi_text"
                                rows="5"
                                style="width:100%;font-family:monospace;font-size:13px;border-radius:6px;border:1px solid #ccc;padding:10px;"
                            ><?php echo esc_textarea( $text ); ?></textarea>
                        </td>
                    </tr>
                </table>

        <!-- LIVE PREVIEW -->
                <div style="margin:18px 0 6px;">
                    <strong style="font-size:13px;color:#555;">Live Preview:</strong>
                    <span style="font-size:12px;color:#999;margin-left:8px;">(placeholders resolved with current values)</span>
                </div>
                <div id="cmfi-preview" style="
                    background:#1a1a2e;
                    color:#ccc;
                    text-align:center;
                    padding:14px 16px;
                    border-radius:7px;
                    font-size:14px;
                    line-height:1.6;
                "></div>

                <!-- BUTTONS -->
                <div style="margin-top:22px;display:flex;gap:10px;flex-wrap:wrap;">
                    <?php submit_button( '💾 Save Settings', 'primary', 'submit', false ); ?>
                    <button type="submit" name="cmfi_reset" value="1" class="button button-secondary"
                        onclick="return confirm('Reset footer text to the default? This cannot be undone.')">
                        ↩ Reset to Default
                    </button>
                </div>
            </form>
        </div>

        <!-- GUIDE BOX -->
        <div style="
            background:#f0f7ff;
            border-left:4px solid #3498db;
            border-radius:6px;
            padding:18px 22px;
            max-width:700px;
            margin-top:24px;
            font-size:13.5px;
            line-height:1.7;
        ">
            <strong style="font-size:15px;">📖 How It Works</strong>
            <ul style="margin:10px 0 0 18px;padding:0;color:#333;">
                <li>The footer bar is automatically injected <strong>at the bottom of every page</strong> — no theme editing needed.</li>
                <li>Use the <strong>toggle switch</strong> to show or hide the bar instantly.</li>
                <li>Edit the <strong>Footer Text</strong> field — basic HTML like <code>&lt;a href="..."&gt;</code> is supported.</li>
                <li>Use <code>{year}</code> → always shows the <strong>current year</strong> automatically.</li>
                <li>Use <code>{site_name}</code> → always shows your <strong>WP site name</strong>, cleaned &amp; capitalized.</li>
                <li>Click <strong>Reset to Default</strong> to restore the original placeholder-based text.</li>
                <li>The bar is <strong>fully responsive</strong> and centered on all screen sizes.</li>
            </ul>
        </div>
    </div>

    <style>
    /* Toggle Switch CSS */
    .cmfi-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
        vertical-align: middle;
    }
    .cmfi-switch input { opacity: 0; width: 0; height: 0; }
    .cmfi-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: #ccc;
        border-radius: 26px;
        transition: .3s;
    }
    .cmfi-slider:before {
        content: "";
        position: absolute;
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background: white;
        border-radius: 50%;
        transition: .3s;
    }
    .cmfi-switch input:checked + .cmfi-slider { background: #27ae60; }
    .cmfi-switch input:checked + .cmfi-slider:before { transform: translateX(24px); }
    </style>

    <script>
    (function(){
        const textarea = document.getElementById('cmfi_text');
        const preview  = document.getElementById('cmfi-preview');
        const checkbox = document.getElementById('cmfi_enabled');
        const label    = checkbox.nextElementSibling;

        // Resolved values from PHP (always current)
        const siteName = <?php
            $raw = get_bloginfo('name');
            $sn  = preg_replace('/\.\w+$/', '', $raw);
            $sn  = ucwords(strtolower($sn));
            echo json_encode($sn);
        ?>;
        const year = '<?php echo date("Y"); ?>';

        function resolvePlaceholders(text) {
            return text
                .replace(/\{year\}/g, year)
                .replace(/\{site_name\}/g, siteName);
        }

        function updatePreview() {
            const resolved = resolvePlaceholders(textarea.value);
            preview.innerHTML = resolved || '<em style="color:#666">Nothing to preview</em>';
            preview.querySelectorAll('a').forEach(function(a){
                a.style.color = '#7eb3ff';
                a.style.fontWeight = '600';
            });
        }

        textarea.addEventListener('input', updatePreview);
        updatePreview();

        checkbox.addEventListener('change', function(){
            label.innerHTML = this.checked
                ? '<span style="color:#27ae60;font-weight:600;">Enabled</span>'
                : '<span style="color:#e74c3c;font-weight:600;">Disabled</span>';
        });
    })();
    </script>
    <?php
}