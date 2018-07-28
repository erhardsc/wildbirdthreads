<?php
global $count;
global $first_name;
global $title;
// $count starts at 1 instead of 0
$last_row_index = $count -1;

?>	 	
<div <?php if ( $last_row_index % 2 != 0 ): ?> class="container-fluid wildbird-social social-dark" <?php else :?> class="container-fluid wildbird-social social-light" <?php endif;?>>
	<h1 class="fancy-heading follow-name">Follow <?php 
		if ( two_artists($title) ) {
						echo $title;
					} else {
						echo $first_name;
					}
		 ?></h1>
	<?php the_field('social_icons'); ?>
	<?php if ( $last_row_index % 2 == 0 ): ?> <hr> <?php endif; ?>
<!--
	<div class="widget themify-social-links">
	    <ul class="social-links horizontal">
		    
		    <?php if ( get_field ( 'twitter' ) ): ?>
	        <li class="social-link-item twitter font-icon icon-x-large">
	            <a href="https://<?php the_field ( 'twitter' );?>" target="_blank"><i class="fa fa-twitter" ></i>  </a>
	        </li>
			<?php endif; ?>
			
			<?php if ( get_field ( 'facebook' ) ): ?>
	        <li class="social-link-item facebook font-icon icon-x-large">
	            <a href="https://<?php the_field ( 'facebook' );?>" target="_blank"><i class="fa fa-facebook" ></i>  </a>
	        </li>
	        <?php endif; ?>
	        
	        <?php if ( get_field ( 'tumblr' ) ): ?>
	        <li class="social-link-item  font-icon icon-x-large">
	            <a href="https://<?php the_field ( 'tumblr' );?>" target="_blank"><i class="fa fa-tumblr" ></i>  </a>
	        </li>
			<?php endif; ?>
			
			 <?php if ( get_field ( 'instagram-link' ) ): ?>
	        <li class="social-link-item  font-icon icon-x-large">
	            <a href="https://<?php the_field ( 'instagram-link' );?>" target="_blank"><i class="fa fa-instagram" ></i>  </a>
	        </li>
	        <?php endif; ?>
	    </ul>
	</div>
-->
</div>

