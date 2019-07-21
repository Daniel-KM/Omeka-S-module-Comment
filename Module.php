<?php
/**
 * Comment
 *
 * Add public and private commenting on resources and manage them.
 *
 * @copyright Daniel Berthereau, 2017-2018
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

use Comment\Entity\Comment;
use Comment\Form\ConfigForm;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Entity\AbstractEntity;
use Omeka\Module\AbstractModule;
// TODO Add IsSelfAssertion.
// use Omeka\Permissions\Assertion\IsSelfAssertion;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Element\Checkbox;
use Zend\Form\Fieldset;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    /**
     * @var array Cache of comments by resource.
     */
    protected $cache = [
        'owners' => [],
        'resources' => [],
        'sites' => [],
    ];

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addEntityManagerFilters();
        $this->addAclRules();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $t = $serviceLocator->get('MvcTranslator');

        $sql = <<<'SQL'
CREATE TABLE comment (
    id INT AUTO_INCREMENT NOT NULL,
    owner_id INT DEFAULT NULL,
    resource_id INT DEFAULT NULL,
    site_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    path VARCHAR(1024) NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(190) NOT NULL,
    website VARCHAR(760) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    body LONGTEXT NOT NULL,
    approved TINYINT(1) NOT NULL,
    flagged TINYINT(1) NOT NULL,
    spam TINYINT(1) NOT NULL,
    created DATETIME NOT NULL,
    modified DATETIME DEFAULT NULL,
    INDEX IDX_9474526C7E3C61F9 (owner_id),
    INDEX IDX_9474526C89329D25 (resource_id),
    INDEX IDX_9474526CF6BD1646 (site_id),
    INDEX IDX_9474526C727ACA70 (parent_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE comment ADD CONSTRAINT FK_9474526C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL;
ALTER TABLE comment ADD CONSTRAINT FK_9474526C89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE SET NULL;
ALTER TABLE comment ADD CONSTRAINT FK_9474526CF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE SET NULL;
ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id) ON DELETE SET NULL;
SQL;
        $connection = $serviceLocator->get('Omeka\Connection');
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }

        $settings = $serviceLocator->get('Omeka\Settings');
        $this->manageSettings($settings, 'install');

        $html = '<p>';
        $html .= sprintf($t->translate('I agree with %sterms of use%s and I accept to free my contribution under the licence %sCC BY-SA%s.'), // @translate
            '<a rel="licence" href="#" target="_blank">', '</a>',
            '<a rel="licence" href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank">', '</a>'
        );
        $html .= '</p>';
        $settings->set('comment_legal_text', $html);
        $this->manageSiteSettings($serviceLocator, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $sql = <<<'SQL'
DROP TABLE IF EXISTS comment;
SQL;
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->exec($sql);

        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');
        $this->manageSiteSettings($serviceLocator, 'uninstall');
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        if (version_compare($oldVersion, '3.1.5', '<')) {
            $connection = $serviceLocator->get('Omeka\Connection');
            $sql = <<<'SQL'
ALTER TABLE `comment` CHANGE `user_agent` `user_agent` text NOT NULL;
SQL;
            $connection->exec($sql);
        }
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    protected function manageSiteSettings(ServiceLocatorInterface $serviceLocator, $process)
    {
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            $this->manageSettings($siteSettings, $process, 'site_settings');
        }
    }

    /**
     * Add comment visibility filters to the entity manager.
     */
    protected function addEntityManagerFilters()
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $entityManagerFilters = $services->get('Omeka\EntityManager')->getFilters();
        $entityManagerFilters->enable('comment_visibility');
        $entityManagerFilters->getFilter('comment_visibility')->setAcl($acl);
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $settings = $services->get('Omeka\Settings');

        $publicViewComment = $settings->get('comment_public_allow_view', false);
        $publicAllowComment = $settings->get('comment_public_allow_comment', false);

        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

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
            $acl->allow(null, Comment::class, $entityRights);
            $acl->allow(null, Api\Adapter\CommentAdapter::class, $adapterRights);
            $acl->allow(null, Controller\Site\CommentController::class, $controllerRights);
        }

        // Identified users can comment. Reviewer and above can approve.
        $roles = $acl->getRoles();
        $acl->allow($roles, Comment::class, ['read', 'create', 'update']);
        $acl->allow($roles, Api\Adapter\CommentAdapter::class, ['search', 'read', 'create', 'update']);
        $acl->allow($roles, Controller\Site\CommentController::class, ['show', 'flag', 'add']);
        $acl->allow($roles, Controller\Admin\CommentController::class, ['browse', 'flag', 'add', 'show-details']);

        $approbators = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
        ];
        $acl->allow(
            $approbators,
            Comment::class,
            ['read', 'create', 'update', 'delete', 'view-all']
        );
        $acl->allow(
            $approbators,
            Api\Adapter\CommentAdapter::class,
            ['search', 'read', 'create', 'update', 'delete', 'batch-create', 'batch-update', 'batch-delete']
        );
        $acl->allow(
            $approbators,
            Controller\Admin\CommentController::class,
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

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $commentResources = $settings->get('comment_resources');
        $commentResources[] = 'user';
        $hasComments = array_flip($commentResources);

        // Add the Comment term definition.
        $sharedEventManager->attach(
            '*',
            'api.context',
            function (Event $event) {
                $context = $event->getParam('context');
                $context['o-module-comment'] = 'http://omeka.org/s/vocabs/module/comment#';
                $event->setParam('context', $context);
            }
        );

        // Add the visibility filters.
        $sharedEventManager->attach(
            '*',
            'sql_filter.resource_visibility',
            function (Event $event) {
                // Users can view comments only if they have permission to view
                // the attached resource.
                $relatedEntities = $event->getParam('relatedEntities');
                $relatedEntities[Comment::class] = 'resource_id';
                $event->setParam('relatedEntities', $relatedEntities);
            }
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
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'addFormElementsSiteSettings']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $value = $settings->get($name);
            // TODO To be replaced by a select.
            if ($name === 'comment_public_notify_post') {
                $value = $value ? implode(PHP_EOL, $value) : '';
            }
            $data[$name] = $value;
        }

        $renderer->ckEditor();

        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        $params = $controller->getRequest()->getPost();

        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($params as $name => $value) {
            if (array_key_exists($name, $defaultSettings)) {
                if ($name === 'comment_public_notify_post') {
                    // The str_replace() allows to fix Apple copy/paste.
                    $value = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $value))));
                }
                $settings->set($name, $value);
            }
        }
    }

    public function addFormElementsSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $config = $services->get('Config');
        $form = $event->getTarget();

        $defaultSiteSettings = $config[strtolower(__NAMESPACE__)]['site_settings'];

        $fieldset = new Fieldset('comment');
        $fieldset->setLabel('Comment'); // @translate

        $fieldset->add([
            'name' => 'comment_append_item_set_show',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Append automatically to item set page', // @translate
                'info' => 'If unchecked, the comments can be added via the helper in the theme in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'comment_append_item_set_show',
                    $defaultSiteSettings['comment_append_item_set_show']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'comment_append_item_show',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Append automatically to item page', // @translate
                'info' => 'If unchecked, the comments can be added via the helper in the theme in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'comment_append_item_show',
                    $defaultSiteSettings['comment_append_item_show']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'comment_append_media_show',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Append automatically to media page', // @translate
                'info' => 'If unchecked, the comments can be added via the helper in the theme in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'comment_append_media_show',
                    $defaultSiteSettings['comment_append_media_show']
                ),
            ],
        ]);

        $form->add($fieldset);
    }

    /**
     * Cache comments for resource API search/read.
     *
     * The cache avoids self::filterJsonLd() to make multiple queries to the
     * database during one request.
     *
     * @param Event $event
     */
    public function cacheData(Event $event)
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
    public function filterJsonLd(Event $event)
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
    public function searchQuery(Event $event)
    {
        $query = $event->getParam('request')->getContent();

        if (!empty($query['has_comments'])) {
            $qb = $event->getParam('queryBuilder');
            $adapter = $event->getTarget();
            $commentAlias = $adapter->createAlias();
            $entityAlias = $adapter->getEntityClass();
            $resourceName = $adapter->getResourceName() === 'users'
                ? 'owner'
                : 'resource';
            $qb->innerJoin(
                Comment::class,
                $commentAlias,
                'WITH',
                $qb->expr()->eq($commentAlias . '.' . $resourceName, $entityAlias . '.id')
            );
        }
    }

    /**
     * Add the headers for admin management.
     *
     * @param Event $event
     */
    public function addHeadersAdmin(Event $event)
    {
        $view = $event->getTarget();
        $view->headLink()->appendStylesheet($view->assetUrl('css/comment-admin.css', 'Comment'));
        $view->headScript()->appendFile($view->assetUrl('js/comment-admin.js', 'Comment'));
    }

    /**
     * Add the tab to section navigation.
     *
     * @param Event $event
     */
    public function addTab(Event $event)
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
    public function viewShowAfterUser(Event $event)
    {
        $owner = $event->getTarget()->vars()->user;
        $this->viewDetails($event, $owner);
    }

    /**
     * Display the comments of a resource.
     *
     * @param Event $event
     */
    public function viewShowAfterResource(Event $event)
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
    public function viewDetails(Event $event, $owner = null)
    {
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
    public function displayAdvancedSearch(Event $event)
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
    public function filterSearchFilters(Event $event)
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
    public function filterSearchFiltersComment(Event $event)
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
    public function viewShowAfterPublic(Event $event)
    {
        if (!$this->userCanRead()) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $view = $event->getTarget();
        $resource = $view->resource;
        $resourceName = $resource->resourceName();
        $appendMap = [
            'item_sets' => 'comment_append_item_set_show',
            'items' => 'comment_append_item_show',
            'media' => 'comment_append_media_show',
        ];
        if (!$siteSettings->get($appendMap[$resourceName])) {
            return;
        }

        echo '<div id="comments-container">';
        echo $view->showComments($resource);
        echo $view->showCommentForm($resource);
        echo '</div>';
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
        $entityColumnName = $entityColumnNames[$resource->getResourceId()];
        return $entityColumnName;
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
        $entityColumnName = $entityColumnNames[$representation->getControllerName()];
        return $entityColumnName;
    }
}
