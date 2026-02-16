<?php declare(strict_types=1);

namespace Comment\Api\Adapter;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Entity\Comment;
use Common\Api\Adapter\CommonAdapterTrait;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Laminas\Validator\EmailAddress;
use Laminas\Validator\Uri as UriValidator;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use Omeka\Entity\Resource;
use Omeka\Stdlib\ErrorStore;

class CommentAdapter extends AbstractEntityAdapter
{
    use CommonAdapterTrait;

    protected $sortFields = [
        'id' => 'id',
        'owner_id' => 'owner',
        'resource_id' => 'resource',
        'item_set_id' => 'resource',
        'item_id' => 'resource',
        'media_id' => 'resource',
        'site_id' => 'site',
        'approved' => 'approved',
        'flagged' => 'flagged',
        'spam' => 'spam',
        'path' => 'path',
        'email' => 'email',
        'website' => 'website',
        'name' => 'name',
        'ip' => 'ip',
        'user_agent' => 'user_agent',
        'parent_id' => 'parent',
        'created' => 'created',
        'modified' => 'modified',
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
        'site' => 'site',
        'approved' => 'approved',
        'flagged' => 'flagged',
        'spam' => 'spam',
        'path' => 'path',
        'email' => 'email',
        'website' => 'website',
        'name' => 'name',
        'ip' => 'ip',
        'user_agent' => 'user_agent',
        'body' => 'body',
        'parent' => 'parent',
        'children' => 'children',
        'created' => 'created',
        'modified' => 'modified',
    ];

    /**
     * @var array
     */
    protected $queryFields = [
        'id' => [
            'id' => 'id',
            'resource_id' => 'resource',
            'item_set_id' => 'resource',
            'item_id' => 'resource',
            'media_id' => 'resource',
            'owner_id' => 'owner',
            'site_id' => 'site',
        ],
        'string' => [
            'path' => 'path',
            'email' => 'email',
            'website' => 'website',
            'name' => 'name',
            'ip' => 'ip',
            'user_agent' => 'userAgent',
        ],
        'datetime' => [
            'created_before' => ['<', 'created'],
            'created_after' => ['>', 'created'],
            'modified_before' => ['<', 'modified'],
            'modified_after' => ['>', 'modified'],
        ],
    ];

    public function getResourceName()
    {
        return 'comments';
    }

    public function getRepresentationClass()
    {
        return CommentRepresentation::class;
    }

    public function getEntityClass()
    {
        return Comment::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        // Handle common fields (id, string, datetime) via CommonAdapterTrait.
        // TODO Check resource and owner visibility for public view.
        $this->buildQueryFields($qb, $query);

        // Manage arguments "group" and "collection_id" together.
        // It there are groups and collections, intersect them: "and" is used
        // between arguments.
        // Warning: the group "none" means to get all comments without group.
        if (array_key_exists('collection_id', $query) && !in_array($query['collection_id'], [null, '', []], true)) {
            $collectionIds = is_array($query['collection_id'])
                ? array_values(array_unique(array_map('intval', $query['collection_id'])))
                : [(int) $query['collection_id']];
        } else {
            $collectionIds = [];
        }

        if (array_key_exists('group', $query) && !in_array($query['group'], [null, '', []], true)) {
            $values = is_array($query['group'])
                ? array_values(array_unique(array_map('string', $query['group'])))
                : [(string) $query['group']];
            $groups = $this->getServiceLocator()->get('Omeka\Settings')->get('comment_groups');
            if (in_array('none', $values)) {
                // Exclude comments whose item belongs to any group item sets.
                // Use a NOT IN subquery instead of LEFT JOIN to avoid
                // false positives when items belong to both grouped and
                // non-grouped item sets (ManyToMany generates two joins
                // and the WITH condition only applies to the second one).
                $groupItemSets = array_unique(array_merge(...array_values($groups)));
                if ($groupItemSets) {
                    $itemSubAlias = $this->createAlias();
                    $itemSetSubAlias = $this->createAlias();
                    $paramAlias = $this->createAlias();
                    $subQb = $this->getEntityManager()->createQueryBuilder();
                    $subQb->select($itemSubAlias . '.id')
                        ->from(Item::class, $itemSubAlias)
                        ->join($itemSubAlias . '.itemSets', $itemSetSubAlias)
                        ->where($expr->in($itemSetSubAlias . '.id', ':' . $paramAlias));
                    $qb
                        ->andWhere($expr->notIn('omeka_root.resource', $subQb->getDQL()))
                        ->setParameter($paramAlias, $groupItemSets, Connection::PARAM_INT_ARRAY);
                }
            } else {
                $groupItemSets = array_intersect_key($groups, array_flip($values));
                $groupItemSets = $groupItemSets ? array_unique(array_merge(...array_values($groupItemSets))) : [];
                if (!$groupItemSets) {
                    $qb->andWhere($expr->isNull('omeka_root.id'));
                } else {
                    // Intersect with collection ids if any and continue below.
                    $collectionIds = $collectionIds
                        ? array_intersect($collectionIds, $groupItemSets)
                        : $groupItemSets;
                    if (!$collectionIds) {
                        $qb->andWhere($expr->isNull('omeka_root.id'));
                    }
                }
            }
        }

        // This is "or" when multiple collections are set.
        if ($collectionIds) {
            $values = array_values($collectionIds);
            $itemAlias = $this->createAlias();
            $itemSetAlias = $this->createAlias();

            // TODO Check resource for collection_id? Add a join on resource? Check rights and visibility?
            // This feature can be used with private collections in some cases?
            $qb
                // Normally, just join "ìtem_item_set", but id does not seems to
                // be possible with doctrine orm, so use a join with item and
                // filter it with item sets below.
                ->innerJoin(
                    // 'item_item_set',
                    Item::class,
                    $itemAlias,
                    'WITH',
                    $expr->eq('omeka_root.resource', $itemAlias . '.id')
                );

            if ($values === [0]) {
                // Only items with no item sets requested.
                $qb
                    ->andWhere($itemAlias . '.itemSets IS EMPTY');
            } elseif (count($values) === 1) {
                // Single collection id.
                $paramAlias = $this->createAlias();
                $qb
                    ->innerJoin($itemAlias . '.itemSets', $itemSetAlias)
                    ->andWhere($expr->eq($itemSetAlias . '.id', ':' . $paramAlias))
                    ->setParameter($paramAlias, reset($values), ParameterType::INTEGER);
            } elseif (in_array(0, $values, true)) {
                // Include items with no item sets plus specific sets: 0 mixed
                // with other ids.
                $wantedIds = array_values(array_filter($values, fn ($v) => $v !== 0));
                if ($wantedIds) {
                    $paramAlias = $this->createAlias();
                    $qb
                        // Left join to allow null (no item sets).
                        ->leftJoin($itemAlias . '.itemSets', $itemSetAlias)
                        ->andWhere($expr->orX(
                            $itemAlias . '.itemSets IS EMPTY',
                            $expr->in($itemSetAlias . '.id', ':' . $paramAlias)
                        ))
                        ->setParameter($paramAlias, $wantedIds, Connection::PARAM_INT_ARRAY);
                } else {
                    $qb->andWhere($itemAlias . '.itemSets IS EMPTY');
                }
            } else {
                // Multiple collection ids.
                $paramAlias = $this->createAlias();
                $qb->innerJoin($itemAlias . '.itemSets', $itemSetAlias);
                $qb
                    ->andWhere($expr->in($itemSetAlias . '.id', ':' . $paramAlias))
                    ->setParameter($paramAlias, $values, Connection::PARAM_INT_ARRAY);
            }
        }

        if (array_key_exists('has_resource', $query)) {
            // An empty string means true in order to manage get/post query.
            if (in_array($query['has_resource'], [false, 'false', 0, '0'], true)) {
                $qb
                    ->andWhere($expr->isNull('omeka_root.resource'));
            } else {
                $qb
                    ->andWhere($expr->isNotNull('omeka_root.resource'));
            }
        }

        if (array_key_exists('resource_type', $query)) {
            $mapResourceTypes = [
                // 'users' => User::class,
                // 'sites' => Site::class,
                'resources' => Resource::class,
                'item_sets' => ItemSet::class,
                'items' => Item::class,
                'media' => Media::class,
            ];
            if ($query['resource_type'] === 'resources') {
                $qb
                     ->andWhere($expr->isNotNull('omeka_root.resource'));
            } elseif (isset($mapResourceTypes[$query['resource_type']])) {
                $entityAlias = $this->createAlias();
                $qb
                    ->innerJoin(
                        $mapResourceTypes[$query['resource_type']],
                        $entityAlias,
                        'WITH',
                        $expr->eq(
                            'omeka_root.resource',
                            $entityAlias . '.id'
                        )
                    );
            } elseif ($query['resource_type'] !== '') {
                $qb
                    ->andWhere('1 = 0');
            }
        }

        // Boolean fields with special empty string handling (empty string = true).
        foreach ([
            'approved' => 'approved',
            'flagged' => 'flagged',
            'spam' => 'spam',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query)) {
                // An empty string means true in order to manage get/post query.
                if (in_array($query[$queryKey], [false, 'false', 0, '0'], true)) {
                    $qb
                        ->andWhere($expr->eq('omeka_root.' . $column, 0));
                } else {
                    $qb
                        ->andWhere($expr->eq('omeka_root.' . $column, 1));
                }
            }
        }
    }

    // public function sortQuery(QueryBuilder $qb, array $query)
    // {
    //     if (is_string($query['sort_by'])) {
    //         switch ($query['sort_by']) {
    //             default:
    //                 parent::sortQuery($qb, $query);
    //                 break;
    //         }
    //     }
    // }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        /** @var \Comment\Entity\Comment $entity */

        $data = $request->getContent();

        // The owner, site and resource can be null.
        switch ($request->getOperation()) {
            case Request::CREATE:
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

                if (isset($data['o:site'])) {
                    if (is_object($data['o:site'])) {
                        $site = $data['o:site'];
                    } elseif (is_array($data['o:site'])) {
                        $site = $this->getAdapter('sites')
                            ->findEntity(['id' => $data['o:site']['o:id']]);
                    } else {
                        $site = null;
                    }
                    $entity->setSite($site);
                }

                if (isset($data['o:parent'])) {
                    if (is_object($data['o:parent'])) {
                        $parent = $data['o:parent'];
                    } elseif (is_array($data['o:parent'])) {
                        $parent = $this
                            ->findEntity(['id' => $data['o:parent']['o:id']]);
                    } else {
                        $parent = null;
                    }
                    $entity->setParent($parent);
                }

                $entity->setPath($request->getValue('o:path', ''));

                $entity->setBody($request->getValue('o:body', ''));

                // Use request values for email/name if provided.
                // This allows logged-in users to use an alias (custom name/email)
                // or anonymous mode (empty name/email) while keeping the comment
                // linked to their account.
                $owner = $entity->getOwner();
                $email = $request->getValue('o:email');
                $name = $request->getValue('o:name');

                // Email: if explicitly set (including empty string), use it.
                // Only fall back to owner's email if not provided at all.
                if ($email !== null) {
                    $entity->setEmail($email);
                } elseif ($owner) {
                    $entity->setEmail($owner->getEmail());
                }

                // Name: if explicitly set (including empty string), use it.
                // Only fall back to owner's name if not provided at all.
                if ($name !== null) {
                    $entity->setName($name);
                } elseif ($owner) {
                    $entity->setName($owner->getName());
                }

                $entity->setWebsite($this->cleanWebsiteUrl($request->getValue('o:website', '')));
                $entity->setIp($this->getClientIp());
                $entity->setUserAgent($this->getUserAgent());
                break;

            case Request::UPDATE:
                // Get current user for history tracking.
                $identity = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
                $userId = $identity ? $identity->getId() : null;

                // Track body changes.
                if ($this->shouldHydrate($request, 'o:body')) {
                    $newBody = $request->getValue('o:body', '');
                    $oldBody = $entity->getBody();
                    if ($newBody !== $oldBody) {
                        $entity->addHistoryEntry('edit', ['previous_body' => $oldBody], $userId);
                    }
                    $entity->setBody($newBody);
                }

                break;
        }

        // Track status changes (for both create and update).
        $identity = $this->getServiceLocator()->get('Omeka\AuthenticationService')->getIdentity();
        $userId = $identity ? $identity->getId() : null;

        if ($this->shouldHydrate($request, 'o:approved')) {
            $newApproved = (bool) $request->getValue('o:approved', false);
            $oldApproved = $entity->isApproved();
            if ($request->getOperation() === Request::UPDATE && $newApproved !== $oldApproved) {
                $entity->addHistoryEntry($newApproved ? 'approve' : 'unapprove', [], $userId);
            }
            $entity->setApproved($newApproved);
        }
        if ($this->shouldHydrate($request, 'o:flagged')) {
            $newFlagged = (bool) $request->getValue('o:flagged', false);
            $oldFlagged = $entity->isFlagged();
            if ($request->getOperation() === Request::UPDATE && $newFlagged !== $oldFlagged) {
                $entity->addHistoryEntry($newFlagged ? 'flag' : 'unflag', [], $userId);
            }
            $entity->setFlagged($newFlagged);
        }
        if ($this->shouldHydrate($request, 'o:spam')) {
            $newSpam = (bool) $request->getValue('o:spam', false);
            $oldSpam = $entity->isSpam();
            if ($request->getOperation() === Request::UPDATE && $newSpam !== $oldSpam) {
                $entity->addHistoryEntry($newSpam ? 'spam' : 'unspam', [], $userId);
            }
            $entity->setSpam($newSpam);
        }

        $this->updateTimestamps($request, $entity);
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore): void
    {
        // When the user, the resource or the site are deleted, there is no
        // validation here, so it can be checked when created or updated?
        // No, because there may be multiple updates.
        // So the name and email are prefilled with current values if exist.
        $owner = $entity->getOwner();
        if (empty($owner)) {
            $email = $entity->getEmail();
            $validator = new EmailAddress();
            if (!$validator->isValid($email)) {
                $errorStore->addValidatorMessages('o:email', $validator->getMessages());
            }
        }

        // Validate website URL if provided.
        $website = $entity->getWebsite();
        if ($website !== null && $website !== '') {
            $uriValidator = new UriValidator(['allowRelative' => false]);
            if (!$uriValidator->isValid($website)) {
                $errorStore->addValidatorMessages('o:website', $uriValidator->getMessages());
            }
        }

        if ($entity->getIp() == '::') {
            $errorStore->addError('o:ip', 'The ip cannot be empty.'); // @translate
        }

        if ($entity->getUserAgent() == false) {
            $errorStore->addError('o:user_agent', 'The user agent cannot be empty.'); // @translate
        }

        $body = $entity->getBody();
        if (!is_string($body) || $body === '') {
            $errorStore->addError('o:body', 'The body cannot be empty.'); // @translate
        }

        // Prevent replying to unapproved comments.
        $parent = $entity->getParent();
        if ($parent && !$parent->isApproved()) {
            $errorStore->addError('o:parent', 'Cannot reply to a comment that is not yet approved.'); // @translate
        }
    }

    public function preprocessBatchUpdate(array $data, Request $request)
    {
        $updatables = [
            'o:approved' => true,
            'o:flagged' => true,
            'o:spam' => true,
        ];
        $rawData = $request->getContent();
        $rawData = array_intersect_key($rawData, $updatables);
        $data = $rawData + $data;
        return $data;
    }

    /**
     * Get the ip of the client.
     *
     * @return string
     */
    protected function getClientIp()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ) {
            return $ip;
        }
        return '::';
    }

    /**
     * Get the user agent.
     *
     * @return string
     */
    protected function getUserAgent()
    {
        return @$_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * Clean website URL by removing query string and fragment.
     *
     * @param string $url
     * @return string
     */
    protected function cleanWebsiteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        $cleanUrl = '';
        if (!empty($parts['scheme'])) {
            $cleanUrl .= $parts['scheme'] . '://';
        }
        if (!empty($parts['host'])) {
            $cleanUrl .= $parts['host'];
        }
        if (!empty($parts['port'])) {
            $cleanUrl .= ':' . $parts['port'];
        }
        if (!empty($parts['path'])) {
            $cleanUrl .= $parts['path'];
        }

        return $cleanUrl;
    }
}
