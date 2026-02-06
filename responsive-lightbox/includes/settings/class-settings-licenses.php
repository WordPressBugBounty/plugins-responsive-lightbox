<?php
// exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Responsive_Lightbox_Settings_Licenses class.
 *
 * Settings page class for Licenses tab migration to new Settings API.
 *
 * @class Responsive_Lightbox_Settings_Licenses
 */
class Responsive_Lightbox_Settings_Licenses extends Responsive_Lightbox_Settings_Base {

	/**
	 * Tab key identifier.
	 *
	 * @var string
	 */
	const TAB_KEY = 'licenses';

	/**
	 * Register this tab as migrated to new API.
	 *
	 * Override - only register if extensions exist.
	 *
	 * @param array $tabs Migrated tabs.
	 * @return array
	 */
	public function register_migrated_tab( $tabs ) {
		$extensions = apply_filters( 'rl_settings_licenses', [] );
		if ( $extensions ) {
			$tabs[] = self::TAB_KEY;
		}
		return $tabs;
	}

	/**
	 * Validate settings for licenses tab.
	 *
	 * Override to use licenses-specific validation.
	 *
	 * @param array $input Input data from form submission.
	 * @return array Validated data.
	 */
	public function validate( $input ) {
		// use licenses-specific validation
		return $this->validate_licenses( $input );
	}

	/**
	 * Provide settings data for licenses.
	 *
	 * @param array $data Settings data.
	 * @return array
	 */
	public function settings_data( $data ) {
		// get extensions
		$extensions = apply_filters( 'rl_settings_licenses', [] );

		if ( $extensions ) {
			$data[self::TAB_KEY] = [
				'option_name'  => 'responsive_lightbox_licenses',
				'option_group' => 'responsive_lightbox_licenses',
				'validate'     => [ $this, 'validate' ],
				'sections'     => [
					'responsive_lightbox_licenses' => [
						'title'    => __( 'Licenses', 'responsive-lightbox' ),
						'callback' => [ $this, 'licenses_section_cb' ]
					]
				],
				'fields'       => []
			];

			// add fields for each extension
			foreach ( $extensions as $id => $extension ) {
				$data[self::TAB_KEY]['fields'][$id] = [
					'title'       => $extension['name'],
					'section'     => 'responsive_lightbox_licenses',
					'type'        => 'custom',
					'callback'    => [ $this, 'license_field_cb' ],
					'callback_args' => $extension
				];
			}
		}

		return $data;
	}

	/**
	 * Licenses section callback.
	 *
	 * @return void
	 */
	public function licenses_section_cb() {
		?><p class="description"><?php esc_html_e( 'A list of licenses for your Responsive Lightbox & Gallery extensions.', 'responsive-lightbox' ); ?></p><?php
	}

	/**
	 * License field callback.
	 *
	 * @param array $args
	 * @return void
	 */
	public function license_field_cb( $args ) {
		// get extension data from callback_args (new API) or directly from args (legacy)
		$extension = ! empty( $args['callback_args'] ) ? $args['callback_args'] : $args;
		$extension_id = ! empty( $extension['id'] ) ? $extension['id'] : '';
		$extension_name = ! empty( $extension['name'] ) ? $extension['name'] : '';

		$licenses = get_option( 'responsive_lightbox_licenses' );

		if ( ! empty( $licenses ) && ! empty( $extension_id ) ) {
			$license = isset( $licenses[$extension_id]['license'] ) ? $licenses[$extension_id]['license'] : '';
			$status = ! empty( $licenses[$extension_id]['status'] );
		} else {
			$license = '';
			$status = false;
		} ?>
		<fieldset class="rl_license rl_license-<?php echo esc_attr( $extension_id ); ?>">
			<input type="text" class="regular-text" name="responsive_lightbox_licenses[<?php echo esc_attr( $extension_id ); ?>][license]" value="<?php echo esc_attr( $license ); ?>"><span class="dashicons <?php echo ( $status ? 'dashicons-yes' : 'dashicons-no' ); ?>"></span>
			<p class="description"><?php echo esc_html( sprintf( __( 'Enter your license key to activate %s extension and enable automatic upgrade notices.', 'responsive-lightbox' ), $extension_name ) ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Validate licenses function.
	 * 
	 * Handles license activation/deactivation with external license server API.
	 * Called by validate() method.
	 *
	 * @param array $input
	 * @return array
	 */
	private function validate_licenses( $input ) {
		// check cap
		if ( ! current_user_can( apply_filters( 'rl_lightbox_settings_capability', Responsive_Lightbox()->options['capabilities']['active'] ? 'edit_lightbox_settings' : 'manage_options' ) ) )
			return $input;

		// check option page
		$option_page = isset( $_POST['option_page'] ) ? sanitize_key( $_POST['option_page'] ) : '';

		// check page
		if ( ! $option_page )
			return $input;

		$rl_licenses = [];

		if ( isset( $_POST['responsive_lightbox_licenses'] ) && is_array( $_POST['responsive_lightbox_licenses'] ) && ! empty( $_POST['responsive_lightbox_licenses'] ) ) {
			foreach ( $_POST['responsive_lightbox_licenses'] as $extension => $data ) {
				$ext = sanitize_key( $extension );

				if ( is_array( $data ) && ! empty( $data['license'] ) )
					$rl_licenses[$ext]['license'] = preg_replace( '/[^a-z0-9]/i', '', $data['license'] );
				else
					$rl_licenses[$ext]['license'] = '';
			}
		}

		// check data
		if ( ! $rl_licenses )
			return $input;

		// get extension licenses
		$extensions = apply_filters( 'rl_settings_licenses', [] );

		if ( empty( $extensions ) )
			return $input;

		// save settings
		if ( isset( $_POST['save_responsive_lightbox_licenses'] ) || isset( $_POST['save_rl_licenses'] ) ) {
			$licenses = get_option( 'responsive_lightbox_licenses' );
			$statuses = [ 'updated' => 0, 'error' => 0 ];

			foreach ( $extensions as $extension ) {
				if ( ! isset( $rl_licenses[$extension['id']] ) )
					continue;

				$license = $rl_licenses[$extension['id']]['license'];
				$status = ! empty( $licenses ) && ! empty( $licenses[$extension['id']]['status'] );

				// update license
				$input[$extension['id']]['license'] = $license;

				// request data
				$request_args = [
					'action'	=> 'activate_license',
					'license'	=> $license,
					'item_name'	=> $extension['item_name']
				];

				// request
				$response = $this->license_request( $request_args );

				// validate request
				if ( is_wp_error( $response ) ) {
					$input[$extension['id']]['status'] = false;
					$statuses['error']++;
				} else {
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );

					// assign the data
					if ( $license_data->license === 'valid' ) {
						$input[$extension['id']]['status'] = true;

						if ( $status === false )
							$statuses['updated']++;
					} else {
						$input[$extension['id']]['status'] = false;
						$statuses['error']++;
					}
				}
			}

			// success notice
			if ( $statuses['updated'] > 0 )
				add_settings_error( 'rl_licenses_settings', 'license_activated', esc_html( sprintf( _n( '%s license successfully activated.', '%s licenses successfully activated.', (int) $statuses['updated'], 'responsive-lightbox' ), (int) $statuses['updated'] ) ), 'updated' );

			// failed notice
			if ( $statuses['error'] > 0 )
				add_settings_error( 'rl_licenses_settings', 'license_activation_failed', esc_html( sprintf( _n( '%s license activation failed.', '%s licenses activation failed.', (int) $statuses['error'], 'responsive-lightbox' ), (int) $statuses['error'] ) ), 'error' );
		} elseif ( isset( $_POST['reset_responsive_lightbox_licenses'] ) || isset( $_POST['reset_rl_licenses'] ) ) {
			$licenses = get_option( 'responsive_lightbox_licenses' );
			$statuses = [
				'updated'	=> 0,
				'error'		=> 0
			];

			foreach ( $extensions as $extension ) {
				$license = ! empty( $licenses ) && isset( $licenses[$extension['id']]['license'] ) ? $licenses[$extension['id']]['license'] : '';
				$status = ! empty( $licenses ) && ! empty( $licenses[$extension['id']]['status'] );

				if ( $status === true || ( $status === false && ! empty( $license ) ) ) {
					// request data
					$request_args = [
						'action'	=> 'deactivate_license',
						'license'	=> trim( $license ),
						'item_name'	=> $extension['item_name']
					];

					// request
					$response = $this->license_request( $request_args );

					// validate request
					if ( is_wp_error( $response ) )
						$statuses['error']++;
					else {
						// decode the license data
						$license_data = json_decode( wp_remote_retrieve_body( $response ) );

						// assign the data
						if ( $license_data->license == 'deactivated' ) {
							$input[$extension['id']]['license'] = '';
							$input[$extension['id']]['status'] = false;

							$statuses['updated']++;
						} else
							$statuses['error']++;
					}
				}
			}

			// success notice
			if ( $statuses['updated'] > 0 )
				add_settings_error( 'rl_licenses_settings', 'license_deactivated', esc_html( sprintf( _n( '%s license successfully deactivated.', '%s licenses successfully deactivated.', (int) $statuses['updated'], 'responsive-lightbox' ), (int) $statuses['updated'] ) ), 'updated' );

			// failed notice
			if ( $statuses['error'] > 0 )
				add_settings_error( 'rl_licenses_settings', 'license_deactivation_failed', esc_html( sprintf( _n( '%s license deactivation failed.', '%s licenses deactivation failed.', (int) $statuses['error'], 'responsive-lightbox' ), (int) $statuses['error'] ) ), 'error' );
		}

		return $input;
	}

	/**
	 * License request function.
	 *
	 * @param array $args
	 * @return mixed
	 */
	private function license_request( $args ) {
		// data to send in our API request
		$api_params = [
			'edd_action'	=> $args['action'],
			'license'		=> sanitize_key( $args['license'] ),
			'item_name'		=> urlencode( $args['item_name'] ),
			// 'item_id'		=> $args['item_id'],
			'url'			=> home_url(),
			'timeout'		=> 60,
			'sslverify'		=> false
		];

		// call the custom API
		$response = wp_remote_get( add_query_arg( $api_params, 'https://www.dfactory.co' ) );

		return $response;
	}
}