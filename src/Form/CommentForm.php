<?php declare(strict_types=1);

namespace Comment\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\ServiceManager\ServiceLocatorInterface as FormElementManager;
use Laminas\Validator\StringLength;
use Laminas\View\Helper\Url;
use Omeka\Settings\FallbackSettings;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Message;

class CommentForm extends Form
{
    /**
     * @var \Omeka\Settings\FallbackSettings
     */
    protected $fallbackSettings;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    /**
     * @var \Omeka\\Settings\Settings
     */
    protected $settings;

/**
     * @var \Laminas\View\Helper\Url
     */
    protected $urlHelper;

    protected $options = [
        'site_slug' => null,
        'resource_id' => null,
        'user' => null,
        'path' => null,
    ];

    public function init(): void
    {
        $resourceId = $this->getOption('resource_id');
        $siteSlug = (string) $this->getOption('site_slug', '');
        $isPublic = (bool) strlen($siteSlug);
        $user = $this->getOption('user');
        $isAnonymous = empty($user);
        $action = $isPublic
            ? $this->urlHelper->__invoke('site/comment', ['action' => 'add', 'site-slug' => $siteSlug])
            : $this->urlHelper->__invoke('admin/comment', ['action' => 'add']);

        $this
            ->setAttribute('id', 'comment-form')
            ->setAttribute('action', $action)
            ->setAttribute('class', 'comment-form disable-unsaved-warning')
            ->setAttribute('data-resource-id', $resourceId);

        if ($isAnonymous) {
            $this->add([
                'type' => Element\Text::class,
                'name' => 'o:name',
                'options' => [
                    'label' => 'Name', // @translate
                ],
                'attributes' => [
                    'placeholder' => 'My name…', // @translate
                    'required' => false,
                    'value' => $isAnonymous ? '' : $user->getName(),
                ],
            ]);

            $this->add([
                'type' => Element\Email::class,
                'name' => 'o:email',
                'options' => [
                    'label' => 'Email', // @translate
                ],
                'attributes' => [
                    'placeholder' => 'My email (it won’t be displayed)…', // @translate
                    'required' => true,
                    'value' => $isAnonymous ? '' : $user->getEmail(),
                ],
            ]);

            $this->add([
                'type' => Element\Url::class,
                'name' => 'o:website',
                'options' => [
                    'label' => 'Website', // @translate
                ],
                'attributes' => [
                    'placeholder' => 'My website…', // @translate
                ],
            ]);
        }

        $this->add([
            'type' => Element\Textarea::class,
            'name' => 'o:body',
            'options' => [
                'label' => 'Comment', // @translate
            ],
            'attributes' => [
                'placeholder' => 'My comment…', // @translate
                'required' => true,
                'rows' => 12,
            ],
            'validators' => [
                ['validator' => 'StringLength', 'options' => [
                    'min' => 1,
                    'max' => $this->fallbackSettings->get('comment_max_length', ['site', 'global']),
                    'messages' => [
                        StringLength::TOO_SHORT =>
                            'Proposed comment cannot be empty.', // @translate
                        StringLength::TOO_LONG =>
                            new Message('Comment cannot be longer than %d characters.', // @translate
                                $this->fallbackSettings->get('comment_max_length', ['site', 'global'])),
                    ],
                ]],
            ],
        ]);

        if ($isAnonymous) {
            // The legal agreement is checked by default for logged users.
            $legalText = $this->fallbackSettings->get('comment_legal_text', ['site', 'global']);
            if ($legalText) {
                // TODO Allow html legal agreement in the comment form help from here.
                $legalText = strtr(strip_tags($legalText), ['&nbsp;' => ' ']);
                $this->add([
                    'type' => Element\Checkbox::class,
                    'name' => 'legal_agreement',
                    'options' => [
                        'label' => 'Terms of service', // @translate
                        'info' => $legalText,
                        'label_options' => [
                            'disable_html_escape' => true,
                        ],
                        'use_hidden_element' => false,
                    ],
                    'attributes' => [
                        'value' => !$isAnonymous,
                        'required' => true,
                    ],
                    'validators' => [
                        ['notEmpty', true, [
                            'messages' => [
                                'isEmpty' => 'You must agree to the terms and conditions.', // @translate
                            ],
                        ]],
                    ],
                ]);

                // Assume registered users are trusted and don't make them play
                // recaptcha.
                $siteKey = $this->settings->get('recaptcha_site_key');
                $secretKey = $this->settings->get('recaptcha_secret_key');
                if ($siteKey && $secretKey) {
                    $element = $this->formElementManager
                        ->get(\Omeka\Form\Element\Recaptcha::class, [
                            'site_key' => $siteKey,
                            'secret_key' => $secretKey,
                            'remote_ip' => (new RemoteAddress)->getIpAddress(),
                        ]);
                    $this->add($element->setName('g-recaptcha-response'));
                }

                if ($this->settings->get('comment_antispam')) {
                    // Return only one digit.
                    $a = random_int(0, 6);
                    $b = random_int(1, 3);

                    $result = (string) ($a + $b);

                    $question = new Message('How much is %d plus %d?', $a, $b); // @translate
                    // Use the name "address" for spam.
                    $this
                        ->add(['type' => Element\Hidden::class, 'name' => 'address_a', 'attributes' => ['value' => $a]])
                        ->add(['type' => Element\Hidden::class, 'name' => 'address_b', 'attributes' => ['value' => $b]])
                        ->add([
                            'type' => Element\Text::class,
                            'name' => 'address',
                            'options' => [
                                'label' => (string) new Message('To prove you are not a robot, answer this question: %s', $question), // @translate
                                'required' => true,
                            ],
                            'validators' => [
                                ['notEmpty', true, [
                                    'messages' => [
                                        'isEmpty' => 'You must answer the antispam question.', // @translate
                                    ],
                                ]],
                                ['identical', false, [
                                    'token' => $result,
                                    'messages' => [
                                        'notSame' => 'Check your anwser to the antispam question.', // @translate
                                    ],
                                ]],
                            ],
                        ]);
                }

                // An honeypot for anti-spam. It’s hidden, so only bots fill it.
                $this->add([
                    'type' => Element\Text::class,
                    'name' => 'o:check',
                    'options' => [
                        'label' => 'String to check', // @translate
                    ],
                    'attributes' => [
                        'placeholder' => 'Set the string to check', // @translate
                        'required' => false,
                        'style' => 'display: none;',
                    ],
                    'validators' => [
                        ['validator' => 'StringLength', 'options' => [
                            'min' => 0,
                            'max' => 0,
                        ]],
                    ],
                ]);
            }
        }

        $this
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'resource_id',
                'attributes' => [
                    'value' => $resourceId,
                    'required' => true,
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'comment_parent_id',
                'attributes' => [
                    'id' => 'comment_parent_id',
                    'value' => null,
                ],
            ])
            ->add([
                'type' => Element\Hidden::class,
                'name' => 'path',
                'attributes' => [
                    'value' => $this->getOption('path'),
                    'required' => true,
                ],
            ])
            // TODO Clarify the use of this second csrf. Only the timeout?
            ->add([
                'type' => Element\Csrf::class,
                'name' => sprintf('csrf_%s', $resourceId),
                'options' => [
                    'csrf_options' => [
                        'timeout' => 3600
                    ]
                ]
            ])
            ->add([
                'type' => Element\Button::class,
                'name' => 'submit',
                'options' => [
                    'label' => 'Comment it!', // @translate
                ],
                'attributes' => [
                    'id' => 'comment-submit',
                    'type' => 'submit',
                    'class' => 'comment-submit',
                ],
            ]);

        $this->getInputFilter()
            ->add([
                'name' => 'o:website',
                'required' => false,
            ]);
    }

    public function setFallbackSettings(FallbackSettings $fallbackSettings): self
    {
        $this->fallbackSettings = $fallbackSettings;
        return $this;
    }

    public function setFormElementManager(FormElementManager $formElementManager): self
    {
        $this->formElementManager = $formElementManager;
        return $this;
    }

    public function setSettings(Settings $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    public function setUrlHelper(Url $urlHelper): self
    {
        $this->urlHelper = $urlHelper;
        return $this;
    }
}
