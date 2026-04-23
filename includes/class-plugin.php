<?php
/**
 * Main plugin bootstrap.
 *
 * @package AdminForge
 */

namespace AdminForge;

use AdminForge\Admin\Admin_Page;
use AdminForge\Modules\Access_Restriction_Manager;
use AdminForge\Modules\Assignment_Manager;
use AdminForge\Modules\Branding_Manager;
use AdminForge\Modules\Dashboard_Manager;
use AdminForge\Modules\Menu_Scanner;
use AdminForge\Modules\Rules_Engine;
use AdminForge\Modules\UI_Customizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Loader.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Menu scanner.
	 *
	 * @var Menu_Scanner
	 */
	protected $menu_scanner;

	/**
	 * Assignment manager.
	 *
	 * @var Assignment_Manager
	 */
	protected $assignment_manager;

	/**
	 * Rules engine.
	 *
	 * @var Rules_Engine
	 */
	protected $rules_engine;

	/**
	 * Dashboard manager.
	 *
	 * @var Dashboard_Manager
	 */
	protected $dashboard_manager;

	/**
	 * Access manager.
	 *
	 * @var Access_Restriction_Manager
	 */
	protected $access_manager;

	/**
	 * UI customizer.
	 *
	 * @var UI_Customizer
	 */
	protected $ui_customizer;

	/**
	 * Branding manager.
	 *
	 * @var Branding_Manager
	 */
	protected $branding_manager;

	/**
	 * Admin page.
	 *
	 * @var Admin_Page
	 */
	protected $admin_page;

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot the plugin.
	 */
	public function boot() {
		load_plugin_textdomain( ADMINFORGE_TEXT_DOMAIN, false, dirname( ADMINFORGE_BASENAME ) . '/languages' );

		$this->loader            = new Loader();
		$this->settings          = new Settings();
		$this->menu_scanner      = new Menu_Scanner( $this->settings );
		$this->assignment_manager = new Assignment_Manager( $this->settings );
		$this->rules_engine      = new Rules_Engine( $this->settings, $this->assignment_manager );
		$this->dashboard_manager = new Dashboard_Manager( $this->settings, $this->menu_scanner, $this->rules_engine );
		$this->access_manager    = new Access_Restriction_Manager( $this->settings, $this->menu_scanner, $this->rules_engine );
		$this->ui_customizer     = new UI_Customizer( $this->settings, $this->rules_engine );
		$this->branding_manager  = new Branding_Manager( $this->settings, $this->rules_engine );
		$this->admin_page        = new Admin_Page( $this->settings, $this->menu_scanner );

		$this->register_hooks();
		$this->loader->run();
	}

	/**
	 * Register hooks.
	 */
	protected function register_hooks() {
		$this->loader->add_action( 'admin_menu', $this, 'register_admin_menu', 5 );
		$this->loader->add_action( 'admin_menu', $this, 'capture_and_apply_menu_rules', 99 );
		$this->loader->add_action( 'admin_menu', $this->access_manager, 'apply_menu_visibility', 999 );
		$this->loader->add_action( 'wp_dashboard_setup', $this->dashboard_manager, 'register_dashboard_hooks', 999 );
		$this->loader->add_action( 'admin_init', $this->access_manager, 'maybe_block_direct_access', 1 );
		$this->loader->add_action( 'current_screen', $this->access_manager, 'maybe_register_screen_guards', 10, 1 );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->ui_customizer, 'enqueue_admin_assets' );
		$this->loader->add_action( 'admin_head', $this->ui_customizer, 'print_inline_admin_css' );
		$this->loader->add_action( 'admin_footer', $this->ui_customizer, 'print_inline_admin_js' );
		$this->loader->add_action( 'login_enqueue_scripts', $this->branding_manager, 'enqueue_login_styles' );
		$this->loader->add_filter( 'login_headerurl', $this->branding_manager, 'filter_login_header_url' );
		$this->loader->add_filter( 'login_headertext', $this->branding_manager, 'filter_login_header_text' );
		$this->loader->add_action( 'admin_bar_menu', $this->branding_manager, 'tweak_admin_bar', 999, 1 );
		$this->loader->add_filter( 'admin_footer_text', $this->branding_manager, 'filter_admin_footer_text' );
		$this->loader->add_filter( 'update_footer', $this->branding_manager, 'filter_update_footer' );
		$this->loader->add_action( 'wp_ajax_adminforge_rescan_menus', $this->admin_page, 'ajax_rescan_menus' );
		$this->loader->add_action( 'wp_ajax_adminforge_search_users', $this->admin_page, 'ajax_search_users' );
		$this->loader->add_action( 'admin_post_adminforge_save_settings', $this->admin_page, 'handle_save_settings' );
	}

	/**
	 * Register plugin admin menu.
	 */
	public function register_admin_menu() {
		$this->admin_page->register();
	}

	/**
	 * Capture menus and apply visibility rules.
	 */
	public function capture_and_apply_menu_rules() {
		$this->menu_scanner->maybe_refresh_inventory();
		$this->access_manager->register_dynamic_guards();
		$this->dashboard_manager->maybe_refresh_dashboard_inventory();
	}

	/**
	 * Activate plugin.
	 */
	public static function activate() {
		$settings = new Settings();
		if ( ! get_option( Settings::OPTION_NAME ) ) {
			update_option( Settings::OPTION_NAME, $settings->get_defaults(), false );
		}
	}

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate() {
		// Intentionally left light-touch to avoid removing user settings.
	}

	/**
	 * Get settings.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get menu scanner.
	 *
	 * @return Menu_Scanner
	 */
	public function menu_scanner() {
		return $this->menu_scanner;
	}

	/**
	 * Get rules engine.
	 *
	 * @return Rules_Engine
	 */
	public function rules_engine() {
		return $this->rules_engine;
	}
}
