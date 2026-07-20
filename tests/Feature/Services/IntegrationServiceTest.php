<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Services\IntegrationService;

beforeEach(function (): void {
    $this->service = app(IntegrationService::class);
});

describe('IntegrationService', function (): void {
    describe('integration settings', function (): void {
        it('sets integration settings for a form', function (): void {
            $form = Form::factory()->create();

            $this->service->setIntegrationSettings($form, 'mailchimp', [
                'api_key' => 'test-api-key',
                'list_id' => 'test-list-id',
            ]);

            $form->refresh();

            expect($form->settings['integrations']['mailchimp'])->toBeArray()
                ->and($form->settings['integrations']['mailchimp']['api_key'])->toBe('test-api-key')
                ->and($form->settings['integrations']['mailchimp']['list_id'])->toBe('test-list-id');
        });

        it('gets integration setting for a form', function (): void {
            $form = Form::factory()->create([
                'settings' => [
                    'integrations' => [
                        'mailchimp' => [
                            'api_key' => 'test-api-key',
                            'list_id' => 'test-list-id',
                        ],
                    ],
                ],
            ]);

            $apiKey = $this->service->getIntegrationSetting($form, 'mailchimp', 'api_key');
            $listId = $this->service->getIntegrationSetting($form, 'mailchimp', 'list_id');
            $missing = $this->service->getIntegrationSetting($form, 'mailchimp', 'missing', 'default');

            expect($apiKey)->toBe('test-api-key')
                ->and($listId)->toBe('test-list-id')
                ->and($missing)->toBe('default');
        });

        it('gets all provider settings when key is null', function (): void {
            $form = Form::factory()->create([
                'settings' => [
                    'integrations' => [
                        'mailchimp' => [
                            'api_key' => 'test-api-key',
                            'list_id' => 'test-list-id',
                        ],
                    ],
                ],
            ]);

            $allSettings = $this->service->getIntegrationSetting($form, 'mailchimp');

            expect($allSettings)->toBeArray()
                ->and($allSettings['api_key'])->toBe('test-api-key')
                ->and($allSettings['list_id'])->toBe('test-list-id');
        });

        it('updates a specific integration setting', function (): void {
            $form = Form::factory()->create([
                'settings' => [
                    'integrations' => [
                        'mailchimp' => [
                            'api_key' => 'old-key',
                        ],
                    ],
                ],
            ]);

            $this->service->updateIntegrationSetting($form, 'mailchimp', 'api_key', 'new-key');
            $form->refresh();

            expect($form->settings['integrations']['mailchimp']['api_key'])->toBe('new-key');
        });

        it('removes integration settings for a provider', function (): void {
            $form = Form::factory()->create([
                'settings' => [
                    'integrations' => [
                        'mailchimp' => ['api_key' => 'test'],
                        'convertkit' => ['api_key' => 'test'],
                    ],
                ],
            ]);

            $this->service->removeIntegrationSettings($form, 'mailchimp');
            $form->refresh();

            expect($form->settings['integrations'])->not->toHaveKey('mailchimp')
                ->and($form->settings['integrations'])->toHaveKey('convertkit');
        });

        it('checks if form has integration configured', function (): void {
            $form = Form::factory()->create([
                'settings' => [
                    'integrations' => [
                        'mailchimp' => ['api_key' => 'test'],
                    ],
                ],
            ]);

            expect($this->service->hasIntegration($form, 'mailchimp'))->toBeTrue()
                ->and($this->service->hasIntegration($form, 'convertkit'))->toBeFalse();
        });

        it('gets list of configured providers', function (): void {
            $form = Form::factory()->create([
                'settings' => [
                    'integrations' => [
                        'mailchimp' => ['api_key' => 'test'],
                        'convertkit' => ['api_key' => 'test'],
                        'empty_provider' => [],
                    ],
                ],
            ]);

            $providers = $this->service->getConfiguredProviders($form);

            expect($providers)->toContain('mailchimp')
                ->and($providers)->toContain('convertkit')
                ->and($providers)->not->toContain('empty_provider');
        });
    });

    describe('settings tabs', function (): void {
        it('returns registered tabs from filter hook', function (): void {
            if (! function_exists('addFilter')) {
                $this->markTestSkipped('Hook system not available');
            }

            addFilter('ap.forms.settingsTabs', function (array $tabs): array {
                $tabs[] = [
                    'id' => 'tab1',
                    'label' => 'Tab 1',
                    'icon' => 'o-cog',
                    'component' => 'tab-1-component',
                ];
                $tabs[] = [
                    'id' => 'tab2',
                    'label' => 'Tab 2',
                    'icon' => 'o-envelope',
                    'component' => 'tab-2-component',
                ];

                return $tabs;
            });

            $tabs = $this->service->getSettingsTabs();

            expect($tabs)->toHaveCount(2)
                ->and($tabs[0]['id'])->toBe('tab1')
                ->and($tabs[1]['id'])->toBe('tab2');
        });
    });
});

afterEach(function (): void {
    if (function_exists('removeAllFilters')) {
        removeAllFilters('ap.forms.settingsTabs');
    }
});
