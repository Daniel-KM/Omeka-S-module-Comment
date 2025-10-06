var Comment = {
    validate: function(form) {
        var button = form.find('button');
        var error = false;
        var field;

        var isAnonymous = $('#comment-main-container').data('is-anonymous');

        if (isAnonymous) {
            field = form.find('[name="o:email"]');
            var email = $.trim(field.val().replace(/\s+/g, ' '));
            if (Comment.validateEmail(email)) {
                field.css('color', 'auto');
            } else {
                field.css('color', 'red');
                error = true;
            }
        }

        field = form.find('[name="o:body"]');
        var text = $.trim(field.val().replace(/\s+/g, ' '));
        if (text.length == 0) {
            field.val('');
            error = true;
        }

        if (error) {
            return;
        }

        if (isAnonymous) {
            var isOmeka = typeof Omeka !== 'undefined' && typeof Omeka.jsTranslate !== 'undefined';
            var legalAgreement = form.find('[name="legal_agreement"]');
            if (legalAgreement.length > 0 && !legalAgreement.prop('checked')) {
                var msg = 'You should accept the legal agreement.';
                alert(isOmeka ? Omeka.jsTranslate(msg): msg)
                return;
            }
        }

        var url = form.attr('action');
        $.post({
            url: url,
            data: form.serialize(),
            timeout: 30000,
            beforeSend: function() {
                button.removeClass('fa-comment').addClass('o-icon-transmit');
            }
        })
        .done(function(data) {
            var msg;
            if (!data.content) {
                msg = 'Something went wrong';
                if (isOmeka) msg = Omeka.jsTranslate(msg);
                alert(msg);
            } else {
                if (data.content.moderation) {
                    msg = 'Comment was added to the resource.';
                    if (isOmeka) msg = Omeka.jsTranslate(msg);
                    var msgTmp = 'It will be displayed definively when approved.';
                    msg += ' ' + (isOmeka ? Omeka.jsTranslate(msgTmp): msgTmp);
                    alert(msg);
                }
                location.reload(true);
            }
        })
        .fail(function(jqXHR, textStatus) {
            if (textStatus == 'timeout') {
                var msg = 'Request too long to process.';
                alert(isOmeka ? Omeka.jsTranslate(msg): msg)
            } else if (jqXHR.status == 404) {
                var msg = 'The resource doesn’t exist.';
                alert(isOmeka ? Omeka.jsTranslate(msg): msg);
            } else {
                var hasMsg = jqXHR.hasOwnProperty('responseJSON') && typeof jqXHR.responseJSON.error !== 'undefined';
                var msg;
                if (hasMsg) {
                    msg = jqXHR.responseJSON.error;
                    var messages = jqXHR.responseJSON.messages
                    if (messages) {
                        for (var inputField in messages) {
                            msg += ' ' + messages[inputField][Object.keys(messages[inputField])[0]];
                            break;
                        }
                    }
                } else {
                    msg = isOmeka ? Omeka .jsTranslate('Something went wrong'): 'Something went wrong';
                }
                alert(msg);
            }
        })
        .always(function() {
            button.removeClass('o-icon-transmit').addClass('fa-comment');
        });
    },

    validateEmail: function(email) {
        var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(email);
    },

    handleReply: function(event) {
        Comment.moveForm(event);
    },

    finalizeMove: function() {
        $('#comment-form-body_parent').attr('style', '');
    },

    moveForm: function(event) {
        $('#comment-form').insertAfter(event.target);
        commentId = Comment.getCommentId(event.target);
        $('#comment_parent_id').val(commentId);
    },

    flag: function(event) {
        var commentId = Comment.getCommentId(event.target);
        var json = {
            'id': commentId
        };
        $.post(Comment.getBasePath() + '/flag', json, Comment.flagResponseHandler);
    },

    unflag: function(event) {
        var commentId = Comment.getCommentId(event.target);
        var json = {
            'id': commentId
        };
        $.post(Comment.getBasePath() + '/unflag', json, Comment.flagResponseHandler);
    },

    flagResponseHandler: function(response, status, jqxhr) {
        var comment = $('#comment-' + response.id);
        if (response.action == 'flagged') {
            comment.find('.comment-body').first().addClass('comment-flagged');
            comment.find('.comment-flag').first().hide();
            comment.find('.comment-unflag').first().show();
        }

        if (response.action == 'unflagged') {
            comment.find('.comment-body').first().removeClass('comment-flagged');
            comment.find('.comment-flag').first().show();
            comment.find('.comment-unflag').first().hide();
        }
    },

    getCommentId: function(el) {
        return $(el).parents('.comment').first().attr('id').substring(8);
    },

    getBasePath: function() {
        return $('#comments').data('comment-url');
    }
};

$(document).ready(function() {
    $('.comment-reply').click(Comment.handleReply);
    $('.comment-flag').click(Comment.flag);
    $('.comment-unflag').click(Comment.unflag);

    $('.comment-form button').on('click', function(e) {
        e.preventDefault();
        Comment.validate($(this).closest('form'));
    });

    $('.comment-form').submit(function(e) {
        e.preventDefault();
        Comment.validate($(this));
    });
});
