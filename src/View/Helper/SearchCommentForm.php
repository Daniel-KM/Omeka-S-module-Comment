<?php
namespace Comment\View\Helper;

use Comment\Form\SearchForm;
use Zend\View\Helper\AbstractHelper;

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

    public function setSearchForm(SearchForm $searchForm)
    {
        $this->searchForm = $searchForm;
    }
}
