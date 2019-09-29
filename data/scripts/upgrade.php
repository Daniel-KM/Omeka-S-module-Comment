<?php
namespace Comment;

/**
 * @var Module $this
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $oldVersion
 * @var string $newVersion
 */
$services = $serviceLocator;

/**
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var array $config
 * @var array $config
 * @var \Omeka\Mvc\Controller\Plugin\Api $api
 */
// $settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
// $config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
// $plugins = $services->get('ControllerPluginManager');
// $api = $plugins->get('api');

if (version_compare($oldVersion, '3.1.5', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `comment` CHANGE `user_agent` `user_agent` text NOT NULL;
SQL;
    $connection->exec($sql);
}
