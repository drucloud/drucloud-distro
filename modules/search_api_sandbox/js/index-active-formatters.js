/**
 * @file
 * Attaches show/hide functionality to processor checkboxes in "Filters" tabs.
 */

(function ($) {

  "use strict";

  Drupal.behaviors.searchApiIndexFormatter = {
    attach: function (context, settings) {
      $('.search-api-status-wrapper input.form-checkbox', context).once('search-api-status', function () {
        var $checkbox = $(this);
        // Retrieve the table row belonging to this processor.
        var $row = $('#' + $checkbox.attr('id').replace(/-status$/, '-weight'), context).closest('tr');
        // Retrieve the vertical tab belonging to this processor.
        var $tab = $('#' + $checkbox.attr('id').replace(/-status$/, '-settings'), context).data('verticalTab');

        // Bind a click handler to this checkbox to conditionally show and hide
        // the filter's table row and vertical tab pane.
        $checkbox.bind('click.searchApiUpdate', function () {
          if ($checkbox.is(':checked')) {
            $('#edit-order').show();
            $('.tabledrag-toggle-weight-wrapper').show();
            $row.show();
            if ($tab) {
              $tab.tabShow().updateSummary();
            }
          }
          else {
            var $enabled_processors = $('.search-api-status-wrapper input.form-checkbox:checked').length;

            if (!$enabled_processors) {
              $('#edit-order').hide();
              $('.tabledrag-toggle-weight-wrapper').hide();
            }

            $row.hide();
            if ($tab) {
              $tab.tabHide().updateSummary();
            }
          }
          // Re-stripe the table after toggling visibility of table row.
          Drupal.tableDrag['edit-order'].restripeTable();
        });

        // Attach summary for configurable items (only for screen-readers).
        if ($tab) {
          $tab.details.drupalSetSummary(function () {
            return $checkbox.is(':checked') ? Drupal.t('Enabled') : Drupal.t('Disabled');
          });
        }

        // Trigger our bound click handler to update elements to initial state.
        $checkbox.triggerHandler('click.searchApiUpdate');
      });
    }
  };

})(jQuery);
