<?php
/**
 * Plugin Name: Elementor CC Republisher Widget
 * Description: Elementor widget for rendering the Creative Commons Post Republisher UI in Elementor Single templates (license-aware, static render).
 * Version: 1.1.1
 * Author: Tarz L
 * Text Domain: elementor-cc-republisher
 *
 * Upstream CC plugin: https://github.com/creativecommons/Creative-Commons-Post-Republisher
 *
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! class_exists( '\Elementor_CC_Republisher_Widget' ) ) :

class Elementor_CC_Republisher_Widget extends Widget_Base {

    public function get_name()        { return 'cc_republisher'; }
    public function get_title()       { return 'CC Republisher'; }
    public function get_icon()        { return 'eicon-blockquote'; }
    public function get_categories()  { return [ 'general' ]; }
    public function get_keywords()    { return [ 'creative commons', 'cc', 'republish', 'license', 'reuse' ]; }

    protected function register_controls() {
        // Block settings
        $this->start_controls_section('section_content', [ 'label' => __( 'Block settings', 'elementor-cc-republisher' ) ]);

        // Optional override for rare cases; otherwise we always render the static widget (license-aware).
        $this->add_control('block_markup', [
            'label' => __( 'Block markup override (optional)', 'elementor-cc-republisher' ),
            'type' => Controls_Manager::TEXTAREA,
            'default' => '',
            'rows' => 2,
            'description' => __( 'If provided, this exact <!-- wp:... --> comment will be passed to do_blocks(). Leave empty to use the license-aware static render.', 'elementor-cc-republisher' ),
        ]);

        $this->add_control('use_do_blocks', [
            'label' => __( 'Try do_blocks() when override is present', 'elementor-cc-republisher' ),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'elementor-cc-republisher' ),
            'label_off' => __( 'No', 'elementor-cc-republisher' ),
            'default' => 'yes',
            'return_value' => 'yes',
            'description' => __( 'Only applies when "Block markup override" is filled.', 'elementor--cc-republisher' ),
        ]);

        $this->add_control('only_when_license', [
            'label' => __( 'Only render if license is set', 'elementor-cc-republisher' ),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'elementor-cc-republisher' ),
            'label_off' => __( 'No', 'elementor-cc-republisher' ),
            'default' => 'yes',
            'return_value' => 'yes',
            'description' => __( 'Hides output when neither per-post nor default site license is detected.', 'elementor-cc-republisher' ),
        ]);

        $this->add_control('honor_default', [
            'label' => __( 'Honor default site license', 'elementor-cc-republisher' ),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'elementor-cc-republisher' ),
            'label_off' => __( 'No', 'elementor-cc-republisher' ),
            'default' => 'yes',
            'return_value' => 'yes',
            'description' => __( 'Treat a site-wide default license as valid for rendering.', 'elementor-cc-republisher' ),
        ]);

        $this->add_control('wrapper_class', [
            'label' => __( 'Wrapper CSS class', 'elementor-cc-republisher' ),
            'type' => Controls_Manager::TEXT,
            'default' => 'wp-block-cc-post-republisher', // match upstream block div
        ]);

        $this->end_controls_section();

        // Debug controls
        $this->start_controls_section('section_debug', [ 'label' => __( 'Debug', 'elementor-cc-republisher' ) ]);

        $this->add_control('show_debug_editor', [
            'label' => __( 'Show debug in editor', 'elementor-cc-republisher' ),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'elementor-cc-republisher' ),
            'label_off' => __( 'No', 'elementor-cc-republisher' ),
            'default' => 'yes',
            'return_value' => 'yes',
            'description' => __( 'Displays detection info in Elementor preview only.', 'elementor-cc-republisher' ),
        ]);

        $this->add_control('show_debug_front', [
            'label' => __( 'Show debug on frontend for admins', 'elementor-cc-republisher' ),
            'type' => Controls_Manager::SWITCHER,
            'label_on' => __( 'Yes', 'elementor-cc-republisher' ),
            'label_off' => __( 'No', 'elementor-cc-republisher' ),
            'default' => 'no',
            'return_value' => 'yes',
            'description' => __( 'Shows the same debug panel on the live page for logged-in administrators.', 'elementor-cc-republisher' ),
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();

        $block_markup      = isset($s['block_markup']) ? trim($s['block_markup']) : '';
        $use_do_blocks     = isset($s['use_do_blocks']) && $s['use_do_blocks'] === 'yes';
        $only_when_license = isset($s['only_when_license']) && $s['only_when_license'] === 'yes';
        $honor_default     = isset($s['honor_default']) && $s['honor_default'] === 'yes';
        $show_debug_editor = isset($s['show_debug_editor']) && $s['show_debug_editor'] === 'yes';
        $show_debug_front  = isset($s['show_debug_front'])  && $s['show_debug_front']  === 'yes';
        $wrapper_class     = isset($s['wrapper_class']) ? $s['wrapper_class'] : 'wp-block-cc-post-republisher';

        $is_editor = ( class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode() );
        $show_debug_now = $is_editor ? $show_debug_editor : ( $show_debug_front && current_user_can('manage_options') );

        echo '<div class="' . esc_attr($wrapper_class) . '">';

        // Resolve a sensible post ID without touching globals.
        $post_id = $this->safe_get_preview_post_id();

        // Determine effective license availability.
        $license_key = $this->get_effective_cc_license_key( $post_id, $honor_default );
        $has_license = ( $license_key !== '' );

        if ( $only_when_license && ! $has_license ) {
            if ( $show_debug_now ) {
                echo $this->debug_panel([
                    'post_id'       => $post_id,
                    'licensed'      => false,
                    'resolved_key'  => $license_key,
                    'source'        => $this->last_license_source,
                    'used_path'     => '(not rendered - license gate)',
                    'html_lengths'  => null,
                    'options'       => $this->collect_cc_options(),
                    'meta'          => $this->collect_cc_meta($post_id),
                ]);
            }
            echo '</div>';
            return;
        }

        $html = '';
        $used_path = '';

        // If override is present and do_blocks() allowed, try it—but ONLY for the override case.
        if ( $block_markup !== '' && $use_do_blocks && function_exists('do_blocks') ) {
            $test = do_blocks( $block_markup );
            if ( trim($test) !== '' ) {
                $html = $test;
                $used_path = 'override → do_blocks';
            }
        }

        // Static, license-aware render (primary path in Elementor).
        if ( trim($html) === '' ) {
            $asset       = $this->license_asset_for( $license_key );
            $button_text = $this->get_cc_button_text();
            $img_url     = esc_url( $this->cc_plugin_badge_url( $asset['file'] ) );
            $alt         = esc_attr( 'License Image ' . $asset['label'] );

            $button_id = 'cc-post-republisher-modal-button-open';
            $close_id  = 'cc-post-republisher-modal-button-close';
            $html =
                '<button id="'.$button_id.'">' .
                    '<img src="' . $img_url . '" alt="' . $alt . '" style="width:88px;margin-right:5px" />' .
                    '<span>' . esc_html( $button_text ) . '</span>' .
                '</button>' .
                '<div id="cc-post-republisher-modal-container">' .
                    '<div id="cc-post-republisher-modal">' .
                        '<button id="'.$close_id.'" type="button" class="ccpr-close" aria-label="Close" style="display:none;">&times;</button>' .
                    '</div>' .
                '</div>';

            $used_path = 'static-license-aware';
            $this->maybe_enqueue_cc_block_assets();
        }

        // Optional init if front-end bundle exposes CCPostRepublisher.init.
        $html .= '<script>(function(){try{if(window.CCPostRepublisher && typeof CCPostRepublisher.init==="function"){CCPostRepublisher.init();}}catch(e){}})();</script>';

        // Debug
        if ( $show_debug_now ) {
            $html_len  = strlen( (string) $html );
            $text_len  = strlen( trim( wp_strip_all_tags( (string) $html ) ) );
            echo $this->debug_panel([
                'post_id'       => $post_id,
                'licensed'      => $has_license,
                'resolved_key'  => $license_key,
                'source'        => $this->last_license_source,
                'used_path'     => $used_path,
                'html_lengths'  => [ 'raw' => $html_len, 'text' => $text_len ],
                'options'       => $this->collect_cc_options(),
                'meta'          => $this->collect_cc_meta($post_id),
            ]);
        }

        echo $html;
        $this->enhance_ccpr_native_modal();
        echo '</div>';
    }

    protected function _content_template() {
        ?>
        <div class="{{ settings.wrapper_class || 'wp-block-cc-post-republisher' }}">
            <em>CC Republisher preview. Final HTML renders on the front end.</em>
        </div>
        <?php
    }

    // =====================
    // Helpers
    // =====================

    private function safe_get_preview_post_id() {
        $qid = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        if ($qid) return $qid;

        $tid = function_exists('get_the_ID') ? (int) get_the_ID() : 0;
        if ($tid) return $tid;

        if ( function_exists('did_action') && did_action('elementor/loaded') && class_exists('\Elementor\Plugin') ) {
            $plugin = \Elementor\Plugin::$instance ?? null;
            if ($plugin && isset($plugin->documents) && is_object($plugin->documents)) {
                $doc = $plugin->documents->get_current();
                if (is_object($doc)) {
                    if (method_exists($doc, 'get_main_id')) {
                        $mid = (int) $doc->get_main_id();
                        if ($mid) return $mid;
                    }
                    if (method_exists($doc, 'get_post')) {
                        $p = $doc->get_post();
                        if (is_object($p) && !empty($p->ID)) return (int) $p->ID;
                    }
                }
            }
        }
        return 0;
    }

    private $last_license_source = '';

    private function get_effective_cc_license_key( $post_id, $honor_default ) {
        if (property_exists($this, 'last_license_source')) {
            $this->last_license_source = '';
        }

        // 1) Per-post meta
        $meta_keys = [
            'cc_post_republisher_license',
            'ccpr_license',
            'cc_license',
            'cc_license_choice',
            '_cc_license',
        ];
        if ($post_id) {
            foreach ($meta_keys as $mk) {
                $v = get_post_meta($post_id, $mk, true);
                if (is_string($v) && $v !== '') {
                    if (property_exists($this, 'last_license_source')) {
                        $this->last_license_source = 'meta:' . $mk;
                    }
                    return strtolower(trim($v));
                }
            }
        }

        // 2) Block attrs in post_content
        if ($post_id && function_exists('get_post_field') && function_exists('parse_blocks')) {
            $content = (string) get_post_field('post_content', $post_id);
            if ($content !== '') {
                $blocks = parse_blocks($content);
                if (is_array($blocks) && $blocks) {
                    $found = null;
                    $walk = function($list) use (&$walk, &$found) {
                        foreach ($list as $blk) {
                            $name = isset($blk['blockName']) ? $blk['blockName'] : '';
                            if ($name === 'cc/post-republisher') { $found = $blk; return true; }
                            if (!empty($blk['innerBlocks']) && is_array($blk['innerBlocks'])) {
                                if ($walk($blk['innerBlocks'])) { return true; }
                            }
                        }
                        return false;
                    };
                    $walk($blocks);
                    if ($found && !empty($found['attrs']) && is_array($found['attrs'])) {
                        foreach (['license','license_key','cc_license','choice','license-type','license_type'] as $ak) {
                            if (!empty($found['attrs'][$ak]) && is_string($found['attrs'][$ak])) {
                                if (property_exists($this, 'last_license_source')) {
                                    $this->last_license_source = 'block-attrs:' . $ak;
                                }
                                return strtolower(trim($found['attrs'][$ak]));
                            }
                        }
                    }
                }
            }
        }

        // 3) Site defaults
        if ($honor_default) {
            $opt = get_option('cc_post_republisher_settings');
            if (is_array($opt)) {
                foreach (['license-type','license_type','default_license','license','cc_license'] as $k) {
                    if (!empty($opt[$k]) && is_string($opt[$k])) {
                        if (property_exists($this, 'last_license_source')) {
                            $this->last_license_source = 'site-default:cc_post_republisher_settings['.$k.']';
                        }
                        return strtolower(trim($opt[$k]));
                    }
                }
            }
            $optRoot = get_option('cc_post_republisher');
            if (is_array($optRoot)) {
                foreach (['license-type','license_type','default_license','license','cc_license'] as $k) {
                    if (!empty($optRoot[$k]) && is_string($optRoot[$k])) {
                        if (property_exists($this, 'last_license_source')) {
                            $this->last_license_source = 'site-default:cc_post_republisher['.$k.']';
                        }
                        return strtolower(trim($optRoot[$k]));
                    }
                }
            }
            $singleKeys = ['cc_post_republisher_default_license','ccpr_default_license'];
            foreach ($singleKeys as $ok) {
                $v = get_option($ok);
                if (is_string($v) && $v !== '') {
                    if (property_exists($this, 'last_license_source')) {
                        $this->last_license_source = 'site-default:'.$ok;
                    }
                    return strtolower(trim($v));
                }
            }
        }
        return '';
    }

    private function license_asset_for( $key ) {
        $map = [
            'by'         => ['file' => 'cc-by.png',         'label' => 'CC BY'],
            'by-sa'      => ['file' => 'cc-by-sa.png',      'label' => 'CC BY-SA'],
            'by-nd'      => ['file' => 'cc-by-nd.png',      'label' => 'CC BY-ND'],
            'by-nc'      => ['file' => 'cc-by-nc.png',      'label' => 'CC BY-NC'],
            'by-nc-sa'   => ['file' => 'cc-by-nc-sa.png',   'label' => 'CC BY-NC-SA'],
            'by-nc-nd'   => ['file' => 'cc-by-nc-nd.png',   'label' => 'CC BY-NC-ND'],
            'zero'       => ['file' => 'cc-zero.png',       'label' => 'CC0'],
            'cc0'        => ['file' => 'cc-zero.png',       'label' => 'CC0'],
        ];
        $k = strtolower((string)$key);
        $norms = [
            'cc-by' => 'by', 'cc-by-sa' => 'by-sa', 'cc-by-nd' => 'by-nd',
            'cc-by-nc' => 'by-nc', 'cc-by-nc-sa' => 'by-nc-sa', 'cc-by-nc-nd' => 'by-nc-nd',
            'publicdomain' => 'zero', 'public-domain' => 'zero',
        ];
        if (isset($norms[$k])) $k = $norms[$k];
        if (isset($map[$k])) return $map[$k];
        foreach ($map as $slug => $info) { if ($k !== '' && strpos($k, $slug) !== false) return $info; }
        return $map['by-sa'];
    }

    private function cc_plugin_badge_url( $filename ) {
        return content_url( 'plugins/cc-post-republisher/assets/img/' . ltrim($filename, '/') );
    }

    private function get_cc_button_text() {
        $opt = get_option('cc_post_republisher_settings');
        if (is_array($opt) && !empty($opt['republish_button_text']) && is_string($opt['republish_button_text'])) {
            return $opt['republish_button_text'];
        }
        return 'Republish';
    }

    private function maybe_enqueue_cc_block_assets() {
        if ( ! class_exists('WP_Block_Type_Registry') ) { return; }
        $reg = \WP_Block_Type_Registry::get_instance()->get_registered('cc/post-republisher');
        if ( ! $reg ) { return; }

        if ( ! empty( $reg->style ) ) {
            if ( is_array( $reg->style ) ) { foreach ($reg->style as $st) { if ($st) wp_enqueue_style( $st ); } }
            else { wp_enqueue_style( $reg->style ); }
        }

        $handles = [];
        if ( ! empty( $reg->view_script ) )          { $handles[] = $reg->view_script; }
        if ( ! empty( $reg->view_script_handles ) )  { $handles = array_merge( $handles, (array) $reg->view_script_handles ); }
        if ( ! empty( $reg->script ) )               { $handles[] = $reg->script; }

        $handles = array_values( array_unique( array_filter( $handles ) ) );
        foreach ($handles as $h) { wp_enqueue_script( $h ); }
    }

    private function collect_cc_options() {
        $names = [
            'cc_post_republisher_settings',
            'cc_post_republisher',
            'cc_post_republisher_default_license',
            'ccpr_default_license',
        ];
        $out = [];
        foreach ($names as $n) { $out[$n] = get_option($n, null); }
        return $out;
    }

    private function collect_cc_meta( $post_id ) {
        if ( ! $post_id ) { return []; }
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
            $post_id, 'cc%'
        ), ARRAY_A );
        return is_array($rows) ? $rows : [];
    }

    private static $ccpr_native_ui_done = false;
    private function enhance_ccpr_native_modal() {
        if ( self::$ccpr_native_ui_done ) return;
        self::$ccpr_native_ui_done = true; ?>

<style>
/* ===== Enhance native CC modal ===== */
#cc-post-republisher-modal-container {
  position: fixed !important;
  inset: 0 !important;
  display: none;
  align-items: center;
  justify-content: center;
  background: rgba(0,0,0,.55);
  z-index: 99998;
  padding: 24px;
  box-sizing: border-box;
}
#cc-post-republisher-modal {
  max-width: min(720px, calc(100vw - 32px));
  max-height: min(85vh, 900px);
  overflow: auto;
  background: #fff;
  color: #1f2937;
  border-radius: 12px;
  box-shadow: 0 20px 40px rgba(0,0,0,.25);
  padding: 24px 28px;
  position: relative;
  outline: none;
}
#cc-post-republisher-modal h1,
#cc-post-republisher-modal h2,
#cc-post-republisher-modal h3 { margin: 0 0 .6em; line-height: 1.25; }
#cc-post-republisher-modal p,
#cc-post-republisher-modal ul,
#cc-post-republisher-modal ol { margin: 0 0 1em; }
#cc-post-republisher-modal a { color: #2563eb; text-decoration: underline; }
#cc-post-republisher-modal-button-close,
#ccpr-native-close {
  position: absolute; right: 10px; top: 10px;
  display: inline-flex; align-items: center; justify-content: center;
  width: 36px; height: 36px; border-radius: 8px;
  border: 1px solid #e5e7eb; background: #ffffff; color: #111827;
  cursor: pointer; font-size: 18px; line-height: 1;
}
#cc-post-republisher-modal-button-close:hover,
#ccpr-native-close:hover { background: #f3f4f6; }
#cc-post-republisher-modal-button-open {
  display: inline-flex; align-items: center; gap: 8px;
  border-radius: 8px; border: 1px solid #e5e7eb; padding: 8px 12px;
  background: #fff; color: #111827; cursor: pointer;
}
#cc-post-republisher-modal-button-open:hover { background: #f9fafb; }
</style>

<script>
(function(){
  if (window.__ccprNativeUI) return; window.__ccprNativeUI = true;
  function qs(s,r){return (r||document).querySelector(s);}
  function on(el,ev,fn,o){el && el.addEventListener(ev,fn,!!o);}
  var container = qs('#cc-post-republisher-modal-container');
  var modal     = qs('#cc-post-republisher-modal');
  var openBtn   = qs('#cc-post-republisher-modal-button-open');
  if (!container || !modal) return;
  function ensureClose(){
    var btn = qs('#cc-post-republisher-modal-button-close', modal);
    if (!btn) {
      btn = document.createElement('button');
      btn.id = 'ccpr-native-close';
      btn.type = 'button';
      btn.setAttribute('aria-label','Close');
      btn.innerHTML = '&times;';
      modal.appendChild(btn);
    }
    btn.style.display = '';
    btn.onclick = close;
  }
  function open(e){
    setTimeout(function(){
      ensureClose();
      container.style.display = 'flex';
      modal.setAttribute('tabindex','-1');
      modal.focus();
      document.documentElement.style.overflow = 'hidden';
    }, 0);
  }
  function close(){
    container.style.display = 'none';
    document.documentElement.style.overflow = '';
  }
  on(container, 'click', function(ev){
    if (!modal.contains(ev.target)) close();
  });
  on(document, 'keydown', function(ev){ if (ev.key === 'Escape') close(); });
  on(openBtn, 'click', open, true);
  var obs = (window.MutationObserver ? new MutationObserver(function(){
    if (container.style.display !== 'none') {
      container.style.display = 'flex';
      ensureClose();
    }
  }) : null);
  if (obs) { obs.observe(container, { attributes:true, attributeFilter:['style','class'] }); }
})();
</script>
<?php }

    private function debug_panel( $data ) {
        ob_start();
        echo '<div style="font:13px/1.4 monospace;background:#f6f8fa;border:1px solid #e1e4e8;padding:10px;margin:8px 0;">';
        echo 'CC Republisher Debug' . "<br>";
        echo 'post_id: ' . esc_html( (string)($data['post_id'] ?: 0) ) . "<br>";
        echo 'effective_license_present: ' . ( $data['licensed'] ? 'yes' : 'no' ) . "<br>";
        echo 'resolved_license_key: ' . esc_html( (string)$data['resolved_key'] ) . "<br>";
        echo 'license_source: ' . esc_html( (string)$data['source'] ) . "<br>";
        echo 'render_path: ' . esc_html( (string)$data['used_path'] ) . "<br>";
        if ( is_array($data['html_lengths']) ) {
            echo 'html_len_raw: ' . intval($data['html_lengths']['raw']) . ', html_len_text: ' . intval($data['html_lengths']['text']) . "<br>";
        }
        echo '<details style="margin-top:6px;"><summary>options</summary><pre style="white-space:pre-wrap;">' . esc_html( print_r( $data['options'], true ) ) . '</pre></details>';
        echo '<details><summary>postmeta cc*</summary><pre style="white-space:pre-wrap;">' . esc_html( print_r( $data['meta'], true ) ) . '</pre></details>';
        echo '</div>';
        return ob_get_clean();
    }
}

endif;
