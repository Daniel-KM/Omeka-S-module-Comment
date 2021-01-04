<?php declare(strict_types=1);
namespace Comment;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.1.5', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `comment` CHANGE `user_agent` `user_agent` text NOT NULL;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.1.11', '<')) {
    $sql = <<<'SQL'
DELETE FROM site_setting
WHERE id IN ("comment_append_item_set_show", "comment_append_item_show", "comment_append_media_show");
SQL;
    $connection->exec($sql);
}
