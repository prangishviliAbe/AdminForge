<?php
/**
 * Plugin Name: AdminForge
 * Description: Transform, simplify, and white-label the WordPress admin experience.
 * Version: 1.0.6
 * Author: Abe Prangishvili
 * Text Domain: adminforge
 * Domain Path: /languages
 *
 * @package AdminForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADMINFORGE_VERSION', '1.0.6' );
define( 'ADMINFORGE_FILE', __FILE__ );
define( 'ADMINFORGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'ADMINFORGE_URL', plugin_dir_url( __FILE__ ) );
define( 'ADMINFORGE_BASENAME', plugin_basename( __FILE__ ) );
define( 'ADMINFORGE_TEXT_DOMAIN', 'adminforge' );

spl_autoload_register(
	function ( $class ) {
		$prefix = 'AdminForge\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_class = str_replace( '\\', '/', $relative_class );
		$parts          = explode( '/', $relative_class );
		$base_dir       = ADMINFORGE_PATH . 'includes/';

		if ( isset( $parts[0] ) && 'Admin' === $parts[0] ) {
			$base_dir = ADMINFORGE_PATH . 'admin/';
			array_shift( $parts );
		} elseif ( isset( $parts[0] ) && 'Modules' === $parts[0] ) {
			$base_dir = ADMINFORGE_PATH . 'modules/';
			array_shift( $parts );
		}

		$file_name = 'class-' . strtolower( str_replace( '_', '-', implode( '-', $parts ) ) ) . '.php';
		$file      = $base_dir . $file_name;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook(
	__FILE__,
	array( 'AdminForge\\Plugin', 'activate' )
);

register_deactivation_hook(
	__FILE__,
	array( 'AdminForge\\Plugin', 'deactivate' )
);

add_action(
	'plugins_loaded',
	static function () {
		AdminForge\Plugin::instance()->boot();
	}
);
