<?php

declare(strict_types=1);

namespace KBMS\Admin;

use DomainException;
use KBMS\Core\Hookable;
use KBMS\Knowledge\Space;
use KBMS\Knowledge\SpaceRepository;

final class SpacesPage implements Hookable {

	public function __construct( private readonly SpaceRepository $spaces ) {
	}

	public function registerHooks(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_action( 'admin_post_kbms_create_space', array( $this, 'create' ) );
	}

	public function registerMenu(): void {
		add_submenu_page( 'kbms', __( 'Knowledge Spaces', 'wp-kbms' ), __( 'Spaces', 'wp-kbms' ), 'manage_kbms_spaces', 'kbms-spaces', array( $this, 'render' ) );
	}

	public function create(): void {
		if ( ! current_user_can( 'manage_kbms_spaces' ) ) {
			wp_die( esc_html__( 'You cannot create knowledge spaces.', 'wp-kbms' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'kbms_create_space' );
		$name        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$slug        = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$visibility  = isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : 'private';
		try {
			$this->spaces->create( $name, $slug, $description, $visibility, get_current_user_id() );
			$url = add_query_arg( 'created', '1', admin_url( 'admin.php?page=kbms-spaces' ) );
		} catch ( DomainException $error ) {
			$url = add_query_arg( 'error', rawurlencode( $error->getMessage() ), admin_url( 'admin.php?page=kbms-spaces' ) );
		}
		wp_safe_redirect( $url );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_kbms_spaces' ) ) {
			wp_die( esc_html__( 'You cannot manage knowledge spaces.', 'wp-kbms' ) );
		}
		$spaces = $this->spaces->all();
		?>
		<div class="wrap kbms-admin">
			<h1><?php echo esc_html__( 'Knowledge Spaces', 'wp-kbms' ); ?></h1>
			<?php if ( isset( $_GET['created'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag. ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Space created.', 'wp-kbms' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only error message. ?>
				<div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></p></div>
			<?php endif; ?>
			<div class="kbms-admin-grid">
				<section class="kbms-card"><h2><?php echo esc_html__( 'Create a space', 'wp-kbms' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="kbms_create_space"><?php wp_nonce_field( 'kbms_create_space' ); ?>
						<p><label for="kbms-space-name"><?php echo esc_html__( 'Name', 'wp-kbms' ); ?></label><input class="regular-text" id="kbms-space-name" name="name" required></p>
						<p><label for="kbms-space-slug"><?php echo esc_html__( 'Slug', 'wp-kbms' ); ?></label><input class="regular-text" id="kbms-space-slug" name="slug"></p>
						<p><label for="kbms-space-description"><?php echo esc_html__( 'Description', 'wp-kbms' ); ?></label><textarea class="large-text" id="kbms-space-description" name="description" rows="4"></textarea></p>
						<p><label for="kbms-space-visibility"><?php echo esc_html__( 'Visibility', 'wp-kbms' ); ?></label><select id="kbms-space-visibility" name="visibility">
						<?php
						foreach ( Space::VISIBILITIES as $visibility ) :
							?>
							<option value="<?php echo esc_attr( $visibility ); ?>"><?php echo esc_html( ucfirst( $visibility ) ); ?></option><?php endforeach; ?>
						</select></p><?php submit_button( __( 'Create space', 'wp-kbms' ) ); ?>
					</form>
				</section>
				<section class="kbms-card"><h2><?php echo esc_html__( 'Existing spaces', 'wp-kbms' ); ?></h2>
				<?php
				if ( $spaces === array() ) :
					?>
					<p><?php echo esc_html__( 'No spaces yet. Create the first governed area for your knowledge.', 'wp-kbms' ); ?></p>
					<?php
				else :
					?>
					<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Name', 'wp-kbms' ); ?></th><th><?php echo esc_html__( 'Visibility', 'wp-kbms' ); ?></th><th><?php echo esc_html__( 'Owner', 'wp-kbms' ); ?></th></tr></thead><tbody>
					<?php
					foreach ( $spaces as $space ) :
						?>
						<tr><td><?php echo esc_html( $space->name ); ?></td><td><?php echo esc_html( $space->visibility ); ?></td><td><?php echo esc_html( get_the_author_meta( 'display_name', $space->ownerId ) ); ?></td></tr><?php endforeach; ?>
				</tbody></table><?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}
}
