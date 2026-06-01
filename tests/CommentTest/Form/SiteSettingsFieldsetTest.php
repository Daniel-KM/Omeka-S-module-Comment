<?php declare(strict_types=1);

namespace CommentTest\Form;

use Comment\Form\SiteSettingsFieldset;
use Laminas\Form\Form;
use Omeka\Test\AbstractHttpControllerTestCase;

class SiteSettingsFieldsetTest extends AbstractHttpControllerTestCase
{
    protected $fieldset;

    public function setUp(): void
    {
        parent::setUp();

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $this->fieldset = $formElementManager->get(SiteSettingsFieldset::class);
    }

    public function testFieldsetHasCorrectLabel(): void
    {
        $this->assertEquals('Comments', $this->fieldset->getLabel());
    }

    public function testFieldsetHasIdAttribute(): void
    {
        $this->assertEquals('comment', $this->fieldset->getAttribute('id'));
    }

    public function testFieldsetHasElementGroups(): void
    {
        $elementGroups = $this->fieldset->getOption('element_groups');
        $this->assertIsArray($elementGroups);
        $this->assertArrayHasKey('comment', $elementGroups);
    }

    public function testFieldsetHasPlacementSubscriptionElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_placement_subscription'));
        $element = $this->fieldset->get('comment_placement_subscription');
        $this->assertEquals('Display subscription button', $element->getLabel());

        $valueOptions = $element->getValueOptions();
        $this->assertArrayHasKey('before/items', $valueOptions);
        $this->assertArrayHasKey('after/items', $valueOptions);
    }

    public function testFieldsetHasPlacementListElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_placement_list'));
        $element = $this->fieldset->get('comment_placement_list');
        $this->assertEquals('Display comments', $element->getLabel());
    }

    public function testFieldsetHasPlacementFormElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_placement_form'));
        $element = $this->fieldset->get('comment_placement_form');
        $this->assertEquals('Display comment form', $element->getLabel());
    }

    public function testFieldsetHasLabelElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_label'));
        $element = $this->fieldset->get('comment_label');
        $this->assertEquals('Main label', $element->getLabel());
    }

    public function testFieldsetHasStructureElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_structure'));
        $element = $this->fieldset->get('comment_structure');
        $this->assertEquals('Structure', $element->getLabel());

        $valueOptions = $element->getValueOptions();
        $this->assertArrayHasKey('', $valueOptions);
        $this->assertArrayHasKey('flat', $valueOptions);
        $this->assertArrayHasKey('threaded', $valueOptions);
        $this->assertEquals('Use default', $valueOptions['']);
    }

    public function testFieldsetHasClosedOnLoadElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_closed_on_load'));
        $element = $this->fieldset->get('comment_closed_on_load');
        $this->assertEquals('Closed on load', $element->getLabel());

        $valueOptions = $element->getValueOptions();
        $this->assertArrayHasKey('', $valueOptions);
        $this->assertEquals('Use default', $valueOptions['']);
    }

    public function testFieldsetHasMaxLengthElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_max_length'));
        $element = $this->fieldset->get('comment_max_length');
        $this->assertEquals('Max length', $element->getLabel());
    }

    public function testFieldsetHasSkipGravatarElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_skip_gravatar'));
        $element = $this->fieldset->get('comment_skip_gravatar');
        $this->assertEquals('Display gravatar', $element->getLabel());

        $valueOptions = $element->getValueOptions();
        $this->assertArrayHasKey('', $valueOptions);
        $this->assertEquals('Use default', $valueOptions['']);
    }

    public function testFieldsetHasSubscribeButtonElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_subscribe_button'));
        $element = $this->fieldset->get('comment_subscribe_button');
        $this->assertEquals('Button to subscribe to comments', $element->getLabel());
    }

    public function testFieldsetCanBeAttachedToForm(): void
    {
        $form = new Form();
        $form->add($this->fieldset, ['name' => 'comment_site_settings']);

        $this->assertTrue($form->has('comment_site_settings'));
    }

    public function testFieldsetElementsHaveCorrectElementGroup(): void
    {
        $elements = [
            'comment_placement_subscription',
            'comment_placement_list',
            'comment_placement_form',
            'comment_label',
            'comment_structure',
            'comment_closed_on_load',
            'comment_max_length',
            'comment_skip_gravatar',
            'comment_subscribe_button',
        ];

        foreach ($elements as $name) {
            $element = $this->fieldset->get($name);
            $this->assertEquals('comment', $element->getOption('element_group'), "Element $name should have 'comment' as element_group");
        }
    }
}
