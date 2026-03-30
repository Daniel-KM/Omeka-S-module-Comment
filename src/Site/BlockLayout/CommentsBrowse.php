<?php declare(strict_types=1);

namespace Comment\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;
use Omeka\Stdlib\ErrorStore;

/**
 * Adapted:
 * @see \BlockPlus\Site\BlockLayout\SearchResults
 * @see \Comment\Site\BlockLayout\CommentsBrowse
 */
class CommentsBrowse extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/block-layout/comments-browse';

    public function getLabel()
    {
        return 'Comment: Browse'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData() ?? [];

        if (empty($data['query'])) {
            $data['query'] = [];
        } elseif (!is_array($data['query'])) {
            $query = [];
            parse_str(ltrim($data['query'], "? \t\n\r\0\x0B"), $query);
            $data['query'] = array_filter($query, fn ($v) => $v !== '' && $v !== [] && $v !== null);
        }

        $block->setData($data);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        ?SitePageRepresentation $page = null,
        ?SitePageBlockRepresentation $block = null
    ) {
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['comment']['block_settings']['commentsBrowse'];
        $blockFieldset = \Comment\Form\CommentsBrowseFieldset::class;

        $data = $block ? ($block->data() ?? []) + $defaultSettings : $defaultSettings;

        $data['query'] = http_build_query($data['query'] ?? [], '', '&', PHP_QUERY_RFC3986);

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }

        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        return $view->formCollection($fieldset);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = self::PARTIAL_NAME)
    {
        $defaultQuery = $block->dataValue('query', []) + ['search' => ''];
        $query = $view->params()->fromQuery() + $defaultQuery;

        $site = $block->page()->site();
        if ($view->siteSetting('browse_attached_items', false)) {
            $query['site_attachments_only'] = true;
        }

        // Allow to force to display resources from another site.
        if (empty($query['site_id'])) {
            $query['site_id'] = $site->id();
        }

        $limit = $block->dataValue('limit', 12) ?: 12;

        // Unlike browse preview, the pagination is always prepared, even if it
        // not displayed in the view.
        $showPagination = $limit && $block->dataValue('pagination');

        $currentPage = $view->params()->fromQuery('page', 1);
        $query['page'] = $currentPage;
        $query['per_page'] = $limit;

        $sortBy = $view->params()->fromQuery('sort_by');
        if ($sortBy) {
            $query['sort_by'] = $sortBy;
        } elseif (!isset($query['sort_by'])) {
            $query['sort_by'] = 'created';
        }

        $sortOrder = $view->params()->fromQuery('sort_order');
        if ($sortOrder) {
            $query['sort_order'] = $sortOrder;
        } elseif (!isset($query['sort_order'])) {
            $query['sort_order'] = 'desc';
        }

        $components = $block->dataValue('components') ?: [];

        /**
         * @var \Omeka\Api\Response $response
         * @var \Common\View\Helper\EasyMeta $easyMeta
         */
        $api = $view->api();
        $easyMeta = $view->easyMeta();
        $response = $api->search('comments', $query);

        // TODO Currently, there can be only one pagination by page.
        $totalCount = $response->getTotalResults();
        $view->pagination(null, $totalCount, $currentPage, $limit);

        /*
        $sortHeadings = $block->dataValue('sort_headings', []);
        if ($sortHeadings) {
            $translate = $view->plugin('translate');
            foreach ($sortHeadings as $key => $sortHeading) {
                switch ($sortHeading) {
                    case 'created':
                        $label = $translate('Created'); // @translate
                        break;
                    case 'resource_class_label':
                        $label = $translate('Class'); // @translate
                        break;
                    default:
                        $propertyId = $easyMeta->propertyId($sortHeading);
                        if ($propertyId) {
                            $propertyLabel = $easyMeta->propertyLabel($propertyId);
                            $label = $translate($propertyLabel);
                            if ($resourceTemplate) {
                                $templateProperty = $resourceTemplate->resourceTemplateProperty($propertyId);
                                if ($templateProperty) {
                                    $alternateLabel = $templateProperty->alternateLabel();
                                    if ($alternateLabel) {
                                        $label = $translate($alternateLabel);
                                    }
                                }
                            }
                        } else {
                            unset($sortHeadings[$key]);
                            continue 2;
                        }
                        break;
                }
                $sortHeadings[$key] = [
                    'label' => $label,
                    'value' => $sortHeading,
                ];
            }
            $sortHeadings = array_filter($sortHeadings);
        }
        */

        $resources = $response->getContent();

        $linkText = $block->dataValue('link-text', null);

        $vars = [
            'block' => $block,
            'site' => $site,
            'comments' => $resources,
            'query' => $query,
            'pagination' => $showPagination,
            // 'sortHeadings' => $sortHeadings,
            'components' => $components,
            'linkText' => $linkText,
        ];
        return $view->partial($templateViewScript, $vars);
    }
}
