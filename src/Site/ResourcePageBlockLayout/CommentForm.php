<?php declare(strict_types=1);

namespace Comment\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

class CommentForm implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Comment: Form'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return [
            'items',
            'media',
            'item_sets',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        return $view->partial('common/resource-page-block-layout/comment-form', [
            'resource' => $resource,
        ]);
    }
}
