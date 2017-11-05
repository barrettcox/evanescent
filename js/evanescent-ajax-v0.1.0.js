(function ($, root, undefined) {

  $(function () {

    'use strict';

    // DOM ready, take it away
    function ajaxCheckTime(email, gate, welcomeUrl) {

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
            window.location.href = welcomeUrl;
          }
        },
        error: function(errorThrown){
          console.log(errorThrown);
        }
      });
    }

    var invitee = $('#evanescent-invitee');
    if (invitee.length) {
      var email = invitee.attr('data-evanescent-email'),
          gate  = invitee.attr('data-evanescent-gate'),
          url   = invitee.attr('data-evanescent-url');
      // Using anonymous function so we can pass params inside setInterval
      var timeCheck = setInterval(function() { ajaxCheckTime(email, gate, url); }, 5000);
    }

  });
})(jQuery, this);