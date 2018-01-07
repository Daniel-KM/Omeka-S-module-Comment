<?php
namespace Comment\Form;

use Zend\Form\Element\Checkbox;
use Zend\Form\Form;

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
