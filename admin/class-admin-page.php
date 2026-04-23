<?php
/**
 * Admin settings page.
 *
 * @package AdminForge
 */

namespace AdminForge\Admin;

use AdminForge\Settings;
use AdminForge\Modules\Menu_Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Page {
	const CAPABILITY = 'manage_options';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Scanner.
	 *
	 * @var Menu_Scanner
	 */
	protected $scanner;

	public function __construct( Settings $settings, Menu_Scanner $scanner ) {
		$this->settings = $settings;
		$this->scanner  = $scanner;
	}

	/**
	 * Register the menu page.
	 */
	public function register() {
		add_menu_page(
			__( 'AdminForge', 'adminforge' ),
			__( 'AdminForge', 'adminforge' ),
			self::CAPABILITY,
			'adminforge',
			array( $this, 'render_page' ),
			'dashicons-shield-alt',
			3
		);
	}

	/**
	 * Save settings.
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage AdminForge.', 'adminforge' ) );
		}

		check_admin_referer( 'adminforge_save_settings' );

		$existing = $this->settings->get_settings();
		$raw      = isset( $_POST['adminforge'] ) ? (array) $_POST['adminforge'] : array();
		$clean    = $this->settings->sanitize_settings( $raw, $existing );

		$this->settings->save_settings( $clean );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'adminforge',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Refresh menu inventory.
	 */
	public function ajax_rescan_menus() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'adminforge' ) ), 403 );
		}

		check_ajax_referer( 'adminforge_ajax', 'nonce' );

		$inventory = $this->scanner->scan();
		$this->scanner->store_inventory( $inventory );

		wp_send_json_success(
			array(
				'message'   => __( 'Menu inventory refreshed.', 'adminforge' ),
				'count'     => count( $inventory['top_level'] ?? array() ),
				'inventory' => $inventory,
			)
		);
	}

	/**
	 * Search users for selector.
	 */
	public function ajax_search_users() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'adminforge' ) ), 403 );
		}

		check_ajax_referer( 'adminforge_ajax', 'nonce' );

		$term  = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$users = $this->settings->search_users( $term, 50 );

		wp_send_json_success( array( 'results' => $users ) );
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access AdminForge.', 'adminforge' ) );
		}

		$settings       = $this->settings->get_settings();
		$inventory      = $settings['menu_inventory'];
		$roles          = $this->settings->get_role_labels();
		$selected_roles  = (array) $settings['general']['target_roles'];
		$selected_users  = (array) $settings['general']['target_users'];
		?>
		<div class="wrap adminforge-wrap">
			<h1><?php echo esc_html__( 'AdminForge', 'adminforge' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Transform the WordPress admin into a cleaner, role-aware, white-label experience.', 'adminforge' ); ?></p>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'adminforge' ); ?></p></div>
			<?php endif; ?>

			<div class="adminforge-toolbar">
				<button type="button" class="button button-secondary" id="adminforge-rescan-menus"><?php echo esc_html__( 'Rescan Menus', 'adminforge' ); ?></button>
				<span class="adminforge-status" id="adminforge-scan-status"></span>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="adminforge-form">
				<input type="hidden" name="action" value="adminforge_save_settings" />
				<?php wp_nonce_field( 'adminforge_save_settings' ); ?>

				<div class="adminforge-tabs" data-adminforge-tabs>
					<a href="#tab-general" class="active"><?php echo esc_html__( 'General', 'adminforge' ); ?></a>
					<a href="#tab-menu"><?php echo esc_html__( 'Menu Visibility', 'adminforge' ); ?></a>
					<a href="#tab-submenu"><?php echo esc_html__( 'Submenu Visibility', 'adminforge' ); ?></a>
					<a href="#tab-dashboard"><?php echo esc_html__( 'Dashboard Control', 'adminforge' ); ?></a>
					<a href="#tab-rules"><?php echo esc_html__( 'Role Rules', 'adminforge' ); ?></a>
					<a href="#tab-users"><?php echo esc_html__( 'User Rules', 'adminforge' ); ?></a>
					<a href="#tab-global"><?php echo esc_html__( 'Global Rules', 'adminforge' ); ?></a>
					<a href="#tab-ui"><?php echo esc_html__( 'UI Customization', 'adminforge' ); ?></a>
					<a href="#tab-access"><?php echo esc_html__( 'Access Restrictions', 'adminforge' ); ?></a>
					<a href="#tab-branding"><?php echo esc_html__( 'Branding', 'adminforge' ); ?></a>
					<a href="#tab-advanced"><?php echo esc_html__( 'Advanced', 'adminforge' ); ?></a>
				</div>

				<div class="adminforge-panels">
					<section id="tab-general" class="adminforge-panel active">
						<h2><?php echo esc_html__( 'General Scope', 'adminforge' ); ?></h2>
						<p><?php echo esc_html__( 'Choose who receives the transformed admin experience.', 'adminforge' ); ?></p>
						<label><input type="checkbox" name="adminforge[general][enabled]" value="1" <?php checked( (int) $settings['general']['enabled'], 1 ); ?> /> <?php echo esc_html__( 'Enable AdminForge transformations', 'adminforge' ); ?></label>
						<div class="adminforge-grid">
							<div>
								<label for="adminforge-mode"><?php echo esc_html__( 'Target mode', 'adminforge' ); ?></label>
								<select name="adminforge[general][mode]" id="adminforge-mode">
									<option value="global" <?php selected( $settings['general']['mode'], 'global' ); ?>><?php echo esc_html__( 'Everyone', 'adminforge' ); ?></option>
									<option value="roles" <?php selected( $settings['general']['mode'], 'roles' ); ?>><?php echo esc_html__( 'Selected roles only', 'adminforge' ); ?></option>
									<option value="users" <?php selected( $settings['general']['mode'], 'users' ); ?>><?php echo esc_html__( 'Selected users only', 'adminforge' ); ?></option>
									<option value="roles_users" <?php selected( $settings['general']['mode'], 'roles_users' ); ?>><?php echo esc_html__( 'Selected roles and users', 'adminforge' ); ?></option>
								</select>
							</div>
							<div>
								<label for="adminforge-bypass"><?php echo esc_html__( 'Administrator bypass', 'adminforge' ); ?></label>
								<label><input type="checkbox" name="adminforge[general][bypass_for_admins]" value="1" <?php checked( (int) $settings['general']['bypass_for_admins'], 1 ); ?> /> <?php echo esc_html__( 'Let administrators bypass restrictions', 'adminforge' ); ?></label>
							</div>
						</div>
						<div class="adminforge-user-selector adminforge-general-users" id="adminforge-general-user-selector" data-selected="<?php echo esc_attr( implode( ',', array_map( 'absint', $selected_users ) ) ); ?>">
							<h3><?php echo esc_html__( 'Registered Users', 'adminforge' ); ?></h3>
							<p class="description"><?php echo esc_html__( 'Select one or more registered users here. These users will receive the transformed experience even if role rules differ.', 'adminforge' ); ?></p>
							<p>
								<label for="adminforge-user-search"><?php echo esc_html__( 'Search registered users', 'adminforge' ); ?></label><br />
								<input type="text" class="regular-text" id="adminforge-user-search" placeholder="<?php echo esc_attr__( 'Type a name, login, or email', 'adminforge' ); ?>" />
							</p>
							<div class="adminforge-user-results" id="adminforge-user-results"></div>
							<div class="adminforge-user-selected" id="adminforge-user-selected">
								<?php foreach ( $selected_users as $user_id ) : ?>
									<?php $user = get_userdata( $user_id ); ?>
									<?php if ( $user ) : ?>
										<span class="adminforge-chip" data-user-id="<?php echo esc_attr( $user_id ); ?>">
											<?php echo esc_html( $user->display_name ); ?>
											<button type="button" class="adminforge-chip-remove" aria-label="<?php echo esc_attr__( 'Remove user', 'adminforge' ); ?>">&times;</button>
											<input type="hidden" name="adminforge[general][target_users][]" value="<?php echo esc_attr( $user_id ); ?>" />
										</span>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</div>
					</section>

					<section id="tab-menu" class="adminforge-panel">
						<h2><?php echo esc_html__( 'Menu Visibility', 'adminforge' ); ?></h2>
						<div class="adminforge-grid">
							<div>
								<label><?php echo esc_html__( 'Menu mode', 'adminforge' ); ?></label>
								<select name="adminforge[visibility][menu_mode]">
									<option value="hide_selected" <?php selected( $settings['visibility']['menu_mode'], 'hide_selected' ); ?>><?php echo esc_html__( 'Hide selected menu items', 'adminforge' ); ?></option>
									<option value="show_only" <?php selected( $settings['visibility']['menu_mode'], 'show_only' ); ?>><?php echo esc_html__( 'Show only selected menu items', 'adminforge' ); ?></option>
								</select>
							</div>
							<div>
								<label><input type="checkbox" name="adminforge[visibility][restrict_direct_access]" value="1" <?php checked( (int) $settings['visibility']['restrict_direct_access'], 1 ); ?> /> <?php echo esc_html__( 'Block direct access to hidden menu pages', 'adminforge' ); ?></label>
							</div>
						</div>
						<p>
							<label for="adminforge-access-action"><?php echo esc_html__( 'Access response', 'adminforge' ); ?></label><br />
							<select name="adminforge[visibility][access_action]" id="adminforge-access-action">
								<option value="redirect" <?php selected( $settings['visibility']['access_action'], 'redirect' ); ?>><?php echo esc_html__( 'Redirect to safe page', 'adminforge' ); ?></option>
								<option value="deny" <?php selected( $settings['visibility']['access_action'], 'deny' ); ?>><?php echo esc_html__( 'Show access denied message', 'adminforge' ); ?></option>
							</select>
						</p>
						<p><label for="adminforge-menu-search"><?php echo esc_html__( 'Search menus', 'adminforge' ); ?></label> <input type="search" id="adminforge-menu-search" class="regular-text" placeholder="<?php echo esc_attr__( 'Type to filter menu items', 'adminforge' ); ?>" /></p>
						<div class="adminforge-inventory" data-filter-target="menu">
							<?php echo $this->render_menu_inventory( $inventory, (array) $settings['visibility']['menu_items'], 'visibility', 'menu_items' ); ?>
						</div>
					</section>

					<section id="tab-submenu" class="adminforge-panel">
						<h2><?php echo esc_html__( 'Submenu Visibility', 'adminforge' ); ?></h2>
						<p><?php echo esc_html__( 'Control submenu pages registered by core, themes, and plugins.', 'adminforge' ); ?></p>
						<div class="adminforge-inventory" data-filter-target="submenu">
							<?php echo $this->render_submenu_inventory( $inventory, (array) $settings['visibility']['submenu_items'] ); ?>
						</div>
					</section>

					<section id="tab-dashboard" class="adminforge-panel">
						<h2><?php echo esc_html__( 'Dashboard Control', 'adminforge' ); ?></h2>
						<p><?php echo esc_html__( 'Hide dashboard widgets and keep the dashboard focused.', 'adminforge' ); ?></p>
						<div class="adminforge-inventory">
							<?php echo $this->render_dashboard_widgets( $settings ); ?>
						</div>
					</section>

					<section id="tab-rules" class="adminforge-panel">
						<h2><?php echo esc_html__( 'Role Rules', 'adminforge' ); ?></h2>
						<p><?php echo esc_html__( 'Select roles that should receive the AdminForge experience.', 'adminforge' ); ?></p>
						<div class="adminforge-checklist">
							<?php foreach ( $roles as $role_key => $role_label ) : ?>
								<label>
									<input type="checkbox" name="adminforge[general][target_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $selected_roles, true ), true ); ?> />
									<?php echo esc_html( $role_label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</section>

					<section id="tab-users" class="adminforge-panel">
						<h2><?php echo esc_html__( 'User Rules', 'adminforge' ); ?></h2>
						<p><?php echo esc_html__( 'User-specific rules still work as a targeting layer, but user selection now lives in General Scope for easier management.', 'adminforge' ); ?></p>
					</section>

					<section id="tab-global" class="adminforge-panel">
						<h2><?php echo esc_html__( 'Global Rules', 'adminforge' ); ?></h2>
						<p><?php echo esc_html__( 'These settings define the shared admin transformation layer.', 'adminforge' ); ?></p>
						<label><input type="checkbox" name="adminforge[general][role_over_global]" value="1" <?php checked( (int) $settings['general']['role_over_global'], 1 ); ?> /> <?php echo esc_html__( 'Role-based assignments override global defaults', 'adminforge' ); ?></label><br />
						<label><input type="checkbox" name="adminforge[general][user_over_role]" value="1" <?php checked( (int) $settings['general']['user_over_role'], 1 ); ?> /> <?php echo esc_html__( 'User-based assignments override role-based assignments', 'adminforge' ); ?></label>
					</section>

					<section id="tab-ui" class="adminforge-panel">
						<h2><?php echo esc_html__( 'UI Customization', 'adminforge' ); ?></h2>
						<div class="adminforge-grid adminforge-color-grid">
							<?php $this->render_color_field( 'Sidebar Background', 'adminforge[ui][sidebar_bg]', $settings['ui']['sidebar_bg'] ); ?>
							<?php $this->render_color_field( 'Sidebar Text', 'adminforge[ui][sidebar_text]', $settings['ui']['sidebar_text'] ); ?>
							<?php $this->render_color_field( 'Accent', 'adminforge[ui][sidebar_accent]', $settings['ui']['sidebar_accent'] ); ?>
							<?php $this->render_color_field( 'Content Background', 'adminforge[ui][content_bg]', $settings['ui']['content_bg'] ); ?>
							<?php $this->render_color_field( 'Content Text', 'adminforge[ui][content_text]', $settings['ui']['content_text'] ); ?>
						</div>
						<p>
							<label><?php echo esc_html__( 'Font family', 'adminforge' ); ?>
								<input type="text" class="regular-text" name="adminforge[ui][font_family]" value="<?php echo esc_attr( $settings['ui']['font_family'] ); ?>" />
							</label>
						</p>
						<p>
							<label><?php echo esc_html__( 'Custom admin CSS', 'adminforge' ); ?></label><br />
							<textarea name="adminforge[ui][custom_css]" rows="8" class="large-text code"><?php echo esc_textarea( $settings['ui']['custom_css'] ); ?></textarea>
						</p>
						<p>
							<label><?php echo esc_html__( 'Custom admin JS', 'adminforge' ); ?></label><br />
							<textarea name="adminforge[ui][custom_js]" rows="8" class="large-text code"><?php echo esc_textarea( $settings['ui']['custom_js'] ); ?></textarea>
						</p>
						<label><input type="checkbox" name="adminforge[ui][hide_wp_logo]" value="1" <?php checked( (int) $settings['ui']['hide_wp_logo'], 1 ); ?> /> <?php echo esc_html__( 'Hide WordPress logo in the admin bar', 'adminforge' ); ?></label><br />
						<label><input type="checkbox" name="adminforge[ui][hide_screen_options]" value="1" <?php checked( (int) $settings['ui']['hide_screen_options'], 1 ); ?> /> <?php echo esc_html__( 'Hide Screen Options and Help tabs', 'adminforge' ); ?></label>
					</section>

					<section id="tab-access" class="adminforge-panel">
						<h2><?php echo esc_html__( 'Access Restrictions', 'adminforge' ); ?></h2>
						<p>
							<label><?php echo esc_html__( 'Redirect target', 'adminforge' ); ?>
								<input type="text" class="regular-text" name="adminforge[visibility][redirect_target]" value="<?php echo esc_attr( $settings['visibility']['redirect_target'] ); ?>" />
							</label>
						</p>
						<p>
							<label><?php echo esc_html__( 'Denied message', 'adminforge' ); ?></label><br />
							<textarea name="adminforge[visibility][deny_message]" rows="4" class="large-text"><?php echo esc_textarea( $settings['visibility']['deny_message'] ); ?></textarea>
						</p>
					</section>

					<section id="tab-branding" class="adminforge-panel">
						<h2><?php echo esc_html__( 'Branding', 'adminforge' ); ?></h2>
						<p>
							<label><?php echo esc_html__( 'Custom admin logo URL', 'adminforge' ); ?>
								<input type="url" class="regular-text" name="adminforge[branding][custom_logo]" value="<?php echo esc_attr( $settings['branding']['custom_logo'] ); ?>" />
							</label>
						</p>
						<p>
							<label><?php echo esc_html__( 'Login page logo URL', 'adminforge' ); ?>
								<input type="url" class="regular-text" name="adminforge[branding][login_logo]" value="<?php echo esc_attr( $settings['branding']['login_logo'] ); ?>" />
							</label>
						</p>
						<p>
							<label><input type="checkbox" name="adminforge[branding][hide_branding]" value="1" <?php checked( (int) $settings['branding']['hide_branding'], 1 ); ?> /> <?php echo esc_html__( 'Hide WordPress branding where safe', 'adminforge' ); ?></label>
						</p>
						<p>
							<label><?php echo esc_html__( 'Footer text', 'adminforge' ); ?></label><br />
							<textarea name="adminforge[branding][footer_text]" rows="3" class="large-text"><?php echo esc_textarea( $settings['branding']['footer_text'] ); ?></textarea>
						</p>
					</section>

					<section id="tab-advanced" class="adminforge-panel">
						<h2><?php echo esc_html__( 'Advanced', 'adminforge' ); ?></h2>
						<label><input type="checkbox" name="adminforge[advanced][cache_inventory]" value="1" <?php checked( (int) $settings['advanced']['cache_inventory'], 1 ); ?> /> <?php echo esc_html__( 'Cache scanned admin menus', 'adminforge' ); ?></label><br />
						<label><input type="checkbox" name="adminforge[advanced][enable_ajax]" value="1" <?php checked( (int) $settings['advanced']['enable_ajax'], 1 ); ?> /> <?php echo esc_html__( 'Enable AJAX helpers in the admin UI', 'adminforge' ); ?></label><br />
						<label><input type="checkbox" name="adminforge[advanced][debug_mode]" value="1" <?php checked( (int) $settings['advanced']['debug_mode'], 1 ); ?> /> <?php echo esc_html__( 'Debug mode', 'adminforge' ); ?></label>
					</section>
				</div>

				<?php submit_button( __( 'Save Settings', 'adminforge' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render menu inventory.
	 *
	 * @param array<string, mixed> $inventory Inventory.
	 * @param array<int, string>   $selected Selected IDs.
	 * @param string               $group Group key.
	 * @param string               $field Field key.
	 * @return string
	 */
	protected function render_menu_inventory( array $inventory, array $selected, $group, $field ) {
		ob_start();

		foreach ( (array) $inventory['top_level'] as $item ) {
			$key = (string) $item['slug'];
			?>
			<label class="adminforge-item">
								<input type="checkbox" name="adminforge[<?php echo esc_attr( $group ); ?>][<?php echo esc_attr( $field ); ?>][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $this->settings->menu_item_is_selected( $key, $selected ), true ); ?> />
				<span class="adminforge-item-title"><?php echo esc_html( $item['title'] ); ?></span>
				<span class="adminforge-item-meta"><?php echo esc_html( $item['slug'] ); ?></span>
			</label>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Render submenu inventory.
	 *
	 * @param array<string, mixed> $inventory Inventory.
	 * @param array<int, string>   $selected Selected IDs.
	 * @return string
	 */
	protected function render_submenu_inventory( array $inventory, array $selected ) {
		ob_start();

		foreach ( (array) $inventory['submenus'] as $parent => $items ) :
			?>
			<div class="adminforge-submenu-group">
				<h3><?php echo esc_html( $parent ); ?></h3>
				<?php foreach ( (array) $items as $item ) : ?>
					<?php $key = (string) $parent . '::' . (string) $item['slug']; ?>
					<label class="adminforge-item">
						<input type="checkbox" name="adminforge[visibility][submenu_items][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $this->settings->submenu_item_is_selected( $parent, $item['slug'], $selected ), true ); ?> />
						<span class="adminforge-item-title"><?php echo esc_html( $item['title'] ); ?></span>
						<span class="adminforge-item-meta"><?php echo esc_html( $item['slug'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php
		endforeach;

		return ob_get_clean();
	}

	/**
	 * Render dashboard widgets.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	protected function render_dashboard_widgets( array $settings ) {
		$inventory = $settings['dashboard_inventory'];
		$selected  = (array) $settings['visibility']['dashboard_widgets'];

		ob_start();

		if ( empty( $inventory['widgets'] ) ) {
			?>
			<p><?php echo esc_html__( 'No dashboard widget inventory is available yet. Visit the Dashboard screen or use the refresh action after widgets have been registered.', 'adminforge' ); ?></p>
			<?php
		}

		foreach ( (array) $inventory['widgets'] as $widget ) :
			$key = (string) $widget['id'];
			?>
			<label class="adminforge-item">
				<input type="checkbox" name="adminforge[visibility][dashboard_widgets][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected, true ), true ); ?> />
				<span class="adminforge-item-title"><?php echo esc_html( $widget['title'] ); ?></span>
				<span class="adminforge-item-meta"><?php echo esc_html( $widget['id'] . ' / ' . $widget['context'] ); ?></span>
			</label>
			<?php
		endforeach;

		return ob_get_clean();
	}

	/**
	 * Render color field.
	 *
	 * @param string $label Label.
	 * @param string $name Field name.
	 * @param string $value Value.
	 */
	protected function render_color_field( $label, $name, $value ) {
		?>
		<p class="adminforge-color-field">
			<label><?php echo esc_html( $label ); ?><br />
				<input type="text" class="regular-text adminforge-color-input" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
				<span class="adminforge-color-swatch" style="background: <?php echo esc_attr( $value ); ?>"></span>
			</label>
		</p>
		<?php
	}
}
