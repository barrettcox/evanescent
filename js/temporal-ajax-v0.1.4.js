'use strict';

var Temporal = Temporal || {};

Temporal.App = ( function($) {

  return {

    init: function() {
      var app      = this, // Store reference to Temporal.App object
          defaults = {
            'invitee'         : $('#temporal-invitee'),
            'timer'           : $('#temporal-timer'),
            'triggerSelector' : '[data-temporal-trigger]',
          };

      for (var prop in defaults) {
        if (typeof app[prop] === 'undefined') {
          app[prop] = defaults[prop];
        }
      }

      if (app.invitee.length) {
        app.username = app.invitee.attr('data-temporal-username');
        app.gateName = app.invitee.attr('data-temporal-gate-name');
        app.gateId   = app.invitee.attr('data-temporal-gate');
        app.url      = app.invitee.attr('data-temporal-url');
        app.ajaxCheckTime(true);
      }

      $(document).on('click', app.triggerSelector ,function(e) {
        e.preventDefault();

        var gateId = $(this).attr('data-temporal-gate') || app.gateId;

        app.startSecondaryTimer(gateId);

      }); 
      
    },

    // Adds leading zeros to single digits
    zeroPad: function(num, size) {
      var s = '0' + num;
      return s.substr(s.length - size);
    },

    // Assembles the hours/minutes/seconds for the timer
    getHMS: function (totalSeconds) {
      var app       = this, // Store reference to Temporal.App object
          hour      = totalSeconds / 3600,
          hourInt   = Math.floor(hour),
          hourDec   = hour % 1,
          min       = hourDec * 60,
          minInt    = Math.floor(min),
          minDec    = min % 1,
          sec       = Math.floor(minDec * 60),
          hourStr   = hourInt + '',
          minStr    = app.zeroPad(minInt, 2),
          secStr    = app.zeroPad(sec, 2),
          remaining = hourStr + ':' + minStr + ':' + secStr;
      return remaining;
    },

    ajaxCheckTime: function (initTimer=false) {
      var app = this; // Store reference to Temporal.App object

      $.ajax({
        url: frontendajax.ajaxurl,
        data: {
          'action' : 'temporal_ajax_check_time',
          'username' : app.username,
          'gate' : app.gateId
        },
        success:function(data) {

          if (data == 'expired') {
            // Expired
            window.location.href = app.url;
          }

          else {
            // Not yet expired

            try {
              var obj = JSON.parse(data);

              // If neccessary, initiate the timer
              if (initTimer) {

                // If secondary event has been triggered and the timer is not visible...
                if (obj.secondary && app.timer.not(':visible')) {
                  app.timer.show();
                }

                app.timer.attr('data-temporal-remaining', obj.remaining);
                app.startInterval(obj.secondary);
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
    },

    startInterval: function(secondaryTriggered) {
      var app     = this, // Store reference to Temporal.App object
          counter = 0;

      // Clear interval if interval has already been set
      if (typeof app.timeCheckInterval !== 'undefined') {
        clearInterval(app.timeCheckInterval);
      }

      app.timeCheckInterval = setInterval(function() {

        console.log('interval');
   
        var timer = app.timer;

        if (secondaryTriggered) {

          // Event was triggered but timer is missing from the DOM,
          // so let's redirect
          if (!timer.length) {
            window.location.href = app.url;
          }

          // Timer exists in DOM
          else {

            var timerRemaining = timer.attr('data-temporal-remaining');

            timerRemaining = parseInt(timerRemaining) - 1;

            if (!timerRemaining) {
              // Timer is 0, redirect
              window.location.href = app.url;
            }

            var timerText = app.getHMS(timerRemaining);

            timer.attr('data-temporal-remaining', timerRemaining);

            // Update the timer if it is visible
            if (timer.is(':visible')) {
              timer.children('.temporal-timer__time').html(timerText);
            }

            if (counter % 10 == 0) {
              app.ajaxCheckTime();             
            }
          }
        }
        
        counter++;  
      }, 1000); // Every 1 second
    },
    
    startSecondaryTimer: function(gateId) {

      console.log('gateId:');
      console.log(gateId);

      var app    = this, // Store reference to Temporal.App object
          gateId = gateId || app.gateId;

      $.ajax({
        url: frontendajax.ajaxurl,
        data: {
          'action'   : 'temporal_ajax_init_secondary',
          'username' : app.username,
          'gate'     : gateId
        },

        success: function(data) {
          console.log(data);
          if (data == 'success') {
            // Secondary timer has been initiated in the db
            // Do the initial Ajax call to initiate the timer
            app.ajaxCheckTime(true)
          }
          else {
            // Problem updating the database
          }
        },

        error: function(errorThrown) {
          console.log(errorThrown);
        }
      });
    }
  };
})( jQuery );

// DOM ready before we initialize
jQuery(document).ready(function($) {
  // Initialize the Temporal.App object
  var temporal = Temporal.App;
  temporal.init();
});
