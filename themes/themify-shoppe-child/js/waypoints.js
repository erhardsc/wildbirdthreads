$=jQuery;
$( document ).ready(function() {


  if(Waypoint.viewportWidth() > 995) {



    //////////////// WAYPOINT ANNIMATIONS FOR SINGLE-ARTIST PAGE //////////////////////
    $('.waypoint-right').css('visibility', 'hidden');
    $('.waypoint-right').waypoint(function () {
        $('.waypoint-right').addClass('animated fadeInRight').css('visibility', 'visible');
      },
      {
        offset: "80%"
      });

    $('.mountains').css('visibility', 'hidden');
    $('.mountains').waypoint(function () {
        $('.mountains').addClass('animated fadeIn').css('visibility', 'visible');
      },
      {
        offset: "80%"
      });

    $('.birds').css('visibility', 'hidden');
    $('.birds').waypoint(function () {
        $('.birds').addClass('animated fadeIn').css('visibility', 'visible');
      },
      {
        offset: "80%"
      });


    // Dynamically add Waypoint classes and add image slide annimation based on index value
    $('.question-answer .image-cropper').each(function (index) {
      index += 1;
      $('.question-answer-' + index + ' .image-cropper').css('visibility', 'hidden');
      $('.question-answer-' + index + ' .image-cropper').waypoint(function () {

          if (index % 2 === 0) {
            $('.question-answer-' + index + ' .image-cropper').addClass('animated fadeInLeft');

          } else {
            $('.question-answer-' + index + ' .image-cropper').addClass('animated fadeInRight');
          }

          $('.question-answer-' + index + ' .image-cropper').css('visibility', 'visible');
        },
        {
          offset: "80%"
        });
    });

    // Dynamically add Waypoint classes and add text slide annimation based on index value
    $('.question-answer .artist-question').each(function (index) {
      index += 1;
      $('.question-answer-' + index + ' .artist-question').css('visibility', 'hidden');
      $('.question-answer-' + index + ' .artist-question').waypoint(function () {
          $('.question-answer-' + index + ' .artist-question').addClass('animated fadeInLeft').css('visibility', 'visible');
        },
        {
          offset: "80%"
        });
    });


    //////////////// WAYPOINT ANNIMATIONS FOR SINGLE-PRODUCT PAGE //////////////////////
    $('.waypoint-left').css('visibility', 'hidden');
    $('.waypoint-left').waypoint(function () {
        console.log('yo');
        $('.waypoint-left').addClass('animated fadeInLeft').css('visibility', 'visible');
      },
      {
        offset: '80%'
      });

    $('.waypoint-fade').css('visibility', 'hidden');
    $('.waypoint-fade').waypoint(function () {
        $('.waypoint-fade').addClass('animated fadeIn').css('visibility', 'visible');
      },
      {
        offset: '80%'
      });


    $('.waypoint-up').css('visibility', 'hidden');
    $('.waypoint-up').waypoint(function () {
        $('.waypoint-up').addClass('animated fadeInUp').css('visibility', 'visible');
      },
      {
        offset: '80%'
      });

    $('.artist-button').css('visibility', 'hidden');
    $('.artist-button').waypoint(function () {
        $('.artist-button').addClass('animated tada').css('visibility', 'visible');
      },
      {
        offset: '80%'
      });

  }
	
});