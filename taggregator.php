<?php

/*
 * Plugin Name: Taggregator
 * Plugin URI: http://github.com/georgestephanis/taggregator/
 * Description: A social media aggregator.
 * Author: George Stephanis
 * Version: 1.0
 * Author URI: http://stephanis.info/
 */

class Taggregator {
	var $options;

	static $instance;

	function __construct() {
		self::$instance = $this;

		add_action( 'taggregator_cron', array( $this, 'fetch' )          );
		add_action( 'init',             array( $this, 'load_providers' ) );
		add_action( 'admin_init',       array( $this, 'admin_init' )     );
	}

	function get_option( $key ) {
		$options = get_option( 'taggregator_options', array() );
		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}
		return null;
	}

	function admin_init() {
		add_settings_section(
			'taggregator',
			esc_html__( 'Taggregator Social Media Aggregator', 'taggregator' ),
			array( $this, 'taggregator_settings_section' ),
			'general'
		);

		add_settings_field(
			'taggregator_active',
			sprintf( '<label for="taggregator_active">%1$s</label>', __( 'Actively Scraping?', 'taggregator' ) ),
			array( $this, 'taggregator_active_cb' ),
			'general',
			'taggregator'
		);

		add_settings_field(
			'taggregator_tag',
			sprintf( '<label for="taggregator_tag">%1$s</label>', __( 'Tag:', 'taggregator' ) ),
			array( $this, 'taggregator_tag_cb' ),
			'general',
			'taggregator'
		);

		register_setting( 'general', 'taggregator_options', array( $this, 'sanitize_options' ) );
	}

	function taggregator_settings_section() {
		?>

		<p id="taggregator-settings-section">
			<?php _e( 'Taggregator is a Social Media Aggregator.  When active, it will accumulate messages from assorted Social Media sites that mention your specified hashtag, and saved as Custom Post Type entries.  Accumulated messages can then be displayed as desired.', 'taggregator' ); ?>
		</p>

		<?php
	}

	function taggregator_active_cb() {
		?>
		<input type="checkbox" id="taggregator_active" name="taggregator_options[active]" <?php checked( $this->get_option( 'active' ) ); ?> />
		<?php
	}

	function taggregator_tag_cb() {
		?>
		<input class="regular-text" type="text" id="taggregator_tag" name="taggregator_options[tag]" value="<?php echo esc_attr( $this->get_option( 'tag' ) ); ?>" placeholder="<?php echo esc_attr( _x( '#hashtag', 'Placeholder text', 'taggregator' ) ); ?>" />
		<?php
	}

	function sanitize_options( $options ) {
		$options['active'] = ! empty( $options['active'] );
		$options['tag']    = sanitize_text_field( $options['tag'] );

		return $options;
	}

	function load_providers() {
		$providers = apply_filters( 'taggregator_providers', array(
			'Twitter'   => plugin_dir_path( __FILE__ ) . 'taggregator-twitter.php',
			'Instagram' => plugin_dir_path( __FILE__ ) . 'taggregator-instagram.php',
		) );

		foreach( $providers as $service => $file ) {
			if ( is_readable( $file ) ) {
				include_once( $file );
			}
		}
	}

	function fetch() {
		if ( $this->get_option( 'active' ) ) {
			do_action( 'taggerator_cron_active' );
		}
	}

	static function on_activation() {
		wp_schedule_event( time(), 'hourly', 'taggerator_cron' );
	}

	static function on_deactivation() {
		wp_clear_scheduled_hook( 'taggregator_cron' );
	}

}

new Taggregator;

register_activation_hook(   __FILE__, array( 'Taggregator', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'Taggregator', 'on_deactivation' ) );
