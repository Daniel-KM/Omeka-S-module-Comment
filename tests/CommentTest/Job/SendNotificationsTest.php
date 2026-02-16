<?php declare(strict_types=1);

namespace CommentTest\Job;

use Comment\Job\SendNotifications;
use CommentTest\CommentTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for comment notification dispatch and SendNotifications job.
 *
 * Tests cover:
 * - Subscriber notification dispatched when comment is auto-approved on create.
 * - No notification dispatched when comment is updated without approving.
 * - Subscriber notification dispatched when comment is approved via update.
 * - Notification job includes site_slug.
 */
class SendNotificationsTest extends AbstractHttpControllerTestCase
{
    use CommentTestTrait;

    protected $site;

    public function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';

        $this->loginAdmin();
        $this->site = $this->createSite('test-notif', 'Test Notification Site');
    }

    public function tearDown(): void
    {
        // Always re-login as admin before cleanup to avoid permission issues.
        $this->loginAdmin();
        $this->cleanupResources();
        $this->resetSettings();
        $this->logout();
        parent::tearDown();
    }

    protected function resetSettings(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_require_moderation', false);
        $settings->set('comment_public_require_moderation', true);
        $settings->set('comment_public_notify_post', []);
    }

    /**
     * Count SendNotifications jobs in the database.
     */
    protected function countNotificationJobs(): int
    {
        $entityManager = $this->getEntityManager();
        $dql = 'SELECT COUNT(j.id) FROM Omeka\Entity\Job j WHERE j.class = :class';
        return (int) $entityManager->createQuery($dql)
            ->setParameter('class', SendNotifications::class)
            ->getSingleScalarResult();
    }

    // =========================================================================
    // Notification on auto-approved comment (create via controller)
    // =========================================================================

    public function testApprovedCommentDispatchesSubscriberNotification(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_require_moderation', false);

        $item = $this->createItem();
        $user = $this->getCurrentUser();

        // Create a subscription for the admin on this item.
        $this->createSubscription($user->getId(), $item->id());

        $jobCountBefore = $this->countNotificationJobs();

        // Post a comment (auto-approved for admin).
        $this->dispatch('/s/test-notif/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment that triggers notification',
            'path' => '/s/test-notif/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
        // Comment should be auto-approved.
        $this->assertFalse($response['data']['moderation']);

        // Verify a new SendNotifications job was dispatched.
        $jobCountAfter = $this->countNotificationJobs();
        $this->assertGreaterThan($jobCountBefore, $jobCountAfter, 'A SendNotifications job should have been dispatched for the approved comment.');

        // Verify the latest job has the correct arguments.
        $latestJob = $this->getLatestJob(SendNotifications::class);
        $this->assertNotNull($latestJob);
        $args = $latestJob->getArgs();
        $this->assertEquals('subscribers', $args['type']);
        $this->assertEquals($item->id(), $args['resource_id']);
    }

    // =========================================================================
    // Notification on approval via update (post-moderation)
    // =========================================================================

    public function testApprovingCommentDispatchesNotification(): void
    {
        $item = $this->createItem();

        // Create an unapproved comment via entity manager (bypasses events).
        $comment = $this->createComment($item->id(), [
            'approved' => false,
            'site_id' => $this->site->id(),
        ]);
        $this->assertFalse($comment->isApproved());

        $jobCountBefore = $this->countNotificationJobs();

        // Approve the comment via API update (triggers api.update.post event).
        $this->api()->update('comments', $comment->id(), [
            'o:approved' => true,
        ], [], ['isPartial' => true]);

        // A new SendNotifications job should be dispatched.
        $jobCountAfter = $this->countNotificationJobs();
        $this->assertGreaterThan($jobCountBefore, $jobCountAfter, 'A SendNotifications job should be dispatched when a comment is approved.');

        $latestJob = $this->getLatestJob(SendNotifications::class);
        $this->assertNotNull($latestJob);
        $args = $latestJob->getArgs();
        $this->assertEquals('subscribers', $args['type']);
        $this->assertEquals($comment->id(), $args['comment_id']);
        $this->assertEquals($item->id(), $args['resource_id']);
    }

    public function testUpdateWithoutApprovingDoesNotDispatchNotification(): void
    {
        $item = $this->createItem();

        // Create an already-approved comment via entity manager.
        $comment = $this->createComment($item->id(), ['approved' => true]);

        $jobCountBefore = $this->countNotificationJobs();

        // Update the comment body only (not the approved status).
        $this->api()->update('comments', $comment->id(), [
            'o:body' => 'Updated comment body',
        ], [], ['isPartial' => true]);

        // No new SendNotifications job should be dispatched.
        $jobCountAfter = $this->countNotificationJobs();
        $this->assertEquals($jobCountBefore, $jobCountAfter, 'No SendNotifications job should be dispatched when updating without approving.');
    }

    public function testSettingApprovedToFalseDoesNotDispatchNotification(): void
    {
        $item = $this->createItem();

        // Create an approved comment.
        $comment = $this->createComment($item->id(), ['approved' => true]);

        $jobCountBefore = $this->countNotificationJobs();

        // Unapprove the comment.
        $this->api()->update('comments', $comment->id(), [
            'o:approved' => false,
        ], [], ['isPartial' => true]);

        // No notification should be sent for unapproving.
        $jobCountAfter = $this->countNotificationJobs();
        $this->assertEquals($jobCountBefore, $jobCountAfter, 'No SendNotifications job should be dispatched when unapproving a comment.');
    }

    // =========================================================================
    // Notification includes site_slug
    // =========================================================================

    public function testNotificationJobIncludesSiteSlug(): void
    {
        $item = $this->createItem();

        // Create an unapproved comment with a site.
        $comment = $this->createComment($item->id(), [
            'approved' => false,
            'site_id' => $this->site->id(),
        ]);

        // Approve the comment.
        $this->api()->update('comments', $comment->id(), [
            'o:approved' => true,
        ], [], ['isPartial' => true]);

        $latestJob = $this->getLatestJob(SendNotifications::class);
        $this->assertNotNull($latestJob);
        $args = $latestJob->getArgs();
        $this->assertArrayHasKey('site_slug', $args);
        $this->assertEquals('test-notif', $args['site_slug']);
    }

    public function testNotificationJobHandlesNullSiteSlug(): void
    {
        $item = $this->createItem();

        // Create an unapproved comment WITHOUT a site.
        $comment = $this->createComment($item->id(), [
            'approved' => false,
        ]);

        // Approve the comment.
        $this->api()->update('comments', $comment->id(), [
            'o:approved' => true,
        ], [], ['isPartial' => true]);

        $latestJob = $this->getLatestJob(SendNotifications::class);
        $this->assertNotNull($latestJob);
        $args = $latestJob->getArgs();
        $this->assertArrayHasKey('site_slug', $args);
        $this->assertNull($args['site_slug']);
    }
}
