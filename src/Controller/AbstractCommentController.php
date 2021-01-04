<?php
namespace Comment\Controller;

use Comment\Api\Representation\CommentRepresentation;
use Comment\Entity\Comment;
use Comment\Form\CommentForm;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Stdlib\Message;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;

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
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        $data = $this->params()->fromPost();

        if (!empty($data['o-module-comment:check'])) {
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        $a = (int) @$data['address_a'];
        $b = (int) @$data['address_b'];
        $c = (int) @$data['address'];
        if ($a + $b !== $c) {
            return $this->jsonError('Are you really a robot?'); // @translate
        }

        $data['o-module-comment:ip'] = $this->getClientIp();
        if ($data['o-module-comment:ip'] == '::') {
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        $data['o-module-comment:user_agent'] = $this->getUserAgent();
        if (empty($data['o-module-comment:user_agent'])) {
            return $this->jsonError('Unauthorized access : no user agent.', Response::STATUS_CODE_403); // @translate
        }

        unset($data['o:id']);

        $resourceId = @$data['resource_id'];
        if (empty($resourceId)) {
            $this->logger()->warn('The url to add comment was accessed without managed resource.'); // @translate
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }

        // Check a manipulation of the post for the resource.
        try {
            $resource = $this->api()
                ->read('resources', $resourceId, [], ['responseContent' => 'resource'])
                ->getContent();
        } catch (NotFoundException $e) {
            $this->logger()->warn('The url to add comment was accessed without resource id.'); // @translate
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        } catch (\Exception $e) {
            $this->logger()->warn('The url to add comment was accessed without resource.'); // @translate
            return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
        }
        $data['o:resource'] = $resource;

        $parentId = @$data['comment_parent_id'];
        if (empty($parentId)) {
            $parent = null;
        } else {
            try {
                $parent = $this->api()
                    ->read('comments', $parentId, [], ['responseContent' => 'resource'])
                    ->getContent();
            } catch (NotFoundException $e) {
                return $this->jsonError('The parent comment does not exist.'); // @translate
            } catch (\Exception $e) {
                return $this->jsonError('The parent comment doesn’t exist.'); // @translate
            }
        }
        $data['o-module-comment:parent'] = $parent;

        $user = $this->identity();
        $data['o:user'] = $user;

        if ($user) {
            $data['o:owner'] = $user;
            $data['o:email'] = $user->getEmail();
            $data['o:name'] = $user->getName();
            $role = $user->getRole();
            $data['o-module-comment:approved'] = in_array($role, $this->approbators)
                || !$this->settings()->get('comment_user_require_moderation');
        } else {
            if (!$this->userIsAllowed(Comment::class, 'create')) {
                return $this->jsonError('Unauthorized access.', Response::STATUS_CODE_403); // @translate
            }
            $legalText = $this->settings()->get('comment_legal_text');
            if ($legalText) {
                if (empty($data['legal_agreement'])) {
                    return $this->jsonError('You should accept the legal agreement.'); // @translate
                }
            }
            $data['o-module-comment:approved'] = !$this->settings()->get('comment_public_require_moderation');
        }

        $site = $this->currentSite();
        if ($site) {
            $site = $this->api()
                ->read('sites', $site->id(), [], ['responseContent' => 'resource'])
                ->getContent();
        }
        $data['o:site'] = $site;

        if (empty($data['o-module-comment:body'])) {
            return $this->jsonError('The comment cannot be empty.'); // @translate
        }

        $path = $data['path'];
        $data['o-module-comment:path'] = $path;

        // Validate the other elements of the form via the form itself.
        $options = [
            'resource_id' => $resourceId,
            'site_slug' => $site ? $site->getSlug() : null,
            'user' => $user,
            'path' => $path,
        ];
        /** @var \Comment\Form\CommentForm $form */
        $form = $this->getForm(CommentForm::class, $options);
        $form->init();
        $form->setData($data);
        if (!$form->isValid()) {
            $messages = $form->getMessages();
            return $this->jsonError('There is issue in your comment.', // @translate
                Response::STATUS_CODE_400,
                $messages);
        }

        $data['o-module-comment:flagged'] = false;
        $data['o-module-comment:spam'] = $this->checkSpam($data);
        if ($data['o-module-comment:spam']) {
            $data['o-module-comment:approved'] = false;
        }

        $response = $this->api($form)->create('comments', $data);
        if (!$response) {
            $messages = $form->getMessages();
            return $this->jsonError('There is issue in your comment.', // @translate
                Response::STATUS_CODE_400,
                $messages);
        }
        $comment = $response->getContent();

        if ($data['o-module-comment:approved']) {
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

        return new JsonModel([
            'content' => [
                'resource_id' => $resourceId,
                'moderation' => !$data['o-module-comment:approved'],
            ],
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
        if (!empty($data['o-module-comment:check'])) {
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
            'comment_content' => $postData['o-module-comment:body'],
        ];
        if (!empty($postData['o-module-comment:website'])) {
            $data['comment_author_url'] = $postData['o-module-comment:website'];
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
    protected function notifyEmail(AbstractResourceEntityRepresentation $resource, CommentRepresentation $comment)
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

    /**
     * Return a message of error.
     *
     * @param string $message
     * @param int $statusCode
     * @param array $messages
     * @return \Laminas\View\Model\JsonModel
     */
    protected function jsonError($message, $statusCode = Response::STATUS_CODE_400, array $messages = [])
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $output = ['error' => $message];
        if ($messages) {
            $output['messages'] = $messages;
        }
        return new JsonModel($output);
    }
}
