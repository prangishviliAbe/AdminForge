<?php
/**
 * Admin UI customizer.
 *
 * @package AdminForge
 */

namespace AdminForge\Modules;

use AdminForge\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UI_Customizer {
	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Rules.
	 *
	 * @var Rules_Engine
	 */
	protected $rules;

	public function __construct( Settings $settings, Rules_Engine $rules ) {
		$this->settings = $settings;
		$this->rules    = $rules;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! $this->rules->should_apply() && 'toplevel_page_adminforge' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'adminforge-admin', ADMINFORGE_URL . 'assets/css/admin.css', array(), ADMINFORGE_VERSION );
		wp_enqueue_script( 'adminforge-admin', ADMINFORGE_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-autocomplete' ), ADMINFORGE_VERSION, true );

		wp_localize_script(
			'adminforge-admin',
			'AdminForgeData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'adminforge_ajax' ),
			)
		);
	}

	/**
	 * Print inline CSS variables and custom styles.
	 */
	public function print_inline_admin_css() {
		if ( ! $this->rules->should_apply() ) {
			return;
		}

		$ui = $this->rules->ui();

		$css = sprintf(
			':root{--adminforge-sidebar-bg:%1$s;--adminforge-sidebar-text:%2$s;--adminforge-accent:%3$s;--adminforge-content-bg:%4$s;--adminforge-content-text:%5$s;--adminforge-font:%6$s;--adminforge-menu-font-size:%7$s;--adminforge-menu-icon-size:%8$s;}',
			esc_html( $ui['sidebar_bg'] ),
			esc_html( $ui['sidebar_text'] ),
			esc_html( $ui['sidebar_accent'] ),
			esc_html( $ui['content_bg'] ),
			esc_html( $ui['content_text'] ),
			esc_html( $ui['font_family'] ),
			esc_html( $ui['menu_font_size'] ),
			esc_html( $ui['menu_icon_size'] )
		);

		$css .= '
			#adminmenu, #adminmenuwrap { background: var(--adminforge-sidebar-bg); }
			#adminmenu .wp-has-current-submenu > a,
			#adminmenu .current a.menu-top,
			#adminmenu a:hover { color: var(--adminforge-sidebar-text); }
			#wpcontent, #wpfooter { background: var(--adminforge-content-bg); color: var(--adminforge-content-text); font-family: var(--adminforge-font); }
			#adminmenu .dashicons, #adminmenu .awaiting-mod, #adminmenu .update-plugins { font-size: var(--adminforge-menu-icon-size); }
			#adminmenu a { font-size: var(--adminforge-menu-font-size); }
		';

		if ( ! empty( $ui['hide_wp_logo'] ) ) {
			$css .= '#wpadminbar #wp-admin-bar-wp-logo { display: none !important; }';
		}

		if ( ! empty( $ui['hide_screen_options'] ) ) {
			$css .= '#screen-options-link-wrap, #contextual-help-link-wrap { display: none !important; }';
		}

		$css .= (string) $ui['custom_css'];

		echo '<style id="adminforge-inline-css">' . $css . '</style>';
	}

	/**
	 * Print inline JS for UI polish.
	 */
	public function print_inline_admin_js() {
		if ( ! $this->rules->should_apply() ) {
			return;
		}

		$ui = $this->rules->ui();
		if ( empty( $ui['custom_js'] ) ) {
			return;
		}

		echo '<script id="adminforge-inline-js">jQuery(function($){' . $ui['custom_js'] . '});</script>';
	}
}
