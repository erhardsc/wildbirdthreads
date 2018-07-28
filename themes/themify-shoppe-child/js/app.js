$=jQuery;

// Single Featured Artist
jQuery( document ).ready( function() {
  
	jQuery('.excerpt').each(function( index ) {
		index+=1;
		$(this).addClass("read-on-" + index);
		
		
		$('.read-on-'+index).click(function() {	
			$('#excerpt-'+index).hide();
	        $('#content-'+index).fadeIn('slow');
	        $(this).remove();
		
		});
		
		
	}); // End loop
	
	let total = $('.question-answer').length;
	
	if( $('.question-answer-'+total).css('background-color')=="rgb(253, 249, 249)") {
		
		$('.social-dark').css("background", "white");
	} else {
		$('.social-dark').css("background", "#fdf8f8");
	}
	
}); // End Document Ready