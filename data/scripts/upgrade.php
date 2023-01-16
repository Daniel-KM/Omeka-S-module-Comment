<?php declare(strict_types=1);

namespace Comment;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.1.5', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `comment` CHANGE `user_agent` `user_agent` text NOT NULL;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.1.11', '<')) {
    $sql = <<<'SQL'
DELETE FROM `site_setting`
WHERE `id` IN ("comment_append_item_set_show", "comment_append_item_show", "comment_append_media_show");
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.3.1.12', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `comment`
CHANGE `owner_id` `owner_id` INT DEFAULT NULL,
CHANGE `resource_id` `resource_id` INT DEFAULT NULL,
CHANGE `site_id` `site_id` INT DEFAULT NULL,
CHANGE `parent_id` `parent_id` INT DEFAULT NULL,
CHANGE `modified` `modified` DATETIME DEFAULT NULL;
SQL;
    $connection->exec($sql);
}
