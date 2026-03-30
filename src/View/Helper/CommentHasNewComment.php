<?php declare(strict_types=1);

namespace Comment\View\Helper;

use Comment\Service\CommentCache;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class CommentHasNewComment extends AbstractHelper
{
    /**
     * Check if a resource has new approved comments since last viewed.
     */
    public function __invoke(?AbstractResourceEntityRepresentation $resource): bool
    {
        if (!$resource) {
            return false;
        }

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
        $user = $plugins->get('identity')();
        if (!$user) {
            return false;
        }

        $userId = $user->getId();
        $resourceId = $resource->id();

        if (CommentCache::hasNewCommentCached($userId, $resourceId)) {
            return CommentCache::getNewComment($userId, $resourceId);
        }

        $services = $resource->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');

        $dql = <<<'DQL'
            SELECT cs.lastViewed, MAX(c.created) AS lastComment
            FROM Comment\Entity\CommentSubscription cs
            LEFT JOIN Comment\Entity\Comment c
                WITH c.resource = cs.resource AND c.approved = true
            WHERE cs.owner = :userId AND cs.resource = :resourceId
            GROUP BY cs.id
            DQL;

        $result = $entityManager->createQuery($dql)
            ->setParameters([
                'userId' => $userId,
                'resourceId' => $resourceId,
            ])
            ->getOneOrNullResult();

        if (!$result) {
            CommentCache::setNewComment($userId, $resourceId, false);
            return false;
        }

        $lastComment = $result['lastComment'] ?? null;
        $lastViewed = $result['lastViewed'] ?? null;

        $hasNew = $lastComment !== null
            && ($lastViewed === null || $lastComment > $lastViewed);

        CommentCache::setNewComment($userId, $resourceId, $hasNew);
        return $hasNew;
    }
}
