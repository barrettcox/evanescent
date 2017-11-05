(function ($, root, undefined) {

  $(function () {

    'use strict';

    // DOM ready, take it away
    console.log('Admin JS Loaded');

    var showEmailButton = $('.evanescent-admin__show');

    if (showEmailButton.length) {
      showEmailButton.click(function(){
        var emailRow = $(this).closest('tr').next('.evanescent-email-row');
        if (emailRow.length) {
          if (emailRow.is(':visible')) {
            emailRow.hide();
          }
          else {
            emailRow.show();
          }
        }
      });
    }
  });
})(jQuery, this);