<?php
/**
 * Plugin Name: GitHub oAuth for WordPress
 * Plugin URI:  https://github.com/brasofilo/github-plugin-for-wordpress
 * Description: Basic information about your repositories and its Forks and Watchers. 
 * Version: 2013.10.10 
 * Author: Rodolfo Buaiz
 * Author URI: http://wordpress.stackexchange.com/users/12615/brasofilo
 * License: GPLv2 or later
 * Text Domain: b5f_gpfw
 * Domain Path: /languages
 *
 * 
 * This program is free software; you can redistribute it and/or modify it 
 * under the terms of the GNU General Public License version 2, 
 * as published by the Free Software Foundation.  You may NOT assume 
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, 
 * but WITHOUT ANY WARRANTY; without even the implied warranty 
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Based on Plugin Class Demo
 * https://gist.github.com/toscho/3804204
 */
add_action(
    'plugins_loaded', array( B5F_Github_oAuth_For_WordPress::get_instance(), 'plugin_setup' )
);


class B5F_Github_oAuth_For_WordPress
{
    /**
     * Plugin instance.
     *
     * @see get_instance()
     * @type object
     */
    protected static $instance = NULL;

    
    /**
     * URL to this plugin's directory.
     *
     * @type string
     */
    public $plugin_url = '';

    
    /**
     * Path to this plugin's directory.
     *
     * @type string
     */
    public $plugin_path = '';

    
    /**
     * Hardcoded in the redirects.
     *
     * @type string
     */
    public $plugin_admin_url = 'index.php?page=my-github-connect';
    
    
    /**
     * Plugin page
     * 
     * @type string
     */
    private $hook;

    
    /**
     * Constructor. Intentionally left empty and public.
     *
     * @see   plugin_setup()
     * @since 2012.09.12
     */
    public function __construct() {}


    /**
     * Access this plugin’s working instance
     *
     * @wp-hook plugins_loaded
     * @since   2012.09.13
     * @return  object of this class
     */
    public static function get_instance()
    {
        NULL === self::$instance and self::$instance = new self;
        return self::$instance;
    }


    /**
     * Used for regular plugin work.
     *
     * @wp-hook plugins_loaded
     * @since   2012.09.10
     * @return  void
     */
    public function plugin_setup()
    {
        $this->plugin_url = plugins_url( '/', __FILE__ );
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->load_language( 'b5f_gpfw' );
        $this->check_oauth();
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_filter( 'plugin_row_meta', array( $this, 'donate_link' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_github_zip' ), 1, 3);
        
		include_once 'inc/class-shortcode.php';
        new B5F_Shortcode_GitHub_For_WordPress();

        include_once 'inc/plugin-updates/plugin-update-checker.php';
		$updateChecker = new PluginUpdateChecker(
			'https://raw.github.com/brasofilo/github-plugin-for-wordpress/master/inc/update.json',
			__FILE__,
			'github-plugin-for-wordpress-master'
		);
    }

    
    /**
     * Add plugin menu to the Dashboard
     */
    public function admin_menu()
    {
        $this->hook = add_dashboard_page( 
            __( 'GitHub Options', 'b5f_gpfw' ), 
            __( 'GitHub', 'b5f_gpfw' ), 
            'manage_options', 
            'my-github-connect', 
            array( $this, 'admin_page' ) 
        );
    }
    
    
    /**
     * Donnow how to implement the Settings API here.
     */
    public function admin_page()
    {
        include_once 'inc/admin-page.php';
    }


    /**
     * Checks for oAuth returning token
     * 
     * @return void
     */ 
    public function check_oauth()
    {
        # Not our page, bail out
        if( !isset( $_GET['page'] ) || 'my-github-connect' != $_GET['page'] )
            return;
        
        # Code received, proceed to storing the token
        if( $_SERVER["REQUEST_METHOD"] == "GET" && isset( $_GET['code'] ) )
        {
            $redirect = admin_url( $this->plugin_admin_url );
            $api_key = get_option( 'GITHUB_API_KEY' );
            $api_secret = get_option( 'GITHUB_API_SECRET_KEY' );

            $args = array(
                'method'      => 'POST',
                'httpversion' => '1.1',
                'blocking'    => true,
                'headers'     => array(
                    'Accept' => 'application/json'
                ),
                'body'        => array(
                    'code'          => $_GET['code'],
                    'redirect_uri'  => $redirect,
                    'client_id'     => $api_key,
                    'client_secret' => $api_secret
                )
            );

            add_filter( 'https_ssl_verify', '__return_false' );
            $response = wp_remote_post( 'https://github.com/login/oauth/access_token', $args );

            $keys = json_decode( $response['body'] );
            if( $keys )
                update_option( 'GITHUB_AUTHENTICATION_TOKEN', $keys->access_token );
            
            wp_redirect( admin_url( $this->plugin_admin_url ) );
            exit;
        }
    }

    
    /**
     * Loads translation file.
     *
     * Accessible to other classes to load different language files (admin and
     * front-end for example).
     *
     * @wp-hook init
     * @param   string $domain
     * @since   2012.09.11
     * @return  void
     */
    public function load_language( $domain )
    {
        $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

        load_textdomain(
            $domain, WP_LANG_DIR . '/github-plugin-for-wordpress/' . $domain . '-' . $locale . '.mo'
        );

        load_plugin_textdomain(
            $domain, FALSE, $this->plugin_path . '/languages'
        );
    }    

    
    /**
	 * Removes the prefix "-master" when updating from GitHub zip files
	 * 
	 * See: https://github.com/YahnisElsts/plugin-update-checker/issues/1
	 * 
	 * @param string $source
	 * @param string $remote_source
	 * @param object $thiz
	 * @return string
	 */
	public function rename_github_zip( $source, $remote_source, $thiz )
	{
		if(  strpos( $source, 'github-plugin-for-wordpress') === false )
			return $source;

		$path_parts = pathinfo($source);
		$newsource = trailingslashit($path_parts['dirname']). trailingslashit('github-plugin-for-wordpress');
		rename($source, $newsource);
		return $newsource;
	}

    /**
     * Add donate link to plugin description in /wp-admin/plugins.php
     * 
     * @param array $plugin_meta
     * @param string $plugin_file
     * @param string $plugin_data
     * @param string $status
     * @return array
     */
    public function donate_link( $plugin_meta, $plugin_file, $plugin_data, $status ) 
	{
		if( plugin_basename( __FILE__ ) == $plugin_file )
			$plugin_meta[] = '&hearts; <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=JNJXKWBYM9JP6&lc=ES&item_name=GitHub%20WordPress%20%3a%20Rodolfo%20Buaiz&currency_code=EUR&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted">Buy me a beer :o)</a>';
		return $plugin_meta;
	}

}