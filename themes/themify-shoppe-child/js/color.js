var img = document.getElementById('coverImage');


RGBaster.colors(img, {
  success: function(payload) {
    // You now have the payload.
    console.log(payload.dominant);
    console.log(payload.secondary);
    console.log(payload.palette);
  }
});




/*
function colorChange(){
  //Be sure to include <img id="coverImage" src="" alt=""/>
  var $myImage = jQuery("#coverImage");
  var colorThief = new ColorThief();
  
  //Grabs 8 swatch color palette from image and sets quality to 5 (0 =slow, 10=default/fast)
  var cp = colorThief.getPalette($myImage[0], 8, 5);
  
  //Sets background to 3rd color in the palette.
  jQuery('html').css('background-color', 'rgb('+cp[2][0]+','+cp[2][1]+','+cp[2][2]+')');
}

jQuery(document).ready(function() {
  //Make sure image is loaded before running.
  colorChange();
});
*/