<?php
namespace Comment\Service\Form;

use Comment\Form\CommentForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new CommentForm(null, $options);
        $viewHelperManager = $services->get('ViewHelperManager');
        $form->setSettingHelper($viewHelperManager->get('setting'));
        $form->setUrlHelper($viewHelperManager->get('Url'));
        $form->setFormElementManager($services->get('FormElementManager'));
        return $form;
    }
}
