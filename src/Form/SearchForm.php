<?php
namespace Comment\Form;

use Laminas\Form\Element\Checkbox;
use Laminas\Form\Form;

class SearchForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'has_comments',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Has comments', // @translate
            ],
        ]);
    }
}
