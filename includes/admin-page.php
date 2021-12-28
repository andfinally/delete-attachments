<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>

<div class="wrap">

	<h1><?php _e( 'Delete Attachments', 'delete-attachments' ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php" style="max-width: 640px;">

		<?php settings_fields( 'delete-attachments' ); ?>

		<p><?php _e( 'When you click the "Start" button, the plugin will start deleting all unused attachments, and any their post meta fields, taxonomy and comments and media files.', 'delete-attachments' ); ?></p>
		<p><?php _e( 'You can leave this page once the process has started. The plugin will delete attachments in batches using WP-Cron scheduled actions. The results will be output to a log file in the wp-content folder.', 'delete-attachments' ); ?></p>
		<p><?php _e( 'Please note, you won\'t be able to get attachments or media files back afterwards. If in doubt, back up your site\'s database and wp-content folder first.', 'delete-attachments' ); ?></p>
		<p>
			<?php
			printf(
				__( 'To check what actions are scheduled in your site, use a plugin like <a href="%s">Cron Control</a>.', 'delete-attachments' ),
				'https://github.com/Automattic/Cron-Control'
			);
			?>
		</p>
		<p class="submit">
			<a href="<?php echo wp_nonce_url( admin_url( add_query_arg( 'action', 'delete-attachments', self::ADMIN_PAGE ) ), 'delete-attachments' ); ?>" class="button button-primary button-large delete"><?php _e( 'Start', 'delete-attachments' ); ?></a>
		</p>

	</form>

</div>
