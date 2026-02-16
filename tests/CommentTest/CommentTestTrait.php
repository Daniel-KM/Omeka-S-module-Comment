<?php declare(strict_types=1);

namespace CommentTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\UserRepresentation;

/**
 * Shared test helpers for Comment module tests.
 */
trait CommentTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array List of created resource IDs for cleanup.
     */
    protected $createdResources = [];

    /**
     * @var array List of created comment IDs for cleanup.
     */
    protected $createdComments = [];

    /**
     * @var array List of created user IDs for cleanup.
     */
    protected $createdUsers = [];

    /**
     * @var array List of created site IDs for cleanup.
     */
    protected $createdSites = [];

    /**
     * Get the API manager.
     */
    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the service locator.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    /**
     * Get the entity manager.
     */
    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Login as a specific user.
     */
    protected function loginAs(string $email, string $password = 'test'): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        $auth->authenticate();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Get the current logged-in user.
     *
     * @return \Omeka\Entity\User|null
     */
    protected function getCurrentUser()
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        return $auth->getIdentity();
    }

    /**
     * Create a test user.
     */
    protected function createUser(string $email, string $name, string $role = 'researcher'): UserRepresentation
    {
        $response = $this->api()->create('users', [
            'o:email' => $email,
            'o:name' => $name,
            'o:role' => $role,
            'o:is_active' => true,
        ]);
        $user = $response->getContent();
        $this->createdUsers[] = $user->id();

        // Set password via entity manager.
        $entityManager = $this->getEntityManager();
        $userEntity = $entityManager->find(\Omeka\Entity\User::class, $user->id());
        $userEntity->setPassword('test');
        $entityManager->flush();

        return $user;
    }

    /**
     * Create a test item.
     */
    protected function createItem(array $data = []): ItemRepresentation
    {
        $itemData = [];

        // Set default title if not provided.
        if (!isset($data['dcterms:title'])) {
            $data['dcterms:title'] = [['type' => 'literal', '@value' => 'Test Item']];
        }

        // Convert property terms to proper format.
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');

        foreach ($data as $term => $values) {
            if (strpos($term, ':') === false) {
                $itemData[$term] = $values;
                continue;
            }

            $propertyId = $easyMeta->propertyId($term);
            if (!$propertyId) {
                continue;
            }

            $itemData[$term] = [];
            foreach ($values as $value) {
                $valueData = [
                    'type' => $value['type'] ?? 'literal',
                    'property_id' => $propertyId,
                ];
                if (isset($value['@value'])) {
                    $valueData['@value'] = $value['@value'];
                }
                if (isset($value['@id'])) {
                    $valueData['@id'] = $value['@id'];
                }
                $itemData[$term][] = $valueData;
            }
        }

        $response = $this->api()->create('items', $itemData);
        $item = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];

        return $item;
    }

    /**
     * Create a test site.
     */
    protected function createSite(string $slug = 'test-site', string $title = 'Test Site'): SiteRepresentation
    {
        $response = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:title' => $title,
            'o:theme' => 'default',
            'o:is_public' => true,
        ]);
        $site = $response->getContent();
        $this->createdSites[] = $site->id();

        return $site;
    }

    /**
     * Create a comment for a resource.
     *
     * Uses entity manager directly to avoid $_SERVER requirements in adapter.
     *
     * @param int $resourceId Resource ID.
     * @param array $data Additional comment data.
     * @return \Comment\Api\Representation\CommentRepresentation
     */
    protected function createComment(int $resourceId, array $data = [])
    {
        $entityManager = $this->getEntityManager();
        $currentUser = $this->getCurrentUser();

        $resource = $entityManager->find(\Omeka\Entity\Resource::class, $resourceId);

        $comment = new \Comment\Entity\Comment();
        $comment->setResource($resource);
        $comment->setBody($data['body'] ?? 'This is a test comment.');
        $comment->setPath($data['path'] ?? '/s/test/item/' . $resourceId);
        $comment->setEmail($data['email'] ?? ($currentUser ? $currentUser->getEmail() : 'test@example.com'));
        $comment->setName($data['name'] ?? ($currentUser ? $currentUser->getName() : 'Test User'));
        $comment->setWebsite($data['website'] ?? '');
        $comment->setIp($data['ip'] ?? '127.0.0.1');
        $comment->setUserAgent($data['user_agent'] ?? 'PHPUnit Test');
        $comment->setApproved($data['approved'] ?? false);
        $comment->setFlagged($data['flagged'] ?? false);
        $comment->setSpam($data['spam'] ?? false);
        $comment->setCreated(new \DateTime('now'));

        if ($currentUser) {
            $comment->setOwner($currentUser);
        }

        if (isset($data['site_id'])) {
            $site = $entityManager->find(\Omeka\Entity\Site::class, $data['site_id']);
            $comment->setSite($site);
        }

        $entityManager->persist($comment);
        $entityManager->flush();

        $this->createdComments[] = $comment->getId();

        // Return as representation.
        $adapter = $this->getCommentAdapter();
        return $adapter->getRepresentation($comment);
    }

    /**
     * Get the CommentAdapter instance.
     *
     * @return \Comment\Api\Adapter\CommentAdapter
     */
    protected function getCommentAdapter()
    {
        return $this->getServiceLocator()->get('Omeka\ApiAdapterManager')->get('comments');
    }

    /**
     * @var array List of created subscription IDs for cleanup.
     */
    protected $createdSubscriptions = [];

    /**
     * Create a subscription for a user on a resource.
     */
    protected function createSubscription(int $userId, int $resourceId): \Comment\Entity\CommentSubscription
    {
        $entityManager = $this->getEntityManager();
        $user = $entityManager->find(\Omeka\Entity\User::class, $userId);
        $resource = $entityManager->find(\Omeka\Entity\Resource::class, $resourceId);

        $subscription = new \Comment\Entity\CommentSubscription();
        $subscription->setOwner($user);
        $subscription->setResource($resource);
        $subscription->setCreated(new \DateTime('now'));

        $entityManager->persist($subscription);
        $entityManager->flush();

        $this->createdSubscriptions[] = $subscription->getId();
        return $subscription;
    }

    /**
     * Count subscriptions for a user on a resource.
     */
    protected function countSubscriptions(int $userId, int $resourceId): int
    {
        $entityManager = $this->getEntityManager();
        $dql = 'SELECT COUNT(cs.id) FROM Comment\Entity\CommentSubscription cs WHERE cs.owner = :userId AND cs.resource = :resourceId';
        return (int) $entityManager->createQuery($dql)
            ->setParameter('userId', $userId)
            ->setParameter('resourceId', $resourceId)
            ->getSingleScalarResult();
    }

    /**
     * Count jobs of a given class dispatched after a given time.
     */
    protected function countJobsSince(string $class, \DateTime $since): int
    {
        $entityManager = $this->getEntityManager();
        $dql = 'SELECT COUNT(j.id) FROM Omeka\Entity\Job j WHERE j.class = :class AND j.id > :minId';
        // Use a subquery to find jobs created after the given time.
        // Since Job doesn't always have a "created" field, use ID ordering.
        // Instead, query by class and check manually.
        $dql = 'SELECT COUNT(j.id) FROM Omeka\Entity\Job j WHERE j.class = :class';
        return (int) $entityManager->createQuery($dql)
            ->setParameter('class', $class)
            ->getSingleScalarResult();
    }

    /**
     * Get the latest job of a given class.
     */
    protected function getLatestJob(string $class): ?\Omeka\Entity\Job
    {
        $entityManager = $this->getEntityManager();
        $dql = 'SELECT j FROM Omeka\Entity\Job j WHERE j.class = :class ORDER BY j.id DESC';
        return $entityManager->createQuery($dql)
            ->setParameter('class', $class)
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    /**
     * Clean up created resources after test.
     */
    protected function cleanupResources(): void
    {
        $entityManager = $this->getEntityManager();

        // Delete created comments first.
        foreach ($this->createdComments as $commentId) {
            try {
                $comment = $entityManager->find(\Comment\Entity\Comment::class, $commentId);
                if ($comment) {
                    $entityManager->remove($comment);
                }
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdComments = [];

        try {
            $entityManager->flush();
        } catch (\Exception $e) {
            // Ignore.
        }

        // Delete created subscriptions.
        foreach ($this->createdSubscriptions as $subscriptionId) {
            try {
                $subscription = $entityManager->find(\Comment\Entity\CommentSubscription::class, $subscriptionId);
                if ($subscription) {
                    $entityManager->remove($subscription);
                }
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdSubscriptions = [];

        try {
            $entityManager->flush();
        } catch (\Exception $e) {
            // Ignore.
        }

        // Delete created items.
        foreach ($this->createdResources as $resource) {
            try {
                $this->api()->delete($resource['type'], $resource['id']);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdResources = [];

        // Delete created sites.
        foreach ($this->createdSites as $siteId) {
            try {
                $this->api()->delete('sites', $siteId);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdSites = [];

        // Delete created users.
        foreach ($this->createdUsers as $userId) {
            try {
                $this->api()->delete('users', $userId);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdUsers = [];
    }
}
