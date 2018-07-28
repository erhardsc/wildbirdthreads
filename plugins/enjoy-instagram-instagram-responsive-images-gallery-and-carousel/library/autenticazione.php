<p style="font-size:14px;">Thank you for you choice! <strong>Enjoy Plugin for Instagram - Responsive gallery</strong> is a plugin lovingly developed for you by <a href="http://www.mediabeta.com" target="_blank"> Mediabeta</a>.</p>

<p style="font-size:14px;">By using this plugin, you are agreeing to the <a href="http://instagram.com/about/legal/terms/api/" target="_blank">Instagram API Terms of Use</a>.</p>

 

<script>
(function($) {
    $(document).ready(function() { 
  var allPanels = $('.enjoy_accordion > dd').hide();
    
  $('.enjoy_accordion > dt > a').click(function() {
    allPanels.slideUp();
    if (!$(this).parent().hasClass('enjoyinstagram_active')){
		 $('.enjoy_accordion > dd').removeClass('enjoyinstagram_active');
		 $(this).parent().next().slideDown();
		 $(this).parent().addClass('enjoyinstagram_active');
		 
		 
	}else{
		 $(this).parent().removeClass('enjoyinstagram_active');
		}
    return false;
  });
  });
})(jQuery);
</script> 
<style>
.enjoy_accordion dt{
	background:rgba(204,204,204,0.5);
	font-size:1.1rem;
	padding-top:1rem;
	padding-bottom:1rem;
	margin-bottom:1px;
	}
	.enjoy_accordion dt a{
	text-decoration:none; padding:1rem;
	}
	.step_number 
	{width: 2rem;
height: 2rem;
border-radius: 1rem;

color: #fff;
line-height: 2rem;
text-align: center;
background: #0074a2;
display:inline-block;
}
.enjoy_accordion {
   margin: 50px;   
   dt, dd {
      padding: 10px;
      border: 1px solid black;
      border-bottom: 0; 
      &:last-of-type {
        border-bottom: 1px solid black; 
      }
      a {
        display: block;
        color: black;
        font-weight: bold;
      }
   }
  dd {
     border-top: 0; 
     font-size: 12px;
     &:last-of-type {
       border-top: 1px solid white;
       position: relative;
       top: -1px;
     }
  }
}

.enjoy_open {content: "\f347";}
.enjoy_close {content: "\f343";}
.button_accordion {display:inline-block; float:right; margin-right:1rem;}
 
 
</style>


<form method="post" action="options.php">
    <?php settings_fields('enjoyinstagram_options_group_auth'); ?>

    <p>
        <input type="button" class="button-primary" id="button_autorizza_instagram" name="button_autorizza_instagram" value="Connect your Account" />
    </p>

</form>
 
  