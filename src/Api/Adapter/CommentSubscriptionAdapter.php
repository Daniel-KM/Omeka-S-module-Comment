<?php declare(strict_types=1);

namespace Comment\Api\Adapter;

use Comment\Api\Representation\CommentSubscriptionRepresentation;
use Comment\Entity\Comment;
use Comment\Entity\CommentSubscription;
use DateTime;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Resource;
use Omeka\Stdlib\ErrorStore;

class CommentSubscriptionAdapter extends AbstractEntityAdapter
{
    use QueryBuilderTrait;

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
        $expr = $qb->expr();

        // All comments with any entities ("OR"). If multiple, mixed with "AND".
        foreach ([
            'resource_id' => 'resource',
            'item_set_id' => 'resource',
            'item_id' => 'resource',
            'media_id' => 'resource',
            'owner_id' => 'owner',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query)) {
                $this->buildQueryIds($qb, $query[$queryKey], $column, 'id');
            }
        }

        /** @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery() */
        $dateSearches = [
            'created_before' => ['lt', 'created'],
            'created_after' => ['gt', 'created'],
        ];
        $dateGranularities = [
            DateTime::ISO8601,
            '!Y-m-d\TH:i:s',
            '!Y-m-d\TH:i',
            '!Y-m-d\TH',
            '!Y-m-d',
            '!Y-m',
            '!Y',
        ];
        foreach ($dateSearches as $dateSearchKey => $dateSearch) {
            if (isset($query[$dateSearchKey])) {
                foreach ($dateGranularities as $dateGranularity) {
                    $date = DateTime::createFromFormat($dateGranularity, $query[$dateSearchKey]);
                    if (false !== $date) {
                        break;
                    }
                }
                $qb->andWhere($expr->{$dateSearch[0]} (
                sprintf('omeka_root.%s', $dateSearch[1]),
                    // If the date is invalid, pass null to ensure no results.
                    $this->createNamedParameter($qb, $date ?: null)
                ));
            }
        }
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
