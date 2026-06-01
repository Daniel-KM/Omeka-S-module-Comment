<?php declare(strict_types=1);

namespace CommentTest\Form;

use Comment\Form\SettingsFieldset;
use Laminas\Form\Form;
use Omeka\Test\AbstractHttpControllerTestCase;

class SettingsFieldsetTest extends AbstractHttpControllerTestCase
{
    protected $fieldset;

    public function setUp(): void
    {
        parent::setUp();

        $services = $this->getApplication()->getServiceManager();
        $formElementManager = $services->get('FormElementManager');

        $this->fieldset = $formElementManager->get(SettingsFieldset::class);
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

    public function testFieldsetHasPublicAllowViewElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_public_allow_view'));
        $element = $this->fieldset->get('comment_public_allow_view');
        $this->assertEquals('Allow public to view comments', $element->getLabel());
    }

    public function testFieldsetHasPublicAllowCommentElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_public_allow_comment'));
        $element = $this->fieldset->get('comment_public_allow_comment');
        $this->assertEquals('Allow public to comment', $element->getLabel());
    }

    public function testFieldsetHasPublicRequireModerationElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_public_require_moderation'));
        $element = $this->fieldset->get('comment_public_require_moderation');
        $this->assertEquals('Require moderation for public comments', $element->getLabel());
    }

    public function testFieldsetHasUserRequireModerationElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_user_require_moderation'));
        $element = $this->fieldset->get('comment_user_require_moderation');
        $this->assertEquals('Require moderation for non-admin users', $element->getLabel());
    }

    public function testFieldsetHasUserAllowEditElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_user_allow_edit'));
        $element = $this->fieldset->get('comment_user_allow_edit');
        $this->assertEquals('Allow non-admin users to edit or delete their own comment', $element->getLabel());
    }

    public function testFieldsetHasAntispamElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_antispam'));
        $element = $this->fieldset->get('comment_antispam');
        $this->assertEquals('Simple antispam', $element->getLabel());
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
        $this->assertArrayHasKey('flat', $valueOptions);
        $this->assertArrayHasKey('threaded', $valueOptions);
    }

    public function testFieldsetHasMaxLengthElement(): void
    {
        $this->assertTrue($this->fieldset->has('comment_max_length'));
        $element = $this->fieldset->get('comment_max_length');
        $this->assertEquals('Max length', $element->getLabel());
    }

    public function testFieldsetCanBeAttachedToForm(): void
    {
        $form = new Form();
        $form->add($this->fieldset, ['name' => 'comment_settings']);

        $this->assertTrue($form->has('comment_settings'));
    }

    public function testFieldsetElementsHaveCorrectElementGroup(): void
    {
        $elements = [
            'comment_public_allow_view',
            'comment_public_allow_comment',
            'comment_public_require_moderation',
            'comment_user_require_moderation',
            'comment_user_allow_edit',
            'comment_antispam',
            'comment_label',
            'comment_structure',
            'comment_max_length',
        ];

        foreach ($elements as $name) {
            $element = $this->fieldset->get($name);
            $this->assertEquals('comment', $element->getOption('element_group'), "Element $name should have 'comment' as element_group");
        }
    }
}
