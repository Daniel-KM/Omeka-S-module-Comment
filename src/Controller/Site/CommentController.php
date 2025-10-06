<?php declare(strict_types=1);
namespace Comment\Controller\Site;

use Comment\Controller\AbstractCommentController;
use Laminas\View\Model\ViewModel;

class CommentController extends AbstractCommentController
{
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch('Comment\Controller\Site\Guest', $params);
    }

    public function subscriptionAction()
    {
        $user = $this->identity();
        if (!$user) {
            return $this->redirect()->toRoute('site/guest/anonymous', ['action' => 'login'], ['query' => ['redirect' => $this->getRequest()->getRequestUri()]]);
        }

        // Browse the subscription of the user.

        // Do not limlit by site.
        // TODO When resource is not in site, set the right url.
        $site = $this->currentSite();

        $this->browse()->setDefaults('comment_subscriptions');

        $query = $this->params()->fromQuery();
        $query['owner_id'] = $user->getId();
        /*
        $query['site_id'] = $site->id();
        if ($this->siteSettings()->get('browse_attached_items', false)) {
            $query['site_attachments_only'] = true;
        }
        */

        $response = $this->api()->search('comment_subscriptions', $query);
        $this->paginator($response->getTotalResults());
        $subscriptions = $response->getContent();

        $view = new ViewModel([
            'site' => $site,
            'user' => $user,
            'subscriptions' => $subscriptions,
            'resources' => $subscriptions,
        ]);
        return $view
            ->setTemplate('guest/site/guest/comment-subscription');
    }
}
