<?php
	if( have_rows('artist_quote') ):
		$i = 1; ?>

<div class="background-wrapper birds">
	<div id="quote" class="container-fluid box">
		<div id="artist-quote" class="row">
			<div class="col-md-8 col-sm-8">
				<span id="quote-marks">"</span>
<!-- 				<span><?php //the_field( 'artist_quote1' ); ?></span> -->
				<?php
						while ( have_rows('artist_quote') ) : the_row();
							
					        // Your loop code
							if ( $i == 1 ){
					        the_sub_field('quote');
					        }
							++$i;
					    endwhile;
				?>	
				<span id="quote-marks">"</span>
			</div>
		</div>
	</div>
</div>
<?php 

					else :

						// no rows found

					endif; ?>
					
<?php
if( have_rows('artist_quote') ):
	$i = 1; ?>
<div class="background-wrapper mountains">
	<div id="quote2" class="container-fluid box">
		<div id="artist-quote" class="row waypoint-right">
			<div class="col-md-offset-4 col-sm-offset-4 right">
				<span id="quote-marks">"</span>
<!-- 				<span><?php //the_field( 'artist_quote2' ); ?></span> -->
				<?php
				
						while ( have_rows('artist_quote') ) : the_row();

					        // Your loop code
					        if ( $i == 2 ){
					        the_sub_field('quote');
					        }
							++$i;
					    endwhile;

					?>
				<span id="quote-marks">"</span>
			</div>
		</div>
	</div>
</div>
<?php

else :

						// no rows found

					endif; ?>