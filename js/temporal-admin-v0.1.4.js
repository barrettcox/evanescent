(function ($, root, undefined) {

  $(function () {

    'use strict';

    // DOM ready, take it away
    console.log('Admin JS Loaded');

    var showEmailButton = $('.temporal-admin__show');

    if (showEmailButton.length) {
      showEmailButton.click(function(){
        var emailRow = $(this).closest('tr').next('.temporal-username-row');
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