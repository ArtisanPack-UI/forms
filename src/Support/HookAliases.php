<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Support;

/**
 * Registers backwards-compat aliases for renamed hooks.
 *
 * Subscribers on the old snake_case / unprefixed name continue firing
 * (with an info-level deprecation log) after the Wave 2 hook rename
 * to `ap.forms.*` camelCase. Aliases will be removed in the next major
 * version.
 *
 * @since 1.3.0
 */
class HookAliases
{
    /**
     * Register deprecation aliases for renamed forms hooks.
     *
     * @since 1.3.0
     */
    public static function register(): void
    {
        if (! function_exists('deprecateHook')) {
            return;
        }

        $aliases = [
            'forms.field_types' => 'ap.forms.fieldTypes',
            'forms.field_categories' => 'ap.forms.fieldCategories',
            'forms.validation_rules' => 'ap.forms.validationRules',
            'forms.form.created' => 'ap.forms.form.created',
            'forms.form.updated' => 'ap.forms.form.updated',
            'forms.form.deleted' => 'ap.forms.form.deleted',
            'forms.submission.created' => 'ap.forms.submission.created',
            'forms.submission.updated' => 'ap.forms.submission.updated',
            'forms.submission.deleted' => 'ap.forms.submission.deleted',
            'forms.webhook_payload' => 'ap.forms.webhookPayload',
            'forms.settings_tabs' => 'ap.forms.settingsTabs',
            'forms.submission_data' => 'ap.forms.submissionData',
            'forms.export_headers' => 'ap.forms.exportHeaders',
            'forms.export_data' => 'ap.forms.exportData',
            'forms.notification_recipients' => 'ap.forms.notificationRecipients',
            'forms.notification.before_send' => 'ap.forms.notification.beforeSend',
            'forms.notification.sent' => 'ap.forms.notification.sent',
            'forms.notification_message' => 'ap.forms.notificationMessage',
        ];

        foreach ($aliases as $old => $new) {
            deprecateHook($old, $new);
        }
    }
}
