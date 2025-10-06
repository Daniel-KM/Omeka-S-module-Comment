<?php declare(strict_types=1);

namespace Comment\View\Helper;

use Comment\Entity\Comment;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class CommentForm extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/comment-form';

    protected $formElementManager;

    public function __construct($formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * Return the partial to display the comment form.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, array $options = []): string
    {
        $view = $this->getView();
        if (!$view->userIsAllowed(Comment::class, 'create')) {
            return '';
        }

        $plugins = $view->getHelperPluginManager();
        $fallbackSetting = $plugins->get('fallbackSetting');

        $user = $view->identity();
        $path = $view->serverUrl(true);
        $siteSlug = $view->params()->fromRoute('site-slug');

        /** @var \Comment\Form\CommentForm $form */
        $form = $this->formElementManager->get(\Comment\Form\CommentForm::class);
        $form
            ->setOptions([
                'site_slug' => $siteSlug,
                'resource_id' => $resource->id(),
                'user' => $user,
                'path' => $path,
            ])
            ->init();

        $label = $options['label'] ?? (string) $fallbackSetting('comment_label', ['site', 'global']);
        $structure = $options['structure'] ?? (string) $fallbackSetting('comment_structure', ['site', 'global'], 'flat');
        $closedOnLoad = $options['closed_on_load'] ?? (bool) $fallbackSetting('comment_closed_on_load', ['site', 'global'], false);
        $maxLength = $options['max_length'] ?? (int) $fallbackSetting('comment_max_length', ['site', 'global'], 2000);
        $skipGravatar = $options['skip_gravatar'] ?? (bool) $fallbackSetting('comment_skip_gravatar', ['site', 'global'], false);
        $legalText = $options['legal_text'] ?? (string) $fallbackSetting('comment_legal_text', ['site', 'global']);

        $template = $options['template'] ?? self::PARTIAL_NAME;

        $args = [
            'resource' => $resource,
            'commentForm' => $form,
            'label' => $label,
            'structure' => $structure,
            'closedOnLoad' => $closedOnLoad,
            'maxLength' => $maxLength,
            'skipGravatar' => $skipGravatar,
            'legalText' => $legalText,
            'template' => $template,
            'parentId' => null,
            'template'  => $template,
        ] + $options;

        return $view->partial($template, $args);
    }
}
