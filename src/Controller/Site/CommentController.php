<?php
namespace Comment\Controller\Site;

use Comment\Controller\AbstractCommentController;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Stdlib\Message;
use Zend\View\Model\JsonModel;

class CommentController extends AbstractCommentController
{
    public function flagAction()
    {
        return $this->flagUnflag(true);
    }

    public function unflagAction()
    {
        return $this->flagUnflag(false);
    }

    /**
     * Flag or unflag a resource.
     *
     * @param bool $flagUnflag
     * @return \Zend\View\Model\JsonModel
     */
    protected function flagUnflag($flagUnflag)
    {
        $data = $this->params()->fromPost();
        $commentId = @$data['id'];
        if (empty($commentId)) {
            $this->logger()->warn('The comment id cannot be identified.'); // @translate
            return new JsonModel(['error' => 'Comment not found.']); // @translate
        }

        // Just check if the comment exists.
        $api = $this->api();
        try {
            $api
                ->read('comments', $commentId, [], ['responseContent' => 'resource'])
                ->getContent();
        } catch (NotFoundException $e) {
            $this->logger()->warn(new Message('The comment #%s cannot be identified.', $commentId)); // @translate
            return new JsonModel(['error' => 'Comment not found.']); // @translate
        } catch (\Exception $e) {
            $this->logger()->warn(new Message('The comment #%s cannot be accessed.', $commentId)); // @translate
            return new JsonModel(['error' => 'Unauthorized access.']); // @translate
        }

        $api
            ->update('comments', $commentId, ['o-module-comment:flagged' => $flagUnflag], [], ['isPartial' => true]);

        if ($flagUnflag) {
            return new JsonModel(['status' => 'ok', 'id' => $commentId, 'action' => 'flagged']);
        } else {
            return new JsonModel(['status' => 'ok', 'id' => $commentId, 'action' => 'unflagged']);
        }
    }
}
