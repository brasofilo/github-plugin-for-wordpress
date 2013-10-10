<?php

if( $_SERVER["REQUEST_METHOD"] == "POST" )
{
    if( isset( $_POST['reset'] ) )
    {
        delete_option( 'GITHUB_API_KEY' );
        delete_option( 'GITHUB_API_SECRET_KEY' );
        delete_option( 'GITHUB_AUTHENTICATION_TOKEN' );
    }
    else
    {
        if( isset( $_POST['api_key'] ) )
            update_option( 'GITHUB_API_KEY', $_POST['api_key'] );
        if( isset( $_POST['api_secret_key'] ) )
            update_option( 'GITHUB_API_SECRET_KEY', $_POST['api_secret_key'] );
    }
}


$state = base64_encode( time() );
$redirect = admin_url($this->plugin_admin_url);
$api_key = get_option( 'GITHUB_API_KEY' );
$api_secret = get_option( 'GITHUB_API_SECRET_KEY' );
$token = get_option( 'GITHUB_AUTHENTICATION_TOKEN' );

if( $api_key && $api_secret && $token )
{
    $tab1 = 'nav-tab-active';
    $wrap1 = 'current';
    $tab4 = '';
    $wrap4 = '';
}
else
{
    $tab1 = '';
    $wrap1 = '';
    $tab4 = 'nav-tab-active';
    $wrap4 = 'current';
}
?>

<div class="wrap">
	<div id="icon-options-general" class="icon32"></div>
	<h2>iGit Hello World</h2>
	
	<div id="poststuff">
		<div id="post-body" class="metabox-holder">
			<!-- main content -->
			<div id="post-body-content">
            <h2 class="nav-tab-wrapper"><?php if(!empty($tab1)): ?>
                <a href="#" class="nav-tab <?php echo $tab1; ?>" data-tab="tab-1">Repos</a>
                <a href="#" class="nav-tab" data-tab="tab-2">Gists</a>
                <a href="#" class="nav-tab" data-tab="tab-3">Starred</a><?php endif; ?>
                <a href="#" class="nav-tab <?php echo $tab4; ?>" data-tab="tab-4">Settings</a>
            </h2>

			<?php echo do_shortcode( '[github_oauth]' ); ?>
               
           
            <form name="options" method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>" id="tab-4" class="tab-content <?php echo $wrap4; ?>">
            <table class="form-table">


                <!----------- CLIENT ID -------------->
                <tr valign="top">
                    <th scope="row"><label for="api_key"><?php _e( 'Client ID', 'b5f_gpfw' ); ?><span class="required">(*)</span>: </label></td>
                    <td><input type="text" name="api_key" value="<?php echo get_option( 'GITHUB_API_KEY', '' ); ?>" size="70" class="all-text"></td>
                </tr>


                <!----------- CLIENT SECRET -------------->
                <tr valign="top" class="alternate">
                    <th scope="row"><label for="api_secret_key"><?php _e( 'Client Secret', 'b5f_gpfw' ); ?><span class="required">(*)</span>: </label></td>
                    <td><input type="text" name="api_secret_key" value="<?php echo get_option( 'GITHUB_API_SECRET_KEY', '' ); ?>" size="70"></td>
                </tr>


                <?php // TOKEN FIELD
                if( $api_key && $api_secret ): 
                    // Make input box light colored
                    $token_css = empty($token) ? 'style="background-color:rgba(255,0,0,.3)"' : '';
                    ?>                    
                    <!----------- AUTHENTICATION TOKEN -------------->
                    <tr valign="top">
                        <th scope="row"><label for="bearer_token"><?php _e( 'Authentication Token', 'b5f_gpfw' ); ?>: </label></td>
                        <td><input type="text" disabled value="<?php echo get_option( 'GITHUB_AUTHENTICATION_TOKEN', '' ); ?>" size="70" <?php echo $token_css; ?>></td>
                    </tr>
                <?php
                endif; 


        # MISSING THE TOKEN
        if( $api_key && $api_secret && !$token )
        {
            $api_url = "https://github.com/login/oauth/authorize?client_id=$api_key&scope=&state=$state&redirect_uri=$redirect";
            ?>
                <tr valign="top">
                    <td colspan="2"><a class="button-primary" type="button" href="<?php echo $api_url; ?>"><?php _e( 'Click to get the token', 'b5f_gpfw' ); ?></a></td>
                </tr>
                <tr valign="top">
                    <td colspan="2"><input class="button-secondary" type="submit" name="reset" value="<?php _e( 'Reset', 'b5f_gpfw' ); ?>" />
                </tr>
            <?php
        } 



        # ALL SET
        elseif( $api_key && $api_secret && $token )
        {
            ?>
                <tr valign="top">
                    <td colspan="2"><input class="button-primary" type="submit" name="reset" value="<?php _e( 'Reset', 'b5f_gpfw' ); ?>" />
                </tr>
            <?php
        }



        # NOTHING SET
        else
        {
            ?>
                <tr valign="top">
                    <td colspan="2"><input class="button-secondary" type="submit" name="save" value="<?php _e( 'Save ID and Secret', 'b5f_gpfw' ); ?>" />
                </tr>
                <tr valign="top">
                    <td colspan="2"><small><?php _e( 'Sign up for an <a href="https://github.com/settings/applications/new" target="_blank">API key</a>.', 'b5f_gpfw' ); ?></small>
                </tr>
            <?php
        }



        # AND THE REST
        ?>
                <tr valign="top">
                    <td colspan="2"><small><?php _e( 'Read the <a href="https://developer.github.com/" target="_blank">API documentation</a>', 'b5f_gpfw' ); ?></small>
                </tr>

                </table> 
            </form>
                
			</div> <!-- post-body-content -->
		</div> <!-- #post-body .metabox-holder .columns-2 -->
		<br class="clear">
	</div> <!-- #poststuff -->
</div> <!-- .wrap -->

<script type="text/javascript">
jQuery(document).ready(function($) 
{    

    $('a.nav-tab').click(function(){  
        var tab_id = $(this).attr('data-tab');  
        if( 'tab-1' == tab_id )
            $( 'div.github' ).show();
        else
            $( 'div.github' ).hide();
            

        $('a.nav-tab').removeClass('nav-tab-active');  
        $('.tab-content').removeClass('current');  

        $(this).addClass('nav-tab-active');  
        $("#"+tab_id).addClass('current');  
    })  
});
</script>
