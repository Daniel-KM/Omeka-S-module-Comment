<?php declare(strict_types=1);

namespace Comment\Job;

use Common\Stdlib\PsrMessage;
use Omeka\Job\AbstractJob;

/**
 * Job to send comment notifications asynchronously.
 *
 * Supports three notification types:
 * - 'subscribers': Notify users subscribed to a resource when a comment is approved
 * - 'moderators': Notify moderators when a new comment is posted
 * - 'flagged': Notify moderators when a comment is flagged
 */
class SendNotifications extends AbstractJob
{
    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Common\Mvc\Controller\Plugin\SendEmail
     */
    protected $sendEmail;

    /**
     * Scheme + host (e.g. "https://example.org") used to make any relative URL
     * absolute. Resolved once via the ServerUrl view helper, with a fallback on
     * settings when the helper has no host (CLI without --server-url, or sync
     * dispatch from a request where HTTP_HOST is missing).
     */
    protected ?string $serverUrlBase = null;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('comment/send-notifications/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');
        // SendEmail only depends on injected services, so it can be used out of
        // a controller (here in a job).
        $this->sendEmail = $services->get('ControllerPluginManager')->get('sendEmail');

        $type = $this->getArg('type');
        $commentId = $this->getArg('comment_id');
        $resourceId = $this->getArg('resource_id');

        if (!$type || !$commentId || !$resourceId) {
            $this->logger->err('Missing required arguments.'); // @translate
            return;
        }

        // Retrieve the comment.
        try {
            $comment = $api->read('comments', $commentId)->getContent();
        } catch (\Throwable $e) {
            $this->logger->err(
                'Comment #{comment_id} not found.', // @translate
                ['comment_id' => $commentId]
            );
            return;
        }

        // Skip notifications for deleted comments.
        if ($comment->isDeleted()) {
            return;
        }

        // Retrieve the resource.
        try {
            $resource = $api->read('resources', $resourceId)->getContent();
        } catch (\Throwable $e) {
            $this->logger->err(
                'Resource #{resource_id} not found.', // @translate
                ['resource_id' => $resourceId]
            );
            return;
        }

        $this->initServerUrlBase();

        switch ($type) {
            case 'subscribers':
                $this->notifySubscribers($comment, $resource);
                break;
            case 'moderators':
                $this->notifyModerators($comment, $resource);
                break;
            case 'flagged':
                $this->notifyFlagged($comment, $resource);
                break;
            default:
                $this->logger->err(
                    'Unknown notification type "{type}".', // @translate
                    ['type' => $type]
                );
        }
    }

    /**
     * Resolve the reply-to address. The from stays the unique installation
     * sender (managed by Common\SendEmail). When a reply from the commenter is
     * expected (moderator notifications), the reply-to is the commenter, else
     * the configured support address, else the administrator.
     */
    protected function resolveReplyTo($comment, bool $preferCommenter): ?array
    {
        if ($preferCommenter) {
            $email = $comment->email();
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [$email => (string) $comment->name()];
            }
        }
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $support = $settings->get('comment_reply_to_email')
            ?: $settings->get('administrator_email');
        return $support ? [$support => ''] : null;
    }

    /**
     * Notify subscribers when a comment is approved.
     */
    protected function notifySubscribers($comment, $resource): void
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $settings = $services->get('Omeka\Settings');
        $translator = $services->get('MvcTranslator');

        // Query entities directly: the job may run as the comment owner
        // (anonymous or guest) who is not allowed to search subscriptions.
        $subscriptions = $entityManager
            ->getRepository(\Comment\Entity\CommentSubscription::class)
            ->findBy(['resource' => $resource->id()]);

        if (!$subscriptions) {
            return;
        }

        $siteName = $settings->get('installation_title');
        $siteSlug = $this->getArg('site_slug');
        $resourceUrl = $this->absoluteUrl($siteSlug
            ? $resource->siteUrl($siteSlug, true)
            : $resource->apiUrl());

        $subjectTemplate = $settings->get('comment_email_subscriber_subject')
            ?: '[{site_name}] New comment'; // @translate
        $bodyTemplate = $settings->get('comment_email_subscriber_body')
            ?: <<<'MAIL'
                Hi,

                A new comment was published for resource #{resource_id} ({resource_title}).

                You can see it at {resource_url}#comments.

                Sincerely,
                MAIL; // @translate

        $placeholders = [
            'site_name' => $siteName,
            'resource_id' => $resource->id(),
            'resource_title' => (string) $resource->displayTitle(),
            'resource_url' => $resourceUrl,
            'comment_author' => $comment->name() ?: 'Anonymous',
            'comment_body' => $comment->body(),
        ];

        $subject = new PsrMessage($subjectTemplate, $placeholders);
        $subject = (string) $subject->setTranslator($translator);

        $body = new PsrMessage($bodyTemplate, $placeholders);
        $body = (string) $body->setTranslator($translator);

        $replyTo = $this->resolveReplyTo($comment, false);

        foreach ($subscriptions as $subscription) {
            $user = $subscription->getOwner();
            if (!$user) {
                continue;
            }
            $result = $this->sendEmail->__invoke($body, $subject, [$user->getEmail() => (string) $user->getName()], null, null, null, $replyTo);
            if (!$result) {
                $this->logger->err(
                    'Failed to send email to {email}.', // @translate
                    ['email' => $user->getEmail()]
                );
            }
        }

        $this->logger->info(
            'Sent subscriber notifications for comment #{comment_id}.', // @translate
            ['comment_id' => $comment->id()]
        );
    }

    /**
     * Notify moderators when a comment is posted.
     */
    protected function notifyModerators($comment, $resource): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $translator = $services->get('MvcTranslator');

        $notifyEmails = $settings->get('comment_public_notify_post');
        if (!$notifyEmails) {
            return;
        }

        $siteName = $settings->get('installation_title');

        $subjectTemplate = $settings->get('comment_email_moderator_subject')
            ?: '[{site_name}] New public comment'; // @translate
        $bodyTemplate = $settings->get('comment_email_moderator_body')
            ?: <<<'MAIL'
                A comment was added to resource #{resource_id} ({resource_title}).

                Author: {comment_author} <{comment_email}>

                Comment:
                {comment_body}

                Public page: {resource_url}
                Admin page: {admin_url}
                MAIL; // @translate

        $siteSlug = $this->getArg('site_slug');
        $placeholders = [
            'site_name' => $siteName,
            'resource_id' => $resource->id(),
            'resource_title' => (string) $resource->displayTitle(),
            'resource_url' => $this->absoluteUrl($siteSlug
                ? $resource->siteUrl($siteSlug, true)
                : $resource->apiUrl()),
            'admin_url' => $this->absoluteUrl($resource->adminUrl(null, true)),
            'comment_author' => $comment->name() ?: 'Anonymous',
            'comment_email' => $comment->email() ?: 'N/A',
            'comment_body' => $comment->body(),
        ];

        $subject = new PsrMessage($subjectTemplate, $placeholders);
        $subject = (string) $subject->setTranslator($translator);

        $body = new PsrMessage($bodyTemplate, $placeholders);
        $body = (string) $body->setTranslator($translator);

        $emails = is_array($notifyEmails)
            ? $notifyEmails
            : array_filter(array_map('trim', explode("\n", $notifyEmails)));

        $replyTo = $this->resolveReplyTo($comment, true);

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $result = $this->sendEmail->__invoke($body, $subject, [$email => ''], null, null, null, $replyTo);
            if (!$result) {
                $this->logger->err(
                    'Failed to send email to {email}.', // @translate
                    ['email' => $email]
                );
            }
        }

        $this->logger->info(
            'Sent moderator notifications for comment #{comment_id}.', // @translate
            ['comment_id' => $comment->id()]
        );
    }

    /**
     * Notify moderators when a comment is flagged.
     */
    protected function notifyFlagged($comment, $resource): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $translator = $services->get('MvcTranslator');

        $notifyEmails = $settings->get('comment_public_notify_post');
        if (!$notifyEmails) {
            return;
        }

        $siteName = $settings->get('installation_title');

        $subjectTemplate = $settings->get('comment_email_flagged_subject')
            ?: '[{site_name}] Comment flagged for review'; // @translate
        $bodyTemplate = $settings->get('comment_email_flagged_body')
            ?: <<<'MAIL'
                A comment has been flagged for review.

                Resource: #{resource_id} ({resource_title})
                Author: {comment_author} <{comment_email}>

                Comment:
                {comment_body}

                Review at: {admin_url}
                MAIL; // @translate

        $placeholders = [
            'site_name' => $siteName,
            'resource_id' => $resource->id(),
            'resource_title' => (string) $resource->displayTitle(),
            'comment_author' => $comment->name() ?: 'Anonymous',
            'comment_email' => $comment->email() ?: 'N/A',
            'comment_body' => $comment->body(),
            'admin_url' => $this->absoluteUrl($resource->adminUrl(null, true)),
        ];

        $subject = new PsrMessage($subjectTemplate, $placeholders);
        $subject = (string) $subject->setTranslator($translator);

        $body = new PsrMessage($bodyTemplate, $placeholders);
        $body = (string) $body->setTranslator($translator);

        $emails = is_array($notifyEmails)
            ? $notifyEmails
            : array_filter(array_map('trim', explode("\n", $notifyEmails)));

        $replyTo = $this->resolveReplyTo($comment, true);

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $result = $this->sendEmail->__invoke($body, $subject, [$email => ''], null, null, null, $replyTo);
            if (!$result) {
                $this->logger->err(
                    'Failed to send email to {email}.', // @translate
                    ['email' => $email]
                );
            }
        }

        $this->logger->info(
            'Sent flagged comment notifications for comment #{comment_id}.', // @translate
            ['comment_id' => $comment->id()]
        );
    }

    /**
     * Resolve scheme + host for absolutizing URLs in mail bodies.
     */
    protected function initServerUrlBase(): void
    {
        $services = $this->getServiceLocator();
        $helpers = $services->get('ViewHelperManager');
        try {
            $serverUrl = $helpers->get('ServerUrl');
            $host = method_exists($serverUrl, 'getHost') ? (string) $serverUrl->getHost() : '';
            if ($host !== '') {
                $scheme = method_exists($serverUrl, 'getScheme') ? (string) $serverUrl->getScheme() : 'http';
                $port = method_exists($serverUrl, 'getPort') ? $serverUrl->getPort() : null;
                $base = $scheme . '://' . $host;
                if ($port && !in_array((int) $port, [80, 443], true)) {
                    $base .= ':' . $port;
                }
                $this->serverUrlBase = $base;
                return;
            }
        } catch (\Throwable $e) {
        }
        // Fallback on a configured base URL.
        $settings = $services->get('Omeka\Settings');
        $candidates = [
            $settings->get('comment_server_url'),
            $settings->get('installation_url'),
            $settings->get('default_site_url'),
        ];
        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }
            $parts = parse_url((string) $candidate);
            if (!empty($parts['scheme']) && !empty($parts['host'])) {
                $base = $parts['scheme'] . '://' . $parts['host'];
                if (!empty($parts['port']) && !in_array((int) $parts['port'], [80, 443], true)) {
                    $base .= ':' . $parts['port'];
                }
                $this->serverUrlBase = $base;
                return;
            }
        }
        $this->serverUrlBase = null;
    }

    /**
     * Make a URL absolute when scheme/host are missing.
     */
    protected function absoluteUrl(?string $url): string
    {
        $url = (string) $url;
        if ($url === '') {
            return '';
        }
        // Already absolute (scheme://host/...).
        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            return $url;
        }
        if ($this->serverUrlBase === null) {
            return $url;
        }
        // Handle "http:/path", "https:/path" produced by ServerUrl without
        // host.
        if (preg_match('~^([a-z][a-z0-9+.-]*):/(?!/)(.*)$~i', $url, $m)) {
            $url = '/' . ltrim($m[2], '/');
        }
        if ($url === '' || $url[0] !== '/') {
            $url = '/' . $url;
        }
        return $this->serverUrlBase . $url;
    }
}
