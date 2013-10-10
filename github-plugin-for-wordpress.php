<?php
/**
 * Plugin Name: GitHub Plugin for WordPress
 * Plugin URI:  https://github.com/brasofilo/github-plugin-for-wordpress
 * Description: Basic information about your repositories and its Forks and Watchers. 
 * Version: 2013.10.09 
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
    'plugins_loaded', array( B5F_Github_Plugin_For_WordPress::get_instance(), 'plugin_setup' )
);


class B5F_Github_Plugin_For_WordPress
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
     * @see plugin_setup()
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
        add_shortcode( 'github_oauth', array( $this, 'render_shortcode' ) );
        add_filter( 'plugin_row_meta', array( $this, 'donate_link' ), 10, 4 );
		include_once 'inc/plugin-updates/plugin-update-checker.php';
		$updateChecker = new PluginUpdateChecker(
			'https://raw.github.com/brasofilo/github-plugin-for-wordpress/master/inc/update.json',
			__FILE__,
			'github-plugin-for-wordpress-master'
		);
		add_filter( 'upgrader_source_selection', array( $this, 'rename_github_zip' ), 1, 3);
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
     * Render the shortcode
     * 
     * Used in admin page as well
     * 
     * @param array $atts
     * @return string
     */
    public function render_shortcode( $atts )
    {
        wp_enqueue_style( 'github-style', plugins_url( '/css/style.css', __FILE__ ) );
        
        if( !$token = get_option( 'GITHUB_AUTHENTICATION_TOKEN' ) )
            return;
        
        add_filter( 'https_ssl_verify', '__return_false' );
        $return = '';
        # Duplicate tabs for frontend
        if( !is_admin() )
        {
            wp_enqueue_script( 'jquery' );
            $return .= '
            <h2 class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active" data-tab="tab-1">'.__('Repos', 'b5f_gpfw' ).'</a>
                <a href="#" class="nav-tab" data-tab="tab-2">'.__('Gists', 'b5f_gpfw' ).'</a>
                <a href="#" class="nav-tab" data-tab="tab-3">'.__('Starred', 'b5f_gpfw' ).'</a>
            </h2>
            <script type="text/javascript">
jQuery(document).ready(function($) 
{    

    $("a.nav-tab").click(function(e){  
        e.preventDefault();
        var tab_id = $(this).attr("data-tab");  
        if( "tab-1" == tab_id )
            $( "div.github" ).show();
        else
            $( "div.github" ).hide();
            

        $("a.nav-tab").removeClass("nav-tab-active");  
        $(".tab-content").removeClass("current");  

        $(this).addClass("nav-tab-active");  
        $("#"+tab_id).addClass("current");  
    })  
});
</script>';
        }
        # USER
            $api_url = "https://api.github.com/user?access_token=$token";
            $user = $this->build_user( $api_url );
            $login = $user['login'];
            $return .= $user['return'];
 
        # REPOSITORIES
        $return .= '<div id="tab-1" class="tab-content current">';
            $api_url = "https://api.github.com/user/repos?access_token=$token&per_page=100";
            $return .= $this->build_repos( $api_url );
        $return .= '</div>';
        
        # GISTS
        $return .= '<div id="tab-2" class="tab-content">';
            $api_url = "https://api.github.com/users/$login/gists?access_token=$token&per_page=100";
            $return .= $this->build_gists( $api_url );
        $return .= '</div>';
        
        # STARRED
        $return .= '<div id="tab-3" class="tab-content">';
            $api_url = "https://api.github.com/user/starred?access_token=$token&per_page=100";
            $return .= $this->build_stars( $api_url );
        $return .= '</div>';
            
        return $return;
    }
 
    
    /**
     * Builds the header with user info
     * 
     * @param string $api_url
     * @return string
     */
    private function build_user( $api_url )
    {
        $json = get_transient( 'b2w_get_user' );
        if( !$json )
        {
            $response = wp_remote_get( $api_url );

            ## ERROR
            if ( is_wp_error( $response ) )
                return '<div class="error">API ERROR: invalid user token</div>';

            $json = json_decode( $response['body'] );
            set_transient( 'b2w_get_user', $json, 60*60 );
        }
        extract( (array)$json );
        $str_repos = __( 'repos', 'b5f_gpfw' );
        $str_followers = __( 'followers', 'b5f_gpfw' );
        $str_following = __( 'following', 'b5f_gpfw' );
        $return = <<<HTML
<div class="github">
    <h2><a href="$html_url" target="_blank">$name</a></h2>
    <div class="bio">
        <span>$bio</span>
    </div>
    <div class="counts">
        <div><a href="https://github.com/$login?tab=repositories" target="_blank">$public_repos</a><br/><span>$str_repos</span></div>
        <div><a href="https://github.com/$login/followers" target="_blank">$followers</a><br/><span>$str_followers</span></div>
        <div><a href="https://github.com/$login/following" target="_blank">$following</a><br/><span>$str_following</span></div>
    </div>
HTML;
        return array( 'login' => $login, 'return' => $return );
    }

    
    /**
     * Builds the user's Repositories
     * 
     * @param string $api_url
     * @return string
     */
    private function build_repos( $api_url )
    {
        $return = '';
        $json = get_transient( 'b2w_get_repos' );
        if( !$json )
        {
            $response = wp_remote_get( $api_url );

            ## ERROR
            if ( is_wp_error( $response ) )
                return '<div class="error">'.__('API ERROR: invalid repository token', 'b5f_gpfw' ).'</div>';

            $json = json_decode( $response['body'] );
            set_transient( 'b2w_get_repos', $json, 60*60 );
        }
        
        $this->sort_on_field($json, 'updated_at', 'DESC');
        
        $return .= '<ol class="repos">';
        foreach( $json as $i => $repos )
        {
            extract( (array)$repos );
            $repo_name = str_replace( '_', ' ', $name );
            $repo_name = str_replace( '-', ' ', $repo_name );
            $repo_name = ucwords( $repo_name );
            
            $fork_url = ( (int)$forks_count > 0 ) 
                ? "<a href='$svn_url/network/members' target='_blank'>" : '';
            $fork_end_url = ( (int)$forks_count > 0 ) ? "</a>" : '';
            
            $forked_class = ( (int)$fork > 0 ) 
                ? "bg-not-mine" : 'bg-mine';

            $star_url = ( (int)$watchers_count > 0 ) 
                ? "<a href='$svn_url/stargazers' target='_blank'>" : '';
            $star_end_url = ( (int)$watchers_count > 0 ) ? "</a>" : '';
            $date = date_format( date_create( $updated_at ), 'd-m-Y');
            $return .= <<<HTML
    <li class="$forked_class">
    <h3><a href="$html_url" target="_blank" title="$description">$repo_name</a></h3>
                <small>$date</small>
    <div>
        $fork_url<div>
            $forks_count
            <br/><span>forks</span>
        </div>$fork_end_url
        $star_url<div>
            $watchers_count
            <br/><span>watchers</span>
        </div>$star_end_url
    </div>
    </li>
HTML;
        }
        $return .= '</ol>';
        $return .= '</div><!-- .github -->';
        return $return;
    }

    
    /**
     * Builds the user's Gists
     * 
     * @param string $api_url
     * @return string
     */
    private function build_gists( $api_url )
    {
        $json = get_transient( 'b2w_get_gists' );
        if( !$json )
        {
            $response = wp_remote_get( $api_url );

            ## ERROR
            if ( is_wp_error( $response ) )
                $json = null;
            else
            {
                $json = json_decode( $response['body'] );
                set_transient( 'b2w_get_gists', $json, 60*60 );
            }
        }
        $return = '';
        if( !empty( $json ) )
        {
            $return = '<hr /><table class="widefat" id="gists-table">
        <thead>
            <tr>
            <th class="row-title">Gists ('.count($json).')</th>
            <th>'.__('Description', 'b5f_gpfw' ).'</th>
            </tr>
        </thead>
        <tbody>';
            $this->sort_on_field($json, 'updated_at', 'DESC');
            $count = 0;
            foreach( $json as $gist )
            {
                $alt = ( $count++ % 2 == 0 ) ? '' : 'class="alternate"';
                $return .= sprintf(
                    '<tr %s><td class="row-title"><a href="%s" target="_blank">%s</a><div class="row-actions"><span class="edit">%s: %s</span></div></td><td>%s</td></tr>',
                    $alt,
                    $gist->html_url,
                    $gist->id,
                    __( 'Updated', 'b5f_gpfw' ),
                    date_format( date_create( $gist->updated_at ), 'd-m-Y'),
                    $gist->description
                );
            }
            $return .= '</tbody>
        <tfoot>
            <tr>
                <th class="row-title">Gists ('.count($json).')</th>
                <th>'.__('Description', 'b5f_gpfw' ).'</th>
            </tr>
        </tfoot>
    </table>';
        }
        return $return;
    }

    
    /**
     * Builds the user's Starred Repos
     * 
     * @param string $api_url
     * @return string
     */
    private function build_stars( $api_url )
    {
        
        $json = get_transient( 'b2w_get_stars' );
        if( !$json )
        {
            $response = wp_remote_get( $api_url );

            ## ERROR
            if ( is_wp_error( $response ) )
                $json = null;
            else
            {
                $json = json_decode( $response['body'] );
                set_transient( 'b2w_get_stars', $json, 60*60 );
            }
        }
        $return = '';
        if( !empty( $json ) )
        {
            $return = '<hr /><table class="widefat" id="stars-table">
        <thead>
            <tr>
            <th class="row-title">Starred ('.count($json).')</th>
            <th>'.__('Description', 'b5f_gpfw' ).'</th>
            </tr>
        </thead>
        <tbody>';
            $this->sort_on_field($json, 'updated_at', 'DESC');
            $count = 0;
            foreach( $json as $star )
            {
                $repo_name = str_replace( '_', ' ', $star->name );
                $repo_name = str_replace( '-', ' ', $repo_name );
                $repo_name = ucwords( $repo_name );

                $alt = ( $count++ % 2 == 0 ) ? '' : 'class="alternate"';
                $return .= sprintf(
                    '<tr %s><td class="row-title starred"><a href="%s" target="_blank">%s</a><div class="row-actions"><span class="edit">%s: %s</span> | <span class="edit">%s: %s</span></div></td><td>%s</td></tr>',
                    $alt,
                    $star->html_url,
                    $repo_name,
                    __('Author', 'b5f_gpfw' ),
                    $star->owner->login,
                    __('Updated', 'b5f_gpfw' ),
                    date_format( date_create( $star->updated_at ), 'd-m-Y'),
                    $star->description
                );
            }
            $return .= '</tbody>
        <tfoot>
            <tr>
                <th class="row-title">Starred ('.count($json).')</th>
                <th>'.__('Description', 'b5f_gpfw' ).'</th>
            </tr>
        </tfoot>
    </table>';
        }
        return $return;
    }

    
    /**
     * Associative array sort OnKey OrderBy
     * 
     * @param array  $objects Reference array to sort
     * @param string $on      Array key to sort
     * @param string $order   Order by
     */
    private function sort_on_field( &$objects, $on, $order = 'ASC' ) { 
        $comparer = ($order === 'DESC') 
            ? "return -strcmp(\$a->{$on},\$b->{$on});" 
            : "return strcmp(\$a->{$on},\$b->{$on});"; 
        usort($objects, create_function('$a,$b', $comparer)); 
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