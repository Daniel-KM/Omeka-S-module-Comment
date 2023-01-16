<?php declare(strict_types=1);
/**
 * Comment
 *
 * Add public and private commenting on resources and manage them.
 *
 * @copyright Daniel Berthereau, 2017-2020
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */
namespace Comment;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Comment\Entity\Comment;
use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
// TODO Add IsSelfAssertion.
// use Omeka\Permissions\Assertion\IsSelfAssertion;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Entity\AbstractEntity;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    /**
     * @var array Cache of comments by resource.
     */
    protected $cache = [
        'owners' => [],
        'resources' => [],
        'sites' => [],
    ];

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->addEntityManagerFilters();
        $this->addAclRules();
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $translator = $services->get('MvcTranslator');

        $html = '<p>';
        $html .= sprintf($translator->translate("I agree with %sterms of use%s and I accept to free my contribution under the licence %sCC\u{a0}BY-SA%s."), // @translate
            '<a rel="licence" href="#" target="_blank">', '</a>',
            '<a rel="licence" href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank">', '</a>'
        );
        $html .= '</p>';
        $settings->set('comment_legal_text', $html);
    }

    /**
     * Add comment visibility filters to the entity manager.
     */
    protected function addEntityManagerFilters(): void
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $services->get('Omeka\EntityManager')->getFilters()
            ->enable('comment_visibility')
            ->setAcl($acl);
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules(): void
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $settings = $services->get('Omeka\Settings');

        $publicViewComment = $settings->get('comment_public_allow_view', false);
        $publicAllowComment = $settings->get('comment_public_allow_comment', false);

        // Check if public can comment and flag, and read comments and own ones.
        if ($publicViewComment) {
            if ($publicAllowComment) {
                $entityRights = ['read', 'create', 'update'];
                $adapterRights = ['search', 'read', 'create', 'update'];
                $controllerRights = ['show', 'flag', 'add'];
            } else {
                $entityRights = ['read', 'update'];
                $adapterRights = ['search', 'read', 'update'];
                $controllerRights = ['show', 'flag'];
            }
            $acl
                ->allow(null, [Comment::class], $entityRights)
                ->allow(null, [Api\Adapter\CommentAdapter::class], $adapterRights)
                ->allow(null, [Controller\Site\CommentController::class], $controllerRights);
        }

        // Identified users can comment. Reviewer and above can approve.
        $roles = $acl->getRoles();
        $acl
            ->allow($roles, [Comment::class], ['read', 'create', 'update'])
            ->allow($roles, [Api\Adapter\CommentAdapter::class], ['search', 'read', 'create', 'update'])
            ->allow($roles, [Controller\Site\CommentController::class], ['show', 'flag', 'add'])
            ->allow($roles, [Controller\Admin\CommentController::class], ['browse', 'flag', 'add', 'show-details']);

        $approbators = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
        ];
        $acl
            ->allow(
                $approbators,
                [Comment::class],
                ['read', 'create', 'update', 'delete', 'view-all']
            )
            ->allow(
                $approbators,
                [Api\Adapter\CommentAdapter::class],
                ['search', 'read', 'create', 'update', 'delete', 'batch-create', 'batch-update', 'batch-delete']
            )
            ->allow(
                $approbators,
                [Controller\Admin\CommentController::class],
                [
                    'show',
                    'add',
                    'browse',
                    'batch-approve',
                    'batch-unapprove',
                    'batch-flag',
                    'batch-unflag',
                    'batch-set-spam',
                    'batch-set-not-spam',
                    'toggle-approved',
                    'toggle-flagged',
                    'toggle-spam',
                    'batch-delete',
                    'batch-delete-all',
                    'batch-update',
                    'approve',
                    'flag',
                    'unflag',
                    'set-spam',
                    'set-not-spam',
                    'delete',
                    'delete-confirm',
                    'show-details',
                ]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $commentResources = $settings->get('comment_resources');
        $commentResources[] = 'user';
        $hasComments = array_flip($commentResources);

        // Add the Comment term definition.
        $sharedEventManager->attach(
            '*',
            'api.context',
            [$this, 'handleApiContext']
        );

        // Add the visibility filters.
        $sharedEventManager->attach(
            '*',
            'sql_filter.resource_visibility',
            [$this, 'handleSqlResourceVisibility']
        );

        // Add the comment part to the representation.
        $representations = [
            'user' => UserRepresentation::class,
            'item_sets' => ItemSetRepresentation::class,
            'items' => ItemRepresentation::class,
            'media' => MediaRepresentation::class,
        ];
        $representations = array_intersect_key($representations, $hasComments);
        foreach ($representations as $representation) {
            $sharedEventManager->attach(
                $representation,
                'rep.resource.json',
                [$this, 'filterJsonLd']
            );
        }

        $adapters = [
            'user' => \Omeka\Api\Adapter\UserAdapter::class,
            'item_sets' => \Omeka\Api\Adapter\ItemSetAdapter::class,
            'items' => \Omeka\Api\Adapter\ItemAdapter::class,
            'media' => \Omeka\Api\Adapter\MediaAdapter::class,
        ];
        $adapters = array_intersect_key($adapters, $hasComments);
        foreach ($adapters as $adapter) {
            // Add the comment filter to the search.
            $sharedEventManager->attach(
                $adapter,
                'api.search.query',
                [$this, 'searchQuery']
            );

            // TODO Check if the cache may be really needed.
            // // Cache some resources after a search.
            // $sharedEventManager->attach(
            //     $adapter,
            //     'api.search.post',
            //     [$this, 'cacheData']
            // );
            // $sharedEventManager->attach(
            //     $adapter,
            //     'api.read.post',
            //     [$this, 'cacheData']
            // );
        }

        // No issue for creation: it cannot be created before the resource.
        // The deletion is managed automatically via sql (set null).

        // Add headers to comment views.
        $sharedEventManager->attach(
            Controller\Admin\CommentController::class,
            'view.show.before',
            [$this, 'addHeadersAdmin']
        );
        $sharedEventManager->attach(
            Controller\Admin\CommentController::class,
            'view.browse.before',
            [$this, 'addHeadersAdmin']
        );
        $sharedEventManager->attach(
            Controller\Admin\CommentController::class,
            'view.search.filters',
            [$this, 'filterSearchFiltersComment']
        );

        $controllers = [
            'item_sets' => 'Omeka\Controller\Admin\ItemSet',
            'items' => 'Omeka\Controller\Admin\Item',
            'media' => 'Omeka\Controller\Admin\Media',
        ];
        $controllers = array_intersect_key($controllers, $hasComments);
        foreach ($controllers as $controller) {
            // Add the comment field to the admin advanced search page.
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'displayAdvancedSearch']
            );

            // Add the show comments to the resource show admin pages.
            $sharedEventManager->attach(
                $controller,
                'view.show.section_nav',
                [$this, 'addTab']
            );

            // Add the show comments to the show admin pages.
            $sharedEventManager->attach(
                $controller,
                'view.show.after',
                [$this, 'viewShowAfterResource']
            );
        }

        $controllers['user'] = 'Omeka\Controller\Admin\User';
        foreach ($controllers as $controller) {
            // Add the show comments to the browse admin pages (details).
            $sharedEventManager->attach(
                $controller,
                'view.details',
                [$this, 'viewDetails']
            );

            // Filter the search filters for the advanced search pages.
            $sharedEventManager->attach(
                $controller,
                'view.search.filters',
                [$this, 'filterSearchFilters']
            );
        }

        // Add the show comment to the show admin pages.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.show.after',
            [$this, 'viewShowAfterUser']
        );

        $controllers = [
            'item_sets' => 'Omeka\Controller\Site\ItemSet',
            'items' => 'Omeka\Controller\Site\Item',
            'media' => 'Omeka\Controller\Site\Media',
        ];
        $controllers = array_intersect_key($controllers, $hasComments);
        foreach ($controllers as $controller) {
            // Add the comment field to the public advanced search page.
            $sharedEventManager->attach(
                $controller,
                'view.advanced_search',
                [$this, 'displayAdvancedSearch']
            );

            // Filter the search filters for the advanced search pages.
            $sharedEventManager->attach(
                $controller,
                'view.search.filters',
                [$this, 'filterSearchFilters']
            );

            // Add the comment to the resource show public pages.
            $sharedEventManager->attach(
                $controller,
                'view.show.after',
                [$this, 'viewShowAfterPublic']
            );
        }

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
    }

    public function handleMainSettings(Event $event): void
    {
        parent::handleMainSettings($event);

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $value = $settings->get('comment_public_notify_post') ?: [];

        $event
            ->getTarget()
            ->get('comment')
            ->get('comment_public_notify_post')
            ->setValue(implode("\n", $value));
    }

    public function handleApiContext(Event $event): void
    {
        $context = $event->getParam('context');
        $context['o-module-comment'] = 'http://omeka.org/s/vocabs/module/comment#';
        $event->setParam('context', $context);
    }

    public function handleSqlResourceVisibility(Event $event): void
    {
        // Users can view comments only if they have permission to view
        // the attached resource.
        $relatedEntities = $event->getParam('relatedEntities');
        $relatedEntities[Comment::class] = 'resource_id';
        $event->setParam('relatedEntities', $relatedEntities);
    }

    /**
     * Cache comments for resource API search/read.
     *
     * The cache avoids self::filterJsonLd() to make multiple queries to the
     * database during one request.
     *
     * @param Event $event
     */
    public function cacheData(Event $event): void
    {
        if (!$this->userCanRead()) {
            return;
        }

        $resource = $event->getParam('response')->getContent();
        // Check if this is an api search or api read to get the list of ids.
        if (is_array($resource)) {
            $resourceIds = array_map(function ($v) {
                return $v->getId();
            }, $resource);
            $first = reset($resource);
        } else {
            $resourceIds = [$resource->getId()];
            $first = $resource;
        }
        if (empty($resourceIds)) {
            return;
        }

        $entityColumnName = $this->columnNameOfEntity($first);

        // TODO Use a unique direct scalar query to get all values to cache? Cache?
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $comments = $api
            ->search('comments', [$entityColumnName => $resourceIds])
            ->getContent();
        foreach ($comments as $comment) {
            $owner = $comment->owner();
            if ($owner) {
                $this->cache['owners'][$owner->id()][$comment->id()] = $comment;
            }
            $resource = $comment->resource();
            if ($resource) {
                $this->cache['resources'][$resource->id()][$comment->id()] = $comment;
            }
            $site = $comment->site();
            if ($site) {
                $this->cache['sites'][$site->id()][$comment->id()] = $comment;
            }
        }
    }

    /**
     * Add the comment data to the resource JSON-LD.
     *
     * @param Event $event
     */
    public function filterJsonLd(Event $event): void
    {
        if (!$this->userCanRead()) {
            return;
        }

        $resource = $event->getTarget();
        $entityColumnName = $this->columnNameOfRepresentation($resource);
        $jsonLd = $event->getParam('jsonLd');
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $comments = $api
            ->search('comments', [$entityColumnName => $resource->id()], ['responseContent' => 'reference'])
            ->getContent();
        $jsonLd['o-module-comment:comment'] = $comments;
        $event->setParam('jsonLd', $jsonLd);
    }

    /**
     * Helper to filter search queries.
     *
     * @param Event $event
     */
    public function searchQuery(Event $event): void
    {
        $query = $event->getParam('request')->getContent();

        if (empty($query['has_comments'])) {
            return;
        }

        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();
        $commentAlias = $adapter->createAlias();

        $resourceName = $adapter->getResourceName() === 'users'
            ? 'owner'
            : 'resource';
        $qb
            ->innerJoin(
                Comment::class,
                $commentAlias,
                'WITH',
                $qb->expr()->eq($commentAlias . '.' . $resourceName, 'omeka_root.id')
            );
    }

    /**
     * Add the headers for admin management.
     *
     * @param Event $event
     */
    public function addHeadersAdmin(Event $event): void
    {
        $view = $event->getTarget();
        $view->headLink()->appendStylesheet($view->assetUrl('css/comment-admin.css', 'Comment'));
        $view->headScript()->appendFile($view->assetUrl('js/comment-admin.js', 'Comment'), 'text/javascript', ['defer' => 'defer']);
    }

    /**
     * Add the tab to section navigation.
     *
     * @param Event $event
     */
    public function addTab(Event $event): void
    {
        $sectionNav = $event->getParam('section_nav');
        $sectionNav['comments'] = 'Comments'; // @translate
        $event->setParam('section_nav', $sectionNav);
    }

    /**
     * Display the comments of a user.
     *
     * @param Event $event
     */
    public function viewShowAfterUser(Event $event): void
    {
        $owner = $event->getTarget()->vars()->user;
        $this->viewDetails($event, $owner);
    }

    /**
     * Display the comments of a resource.
     *
     * @param Event $event
     */
    public function viewShowAfterResource(Event $event): void
    {
        $view = $event->getTarget();
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $resource = $view->vars()->resource;
        $comments = $api->search('comments', ['resource_id' => $resource->id()])->getContent();

        echo '<div id="comments" class="section">';
        echo $view->partial(
            'common/admin/comments',
            [
                'resource' => $resource,
                'comments' => $comments,
            ]
        );
        echo $view->showCommentForm($resource);
        echo '</div>';
    }

    /**
     * Add details for a resource.
     *
     * @param Event $event
     * @param UserRepresentation $owner
     */
    public function viewDetails(Event $event, $owner = null): void
    {
        // TODO Api limit 0?

        $representation = $owner ?: $event->getParam('entity');
        $columnName = $this->columnNameOfRepresentation($representation);
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        // TODO Use one direct query instead of four for stats.
        $totalComments = $api
            ->search('comments', [$columnName => $representation->id()])
            ->getTotalResults();
        if (empty($totalComments)) {
            return;
        }
        $totalApproved = $api
            ->search('comments', [$columnName => $representation->id(), 'approved' => true])
            ->getTotalResults();
        $totalFlagged = $api
            ->search('comments', [$columnName => $representation->id(), 'flagged' => true])
            ->getTotalResults();
        $totalSpam = $api
            ->search('comments', [$columnName => $representation->id(), 'spam' => true])
            ->getTotalResults();
        echo $event->getTarget()->partial(
            'common/admin/comments-details',
            [
                'resource' => $representation,
                'total_comments' => $totalComments,
                'total_approved' => $totalApproved,
                'total_flagged' => $totalFlagged,
                'total_spam' => $totalSpam,
            ]
        );
    }

    /**
     * Display the advanced search form via partial.
     *
     * @param Event $event
     */
    public function displayAdvancedSearch(Event $event): void
    {
        $query = $event->getParam('query', []);
        $query['has_comments'] = !empty($query['has_comments']);
        $event->setParam('query', $query);

        $partials = $event->getParam('partials', []);
        $partials[] = 'common/comments-advanced-search';
        $event->setParam('partials', $partials);
    }

    /**
     * Filter search filters.
     *
     * @param Event $event
     */
    public function filterSearchFilters(Event $event): void
    {
        $translate = $event->getTarget()->plugin('translate');
        $filters = $event->getParam('filters');
        $query = $event->getParam('query', []);
        if (!empty($query['has_comments'])) {
            $filterLabel = $translate('Has comments'); // @translate
            $filterValue = $translate('true');
            $filters[$filterLabel][] = $filterValue;
        }
        $event->setParam('filters', $filters);
    }

    /**
     * Filter search filters for comments.
     *
     * @param Event $event
     */
    public function filterSearchFiltersComment(Event $event): void
    {
        $translate = $event->getTarget()->plugin('translate');
        $filters = $event->getParam('filters');
        $query = $event->getParam('query', []);
        if (!empty($query['has_comments'])) {
            $filterLabel = $translate('Has comments'); // @translate
            $filterValue = $translate('true');
            $filters[$filterLabel][] = $filterValue;
        }
        foreach ([
            'approved' => $translate('Approved'), // @translate
            'flagged' => $translate('Flagged'), // @translate
            'spam' => $translate('Is spam'), // @translate
        ] as $key => $label) {
            if (array_key_exists($key, $query)) {
                $filterLabel = $translate($label);
                $filterValue = in_array($query[$key], [false, 'false', 0, '0'], true)
                    ? $translate('false')  // @translate
                    : $translate('true'); // @translate
                $filters[$filterLabel][] = $filterValue;
            }
        }
        $event->setParam('filters', $filters);
    }

    /**
     * Display a partial for a resource in public.
     *
     * @param Event $event
     */
    public function viewShowAfterPublic(Event $event): void
    {
        if (!$this->userCanRead()) {
            return;
        }

        $view = $event->getTarget();
        $resource = $view->resource;
        echo $view->partial('common/comment', ['resource' => $resource]);
    }

    protected function userCanRead()
    {
        $userIsAllowed = $this->getServiceLocator()->get('ViewHelperManager')
            ->get('userIsAllowed');
        return $userIsAllowed(Comment::class, 'read');
    }

    protected function isCommentEnabledForResource(AbstractEntityRepresentation $resource)
    {
        if ($resource->getControllerName() === 'user') {
            return true;
        }
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $commentResources = $settings->get('comment_resources');
        $resourceName = $resource->resourceName();
        return in_array($resourceName, $commentResources);
    }

    /**
     * Helper to get the column id of an entity.
     *
     * Note: Resource representation have method resourceName(), but site page
     * and user don't. Site page has no getControllerName().
     *
     * @param AbstractEntity $resource
     * @return string
     */
    protected function columnNameOfEntity(AbstractEntity $resource)
    {
        $entityColumnNames = [
            \Omeka\Entity\ItemSet::class => 'item_set_id',
            \Omeka\Entity\Item::class => 'item_id',
            \Omeka\Entity\Media::class => 'media_id',
            \Omeka\Entity\User::class => 'owner_id',
        ];
        return $entityColumnNames[$resource->getResourceId()];
    }

    /**
     * Helper to get the column id of a representation.
     *
     * Note: Resource representation have method resourceName(), but site page
     * and user don't. Site page has no getControllerName().
     *
     * @param AbstractEntityRepresentation $representation
     * @return string
     */
    protected function columnNameOfRepresentation(AbstractEntityRepresentation $representation)
    {
        $entityColumnNames = [
            'item-set' => 'item_set_id',
            'item' => 'item_id',
            'media' => 'media_id',
            'user' => 'owner_id',
        ];
        return $entityColumnNames[$representation->getControllerName()];
    }
}
