<p>
	<h2>Your Instagram Profile</h2>
	<hr />
    
   <div id="enjoy_user_profile">
                    	 <img class="enjoy_user_profile" src="<?php echo get_option('enjoyinstagram_user_profile_picture'); ?>"> 
                           <input type="button" id="button_logout" value="Unlink Profile" class="button-primary ei_top" />
                         </div>
        
            <div id="enjoy_user_block" >
            <h3><?php echo get_option('enjoyinstagram_user_username'); ?></h3>
            <p><i><?php echo get_option('enjoyinstagram_user_bio'); ?></i></p>
            <hr/>
            Customize the plugin with our <a href="<?php echo get_admin_url(); ?>options-general.php?page=enjoyinstagram_plugin_options&tab=enjoyinstagram_advanced_settings">settings</a> tab.
            
            <hr />   
            </div>

            </p>
<div class="wrap" style="
    float: left;
    width: 95%;
    background: rgba(79, 173, 26, 0.45);
    padding: 20px;
    margin-top: 20px;
    border: 2px solid green;">
<h3>Shortocodes to use:</h3>
<b>[enjoyinstagram_mb]</b> -> Carousel View <br />
<b>[enjoyinstagram_mb_grid]</b> -> Grid View
</div>