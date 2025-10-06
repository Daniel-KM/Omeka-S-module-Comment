<?php declare(strict_types=1);

namespace Comment\Service\ViewHelper;

use Comment\View\Helper\CommentsSearchForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentsSearchFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formElementManager = $services->get('FormElementManager');

        return new CommentsSearchForm(
            $formElementManager->get(\Comment\Form\CommentsSearchForm::class)
        );
    }
}
