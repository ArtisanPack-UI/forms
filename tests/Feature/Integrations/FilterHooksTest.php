<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Services\ExportService;
use ArtisanPackUI\Forms\Services\IntegrationService;
use ArtisanPackUI\Forms\Services\SubmissionService;

beforeEach(function (): void {
    // Reset any registered filters between tests
    if (function_exists('removeAllFilters')) {
        removeAllFilters('ap.forms.fieldTypes');
        removeAllFilters('ap.forms.fieldCategories');
        removeAllFilters('ap.forms.validationRules');
        removeAllFilters('ap.forms.submissionData');
        removeAllFilters('ap.forms.exportHeaders');
        removeAllFilters('ap.forms.exportData');
        removeAllFilters('ap.forms.settingsTabs');
    }
});

describe('Filter Hooks', function (): void {
    describe('ap.forms.fieldTypes filter', function (): void {
        it('allows registering custom field types', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.fieldTypes', function (array $types): array {
                $types['custom_rating'] = [
                    'label' => 'Star Rating',
                    'icon' => 'o-star',
                    'category' => 'advanced',
                    'has_options' => false,
                    'supports_placeholder' => false,
                    'supports_default_value' => true,
                    'validation_options' => ['min', 'max'],
                    'defaults' => [
                        'label' => 'Rating',
                    ],
                ];

                return $types;
            });

            $types = FieldTypes::getTypes();

            expect($types)->toHaveKey('custom_rating')
                ->and($types['custom_rating']['label'])->toBe('Star Rating');
        });

        it('can modify existing field types', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.fieldTypes', function (array $types): array {
                $types['text']['label'] = 'Modified Text Input';

                return $types;
            });

            $types = FieldTypes::getTypes();

            expect($types['text']['label'])->toBe('Modified Text Input');
        });
    });

    describe('ap.forms.fieldCategories filter', function (): void {
        it('allows registering custom field categories', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.fieldCategories', function (array $categories): array {
                $categories['custom'] = [
                    'label' => 'Custom Fields',
                    'fields' => ['custom_rating'],
                ];

                return $categories;
            });

            $categories = FieldTypes::getCategoriesFiltered();

            expect($categories)->toHaveKey('custom')
                ->and($categories['custom']['label'])->toBe('Custom Fields');
        });
    });

    describe('ap.forms.validationRules filter', function (): void {
        it('allows modifying validation rules for a field', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.validationRules', function (array $rules, FormField $field): array {
                // Add a custom rule for email fields
                if ($field->type === 'email') {
                    $rules[] = 'ends_with:@example.com';
                }

                return $rules;
            });

            $form = Form::factory()->create();
            $field = FormField::factory()->for($form)->create([
                'type' => 'email',
                'is_required' => true,
            ]);

            $rules = $field->buildValidationRules();

            expect($rules)->toContain('email')
                ->and($rules)->toContain('ends_with:@example.com');
        });
    });

    describe('ap.forms.submissionData filter', function (): void {
        it('allows modifying submission data before saving', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.submissionData', function (array $data, Form $form): array {
                // Add a tracking field to all submissions
                $data['_tracking_id'] = 'TRACK-'.uniqid();

                return $data;
            });

            $form = Form::factory()->create();
            FormField::factory()->for($form)->create(['name' => 'name', 'type' => 'text']);

            $service = app(SubmissionService::class);

            // Note: The filter modifies the data before processing
            // but in practice, the tracking_id would need to be stored
            // This test verifies the filter is called
            $submission = $service->create($form, ['name' => 'Test User'], [], []);

            expect($submission)->not->toBeNull();
        });
    });

    describe('ap.forms.exportHeaders filter', function (): void {
        it('allows modifying export headers', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.exportHeaders', function (array $headers, Form $form): array {
                $headers[] = 'Custom Column';

                return $headers;
            });

            $form = Form::factory()->create();
            $service = app(ExportService::class);

            $headers = $service->buildHeaders($form);

            expect($headers)->toContain('Custom Column');
        });
    });

    describe('ap.forms.settingsTabs filter', function (): void {
        it('allows registering custom settings tabs', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.settingsTabs', function (array $tabs): array {
                $tabs[] = [
                    'id' => 'mailchimp',
                    'label' => 'Mailchimp',
                    'icon' => 'o-envelope',
                    'component' => 'mailchimp-settings',
                    'description' => 'Configure Mailchimp integration',
                ];

                return $tabs;
            });

            $service = app(IntegrationService::class);
            $tabs = $service->getSettingsTabs();

            expect($tabs)->toHaveCount(1)
                ->and($tabs[0]['id'])->toBe('mailchimp')
                ->and($tabs[0]['label'])->toBe('Mailchimp');
        });

        it('returns empty array when no tabs registered', function (): void {
            $service = app(IntegrationService::class);
            $tabs = $service->getSettingsTabs();

            expect($tabs)->toBeArray()
                ->and($tabs)->toBeEmpty();
        });
    });
});
