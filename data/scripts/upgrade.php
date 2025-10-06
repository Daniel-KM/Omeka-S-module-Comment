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
        CHANGE `modified` `modified` DATETIME DEFAULT NULL
        ;
        SQL;
    $connection->executeStatement($sql);
}

if (version_compare($oldVersion, '3.4.14', '<')) {
    $sql = <<<'SQL'
        ALTER TABLE `comment`
        CHANGE `approved` `approved` TINYINT(1) DEFAULT 0 NOT NULL AFTER `parent_id`,
        CHANGE `flagged` `flagged` TINYINT(1) DEFAULT 0 NOT NULL AFTER `approved`,
        CHANGE `spam` `spam` TINYINT(1) DEFAULT 0 NOT NULL AFTER `flagged`,
        CHANGE `email` `email` VARCHAR(190) NOT NULL AFTER `path`,
        CHANGE `name` `name` VARCHAR(190) NOT NULL AFTER `email`,
        CHANGE `ip` `ip` VARCHAR(45) NOT NULL COLLATE `latin1_bin` AFTER `website`,
        CHANGE `user_agent` `user_agent` VARCHAR(1024) NOT NULL AFTER `ip`
        SQL;
    $connection->executeStatement($sql);

    $sql = <<<'SQL'
        ALTER TABLE `comment`
        ADD `edited` DATETIME NULL AFTER `modified`;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already created.
    }

    $sql = <<<'SQL'
        CREATE TABLE `comment_subscription` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `owner_id` INT NOT NULL,
            `resource_id` INT NOT NULL,
            `created` DATETIME NOT NULL,
            INDEX `IDX_3B2FA8AE7E3C61F9` (`owner_id`),
            INDEX `IDX_3B2FA8AE89329D25` (`resource_id`),
            PRIMARY KEY(`id`)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        ALTER TABLE `comment_subscription` ADD CONSTRAINT `FK_3B2FA8AE7E3C61F9` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
        ALTER TABLE `comment_subscription` ADD CONSTRAINT `FK_3B2FA8AE89329D25` FOREIGN KEY (`resource_id`) REFERENCES `resource` (`id`) ON DELETE CASCADE;
        SQL;
    try {
        $connection->executeStatement($sql);
    } catch (\Exception $e) {
        // Already created.
    }

    $manageModuleAndResources = $this->getManageModuleAndResources();
    $strings = [
        'showComment',
    ];
    $globs = [
        'themes/*/view/common/*',
        'themes/*/view/common/*/*',
        'themes/*/view/omeka/site/*/*',
    ];
    $result = [];
    foreach ($globs as $glob) {
        $res = $manageModuleAndResources->checkStringsInFiles($strings, $glob) ?? [];
        $result = array_merge($result, $res);
    }
    if ($result) {
        $message = new PsrMessage(
            'The templates and view helpers were renamed. You should check your theme. Matching templates: {json}', // @translate
            ['json' => json_encode($result, 448)]
        );
        $logger->warn($message->getMessage(), $message->getContext());
        $messenger->addWarning($message);
    }

    // Do not update twice.
    $label = $settings->get('comment_label');
    $structure = $settings->get('comment_structure');
    if (!$label || !$structure) {
        $label = $settings->get('comment_comments_label');
        $settings->set('comment_label', $label);
        $structure = $settings->get('comment_threaded', true) ? 'threaded' : 'flat';
        $settings->set('comment_structure', $structure);
        $settings->set('comment_closed_on_load', false);
        $settings->set('comment_skip_gravatar', false);
    }
    $settings->delete('comment_comments_label');
    $settings->delete('comment_threaded');
    $settings->delete('comment_list_open');

    // Add new site settings according to old settings.
    $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
    foreach ($siteIds as $siteId) {
        $siteSettings->setTargetId($siteId);
        $siteSettings->set('comment_placement_form', ['after/items']);
        $siteSettings->set('comment_placement_list', ['after/items']);
        $siteSettings->set('comment_label', null);
        $siteSettings->set('comment_structure', null);
        $siteSettings->set('comment_closed_on_load', null);
        $siteSettings->set('comment_max_length', null);
        $siteSettings->set('comment_skip_gravatar', null);
        $siteSettings->set('comment_legal_text', null);
        $v = $siteSettings->get('comment_placement_subscription', []);
        $siteSettings->set('comment_placement_subscription', array_diff($v, ['block/items', 'block/media', 'block/item_sets']));
        $v = $siteSettings->get('comment_placement_form', []);
        $siteSettings->set('comment_placement_form', array_diff($v, ['block/items', 'block/media', 'block/item_sets']));
        $v = $siteSettings->get('comment_placement_list', []);
        $siteSettings->set('comment_placement_list', array_diff($v, ['block/items', 'block/media', 'block/item_sets']));
    }

    $message = new PsrMessage(
        'Resource blocks were added to display the comments and the comment form via the theme config.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'A resource block allows to subscribe to a resource to be notified when a comment is added.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'An option allows to skip display of gravatar.' // @translate
    );
    $messenger->addSuccess($message);

    $message = new PsrMessage(
        'It is now possible to edit for the user or an admin to edit a comment.' // @translate
    );
    $messenger->addSuccess($message);
}
