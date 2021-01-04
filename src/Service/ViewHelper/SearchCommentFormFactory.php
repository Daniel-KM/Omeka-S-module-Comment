<?php
namespace Comment\Service\ViewHelper;

use Comment\Form\SearchForm;
use Comment\View\Helper\SearchCommentForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SearchCommentFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $formElementManager = $services->get('FormElementManager');
        $searchForm = $formElementManager->get(SearchForm::class);
        $form = new SearchCommentForm();
        $form->setSearchForm($searchForm);
        return $form;
    }
}
