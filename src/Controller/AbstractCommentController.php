<?php declare(strict_types=1);

namespace Comment\Controller;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Entity\Comment;
use Comment\Form\CommentForm;
use Common\Mvc\Controller\Plugin\JSend;
use Common\Stdlib\PsrMessage;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Stdlib\Message;

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
            $this->logger()->warn('The url to add comment was accessed without post data.');
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        }

        $data = $this->params()->fromPost();

        if (!empty($data['o:check'])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        }

        $a = (int) ($data['address_a'] ?? 0);
        $b = (int) ($data['address_b'] ?? 0);
        $c = (int) ($data['address'] ?? 0);
        if ($a + $b !== $c) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Are you really a robot?' // @translate
            ))->setTranslator($this->translator()));
        }

        $data['o:ip'] = $this->getClientIp();
        if ($data['o:ip'] == '::') {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        }

        $data['o:user_agent'] = $this->getUserAgent();
        if (empty($data['o:user_agent'])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access : no user agent.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        }

        unset($data['o:id']);

        $resourceId = (int) ($data['resource_id'] ?? 0) ?: null;
        if (empty($resourceId)) {
            $this->logger()->warn('The url to add comment was accessed without managed resource.'); // @translate
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        }

        // Check a manipulation of the post for the resource.
        try {
            /** @var \Omeka\Api\Representation\AbstractResourceRepresentation $resource */
            $resource = $this->api()
                ->read('resources', $resourceId)
                ->getContent();
        } catch (NotFoundException $e) {
            $this->logger()->warn('The url to add comment was accessed without resource id.'); // @translate
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        } catch (\Exception $e) {
            $this->logger()->warn('The url to add comment was accessed without resource.'); // @translate
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
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
                return $this->jSend(JSend::Error, null, (new PsrMessage(
                    'The parent comment does not exist.' // @translate
                ))->setTranslator($this->translator()));
            } catch (\Exception $e) {
                return $this->jSend(JSend::Error, null, (new PsrMessage(
                    'The parent comment doesn’t exist.' // @translate
                ))->setTranslator($this->translator()));
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
            $data['o:email'] = $user->getEmail();
            $data['o:name'] = $user->getName();
            $role = $user->getRole();
            $data['o:approved'] = in_array($role, $this->approbators)
                || !$this->settings()->get('comment_user_require_moderation');
        } else {
            if (!$this->userIsAllowed(Comment::class, 'create')) {
                return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                    'Unauthorized access.' // @translate
                ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
            }
            $legalText = $this->fallbackSettings()->get('comment_legal_text', ['site', 'global']);
            if ($legalText) {
                if (empty($data['legal_agreement'])) {
                    return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                        'You should accept the legal agreement.' // @translate
                    ))->setTranslator($this->translator()));
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
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'The comment cannot be empty.' // @translate
            ))->setTranslator($this->translator()));
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
            $message = (new PsrMessage(
                'There is issue in your comment.' // @translate
            ))->setTranslator($this->translator());
            $messages = $form->getMessages();
            if ($messages) {
                $message .= "\n" . implode("\n", $messages);
            }
            return $this->jSend(JSend::FAIL, null, $message);
        }

        $data['o:flagged'] = false;
        $data['o:spam'] = $this->checkSpam($data);
        if ($data['o:spam']) {
            $data['o:approved'] = false;
        }

        // Remove non allowed data.
        $response = $this->api($form)->create('comments', $data);
        if (!$response) {
            $message = (new PsrMessage(
                'There is issue in your comment.' // @translate
            ))->setTranslator($this->translator());
            $messages = $form->getMessages();
            if ($messages) {
                $message .= "\n" . implode("\n", $messages);
            }
            return $this->jSend(JSend::FAIL, null, $message);
        }

        /** @var \Comment\Api\Representation\CommentRepresentation $comment */
        $comment = $response->getContent();

        if ($data['o:approved']) {
            $this->messenger()->addSuccess('Your comment is online.'); // @translate
        } else {
            $this->messenger()->addSuccess('Your comment is awaiting moderation'); // @translate
        }

        if ($this->settings()->get('comment_public_notify_post')) {
            // TODO Use adapter to get representation.
            $representation = $this->api()
                ->read('resources', $resourceId)
                ->getContent();
            $this->notifyEmail($representation, $comment);
        }

        $toModerate = !$data['o:approved'] || $data['o:spam'];
        $toModerate = $toModerate || ($parent && !$parent->isApproved());
        if ($toModerate) {
            return $this->jSend(JSend::SUCCESS, [
                'o:resource' => ['o:id' => $resourceId],
                'moderation' => true,
                'status' => 'commented',
            ], $this->translate(
                'Comment was added to the resource. It will be displayed definitely when approved.' // @translate
            ));
        } else {
            return $this->jSend(JSend::SUCCESS, [
                'o:resource' => ['o:id' => $resourceId],
                'moderation' => false,
                'status' => 'commented',
            ]);
        }
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

    public function subscribeResourceAction()
    {
        $user = $this->identity();
        if (!$user) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        }

        $data = $this->params()->fromPost()
            + ['action' => 'toggle', 'id' => null];

        $resourceId = (int) $data['id'];
        if (empty($resourceId)) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        }

        /** @var \Omeka\Mvc\Controller\Plugin\Api $api */
        $api = $this->api();

        // Check a manipulation of the post for the resource.
        try {
            /** @var \Omeka\Api\Representation\AbstractResourceRepresentation $resource */
            $resource = $api->read('resources', ['id' => $resourceId])->getContent();
        } catch (NotFoundException $e) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Resource not found.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        } catch (\Exception $e) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Unauthorized access.' // @translate
            ))->setTranslator($this->translator()), Response::STATUS_CODE_403);
        }

        $action = $data['action'] ?: 'toggle';
        if (!in_array($action, ['add', 'delete', 'toggle'])) {
            return $this->jSend(JSend::FAIL, null, (new PsrMessage(
                'Action {action} not allowed.', // @translate
                ['action' => $action]
            ))->setTranslator($this->translator()));
        }

        try {
            $subscription = $api->read('comment_subscriptions', [
                'owner' => $user->getId(),
                'resource' => $resourceId,
            ], [], ['responseContent' => 'resource'])->getContent();
        } catch (\Exception $e) {
            $subscription = null;
        }

        if ($action === 'toggle') {
            $action = $subscription ? 'delete' : 'add';
        }

        if ($action === 'add') {
            if (!$subscription) {
                $subscription = $api->create('comment_subscriptions', [
                    'o:owner' => ['o:id' => $user->getId()],
                    'o:resource' => ['o:id' => $resourceId]
                ], [], ['responseContent' => 'resource'])->getContent();
            }
        } else {
            if ($subscription) {
                $api->delete('comment_subscriptions', ['id' => $subscription->getId()]);
                $subscription = null;
            }
        }

        return $this->jSend(JSend::SUCCESS, [
            'o:resource' => $resource->getReference()->jsonSerialize(),
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
                $this->logger()->err('Akismet not available: install it.'); // @translate
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
     * Notify by email for a comments on a resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param CommentRepresentation $comment
     */
    protected function notifyEmail(AbstractResourceEntityRepresentation $resource, CommentRepresentation $comment): void
    {
        $site = @$_SERVER['SERVER_NAME'] ?: sprintf('Server (%s)', @$_SERVER['SERVER_ADDR']); // @translate
        $subject = new Message('[%s] New public comment', $site); // @translate

        $body = new Message('A comment was added to resource #%d (%s) by %s <%s>.', // @translate
            $resource->id(), $resource->adminUrl(), $comment->name(), $comment->email());
        $body .= "\r\n";
        $body .= new Message('Comment: %s', "\n" . $comment->body()); // @translate
        $body .= "\r\n\r\n";

        $mailer = $this->mailer();
        $message = $mailer->createMessage();
        $emails = $this->settings()->get('comment_public_notify_post');
        foreach ($emails as $email) {
            $message->addTo($email);
        }
        $message
            ->setSubject($subject)
            ->setBody($body);
        try {
            $mailer->send($message);
        } catch (\Laminas\Mail\Transport\Exception\RuntimeException $e) {
            $this->logger()->err('Unable to send an email after commenting.'); // @translate
        }
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
}
