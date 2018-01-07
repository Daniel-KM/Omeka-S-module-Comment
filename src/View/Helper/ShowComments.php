<?php
namespace Comment\View\Helper;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

class ShowComments extends AbstractHelper
{
    /**
     * Return the partial to display the comments.
     *
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $view = $this->getView();
        $comments = $this->listResourceComments($resource);
        return $view->partial(
            'common/comments',
            [
                'resource' => $resource,
                'comments' => $comments,
            ]
        );
    }

    /**
     * Helper to return comments of a resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function listResourceComments(AbstractResourceEntityRepresentation $resource)
    {
        $view = $this->getView();
        $comments = $view->api()->search('comments', ['resource_id' => $resource->id()])->getContent();
        return $comments;
    }
}
