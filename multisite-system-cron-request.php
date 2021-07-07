<?php

/**
 * MultiSite System Cron Request class
 *
 * @package MultiSite System Cron
 * @subpackage MultiSite System Cron Request
 */
class BE_MSSC_Request {



	/**
	 * Executes cron for all public blogs
	 */
	public static function cron() {

		// Check disable-cron constant
		if ( ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
			return;
		}

		// Check plugin cron status
		$status = (int) get_site_option( 'be_mssc_status' );
		if ( empty( $status ) ) {
			die;
		}

		// Not allowed with cron transient args
		if ( ! empty( $_GET['doing_wp_cron'] ) ) {
			die;
		}

		// Check main crontab request
		if ( empty( $_GET['mssc'] ) ) {

			// Check execution request from this script
			if ( empty( $_GET['mssce'] ) || $_GET['mssce'] != get_site_option( 'be_mssc_key_exec' ) ) {
				self::log( 'invalid execution request key' );
				die;
			}

			// Log execution cron
			self::log( 'doing cron request in ' . $_SERVER['HTTP_HOST'], true );

			// Continue
			return;
		}

		// From here only primary blog
		if ( ! is_main_site() ) {
			die;
		}

		// Check main request key
		if ( $_GET['mssc'] != get_site_option( 'be_mssc_key_main' ) ) {
			self::log( 'invalid main request key' );
			die;
		}

		// Check time recording request call
		if ( ! empty( $_GET['mssct'] ) ) {
			$started = (int) $_GET['mssct'];
			if ( ! empty( $started ) && time() > $started ) {
				update_site_option( 'be_mssc_last_period', $started . '-' . time() );
			}
			die;
		}

		// Check elapsed time value
		$timeout = (int) get_site_option( 'be_mssc_timeout' );
		if ( empty( $timeout ) ) {
			die;
		}

		// Retrieve status time
		$started = get_site_option( 'be_mssc_started' );
		if ( ! empty( $started ) && ( $started >= time() || ( time() - $started ) <= $timeout ) ) {
			self::log( 'main cron already started' );
			die;
		}

		// Update last time status
		$current_time = time();
		update_site_option( 'be_mssc_started', $current_time );

		// Log start
		self::log( '' );
		self::log( 'multisite system cron starting now' );

		// Retrieve blogs
		global $site_id;
		$blogs_public = get_sites(
			[
				'public'     => 1,
				'archived'   => 0,
				'deleted'    => 0,
				'network_id' => $site_id,
			]
		);
		if ( empty( $blogs_public ) || ! is_array( $blogs_public ) ) {
			die;
		}

		// Map blogs
		$blogs = array();
		foreach ( $blogs_public as $blog ) {
			$path                    = empty( $blog->path ) ? '' : trim( $blog->path, '/' );
			$blogs[ $blog->blog_id ] = rtrim( $blog->domain, '/' ) . ( empty( $path ) ? '' : '/' . $path );
		}

		// Domain Mapping plugin support
		if ( (int) get_site_option( 'be_mssc_dms' ) ) {
			global $wpdb;
			$dm_table = $wpdb->get_var( 'SHOW TABLES LIKE "' . $wpdb->base_prefix . 'domain_mapping"' );
			if ( ! empty( $dm_table ) ) {
				foreach ( $blogs as $blog_id => $domain ) {
					$domain = $wpdb->get_var( $wpdb->prepare( "SELECT domain FROM {$dm_table} WHERE blog_id = %d AND active = 1 LIMIT 1", $blog_id ) );
					if ( ! empty( $domain ) ) {
						$blogs[ $blog_id ] = rtrim( $domain, '/' );
					}
				}
			}
		}

		// Check sleep time
		$sleep = (int) get_site_option( 'be_mssc_sleep' );
		if ( empty( $sleep ) ) {
			$sleep = 10;
		}

		// Compose schema
		$schema     = is_ssl() ? 'https://' : 'http://';
		$ssl_verify = apply_filters( 'https_local_ssl_verify', false );

		// No timeout
		set_time_limit( 0 );

		// Enum blogs
		foreach ( $blogs as $blog_id => $domain ) {

			// // Check existing schema
			// if ( 0 === strpos( $domain, 'http://' ) ) {
			// 	$domain = substr( $domain, 7 );
			// } elseif ( 0 === strpos( $domain, 'https://' ) ) {
			// 	$domain = substr( $domain, 8 );
			// } elseif ( 0 === strpos( $domain, '//' ) ) {
			// 	$domain = substr( $domain, 2 );
			// }

			// remove existing schema
			$domain = preg_replace( '@(https?://|//)@', '', $domain );

			// Compose URL
			$subsite_url = $schema . $domain;
			$cron_url    = $subsite_url . '/wp-cron.php?mssce=' . get_site_option( 'be_mssc_key_exec' );

			// Log action
			self::log( 'spawn cron url ' . $cron_url );

			// Spawn cron
			wp_remote_post(
				$cron_url,
				array(
					'timeout'   => 0.01,
					'blocking'  => false,
					'sslverify' => $ssl_verify,
				)
			);

			do_action( 'mssc_add_to_cron', $blog_id, $subsite_url, $sleep );
			// Pause
			sleep( $sleep );
		}

		// Save end time
		wp_remote_post(
			home_url( 'wp-cron.php?mssc=' . get_site_option( 'be_mssc_key_main' ) . '&mssct=' . $current_time ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => $ssl_verify,
			)
		);

		// Done
		self::log( 'end of multisite system cron at ' . timer_stop( 0, 0 ) . ' seconds' );

		// End
		die;
	}



	/**
	 * Private system log
	 */
	private static function log( $message ) {

		// Local
		static $active;

		// Active or forced
		if ( ! isset( $active ) ) {
			$active = (int) get_site_option( 'be_mssc_log' );
		}

		// Log
		if ( $active ) {
			@error_log( 'MSSC log: ' . $message );
		}
	}



}
