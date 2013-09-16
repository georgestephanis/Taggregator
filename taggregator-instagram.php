<?php

class Taggregator_Instagram {

	static $instance;

	function __construct() {
		self::$instance = $this;

		add_action( 'taggregator_cron_active',       array( $this, 'fetch' )             );
		add_action( 'taggregator_register_settings', array( $this, 'register_settings' ) );
		add_filter( 'taggregator_sanitize_options',  array( $this, 'sanitize_options' )  );
	}

	static function get_option( $key ) {
		// Constant overrides to permit defining this globally for a multisite environment.
		switch( $key ) {
			case 'instagram_access_token' :
				if ( defined( 'TAGGREGATOR_INSTAGRAM_ACCESS_TOKEN' ) ) {
					return TAGGREGATOR_INSTAGRAM_ACCESS_TOKEN;
				}
		}

		return Taggregator::get_option( $key );
	}

	function register_settings() {
		if ( ! defined( 'TAGGREGATOR_INSTAGRAM_ACCESS_TOKEN' ) ) {
			add_settings_field(
				'taggregator_instagram_access_token',
				sprintf( '<label for="taggregator_instagram_access_token">%1$s</label>', __( 'Instagram Access Token', 'taggregator' ) ),
				array( $this, 'taggregator_instagram_access_token_cb' ),
				'discussion',
				'taggregator'
			);
		}
	}

	function taggregator_instagram_access_token_cb() {
		?>
		<input class="regular-text code" type="text" id="taggregator_instagram_access_token" name="taggregator_options[instagram_access_token]" value="<?php echo esc_attr( $this->get_option( 'instagram_access_token' ) ); ?>" />
		<?php
	}

	function sanitize_options( $options ) {
		if ( isset( $options['instagram_access_token'] ) ) {
			$options['instagram_access_token'] = sanitize_text_field( $options['instagram_access_token'] );
		}
		return $options;
	}

	function fetch() {
		$args = array(
			'access_token' => urlencode( $this->strip_hashtag( $this->get_option( 'instagram_access_token' ) ) ),
			'min_tag_id'   => get_option( 'taggregator_instagram_next_min_id', null ),
		);

		$api_base = sprintf( 'https://api.instagram.com/v1/tags/%1$s/media/recent', urlencode( $this->strip_hashtag( $this->get_option( 'tag' ) ) ) );
		$response = wp_remote_get( add_query_arg( $args, $api_base ) );
		$data     = json_decode( wp_remote_retrieve_body( $response ) );

		// Make sure that the option isn't autoloaded.
		if ( ! empty( $data->pagination->next_min_id ) ) {
			if ( ! add_option( 'taggregator_instagram_next_min_id', $data->pagination->next_min_id, null, 'no' ) ) {
				update_option( 'taggregator_instagram_next_min_id', $data->pagination->next_min_id );
			}
		}

		$this->create_posts( $data->data );
	}

	function create_posts( $instagram_posts = array() ) {
		global $wpdb;

		if ( empty( $instagram_posts ) ) return;

		$existing_ids = $wpdb->get_col( "SELECT DISTINCT `meta_value` FROM {$wpdb->postmeta} WHERE `meta_key` = 'instagram_id'" );

		foreach( $instagram_posts as $instagram_post ) {
			if ( ! in_array( $instagram_post->id, $existing_ids ) ) {
				$this->create_post( $instagram_post );
			}
		}
	}

	function create_post( $instagram_post ) {
		if ( $instagram_post->tags ) {
			$hashtags = array_map( array( $this, 'prefix_with_hashtag' ), $instagram_post->tags );
		}

		$post_id = wp_insert_post( array(
			'post_type'     => Taggregator::POST_TYPE,
			'post_status'   => 'publish',
			'post_date'     => date(   'Y-m-d H:i:s', $instagram_post->created_time ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $instagram_post->created_time ),
			'post_title'    => empty( $instagram_post->caption ) ? __( 'No caption provided', 'taggregator' ) : $instagram_post->caption->text,
			'post_content'  => $instagram_post->link,
			'post_excerpt'  => empty( $instagram_post->caption ) ? __( 'No caption provided', 'taggregator' ) : $instagram_post->caption->text,
			'tags_input'    => $hashtags ? implode( ', ', $hashtags ) : '',
		) );

		set_post_format(  $post_id, $instagram_post->type ); // either 'image' or 'video'
		update_post_meta( $post_id, 'raw',           json_encode( $instagram_post )               );
		update_post_meta( $post_id, 'instagram_id',  $instagram_post->id                          );
		update_post_meta( $post_id, 'instagram_url', $instagram_post->link                        );
		update_post_meta( $post_id, 'image',         $instagram_post->images->standard_resolution );

		if ( 'video' == $instagram_post->type ) {
			update_post_meta( $post_id, 'video',     $instagram_post->videos->standard_resolution );
		}
	}

	static function strip_hashtag( $string ) {
		return ltrim( trim( $string ), '#' );
	}

	static function prefix_with_hashtag( $string ) {
		return '#' . ltrim( trim( $string ), '#' );
	}
}

new Taggregator_Instagram;