<?php
/**
 * Plugin Name: KBMS Knowledge Platform
 * Plugin URI:  https://example.org/wp-kbms
 * Description: Governed, permission-aware knowledge management for WordPress.
 * Version:     0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author:      KBMS Contributors
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-kbms
 * Domain Path: /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KBMS_VERSION', '0.1.0' );
define( 'KBMS_FILE', __FILE__ );
define( 'KBMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'KBMS_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'KBMS\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = KBMS_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( KBMS\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( KBMS\Core\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( 'wp-kbms', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		try {
			( new KBMS\Core\Plugin() )->start();
		} catch ( Throwable $error ) {
			$reference = substr( hash( 'sha256', get_class( $error ) . '|' . $error->getMessage() ), 0, 12 );
			error_log( '[KBMS] Bootstrap failed. Reference: ' . $reference );
			add_action(
				'admin_notices',
				static function (): void {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p>'
						. esc_html__( 'KBMS could not start. Check the PHP error log and the KBMS system status.', 'wp-kbms' )
						. '</p></div>';
					}
				}
			);
		}
	}
);
