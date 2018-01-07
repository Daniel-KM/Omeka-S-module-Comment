$(document).ready(function() {

/* Update comments. */

// Toggle the status of a comment.
$('#content').on('click', 'a.toggle-property', function(e) {
    e.preventDefault();

    var button = $(this);
    var url = button.data('toggle-url');
    var status = button.data('status');
    $.ajax({
        url: url,
        beforeSend: function() {
            button.removeClass('o-icon-' + status).addClass('o-icon-transmit');
        }
    })
    .done(function(data) {
        if (!data.content) {
            alert(Omeka.jsTranslate('Something went wrong'));
            data = {
                'content': {
                    'status': status,
                    'is_public': null
                }
            }
        }
        status = data.content.status;
        button.data('status', status);
        var row = button.closest('.comment')
        var iconLink = row.find('.toggle-property.' + status);
        iconLink.data('status', status);
        var isPublicOrNot = row.find('.is-public-or-not');
        if (data.content.is_public) {
            isPublicOrNot.hide();
            isPublicOrNot.removeClass('o-icon-private');
        } else if (data.content.is_public === false) {
            isPublicOrNot.show();
            isPublicOrNot.addClass('o-icon-private');
        }
    })
    .fail(function(jqXHR, textStatus) {
        if (jqXHR.status == 404) {
            alert(Omeka.jsTranslate('The resource or the comment doesn’t exist.'));
        } else {
            alert(Omeka.jsTranslate('Something went wrong'));
        }
    })
    .always(function () {
        button.removeClass('o-icon-transmit').addClass('o-icon-' + status);
    });
});

// Approve or reject a list of comments.
$('#content').on('click', 'a.batch-property', function(e) {
    e.preventDefault();

    var selected = $('.batch-edit td input[name="resource_ids[]"][type="checkbox"]:checked');
    if (selected.length == 0) {
        return;
    }
    var checked = selected.map(function() { return $(this).val(); }).get();
    var button = $(this);
    var url = button.data('batch-property-url');
    var status = button.data('status');
    $.post({
        url: url,
        data: {resource_ids: checked},
        beforeSend: function() {
            selected.closest('.comment').find('.toggle-property.' + status).each(function() {
                $(this).removeClass('o-icon-' + $(this).data('status')).addClass('o-icon-transmit');
            });
            $('.select-all').prop('checked', false);
        }
    })
    .done(function(data) {
        if (!data.content) {
            alert(Omeka.jsTranslate('Something went wrong'));
            data = {
                'content': {
                    'status': status,
                    'is_public': null
                }
            }
        }
        status = data.content.status;
        selected.closest('.comment').each(function() {
            var row = $(this);
            row.find('input[type="checkbox"]').prop('checked', false);
            var iconLink = row.find('.toggle-property.' + status);
            iconLink.data('status', status);
            iconLink.removeClass('o-icon-transmit').addClass('o-icon-' + status);
            var isPublicOrNot = row.find('.is-public-or-not');
            if (data.content.is_public) {
                isPublicOrNot.hide();
                isPublicOrNot.removeClass('o-icon-private');
            } else if (data.content.is_public === false) {
                isPublicOrNot.show();
                isPublicOrNot.addClass('o-icon-private');
            }
        });
    })
    .fail(function(jqXHR, textStatus) {
        selected.closest('.comment').find('.toggle-property.' + status).each(function() {
            $(this).removeClass('o-icon-transmit').addClass('o-icon-' + $(this).data('status'));
        });
        if (jqXHR.status == 404) {
            alert(Omeka.jsTranslate('The resource or the comment doesn’t exist.'));
        } else {
            alert(Omeka.jsTranslate('Something went wrong'));
        }
    });
});

});
