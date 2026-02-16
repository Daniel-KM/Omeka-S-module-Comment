<?php declare(strict_types=1);

namespace Comment\View\Helper;

use Comment\Service\CommentCache;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class CommentSubscriptionButton extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/comment-subscription-button';

    /**
     * Create a button to add or remove a resource to/from the selection.
     *
     * @param array $options Options for the partial. Managed keys:
     * - action: "add" or "delete". If not specified, the action is "toggle".
     * - labelSubscribe/label_subscribe (string)
     * - labelUnsubscribe/abel_unsubscribe (string)
     * - showLabel/show_label(bool) Display the text on the button or not (default false).
     * - template (string)
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        static $first = true;

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();

        // Check site setting to enable/disable the subscription button.
        // When the button is used as a resource page block, the admin
        // explicitly chose to add it, so skip the setting check.
        if (empty($options['skipSettingCheck'])) {
            $isSite = $view->status()->isSiteRequest();
            if ($isSite) {
                $siteSetting = $plugins->get('siteSetting');
                if (!$siteSetting('comment_subscribe_button')) {
                    return '';
                }
            }
        }

        $partial = $plugins->get('partial');

        $user = $view->identity();

        if ($first) {
            $assetUrl = $plugins->get('assetUrl');
            $view->headLink()
                ->appendStylesheet($assetUrl('css/common-dialog.css', 'Common'))
                ->appendStylesheet($assetUrl('css/comment.css', 'Comment'));
            $view->headScript()
                ->appendFile($assetUrl('js/common-dialog.js', 'Common'), 'text/javascript', ['defer' => 'defer'])
                ->appendFile($assetUrl('js/comment.js', 'Comment'), 'text/javascript', ['defer' => 'defer']);
            $first = false;
        }

        $subscribed = false;
        if ($user) {
            $userId = $user->getId();
            $resourceId = $resource->id();

            // Use cached subscription status if available.
            if (CommentCache::hasSubscription($userId, $resourceId)) {
                $subscribed = CommentCache::getSubscription($userId, $resourceId);
            } else {
                /** @var \Omeka\View\Helper\Api $api */
                $api = $plugins->get('api');
                try {
                    $api->read('comment_subscriptions', ['owner' => $userId, 'resource' => $resourceId], [], ['responseContent' => 'resource']);
                    $subscribed = true;
                } catch (\Exception $e) {
                    // Not subscribed.
                }
                CommentCache::setSubscription($userId, $resourceId, $subscribed);
            }
        }

        $isAllowedAnonymous = (bool) $plugins->get('setting')('comment_public_allow_comment');

        $isSite = $view->status()->isSiteRequest();
        $site = $isSite ? $view->currentSite() : null;

        // In rare cases, the route may "site", but the site not yet prepared.
        if ($isSite && !$site) {
            return '';
        }

        $action = $options['action'] ?? 'toggle';

        $url = $plugins->get('url');
        $urlBaseComment = $isSite ? $url('site/comment', ['site-slug' => $site->slug()]) : $url('admin/comment');
        $urlButton =  $view->status()->isAdminRequest()
            ? $url('admin/comment', ['action' => 'subscribe-resource'], ['query' => ['action' => $action, 'resource_id' => $resource->id()]])
            : $url("site/comment", ['site-slug' => $site->slug(), 'action' => 'subscribe-resource'], ['query' => ['action' => $action, 'resource_id' => $resource->id()]]);

        $template = $options['template'] ?? self::PARTIAL_NAME;

        $args = [
            'site' => $site,
            'resource' => $resource,
            'user' => $user,
            'isAllowed' => (bool) $user,
            // Subscription always requires a logged-in user, unlike commenting.
            'isAllowedAnonymous' => false,
            'subscribed' => $subscribed,
            'action' => $action,
            'urlButton' => $urlButton,
            'urlBaseComment' => $urlBaseComment,
            'labelSubscribe' => $options['labelSubscribe'] ?? $options['label_subscribe'] ?? null,
            'labelUnsubscribe' => $options['labelUnsubscribe'] ?? $options['label_unsubscribe'] ?? null,
            'showLabel' => $options['showLabel'] ?? $options['show_label'] ?? false,
        ] + array_diff_key($options, array_flip(['skipSettingCheck']));

        return $partial($template, $args);
    }
}
