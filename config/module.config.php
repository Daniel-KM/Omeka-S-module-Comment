<?php declare(strict_types=1);

namespace Comment;

return [
    'api_adapters' => [
        'invokables' => [
            'comments' => Api\Adapter\CommentAdapter::class,
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
            'showComments' => View\Helper\ShowComments::class,
        ],
        'factories' => [
            'searchCommentForm' => Service\ViewHelper\SearchCommentFormFactory::class,
            'showCommentForm' => Service\ViewHelper\ShowCommentFormFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SearchForm::class => Form\SearchForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SiteSettingsFieldset::class => Form\SiteSettingsFieldset::class,
        ],
        'factories' => [
            Form\CommentForm::class => Service\Form\CommentFormFactory::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'commentForm' => Site\ResourcePageBlockLayout\CommentForm::class,
            'comments' => Site\ResourcePageBlockLayout\Comments::class,
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
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
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
            'comment_user_require_moderation' => false,
            'comment_threaded' => true,
            'comment_max_length' => 2000,
            'comment_comments_label' => 'Comments', // @translate
            'comment_legal_text' => '<p>I agree with <a rel="license" href="#" target="_blank">terms of use</a> and I accept to free my contribution under the license <a rel="license" href="https://creativecommons.org/licenses/by-sa/3.0/" target="_blank">CC BY-SA</a>.</p>',
            'comment_wpapi_key' => '',
            'comment_antispam' => true,
        ],
        'site_settings' => [
            'comment_placement_form' => [],
            'comment_placement_list' => [],
            'comment_list_open' => true,
            'comment_skip_gravatar' => false,
        ],
    ],
];
