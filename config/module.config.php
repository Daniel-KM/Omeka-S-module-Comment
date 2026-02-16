<?php declare(strict_types=1);

namespace Comment;

return [
    'api_adapters' => [
        'invokables' => [
            'comments' => Api\Adapter\CommentAdapter::class,
            'comment_subscriptions' => Api\Adapter\CommentSubscriptionAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
        'filters' => [
            'comment_visibility' => Db\Filter\CommentVisibilityFilter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'commentIsSubscribed' => View\Helper\CommentIsSubscribed::class,
            'commentsResource' => View\Helper\CommentsResource::class,
            'commentSubscriptionButton' => View\Helper\CommentSubscriptionButton::class,
        ],
        'factories' => [
            'commentForm' => Service\ViewHelper\CommentFormFactory::class,
            'commentsSearchForm' => Service\ViewHelper\CommentsSearchFormFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\CommentsBrowseFieldset::class => Form\CommentsBrowseFieldset::class,
            Form\CommentsSearchForm::class => Form\CommentsSearchForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\CommentForm::class => Service\Form\CommentFormFactory::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'commentsBrowse' => Site\BlockLayout\CommentsBrowse::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'commentForm' => Site\ResourcePageBlockLayout\CommentForm::class,
            'comments' => Site\ResourcePageBlockLayout\Comments::class,
            'commentSubscriptionButton' => Site\ResourcePageBlockLayout\CommentSubscriptionButton::class,
            'commentSubscriptionStatus' => Site\ResourcePageBlockLayout\CommentSubscriptionStatus::class,
        ],
    ],
    'navigation_links' => [
        'invokables' => [
            'comments' => Site\Navigation\Link\Comments::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\CommentController::class => Controller\Admin\CommentController::class,
            Controller\Site\CommentController::class => Controller\Site\CommentController::class,
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Comments', // @translate
                'route' => 'admin/comment',
                'controller' => Controller\Admin\CommentController::class,
                'action' => 'browse',
                'resource' => Controller\Admin\CommentController::class,
                'class' => 'o-icon- fa-comments',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'comment' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/comment[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Comment\Controller\Site',
                                'controller' => Controller\Site\CommentController::class,
                                'action' => 'add',
                            ],
                        ],
                    ],
                    'comment-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/comment/:id[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Comment\Controller\Site',
                                'controller' => Controller\Site\CommentController::class,
                                'action' => 'show',
                            ],
                        ],
                    ],
                    'guest' => [
                        // The default values for the guest user route are kept
                        // to avoid issues for visitors when an upgrade of
                        // module Guest occurs or when it is disabled.
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/guest',
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'comment' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/comment[/:action]',
                                    'constraints' => [
                                        'action' => 'browse|subscription',
                                    ],
                                    'defaults' => [
                                        '__NAMESPACE__' => 'Comment\Controller\Site',
                                        'controller' => Controller\Site\CommentController::class,
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'admin' => [
                'child_routes' => [
                    'comment' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/comment[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Comment\Controller\Admin',
                                'controller' => Controller\Admin\CommentController::class,
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'comment-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/comment/:id[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'id' => '\d+',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Comment\Controller\Admin',
                                'controller' => Controller\Admin\CommentController::class,
                                'action' => 'show',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => \Laminas\I18n\Translator\Loader\Gettext::class,
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'comments' => [
                'sort_by' => 'created',
                'sort_order' => 'desc',
            ],
            'comment_subscriptions' => [
                'sort_by' => 'created',
                'sort_order' => 'desc',
            ],
        ],
        'public' => [
            'comments' => [
                'sort_by' => 'created',
                'sort_order' => 'desc',
            ],
            'comment_subscriptions' => [
                'sort_by' => 'created',
                'sort_order' => 'desc',
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'comments' => [
                'created' => 'Date created', // @translate
                'modified' => 'Date modified', // @translate
                'resource_id' => 'Resource', // @translate
                'id' => 'ID', // @translate
            ],
            'comment_subscriptions' => [
                'created' => 'Date created', // @translate
                'id' => 'ID', // @translate
            ],
        ],
        'public' => [
            'comments' => [
                'created' => 'Date created', // @translate
                'modified' => 'Date modified', // @translate
                'resource_id' => 'Resource', // @translate
            ],
            'comment_subscriptions' => [
                'created' => 'Date created', // @translate
            ],
        ],
    ],
    'js_translate_strings' => [
        'You should accept the legal agreement.', // @translate
        'Comment was added to the resource.', // @translate
        'It will be displayed definitely when approved.', // @translate
        'Request too long to process.', // @translate
        'The resource doesn’t exist.', // @translate
        'Something went wrong', // @translate
        'The resource or the comment doesn’t exist.', // @translate
        'Edit your comment', // @translate
        'Are you sure you want to delete this comment?', // @translate
    ],
    'comment' => [
        'settings' => [
            'comment_resources' => [
                'items',
            ],
            'comment_public_allow_view' => true,
            'comment_public_allow_comment' => true,
            'comment_public_require_moderation' => true,
            'comment_public_notify_post' => [],
            // Email templates with placeholders.
            'comment_email_subscriber_subject' => '[{site_name}] New comment', // @translate
            'comment_email_subscriber_body' => <<<'TXT'
                Hi,

                A new comment was published for resource #{resource_id} ({resource_title}).

                You can see it at {resource_url}#comments.

                Sincerely,
                TXT, // @translate
            'comment_email_moderator_subject' => '[{site_name}] New public comment', // @translate
            'comment_email_moderator_body' => <<<'TXT'
                A comment was added to resource #{resource_id} ({resource_title}).

                Author: {comment_author} <{comment_email}>

                Comment:
                {comment_body}

                Review at: {resource_url}
                TXT, // @translate
            'comment_email_flagged_subject' => '[{site_name}] Comment flagged for review', // @translate
            'comment_email_flagged_body' => <<<'TXT'
                A comment has been flagged for review.

                Resource: #{resource_id} ({resource_title})
                Author: {comment_author} <{comment_email}>

                Comment:
                {comment_body}

                Review at: {admin_url}
                TXT, // @translate
            'comment_user_require_moderation' => false,
            'comment_user_allow_edit' => false,
            'comment_user_allow_alias' => false,
            'comment_user_allow_anonymous' => false,
            'comment_wpapi_key' => '',
            'comment_antispam' => true,
            'comment_label' => 'Comments', // @translate
            'comment_structure' => 'flat',
            'comment_closed_on_load' => '0',
            'comment_max_length' => 2000,
            'comment_skip_gravatar' => false,
            'comment_legal_text' => <<<'HTML'
                <p>I agree with <a rel="license" href="#" target="_blank">terms of use</a> and I accept to free my contribution under the license <a rel="license" href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank">CC BY-SA</a>.</p>
                HTML, // @translate
            'comment_subscribe_button' => '0',
            'comment_website' => true,
            'comment_groups' => [],
        ],
        'site_settings' => [
            'comment_placement_subscription' => [],
            'comment_placement_form' => [],
            'comment_placement_list' => [],
            'comment_label' => '',
            'comment_structure' => '',
            'comment_closed_on_load' => '',
            'comment_max_length' => '',
            'comment_skip_gravatar' => '',
            'comment_legal_text' => '',
            'comment_website' => '',
        ],
        'block_settings' => [
            'commentsBrowse' => [
                'query' => [],
                'limit' => 12,
                // 'pagination' => true,
                // 'sort_headings' => [],
                'components' => [
                    'resource-heading',
                    'resource-body',
                    'thumbnail',
                ],
                'linkText' => '',
            ],
        ],
    ],
];
