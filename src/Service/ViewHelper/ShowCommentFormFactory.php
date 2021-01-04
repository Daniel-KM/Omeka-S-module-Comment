<?php
namespace Comment\Service\ViewHelper;

use Comment\View\Helper\ShowCommentForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ShowCommentFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formElementManager = $services->get('FormElementManager');
        return new ShowCommentForm($formElementManager);
    }
}
