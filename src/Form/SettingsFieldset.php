<?php declare(strict_types=1);

namespace Comment\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Comments'; // @translate

    protected $elementGroups = [
        'comment' => 'Comments', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'comment')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'comment_public_allow_view',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Allow public to view comments', // @translate
                    'info' => 'If unchecked, comments will be displayed only in admin pages.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_public_allow_comment',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Allow public to comment', // @translate
                    'info' => 'Allows everyone to comment, including non-registered users.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_public_require_moderation',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Require moderation for public comments', // @translate
                    'info' => 'If unchecked, comments will appear immediately.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_user_require_moderation',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Require moderation for non-admin users', // @translate
                    'info' => 'If unchecked, comments will appear immediately.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_public_notify_post',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Notify public comments by email', // @translate
                    'info' => 'The list of emails to notify when a comment is posted or flagged, one by row.', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'placeholder' => <<<'TXT'
                        contact@example.org
                        info@example2.org
                        TXT,
                    'rows' => 5,
                ],
            ])

            ->add([
                'name' => 'comment_wpapi_key',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'WordPress API key for Akismet if installed', // @translate
                    'info' => 'This feature requires the dependency package "zendframework/zendservice-akismet" that is not installed automatically.',
                ],
            ])

            ->add([
                'name' => 'comment_antispam',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Simple antispam', // @translate
                    'info' => 'If checked, a simple antispam (an addition of two digits) will be added for anonymous people if ReCaptcha is not set.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_label',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Main label', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_structure',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Structure', // @translate
                    'value_options' => [
                        'flat' => 'Flat (by date)', // @translate
                        'threaded' => 'Threaded (by conversation)' // @translate
                    ],
                ],
            ])
            ->add([
                'name' => 'comment_closed_on_load',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Closed on load', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_max_length',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Max length', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_skip_gravatar',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Display gravatar', // @translate
                    'value_options' => [
                        // Warning, the option is the inverse of the label.
                        '1' => 'No', // @translate
                        '0' => 'Yes', // @translate
                    ],
                ],
            ])
            ->add([
                'name' => 'comment_legal_text',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Legal agreement', // @translate
                    'info' => 'This text will be shown beside the legal checkbox. Let empty if you don’t want to use a legal agreement.', // @translate
                ],
            ])
        ;
    }
}
