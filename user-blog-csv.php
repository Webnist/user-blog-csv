<?php
/*
  Plugin Name: User Blog CSV
  Plugin URI: http://plugins.webnist.net/
  Description:
  Version: 0.7.1.0
  Author: Webnist
  Author URI: http://webni.st
  License: GPLv2 or later
  Network: true
 */
define( 'UBC_VERSION', '0.7.1.0' );

if ( ! defined( 'UBC_PLUGIN_BASENAME' ) )
	define( 'UBC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'UBC_DOMAIN' ) )
	define( 'UBC_DOMAIN', 'ubc' );

if ( ! defined( 'UBC_PLUGIN_NAME' ) )
	define( 'UBC_PLUGIN_NAME', trim( dirname( UBC_PLUGIN_BASENAME ), '/' ) );

if ( ! defined( 'UBC_PLUGIN_DIR' ) )
	define( 'UBC_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . UBC_PLUGIN_NAME );

if ( ! defined( 'UBC_PLUGIN_URL' ) )
	define( 'UBC_PLUGIN_URL', WP_PLUGIN_URL . '/' . UBC_PLUGIN_NAME );

if ( ! defined( 'UBC_ACCEPT_SJIS_CSV' ) )
	define( 'UBC_ACCEPT_SJIS_CSV', false );

new User_Blog_CSV();

class User_Blog_CSV {

	private $version = '0.7.1.0';
	private $plugin_dir;
	private $plugin_url;
	private $relative_lang_path;
	private $absolute_lang_path;
	private $current_blog_id;

	public function __construct() {
		$this->plugin_url = UBC_PLUGIN_URL;
		$this->plugin_dir = UBC_PLUGIN_DIR;
		$this->relative_lang_path = UBC_DOMAIN . '/languages';
		$this->absolute_lang_path = UBC_PLUGIN_DIR . '/languages';
		$this->current_blog_id = get_current_blog_id();
		load_plugin_textdomain( UBC_DOMAIN, $this->absolute_lang_path, $this->relative_lang_path );
		if ( $this->current_blog_id == 1 ) {
			add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
			add_action( 'admin_init', array( &$this, 'ubc_admin_init' ) );
		}
	}

	/* admin */
	public function admin_menu() {
		add_menu_page( __( 'UBC', 'ubc' ), __( 'UBC', 'ubc' ), 'level_10', dirname( plugin_basename( __FILE__ ) ), array( &$this, 'add_admin_edit_page' ) );
	}

	public function add_admin_edit_page() {
		require_once $this->plugin_dir . '/admin.php';
	}

	public function ubc_admin_init() {
		if ( isset( $_POST['action'] ) )
			$action = trim( $_POST['action'] );

		if ( empty( $action ) )
			return;


		if ( 'ubc_import_data' == $action ) {
			check_admin_referer( 'ubc_import_data' );

			if ( ! current_user_can( 'manage_options' ) )
				return;

			$file = $_FILES['ubc_import_data_file'];

			if ( ! isset( $file ) || empty( $file ) || $file['error'] || empty( $file['tmp_name'] ) )
				return;

			$uploaded = $this->ubc_import_data_handle_upload( $file );

			if ( empty( $uploaded ) )
				return;

			$file = $uploaded['file'];

			if ( UBC_ACCEPT_SJIS_CSV && function_exists( 'mb_convert_encoding' ) ) {
				$contents = file_get_contents( $file );
				$contents = mb_convert_encoding( $contents, 'UTF-8', 'SJIS' );
				file_put_contents( $file, $contents );
			}

			$attachment_id = wp_insert_attachment(
				array(
					'post_title' => $uploaded['filename'],
					'post_content' => $uploaded['url'],
					'post_mime_type' => $uploaded['type'],
					'guid' => $uploaded['url'] ),
				$file );

			setlocale( LC_ALL, 'ja_JP.UTF-8' );
			@set_time_limit( 600 );

			$handle = fopen( $file, 'r' );

			if ( $handle )
				$result = $this->ubc_import_data( $handle );
			else
				$result = false;

			wp_delete_attachment( $attachment_id );

			$redirect_to = menu_page_url( 'ubc', false );

			if ( $result )
				$redirect_to = add_query_arg( array( 'page' => 'user-blog-csv' ), $redirect_to );

			wp_safe_redirect( $redirect_to );
			exit();
		}
	}
	public function ubc_import_data_handle_upload( $file ) {
		$mimes = array( 'csv' => 'text/csv' );

		$filetype = wp_check_filetype( $file['name'], $mimes );

		if ( ! $ext = $filetype['ext'] )
			return false;

		$overrides = array(
			'test_form' => false,
			'test_type' => false );

		$file['name'] = 'ubc_import_data.csv';
		$file = wp_handle_upload( $file, $overrides );

		if ( isset( $file['error'] ) )
			return false;

		$url = $file['url'];
		$type = $file['type'];
		$file = addslashes( $file['file'] );
		$filename = basename( $file, '.' . $ext );

		return compact( 'url', 'type', 'file', 'filename' );
	}
	public function ubc_import_data( $handle ) {
		fgets( $handle );

		while ( ( $data = fgetcsv( $handle ) ) !== FALSE ) {
			$user_id      = (int) trim( $data[0] );
			$user_login   = trim( $data[1] );
			$user_pass    = trim( $data[2] );
			$user_email   = trim( $data[3] );
			$first_name   = trim( $data[4] );
			$last_name    = trim( $data[5] );
			$display_name = trim( $data[6] );
			$role         = trim( $data[7] );
			$site_url     = trim( $data[8] );
			$site_name    = trim( $data[9] );
			$site_desc    = trim( $data[10] );
			$check_user = username_exists( $user_login );
			if ( $check_user ) {
				wp_update_user( array(
					'ID' => $user_id,
					'user_pass' => $user_pass,
					'user_email' => $user_email,
				) );
			} else {
				if ( !$user_pass )
					$user_pass = wp_generate_password();

				$user_id = wp_create_user( $user_login, $user_pass, $user_email );

				if ( $site_url ) {
					global $domain;
					if ( domain_exists( $domain, $site_url ) )
						return new WP_Error( 'blog_taken', __( 'Site already exists.' ) );

					if ( !$site_name )
						$site_name = $user_login;

					if ( !$site_desc )
						$site_desc = $site_desc;

					if ( is_subdomain_install() ) {
						$domain = $site_url . '.' . $domain;
						$path   = '/';
					} else {
						$domain = $domain;
						$path   = '/' . $site_url;
					}

					$blog_id = wpmu_create_blog( $domain, $path, $site_name, $user_id, array( 'public' => 1 ) );

					if ( '' == get_blog_option( $blog_id, 'blogdescription' ) ) {
						add_blog_option( $blog_id, 'blogdescription', $site_desc );
					} else if ( $site_desc != get_blog_option( $blog_id, 'blogdescription' ) ) {
						update_blog_option( $blog_id, 'blogdescription', $site_desc );
					} else if ( '' == $site_desc ) {
						delete_blog_option( $blog_id, 'blogdescription' );
					}

				}

			}
		}
		fclose( $handle );

		return true;
	}

}
