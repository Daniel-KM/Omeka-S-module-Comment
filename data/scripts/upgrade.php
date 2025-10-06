<?php declare(strict_types=1);

namespace Comment;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.73')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.73'
    );
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
}

if (version_compare($oldVersion, '3.1.5', '<')) {
    $sql = <<<'SQL'
ALTER TABLE `comment` CHANGE `user_agent` `user_agent` text NOT NULL;
SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.1.11', '<')) {
    $sql = <<<'SQL'
DELETE FROM `site_setting`
WHERE `id` IN ("comment_append_item_set_show", "comment_append_item_show", "comment_append_media_show");
SQL;
    $connection->executeStatement($sql);
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
    $connection->executeStatement($sql);
}
