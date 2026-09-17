<?php

declare(strict_types=1);

namespace KBMS\Admin;

use KBMS\Core\Hookable;

final class Settings implements Hookable {

	public const OPTION = 'kbms_settings';

	/** @var array<string, bool|int|string|array<int, string>> */
	private const DEFAULTS = array(
		'ai_enabled'              => false,
		'graph_enabled'           => true,
		'analytics_enabled'       => true,
		'git_sync_enabled'        => false,
		'mcp_enabled'             => false,
		'external_search_enabled' => false,
		'confidence_threshold'    => 70,
		'review_interval_days'    => 180,
		'retention_days'          => 365,
		'allowed_webhook_hosts'   => array(),
	);

	public function registerHooks(): void {
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, 'kbms' ) ) {
			return;
		}
		wp_enqueue_style( 'kbms-admin', KBMS_URL . 'assets/css/admin.css', array(), KBMS_VERSION );
	}

	public static function get( string $key ): mixed {
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::DEFAULTS );
		return $settings[ $key ] ?? null;
	}

	public function registerSettings(): void {
		register_setting(
			'kbms',
			self::OPTION,
			array(
				'type'              => 'object',
				'default'           => self::DEFAULTS,
				'sanitize_callback' => array( $this, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
		register_setting(
			'kbms',
			'kbms_delete_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => static function ( mixed $value ): bool {
					return current_user_can( 'manage_kbms' ) && rest_sanitize_boolean( $value );
				},
				'show_in_rest'      => false,
			)
		);
	}

	/** @param mixed $input @return array<string, bool|int|array<int, string>> */
	public function sanitize( mixed $input ): array {
		if ( ! current_user_can( 'manage_kbms' ) ) {
			return (array) get_option( self::OPTION, self::DEFAULTS );
		}

		$input = is_array( $input ) ? $input : array();
		$hosts = isset( $input['allowed_webhook_hosts'] )
			? preg_split( '/[\r\n,]+/', (string) $input['allowed_webhook_hosts'] )
			: array();
		$hosts = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn ( string $host ): string => strtolower( sanitize_text_field( trim( $host ) ) ),
						is_array( $hosts ) ? $hosts : array()
					),
					static fn ( string $host ): bool => $host !== '' && preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/', $host ) === 1
				)
			)
		);

		return array(
			'ai_enabled'              => ! empty( $input['ai_enabled'] ),
			'graph_enabled'           => ! empty( $input['graph_enabled'] ),
			'analytics_enabled'       => ! empty( $input['analytics_enabled'] ),
			'git_sync_enabled'        => ! empty( $input['git_sync_enabled'] ),
			'mcp_enabled'             => ! empty( $input['mcp_enabled'] ),
			'external_search_enabled' => ! empty( $input['external_search_enabled'] ),
			'confidence_threshold'    => max( 0, min( 100, absint( $input['confidence_threshold'] ?? 70 ) ) ),
			'review_interval_days'    => max( 1, min( 3650, absint( $input['review_interval_days'] ?? 180 ) ) ),
			'retention_days'          => max( 30, min( 3650, absint( $input['retention_days'] ?? 365 ) ) ),
			'allowed_webhook_hosts'   => $hosts,
		);
	}

	public function registerMenu(): void {
		add_menu_page(
			__( 'Knowledge Platform', 'wp-kbms' ),
			__( 'Knowledge', 'wp-kbms' ),
			'edit_kbms_items',
			'kbms',
			array( $this, 'renderDashboard' ),
			'dashicons-welcome-learn-more',
			24
		);
		add_submenu_page( 'kbms', __( 'KBMS Settings', 'wp-kbms' ), __( 'Settings', 'wp-kbms' ), 'manage_kbms', 'kbms-settings', array( $this, 'renderSettings' ) );
	}

	public function renderDashboard(): void {
		if ( ! current_user_can( 'edit_kbms_items' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-kbms' ) );
		}

		$counts    = wp_count_posts( 'kbms_item' );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$drafts    = isset( $counts->draft ) ? (int) $counts->draft : 0;
		?>
		<div class="wrap kbms-admin">
			<h1><?php echo esc_html__( 'Knowledge Platform', 'wp-kbms' ); ?></h1>
			<p><?php echo esc_html__( 'Create, govern, verify, and discover organizational knowledge.', 'wp-kbms' ); ?></p>
			<div class="kbms-cards">
				<section class="kbms-card" aria-labelledby="kbms-published"><h2 id="kbms-published"><?php echo esc_html__( 'Published', 'wp-kbms' ); ?></h2><strong><?php echo esc_html( (string) $published ); ?></strong></section>
				<section class="kbms-card" aria-labelledby="kbms-drafts"><h2 id="kbms-drafts"><?php echo esc_html__( 'Drafts', 'wp-kbms' ); ?></h2><strong><?php echo esc_html( (string) $drafts ); ?></strong></section>
				<section class="kbms-card" aria-labelledby="kbms-due"><h2 id="kbms-due"><?php echo esc_html__( 'Review queue', 'wp-kbms' ); ?></h2><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=kbms_item&kbms_review=due' ) ); ?>"><?php echo esc_html__( 'Open queue', 'wp-kbms' ); ?></a></section>
			</div>
		</div>
		<?php
	}

	public function renderSettings(): void {
		if ( ! current_user_can( 'manage_kbms' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage KBMS.', 'wp-kbms' ) );
		}
		$settings = wp_parse_args( (array) get_option( self::OPTION, array() ), self::DEFAULTS );
		?>
		<div class="wrap kbms-admin">
			<h1><?php echo esc_html__( 'KBMS Settings', 'wp-kbms' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'kbms' ); ?>
				<table class="form-table" role="presentation"><tbody>
				<?php
				foreach ( array(
					'ai_enabled'              => __( 'AI assistant', 'wp-kbms' ),
					'graph_enabled'           => __( 'Knowledge graph', 'wp-kbms' ),
					'analytics_enabled'       => __( 'Privacy-conscious analytics', 'wp-kbms' ),
					'git_sync_enabled'        => __( 'Git synchronization', 'wp-kbms' ),
					'mcp_enabled'             => __( 'MCP API', 'wp-kbms' ),
					'external_search_enabled' => __( 'External search provider', 'wp-kbms' ),
				) as $key => $label ) :
					?>
					<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><label><input type="checkbox" name="kbms_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>> <?php echo esc_html__( 'Enabled', 'wp-kbms' ); ?></label></td></tr>
				<?php endforeach; ?>
					<tr><th scope="row"><label for="kbms-confidence"><?php echo esc_html__( 'AI confidence threshold', 'wp-kbms' ); ?></label></th><td><input id="kbms-confidence" type="number" min="0" max="100" name="kbms_settings[confidence_threshold]" value="<?php echo esc_attr( (string) $settings['confidence_threshold'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="kbms-review-days"><?php echo esc_html__( 'Default review interval (days)', 'wp-kbms' ); ?></label></th><td><input id="kbms-review-days" type="number" min="1" max="3650" name="kbms_settings[review_interval_days]" value="<?php echo esc_attr( (string) $settings['review_interval_days'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="kbms-hosts"><?php echo esc_html__( 'Allowed integration hosts', 'wp-kbms' ); ?></label></th><td><textarea id="kbms-hosts" class="large-text code" rows="5" name="kbms_settings[allowed_webhook_hosts]"><?php echo esc_textarea( implode( "\n", (array) $settings['allowed_webhook_hosts'] ) ); ?></textarea><p class="description"><?php echo esc_html__( 'One DNS hostname per line. Private and loopback destinations remain blocked.', 'wp-kbms' ); ?></p></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Uninstall data', 'wp-kbms' ); ?></th><td><label><input type="checkbox" name="kbms_delete_data_on_uninstall" value="1" <?php checked( (bool) get_option( 'kbms_delete_data_on_uninstall', false ) ); ?>> <?php echo esc_html__( 'Permanently delete every KBMS item, table, role, and setting when the plugin is deleted.', 'wp-kbms' ); ?></label><p class="description"><?php echo esc_html__( 'This is destructive and cannot be undone without a backup. Deactivation never deletes data.', 'wp-kbms' ); ?></p></td></tr>
				</tbody></table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
