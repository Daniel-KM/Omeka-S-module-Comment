<?php
namespace Comment\Form;

use Zend\Form\Element;
use Zend\Form\Fieldset;

class SiteSettingsFieldset extends Fieldset
{
    protected $label = 'Comment'; // @translate

    public function init()
    {
        $this
            ->add([
                'name' => 'comment_append_item_set_show',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Append automatically to item set page', // @translate
                    'info' => 'If unchecked, the comments can be added via the helper in the theme in any page.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment_append_item_set_show',
                ],
            ])
            ->add([
                'name' => 'comment_append_item_show',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Append automatically to item page', // @translate
                    'info' => 'If unchecked, the comments can be added via the helper in the theme in any page.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment_append_item_show',
                ],
            ])
            ->add([
                'name' => 'comment_append_media_show',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Append automatically to media page', // @translate
                    'info' => 'If unchecked, the comments can be added via the helper in the theme in any page.', // @translate
                ],
                'attributes' => [
                    'id' => 'comment_append_media_show',
                ],
            ]);
    }
}
