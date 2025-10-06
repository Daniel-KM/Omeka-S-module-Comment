'use strict';

/**
 * Requires common-dialog.js.
 *
 * @see Comment, ContactUs, Contribute, Generate, Guest, Resa, SearchHistory, Selection, TwoFactorAuth.
 */

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
            var legalAgreement = form.find('[name="legal_agreement"]');
            if (legalAgreement.length > 0 && !legalAgreement.prop('checked')) {
                var msg = 'You should accept the legal agreement.';
                CommonDialog.dialogAlert(Omeka.jsTranslate(msg));
                return;
            }
        }

        // Use CommonDialog.jSend for AJAX submission.
        CommonDialog.jSend({
            preventDefault: function() {},
            target: form[0],
            submitter: button[0]
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
        const commentId = Comment.getCommentId(event.target);
        $('#comment_parent_id').val(commentId);
    },

    flag: function(event) {
        var commentId = Comment.getCommentId(event.target);
        const button = event.target;
        button.dataset.action = Comment.getBasePath() + '/flag';
        button.dataset.payload = JSON.stringify({ id: commentId });
        CommonDialog.jSend(event);
    },

    unflag: function(event) {
        var commentId = Comment.getCommentId(event.target);
        const button = event.target;
        button.dataset.action = Comment.getBasePath() + '/unflag';
        button.dataset.payload = JSON.stringify({ id: commentId });
        CommonDialog.jSend(event);
    },

    flagResponseHandler: function(response, status, jqxhr) {
        if (!response || !response.status || !response.data) {
            return;
        }
        var comment = $('#comment-' + response.data['o:id']);
        if (response.data.status === 'flagged') {
            comment.find('.comment-body').first().addClass('comment-flagged');
            comment.find('.comment-flag').first().hide();
            comment.find('.comment-unflag').first().show();
        } else if (response.data.status == 'unflagged') {
            comment.find('.comment-body').first().removeClass('comment-flagged');
            comment.find('.comment-flag').first().show();
            comment.find('.comment-unflag').first().hide();
        }
    },

    getCommentId: function(el) {
        return $(el).parents('.comment').first().attr('id').substring(8);
    },

    getBasePath: function() {
        // Warning, the url may be any page and may be a clean url.
        // Default in public side is "/s/my-site/comment".
        return $('#comments').data('comment-url');
    }
};

(function() {
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

        document.addEventListener('o:jsend-success', function(event) {
            Comment.flagResponseHandler(event.detail);
        });

    });
})();
