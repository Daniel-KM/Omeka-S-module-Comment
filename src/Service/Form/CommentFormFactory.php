<?php declare(strict_types=1);

namespace Comment\Service\Form;

use Comment\Form\CommentForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $viewHelpers = $services->get('ViewHelperManager');

        $form = new CommentForm(null, $options ?? []);
        return $form
            ->setSettingHelper($viewHelpers->get('setting'))
            ->setUrlHelper($viewHelpers->get('Url'))
            ->setFormElementManager($services->get('FormElementManager'));
    }
}
