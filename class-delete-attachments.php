<?php

/*
Plugin Name: Delete Attachments
Plugin URI: https://github.com/andfinally/delete-attachments
Description: Simple WordPress plugin to delete attachments which aren't used in any posts.
Version: 1.0.1
Author: Andfinally
Author URI: https://github.com/andfinally/
License: GPLv2 or later
Text Domain: delete-attachments
*/

/**
 * Class Delete_Attachments
 *
 * Deletes orphan attachments, their metadata and media files.
 * See https://neliosoftware.com/blog/how-to-remove-unused-images-from-your-media-library-in-wordpress/
 * for more info.
 *
 * To reduce the effect on site performance, this process uses WP-Cron scheduled events to delete
 * attachments in batches. If you're not worried about that, or don't have many orphan attachments, increase
 * the `BATCH_SIZE` constant.
 *
 * This plugin assumes you haven't changed the hostname of your site after you created the attachments – i.e., that
 * each attachment's `guid` is its URL.
 *
 * Please note – by default, WP-Cron only does scheduled actions when someone views a page on the WordPress site.
 *
 * See https://developer.wordpress.org/plugins/cron/ for details about WP-Cron.
 */
class Delete_Attachments {
	const SCREEN = 'tools_page_delete-attachments';
	const ADMIN_PAGE = 'tools.php?page=delete-attachments';
	/** @var int How many attachments to delete in each scheduled job. */
	const BATCH_SIZE = 10;

	/**
	 * Initialise the plugin
	 */
	public static function load() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'load-' . self::SCREEN, array( __CLASS__, 'do_admin_actions' ) );
		add_action( 'load-' . self::SCREEN, array( __CLASS__, 'add_settings_notices' ) );
		add_action( 'delete_attachments', array( __CLASS__, 'delete_attachments_job' ) );
	}

	/**
	 * Set up the menu item under Tools
	 */
	public static function admin_menu() {
		$hook = add_management_page(
			'Delete Attachments',
			'Delete Attachments',
			'install_plugins',
			'delete-attachments',
			array( __CLASS__, 'admin_page' ),
			3
		);
	}

	/**
	 * Output the admin page
	 */
	public static function admin_page() {
		require_once plugin_dir_path( __FILE__ ) . '/includes/admin-page.php';
	}

	/**
	 * Check the user's capabilities and maybe start the deletion
	 */
	public static function do_admin_actions() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_safe_redirect( admin_url( add_query_arg( 'message', 'delete-attachments-unauthorised' ) ) );
			exit;
		}

		if (
			isset( $_GET['action'] )
			&& 'delete-attachments' === $_GET['action']
			&& wp_verify_nonce( $_GET['_wpnonce'], 'delete-attachments' )
		) {

			// Start deleting attachments
			$result = self::schedule_delete_job();

			wp_safe_redirect(
				admin_url(
					add_query_arg(
						'message',
						is_wp_error( $result )
							? 'delete-attachments-error'
							: 'delete-attachments-started',
						self::ADMIN_PAGE
					)
				)
			);
			exit;
		}
	}

	/**
	 * Show notices at the top of the page when the process has started
	 * or when there's an error.
	 */
	public static function add_settings_notices() {
		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		switch ( $_GET['message'] ) {
			case 'delete-attachments-started':
				// We've started the delete process
				add_settings_error(
					'',
					'delete-attachments',
					__( 'Attachment delete has started.', 'delete-attachments' ),
					'success'
				);
				break;
			case 'delete-attachments-error':
				// Error deleting attachments
				add_settings_error(
					'',
					'delete-attachments',
					sprintf(
						__( 'Error deleting attachments. %s', 'delete-attachments' ),
						wptexturize( self::$result->get_error_message()
						)
					)
				);
				break;
			case 'delete-attachments-schedule-error':
				// Error scheduling job to delete attachments
				add_settings_error(
					'',
					'delete-attachments',
					__( 'Error scheduling delete job', 'delete-attachments' )
				);
				break;
			case 'delete-attachments-none':
				// Couldn't find any orphan attachments
				add_settings_error(
					'',
					'delete-attachments',
					__( 'No attachments to delete.', 'delete-attachments' ),
					'info'
				);
				break;
			case 'delete-attachments-unauthorised':
				// User doesn't have the required admin capabilities
				add_settings_error(
					'',
					'delete-attachments',
					__( 'Only administrators can delete attachments.', 'delete-attachments' )
				);
				break;
		}

		$job_scheduled = self::job_is_scheduled();
		if ( $job_scheduled ) {
			$timestamp = \DateTime::createFromFormat( 'U', $job_scheduled );
			$timestamp = $timestamp->format( 'Y-m-d H:i:s' );
			add_settings_error(
				'',
				'delete-attachments',
				sprintf(
					__( 'A delete_attachments action was scheduled at %s.', 'delete-attachments' ),
					$timestamp
				),
				'info'
			);
		}
	}

	/**
	 * If we can find orphan attachments, schedule an async job to start deleting them
	 *
	 * @return bool|void|\WP_Error
	 */
	public static function schedule_delete_job() {
		$attachment_ids = self::get_orphan_attachments();
		if ( empty( $attachment_ids ) ) {
			wp_safe_redirect( admin_url( add_query_arg( 'message', 'delete-attachments-none' ) ) );
			exit;
		}

		$args = array(
			'attachment_ids' => $attachment_ids
		);

		if ( ! wp_next_scheduled( 'delete_attachments', $args ) ) {
			return wp_schedule_single_event( time(), 'delete_attachments', $args, true );
		}

		wp_safe_redirect( admin_url( add_query_arg( 'message', 'delete-attachments-schedule-error' ) ) );
		exit;
	}

	/**
	 * Get IDs of attachments which don't have a post parent which exists,
	 * whose guid is not in any post's content or metadata, and isn't the
	 * featured image of any post.
	 *
	 * @return array|null Array of attachment IDs, or null
	 */
	private static function get_orphan_attachments() {
		global $wpdb;

		$sql = <<<HEREDOC
		SELECT * from wp_posts atts
		WHERE post_type = 'attachment'
		AND	NOT EXISTS (SELECT * FROM wp_posts p WHERE p.ID = atts.post_parent)
		AND	NOT EXISTS (SELECT * FROM wp_postmeta pm WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = atts.ID)
		AND	NOT EXISTS (SELECT * FROM wp_posts p WHERE p.post_type <> 'attachment' AND p.post_content LIKE CONCAT('%',atts.guid,'%'))
		AND	NOT EXISTS (SELECT * FROM wp_postmeta pm WHERE pm.meta_value LIKE CONCAT('%',atts.guid,'%'))
		HEREDOC;

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return wp_list_pluck( $results, 'ID' );
	}

	/**
	 * Handler for the `delete_attachments` async job.
	 *
	 * @param array $attachment_ids Attachments we want to delete
	 */
	public static function delete_attachments_job( array $attachment_ids ) {
		// Remove the first batch from the array of attachment IDs
		$attachment_id_batch = array_splice( $attachment_ids, 0, self::BATCH_SIZE );
		foreach ( $attachment_id_batch as $attachment_id ) {
			$result = wp_delete_attachment( $attachment_id, true );
			if ( empty( $result ) ) {
				self::log( "Error deleting attachment $attachment_id." );
			} else {
				self::log( "Attachment $attachment_id deleted." );
			}
		}

		// If there are still some IDs in the attachments array, schedule a job to delete them
		if ( ! empty( $attachment_ids ) ) {
			$args = array(
				'attachment_ids' => $attachment_ids
			);
			if ( ! wp_next_scheduled( 'delete_attachments', $args ) ) {
				wp_schedule_single_event( time(), 'delete_attachments', $args, true );
			} else {
				self::log( 'Error scheduling delete_attachments job.' );
			}
		} else {
			self::log( 'Delete attachments job complete.' );
		}
	}

	/**
	 * Find if a `delete_attachments` job is scheduled.
	 * `wp_next_scheduled` will only work if we specify the correct $args, so we look through
	 * the whole array of cron jobs.
	 *
	 * @return false|int    Timestamp when job was scheduled, or false
	 */
	private static function job_is_scheduled() {
		$scheduled_actions = _get_cron_array();
		foreach ( $scheduled_actions as $timestamp => $action ) {
			if ( isset( $action['delete_attachments'] ) ) {
				return $timestamp;
			}
		}

		return false;
	}

	/**
	 * Output messages to log file in wp-content.
	 *
	 * @param string $message
	 */
	private static function log( string $message ) {
		$log_file = WP_CONTENT_DIR . '/delete-attachments-' . date( 'Y-m-d' );
		$message  .= PHP_EOL;
		error_log( $message, 3, $log_file );
	}
}

Delete_Attachments::load();
