<?php
/**
 * Plugin Name: Importent Info
 * Plugin URI:  https://codexmart.app/bn
 * Description: Automatically adds a customizable copyright footer to your WordPress site. Manage it under Tools > Info.
 * Version:     2.0.0
 * Author:      Codex Mart
 * Author URI:  https://codexmart.app/bn
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────
// SYSTEM FONT STACK (no Google Fonts)
// ─────────────────────────────────────────────
function cmfi_font_stacks() {
    return array(
        'system'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif',
        'geometric' => 'Futura, "Century Gothic", CenturyGothic, AppleGothic, sans-serif',
        'humanist'  => 'Gill Sans, "Gill Sans MT", Calibri, "Trebuchet MS", sans-serif',
        'modern'    => '"Helvetica Neue", Helvetica, Arial, sans-serif',
        'elegant'   => 'Palatino, "Palatino Linotype", "Book Antiqua", Georgia, serif',
        'mono'      => '"Courier New", Courier, "Lucida Console", monospace',
        'tahoma'    => 'Tahoma, Verdana, Geneva, sans-serif',
        'garamond'  => 'Garamond, "Adobe Garamond Pro", "EB Garamond", Georgia, serif',
    );
}

function cmfi_font_label( $key ) {
    $labels = array(
        'system'    => 'System UI (Default)',
        'geometric' => 'Geometric (Futura/Century Gothic)',
        'humanist'  => 'Humanist (Gill Sans/Calibri)',
        'modern'    => 'Modern (Helvetica Neue)',
        'elegant'   => 'Elegant (Palatino/Georgia)',
        'mono'      => 'Monospace (Courier New)',
        'tahoma'    => 'Classic (Tahoma/Verdana)',
        'garamond'  => 'Garamond (Serif)',
    );
    return isset( $labels[$key] ) ? $labels[$key] : $key;
}

// ─────────────────────────────────────────────
// DEFAULTS
// ─────────────────────────────────────────────
function cmfi_default_text() {
    return '&copy; Copyright {year} {site_name}. Design by <a href="https://codexmart.app/bn" target="_blank" rel="noopener noreferrer">Codex Mart</a>';
}

function cmfi_defaults() {
    return array(
        'cmfi_enabled'      => '1',
        'cmfi_text'         => cmfi_default_text(),
        'cmfi_theme'        => 'light',
        // Light theme colors (Codex Mart white vibe)
        'cmfi_light_bg'     => '#f0f4f8',
        'cmfi_light_color'  => '#02172c',
        'cmfi_light_link'   => '#1c69d7',
        // Dark theme colors (Codex Mart dark vibe: navy/blue from logo)
        'cmfi_dark_bg'      => '#0a1628',
        'cmfi_dark_color'   => '#c8d8ec',
        'cmfi_dark_link'    => '#4da6ff',
        // Typography
        'cmfi_font_family'  => 'tahoma',
        'cmfi_font_size'    => '15',
        'cmfi_font_weight'  => '600',
        // Spacing
        'cmfi_padding_v'    => '14',
        'cmfi_padding_h'    => '16',
        'cmfi_min_height'   => '0',
    );
}

// ─────────────────────────────────────────────
// RESOLVE PLACEHOLDERS
// ─────────────────────────────────────────────
function cmfi_resolve( $text ) {
    $year      = date( 'Y' );
    $raw       = get_bloginfo( 'name' );
    $site_name = preg_replace( '/\.\w+$/', '', $raw );
    $site_name = ucwords( strtolower( $site_name ) );
    $text = str_replace( '{year}',      $year,              $text );
    $text = str_replace( '{site_name}', esc_html($site_name), $text );
    return $text;
}

// ─────────────────────────────────────────────
// ACTIVATION DEFAULTS
// ─────────────────────────────────────────────
register_activation_hook( __FILE__, function() {
    foreach ( cmfi_defaults() as $key => $val ) {
        if ( get_option( $key ) === false ) {
            update_option( $key, $val );
        }
    }
});

// ─────────────────────────────────────────────
// HELPER: GET OPTION WITH FALLBACK
// ─────────────────────────────────────────────
function cmfi_opt( $key ) {
    $defaults = cmfi_defaults();
    return get_option( $key, isset($defaults[$key]) ? $defaults[$key] : '' );
}

// ─────────────────────────────────────────────
// INJECT FOOTER
// ─────────────────────────────────────────────
add_action( 'wp_footer', function() {
    if ( cmfi_opt('cmfi_enabled') !== '1' ) return;

    $theme      = cmfi_opt('cmfi_theme');
    $fonts      = cmfi_font_stacks();
    $font_key   = cmfi_opt('cmfi_font_family');
    $font_stack = isset($fonts[$font_key]) ? $fonts[$font_key] : $fonts['tahoma'];

    $bg         = ($theme === 'dark') ? cmfi_opt('cmfi_dark_bg')    : cmfi_opt('cmfi_light_bg');
    $color      = ($theme === 'dark') ? cmfi_opt('cmfi_dark_color')  : cmfi_opt('cmfi_light_color');
    $link_color = ($theme === 'dark') ? cmfi_opt('cmfi_dark_link')   : cmfi_opt('cmfi_light_link');

    $font_size   = absint( cmfi_opt('cmfi_font_size') );
    $font_weight = esc_attr( cmfi_opt('cmfi_font_weight') );
    $pad_v       = absint( cmfi_opt('cmfi_padding_v') );
    $pad_h       = absint( cmfi_opt('cmfi_padding_h') );
    $min_height  = absint( cmfi_opt('cmfi_min_height') );

    $text = cmfi_resolve( get_option( 'cmfi_text', cmfi_default_text() ) );
    $allowed = array(
        'a'      => array( 'href' => true, 'target' => true, 'rel' => true, 'style' => true, 'class' => true ),
        'strong' => array(),
        'em'     => array(),
        'span'   => array( 'style' => true, 'class' => true ),
        'br'     => array(),
    );

    $min_height_css = $min_height > 0 ? "min-height:{$min_height}px; display:flex; align-items:center; justify-content:center;" : '';
    ?>
    <style id="cmfi-styles">
        #cmfi-footer-bar {
            width: 100%;
            background: <?php echo esc_attr($bg); ?>;
            color: <?php echo esc_attr($color); ?>;
            text-align: center;
            padding: <?php echo $pad_v; ?>px <?php echo $pad_h; ?>px;
            font-size: <?php echo $font_size; ?>px;
            font-family: <?php echo esc_attr($font_stack); ?>;
            font-weight: <?php echo $font_weight; ?>;
            box-sizing: border-box;
            line-height: 1.6;
            clear: both;
            <?php echo $min_height_css; ?>
        }
        #cmfi-footer-bar a {
            color: <?php echo esc_attr($link_color); ?>;
            text-decoration: underline;
            font-weight: <?php echo $font_weight; ?>;
            white-space: nowrap;
            transition: opacity 0.2s;
        }
        #cmfi-footer-bar a:hover {
            opacity: 0.75;
        }
        @media (max-width: 600px) {
            #cmfi-footer-bar {
                font-size: <?php echo max(12, $font_size - 2); ?>px;
                padding: <?php echo max(10, $pad_v - 2); ?>px <?php echo max(10, $pad_h - 4); ?>px;
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
// ADMIN MENU
// ─────────────────────────────────────────────
add_action( 'admin_menu', function() {
    add_management_page(
        'Footer Info Settings',
        'Info',
        'manage_options',
        'cmfi-settings',
        'cmfi_settings_page'
    );
});

// ─────────────────────────────────────────────
// HANDLE SAVE
// ─────────────────────────────────────────────
function cmfi_handle_save() {
    if (
        isset( $_POST['cmfi_nonce'] ) &&
        wp_verify_nonce( $_POST['cmfi_nonce'], 'cmfi_save_settings' ) &&
        current_user_can( 'manage_options' )
    ) {
        // Basic fields
        update_option( 'cmfi_enabled', isset($_POST['cmfi_enabled']) ? '1' : '0' );
        update_option( 'cmfi_theme',   in_array($_POST['cmfi_theme'] ?? '', ['light','dark']) ? $_POST['cmfi_theme'] : 'light' );

        // Text
        $allowed = array(
            'a'      => array('href'=>true,'target'=>true,'rel'=>true),
            'strong' => array(),
            'em'     => array(),
            'span'   => array('style'=>true),
            'br'     => array(),
        );
        $raw_text = isset($_POST['cmfi_text']) ? $_POST['cmfi_text'] : cmfi_default_text();

        if ( isset($_POST['cmfi_reset']) ) {
            update_option( 'cmfi_text', cmfi_default_text() );
        } else {
            update_option( 'cmfi_text', wp_kses($raw_text, $allowed) );
        }

        // Colors
        $color_fields = ['cmfi_light_bg','cmfi_light_color','cmfi_light_link','cmfi_dark_bg','cmfi_dark_color','cmfi_dark_link'];
        foreach ($color_fields as $field) {
            if ( isset($_POST[$field]) && preg_match('/^#[0-9a-fA-F]{3,6}$/', $_POST[$field]) ) {
                update_option( $field, sanitize_text_field($_POST[$field]) );
            }
        }

        // Font
        $valid_fonts = array_keys( cmfi_font_stacks() );
        if ( isset($_POST['cmfi_font_family']) && in_array($_POST['cmfi_font_family'], $valid_fonts) ) {
            update_option( 'cmfi_font_family', $_POST['cmfi_font_family'] );
        }

        // Font size
        if ( isset($_POST['cmfi_font_size']) ) {
            $fs = absint($_POST['cmfi_font_size']);
            update_option( 'cmfi_font_size', max(10, min(36, $fs)) );
        }

        // Font weight
        $valid_weights = ['300','400','500','600','700','800'];
        if ( isset($_POST['cmfi_font_weight']) && in_array($_POST['cmfi_font_weight'], $valid_weights) ) {
            update_option( 'cmfi_font_weight', $_POST['cmfi_font_weight'] );
        }

        // Spacing
        if ( isset($_POST['cmfi_padding_v']) )  update_option('cmfi_padding_v',  max(0, min(80, absint($_POST['cmfi_padding_v']))));
        if ( isset($_POST['cmfi_padding_h']) )  update_option('cmfi_padding_h',  max(0, min(120, absint($_POST['cmfi_padding_h']))));
        if ( isset($_POST['cmfi_min_height']) ) update_option('cmfi_min_height', max(0, min(300, absint($_POST['cmfi_min_height']))));

        return true;
    }
    return false;
}

// ─────────────────────────────────────────────
// SETTINGS PAGE
// ─────────────────────────────────────────────
function cmfi_settings_page() {
    $saved = false;
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $saved = cmfi_handle_save();
    }

    $enabled     = cmfi_opt('cmfi_enabled');
    $theme       = cmfi_opt('cmfi_theme');
    $text        = get_option('cmfi_text', cmfi_default_text());
    $is_on       = $enabled === '1';
    $fonts       = cmfi_font_stacks();
    $font_key    = cmfi_opt('cmfi_font_family');
    $font_size   = cmfi_opt('cmfi_font_size');
    $font_weight = cmfi_opt('cmfi_font_weight');
    $pad_v       = cmfi_opt('cmfi_padding_v');
    $pad_h       = cmfi_opt('cmfi_padding_h');
    $min_height  = cmfi_opt('cmfi_min_height');

    $lbg  = cmfi_opt('cmfi_light_bg');
    $lc   = cmfi_opt('cmfi_light_color');
    $ll   = cmfi_opt('cmfi_light_link');
    $dbg  = cmfi_opt('cmfi_dark_bg');
    $dc   = cmfi_opt('cmfi_dark_color');
    $dl   = cmfi_opt('cmfi_dark_link');

    $site_raw = get_bloginfo('name');
    $site_name_js = json_encode(ucwords(strtolower(preg_replace('/\.\w+$/', '', $site_raw))));
    $year_js = date('Y');
    ?>
    <div class="wrap" id="cmfi-wrap">

    <style>
    /* ── Admin Page Styles ── */
    #cmfi-wrap { max-width: 860px; }
    #cmfi-wrap h1 { display:flex; align-items:center; gap:10px; font-size:21px; margin-bottom:4px; }
    .cmfi-subtitle { color:#666; margin:0 0 6px; font-size:13.5px; }
    .cmfi-hint { color:#888; font-size:12.5px; margin:0 0 18px; }
    .cmfi-hint code { background:#f0f0f0; padding:1px 5px; border-radius:3px; font-size:12px; }

    /* Card */
    .cmfi-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 24px 28px;
        margin-top: 16px;
        box-shadow: 0 2px 10px rgba(0,0,0,.05);
    }
    .cmfi-card h2 {
        margin: 0 0 16px;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #02172c;
        border-bottom: 2px solid #1c69d7;
        padding-bottom: 8px;
        display: inline-block;
    }

    /* Grid layout for color panels */
    .cmfi-color-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .cmfi-color-panel {
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 16px 18px;
        background: #fafafa;
    }
    .cmfi-color-panel h3 { margin:0 0 14px; font-size:13px; display:flex; align-items:center; gap:6px; }

    /* Color row */
    .cmfi-color-row { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
    .cmfi-color-row label { font-size:12.5px; color:#555; width:90px; flex-shrink:0; }
    .cmfi-color-row input[type=color] {
        width:40px; height:32px; border:1px solid #ccc; border-radius:5px;
        padding:2px; cursor:pointer; background:none;
    }
    .cmfi-color-row input[type=text] {
        width:90px; font-family:monospace; font-size:12px;
        border:1px solid #ccc; border-radius:4px; padding:5px 8px;
    }

    /* Toggle switch */
    .cmfi-switch {
        position:relative; display:inline-block;
        width:50px; height:26px; vertical-align:middle;
    }
    .cmfi-switch input { opacity:0; width:0; height:0; }
    .cmfi-slider {
        position:absolute; cursor:pointer; inset:0;
        background:#ccc; border-radius:26px; transition:.3s;
    }
    .cmfi-slider:before {
        content:""; position:absolute;
        height:20px; width:20px; left:3px; bottom:3px;
        background:#fff; border-radius:50%; transition:.3s;
    }
    .cmfi-switch input:checked + .cmfi-slider { background:#27ae60; }
    .cmfi-switch input:checked + .cmfi-slider:before { transform:translateX(24px); }

    /* Theme toggle pills */
    .cmfi-theme-toggle { display:flex; gap:0; border:1px solid #ccc; border-radius:8px; overflow:hidden; width:fit-content; }
    .cmfi-theme-toggle label {
        padding:8px 22px; font-size:13px; font-weight:600; cursor:pointer;
        display:flex; align-items:center; gap:6px; user-select:none;
        transition:background .2s, color .2s;
        background:#f5f5f5; color:#555;
    }
    .cmfi-theme-toggle input[type=radio] { display:none; }
    .cmfi-theme-toggle input:checked + label { background:#02172c; color:#fff; }
    .cmfi-theme-toggle label:first-of-type { border-right:1px solid #ccc; }

    /* Slider range */
    .cmfi-range-row { display:flex; align-items:center; gap:12px; }
    .cmfi-range-row input[type=range] { flex:1; accent-color:#1c69d7; }
    .cmfi-range-row .cmfi-range-val {
        font-size:13px; font-weight:700; color:#02172c;
        min-width:36px; text-align:right;
    }

    /* Preview box */
    #cmfi-preview-wrap { margin-top:18px; border-radius:8px; overflow:hidden; border:1px solid #e0e0e0; }
    #cmfi-preview-label { font-size:12px; color:#888; font-weight:600; text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px; }

    /* Buttons */
    .cmfi-btn-row { margin-top:20px; display:flex; gap:10px; flex-wrap:wrap; }

    /* Guide */
    .cmfi-guide {
        background:#f0f7ff; border-left:4px solid #1c69d7;
        border-radius:6px; padding:16px 20px;
        margin-top:22px; font-size:13px; line-height:1.75;
    }
    .cmfi-guide strong { font-size:14.5px; }
    .cmfi-guide ul { margin:8px 0 0 18px; padding:0; color:#333; }
    .cmfi-guide code { background:#ddeeff; padding:1px 5px; border-radius:3px; }

    /* Font weight badges */
    select { border-radius:5px; border:1px solid #ccc; padding:6px 10px; font-size:13px; }

    @media(max-width:640px){
        .cmfi-color-grid { grid-template-columns:1fr; }
    }
    </style>

        <h1><span>🎨</span> Footer Info Settings <span style="font-size:13px;font-weight:400;color:#1c69d7;margin-left:4px;">v2.0</span></h1>
        <p class="cmfi-subtitle">Manage the global footer copyright bar — <strong>Tools → Info</strong></p>
        <p class="cmfi-hint">💡 Use <code>{year}</code> and <code>{site_name}</code> as dynamic placeholders.</p>

        <?php if ( $saved ): ?>
            <div class="notice notice-success is-dismissible"><p>✅ Settings saved successfully!</p></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('cmfi_save_settings','cmfi_nonce'); ?>

            <!-- ── CARD 1: Visibility & Theme ── -->
            <div class="cmfi-card">
                <h2>⚙️ Visibility & Theme</h2>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="padding-left:0;width:160px;"><label><strong>Show Footer Bar</strong></label></th>
                        <td>
                            <label class="cmfi-switch">
                                <input type="checkbox" name="cmfi_enabled" id="cmfi_enabled" value="1" <?php checked($is_on); ?>>
                                <span class="cmfi-slider"></span>
                            </label>
                            <span id="cmfi-enabled-label" style="margin-left:10px;font-size:13px;">
                                <?php echo $is_on
                                    ? '<span style="color:#27ae60;font-weight:700;">Enabled</span>'
                                    : '<span style="color:#e74c3c;font-weight:700;">Disabled</span>'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding-left:0;"><label><strong>Color Theme</strong></label></th>
                        <td>
                            <div class="cmfi-theme-toggle">
                                <input type="radio" name="cmfi_theme" id="cmfi_theme_light" value="light" <?php checked($theme,'light'); ?>>
                                <label for="cmfi_theme_light">☀️ Light</label>
                                <input type="radio" name="cmfi_theme" id="cmfi_theme_dark" value="dark" <?php checked($theme,'dark'); ?>>
                                <label for="cmfi_theme_dark">🌙 Dark</label>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── CARD 2: Footer Text ── -->
            <div class="cmfi-card" style="margin-top:16px;">
                <h2>✏️ Footer Text</h2>
                <p style="font-size:12.5px;color:#888;margin:0 0 10px;">HTML allowed: <code>a</code>, <code>strong</code>, <code>em</code>, <code>span</code>, <code>br</code></p>
                <textarea name="cmfi_text" id="cmfi_text" rows="4"
                    style="width:100%;font-family:monospace;font-size:13px;border-radius:6px;border:1px solid #ccc;padding:10px;box-sizing:border-box;"
                ><?php echo esc_textarea($text); ?></textarea>

                <div style="margin:14px 0 6px;display:flex;align-items:center;gap:10px;">
                    <strong style="font-size:12.5px;color:#555;text-transform:uppercase;letter-spacing:.04em;">Live Preview</strong>
                    <span style="font-size:11.5px;color:#aaa;">(placeholders resolved)</span>
                </div>
                <div id="cmfi-preview-wrap">
                    <div id="cmfi-preview" style="text-align:center;padding:14px 16px;font-size:15px;line-height:1.6;"></div>
                </div>
            </div>

            <!-- ── CARD 3: Colors ── -->
            <div class="cmfi-card" style="margin-top:16px;">
                <h2>🎨 Colors</h2>
                <div class="cmfi-color-grid">

                    <!-- Light Theme -->
                    <div class="cmfi-color-panel">
                        <h3>☀️ <strong>Light Theme</strong></h3>
                        <div class="cmfi-color-row">
                            <label>Background</label>
                            <input type="color" value="<?php echo esc_attr($lbg); ?>"
                                oninput="syncColor(this,'cmfi_light_bg_text','light')">
                            <input type="text" id="cmfi_light_bg_text" name="cmfi_light_bg"
                                value="<?php echo esc_attr($lbg); ?>" maxlength="7"
                                oninput="syncColorText(this,'light')">
                        </div>
                        <div class="cmfi-color-row">
                            <label>Text Color</label>
                            <input type="color" value="<?php echo esc_attr($lc); ?>"
                                oninput="syncColor(this,'cmfi_light_color_text','light')">
                            <input type="text" id="cmfi_light_color_text" name="cmfi_light_color"
                                value="<?php echo esc_attr($lc); ?>" maxlength="7"
                                oninput="syncColorText(this,'light')">
                        </div>
                        <div class="cmfi-color-row">
                            <label>Link Color</label>
                            <input type="color" value="<?php echo esc_attr($ll); ?>"
                                oninput="syncColor(this,'cmfi_light_link_text','light')">
                            <input type="text" id="cmfi_light_link_text" name="cmfi_light_link"
                                value="<?php echo esc_attr($ll); ?>" maxlength="7"
                                oninput="syncColorText(this,'light')">
                        </div>
                    </div>

                    <!-- Dark Theme -->
                    <div class="cmfi-color-panel" style="background:#1a2235;">
                        <h3 style="color:#c8d8ec;">🌙 <strong style="color:#fff;">Dark Theme</strong></h3>
                        <div class="cmfi-color-row">
                            <label style="color:#aac;">Background</label>
                            <input type="color" value="<?php echo esc_attr($dbg); ?>"
                                oninput="syncColor(this,'cmfi_dark_bg_text','dark')">
                            <input type="text" id="cmfi_dark_bg_text" name="cmfi_dark_bg"
                                value="<?php echo esc_attr($dbg); ?>" maxlength="7" style="background:#111b2e;color:#c8d8ec;border-color:#2a3a5a;"
                                oninput="syncColorText(this,'dark')">
                        </div>
                        <div class="cmfi-color-row">
                            <label style="color:#aac;">Text Color</label>
                            <input type="color" value="<?php echo esc_attr($dc); ?>"
                                oninput="syncColor(this,'cmfi_dark_color_text','dark')">
                            <input type="text" id="cmfi_dark_color_text" name="cmfi_dark_color"
                                value="<?php echo esc_attr($dc); ?>" maxlength="7" style="background:#111b2e;color:#c8d8ec;border-color:#2a3a5a;"
                                oninput="syncColorText(this,'dark')">
                        </div>
                        <div class="cmfi-color-row">
                            <label style="color:#aac;">Link Color</label>
                            <input type="color" value="<?php echo esc_attr($dl); ?>"
                                oninput="syncColor(this,'cmfi_dark_link_text','dark')">
                            <input type="text" id="cmfi_dark_link_text" name="cmfi_dark_link"
                                value="<?php echo esc_attr($dl); ?>" maxlength="7" style="background:#111b2e;color:#c8d8ec;border-color:#2a3a5a;"
                                oninput="syncColorText(this,'dark')">
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── CARD 4: Typography ── -->
            <div class="cmfi-card" style="margin-top:16px;">
                <h2>🔤 Typography</h2>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="padding-left:0;width:160px;"><label><strong>Font Family</strong></label></th>
                        <td>
                            <select name="cmfi_font_family" id="cmfi_font_family" onchange="updatePreview()">
                                <?php foreach ($fonts as $k => $v): ?>
                                    <option value="<?php echo esc_attr($k); ?>" <?php selected($font_key,$k); ?>>
                                        <?php echo esc_html(cmfi_font_label($k)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="font-size:12px;color:#888;margin:4px 0 0;">No Google Fonts — all system fonts, works everywhere.</p>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding-left:0;"><label><strong>Font Size</strong></label></th>
                        <td>
                            <div class="cmfi-range-row">
                                <input type="range" name="cmfi_font_size" id="cmfi_font_size"
                                    min="10" max="36" value="<?php echo esc_attr($font_size); ?>"
                                    oninput="document.getElementById('fs_val').textContent=this.value+'px';updatePreview()">
                                <span class="cmfi-range-val" id="fs_val"><?php echo esc_html($font_size); ?>px</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding-left:0;"><label><strong>Font Weight</strong></label></th>
                        <td>
                            <select name="cmfi_font_weight" id="cmfi_font_weight" onchange="updatePreview()">
                                <?php
                                $weights = ['300'=>'Light (300)','400'=>'Regular (400)','500'=>'Medium (500)','600'=>'Semi-Bold (600)','700'=>'Bold (700)','800'=>'Extra Bold (800)'];
                                foreach ($weights as $w => $label): ?>
                                    <option value="<?php echo $w; ?>" <?php selected($font_weight,$w); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── CARD 5: Spacing ── -->
            <div class="cmfi-card" style="margin-top:16px;">
                <h2>📐 Spacing & Height</h2>
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="padding-left:0;width:160px;"><label><strong>Vertical Padding</strong></label></th>
                        <td>
                            <div class="cmfi-range-row">
                                <input type="range" name="cmfi_padding_v" id="cmfi_padding_v"
                                    min="0" max="80" value="<?php echo esc_attr($pad_v); ?>"
                                    oninput="document.getElementById('pv_val').textContent=this.value+'px';updatePreview()">
                                <span class="cmfi-range-val" id="pv_val"><?php echo esc_html($pad_v); ?>px</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding-left:0;"><label><strong>Horizontal Padding</strong></label></th>
                        <td>
                            <div class="cmfi-range-row">
                                <input type="range" name="cmfi_padding_h" id="cmfi_padding_h"
                                    min="0" max="120" value="<?php echo esc_attr($pad_h); ?>"
                                    oninput="document.getElementById('ph_val').textContent=this.value+'px';updatePreview()">
                                <span class="cmfi-range-val" id="ph_val"><?php echo esc_html($pad_h); ?>px</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding-left:0;"><label><strong>Min Height</strong></label>
                            <p style="font-weight:400;color:#999;font-size:11.5px;margin-top:3px;">0 = auto</p>
                        </th>
                        <td>
                            <div class="cmfi-range-row">
                                <input type="range" name="cmfi_min_height" id="cmfi_min_height"
                                    min="0" max="300" value="<?php echo esc_attr($min_height); ?>"
                                    oninput="document.getElementById('mh_val').textContent=(+this.value===0?'Auto':this.value+'px');updatePreview()">
                                <span class="cmfi-range-val" id="mh_val"><?php echo $min_height > 0 ? esc_html($min_height).'px' : 'Auto'; ?></span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Buttons ── -->
            <div class="cmfi-btn-row">
                <?php submit_button('💾 Save Settings','primary','submit',false); ?>
                <button type="submit" name="cmfi_reset" value="1" class="button button-secondary"
                    onclick="return confirm('Reset footer text to the default?')">
                    ↩ Reset Text to Default
                </button>
            </div>
        </form>

        <!-- Guide -->
        <div class="cmfi-guide">
            <strong>📖 How It Works</strong>
            <ul>
                <li>The footer bar injects at the <strong>bottom of every page</strong> — no theme editing needed.</li>
                <li>Switch between <strong>Light ☀️</strong> and <strong>Dark 🌙</strong> themes from the backend instantly.</li>
                <li>Use <code>{year}</code> → always shows the <strong>current year</strong> automatically.</li>
                <li>Use <code>{site_name}</code> → shows your <strong>WP site name</strong>, cleaned & capitalized.</li>
                <li>All fonts are <strong>system fonts</strong> — no Google Fonts, no external requests, works everywhere.</li>
                <li>Control <strong>bg color, text color, link color, font, size, weight, padding & height</strong> per theme.</li>
                <li>Codex Mart link has <strong>underline</strong> styling applied automatically.</li>
            </ul>
        </div>
    </div>

    <script>
    (function(){
        const siteName = <?php echo $site_name_js; ?>;
        const year     = '<?php echo $year_js; ?>';
        const fontStacks = <?php echo json_encode($fonts); ?>;

        function resolvePlaceholders(text) {
            return text.replace(/\{year\}/g, year).replace(/\{site_name\}/g, siteName);
        }

        function getColors() {
            const theme = document.querySelector('input[name="cmfi_theme"]:checked')?.value || 'light';
            if (theme === 'dark') {
                return {
                    bg:   document.getElementById('cmfi_dark_bg_text')?.value    || '#0a1628',
                    text: document.getElementById('cmfi_dark_color_text')?.value  || '#c8d8ec',
                    link: document.getElementById('cmfi_dark_link_text')?.value   || '#4da6ff',
                };
            }
            return {
                bg:   document.getElementById('cmfi_light_bg_text')?.value    || '#f0f4f8',
                text: document.getElementById('cmfi_light_color_text')?.value  || '#02172c',
                link: document.getElementById('cmfi_light_link_text')?.value   || '#1c69d7',
            };
        }

        window.updatePreview = function() {
            const textarea = document.getElementById('cmfi_text');
            const preview  = document.getElementById('cmfi-preview');
            if (!textarea || !preview) return;

            const resolved = resolvePlaceholders(textarea.value);
            const colors   = getColors();
            const fontKey  = document.getElementById('cmfi_font_family')?.value || 'tahoma';
            const fontSize = document.getElementById('cmfi_font_size')?.value   || '15';
            const fontWeight = document.getElementById('cmfi_font_weight')?.value || '600';
            const padV     = document.getElementById('cmfi_padding_v')?.value   || '14';
            const padH     = document.getElementById('cmfi_padding_h')?.value   || '16';
            const minH     = parseInt(document.getElementById('cmfi_min_height')?.value || '0');
            const fontStack = fontStacks[fontKey] || fontStacks['tahoma'];

            preview.style.background   = colors.bg;
            preview.style.color        = colors.text;
            preview.style.fontFamily   = fontStack;
            preview.style.fontSize     = fontSize + 'px';
            preview.style.fontWeight   = fontWeight;
            preview.style.padding      = padV + 'px ' + padH + 'px';
            if (minH > 0) {
                preview.style.minHeight = minH + 'px';
                preview.style.display   = 'flex';
                preview.style.alignItems = 'center';
                preview.style.justifyContent = 'center';
            } else {
                preview.style.minHeight = '';
                preview.style.display   = '';
            }

            preview.innerHTML = resolved || '<em style="color:#888">Nothing to preview</em>';
            preview.querySelectorAll('a').forEach(function(a){
                a.style.color          = colors.link;
                a.style.textDecoration = 'underline';
                a.style.fontWeight     = fontWeight;
            });
        };

        // Sync color picker → text input
        window.syncColor = function(picker, textId, theme) {
            const textEl = document.getElementById(textId);
            if (textEl) textEl.value = picker.value;
            updatePreview();
        };

        // Sync text input → color picker (find sibling)
        window.syncColorText = function(textInput, theme) {
            if (/^#[0-9a-fA-F]{6}$/.test(textInput.value)) {
                const row = textInput.closest('.cmfi-color-row');
                const picker = row && row.querySelector('input[type=color]');
                if (picker) picker.value = textInput.value;
            }
            updatePreview();
        };

        // Textarea live update
        const textarea = document.getElementById('cmfi_text');
        if (textarea) textarea.addEventListener('input', updatePreview);

        // Theme switch
        document.querySelectorAll('input[name="cmfi_theme"]').forEach(function(r){
            r.addEventListener('change', updatePreview);
        });

        // Enable/disable label
        const cb = document.getElementById('cmfi_enabled');
        const el = document.getElementById('cmfi-enabled-label');
        if (cb && el) {
            cb.addEventListener('change', function(){
                el.innerHTML = this.checked
                    ? '<span style="color:#27ae60;font-weight:700;">Enabled</span>'
                    : '<span style="color:#e74c3c;font-weight:700;">Disabled</span>';
            });
        }

        // Initial render
        updatePreview();
    })();
    </script>
    <?php
}