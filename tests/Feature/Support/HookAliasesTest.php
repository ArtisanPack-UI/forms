<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Support\HookAliases;

beforeEach(function (): void {
    if (! function_exists('removeAllFilters')) {
        $this->markTestSkipped('Hook system not available');
    }

    // Wipe both old and new names so the alias plumbing is exercised
    // fresh in each test.
    $pairs = [
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

    foreach ($pairs as $old => $new) {
        removeAllFilters($old);
        removeAllFilters($new);
    }
});

describe('HookAliases', function (): void {
    it('is a no-op when deprecateHook is unavailable', function (): void {
        // Sanity: the registrar must not error under any load order.
        HookAliases::register();
        expect(true)->toBeTrue();
    });

    it('routes old filter-name subscribers through the canonical hook', function (): void {
        if (! function_exists('deprecateHook')) {
            $this->markTestSkipped('hooks < 1.3.0 has no deprecation aliasing');
        }

        HookAliases::register();

        $oldCalled = false;

        addFilter('forms.field_types', function (array $types) use (&$oldCalled): array {
            $oldCalled = true;
            $types['legacy'] = ['label' => 'Legacy'];

            return $types;
        });

        $result = applyFilters('ap.forms.fieldTypes', []);

        expect($oldCalled)->toBeTrue();
        expect($result)->toHaveKey('legacy');
    });

    it('routes old action-name subscribers through the canonical hook', function (): void {
        if (! function_exists('deprecateHook')) {
            $this->markTestSkipped('hooks < 1.3.0 has no deprecation aliasing');
        }

        HookAliases::register();

        $oldCalled = false;

        addAction('forms.form.created', function () use (&$oldCalled): void {
            $oldCalled = true;
        });

        doAction('ap.forms.form.created', new stdClass);

        expect($oldCalled)->toBeTrue();
    });
});
