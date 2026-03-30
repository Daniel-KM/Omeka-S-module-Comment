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

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->logger = $services->get('Omeka\Logger');
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');

        $type = $this->getArg('type');
        $commentId = $this->getArg('comment_id');
        $resourceId = $this->getArg('resource_id');

        if (!$type || !$commentId || !$resourceId) {
            $this->logger->err('SendNotifications job: missing required arguments.');
            return;
        }

        // Retrieve the comment.
        try {
            $comment = $api->read('comments', $commentId)->getContent();
        } catch (\Throwable $e) {
            $this->logger->err(
                'SendNotifications job: comment #{comment_id} not found.', // @translate
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
                'SendNotifications job: resource #{resource_id} not found.', // @translate
                ['resource_id' => $resourceId]
            );
            return;
        }

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
                    'SendNotifications job: unknown notification type "{type}".', // @translate
                    ['type' => $type]
                );
        }
    }

    /**
     * Notify subscribers when a comment is approved.
     */
    protected function notifySubscribers($comment, $resource): void
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');
        $translator = $services->get('MvcTranslator');
        $mailer = $services->get('Omeka\Mailer');

        $subscriptions = $api
            ->search('comment_subscriptions', ['resource_id' => $resource->id()], ['initialize' => false, 'finalize' => false, 'responseContent' => 'resource'])
            ->getContent();

        if (!$subscriptions) {
            return;
        }

        $siteName = $settings->get('installation_title');
        $siteSlug = $this->getArg('site_slug');
        $resourceUrl = $siteSlug
            ? $resource->siteUrl($siteSlug, true)
            : $resource->apiUrl();

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

        foreach ($subscriptions as $subscription) {
            $user = $subscription->getOwner();
            if (!$user) {
                continue;
            }
            try {
                $message = $mailer->createMessage();
                $message->addTo($user->getEmail(), $user->getName())
                    ->setSubject($subject)
                    ->setBody($body);
                $mailer->send($message);
            } catch (\Throwable $e) {
                $this->logger->err(
                    'SendNotifications job: failed to send email to {email}: {error}', // @translate
                    ['email' => $user->getEmail(), 'error' => $e->getMessage()]
                );
            }
        }

        $this->logger->info(
            'SendNotifications job: sent subscriber notifications for comment #{comment_id}.', // @translate
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
        $mailer = $services->get('Omeka\Mailer');

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

                Review at: {resource_url}
                MAIL; // @translate

        $placeholders = [
            'site_name' => $siteName,
            'resource_id' => $resource->id(),
            'resource_title' => (string) $resource->displayTitle(),
            'resource_url' => $resource->adminUrl(),
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

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                $message = $mailer->createMessage();
                $message->addTo($email)
                    ->setSubject($subject)
                    ->setBody($body);
                $mailer->send($message);
            } catch (\Throwable $e) {
                $this->logger->err(
                    'SendNotifications job: failed to send email to {email}: {error}', // @translate
                    ['email' => $email, 'error' => $e->getMessage()]
                );
            }
        }

        $this->logger->info(
            'SendNotifications job: sent moderator notifications for comment #{comment_id}.', // @translate
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
        $mailer = $services->get('Omeka\Mailer');

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
            'admin_url' => $comment->adminUrl(),
        ];

        $subject = new PsrMessage($subjectTemplate, $placeholders);
        $subject = (string) $subject->setTranslator($translator);

        $body = new PsrMessage($bodyTemplate, $placeholders);
        $body = (string) $body->setTranslator($translator);

        $emails = is_array($notifyEmails)
            ? $notifyEmails
            : array_filter(array_map('trim', explode("\n", $notifyEmails)));

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                $message = $mailer->createMessage();
                $message->addTo($email)
                    ->setSubject($subject)
                    ->setBody($body);
                $mailer->send($message);
            } catch (\Throwable $e) {
                $this->logger->err(
                    'SendNotifications job: failed to send email to {email}: {error}', // @translate
                    ['email' => $email, 'error' => $e->getMessage()]
                );
            }
        }

        $this->logger->info(
            'SendNotifications job: sent flagged comment notifications for comment #{comment_id}.', // @translate
            ['comment_id' => $comment->id()]
        );
    }
}
