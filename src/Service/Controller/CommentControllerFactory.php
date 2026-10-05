<?php declare(strict_types=1);

namespace Comment\Service\Controller;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new $requestedName(
            $services->get('Comment\SpamChecker')
        );
    }
}
