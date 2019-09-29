<?php
namespace Comment\Form;

use Omeka\Form\Element\CkeditorInline;
use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'comment_resources',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Resources to comment', // @translate
                'info' => 'The type of resources for which comment is enabled.', // @translate
                'value_options' => [
                    'item_sets' => 'Item sets', // @translate
                    'items' => 'Items', // @translate
                    'media' => 'media', // @translate
                    // 'site_pages' => 'Site pages', // @translate
                ],
            ],
        ]);

        $this->add([
            'name' => 'comment_public_allow_view',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Allow public to view comments', // @translate
                'info' => 'If unchecked, comments will be displayed only in admin pages.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'comment_public_allow_comment',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Allow public to comment', // @translate
                'info' => 'Allows everyone to comment, including non-registered users.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'comment_public_require_moderation',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Require moderation for public comments', // @translate
                'info' => 'If unchecked, comments will appear immediately.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'comment_public_notify_post',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Notify public comments by email', // @translate
                'info' => 'The list of emails to notify when a comment is posted or flagged, one by row.', // @translate
            ],
            'attributes' => [
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'comment_comments_label',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Label for comments', // @translate
                'info' => 'A label to use, for example "Comments".', // @translate
            ],
        ]);

        $this->add([
            'name' => 'comment_threaded',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Use threaded comments', // @translate
                'info' => 'If checked, the replies will be displayed indented below the comment.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'comment_legal_text',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Legal agreement', // @translate
                'info' => 'This text will be shown beside the legal checkbox. Let empty if you don’t want to use a legal agreement.', // @translate
            ],
            'attributes' => [
                'id' => 'comment-legal-text',
            ],
        ]);

        $this->add([
            'name' => 'comment_wpapi_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'WordPress API key for Akismet if installed', // @translate
                'info' => 'This feature requires the dependency package "zendframework/zendservice-akismet" that is not installed automatically.',
            ],
        ]);

        $this->add([
            'name' => 'comment_antispam',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Simple antispam', // @translate
                'info' => 'If checked, a simple antispam (an addition of two digits) will be added for anonymous people if ReCaptcha is not set.', // @translate
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'comment_public_notify_post',
            'required' => false,
        ]);
    }
}
