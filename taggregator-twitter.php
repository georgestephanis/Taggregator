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
		add_settings_field(
			'taggregator_twitter_consumer_key',
			sprintf( '<label for="taggregator_twitter_consumer_key">%1$s</label>', __( 'Twitter Consumer Key', 'taggregator' ) ),
			array( $this, 'taggregator_twitter_consumer_key_cb' ),
			'discussion',
			'taggregator'
		);

		add_settings_field(
			'taggregator_twitter_consumer_secret',
			sprintf( '<label for="taggregator_twitter_consumer_secret">%1$s</label>', __( 'Twitter Consumer Secret', 'taggregator' ) ),
			array( $this, 'taggregator_twitter_consumer_secret_cb' ),
			'discussion',
			'taggregator'
		);
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

	function fetch( $max_id = null, $since_id = null ) {
		$args = array(
			'q'           => Taggregator::get_option( 'tag' ),
			'result_type' => 'recent',
			'count'       => 100,
		);

		if ( is_null( $max_id ) ) {
			$args['max_id'] = (int) $max_id;
		}

		if ( is_null( $since_id ) ) {
			$latest_existing_tweets = get_posts( array(
				'post_type'      => Taggregator::POST_TYPE,
				'meta_key'       => 'tweet_id',
				'orderby'        => 'meta_value_num',
				'order'          => 'desc',
				'posts_per_page' => 1,
			) );

			if ( $latest_existing_tweets ) {
				$since_id = get_post_meta( $latest_existing_tweets[0]->ID, 'tweet_id', true );
			}
		}

		if ( $since_id ) {
			$args['since_id'] = (int) $since_id;
		}

		$url = add_query_arg( $args, self::API_BASE );

	}

}

new Taggregator_Twitter;