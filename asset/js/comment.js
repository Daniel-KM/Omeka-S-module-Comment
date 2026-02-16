'use strict';

/**
 * Requires common-dialog.js.
 *
 * @see Comment, ContactUs, Contribute, Generate, Guest, Resa, SearchHistory, Selection, TwoFactorAuth.
 */

var Comment = {
    _submitting: false,

    validate: function(form) {
        var button = form.find('button');
        var error = false;
        var field;

        // Prevent double submission.
        if (Comment._submitting || button.prop('disabled')) {
            return;
        }

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
                CommonDialog.dialogAlert({
                    heading: Omeka.jsTranslate('Comment'),
                    message: Omeka.jsTranslate(msg),
                });
                return;
            }
        }

        // Disable immediately to prevent double submission.
        Comment._submitting = true;
        button.prop('disabled', true);

        // Use CommonDialog.jSend for AJAX submission.
        CommonDialog.jSend({
            preventDefault: function() {},
            target: form[0],
            submitter: button[0],
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
        const button = event.currentTarget;
        const commentId = Comment.getCommentId(button);
        $('#comment-form').insertAfter(button);
        $('#comment_parent_id').val(commentId);
    },

    edit: function(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const commentId = Comment.getCommentId(button);
        const commentBody = $(button).closest('.comment').find('.comment-body').text().trim();
        CommonDialog.dialogPrompt({
            heading: Omeka.jsTranslate('Comment'),
            message: Omeka.jsTranslate('Edit your comment'),
            textarea: true,
            defaultValue: commentBody,
            nl2br: false,
        }).then(function(updatedText) {
            if (updatedText === null || updatedText === commentBody) {
                return;
            }
            button.dataset.action = Comment.getBasePath() + '/' + commentId  +'/edit';
            button.dataset.payload = JSON.stringify({ 'o:body': updatedText });
            event.target = button;
            CommonDialog.jSend(event);
        });
    },

    delete: function(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const commentId = Comment.getCommentId(button);
        CommonDialog.dialogConfirm({
            heading: Omeka.jsTranslate('Comment'),
            message: Omeka.jsTranslate('Are you sure you want to delete this comment?'),
        }).then(function(confirmed) {
            if (!confirmed) {
                return;
            }
            button.dataset.action = Comment.getBasePath() + '/' + commentId + '/delete';
            button.dataset.payload = JSON.stringify({});
            event.target = button;
            CommonDialog.jSend(event);
        });
    },

    flag: function(event) {
        const button = event.currentTarget;
        const commentId = Comment.getCommentId(button);
        button.dataset.action = Comment.getBasePath() + '/flag';
        button.dataset.payload = JSON.stringify({ id: commentId });
        event.target = button;
        CommonDialog.jSend(event);
    },

    unflag: function(event) {
        const button = event.currentTarget;
        const commentId = Comment.getCommentId(button);
        button.dataset.action = Comment.getBasePath() + '/unflag';
        button.dataset.payload = JSON.stringify({ id: commentId });
        event.target = button;
        CommonDialog.jSend(event);
    },

    subscribeResource: function(event) {
        const button = event.currentTarget;
        const action = button.dataset.subscriptionAction ? button.dataset.subscriptionAction : 'toggle';
        const resourceId = button.dataset.id ? button.dataset.id : 0;
        button.dataset.action = Comment.getBasePath() + '/subscribe-resource';
        button.dataset.payload = JSON.stringify({ action: action, id: resourceId });
        event.target = button;
        CommonDialog.jSend(event);
    },

    flagResponseHandler: function(response, status, jqxhr) {
        if (!response || !response.data || !response.data.status || !response.data.data || !response.data.data.status) {
            return;
        }
        if (response.data.data.status === 'commented') {
            location.reload();
        } else if (response.data.data.status === 'deleted') {
            var commentId = response.data.data.comment['o:id'];
            // Remove from DOM: the comment div and its parent li if on browse page.
            var $comment = $('#comment-' + commentId);
            var $li = $comment.closest('li.resource');
            if ($li.length) {
                $li.remove();
            } else {
                $comment.remove();
            }
        } else if (response.data.data.status === 'flagged') {
            const comment = $('#comment-' + response.data.data.comment['o:id']);
            comment.find('.comment-body').first().addClass('comment-flagged');
            comment.find('.comment-flag').first().hide();
            comment.find('.comment-unflag').first().show();
        } else if (response.data.data.status == 'unflagged') {
            const comment = $('#comment-' + response.data.data.comment['o:id']);
            comment.find('.comment-body').first().removeClass('comment-flagged');
            comment.find('.comment-flag').first().show();
            comment.find('.comment-unflag').first().hide();
        } else if (response.data.data.status === 'subscribed') {
            const resourceId = response.data.data.comment_subscription['o:resource']['o:id'];
            $('.comment-subscribe[data-id="' + resourceId + '"]:not(.comment-login)').each(function() {
                $(this)
                    .removeClass('unsubscribed')
                    .addClass('subscribed');
                this.setAttribute('title', this.dataset.titleSubscribed);
                const textSpan = $(this).find('.comment-subscription-text');
                if (textSpan.length) {
                    textSpan.text(this.dataset.textSubscribed || this.dataset.titleSubscribed);
                }
            });
        } else if (response.data.data.status === 'unsubscribed') {
            const resourceId = response.data.data.comment_subscription['o:resource']['o:id'];
            $('.comment-subscribe[data-id="' + resourceId + '"]:not(.comment-login)').each(function() {
                  $(this)
                      .removeClass('subscribed')
                      .addClass('unsubscribed');
                  this.setAttribute('title', this.dataset.titleUnsubscribed);
                  const textSpan = $(this).find('.comment-subscription-text');
                  if (textSpan.length) {
                      textSpan.text(this.dataset.textUnsubscribed || this.dataset.titleUnsubscribed);
                  }
              });
              // Remove the resource from the list if on subscriptions page.
              $('.subscription-removable[data-id="' + resourceId + '"]').remove();
          }
    },

    getCommentId: function(el) {
        const id = $(el).parents('.comment').first().attr('data-id');
        return id ? id : $(el).parents('.comment').first().attr('id').substring(8);
    },

    getBasePath: function() {
        // Warning, the url may be any page and may be a clean url.
        // Default in public side is "/s/my-site/comment".
        return $('[data-comment-url]').data('comment-url');
    },

    /**
     * Initialize identity mode toggle for logged-in users.
     * Handles account, alias, and anonymous modes.
     */
    initIdentityMode: function() {
        var $identityMode = $('input[name="comment_identity_mode"]');
        if ($identityMode.length === 0) {
            return;
        }

        var $aliasFields = $('.comment-alias-field').closest('.field, .input');
        // If no .field wrapper, try parent elements.
        if ($aliasFields.length === 0) {
            $aliasFields = $('.comment-alias-field').parent();
        }

        // Function to toggle alias fields visibility.
        function toggleIdentityFields() {
            var mode = $('input[name="comment_identity_mode"]:checked').val()
                || $('input[name="comment_identity_mode"][type="hidden"]').val();
            if (mode === 'alias') {
                $aliasFields.show();
                // Make email required when using alias.
                $('#comment-alias-email').attr('required', true);
            } else {
                // Hide for both 'account' and 'anonymous' modes.
                $aliasFields.hide();
                $('#comment-alias-email').attr('required', false);
                // Clear values when switching away from alias mode.
                $('#comment-alias-name').val('');
                $('#comment-alias-email').val('');
            }
        }

        // Initial state.
        toggleIdentityFields();

        // Listen for changes.
        $identityMode.on('change', toggleIdentityFields);
    }
};

(function() {
    $(document).ready(function() {

        $('.comment-reply').click(Comment.handleReply);
        $('.comment-edit').click(Comment.edit);
        $('.comment-delete').click(Comment.delete);
        $('.comment-flag').click(Comment.flag);
        $('.comment-unflag').click(Comment.unflag);
        $('.comment-subscribe:not(.comment-login').click(Comment.subscribeResource);

        // Initialize identity mode toggle (account/alias/anonymous).
        Comment.initIdentityMode();

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
