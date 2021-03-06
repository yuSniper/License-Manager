<?php

if ( class_exists( 'Yoast_Update_Manager_v2' ) && ! class_exists( 'Yoast_Theme_Update_Manager_v2', false ) ) {

	class Yoast_Theme_Update_Manager_v2 extends Yoast_Update_Manager_v2 {

		/**
		 * Constructor
		 *
		 * @param Yoast_Product_v2 $product     The Product.
		 * @param string           $license_key The License key.
		 */
		public function __construct( Yoast_Product_v2 $product, $license_key ) {
			parent::__construct( $product, $license_key );

			// setup hooks
			$this->setup_hooks();
		}

		/**
		 * Get the current theme version
		 *
		 * @return string The version number
		 */
		private function get_theme_version() {

			// if version was not set, get it from the Theme stylesheet
			if ( $this->product->get_version() === '' ) {
				$theme = wp_get_theme( $this->product->get_slug() );

				return $theme->get( 'Version' );
			}

			return $this->product->get_version();
		}

		/**
		 * Setup hooks
		 */
		private function setup_hooks() {
			add_filter( 'site_transient_update_themes', array( $this, 'set_theme_update_transient' ) );
			add_action( 'load-themes.php', array( $this, 'load_themes_screen' ) );
		}

		/**
		 * Set "updates available" transient
		 */
		public function set_theme_update_transient( $value ) {

			$update_data = $this->get_update_data();

			if ( $update_data === false ) {
				return $value;
			}

			// add update data to "updates available" array. convert object to array.
			$value->response[ $this->product->get_slug() ] = (array) $update_data;

			return $value;
		}

		/**
		 * Add hooks and scripts to the Appearance > Themes screen
		 */
		public function load_themes_screen() {

			$update_data = $this->get_update_data();

			// only do if an update is available
			if ( $update_data === false ) {
				return;
			}

			add_thickbox();
			add_action( 'admin_notices', array( $this, 'show_update_details' ) );
		}

		/**
		 * Show update link.
		 * Opens Thickbox with Changelog.
		 */
		public function show_update_details() {

			$update_data = $this->get_update_data();

			// only show if an update is available
			if ( $update_data === false ) {
				return;
			}

			$update_url     = wp_nonce_url( 'update.php?action=upgrade-theme&amp;theme=' . urlencode( $this->product->get_slug() ), 'upgrade-theme_' . $this->product->get_slug() );
			$update_onclick = ' onclick="if ( confirm(\'' . esc_js( __( "Updating this theme will lose any customizations you have made. 'Cancel' to stop, 'OK' to update." ) ) . '\') ) {return true;}return false;"';
			?>
			<div id="update-nag">
				<?php
				/* translators: %1$s expands to product name, %2$s expands to version, %3$s expands to changelog HTML link, %4$s expands to closing HTML link tag, %5$s expands to update HTML link */
				printf(
					__( '<strong>%1$s version %2$s</strong> is available. %3$sCheck out what\'s new%4$s or %5$supdate now%4$s.' ),
					$this->product->get_item_name(),
					$update_data->new_version,
					'<a href="#TB_inline?width=640&amp;inlineId=' . $this->product->get_slug() . '_changelog" class="thickbox" title="' . $this->get_item_name() . '">',
					'</a>',
					'<a href="' . $update_url . '" ' . $update_onclick . '>'
				);
				?>
			</div>
			<div id="<?php echo esc_attr( $this->product->get_slug() . '_changelog' ); ?>" style="display: none;">
				<?php echo wpautop( $update_data->sections['changelog'] ); ?>
			</div>
			<?php
		}

		/**
		 * Get update data
		 *
		 * This gets the update data from a transient (12 hours), if set.
		 * If not, it will make a remote request and get the update data.
		 *
		 * @return object $update_data Object containing the update data
		 */
		public function get_update_data() {

			$api_response = $this->get_remote_data();

			if ( false === $api_response ) {
				return false;
			}

			$update_data = $api_response;

			// check if a new version is available.
			if ( version_compare( $this->get_theme_version(), $update_data->new_version, '>=' ) ) {
				return false;
			}

			// an update is available
			return $update_data;
		}
	}

}
