<?php declare(strict_types=1);
namespace Comment\View\Helper;

use Comment\Form\SearchForm;
use Laminas\View\Helper\AbstractHelper;

class SearchCommentForm extends AbstractHelper
{
    /**
     * @var SearchForm
     */
    protected $searchForm;

    /**
     * Return the partial to display search comments.
     *
     * @return string
     */
    public function __invoke()
    {
        return $this->searchForm;
    }

    public function setSearchForm(SearchForm $searchForm): void
    {
        $this->searchForm = $searchForm;
    }
}
