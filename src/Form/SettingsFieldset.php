<?php declare(strict_types=1);

namespace Comment\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Comments'; // @translate

    protected $elementGroups = [
        'comment' => 'Comments', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'comment')
            ->setOption('element_groups', $this->elementGroups)

            ->add([
                'name' => 'comment_public_allow_view',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Allow public to view comments', // @translate
                    'info' => 'If unchecked, comments will be displayed only in admin pages.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_public_allow_comment',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Allow public to comment', // @translate
                    'info' => 'Allows everyone to comment, including non-registered users.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_public_require_moderation',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Require moderation for public comments', // @translate
                    'info' => 'If unchecked, comments will appear immediately.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_user_require_moderation',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Require moderation for non-admin users', // @translate
                    'info' => 'If unchecked, comments will appear immediately.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_public_notify_post',
                'type' => OmekaElement\ArrayTextarea::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Notify public comments by email', // @translate
                    'info' => 'The list of emails to notify when a comment is posted or flagged, one by row.', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'placeholder' => <<<'TXT'
                        contact@example.org
                        info@example2.org
                        TXT,
                    'rows' => 5,
                ],
            ])

            // Email templates for subscriber notifications.
            ->add([
                'name' => 'comment_email_subscriber_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Subscriber notification: subject', // @translate
                    'info' => 'Placeholders: {site_name}', // @translate
                ],
                'attributes' => [
                    'placeholder' => '[{site_name}] New comment', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_email_subscriber_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Subscriber notification: body', // @translate
                    'info' => 'Placeholders: {site_name}, {resource_id}, {resource_title}, {resource_url}, {comment_author}, {comment_body}', // @translate
                ],
                'attributes' => [
                    'rows' => 8,
                    'placeholder' => <<<'TXT'
                        Hi,

                        A new comment was published for resource #{resource_id} ({resource_title}).

                        You can see it at {resource_url}#comments.

                        Sincerely,
                        TXT, // @translate
                ],
            ])

            // Email templates for moderator notifications.
            ->add([
                'name' => 'comment_email_moderator_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Moderator notification: subject', // @translate
                    'info' => 'Placeholders: {site_name}', // @translate
                ],
                'attributes' => [
                    'placeholder' => '[{site_name}] New public comment', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_email_moderator_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Moderator notification: body', // @translate
                    'info' => 'Placeholders: {site_name}, {resource_id}, {resource_title}, {resource_url}, {comment_author}, {comment_email}, {comment_body}', // @translate
                ],
                'attributes' => [
                    'rows' => 8,
                    'placeholder' => <<<'TXT'
                        A comment was added to resource #{resource_id} ({resource_title}).

                        Author: {comment_author} <{comment_email}>

                        Comment:
                        {comment_body}

                        Review at: {resource_url}
                        TXT, // @translate
                ],
            ])

            // Email templates for flagged comment notifications.
            ->add([
                'name' => 'comment_email_flagged_subject',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Flagged comment notification: subject', // @translate
                    'info' => 'Placeholders: {site_name}', // @translate
                ],
                'attributes' => [
                    'placeholder' => '[{site_name}] Comment flagged for review', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_email_flagged_body',
                'type' => Element\Textarea::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Flagged comment notification: body', // @translate
                    'info' => 'Placeholders: {site_name}, {resource_id}, {resource_title}, {comment_author}, {comment_email}, {comment_body}, {admin_url}', // @translate
                ],
                'attributes' => [
                    'rows' => 10,
                    'placeholder' => <<<'TXT'
                        A comment has been flagged for review.

                        Resource: #{resource_id} ({resource_title})
                        Author: {comment_author} <{comment_email}>

                        Comment:
                        {comment_body}

                        Review at: {admin_url}
                        TXT, // @translate
                ],
            ])

            ->add([
                'name' => 'comment_user_allow_edit',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Allow non-admin users to edit or delete their own comment', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_user_allow_alias',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Allow users to comment with an alias', // @translate
                    'info' => 'If checked, logged-in users can choose to comment with a custom name and email instead of their account information. The comment remains linked to their account.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_user_allow_anonymous',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Allow users to comment anonymously', // @translate
                    'info' => 'If checked, logged-in users can choose to comment anonymously. Their name will be displayed as "[Anonymous]" but the comment remains linked to their account for moderation purposes.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_wpapi_key',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'WordPress API key for Akismet if installed', // @translate
                    'info' => 'This feature requires the dependency package "zendframework/zendservice-akismet" that is not installed automatically.',
                ],
            ])

            ->add([
                'name' => 'comment_antispam',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Simple antispam', // @translate
                    'info' => 'If checked, a simple antispam (an addition of two digits) will be added for anonymous people if ReCaptcha is not set.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_rate_limit_count',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Rate limit: max comments', // @translate
                    'info' => 'Maximum number of comments allowed per IP address within the time period. Set 0 or empty to disable.', // @translate
                ],
                'attributes' => [
                    'min' => 0,
                    'placeholder' => '5',
                ],
            ])
            ->add([
                'name' => 'comment_rate_limit_period',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Rate limit: period (minutes)', // @translate
                    'info' => 'Time window in minutes for the rate limit.', // @translate
                ],
                'attributes' => [
                    'min' => 1,
                    'placeholder' => '60',
                ],
            ])

            ->add([
                'name' => 'comment_label',
                'type' => Element\Text::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Main label', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_structure',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Structure', // @translate
                    'value_options' => [
                        'flat' => 'Flat (by date)', // @translate
                        'threaded' => 'Threaded (by conversation)' // @translate
                    ],
                ],
            ])
            ->add([
                'name' => 'comment_closed_on_load',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Closed on load', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_max_length',
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Max length', // @translate
                ],
            ])
            ->add([
                'name' => 'comment_skip_gravatar',
                'type' => CommonElement\OptionalRadio::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Display gravatar', // @translate
                    'value_options' => [
                        // Warning, the option is the inverse of the label.
                        '1' => 'No', // @translate
                        '0' => 'Yes', // @translate
                    ],
                ],
            ])
            ->add([
                'name' => 'comment_legal_text',
                'type' => OmekaElement\CkeditorInline::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Legal agreement', // @translate
                    'info' => 'This text will be shown beside the legal checkbox. Let empty if you don’t want to use a legal agreement.', // @translate
                ],
            ])

            ->add([
                'name' => 'comment_groups',
                'type' => CommonElement\DataTextarea::class,
                'options' => [
                    'element_group' => 'comment',
                    'label' => 'Shortcuts to group comments by item sets', // @translate
                    'info' => 'A group allows to separate comments that have different purposes, fo example comments to identify and generic comments. The url will be "?group={name}". Set a list a name, then a "=", then a list of item set ids. The special group "none" allows to get comments without group.', // @translate
                    'as_key_value' => true,
                    'key_value_separator' => '=',
                    'data_options' => [
                        'name' => null,
                        'item_set_ids' => [
                            'separator' => ' ',
                            'is_integer' => true,
                        ],
                    ],
                    'data_flat_key' => 'item_set_ids',
                    'data_text_mode' => 'by_line',
                ],
                'attributes' => [
                    'id' => 'comment_groups',
                    'required' => false,
                    'placeholder' => <<<'TXT'
                        identify = 10
                        already_identified = 11 12
                        TXT,
                ],
            ])
        ;
    }
}
