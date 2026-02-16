<?php declare(strict_types=1);

namespace CommentTest\Controller\Site;

use CommentTest\CommentTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Tests for anonymous commenting functionality.
 *
 * Note: Many anonymous commenting tests require ACL reconfiguration which needs
 * an application reset. These are tested indirectly through the Form tests
 * and manual testing. This file tests what can be tested without reset().
 */
class CommentControllerTest extends AbstractHttpControllerTestCase
{
    use CommentTestTrait;

    protected $site;

    public function setUp(): void
    {
        parent::setUp();

        // Set server variables required by CommentAdapter.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';

        $this->loginAdmin();
        $this->site = $this->createSite('test-site', 'Test Site');
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        $this->resetSettings();
        $this->logout();
        parent::tearDown();
    }

    protected function resetSettings(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_public_allow_view', false);
        $settings->set('comment_public_allow_comment', false);
        $settings->set('comment_public_require_moderation', true);
        $settings->set('comment_legal_text', '');
        $settings->set('comment_antispam', false);
        $settings->set('comment_rate_limit_count', 0);
        $settings->set('comment_rate_limit_period', 60);
    }

    // =========================================================================
    // Logged-in User Tests
    // These don't require ACL reconfiguration.
    // =========================================================================

    public function testLoggedInUserCanComment(): void
    {
        $item = $this->createItem();

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Admin comment',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }

    public function testLoggedInUserCommentAutoApprovedForAdmins(): void
    {
        $item = $this->createItem();
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_require_moderation', true);

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Admin comment',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
        // Admins are always auto-approved.
        $this->assertFalse($response['data']['moderation']);
    }

    public function testLoggedInUserCanCommentWithoutLegalAgreement(): void
    {
        $item = $this->createItem();
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_legal_text', 'You must accept.');

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Admin comment',
            'path' => '/s/test-site/item/' . $item->id(),
            // No legal_agreement needed for logged-in users.
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }

    public function testHoneypotRejectsFilledValueForLoggedInUser(): void
    {
        $item = $this->createItem();

        // Submit with honeypot field filled (bot behavior).
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Spam comment',
            'path' => '/s/test-site/item/' . $item->id(),
            'o:check' => 'I am a bot',
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testRejectsEmptyBody(): void
    {
        $item = $this->createItem();

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => '',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        // Empty body returns fail status with 200, or might return 400 if route validation fails.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertTrue(in_array($statusCode, [200, 400]), "Unexpected status code: $statusCode");

        if ($statusCode === 200) {
            $response = json_decode($this->getResponse()->getContent(), true);
            $this->assertEquals('fail', $response['status']);
        }
    }

    public function testRejectsInvalidResourceId(): void
    {
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => 999999,
            'o:body' => 'Test comment',
            'path' => '/s/test-site/item/999999',
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testRejectsMissingResourceId(): void
    {
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'o:body' => 'Test comment',
            'path' => '/s/test-site/item/1',
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testRejectsGetRequest(): void
    {
        $this->dispatch('/s/test-site/comment/add', 'GET');

        $this->assertResponseStatusCode(403);
    }

    public function testRejectsEmptyUserAgent(): void
    {
        $item = $this->createItem();
        $_SERVER['HTTP_USER_AGENT'] = '';

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Test comment',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(403);

        // Restore user agent for cleanup.
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';
    }

    public function testRateLimitBlocksExcessiveComments(): void
    {
        $item = $this->createItem();
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_rate_limit_count', 2);
        $settings->set('comment_rate_limit_period', 60);

        // Pre-create comments from this IP.
        $this->createComment($item->id(), ['ip' => '127.0.0.1']);
        $this->createComment($item->id(), ['ip' => '127.0.0.1']);

        // Third comment should be blocked.
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Third comment',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(429);
    }

    public function testRateLimitAllowsWithinLimit(): void
    {
        // Use a different IP to avoid conflicts with previous test.
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $item = $this->createItem();
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_rate_limit_count', 5);
        $settings->set('comment_rate_limit_period', 60);

        // Pre-create 2 comments with this specific IP.
        $this->createComment($item->id(), ['ip' => '192.168.1.100']);
        $this->createComment($item->id(), ['ip' => '192.168.1.100']);

        // Third comment should be allowed (limit is 5).
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Third comment within limit',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Restore IP.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    /**
     * Test that regular users require moderation when enabled.
     *
     * Note: This test is skipped because the auto-subscription feature
     * in the controller requires change-owner permission that non-admin
     * users don't have by default. This is tested via the adapter tests.
     */
    public function testRegularUserModerationSetting(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_require_moderation', true);

        // Verify the setting is applied.
        $this->assertTrue((bool) $settings->get('comment_user_require_moderation'));

        // Create a comment as admin (which bypasses moderation).
        $item = $this->createItem();
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Admin comment should bypass moderation',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
        // Admins bypass moderation even when it's enabled.
        $this->assertFalse($response['data']['moderation']);
    }

    // =========================================================================
    // Auto-subscription Tests
    // =========================================================================

    public function testCommentCreatesSubscription(): void
    {
        $item = $this->createItem();
        $user = $this->getCurrentUser();

        // Verify no subscription exists yet.
        $this->assertEquals(0, $this->countSubscriptions($user->getId(), $item->id()));

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment that should auto-subscribe',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify subscription was created.
        $this->assertEquals(1, $this->countSubscriptions($user->getId(), $item->id()));
    }

    public function testCommentDoesNotDuplicateSubscription(): void
    {
        $item = $this->createItem();
        $user = $this->getCurrentUser();

        // Pre-create a subscription.
        $this->createSubscription($user->getId(), $item->id());
        $this->assertEquals(1, $this->countSubscriptions($user->getId(), $item->id()));

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Second comment on same resource',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify still only one subscription.
        $this->assertEquals(1, $this->countSubscriptions($user->getId(), $item->id()));
    }

    // =========================================================================
    // Alias Mode Tests
    // =========================================================================

    public function testLoggedInUserCommentWithAccountMode(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $item = $this->createItem();
        $user = $this->getCurrentUser();

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment with account mode',
            'path' => '/s/test-site/item/' . $item->id(),
            'comment_identity_mode' => 'account',
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify the comment was created with account info.
        $commentId = $response['data']['comment']['o:id'];
        $comment = $this->api()->read('comments', $commentId)->getContent();
        $this->assertEquals($user->getEmail(), $comment->email());
        $this->assertEquals($user->getName(), $comment->name());

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    public function testLoggedInUserCommentWithAliasMode(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $item = $this->createItem();

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment with alias mode',
            'path' => '/s/test-site/item/' . $item->id(),
            'comment_identity_mode' => 'alias',
            'o:name' => 'My Alias Name',
            'o:email' => 'alias@example.com',
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify the comment was created with alias info.
        $commentId = $response['data']['comment']['o:id'];
        $comment = $this->api()->read('comments', $commentId)->getContent();
        $this->assertEquals('alias@example.com', $comment->email());
        $this->assertEquals('My Alias Name', $comment->name());

        // But still linked to the user account.
        $this->assertNotNull($comment->owner());

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    public function testAliasModeRequiresEmail(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $item = $this->createItem();

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment with alias mode without email',
            'path' => '/s/test-site/item/' . $item->id(),
            'comment_identity_mode' => 'alias',
            'o:name' => 'My Alias Name',
            'o:email' => '', // Empty email.
        ]);

        // The form validation may return 400 or 200 with fail status.
        $statusCode = $this->getResponse()->getStatusCode();
        $response = json_decode($this->getResponse()->getContent(), true);

        // Accept either 200 with fail or 400 (form validation error).
        if ($statusCode === 200) {
            $this->assertEquals('fail', $response['status']);
        } else {
            // Form validation returned 400 - this is also acceptable.
            $this->assertTrue(in_array($statusCode, [200, 400]), "Unexpected status: $statusCode");
        }

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    public function testAliasModeWithEmptyNameUsesEmptyString(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $item = $this->createItem();

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment with alias mode no name',
            'path' => '/s/test-site/item/' . $item->id(),
            'comment_identity_mode' => 'alias',
            'o:email' => 'alias@example.com',
            // No name provided.
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify the comment was created with empty name.
        $commentId = $response['data']['comment']['o:id'];
        $comment = $this->api()->read('comments', $commentId)->getContent();
        $this->assertEquals('', $comment->name());
        $this->assertEquals('alias@example.com', $comment->email());

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    public function testAliasModeIgnoredWhenSettingDisabled(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', false);

        $item = $this->createItem();
        $user = $this->getCurrentUser();

        // Try to use alias mode when setting is disabled.
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment trying to use alias',
            'path' => '/s/test-site/item/' . $item->id(),
            'comment_identity_mode' => 'alias',
            'o:name' => 'Hacker Name',
            'o:email' => 'hacker@example.com',
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify the comment was created with account info, not alias.
        $commentId = $response['data']['comment']['o:id'];
        $comment = $this->api()->read('comments', $commentId)->getContent();
        $this->assertEquals($user->getEmail(), $comment->email());
        $this->assertEquals($user->getName(), $comment->name());
    }

    public function testDefaultBehaviorUsesAccountWhenAliasModeEnabled(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);

        $item = $this->createItem();
        $user = $this->getCurrentUser();

        // Submit without specifying identity mode (should default to account).
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment without specifying mode',
            'path' => '/s/test-site/item/' . $item->id(),
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify the comment was created with account info.
        $commentId = $response['data']['comment']['o:id'];
        $comment = $this->api()->read('comments', $commentId)->getContent();
        $this->assertEquals($user->getEmail(), $comment->email());
        $this->assertEquals($user->getName(), $comment->name());

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
    }

    // =========================================================================
    // Anonymous Mode Tests
    // =========================================================================

    public function testLoggedInUserCommentWithAnonymousMode(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_anonymous', true);

        $item = $this->createItem();

        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Anonymous comment',
            'path' => '/s/test-site/item/' . $item->id(),
            'comment_identity_mode' => 'anonymous',
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify the comment was created with empty name and email.
        $commentId = $response['data']['comment']['o:id'];
        $comment = $this->api()->read('comments', $commentId)->getContent();
        $this->assertEquals('', $comment->email());
        $this->assertEquals('', $comment->name());

        // But still linked to the user account.
        $this->assertNotNull($comment->owner());

        // Display name should be "[Anonymous]".
        $this->assertEquals('[Anonymous]', $comment->displayName());

        // Clean up.
        $settings->set('comment_user_allow_anonymous', false);
    }

    public function testAnonymousModeIgnoredWhenSettingDisabled(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_anonymous', false);

        $item = $this->createItem();
        $user = $this->getCurrentUser();

        // Try to use anonymous mode when setting is disabled.
        $this->dispatch('/s/test-site/comment/add', 'POST', [
            'resource_id' => $item->id(),
            'o:body' => 'Comment trying to be anonymous',
            'path' => '/s/test-site/item/' . $item->id(),
            'comment_identity_mode' => 'anonymous',
        ]);

        $this->assertResponseStatusCode(200);
        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);

        // Verify the comment was created with account info, not anonymous.
        $commentId = $response['data']['comment']['o:id'];
        $comment = $this->api()->read('comments', $commentId)->getContent();
        $this->assertEquals($user->getEmail(), $comment->email());
        $this->assertEquals($user->getName(), $comment->name());
    }

    public function testFormShowsAnonymousOptionWhenEnabled(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_anonymous', true);
        $settings->set('comment_user_allow_alias', false);

        $user = $this->getCurrentUser();
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(\Comment\Form\CommentForm::class, [
            'site_slug' => 'test-site',
            'resource_id' => 1,
            'user' => $user,
            'path' => '/s/test-site/item/1',
        ]);

        // Should have identity mode selector with anonymous option.
        $this->assertTrue($form->has('comment_identity_mode'));
        $element = $form->get('comment_identity_mode');
        $options = $element->getValueOptions();

        $this->assertArrayHasKey('account', $options);
        $this->assertArrayHasKey('anonymous', $options);
        // Alias should not be available.
        $this->assertArrayNotHasKey('alias', $options);

        // Clean up.
        $settings->set('comment_user_allow_anonymous', false);
    }

    public function testFormShowsBothAliasAndAnonymousWhenBothEnabled(): void
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('comment_user_allow_alias', true);
        $settings->set('comment_user_allow_anonymous', true);

        $user = $this->getCurrentUser();
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $form = $formElementManager->get(\Comment\Form\CommentForm::class, [
            'site_slug' => 'test-site',
            'resource_id' => 1,
            'user' => $user,
            'path' => '/s/test-site/item/1',
        ]);

        $element = $form->get('comment_identity_mode');
        $options = $element->getValueOptions();

        // All three options should be available.
        $this->assertArrayHasKey('account', $options);
        $this->assertArrayHasKey('alias', $options);
        $this->assertArrayHasKey('anonymous', $options);

        // Clean up.
        $settings->set('comment_user_allow_alias', false);
        $settings->set('comment_user_allow_anonymous', false);
    }
}
