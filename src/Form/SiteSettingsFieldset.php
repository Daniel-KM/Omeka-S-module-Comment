<?php declare(strict_types=1);

namespace Comment\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;

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
                'name' => 'comment_list_open',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Open the comments by default', // @translate
                ],
                'attributes' => [
                    'id' => 'comment_list_open',
                ],
            ])
        ;
    }
}
