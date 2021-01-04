<?php declare(strict_types=1);
namespace Comment\Api\Adapter;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Entity\Comment;
use Doctrine\ORM\QueryBuilder;
use Laminas\Validator\EmailAddress;
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
    use QueryBuilderTrait;

    protected $sortFields = [
        'id' => 'id',
        'owner_id' => 'owner',
        'resource_id' => 'resource',
        'item_set_id' => 'resource',
        'item_id' => 'resource',
        'media_id' => 'resource',
        'site_id' => 'site',
        'path' => 'path',
        'email' => 'email',
        'website' => 'website',
        'name' => 'name',
        'ip' => 'ip',
        'user_agent' => 'user_agent',
        // For info.
        // // 'resource_title' => 'resource',
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

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
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
                    } elseif (is_numeric($data['o:resource']['o:id'])) {
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
                    } elseif (is_numeric($data['o:site']['o:id'])) {
                        $site = $this->getAdapter('sites')
                            ->findEntity(['id' => $data['o:site']['o:id']]);
                    } else {
                        $site = null;
                    }
                    $entity->setSite($site);
                }

                if (isset($data['o-module-comment:parent'])) {
                    if (is_object($data['o-module-comment:parent'])) {
                        $parent = $data['o-module-comment:parent'];
                    } elseif (is_numeric($data['o-module-comment:parent']['o:id'])) {
                        $parent = $this
                            ->findEntity(['id' => $data['o-module-comment:parent']['o:id']]);
                    } else {
                        $parent = null;
                    }
                    $entity->setParent($parent);
                }

                $entity->setPath($request->getValue('o-module-comment:path', ''));
                $entity->setBody($request->getValue('o-module-comment:body', ''));

                $owner = $entity->getOwner();
                if ($owner) {
                    $entity->setEmail($owner->getEmail());
                    $entity->setName($owner->getName());
                } else {
                    $entity->setEmail($request->getValue('o:email'));
                    $entity->setName($request->getValue('o:name'));
                }

                $entity->setWebsite($request->getValue('o-module-comment:website', ''));
                $entity->setIp($this->getClientIp());
                $entity->setUserAgent($this->getUserAgent());
                break;

            case Request::UPDATE:
                // Nothing can be changed, except flags below.
                break;
        }

        if ($this->shouldHydrate($request, 'o-module-comment:approved')) {
            $entity->setApproved($request->getValue('o-module-comment:approved', false));
        }
        if ($this->shouldHydrate($request, 'o-module-comment:flagged')) {
            $entity->setFlagged($request->getValue('o-module-comment:flagged', false));
        }
        if ($this->shouldHydrate($request, 'o-module-comment:spam')) {
            $entity->setSpam($request->getValue('o-module-comment:spam', false));
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

        if ($entity->getIp() == '::') {
            $errorStore->addError('o-module-comment:ip', 'The ip cannot be empty.'); // @translate
        }

        if ($entity->getUserAgent() == false) {
            $errorStore->addError('o-module-comment:user_agent', 'The user agent cannot be empty.'); // @translate
        }

        $body = $entity->getBody();
        if (!is_string($body) || $body === '') {
            $errorStore->addError('o-module-comment:body', 'The body cannot be empty.'); // @translate
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
        $expr = $qb->expr();

        // TODO Check resource and owner visibility for public view.

        if (array_key_exists('id', $query)) {
            $this->buildQueryIdsItself($qb, $query['id'], 'id');
        }

        // All comments with any entities ("OR"). If multiple, mixed with "AND".
        foreach ([
            'resource_id' => 'resource',
            'item_set_id' => 'resource',
            'item_id' => 'resource',
            'media_id' => 'resource',
            'owner_id' => 'owner',
            'site_id' => 'tag',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query)) {
                $this->buildQueryIds($qb, $query[$queryKey], $column, 'id');
            }
        }

        if (array_key_exists('has_resource', $query)) {
            // An empty string means true in order to manage get/post query.
            if (in_array($query['has_resource'], [false, 'false', 0, '0'], true)) {
                $qb
                    ->andWhere($expr->isNull($alias . '.resource'));
            } else {
                $qb
                    ->andWhere($expr->isNotNull($alias . '.resource'));
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
                     ->andWhere($expr->isNotNull($alias . '.resource'));
            } elseif (isset($mapResourceTypes[$query['resource_type']])) {
                $entityAlias = $this->createAlias();
                $qb
                    ->innerJoin(
                        $mapResourceTypes[$query['resource_type']],
                        $entityAlias,
                        'WITH',
                        $expr->eq(
                            $alias . '.resource',
                            $entityAlias . '.id'
                        )
                    );
            } elseif ($query['resource_type'] !== '') {
                $qb
                    ->andWhere('1 = 0');
            }
        }

        foreach ([
            'path' => 'path',
            'email' => 'email',
            'website' => 'website',
            'name' => 'name',
            'ip' => 'ip',
            'user_agent' => 'user_agent',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query) && strlen($query[$queryKey])) {
                $qb
                    ->andWhere($expr->eq($alias . '.' . $column, $query[$queryKey]));
            }
        }

        // All comments with any entities ("OR"). If multiple, mixed with "AND".
        foreach ([
            'approved' => 'approved',
            'flagged' => 'flagged',
            'spam' => 'spam',
        ] as $queryKey => $column) {
            if (array_key_exists($queryKey, $query)) {
                // An empty string means true in order to manage get/post query.
                if (in_array($query[$queryKey], [false, 'false', 0, '0'], true)) {
                    $qb
                        ->andWhere($expr->eq($alias . '.' . $column, 0));
                } else {
                    $qb
                        ->andWhere($expr->eq($alias . '.' . $column, 1));
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

    public function preprocessBatchUpdate(array $data, Request $request)
    {
        $updatables = [
            'o-module-comment:approved' => true,
            'o-module-comment:flagged' => true,
            'o-module-comment:spam' => true,
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
}
