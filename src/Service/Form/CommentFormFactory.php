<?php declare(strict_types=1);

namespace Comment\Service\Form;

use Comment\Form\CommentForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $viewHelperManager = $services->get('ViewHelperManager');
        $form = new CommentForm(null, $options ?? []);
        return $form
            ->setSettingHelper($viewHelperManager->get('setting'))
            ->setUrlHelper($viewHelperManager->get('Url'))
            ->setFormElementManager($services->get('FormElementManager'));
    }
}
