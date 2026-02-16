<?php declare(strict_types=1);

namespace CommentTest\View\Helper;

use Comment\Service\CommentCache;
use Comment\View\Helper\CommentSubscriptionButton;
use Omeka\Test\AbstractHttpControllerTestCase;

class CommentSubscriptionButtonTest extends AbstractHttpControllerTestCase
{
    public function tearDown(): void
    {
        CommentCache::clear();
        parent::tearDown();
    }

    public function testHelperHasDefaultPartialName(): void
    {
        $this->assertEquals('common/comment-subscription-button', CommentSubscriptionButton::PARTIAL_NAME);
    }

    public function testHelperIsRegistered(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $viewHelpers = $services->get('ViewHelperManager');

        $this->assertTrue($viewHelpers->has('commentSubscriptionButton'));
        $helper = $viewHelpers->get('commentSubscriptionButton');
        $this->assertInstanceOf(CommentSubscriptionButton::class, $helper);
    }

    public function testSubscriptionCacheWorks(): void
    {
        $this->assertFalse(CommentCache::hasSubscription(1, 999));

        CommentCache::setSubscription(1, 999, true);

        $this->assertTrue(CommentCache::hasSubscription(1, 999));
        $this->assertTrue(CommentCache::getSubscription(1, 999));
    }

    public function testSubscribeButtonDefaultIsFalse(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $config = $services->get('Config');
        $default = $config['comment']['settings']['comment_subscribe_button'] ?? null;
        $this->assertEquals('0', $default, 'The default value for comment_subscribe_button should be "0" (disabled).');
    }

    public function testSubscribeButtonSettingIsCheckbox(): void
    {
        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');
        $fieldset = $formElementManager->get(\Comment\Form\SiteSettingsFieldset::class);
        $element = $fieldset->get('comment_subscribe_button');
        $this->assertInstanceOf(\Laminas\Form\Element\Checkbox::class, $element);
    }
}
