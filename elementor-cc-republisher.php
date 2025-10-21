<?php
/**
 * Plugin Name: Elementor CC Republisher Widget
 * Description: Adds an Elementor widget that renders the Creative Commons Post Republisher UI inside Elementor layouts.
 * Version: 1.1.1
 * Author: Tarz L | Public Health Action Network
 * Text Domain: elementor-cc-republisher
 *
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Constants */
define( 'ECCR_VERSION', '1.1.1' );
define( 'ECCR_PLUGIN_FILE', __FILE__ );
define( 'ECCR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ECCR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECCR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ECCR_WIDGETS_DIR', trailingslashit( ECCR_PLUGIN_DIR . 'widgets' ) );

/**
 * Register the widget with Elementor *after* Elementor is ready.
 * We include the widget class inside this hook so Widget_Base already exists.
 */
add_action( 'elementor/widgets/register', function( $widgets_manager ) {
	$widget_file = ECCR_WIDGETS_DIR . 'class-elementor-cc-republisher-widget.php';

	// Bail if Elementor base class isn't loaded yet.
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
		return;
	}

	// Include the widget class (safe now).
	if ( file_exists( $widget_file ) ) {
		require_once $widget_file;
	}

	// Register widget if the class is present.
	if ( class_exists( 'Elementor_CC_Republisher_Widget' ) ) {
		$widgets_manager->register( new Elementor_CC_Republisher_Widget() );
	}
}, 11 ); // >10 to ensure Elementor internals are loaded

/**
 * Admin notice if Elementor is missing.
 */
add_action( 'admin_init', function () {
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', function () {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'Elementor CC Republisher Widget requires Elementor to be active.', 'elementor-cc-republisher' )
			);
		} );
	}
} );

/* ========================================================================== */
/* Settings page: toggle for GitHub updater                                   */
/* ========================================================================== */

/** Option key for all plugin settings. */
if ( ! defined( 'ECCR_OPTION_KEY' ) ) {
	define( 'ECCR_OPTION_KEY', 'eccr_settings' );
}

/** Fetch a setting with a default. */
if ( ! function_exists( 'eccr_get_option' ) ) {
	function eccr_get_option( $key, $default = false ) {
		$opts = (array) get_option( ECCR_OPTION_KEY, array() );
		return array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
	}
}

/** Register option + settings fields. */
add_action( 'admin_init', function() {
	register_setting( 'eccr_settings_group', ECCR_OPTION_KEY, array(
		'type'              => 'array',
		'description'       => 'Elementor CC Republisher settings',
		'sanitize_callback' => function( $input ) {
			$input = is_array( $input ) ? $input : array();
			$out = array();
			$out['enable_updater'] = ! empty( $input['enable_updater'] ) ? 1 : 0;
			return $out;
		},
		'show_in_rest'      => false,
	) );

	add_settings_section(
		'eccr_main',
		__( 'General', 'elementor-cc-republisher' ),
		function(){ /* no description */ },
		'eccr_settings_page'
	);

	add_settings_field(
		'eccr_enable_updater',
		__( 'Enable GitHub auto-updates', 'elementor-cc-republisher' ),
		function() {
			$opts    = (array) get_option( ECCR_OPTION_KEY, array() );
			$checked = ! empty( $opts['enable_updater'] );
			echo '<label><input type="checkbox" name="' . esc_attr( ECCR_OPTION_KEY ) . '[enable_updater]" value="1" ' . checked( $checked, true, false ) . ' /> ';
			echo esc_html__( 'Check for updates from the PHAN GitHub repo and show them in the Updates screen.', 'elementor-cc-republisher' ) . '</label>';
			echo '<p class="description">' . esc_html__( 'Uses the Version header from the repo’s main branch.', 'elementor-cc-republisher' ) . '</p>';
		},
		'eccr_settings_page',
		'eccr_main'
	);
} );

/** Add the settings page under Settings. */
add_action( 'admin_menu', function() {
	add_options_page(
		__( 'CC Republisher', 'elementor-cc-republisher' ),
		__( 'CC Republisher', 'elementor-cc-republisher' ),
		'manage_options',
		'eccr-settings',
		function() {
			if ( ! current_user_can( 'manage_options' ) ) { return; }
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Elementor CC Republisher', 'elementor-cc-republisher' ) . '</h1>';
			echo '<form method="post" action="options.php">';
			settings_fields( 'eccr_settings_group' );
			do_settings_sections( 'eccr_settings_page' );
			submit_button();
			echo '</form>';
			echo '</div>';
		}
	);
} );

/** Add a quick Settings link on the Plugins list row. */
add_filter( 'plugin_action_links_' . ECCR_PLUGIN_BASENAME, function( $links ) {
	$settings_url = admin_url( 'options-general.php?page=eccr-settings' );
	array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'elementor-cc-republisher' ) . '</a>' );
	return $links;
} );

/* ========================================================================== */
/* Optional GitHub Updater (admin-only, controlled by setting)                */
/* ========================================================================== */
/**
 * When enabled via Settings → CC Republisher → “Enable GitHub auto-updates”,
 * this checks your repo’s main branch header for a higher Version and offers
 * updates in the standard WP UI. Runs admin-only, never on front-end.
 *
 * Repo: Public-Health-Action-Network/elementor-cc-republisher
 * Header URL:
 *   https://raw.githubusercontent.com/Public-Health-Action-Network/elementor-cc-republisher/main/elementor-cc-republisher.php
 * ZIP URL:
 *   https://github.com/Public-Health-Action-Network/elementor-cc-republisher/archive/refs/heads/main.zip
 */
add_action( 'plugins_loaded', function() {
	if ( ! is_admin() ) { return; }

	$enabled = (bool) eccr_get_option( 'enable_updater', false );
	if ( ! $enabled || class_exists( 'PHAN_CC_Republisher_Updater' ) ) {
		return;
	}

	class PHAN_CC_Republisher_Updater {

		private $basename;
		private $version;
		private $repo       = 'Public-Health-Action-Network/elementor-cc-republisher';
		private $header_url = 'https://raw.githubusercontent.com/Public-Health-Action-Network/elementor-cc-republisher/main/elementor-cc-republisher.php';
		private $zip_url    = 'https://github.com/Public-Health-Action-Network/elementor-cc-republisher/archive/refs/heads/main.zip';
		private $cache_key  = 'phan_cc_republisher_update_info';
		private $cache_ttl  = 6 * HOUR_IN_SECONDS; // cache 6h to reduce calls

		public function __construct( $basename, $version ) {
			$this->basename = $basename;
			$this->version  = $version;

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
			add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		}

		/** Inject update info if remote Version is higher. */
		public function check_for_update( $transient ) {
			if ( empty( $transient->checked ) ) { return $transient; }

			$info = $this->get_remote_info();
			if ( ! $info || empty( $info['version'] ) ) { return $transient; }

			if ( version_compare( $info['version'], $this->version, '>' ) ) {
				$transient->response[ $this->basename ] = (object) array(
					'slug'        => dirname( $this->basename ),
					'plugin'      => $this->basename,
					'new_version' => $info['version'],
					'url'         => 'https://github.com/' . $this->repo,
					'package'     => $this->zip_url,
					'icons'       => array(),
					'banners'     => array(),
				);
			}

			return $transient;
		}

		/** Provide “View details” modal in the Plugins screen. */
		public function plugins_api( $res, $action, $args ) {
			if ( 'plugin_information' !== $action ) { return $res; }
			if ( empty( $args->slug ) || $args->slug !== dirname( $this->basename ) ) { return $res; }

			$info = $this->get_remote_info();
			if ( ! $info ) { return $res; }

			return (object) array(
				'name'          => 'Elementor CC Republisher Widget',
				'slug'          => dirname( $this->basename ),
				'version'       => $info['version'],
				'author'        => '<a href="https://phan.global">PHAN</a>',
				'homepage'      => 'https://github.com/' . $this->repo,
				'download_link' => $this->zip_url,
				'sections'      => array(
					'description'  => wp_kses_post( $info['description'] ),
					'changelog'    => wp_kses_post( $info['changelog'] ),
				),
			);
		}

		/** Fetch and cache Version/notes from GitHub main header. */
		private function get_remote_info() {
			$cached = get_site_transient( $this->cache_key );
			if ( $cached && is_array( $cached ) ) { return $cached; }

			$response = wp_remote_get( $this->header_url, array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'text/plain' ),
			) );
			if ( is_wp_error( $response ) ) { return false; }
			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) { return false; }

			$body = wp_remote_retrieve_body( $response );
			if ( ! is_string( $body ) || '' === $body ) { return false; }

			$version   = $this->parse_header_field( $body, 'Version' );
			$desc      = $this->parse_header_field( $body, 'Description' );
			$changelog = '';

			if ( preg_match( '/^\s*Changelog:(.*)$/ims', $body, $m ) ) {
				$changelog = trim( $m[1] );
			}

			if ( ! $version ) { return false; }

			$data = array(
				'version'     => $version,
				'description' => $desc ? $desc : 'GitHub-sourced update for Elementor CC Republisher Widget.',
				'changelog'   => $changelog,
			);

			set_site_transient( $this->cache_key, $data, $this->cache_ttl );
			return $data;
		}

		/** Extract a single header field from a plugin header comment. */
		private function parse_header_field( $text, $field ) {
			if ( preg_match( '/^\s*\*?\s*' . preg_quote( $field, '/' ) . '\s*:\s*(.+)$/mi', $text, $m ) ) {
				return trim( $m[1] );
			}
			return null;
		}
	}

	// Instantiate the updater now (admin-only).
	$plugin_file = plugin_basename( __FILE__ );
	$data        = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
	$version     = isset( $data['Version'] ) ? $data['Version'] : ECCR_VERSION;
	new PHAN_CC_Republisher_Updater( $plugin_file, $version );
} );

/**
 * Plugin row status: Version, updater state, and remote version (if known).
 */
add_filter( 'plugin_row_meta', function( $links, $file ) {
	if ( $file !== ECCR_PLUGIN_BASENAME ) {
		return $links;
	}

	// Current installed version (prefer header read for accuracy).
	$ver = ECCR_VERSION;
	if ( function_exists( 'get_file_data' ) ) {
		$data = get_file_data( ECCR_PLUGIN_FILE, array( 'Version' => 'Version' ) );
		if ( ! empty( $data['Version'] ) ) {
			$ver = $data['Version'];
		}
	}

	// Updater toggle (falls back to disabled if settings helper missing).
	$enabled = function_exists( 'eccr_get_option' ) ? (bool) eccr_get_option( 'enable_updater', false ) : false;

	// If our updater has cached remote info, show its version too.
	$remote = get_site_transient( 'phan_cc_republisher_update_info' );
	$remote_ver = ( is_array( $remote ) && ! empty( $remote['version'] ) ) ? $remote['version'] : '';

	$bits = array();

	$bits[] = '<span><strong>' . sprintf( esc_html__( 'Version %s', 'elementor-cc-republisher' ), esc_html( $ver ) ) . '</strong></span>';

	$bits[] = '<span>' . sprintf(
		esc_html__( 'GitHub updates: %s', 'elementor-cc-republisher' ),
		$enabled
			? '<span style="color:#22863a;">' . esc_html__( 'enabled', 'elementor-cc-republisher' ) . '</span>'
			: '<span style="color:#6a737d;">' . esc_html__( 'disabled', 'elementor-cc-republisher' ) . '</span>'
	) . '</span>';

	if ( $remote_ver ) {
		$bits[] = '<span>' . sprintf(
			esc_html__( 'Remote: v%s', 'elementor-cc-republisher' ),
			esc_html( $remote_ver )
		) . '</span>';
	}

	// Append our bits to the existing meta links.
	return array_merge( $links, $bits );

}, 10, 2 );

