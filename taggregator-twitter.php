<?php

class Taggregator_Twitter {

	const API_BASE = 'https://api.twitter.com/1.1/search/tweets.json';

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
			case 'twitter_consumer_key' :
				if ( defined( 'TAGGREGATOR_TWITTER_CONSUMER_KEY' ) ) {
					return TAGGREGATOR_TWITTER_CONSUMER_KEY;
				}
			case 'twitter_consumer_secret' :
				if ( defined( 'TAGGREGATOR_TWITTER_CONSUMER_SECRET' ) ) {
					return TAGGREGATOR_TWITTER_CONSUMER_SECRET;
				}
		}

		return Taggregator::get_option( $key );
	}

	function register_settings() {
		if ( ! defined( 'TAGGREGATOR_TWITTER_CONSUMER_KEY' ) ) {
			add_settings_field(
				'taggregator_twitter_consumer_key',
				sprintf( '<label for="taggregator_twitter_consumer_key">%1$s</label>', __( 'Twitter Consumer Key', 'taggregator' ) ),
				array( $this, 'taggregator_twitter_consumer_key_cb' ),
				'discussion',
				'taggregator'
			);
		}

		if ( ! defined( 'TAGGREGATOR_TWITTER_CONSUMER_SECRET' ) ) {
			add_settings_field(
				'taggregator_twitter_consumer_secret',
				sprintf( '<label for="taggregator_twitter_consumer_secret">%1$s</label>', __( 'Twitter Consumer Secret', 'taggregator' ) ),
				array( $this, 'taggregator_twitter_consumer_secret_cb' ),
				'discussion',
				'taggregator'
			);
		}
	}

	function taggregator_twitter_consumer_key_cb() {
		?>
		<input class="regular-text code" type="text" id="taggregator_twitter_consumer_key" name="taggregator_options[twitter_consumer_key]" value="<?php echo esc_attr( $this->get_option( 'twitter_consumer_key' ) ); ?>" />
		<p><em><a href="https://dev.twitter.com/apps/new" target="_blank"><?php _e( 'You can register a new app here', 'taggregator' ); ?></a></em></p>
		<?php
	}

	function taggregator_twitter_consumer_secret_cb() {
		?>
		<input class="regular-text code" type="text" id="taggregator_twitter_consumer_secret" name="taggregator_options[twitter_consumer_secret]" value="<?php echo esc_attr( $this->get_option( 'twitter_consumer_secret' ) ); ?>" />
		<?php
	}

	function sanitize_options( $options ) {
		if ( isset( $options['twitter_consumer_key'] ) ) {
			$options['twitter_consumer_key'] = sanitize_text_field( $options['twitter_consumer_key'] );
		}
		if ( isset( $options['twitter_consumer_secret'] ) ) {
			$options['twitter_consumer_secret'] = sanitize_text_field( $options['twitter_consumer_secret'] );
		}
		return $options;
	}

	function get_bearer_token() {
		if ( ! empty( $this->bearer_token ) ) {
			return $this->bearer_token;
		}

		$consumer_key    = urlencode( $this->get_option( 'twitter_consumer_key' ) );
		$consumer_secret = urlencode( $this->get_option( 'twitter_consumer_secret' ) );
		$credentials     = base64_encode( sprintf( '%1$s:%2$s', $consumer_key, $consumer_secret ) );

		$args = array(
			'body'    => 'grant_type=client_credentials',
			'headers' => array(
				'Authorization' => sprintf( 'Basic %s', $credentials ),
				'Content-Type'  => 'application/x-www-form-urlencoded;charset=UTF-8',
			),
		);

		$response = wp_remote_post( 'https://api.twitter.com/oauth2/token', $args );

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 'bearer' == $data->token_type ) {
			return $this->bearer_token = $data->access_token;
		} else {
			error_log( 'Error in ' . __FILE__ . '@' . __LINE__ . '! Data: ' . json_encode( $data ) );
		}
	}

	function fetch() {
		$args = array(
			'q'           => urlencode( $this->prefix_with_hashtag( $this->get_option( 'tag' ) ) ),
			'result_type' => 'recent',
			'count'       => 100,
		);

		$latest_existing_tweets = get_posts( array(
			'post_type'      => Taggregator::POST_TYPE,
			'meta_key'       => 'tweet_id',
			'orderby'        => 'meta_value_num',
			'order'          => 'desc',
			'posts_per_page' => 1,
		) );

		if ( $latest_existing_tweets ) {
			$args['since_id'] = get_post_meta( $latest_existing_tweets[0]->ID, 'tweet_id', true );
		}

		$request_args = array(
			'headers' => array(
				'Authorization' => sprintf( 'Bearer %s', $this->get_bearer_token() ),
			),
		);

		$response = wp_remote_get( add_query_arg( $args, self::API_BASE ), $request_args );
		$data     = json_decode( wp_remote_retrieve_body( $response ) );
		$this->create_posts( $data->statuses );

		while ( 100 == count( $data->statuses ) ) {
			$args     = wp_parse_args( ltrim( $data->search_metadata->next_results, '?' ) );
			$response = wp_remote_get( add_query_arg( $args, self::API_BASE ), $request_args );
			$data     = json_decode( wp_remote_retrieve_body( $response ) );
			$this->create_posts( $data->statuses );
		}
	}

	function create_posts( $tweets = array() ) {
		if ( empty( $tweets ) ) return;

		foreach( $tweets as $tweet ) {
			$this->create_post( $tweet );
		}
	}

	function create_post( $tweet ) {
		if ( $tweet->entities->hashtags && $hashtags = wp_list_pluck( $tweet->entities->hashtags, 'text' ) ) {
			$hashtags = array_map( array( $this, 'prefix_with_hashtag' ), $hashtags );
		}
		$tweet_url = sprintf( 'https://twitter.com/%1$s/status/%2$s', $tweet->user->id_str, $tweet->id_str );

		$post_id = wp_insert_post( array(
			'post_type'     => Taggregator::POST_TYPE,
			'post_status'   => 'publish',
			'post_title'    => $tweet->text,
			'post_content'  => $tweet_url,
			'post_excerpt'  => $tweet->text,
			'tags_input'    => $hashtags ? implode( ', ', $hashtags ) : '',
		) );

		set_post_format(  $post_id, 'status' );
		update_post_meta( $post_id, 'raw',       json_encode( $tweet ) );
		update_post_meta( $post_id, 'tweet_id',  $tweet->id_str        );
		update_post_meta( $post_id, 'tweet_url', $tweet_url            );
	}

	static function prefix_with_hashtag( $string ) {
		return '#' . ltrim( trim( $string ), '#' );
	}
}

new Taggregator_Twitter;