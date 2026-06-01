<?php declare(strict_types=1);

namespace Comment\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Form;

class QuickSearchForm extends Form
{
    public function init(): void
    {
        $this->setAttribute('method', 'get');
        $this->setAttribute('id', 'quick-search-form');

        // No csrf: see main search form.
        $this->remove('csrf');

        $this
            ->add([
                'name' => 'body',
                'type' => Element\Search::class,
                'options' => [
                    'label' => 'Body', // @translate
                ],
                'attributes' => [
                    'id' => 'body',
                ],
            ])
            ->add([
                'name' => 'email',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Email', // @translate
                ],
                'attributes' => [
                    'id' => 'email',
                ],
            ])
            ->add([
                'name' => 'owner_id',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'User by id', // @translate
                ],
                'attributes' => [
                    'id' => 'owner_id',
                ],
            ])
            ->add([
                'name' => 'resource_id',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Resource by id', // @translate
                ],
                'attributes' => [
                    'id' => 'resource_id',
                ],
            ])
            ->add([
                'name' => 'resource_type',
                'type' => CommonElement\OptionalSelect::class,
                'options' => [
                    'label' => 'Resource type', // @translate
                    'value_options' => [
                        '' => 'All', // @translate
                        'resources' => 'All resources', // @translate
                        'items' => 'Items', // @translate
                        'item_sets' => 'Item sets', // @translate
                        'media' => 'Media', // @translate
                    ] + (class_exists(\DigitalObject\Entity\DigitalObject::class)
                        ? ['digital_objects' => 'Digital objects'] // @translate
                        : []),
                ],
                'attributes' => [
                    'id' => 'resource_type',
                ],
            ])
            ->add([
                'name' => 'has_resource',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Has resource', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'has_resource',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'approved',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Approved', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'approved',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'flagged',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Flagged', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'flagged',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'spam',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'label' => 'Spam', // @translate
                    'label_attributes' => [
                        'style' => 'display: inline; margin-right: 1em;',
                    ],
                    'value_options' => [
                        '' => 'All', // @translate
                        '0' => 'No', // @translate
                        '1' => 'Yes', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'spam',
                    'value' => '',
                ],
            ])
            ->add([
                'name' => 'submit',
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'submit',
                    'type' => 'submit',
                    'class' => 'button',
                ],
            ]);
    }
}
