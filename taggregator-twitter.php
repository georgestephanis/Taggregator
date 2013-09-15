<?php

class Taggregator_Twitter {

	const API_BASE = 'https://api.twitter.com/1.1/search/tweets.json';

	static $instance;

	function __construct() {
		self::$instance = $this;

		add_action( 'taggregator_cron_active', array( $this, 'fetch' ) );
	static function get_option( $key ) {
		return Taggregator::get_option( $key );
	}

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