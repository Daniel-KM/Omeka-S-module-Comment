<?php declare(strict_types=1);
namespace Comment\Controller\Site;

use Comment\Controller\AbstractCommentController;
use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use Omeka\Api\Exception\NotFoundException;

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
     * @return \Laminas\View\Model\JsonModel
     */
    protected function flagUnflag($flagUnflag)
    {
        $data = $this->params()->fromPost();
        $commentId = $data['id'] ?? null;
        if (!$commentId) {
            $this->logger()->warn('The comment id cannot be identified.'); // @translate
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Comment not found.' // @translate
            ))->setTranslator($this->translator()));
        }

        // Just check if the comment exists.
        $api = $this->api();
        try {
            $api
                ->read('comments', $commentId, [], ['responseContent' => 'resource'])
                ->getContent();
        } catch (NotFoundException $e) {
            $this->logger()->warn(
                'The comment #{comment_id} cannot be identified.', // @translate
                ['comment_id' => $commentId]
            );
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Comment not found.' // @translate
            ))->setTranslator($this->translator()));
        } catch (\Exception $e) {
            $this->logger()->warn(
                'The comment #{comment_id} cannot be accessed.', // @translate
                ['comment_id' => $commentId]
            );
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()));
        }

        $api
            ->update('comments', $commentId, ['o:flagged' => $flagUnflag], [], ['isPartial' => true]);

        return $this->jSend(JSend::SUCCESS, [
            'o:id' => $commentId,
            'o:flagged' => $flagUnflag,
            'status' => $flagUnflag ? 'flagged' : 'unflagged',
        ]);
    }
}
