<?php declare(strict_types=1);

namespace Comment\Api\Adapter;

use Comment\Api\Representation\CommentSubscriptionRepresentation;
use Comment\Entity\CommentSubscription;
use Common\Api\Adapter\CommonAdapterTrait;
use DateTime;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Resource;
use Omeka\Stdlib\ErrorStore;

class CommentSubscriptionAdapter extends AbstractEntityAdapter
{
    use CommonAdapterTrait;

    protected $sortFields = [
        'id' => 'id',
        'owner_id' => 'owner',
        'resource_id' => 'resource',
        'item_set_id' => 'resource',
        'item_id' => 'resource',
        'media_id' => 'resource',
        'created' => 'created',
        // For info.
        // // 'resource_title' => 'resource',
    ];

    protected $scalarFields = [
        'id' => 'id',
        'owner' => 'owner',
        'resource' => 'resource',
        'item_set' => 'resource',
        'item' => 'resource',
        'media' => 'resource',
        'created' => 'created',
    ];

    /**
     * @var array
     */
    protected $queryFields = [
        'id' => [
            'resource_id' => 'resource',
            'item_set_id' => 'resource',
            'item_id' => 'resource',
            'media_id' => 'resource',
            'owner_id' => 'owner',
        ],
        'datetime' => [
            'created_before' => ['<', 'created'],
            'created_after' => ['>', 'created'],
        ],
    ];

    public function getResourceName()
    {
        return 'comment_subscriptions';
    }

    public function getRepresentationClass()
    {
        return CommentSubscriptionRepresentation::class;
    }

    public function getEntityClass()
    {
        return CommentSubscription::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        // Handle all fields (id, datetime) via CommonAdapterTrait.
        $this->buildQueryFields($qb, $query);
    }

    public function sortQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['sort_by']) && $query['sort_by'] === 'resource_title') {
            $resourceAlias = $qb->createAlias();
            $qb->innerJoin('omeka_root.resource', $resourceAlias);
            $qb->addOrderBy("$resourceAlias.title", $query['sort_order'] ?? 'asc');
            return;
        }
        parent::sortQuery($qb, $query);
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \Comment\Entity\CommentSubscription $entity */

        // Only creation is possible.
        if ($request->getOperation() === Request::CREATE) {
            $data = $request->getContent();

            $this->hydrateOwner($request, $entity);

            if (isset($data['o:resource'])) {
                if (is_object($data['o:resource'])) {
                    $resource = $data['o:resource'] instanceof Resource
                        ? $data['o:resource']
                        : null;
                } elseif (is_array($data['o:resource'])) {
                    $resource = $this->getAdapter('resources')
                        ->findEntity(['id' => $data['o:resource']['o:id']]);
                } else {
                    $resource = null;
                }
                $entity->setResource($resource);
            }

            $entity->setCreated(new DateTime('now'));
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        /** @var \Comment\Entity\CommentSubscription $entity */

        // Check if the subscription is already set.
        $user = $entity->getOwner();
        $resource = $entity->getResource();
        if (!$this->isUnique($entity, ['owner' => $user, 'resource' => $resource])) {
            $errorStore->addError('resource', 'This user has already subscribed to this resource.'); // @translate
        }
    }
}
