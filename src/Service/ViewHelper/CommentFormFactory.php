<?php declare(strict_types=1);

namespace Comment\Service\ViewHelper;

use Comment\View\Helper\CommentForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new CommentForm(
            $services->get('FormElementManager')
        );
    }
}
