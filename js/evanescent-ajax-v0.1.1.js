(function ($, root, undefined) {

  $(function () {

    'use strict';

    // DOM ready, take it away

    // Adds leading zeros to single digits
    function zeroPad(num, size) {
      var s = '0' + num;
      return s.substr(s.length - size);
    }

    function getHMS(totalSeconds) {
      var hour      = totalSeconds / 3600,
          hourInt   = Math.floor(hour),
          hourDec   = hour % 1,
          min       = hourDec * 60,
          minInt    = Math.floor(min),
          minDec    = min % 1,
          sec       = Math.floor(minDec * 60),
          hourStr   = hourInt + '',
          minStr    = zeroPad(minInt, 2),
          secStr    = zeroPad(sec, 2),
          remaining = hourStr + ':' + minStr + ':' + secStr;
      return remaining;
    }

    function ajaxCheckTime(updateTimer=false) {

      $.ajax({
        url: frontendajax.ajaxurl,
        data: {
          'action' : 'evanescent_ajax_check_time',
          'email' : email,
          'gate' : gate
        },
        success:function(data) {
          console.log(data);
          if (data == 'expired') {
            window.location.href = url;
          }
          else {
            // Not yet expired
            if (updateTimer) {
              $('#evanescent-timer').attr('data-evanescent-remaining', data);
              startTimer();
            }
          }
        },
        error: function(errorThrown){
          console.log(errorThrown);
        }
      });
    }

    function startTimer() {
      // Using anonymous function so we can pass params inside setInterval
      var counter = 0;
      var timeCheck = setInterval(function() {
   
                        var timer          = $('#evanescent-timer'),
                            timerRemaining = timer.attr('data-evanescent-remaining');

                        timerRemaining = parseInt(timerRemaining) - 1;

                        var timerText = getHMS(timerRemaining);

                        timer.attr('data-evanescent-remaining', timerRemaining);
                        timer.children('.evanescent-timer__time').html(timerText);

                        if (!timerRemaining) {
                          // Timer is 0, redirect
                          window.location.href = url;
                        }

                        if (counter % 10 == 0) {
                          ajaxCheckTime();             
                        }
                        
                        counter++;  
                      }, 1000); // Every 1 second
    }

    var invitee = $('#evanescent-invitee');

    if (invitee.length) {
      var email = invitee.attr('data-evanescent-email'),
          gate  = invitee.attr('data-evanescent-gate'),
          url   = invitee.attr('data-evanescent-url');

      // Do the initial Ajax call to set the timer
      ajaxCheckTime(true)

    }

  });
})(jQuery, this);