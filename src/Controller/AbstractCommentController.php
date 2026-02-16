<?php declare(strict_types=1);

namespace Comment\Controller;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Entity\Comment;
use Comment\Form\CommentForm;
use Common\Stdlib\PsrMessage;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Api\Exception\NotFoundException;

abstract class AbstractCommentController extends AbstractActionController
{
    protected $approbators = [
        \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
        \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        \Omeka\Permissions\Acl::ROLE_EDITOR,
        \Omeka\Permissions\Acl::ROLE_REVIEWER,
    ];

    public function addAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            $this->logger()->warn(
                'The url to add comment was accessed without post data.' // @translate
            );
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $data = $this->params()->fromPost();

        if (!empty($data['o:check'])) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $a = (int) ($data['address_a'] ?? 0);
        $b = (int) ($data['address_b'] ?? 0);
        $c = (int) ($data['address'] ?? 0);
        if ($a + $b !== $c) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Are you really a robot?' // @translate
            ));
        }

        $data['o:ip'] = $this->getClientIp();
        if ($data['o:ip'] == '::') {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        // Check rate limiting.
        $rateLimitError = $this->checkRateLimit($data['o:ip']);
        if ($rateLimitError) {
            return $rateLimitError;
        }

        $data['o:user_agent'] = $this->getUserAgent();
        if (empty($data['o:user_agent'])) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access : no user agent.' // @translate
            ), Response::STATUS_CODE_403);
        }

        unset($data['o:id']);

        $resourceId = (int) ($data['resource_id'] ?? 0) ?: null;
        if (empty($resourceId)) {
            $this->logger()->warn(
                'The url to add comment was accessed without managed resource.' // @translate
            );
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        // Check a manipulation of the post for the resource.
        try {
            /** @var \Omeka\Api\Representation\AbstractResourceRepresentation $resource */
            $resource = $this->api()
                ->read('resources', $resourceId)
                ->getContent();
        } catch (NotFoundException $e) {
            $this->logger()->warn(
                'The url to add comment was accessed without resource id.' // @translate
            );
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        } catch (\Exception $e) {
            $this->logger()->warn(
                'The url to add comment was accessed without resource.' // @translate
            );
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }
        // A resource is required.
        $data['o:resource'] = $resource->getReference()->jsonSerialize();

        /** @var \Comment\Api\Representation\CommentRepresentation|null $parent */
        $parent = null;
        $parentId = (int) ($data['comment_parent_id'] ?? 0) ?: null;
        if ($parentId) {
            try {
                $parent = $this->api()
                    ->read('comments', $parentId)
                    ->getContent();
            } catch (NotFoundException $e) {
                return $this->jSend()->error(null, new PsrMessage(
                    'The parent comment does not exist.' // @translate
                ));
            } catch (\Exception $e) {
                return $this->jSend()->error(null, new PsrMessage(
                    'The parent comment does not exist.' // @translate
                ));
            }

            // Prevent replying to unapproved comments.
            if (!$parent->isApproved()) {
                return $this->jSend()->fail(null, new PsrMessage(
                    'Cannot reply to a comment that is not yet approved.' // @translate
                ));
            }
        }
        $data['o:parent'] = $parent ? $parent->getReference()->jsonSerialize() : null;

        // A user is not required, but checked for the session.
        /** @var \Omeka\Entity\User|null $user */
        $user = $this->identity();
        // TODO What is the purpose of o:user here?
        $data['o:user'] = $user ? ['o:id' => $user->getId()] : null;

        if ($user) {
            $data['o:owner'] = ['o:id' => $user->getId()];

            // Check if user chose to use an alias or anonymous mode.
            $identityMode = $data['comment_identity_mode'] ?? 'account';

            $allowAlias = $this->settings()->get('comment_user_allow_alias');
            $useAlias = $allowAlias && $identityMode === 'alias';

            $allowAnonymous = $this->settings()->get('comment_user_allow_anonymous');
            $useAnonymous = $allowAnonymous && $identityMode === 'anonymous';

            if ($useAlias) {
                // Use provided alias name and email.
                // Validate that email is provided when using an alias.
                if (empty($data['o:email'])) {
                    return $this->jSend()->fail(null, new PsrMessage(
                        'Email is required when using an alias.' // @translate
                    ));
                }
                // Name can default to empty or use provided value.
                $data['o:name'] = $data['o:name'] ?? '';
            } elseif ($useAnonymous) {
                // Anonymous mode: empty name and email.
                $data['o:name'] = '';
                $data['o:email'] = '';
            } else {
                // Use account info.
                $data['o:email'] = $user->getEmail();
                $data['o:name'] = $user->getName();
            }

            $role = $user->getRole();
            $data['o:approved'] = in_array($role, $this->approbators)
                || !$this->settings()->get('comment_user_require_moderation');
        } else {
            if (!$this->userIsAllowed(Comment::class, 'create')) {
                return $this->jSend()->fail(null, new PsrMessage(
                    'Unauthorized access.' // @translate
                ), Response::STATUS_CODE_403);
            }
            $legalText = $this->fallbackSettings()->get('comment_legal_text', ['site', 'global']);
            if ($legalText) {
                if (empty($data['legal_agreement'])) {
                    return $this->jSend()->fail(null, new PsrMessage(
                        'You should accept the legal agreement.' // @translate
                    ));
                }
            }
            $data['o:approved'] = !$this->settings()->get('comment_public_require_moderation');
        }

        /** @var \Omeka\Api\Representation\SiteRepresentation|null $site */
        $site = $this->currentSite();
        // TODO What is the purpose of this check of the current site?
        if ($site) {
            $site = $this->api()
                ->read('sites', ['id' => $site->id()])
                ->getContent();
        }
        $data['o:site'] = $site ? $site->getReference()->jsonSerialize() : null;

        if (empty($data['o:body'])) {
            return $this->jSend()->fail(null, new PsrMessage(
                'The comment cannot be empty.' // @translate
            ));
        }

        // Prevent duplicate comment (same body + resource + user/ip within 60s).
        $duplicateCheck = $this->checkDuplicateComment($resourceId, $data['o:body'], $user, $data['o:ip']);
        if ($duplicateCheck) {
            return $duplicateCheck;
        }

        $path = $data['path'];
        $data['o:path'] = $path;

        // Validate the other elements of the form via the form itself.
        $options = [
            'resource_id' => $resourceId,
            'site_slug' => $site ? $site->slug() : null,
            'user' => $user,
            'path' => $path,
        ];
        /** @var \Comment\Form\CommentForm $form */
        $form = $this->getForm(CommentForm::class, $options);
        $form->init();
        $form->setData($data);
        if (!$form->isValid()) {
            $message = new PsrMessage(
                'There is issue in your comment.' // @translate
            );
            $messages = $form->getMessages();
            if ($messages) {
                $message .= "\n" . implode("\n", $messages);
            }
            return $this->jSend()->fail(null, $message);
        }

        $data['o:flagged'] = false;
        $data['o:spam'] = $this->checkSpam($data);
        if ($data['o:spam']) {
            $data['o:approved'] = false;
        }

        // Remove non allowed data.
        try {
            $response = $this->api($form)->create('comments', $data);
        } catch (\Exception $e) {
            $response = null;
        }
        if (!$response) {
            $message = $this->translate(
                'There is issue in your comment.' // @translate
            );
            $messages = $form->getMessages();
            if ($messages) {
                $message .= "\n" . implode("\n", $messages);
            }
            return $this->jSend()->fail(null, $message);
        }

        /** @var \Comment\Api\Representation\CommentRepresentation $comment */
        $comment = $response->getContent();

        // Auto-subscribe the user to the resource when they comment.
        if ($user) {
            try {
                // Check if already subscribed.
                $this->api()->read('comment_subscriptions', [
                    'owner' => $user->getId(),
                    'resource' => $resourceId,
                ]);
            } catch (NotFoundException $e) {
                // Not subscribed yet, create subscription.
                $this->api()->create('comment_subscriptions', [
                    'o:owner' => ['o:id' => $user->getId()],
                    'o:resource' => ['o:id' => $resourceId],
                ]);
            } catch (\Exception $e) {
                // Ignore other errors silently.
            }
        }

        if ($data['o:approved']) {
            $this->messenger()->addSuccess('Your comment is online.'); // @translate
        } else {
            $this->messenger()->addSuccess('Your comment is awaiting moderation'); // @translate
        }

        if ($this->settings()->get('comment_public_notify_post')) {
            // Dispatch background job for moderator notification.
            $this->jobDispatcher()->dispatch(\Comment\Job\SendNotifications::class, [
                'type' => 'moderators',
                'comment_id' => $comment->id(),
                'resource_id' => $resourceId,
            ]);
        }

        $toModerate = !$data['o:approved'] || $data['o:spam'];
        if ($toModerate) {
            return $this->jSend()->success([
                'comment' => $comment->jsonSerialize(),
                'moderation' => true,
                'status' => 'commented',
            ], $this->translate(
                'Comment was added to the resource. It will be displayed definitely when approved.' // @translate
            ));
        } else {
            return $this->jSend()->success([
                'comment' => $comment->jsonSerialize(),
                'moderation' => false,
                'status' => 'commented',
            ]);
        }
    }

    public function editAction()
    {
        /** @var \Omeka\Entity\User $user */
        $user = $this->identity();
        if (!$user) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        try {
            $response = $this->api()->read('comments', $this->params('id'));
        } catch (\Exception $e) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access or not found.' // @translate
            ), Response::STATUS_CODE_403);
        }

        /** @var \Comment\Api\Representation\CommentRepresentation $comment */
        $comment = $response->getContent();

        if (!$comment->userIsAllowed('edit')) {
            return $this->jSend()->fail(null, new PsrMessage(
                'The user has no right to edit this resource.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $request = $this->getRequest();
        if (!$request->isPost()) {
            $this->logger()->warn(
                'The url to edit comment was accessed without post data.' // @translate
            );
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $newBody = trim($this->params()->fromPost('o:body', ''));
        if (!strlen($newBody)) {
            return $this->jSend()->fail(null, new PsrMessage(
                'No text submitted.' // @translate
            ));
        }

        if ($newBody === $comment->body()) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Text submitted is the same than the existing one.' // @translate
            ));
        }

        $resourceId = $comment->id();

        // TODO Add more check: right to edit after approbation, etc.
        $data = [
            'o:body' => $newBody,
        ];

        $isSpam = $this->checkSpam(['o:check' => false] + $data);
        if ($isSpam) {
            $data['o:spam'] = true;
        }

        // In any case, an edited comment should be reapproved.
        $role = $user->getRole();
        $data['o:approved'] = in_array($role, $this->approbators)
            || !$this->settings()->get('comment_user_require_moderation');

        // Save the new body.
        try {
            $response = $this->api()->update('comments', $resourceId, $data, [], ['isPartial' => true]);
        } catch (\Exception $e) {
            $response = null;
        }
        // Normally not possible because checked above.
        if (!$response) {
            $message = new PsrMessage(
                'An error occurred.' // @translate
            );
            return $this->jSend()->error(null, $message);
        }

        $comment = $response->getContent();

        if ($this->settings()->get('comment_public_notify_post')) {
            // Dispatch background job for moderator notification.
            $this->jobDispatcher()->dispatch(\Comment\Job\SendNotifications::class, [
                'type' => 'moderators',
                'comment_id' => $comment->id(),
                'resource_id' => $resourceId,
            ]);
        }

        // $parent = $comment->parent();
        // TODO Check parent for moderation?
        $toModerate = !$comment->isApproved() || $comment->isSpam();
        if ($toModerate) {
            return $this->jSend()->success([
                'comment' => $comment->jsonSerialize(),
                'moderation' => true,
                'status' => 'commented',
            ], $this->translate(
                'Comment was updated. It will be displayed definitely when approved.' // @translate
            ));
        } else {
            return $this->jSend()->success([
                'comment' => $comment->jsonSerialize(),
                'moderation' => false,
                'status' => 'commented',
            ]);
        }
    }

    public function deleteAction()
    {
        /** @var \Omeka\Entity\User $user */
        $user = $this->identity();
        if (!$user) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $commentId = $this->params('id');

        try {
            $response = $this->api()->read('comments', $commentId);
        } catch (\Exception $e) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access or not found.' // @translate
            ), Response::STATUS_CODE_403);
        }

        /** @var \Comment\Api\Representation\CommentRepresentation $comment */
        $comment = $response->getContent();

        if (!$comment->userIsAllowed('delete')) {
            return $this->jSend()->fail(null, new PsrMessage(
                'The user has no right to delete this resource.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $role = $user->getRole();
        $isApprobator = in_array($role, $this->approbators);

        // Soft delete: mark as deleted, keep in database.
        try {
            $this->api()->update('comments', $commentId, [
                'o:deleted' => true,
            ], [], ['isPartial' => true]);
        } catch (\Exception $e) {
            return $this->jSend()->error(null, new PsrMessage(
                'An error occurred.' // @translate
            ));
        }

        return $this->jSend()->success([
            'comment' => ['o:id' => (int) $commentId],
            'status' => 'deleted',
        ], $this->translate(
            'Comment successfully deleted.' // @translate
        ));
    }

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
        $commentId = (int) ($data['id'] ?? 0);
        if (!$commentId) {
            $this->logger()->warn(
                'The comment id cannot be identified.' // @translate
            );
            return $this->jSend()->fail(null, new PsrMessage(
                'Comment not found.' // @translate
            ));
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
            return $this->jSend()->fail(null, new PsrMessage(
                'Comment not found.' // @translate
            ));
        } catch (\Exception $e) {
            $this->logger()->warn(
                'The comment #{comment_id} cannot be accessed.', // @translate
                ['comment_id' => $commentId]
            );
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ));
        }

        $api
            ->update('comments', $commentId, ['o:flagged' => $flagUnflag], [], ['isPartial' => true]);

        return $this->jSend()->success([
            'comment' => ['o:id' => $commentId],
            'o:flagged' => $flagUnflag,
            'status' => $flagUnflag ? 'flagged' : 'unflagged',
        ]);
    }

    public function subscribeResourceAction()
    {
        $user = $this->identity();
        if (!$user) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $data = $this->params()->fromPost()
            + ['action' => 'toggle', 'id' => null];

        $resourceId = (int) $data['id'];
        if (empty($resourceId)) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        // Check a manipulation of the post for the resource.
        try {
            /** @var \Omeka\Api\Representation\AbstractResourceRepresentation $resource */
            $resource = $api->read('resources', ['id' => $resourceId])->getContent();
        } catch (NotFoundException $e) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Resource not found.' // @translate
            ), Response::STATUS_CODE_403);
        } catch (\Exception $e) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Unauthorized access.' // @translate
            ), Response::STATUS_CODE_403);
        }

        $action = $data['action'] ?: 'toggle';
        if (!in_array($action, ['add', 'delete', 'toggle'])) {
            return $this->jSend()->fail(null, new PsrMessage(
                'Action {action} not allowed.', // @translate
                ['action' => $action]
            ));
        }

        try {
            $subscription = $api->read('comment_subscriptions', [
                'owner' => $user->getId(),
                'resource' => $resourceId,
            ])->getContent();
        } catch (\Exception $e) {
            $subscription = null;
        }

        if ($action === 'toggle') {
            $action = $subscription ? 'delete' : 'add';
        }

        if ($action === 'add') {
            if (!$subscription) {
                $subscription = $api->create('comment_subscriptions', [
                    'o:resource' => ['o:id' => $resourceId],
                ])->getContent();
            }
        } else {
            if ($subscription) {
                $api->delete('comment_subscriptions', ['id' => $subscription->id()]);
                $subscription = null;
            }
        }

        return $this->jSend()->success([
            'comment_subscription' => $subscription
                ? $subscription->jsonSerialize()
               : ['o:resource' => $resource->getReference()->jsonSerialize()],
            'status' => $subscription ? 'subscribed' : 'unsubscribed',
        ]);
    }

    /**
     * Check if a comment is a spam.
     *
     * If Akismet is not installed, return false.
     *
     * @param array $data
     * @return bool
     */
    protected function checkSpam(array $data)
    {
        // Check if honey pot is filled.
        if (!empty($data['o:check'])) {
            return true;
        }

        $wordPressAPIKey = $this->settings()->get('commenting_wpapi_key');
        if ($wordPressAPIKey) {
            if (!class_exists('ZendService\Akismet\Akismet')) {
                $this->logger()->err(
                    'Akismet is not available: install it.' // @translate
                );
                return false;
            }
            $viewHelpers = $this->viewHelpers();
            $serverUrlHelper = $viewHelpers->get('serverUrl');
            $basePath = $viewHelpers->get('basePath');
            $serverUrl = $serverUrlHelper($basePath());
            $akismet = new \ZendService\Akismet\Akismet($wordPressAPIKey, $serverUrl);
            $akismetData = $this->getAkismetData($data);
            try {
                $isSpam = $akismet->isSpam($akismetData);
            } catch (\Exception $e) {
                $isSpam = true;
            }
        }

        // If not using Akismet, assume only registered users are commenting,
        // or there is moderation.
        else {
            $isSpam = false;
        }

        return $isSpam;
    }

    protected function getAkismetData(array $postData)
    {
        $serverUrl = $this->getPluginManager()->get('viewHelpers')->get('serverUrl');
        $path = $this->getRequest()->getRequestUri();
        $permalink = $serverUrl() . $path;
        $data = [
            'user_ip' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'permalink' => $permalink,
            'comment_type' => 'comment',
            'comment_author_email' => $postData['o:email'],
            'comment_content' => $postData['o:body'],
        ];
        if (!empty($postData['o:website'])) {
            $data['comment_author_url'] = $postData['o:website'];
        }
        if (!empty($postData['o:name'])) {
            $data['comment_author_name'] = $postData['o:name'];
        }
        return $data;
    }

    /**
     * Get the ip of the client.
     *
     * @return string
     */
    protected function getClientIp()
    {
        $ip = (new RemoteAddress())->getIpAddress();
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
     * Check if the IP address has exceeded the rate limit.
     *
     * @param string $ip
     * @return \Laminas\View\Model\JsonModel|null Error response or null if allowed.
     */
    protected function checkRateLimit(string $ip)
    {
        $settings = $this->settings();
        $maxCount = (int) $settings->get('comment_rate_limit_count', 0);
        $periodMinutes = (int) $settings->get('comment_rate_limit_period', 60);

        // Rate limiting disabled.
        if ($maxCount <= 0) {
            return null;
        }

        // Count recent comments from this IP using the API.
        $since = (new \DateTime("-{$periodMinutes} minutes"))->format('Y-m-d\TH:i:s');

        try {
            $response = $this->api()->search('comments', [
                'ip' => $ip,
                'created_after' => $since,
            ], ['returnScalar' => 'id']);
            $count = $response->getTotalResults();
        } catch (\Exception $e) {
            // On error, allow the comment (fail open).
            return null;
        }

        if ($count >= $maxCount) {
            $this->logger()->warn(
                'Rate limit exceeded for IP {ip}: {count} comments in {period} minutes.', // @translate
                ['ip' => $ip, 'count' => $count, 'period' => $periodMinutes]
            );
            return $this->jSend()->fail(null, new PsrMessage(
                'Too many comments. Please wait before posting again.' // @translate
            ), Response::STATUS_CODE_429);
        }

        return null;
    }

    /**
     * Check if a duplicate comment was recently posted.
     *
     * @param int $resourceId
     * @param string $body
     * @param \Omeka\Entity\User|null $user
     * @param string $ip
     * @return \Laminas\View\Model\JsonModel|null Error response or null if allowed.
     */
    protected function checkDuplicateComment(int $resourceId, string $body, $user, string $ip)
    {
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $this->getEvent()->getApplication()
            ->getServiceManager()
            ->get('Omeka\EntityManager');

        $since = new \DateTime('-60 seconds');
        $dql = 'SELECT COUNT(c.id) FROM Comment\Entity\Comment c'
            . ' WHERE c.resource = :resourceId'
            . ' AND c.body = :body'
            . ' AND c.created >= :since';
        $params = [
            'resourceId' => $resourceId,
            'body' => $body,
            'since' => $since,
        ];

        if ($user) {
            $dql .= ' AND c.owner = :userId';
            $params['userId'] = $user->getId();
        } else {
            $dql .= ' AND c.ip = :ip';
            $params['ip'] = $ip;
        }

        $count = (int) $entityManager->createQuery($dql)
            ->setParameters($params)
            ->getSingleScalarResult();

        if ($count > 0) {
            return $this->jSend()->fail(null, new PsrMessage(
                'This comment has already been posted.' // @translate
            ));
        }

        return null;
    }
}
