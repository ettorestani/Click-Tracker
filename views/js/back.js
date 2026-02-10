/**
 * Click Tracker Module - Backend JavaScript
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   AFL-3.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize select all checkbox
        initSelectAll();

        // Initialize tooltips
        initTooltips();

        // Initialize confirmations
        initConfirmations();
    });

    /**
     * Initialize select all checkbox functionality
     */
    function initSelectAll() {
        var $selectAll = $('#selectAll');
        var $checkboxes = $('.logCheckbox');

        if ($selectAll.length === 0) {
            return;
        }

        $selectAll.on('change', function() {
            var isChecked = $(this).prop('checked');
            $checkboxes.prop('checked', isChecked);
        });

        $checkboxes.on('change', function() {
            var allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
            $selectAll.prop('checked', allChecked);
        });
    }

    /**
     * Initialize Bootstrap tooltips
     */
    function initTooltips() {
        $('[data-toggle="tooltip"]').tooltip();
        $('[title]').tooltip();
    }

    /**
     * Initialize confirmation dialogs
     */
    function initConfirmations() {
        // Bulk delete confirmation
        $('button[name="submitBulkDelete"]').on('click', function(e) {
            var $checked = $('.logCheckbox:checked');

            if ($checked.length === 0) {
                e.preventDefault();
                var msg = (typeof et_clickTrackerTranslations !== 'undefined' && et_clickTrackerTranslations.noItemsSelected)
                    ? et_clickTrackerTranslations.noItemsSelected
                    : 'Please select at least one item.';
                alert(msg);
                return false;
            }

            var confirmMsg = (typeof et_clickTrackerTranslations !== 'undefined' && et_clickTrackerTranslations.confirmBulkDelete)
                ? et_clickTrackerTranslations.confirmBulkDelete
                : 'Are you sure you want to delete the selected items?';

            if (!confirm(confirmMsg)) {
                e.preventDefault();
                return false;
            }
        });

        // Single delete confirmation (for POST forms)
        $(document).on('submit', '.et-clicktracker-delete-form', function(e) {
            var confirmMsg = (typeof et_clickTrackerTranslations !== 'undefined' && et_clickTrackerTranslations.confirmDelete)
                ? et_clickTrackerTranslations.confirmDelete
                : 'Are you sure you want to delete this item?';

            if (!confirm(confirmMsg)) {
                e.preventDefault();
                return false;
            }
        });
    }

})(jQuery);
