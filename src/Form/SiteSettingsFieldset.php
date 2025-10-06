<?php declare(strict_types=1);

namespace Comment\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SiteSettingsFieldset extends Fieldset
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
                'name' => 'comment_placement_subscription',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Display subscription button', // @translate
                    'value_options' => [
                        // 'block/items' => 'Items: Via resource block or custom theme', // @translate
                        // 'block/media' => 'Media: Via resource block or custom theme', // @translate
                        // 'block/item_sets' => 'Item set: Via resource block or custom theme', // @translate
                        'before/items' => 'Item: Top', // @translate
                        'before/media' => 'Media: Top', // @translate
                        'before/item_sets' => 'Item set: Top', // @translate
                        'after/items' => 'Item: Bottom', // @translate
                        'after/media' => 'Media: Bottom', // @translate
                        'after/item_sets' => 'Item set: Bottom', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'comment_placement_subscription',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'comment_placement_list',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Display comments', // @translate
                    'value_options' => [
                        // 'block/items' => 'Items: Via resource block or custom theme', // @translate
                        // 'block/media' => 'Media: Via resource block or custom theme', // @translate
                        // 'block/item_sets' => 'Item set: Via resource block or custom theme', // @translate
                        'before/items' => 'Item: Top', // @translate
                        'before/media' => 'Media: Top', // @translate
                        'before/item_sets' => 'Item set: Top', // @translate
                        'after/items' => 'Item: Bottom', // @translate
                        'after/media' => 'Media: Bottom', // @translate
                        'after/item_sets' => 'Item set: Bottom', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'comment_placement_list',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'comment_placement_form',
                'type' => CommonElement\OptionalMultiCheckbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Display comment form', // @translate
                    'value_options' => [
                        // 'block/items' => 'Items: Via resource block or custom theme', // @translate
                        // 'block/media' => 'Media: Via resource block or custom theme', // @translate
                        // 'block/item_sets' => 'Item set: Via resource block or custom theme', // @translate
                        'before/items' => 'Item: Top', // @translate
                        'before/media' => 'Media: Top', // @translate
                        'before/item_sets' => 'Item set: Top', // @translate
                        'after/items' => 'Item: Bottom', // @translate
                        'after/media' => 'Media: Bottom', // @translate
                        'after/item_sets' => 'Item set: Bottom', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'comment_placement_form',
                    'required' => false,
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
                        '' => 'Use default', // @translate
                        'flat' => 'Flat (by date)', // @translate
                        'threaded' => 'Threaded (by conversation)' // @translate
                    ],
                ],
            ])
            ->add([
                'name' => 'comment_closed_on_load',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Closed on load', // @translate
                    'value_options' => [
                        '' => 'Use default', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
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
                        '' => 'Use default', // @translate
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
                ],
            ])
            ->add([
                'name' => 'comment_subscribe_button',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Button to subscribe to comments', // @translate
                ],
            ])
        ;
    }
}
