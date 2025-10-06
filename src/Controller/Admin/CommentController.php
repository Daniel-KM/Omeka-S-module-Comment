<?php declare(strict_types=1);

namespace Comment\Controller\Admin;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Controller\AbstractCommentController;
use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use Laminas\View\Model\ViewModel;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Form\ConfirmForm;

class CommentController extends AbstractCommentController
{
    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('comments', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults());

        /** @var \Omeka\Form\ConfirmForm $formDeleteSelected */
        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected
            ->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true))
            ->setAttribute('id', 'confirm-delete-selected')
            ->setButtonLabel('Confirm Delete'); // @translate

        /** @var \Omeka\Form\ConfirmForm $formDeleteAll */
        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll
            ->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true))
            ->setAttribute('id', 'confirm-delete-all')
            ->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll
            ->get('submit')
            ->setAttribute('disabled', true);

        $comments = $response->getContent();

        return new ViewModel([
            'comments' => $comments,
            'formDeleteSelected' => $formDeleteSelected,
            'formDeleteAll' => $formDeleteAll,
        ]);
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

        $view = new ViewModel([
            'comment' => $comment,
            'resource' => $comment,
            'resourceLabel' => 'comment',
            'partialPath' => 'comment/admin/comment/show-details',
        ]);
        return $view
            ->setTemplate('common/delete-confirm-details')
            ->setTerminal(true);
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            /** @var \Omeka\Form\ConfirmForm $form */
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('comments', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Comment successfully deleted.'); // @translate
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
            ->setAttribute('id', 'batch-delete-confirm')
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
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'No comments submitted.' // @translate
            ))->setTranslator($this->translator()));
        }

        try {
            $this->api()
                ->batchUpdate('comments', $resourceIds, $data, ['continueOnError' => true]);
        } catch (\Exception $e) {
            $this->logger()->err(
                '[Comment]: {msg}', // @translate
                ['msg' => $e->getMessage()]
            );
            return $this->jSend(JSend::ERROR, null, (new PsrMessage(
                'An internal error occurred.' // @translate
            ))->setTranslator($this->translator()));
        }

        $value = reset($data);
        $property = key($data);

        $statuses = [
            'o:approved' => ['unapproved', 'approved'],
            'o:flagged' => ['unflagged', 'flagged'],
            'o:spam' => ['not-spam', 'spam'],
        ];

        // TODO According to jsend, output the list of comments and the propety for each? Probably useless.
        return $this->jSend(JSend::SUCCESS, [
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
        } catch (\Exception $e) {
            $this->logger()->err(
                '[Comment]: {msg}', // @translate
                ['msg' => $e->getMessage()]
            );
            return $this->jSend(JSend::ERROR, null, $this->translate(
                'An internal error occurred.' // @translate
            ));
        }

        $statuses = [
            'o:approved' => ['unapproved', 'approved'],
            'o:flagged' => ['unflagged', 'flagged'],
            'o:spam' => ['not-spam', 'spam'],
        ];

        return $this->jSend(JSend::SUCCESS, [
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
