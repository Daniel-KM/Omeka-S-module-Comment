<?php
namespace Comment\Form;

use Omeka\Stdlib\Message;
use Omeka\View\Helper\Setting;
use Zend\Form\Element\Button;
use Zend\Form\Element\Checkbox;
use Zend\Form\Element\Csrf;
use Zend\Form\Element\Email;
use Zend\Form\Element\Hidden;
use Zend\Form\Element\Text;
use Zend\Form\Form;
use Zend\Http\PhpEnvironment\RemoteAddress;
use Zend\ServiceManager\ServiceLocatorInterface as FormElementManager;
use Zend\Validator\StringLength;
use Zend\View\Helper\Url;

class CommentForm extends Form
{
    /**
     * @var Setting
     */
    protected $settingHelper;

    /**
     * @var Url
     */
    protected $urlHelper;

    /**
     * @var FormElementManager
     */
    protected $formElementManager;

    protected $options = [
        'site_slug' => null,
        'resource_id' => null,
        'user' => null,
        'path' => null,
    ];

    public function init()
    {
        $settingHelper = $this->getSettingHelper();
        $urlHelper = $this->getUrlHelper();
        $resourceId = $this->getOption('resource_id');
        $siteSlug = $this->getOption('site_slug');
        $isPublic = (bool) strlen($siteSlug);
        $user = $this->getOption('user');
        $isAnonymous = empty($user);
        $action = $isPublic
            ? $urlHelper('site/comment', ['action' => 'add', 'site-slug' => $siteSlug])
            : $urlHelper('admin/comment', ['action' => 'add']);

        $this->setAttribute('id', 'comment-form');
        $this->setAttribute('action', $action);
        $this->setAttribute('class', 'comment-form disable-unsaved-warning');
        $this->setAttribute('data-resource-id', $resourceId);

        if ($isAnonymous) {
            $this->add([
                'type' => Text::class,
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
                'type' => Email::class,
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
                'type' => \Zend\Form\Element\Url::class,
                'name' => 'o-module-comment:website',
                'options' => [
                    'label' => 'Website', // @translate
                ],
                'attributes' => [
                    'placeholder' => 'My website…', // @translate
                ],
            ]);
        }

        $this->add([
            'type' => 'Textarea',
            'name' => 'o-module-comment:body',
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
                    'max' => $settingHelper('comment_max_length'),
                    'messages' => [
                        StringLength::TOO_SHORT =>
                        'Proposed comment cannot be empty.', // @translate
                        StringLength::TOO_LONG =>
                        new Message('Comment cannot be longer than %d characters.', // @translate
                            $settingHelper('comment_max_length')),
                    ],
                ]],
            ],
        ]);

        if ($isAnonymous) {
            // The legal agreement is checked by default for logged users.
            $legalText = $settingHelper('comment_legal_text');
            if ($legalText) {
                // TODO Allow html legal agreement in the comment form help from here.
                $legalText = str_replace('&nbsp;', ' ', strip_tags($legalText));
                $this->add([
                    'type' => Checkbox::class,
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
                $siteKey = $settingHelper('recaptcha_site_key');
                $secretKey = $settingHelper('recaptcha_secret_key');
                if ($siteKey && $secretKey) {
                    $element = $this->getFormElementManager()
                        ->get('Omeka\Form\Element\Recaptcha', [
                            'site_key' => $siteKey,
                            'secret_key' => $secretKey,
                            'remote_ip' => (new RemoteAddress)->getIpAddress(),
                        ]);
                    $this->add($element);
                }

                if ($settingHelper('comment_antispam')) {
                    // Return only one digit.
                    $a = mt_rand(0, 6);
                    $b = mt_rand(1, 3);
                    $result = (string) ($a + $b);

                    $question = new Message('How much is %d plus %d?', $a, $b); // @translate
                    // Use the name "address" for spam.
                    $this->add(['type' => Hidden::class, 'name' => 'address_a', 'attributes' => ['value' => $a]]);
                    $this->add(['type' => Hidden::class, 'name' => 'address_b', 'attributes' => ['value' => $b]]);
                    $this->add([
                        'type' => Text::class,
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
                    'type' => Text::class,
                    'name' => 'o-module-comment:check',
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

        $this->add([
            'type' => Hidden::class,
            'name' => 'resource_id',
            'attributes' => [
                'value' => $resourceId,
                'required' => true,
            ],
        ]);

        $this->add([
            'type' => Hidden::class,
            'name' => 'comment_parent_id',
            'attributes' => [
                'id' => 'comment_parent_id',
                'value' => null,
            ],
        ]);

        $this->add([
            'type' => Hidden::class,
            'name' => 'path',
            'attributes' => [
                'value' => $this->getOption('path'),
                'required' => true,
            ],
        ]);

        $this->add([
            'type' => Csrf::class,
            'name' => sprintf('csrf_%s', $resourceId),
            'options' => [
                'csrf_options' => ['timeout' => 3600],
            ],
        ]);

        $this->add([
            'type' => Button::class,
            'name' => 'submit',
            'options' => [
                'label' => 'Comment it!', // @translate
            ],
            'attributes' => [
                'class' => 'fa fa-comment',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o-module-comment:website',
            'required' => false,
        ]);
    }

    /**
     * @param Setting $setting
     */
    public function setSettingHelper(Setting $settingHelper)
    {
        $this->settingHelper = $settingHelper;
    }

    /**
     * @return Setting
     */
    public function getSettingHelper()
    {
        return $this->settingHelper;
    }

    /**
     * @param Url $urlHelper
     */
    public function setUrlHelper(Url $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }

    /**
     * @param FormElementManager $formElementManager
     */
    public function setFormElementManager($formElementManager)
    {
        $this->formElementManager = $formElementManager;
    }

    /**
     * @return FormElementManager
     */
    public function getFormElementManager()
    {
        return $this->formElementManager;
    }
}
