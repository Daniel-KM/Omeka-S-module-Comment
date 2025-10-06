'use strict';

/**
 * Requires common-dialog.js.
 */

(function() {
    $(document).ready(function() {

        /**
         * Use common-dialog.js.
         *
         * @see Comment, ContactUs, Contribute, Generate, Guest, Resa, SearchHistory, Selection, TwoFactorAuth.
         */

        /**
         * Toggle a status of a comment property.
         *
         * Status can be various values for different comment properties:
         * approved/unapproved, flagged/unflagged, spam/not-spam.
         * It can be fail or error too.
         *
         * @todo Finalize to use jsend with buttons.
         */
        $('#content').on('click', '.toggle-property', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const button = $(this);
            const url = button.attr('data-action') ? button.attr('data-action') : button.attr('data-url');
            var status = button.data('status');
            // CommonDialog.jSend(e);
            $.ajax({
                url: url,
                beforeSend: function() {
                    button.removeClass('o-icon-' + status);
                    CommonDialog.spinnerEnable(button[0]);
                },
            })
            .done(function(data) {
                if (!data.data || !Object.keys(data.data).length) {
                    const msg = CommonDialog.jSendMessage(data) || Omeka.jsTranslate('An error occurred.');
                    CommonDialog.dialogAlert({ message: msg, nl2br: true });
                    document.dispatchEvent(new CustomEvent('o:jsend-fail', { detail: data }));
                    data = {
                        data: {
                            // For css.
                            status: 'fail',
                            is_public: null,
                        }
                    };
                }
                status = data.data.status;
                button.data('status', status);
                var row = button.closest('.comment')
                var iconLink = row.find('.toggle-property.' + status);
                iconLink.data('status', status);
                var isPublicOrNot = row.find('.is-public-or-not');
                if (data.data.is_public) {
                    isPublicOrNot.hide();
                    isPublicOrNot.removeClass('o-icon-private');
                } else if (data.data.is_public === false) {
                    isPublicOrNot.show();
                    isPublicOrNot.addClass('o-icon-private');
                }
                $(document).trigger('o:comment-updated', data);
            })
            .fail(CommonDialog.jSendFail)
            .always(function () {
                CommonDialog.spinnerDisable(button[0]);
                button.addClass('o-icon-' + status);
            });
        });

        /**
         * Approve or reject a list of comments in batch.
         */
        $('#content').on('click', '.batch-property', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var selected = $('.batch-edit td input[name="resource_ids[]"][type="checkbox"]:checked');
            if (!selected.length) {
                return;
            }
            var checked = selected.map(function() { return $(this).val(); }).get();
            var button = $(this);
            var url = button.data('batch-property-url');
            var status = button.data('status');
            $.ajax({
                url: url,
                data: { resource_ids: checked },
                beforeSend: function() {
                    selected.closest('.comment').find('.toggle-property.' + status).each(function() {
                        $(this)
                            .removeClass('o-icon-' + $(this).data('status'))
                            .prop('disabled', true);
                        CommonDialog.spinnerEnable($(this)[0]);
                    });
                    CommonDialog.spinnerEnable(button[0]);
                    $('.select-all').prop('checked', false);
                },
            })
            .done(function(data) {
                if (!data.data || !Object.keys(data.data).length) {
                    const msg = CommonDialog.jSendMessage(data) || Omeka.jsTranslate('An error occurred.');
                    CommonDialog.dialogAlert({ message: msg, nl2br: true });
                    document.dispatchEvent(new CustomEvent('o:jsend-fail', { detail: data }));
                    data = {
                        data: {
                            // For css.
                            status: 'fail',
                            is_public: null,
                        }
                    };
                }
                status = data.data.status;
                selected.closest('.comment').each(function() {
                    var row = $(this);
                    row.find('input[type="checkbox"]').prop('checked', false);
                    var iconLink = row.find('.toggle-property.' + status);
                    CommonDialog.spinnerDisable(iconLink[0]);
                    iconLink
                        .data('status', status)
                        .addClass('o-icon-' + status)
                        .prop('disabled', false);
                    var isPublicOrNot = row.find('.is-public-or-not');
                    if (data.data.is_public) {
                        isPublicOrNot.hide();
                        isPublicOrNot.removeClass('o-icon-private');
                    } else if (data.data.is_public === false) {
                        isPublicOrNot.show();
                        isPublicOrNot.addClass('o-icon-private');
                    }
                });
                $(document).trigger('o:comment-updated', data);
            })
            .fail(function(jqXHR, textStatus) {
                selected.closest('.comment').find('.toggle-property.' + status).each(function() {
                    $(this)
                        .addClass('o-icon-' + $(this).data('status'))
                        .prop('disabled', false);
                    CommonDialog.spinnerDisable($(this)[0]);
                });
                CommonDialog.jSendFail(jqXHR);
            })
            .always(function () {
                CommonDialog.spinnerDisable(button[0]);
            });
        });

    });
})();
