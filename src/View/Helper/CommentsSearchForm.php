<?php declare(strict_types=1);

namespace Comment\View\Helper;

use Comment\Form\CommentsSearchForm as SearchForm;
use Laminas\View\Helper\AbstractHelper;

class CommentsSearchForm extends AbstractHelper
{
    /**
     * @var \Comment\Form\CommentsSearchForm
     */
    protected $searchForm;

    public function __construct(SearchForm $searchForm): void
    {
        $this->searchForm = $searchForm;
    }

    /**
     * Return the form to search comments.
     */
    public function __invoke(): SearchForm
    {
        return $this->searchForm;
    }
}
