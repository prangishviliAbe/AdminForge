<?php
/**
 * Branding and white-label support.
 *
 * @package AdminForge
 */

namespace AdminForge\Modules;

use AdminForge\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Branding_Manager {
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
	 * Enqueue login styles.
	 */
	public function enqueue_login_styles() {
		if ( ! $this->has_login_branding() ) {
			return;
		}

		$branding = $this->settings->get_settings()['branding'];
		$logo     = ! empty( $branding['login_logo'] ) ? esc_url( $branding['login_logo'] ) : '';

		$css  = 'body.login { background: #0f172a; }';
		$css .= 'body.login #login h1 a { background-size: contain; width: 100%; height: 80px; }';

		if ( $logo ) {
			$css .= sprintf( 'body.login #login h1 a{background-image:url("%s");}', $logo );
		}

		echo '<style id="adminforge-login-css">' . $css . '</style>';
	}

	/**
	 * Login logo URL.
	 *
	 * @return string
	 */
	public function filter_login_header_url() {
		return $this->has_login_branding() ? home_url( '/' ) : 'https://wordpress.org/';
	}

	/**
	 * Login header text.
	 *
	 * @return string
	 */
	public function filter_login_header_text() {
		$branding = $this->settings->get_settings()['branding'];
		return ! empty( $branding['hide_branding'] ) ? get_bloginfo( 'name' ) : get_bloginfo( 'description' );
	}

	/**
	 * Admin bar adjustments.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public function tweak_admin_bar( $wp_admin_bar ) {
		if ( ! $this->rules->should_apply() || ! is_object( $wp_admin_bar ) ) {
			return;
		}

		$ui       = $this->rules->ui();
		$branding = $this->rules->branding();

		if ( ! empty( $ui['hide_wp_logo'] ) || ! empty( $branding['hide_branding'] ) ) {
			$wp_admin_bar->remove_node( 'wp-logo' );
		}
	}

	/**
	 * Filter admin footer text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		if ( ! $this->rules->should_apply() ) {
			return $text;
		}

		$branding = $this->rules->branding();
		return '' !== trim( (string) $branding['footer_text'] ) ? wp_kses_post( $branding['footer_text'] ) : $text;
	}

	/**
	 * Filter update footer.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function filter_update_footer( $text ) {
		if ( ! $this->rules->should_apply() ) {
			return $text;
		}

		$branding = $this->rules->branding();
		return ! empty( $branding['hide_branding'] ) ? '' : $text;
	}

	/**
	 * Determine whether login branding is configured.
	 *
	 * @return bool
	 */
	protected function has_login_branding() {
		$branding = $this->settings->get_settings()['branding'];
		return ! empty( $branding['login_logo'] ) || ! empty( $branding['hide_branding'] );
	}
}
