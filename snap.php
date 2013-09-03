<?php
/*
Plugin Name: Snap
Plugin URI: http://wordpress.org/plugins/snap
Description: Ultra simple photo sharing, for use with your favorite theme. WARNING: This takes over your homepage and archives.
Author: Helen Hou-Sandi | 10up
Version: 0.1
Author URI: http://profiles.wordpress.org/helen
License: MIT
License URI: http://opensource.org/licenses/MIT
*/

class Snap_Plugin {
	/**
	 * Set up hooks
	 *
	 * @since 0.1
	 */
	public function __construct() {
		add_filter( 'login_redirect',         array( $this, 'login_redirect' ), 10, 3 );
		add_action( 'pre_get_posts',          array( $this, 'pre_get_posts' ) );
		add_action( 'add_attachment',         array( $this, 'add_attachment' ) );

		// Snap shortlinks
		add_action( 'generate_rewrite_rules', array( $this, 'snap_rewrites' ) );
		add_filter( 'pre_get_shortlink',      array( $this, 'get_shortlink' ), 10, 4 );
		add_filter( 'attachment_link',        array( $this, 'attachment_permalink' ), 10, 2 );
	}

	/**
	 * Redirect users to media-new when logging in
	 *
	 * @since 0.1
	 *
	 * @param string  $redirect_to Redirect location.
	 * @param string  $request     Any redirect location passed via URL.
	 * @param WP_User $user        Current user object.
	 *
	 * @return string Redirect location.
	 */
	public function login_redirect( $redirect_to, $request, $user ) {
		if ( isset( $user ) && is_a( $user, 'WP_User' ) ) {
			if ( user_can( $user, 'upload_files' ) && user_can( $user, 'publish_posts' ) )
				return admin_url( 'media-new.php' );
			else
				return apply_filters( 'snap_login_redirect', home_url(), $request, $user );
		}

		return $redirect_to;
	}

	/**
	 * Only show attachments in certain main queries
	 *
	 * @since 0.1
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public function pre_get_posts( $query ) {
		// Only do this for the main loop on home and archive views. for now.
		if ( ! $query->is_main_query() || ( ! $query->is_home() && ! $query->is_archive() ) )
			return;

		$query->set( 'post_type', array( 'attachment' ) );
		$query->set( 'post_status', array( 'publish', 'inherit' ) );
	}

	/**
	 * Add the action to update the 'post_date' field on upload
	 *
	 * @since 0.1
	 *
	 * @param int $post_id The attachment ID
	 */
	public function add_attachment( $post_id ) {
		// Only alter the publish date when first uploading
		add_filter( 'wp_update_attachment_metadata', array( $this, 'update_image_date' ), 10, 2 );
	}

	/**
	 * Update the 'post_date' field with the date in the image meta
	 *
	 * @since 0.1
	 *
	 * @param array $data    The attachment meta array.
	 * @param int   $post_id The attachment ID.
	 *
	 * @return array The image meta data array.
	 */
	public function update_image_date( $data, $post_id ) {
		// No loops :)
		// We don't add this back because we only want it to run once per attachment
		remove_filter( 'wp_update_attachment_metadata', array( $this, 'update_image_date' ), 10, 2 );

		// If the created-date is saved in EXIF data
		if ( isset( $data['image_meta']['created_timestamp'] ) && 0 !== $data['image_meta']['created_timestamp'] ) {
			// Save the WordPress-generated publish date
			$original = get_post_field( 'post_date', $post_id );
			update_post_meta( $post_id, '_original_upload', $original );

			$post_array = array(
				'ID' => $post_id,
				'post_date' => date( 'Y-m-d H:i:s', $data['image_meta']['created_timestamp'] ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $data['image_meta']['created_timestamp'] ),
			);

			wp_update_post( $post_array );
		}

		return $data;
	}

	/**
	 * Handle /snap/## as attachment rewrites
	 * 
	 * @param WP_Rewrite $wp_rewrite The rewrites object.
	 * 
	 * @return string The shortlink.
	 */
	public function snap_rewrites( $wp_rewrite ) {
		$snap = array( 'snap/(\d*)$' => 'index.php?attachment_id=$matches[1]' );
		$wp_rewrite->rules = $snap + $wp_rewrite->rules;
	}

	/**
	 * Filter attachment shortlinks as /snap/$id
	 *
	 * @param string $shortlink   The shortlink URL.
	 * @param int    $id          The post id.
	 * @param string $context     The shortlink context, default is 'post'.
	 * @param bool   $allow_slugs Whether to allow post slugs in the shortlink.
	 *
	 * @return string The shortlink.
	 */
	public function get_shortlink( $shortlink, $id, $context, $allow_slugs ) {
		global $wp_rewrite;

		if ( $wp_rewrite->using_mod_rewrite_permalinks() ) {
			if ( 'attachment' == get_post_type( $id ) )
				$shortlink = home_url( '/snap/' . $id );
		}

		return $shortlink;
	}

	/**
	 * Filter attachment permalinks as /snap/$id
	 *
	 * @param string $link The attachment permalink.
	 * @param int    $id   The post id.
	 *
	 * @return string The attachment permalink.
	 */
	public function attachment_permalink( $link, $id ) {
		global $wp_rewrite;

		if ( $wp_rewrite->using_mod_rewrite_permalinks() )
			$link = home_url( '/snap/' . $id );

		return $link;
	}
}

$snap_plugin = new Snap_Plugin;