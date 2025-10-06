<?php declare(strict_types=1);

namespace Comment\View\Helper;

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
     * - resourceId (int)
     * - action: "add" or "delete". If not specified, the action is "toggle".
     * - template (string)
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        static $first = true;

        $view = $this->getView();
        $plugins = $view->getHelperPluginManager();
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
            /** @var \Omeka\View\Helper\Api $api */
            $api = $plugins->get('api');
            try {
                // TODO The options are not managed in api view helper.
                $api->read('comment_subscriptions', ['owner' => $user->getId(), 'resource' => $resource->id()], [], ['responseContent' => 'resource']);
                $subscribed = true;
            } catch (\Exception $e) {
                // Skip.
            }
        }

        $isAllowedAnonymous = (bool) $plugins->get('setting')('comment_public_allow_comment');

        $isSite = $view->status()->isSiteRequest();
        $site = $isSite ? $view->currentSite() : null;

        $action = $options['action'] ?? 'toggle';

        $url = $plugins->get('url');
        $urlButton =  $view->status()->isAdminRequest()
            ? $url('admin/comment', ['action' => 'subscribe-resource'], ['query' => ['action' => $action, 'resource_id' => $resource->id()]])
            : $url("site/comment", ['site-slug' => $site->slug(), 'action' => 'subscribe-resource'], ['query' => ['action' => $action, 'resource_id' => $resource->id()]]);

        $template = $options['template'] ?? self::PARTIAL_NAME;

        $args = [
            'site' => $site,
            'resource' => $resource,
            'user' => $user,
            'isAllowed' => (bool) $user,
            'isAllowedAnonymous' => $isAllowedAnonymous,
            'subscribed' => $subscribed,
            'action' => $action,
            'urlButton' => $urlButton,
        ] + $options;

        return $partial($template, $args);
    }
}
