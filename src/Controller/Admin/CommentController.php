<?php declare(strict_types=1);

namespace Comment\Controller\Admin;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Controller\AbstractCommentController;
use Comment\Form\QuickSearchForm;
use Comment\Form\SendMessageForm;
use Common\Stdlib\PsrMessage;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Form\ConfirmForm;

class CommentController extends AbstractCommentController
{
    public function browseAction()
    {
        $this->browse()->setDefaults('comments');

        $query = $this->params()->fromQuery();
        $response = $this->api()->search('comments', $query);
        $this->paginator($response->getTotalResults());

        /** @var \Omeka\Form\ConfirmForm $formDeleteSelected */
        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected
            ->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true))
            ->setButtonLabel('Confirm Delete'); // @translate

        /** @var \Omeka\Form\ConfirmForm $formDeleteAll */
        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll
            ->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true))
            ->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll
            ->get('submit')
            ->setAttribute('disabled', true);

        $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'browse'], true));
        $formSearch->setData($query);

        $comments = $response->getContent();

        $settings = $this->settings();
        $formSendMessage = $this->getForm(SendMessageForm::class);
        $formSendMessage->get('subject')->setValue((string) $settings->get('comment_reply_subject'));
        $formSendMessage->get('body')->setValue((string) $settings->get('comment_reply_body'));
        // When a support reply-to is set, the answering admin is no longer the
        // reply-to, so default to a discreet copy (bcc); else the admin is the
        // reply-to and needs no copy.
        $formSendMessage->get('myself')->setValue($settings->get('comment_reply_to_email') ? 'bcc' : '');

        return new ViewModel([
            'comments' => $comments,
            'formDeleteSelected' => $formDeleteSelected,
            'formDeleteAll' => $formDeleteAll,
            'formSearch' => $formSearch,
            'formSendMessage' => $formSendMessage,
        ]);
    }

    public function sendMessageAction()
    {
        if (!$this->getRequest()->isPost()) {
            throw new \Omeka\Mvc\Exception\NotFoundException;
        }

        $id = $this->params('id');
        /** @var \Comment\Api\Representation\CommentRepresentation $comment */
        $comment = $this->api()->read('comments', $id)->getContent();

        $owner = $comment->owner();
        $toEmail = $comment->email() ?: ($owner ? $owner->email() : null);
        if (!$toEmail) {
            return $this->jSend()->fail(null, $this->translate(
                'No email defined for this comment.' // @translate
            ));
        }

        $params = $this->params();

        $body = trim((string) $params->fromPost('body'));
        if (!strlen($body)) {
            return $this->jSend()->fail(null, $this->translate('Empty message.')); // @translate
        }
        if (mb_strlen($body) > 10000) {
            return $this->jSend()->fail(null, $this->translate('Too long message.')); // @translate
        }

        $settings = $this->settings();

        $subject = trim((string) $params->fromPost('subject'));
        if (!strlen($subject)) {
            $subject = $settings->get('comment_reply_subject')
                ?: $this->translate('Reply to your comment'); // @translate
        }

        $subject = $this->fillMessage($subject, $comment);
        $body = $this->fillMessage($body, $comment);

        if (mb_strlen($subject) > 190) {
            return $this->jSend()->fail(null, $this->translate('Too long subject.')); // @translate
        }

        $to = [$toEmail => $comment->name() ?: ($owner ? $owner->name() : '')];
        $replyTo = $this->replyToAddress();

        // The from stays the unique installation sender; copies to the
        // answering admin are optional, exclusive (cc or bcc), via the form
        // radio.
        $cc = null;
        $bcc = null;
        $myself = $params->fromPost('myself');
        $user = $this->identity();
        if ($user && $myself === 'cc') {
            $cc = [$user->getEmail() => (string) $user->getName()];
        } elseif ($user && $myself === 'bcc') {
            $bcc = [$user->getEmail() => (string) $user->getName()];
        }

        /** @see \Common\Mvc\Controller\Plugin\SendEmail */
        $result = $this->sendEmail($body, $subject, $to, null, $cc, $bcc, $replyTo);
        if (!$result) {
            return $this->jSend()->error(null, $this->translate(
                'Sorry, the message cannot be sent. Contact the administrator.' // @translate
            ));
        }

        $message = new PsrMessage(
            'Message successfully sent to {email}.', // @translate
            ['email' => $toEmail]
        );
        return $this->jSend()->success([
            'comment' => $id,
        ], $message->setTranslator($this->translator()));
    }

    /**
     * Resolve the reply-to address: the configured support address, else the
     * connected admin. The sender (from) is the unique installation address.
     */
    protected function replyToAddress(): ?array
    {
        $email = $this->settings()->get('comment_reply_to_email');
        if ($email) {
            return [$email => ''];
        }
        $user = $this->identity();
        if ($user) {
            return [$user->getEmail() => (string) $user->getName()];
        }
        return null;
    }

    /**
     * Fill a message with placeholders (moustache style).
     */
    protected function fillMessage(string $message, CommentRepresentation $comment): string
    {
        if (!strlen($message) || mb_strpos($message, '{') === false) {
            return $message;
        }
        $settings = $this->settings();
        $resource = $comment->resource();
        $placeholders = [
            '{main_title}' => $settings->get('installation_title', 'Omeka S'),
            '{main_url}' => $this->url()->fromRoute('top', [], ['force_canonical' => true]),
            '{name}' => (string) $comment->name(),
            '{email}' => (string) $comment->email(),
            '{comment}' => (string) $comment->body(),
            '{resource_title}' => $resource ? (string) $resource->displayTitle() : '',
            '{resource_url}' => $resource ? $resource->siteUrl(null, true) : '',
        ];
        return strtr($message, $placeholders);
    }

    public function showDetailsAction()
    {
        $response = $this->api()->read('comments', $this->params('id'));
        $comment = $response->getContent();

        $view = new ViewModel([
            'resource' => $comment,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function deleteConfirmAction()
    {
        $response = $this->api()->read('comments', $this->params('id'));
        $comment = $response->getContent();

        /** @var \Omeka\Form\ConfirmForm $form */
        $form = $this->getForm(ConfirmForm::class);
        $form->setAttribute('action', $comment->url('delete'));
        $form->setAttribute('id', 'confirm-delete-comment');
        $form->setButtonLabel('Confirm delete'); // @translate

        $view = new ViewModel([
            'resource' => $comment,
            'form' => $form,
            'isDeleted' => $comment->isDeleted(),
            'partialPath' => 'comment/admin/comment/show-details',
        ]);
        return $view
            ->setTemplate('comment/admin/comment/delete-confirm')
            ->setTerminal(true);
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            /** @var \Omeka\Form\ConfirmForm $form */
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $id = $this->params('id');
                $hardDelete = (bool) $this->params()->fromPost('hard_delete', false);
                if ($hardDelete) {
                    $response = $this->api($form)->delete('comments', $id);
                    if ($response) {
                        $this->messenger()->addSuccess('Comment permanently deleted.'); // @translate
                    }
                } else {
                    $response = $this->api($form)->update('comments', $id, ['o:deleted' => true], [], ['isPartial' => true]);
                    if ($response) {
                        $this->messenger()->addSuccess('Comment soft-deleted.'); // @translate
                    }
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute('admin/comment', ['action' => 'browse'], true);
    }

    public function batchDeleteConfirmAction()
    {
        /** @var \Omeka\Form\ConfirmForm $form */
        $form = $this->getForm(ConfirmForm::class);
        $routeAction = $this->params()->fromQuery('all') ? 'batch-delete-all' : 'batch-delete';
        $form
            ->setAttribute('action', $this->url()->fromRoute(null, ['action' => $routeAction], true))
            ->setAttribute('class', $routeAction)
            ->setButtonLabel('Confirm delete'); // @translate

        $view = new ViewModel([
            'form' => $form,
        ]);
        return $view
            ->setTerminal(true);
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one comment to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        /** @var \Omeka\Form\ConfirmForm $form */
        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('comments', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Comments successfully deleted.'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction(): void
    {
        $this->messenger()->addError('Delete of all comments is not supported currently.'); // @translate
    }

    public function batchApproveAction()
    {
        return $this->batchUpdateProperty(['o:approved' => true]);
    }

    public function batchUnapproveAction()
    {
        return $this->batchUpdateProperty(['o:approved' => false]);
    }

    public function batchFlagAction()
    {
        return $this->batchUpdateProperty(['o:flagged' => true]);
    }

    public function batchUnflagAction()
    {
        return $this->batchUpdateProperty(['o:flagged' => false]);
    }

    public function batchSetSpamAction()
    {
        return $this->batchUpdateProperty(['o:spam' => true]);
    }

    public function batchSetNotSpamAction()
    {
        return $this->batchUpdateProperty(['o:spam' => false]);
    }

    protected function batchUpdateProperty(array $data)
    {
        if (!$this->getRequest()->isPost()
            && !$this->getRequest()->isXmlHttpRequest()
        ) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', [])
            ?: $this->params()->fromQuery('resource_ids', []);

        // Secure the input.
        $resourceIds = array_values(array_unique(array_filter(array_map('intval', $resourceIds))));
        if (empty($resourceIds)) {
            return $this->jSend()->fail(null, new PsrMessage(
                'No comments submitted.' // @translate
            ));
        }

        try {
            $this->api()
                ->batchUpdate('comments', $resourceIds, $data, ['continueOnError' => true]);
        } catch (\Throwable $e) {
            $this->logger()->err(
                '[Comment]: {msg}', // @translate
                ['msg' => $e->getMessage()]
            );
            return $this->jSend()->error(null, new PsrMessage(
                'An internal error occurred.' // @translate
            ));
        }

        $value = reset($data);
        $property = key($data);

        $statuses = [
            'o:approved' => ['unapproved', 'approved'],
            'o:flagged' => ['unflagged', 'flagged'],
            'o:spam' => ['not-spam', 'spam'],
        ];

        // TODO According to jSend, output the list of comments and the propety for each? Probably useless.
        return $this->jSend()->success([
            'ids' => $resourceIds,
            'property' => $property,
            'value' => $value,
            'status' => $statuses[$property][(int) $value],
            'is_public' => $property === 'o:approved' ? $value : null,
            $property => $value,
        ]);
    }

    public function toggleApprovedAction()
    {
        return $this->toggleProperty('o:approved');
    }

    public function toggleFlaggedAction()
    {
        return $this->toggleProperty('o:flagged');
    }

    public function toggleSpamAction()
    {
        return $this->toggleProperty('o:spam');
    }

    protected function toggleProperty($property)
    {
        $id = $this->params('id');
        $comment = $this->api()->read('comments', $id)->getContent();

        switch ($property) {
            case 'o:approved':
                $value = !$comment->isApproved();
                break;
            case 'o:flagged':
                $value = !$comment->isFlagged();
                break;
            case 'o:spam':
                $value = !$comment->isSpam();
                break;
        }

        $data = [];
        $data[$property] = $value;
        try {
            $this->api()
                ->update('comments', ['id' => $id], $data, [], ['isPartial' => true]);
        } catch (\Throwable $e) {
            $this->logger()->err(
                '[Comment]: {msg}', // @translate
                ['msg' => $e->getMessage()]
            );
            return $this->jSend()->error(null, $this->translate(
                'An internal error occurred.' // @translate
            ));
        }

        $statuses = [
            'o:approved' => ['unapproved', 'approved'],
            'o:flagged' => ['unflagged', 'flagged'],
            'o:spam' => ['not-spam', 'spam'],
        ];

        return $this->jSend()->success([
            'property' => $property,
            'value' => $value,
            'status' => $statuses[$property][(int) $value],
            'is_public' => $property === 'o:approved' ? $value : null,
            $property => $value,
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * No spam for admin board.
     */
    protected function checkSpam(array $data)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     *
     * No spam, so no notification for admin board.
     */
    protected function notifyEmail(AbstractResourceEntityRepresentation $resource, CommentRepresentation $comment): void
    {
        // Nothing to do.
    }
}
