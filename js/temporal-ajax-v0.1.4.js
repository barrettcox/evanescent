'use strict';

var Temporal = Temporal || {};

(function ($) {

  Temporal.init = function() {
    var defaults = {
          'invitee'         : $('#temporal-invitee'),
          'timer'           : $('#temporal-timer'),
          'triggerSelector' : '[data-temporal-trigger]',
        };

    for (var prop in defaults) {
      if (typeof Temporal[prop] === 'undefined') {
        Temporal[prop] = defaults[prop];
      }
    }

    if (Temporal.invitee.length) {
      Temporal.username = Temporal.invitee.attr('data-temporal-username');
      Temporal.gateName = Temporal.invitee.attr('data-temporal-gate-name');
      Temporal.gateId   = Temporal.invitee.attr('data-temporal-gate');
      Temporal.url      = Temporal.invitee.attr('data-temporal-url');
      Temporal.ajaxCheckTime(true);
    }

    //if (Temporal.trigger.length) {
    $(document).on('click', Temporal.triggerSelector ,function(e) {
      e.preventDefault();

      var gateId = $(this).attr('data-temporal-gate') || Temporal.gateId;

      Temporal.startSecondaryTimer(gateId);

    }); 
    //}
  };

  // Adds leading zeros to single digits
  Temporal.zeroPad = function(num, size) {
    var s = '0' + num;
    return s.substr(s.length - size);
  };

  // Assembles the hours/minutes/seconds for the timer
  Temporal.getHMS = function (totalSeconds) {
    var hour      = totalSeconds / 3600,
        hourInt   = Math.floor(hour),
        hourDec   = hour % 1,
        min       = hourDec * 60,
        minInt    = Math.floor(min),
        minDec    = min % 1,
        sec       = Math.floor(minDec * 60),
        hourStr   = hourInt + '',
        minStr    = Temporal.zeroPad(minInt, 2),
        secStr    = Temporal.zeroPad(sec, 2),
        remaining = hourStr + ':' + minStr + ':' + secStr;
    return remaining;
  };

  Temporal.ajaxCheckTime = function (initTimer=false) {
    $.ajax({
      url: frontendajax.ajaxurl,
      data: {
        'action' : 'temporal_ajax_check_time',
        'username' : Temporal.username,
        'gate' : Temporal.gateId
      },
      success:function(data) {

        if (data == 'expired') {
          // Expired
          window.location.href = Temporal.url;
        }

        else {
          // Not yet expired

          try {
            var obj = JSON.parse(data);

            // If neccessary, initiate the timer
            if (initTimer) {

              // If secondary event has been triggered and the timer is not visible...
              if (obj.secondary && Temporal.timer.not(':visible')) {
                Temporal.timer.show();
              }

              Temporal.timer.attr('data-temporal-remaining', obj.remaining);
              Temporal.startInterval(obj.secondary);
            }
          }

          catch(err) {
            console.log('Problem with JSON object: ');
            console.log(data);
          }
        }
      },
      error: function(errorThrown) {
        console.log(errorThrown);
      }
    });
  };

  Temporal.startInterval = function(secondaryTriggered) {
    // Using anonymous function so we can pass params inside setInterval
    var counter = 0;

    // Clear interval if interval has already been set
    if (typeof Temporal.timeCheckInterval !== 'undefined') {
      clearInterval(Temporal.timeCheckInterval);
    }

    Temporal.timeCheckInterval = setInterval(function() {

      console.log('interval');
 
      var timer = Temporal.timer;

      if (secondaryTriggered) {

        // Event was triggered but timer is missing from the DOM,
        // so let's redirect
        if (!timer.length) {
          window.location.href = Temporal.url;
        }

        // Timer exists in DOM
        else {

          var timerRemaining = timer.attr('data-temporal-remaining');

          timerRemaining = parseInt(timerRemaining) - 1;

          if (!timerRemaining) {
            // Timer is 0, redirect
            window.location.href = Temporal.url;
          }

          var timerText = Temporal.getHMS(timerRemaining);

          timer.attr('data-temporal-remaining', timerRemaining);

          // Update the timer if it is visible
          if (timer.is(':visible')) {
            timer.children('.temporal-timer__time').html(timerText);
          }

          if (counter % 10 == 0) {
            Temporal.ajaxCheckTime();             
          }
        }
      }
      
      counter++;  
    }, 1000); // Every 1 second
  };
  
  Temporal.startSecondaryTimer = function(gateId) {

    var gateId = gateId || Temporal.gateId;

    $.ajax({
      url: frontendajax.ajaxurl,
      data: {
        'action'   : 'temporal_ajax_init_secondary',
        'username' : Temporal.username,
        'gate'     : gateId
      },

      success: function(data) {
        console.log(data);
        if (data == 'success') {
          // Secondary timer has been initiated in the db
          // Do the initial Ajax call to initiate the timer
          Temporal.ajaxCheckTime(true)
        }
        else {
          // Problem updating the database
        }
      },

      error: function(errorThrown) {
        console.log(errorThrown);
      }
    });
  };

  $(document).ready(function() {
    Temporal.init();  
  });


})( jQuery );
