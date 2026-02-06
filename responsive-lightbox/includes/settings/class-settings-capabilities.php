<?php
/**
 * Responsive Lightbox Capabilities Settings
 *
 * Manages the Capabilities settings tab using the Settings API.
 *
 * @package responsive-lightbox
 */

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive Lightbox Capabilities Settings class.
 *
 * @class Responsive_Lightbox_Settings_Capabilities
 */
class Responsive_Lightbox_Settings_Capabilities extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 */
	const TAB_KEY = 'capabilities';

	/**
	 * Get priority for settings data filter.
	 *
	 * @return int
	 */
	protected function get_settings_data_priority() {
		return 100; // load after legacy settings
	}

	/**
	 * Validate settings for this tab.
	 *
	 * Override to use capabilities-specific validation.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		// use capabilities-specific validation
		return $this->validate_capabilities( $input );
	}

	/**
	 * Provide settings data for this tab.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function settings_data( $data ) {
		// get main instance
		$rl = Responsive_Lightbox();

		$data[self::TAB_KEY] = [
			'option_name'	=> 'responsive_lightbox_capabilities',
			'option_group'	=> 'responsive_lightbox_capabilities',
			'validate'		=> [ $this, 'validate' ],
			'sections'		=> [
				'responsive_lightbox_capabilities_fields' => [
					'title'			=> __( 'Capabilities Settings', 'responsive-lightbox' ),
					'description'	=> '',
					'fields'		=> [
						'active' => [
							'title'			=> __( 'Capabilities', 'responsive-lightbox' ),
							'type'			=> 'boolean',
							'label'			=> __( 'Enable advanced capability management.', 'responsive-lightbox' ),
							'description'	=> __( 'Check this to enable access to plugin features for selected user roles.', 'responsive-lightbox' )
						]
					]
				],
				'responsive_lightbox_capabilities' => [
					'title'		=> '',
					'callback'	=> [ $this, 'capabilities_table' ]
				]
			]
		];

		return $data;
	}

	/**
	 * Render capabilities table section.
	 *
	 * @global object $wp_roles
	 *
	 * @return void
	 */
	public function capabilities_table() {
		global $wp_roles;

		// get available user roles
		$editable_roles = get_editable_roles();

		echo '
		<br class="clear" />
		<table class="widefat fixed posts">
			<thead>
				<tr>
					<th>' . esc_html__( 'Role', 'responsive-lightbox' ) . '</th>';

		foreach ( $editable_roles as $role_name => $role_info ) {
			echo '<th>' . esc_html( isset( $wp_roles->role_names[$role_name] ) ? translate_user_role( $wp_roles->role_names[$role_name] ) : $role_name ) . '</th>';
		}

		echo '
				</tr>
			</thead>
			<tbody id="the-list">';

		$i = 0;

		foreach ( Responsive_Lightbox()->get_data( 'capabilities' ) as $cap_role => $cap_label ) {
			echo '
				<tr' . ( ( $i++ % 2 === 0 ) ? ' class="alternate"' : '' ) . '>
					<td>' . esc_html__( $cap_label, 'responsive-lightbox' ) . '</td>';

			foreach ( $editable_roles as $role_name => $role_info ) {
				// get user role
				$role = $wp_roles->get_role( $role_name );

				echo '
					<td>
						<input type="checkbox" name="responsive_lightbox_capabilities[roles][' . esc_attr( $role->name ) . '][' . esc_attr( $cap_role ) . ']" value="true" ' . checked( true, ( $role->has_cap( $cap_role ) || $role_name === 'administrator' ), false ) . ' ' . disabled( $role_name, 'administrator', false ) . ' />
					</td>';
			}

			echo '
				</tr>';
		}

		echo '
			</tbody>
		</table>';
	}

	/**
	 * Validate capabilities.
	 * 
	 * Handles WordPress role/capability management for plugin access control.
	 *
	 * @global object $wp_roles
	 *
	 * @param array $input
	 * @return array
	 */
	private function validate_capabilities( $input ) {
		// get main instance
		$rl = Responsive_Lightbox();

		// check capability
		if ( ! current_user_can( apply_filters( 'rl_lightbox_settings_capability', $rl->options['capabilities']['active'] ? 'edit_lightbox_settings' : 'manage_options' ) ) )
			return $input;

		global $wp_roles;

		// sanitize the 'active' boolean field manually (simple field, no complex logic needed)
		if ( isset( $input['active'] ) ) {
			$input['active'] = (bool) $input['active'];
		} else {
			$input['active'] = false;
		}

		// if capabilities are being enabled, grant them immediately before redirect
		if ( ( isset( $_POST['save_rl_capabilities'] ) || isset( $_POST['save_responsive_lightbox_capabilities'] ) ) && ! empty( $input['active'] ) && empty( $rl->options['capabilities']['active'] ) ) {
			// temporarily set the option so grant_capabilities works
			$rl->options['capabilities']['active'] = true;
			$rl->grant_capabilities();
		}

		// save capabilities?
		if ( isset( $_POST['save_rl_capabilities'] ) || isset( $_POST['save_responsive_lightbox_capabilities'] ) ) {
			foreach ( $wp_roles->roles as $role_name => $role_label ) {
				// get user role
				$role = $wp_roles->get_role( $role_name );

				// manage new capabilities only for non-admins
				if ( $role_name !== 'administrator' ) {
					foreach ( $rl->get_data( 'capabilities' ) as $capability => $label ) {
						if ( isset( $input['roles'][$role_name][$capability] ) && $input['roles'][$role_name][$capability] === 'true' )
							$role->add_cap( $capability );
						else
							$role->remove_cap( $capability );
					}
				}
			}
		// reset capabilities?
		} elseif ( isset( $_POST['reset_rl_capabilities'] ) || isset( $_POST['reset_responsive_lightbox_capabilities'] ) ) {
			foreach ( $wp_roles->roles as $role_name => $display_name ) {
				// get user role
				$role = $wp_roles->get_role( $role_name );

				foreach ( $rl->get_data( 'capabilities' ) as $capability => $label ) {
					if ( array_key_exists( $role_name, $rl->defaults['capabilities']['roles'] ) && in_array( $capability, $rl->defaults['capabilities']['roles'][$role_name], true ) )
						$role->add_cap( $capability );
					else
						$role->remove_cap( $capability );
				}
			}

			add_settings_error( 'reset_rl_capabilities', 'settings_restored', esc_html__( 'Settings restored to defaults.', 'responsive-lightbox' ), 'updated' );
		}

		return $input;
	}
}
