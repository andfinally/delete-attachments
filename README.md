# Delete Attachments

## Description

This WordPress plugin deletes orphan attachments, their metadata and media files. Orphan attachments are media items which haven't been used in any post.

See https://neliosoftware.com/blog/how-to-remove-unused-images-from-your-media-library-in-wordpress/
for more info about the query this plugin uses to detect orphan attachments.

The query assumes you haven't changed the hostname of your site after you created the attachments – i.e., that
each attachment's `guid` is its URL. Check the posts of type `attachment` in your `wp_posts` table if you're not sure.

## Warning

You won't be able to retrieve the deleted attachments or their media files afterwards, so back up your database and media files before you do a deletion. 

## WP-Cron

To reduce the effect on site performance, this plugin uses WP-Cron scheduled events to delete
attachments in batches. The scheduled action tries to delete all the attachments in a batch, then passes the remaining attachment IDs to a new scheduled action. If you're not worried about performance, or don't have many orphan attachments, increase the `BATCH_SIZE` constant to delete more attachments in one go.

Because we're using WP-Cron events, the deletion won't start straight away – please allow some time for it to start. By default, WP-Cron only does scheduled actions when someone views a page on the WordPress site. See https://developer.wordpress.org/plugins/cron/ for more details. Also check out [Cron Control](https://github.com/Automattic/Cron-Control), a good plugin which lets you see which cron jobs are scheduled, and run them sooner.

## Installation

- Download the latest release from [Releases](https://github.com/andfinally/delete-attachments/releases).
- Unzip the zip file into your `wp-content/plugins` folder, or go to `Plugins > Add New` and upload it there.
- Go to `Plugins` and activate the plugin.
- Go to `Tools > Delete Attachments` to use the plugin.

## Log file

The plugin will output the results of deletion in a log file with a name like `delete-attachments-[YYYY-mm-dd]` in your `wp-content` folder.

## Changelog

### 1.0.0

- Initial release

### 1.0.1

- Added note about log file.

## Screenshots

<img width="500" src="https://github.com/andfinally/delete-attachments/blob/66eaa17bcbb7dd8680f56c21a7d084f0435357df/delete-attachments-screenshot.png">
