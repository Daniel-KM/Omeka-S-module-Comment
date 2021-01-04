<?php declare(strict_types=1);
namespace Comment\View\Helper;

use Comment\Entity\Comment;
use Comment\Form\CommentForm;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class ShowCommentForm extends AbstractHelper
{
    protected $formElementManager;

    public function __construct($formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Return the partial to display the comment form.
     *
     * @return string
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $view = $this->getView();
        if (!$view->userIsAllowed(Comment::class, 'create')) {
            return '';
        }

        $user = $view->identity();
        $path = $view->serverUrl(true);
        $siteSlug = $view->params()->fromRoute('site-slug');

        $form = $this->formElementManager->get(CommentForm::class);
        $form->setOptions([
            'site_slug' => $siteSlug,
            'resource_id' => $resource->id(),
            'user' => $user,
            'path' => $path,
        ]);
        $form->init();
        $view->vars()->offsetSet('commentForm', $form);
        return $view->partial('common/comment-form');
    }
}
