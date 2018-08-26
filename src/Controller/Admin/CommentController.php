<?php
namespace Comment\Controller\Admin;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Controller\AbstractCommentController;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Form\ConfirmForm;
use Zend\Http\Response;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class CommentController extends AbstractCommentController
{
    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('comments', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $view = new ViewModel;
        $comments = $response->getContent();
        $view->setVariable('comments', $comments);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        return $view;
    }

    public function showDetailsAction()
    {
        $response = $this->api()->read('comments', $this->params('id'));
        $comment = $response->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('resource', $comment);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $response = $this->api()->read('comments', $this->params('id'));
        $comment = $response->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('tagging', $comment);
        $view->setVariable('resource', $comment);
        $view->setVariable('resourceLabel', 'comment');
        $view->setVariable('partialPath', 'comment/admin/comment/show-details');
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
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
        return $this->redirect()->toRoute(
            'admin/comment',
            ['action' => 'browse'],
            true
        );
    }

    public function batchDeleteConfirmAction()
    {
        $form = $this->getForm(ConfirmForm::class);
        $routeAction = $this->params()->fromQuery('all') ? 'batch-delete-all' : 'batch-delete';
        $form->setAttribute('action', $this->url()->fromRoute(null, ['action' => $routeAction], true));
        $form->setButtonLabel('Confirm delete'); // @translate
        $form->setAttribute('id', 'batch-delete-confirm');
        $form->setAttribute('class', $routeAction);

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('form', $form);
        return $view;
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

    public function batchDeleteAllAction()
    {
        $this->messenger()->addError('Delete of all comments is not supported currently.'); // @translate
    }

    public function batchApproveAction()
    {
        return $this->batchUpdateProperty(['o-module-comment:approved' => true]);
    }

    public function batchUnapproveAction()
    {
        return $this->batchUpdateProperty(['o-module-comment:approved' => false]);
    }

    public function batchFlagAction()
    {
        return $this->batchUpdateProperty(['o-module-comment:flagged' => true]);
    }

    public function batchUnflagAction()
    {
        return $this->batchUpdateProperty(['o-module-comment:flagged' => false]);
    }

    public function batchSetSpamAction()
    {
        return $this->batchUpdateProperty(['o-module-comment:spam' => true]);
    }

    public function batchSetNotSpamAction()
    {
        return $this->batchUpdateProperty(['o-module-comment:spam' => false]);
    }

    protected function batchUpdateProperty(array $data)
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        // Secure the input.
        $resourceIds = array_filter(array_map('intval', $resourceIds));
        if (empty($resourceIds)) {
            return $this->jsonError('No comments submitted.', Response::STATUS_CODE_400); // @translate
        }

        $response = $this->api()
            ->batchUpdate('comments', $resourceIds, $data, ['continueOnError' => true]);
        if (!$response) {
            return $this->jsonError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        $value = reset($data);
        $property = key($data);

        $statuses = [
            'o-module-comment:approved' => ['unapproved', 'approved'],
            'o-module-comment:flagged' => ['unflagged', 'flagged'],
            'o-module-comment:spam' => ['not-spam', 'spam'],
        ];

        return new JsonModel([
            'content' => [
                'property' => $property,
                'value' => $value,
                'status' => $statuses[$property][(int) $value],
                'is_public' => $property === 'o-module-comment:approved' ? $value : null,
            ],
        ]);
    }

    public function toggleApprovedAction()
    {
        return $this->toggleProperty('o-module-comment:approved');
    }

    public function toggleFlaggedAction()
    {
        return $this->toggleProperty('o-module-comment:flagged');
    }

    public function toggleSpamAction()
    {
        return $this->toggleProperty('o-module-comment:spam');
    }

    protected function toggleProperty($property)
    {
        $id = $this->params('id');
        $comment = $this->api()->read('comments', $id)->getContent();

        switch ($property) {
            case 'o-module-comment:approved':
                $value = !$comment->isApproved();
                break;
            case 'o-module-comment:flagged':
                $value = !$comment->isFlagged();
                break;
            case 'o-module-comment:spam':
                $value = !$comment->isSpam();
                break;
        }

        $data = [];
        $data[$property] = $value;
        $response = $this->api()
            ->update('comments', $id, $data, [], ['isPartial' => true]);
        if (!$response) {
            return $this->jsonError('An internal error occurred.', Response::STATUS_CODE_500); // @translate
        }

        $statuses = [
            'o-module-comment:approved' => ['unapproved', 'approved'],
            'o-module-comment:flagged' => ['unflagged', 'flagged'],
            'o-module-comment:spam' => ['not-spam', 'spam'],
        ];

        return new JsonModel([
            'content' => [
                'property' => $property,
                'value' => $value,
                'status' => $statuses[$property][(int) $value],
                'is_public' => $property === 'o-module-comment:approved' ? $value : null,
            ],
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
    protected function notifyEmail(AbstractResourceEntityRepresentation $resource, CommentRepresentation $comment)
    {
        // Nothing to do.
    }
}
