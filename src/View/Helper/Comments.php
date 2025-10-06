<?php declare(strict_types=1);

namespace Comment\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class Comments extends AbstractHelper
{
    /**
     * Return the partial to display the comments.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        $view = $this->getView();

        $plugins = $view->getHelperPluginManager();
        $api = $plugins->get('api');
        $setting = $plugins->get('setting');

        $comments = $api->search('comments', ['resource_id' => $resource->id()])->getContent();

        $listOpen = isset($options['list_open']) ? !empty($options['list_open']) : (bool) $setting('comment_list_open');

        return $view->partial(
            'common/comments',
            [
                'site' => $view->currentSite(),
                'resource' => $resource,
                'comments' => $comments,
                'commentThreaded' => $setting('comment_threaded'),
                'listOpen' => $listOpen,
            ]
        );
    }
}
