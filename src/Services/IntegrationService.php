<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Models\Form;

/**
 * IntegrationService
 *
 * Business logic layer for managing third-party integrations.
 * Provides hooks for integration packages to register settings panels
 * and handles integration settings storage.
 *
 * @since 1.0.0
 */
class IntegrationService
{
    /**
     * Get all registered integration settings tabs.
     *
     * Applies the 'forms.settings_tabs' filter hook to allow
     * third-party packages to register custom settings tabs.
     *
     * Each tab should have the following structure:
     * [
     *     'id' => 'unique-tab-id',
     *     'label' => 'Tab Label',
     *     'icon' => 'o-icon-name',
     *     'component' => 'livewire-component-name',
     *     'description' => 'Optional description',
     * ]
     *
     * @return array<int, array{id: string, label: string, icon: string, component: string, description?: string}>
     */
    public function getSettingsTabs(): array
    {
        $tabs = [];

        // Apply filter hook for extensibility
        if (function_exists('applyFilters')) {
            $tabs = applyFilters('forms.settings_tabs', $tabs);
        }

        return $tabs;
    }

    /**
     * Get integration settings for a specific form and provider.
     *
     * Settings are stored in the form's `settings` JSON column
     * under the path: `settings.integrations.{provider}.{key}`
     *
     * @param  string|null  $key  Specific setting key, or null for all provider settings.
     * @return mixed The setting value, provider settings array, or default.
     */
    public function getIntegrationSetting(Form $form, string $provider, ?string $key = null, mixed $default = null): mixed
    {
        $path = "integrations.{$provider}";

        if ($key !== null) {
            $path .= ".{$key}";
        }

        return $form->getSetting($path, $default);
    }

    /**
     * Set integration settings for a specific form and provider.
     *
     * @param  array<string, mixed>  $settings  The settings to store.
     */
    public function setIntegrationSettings(Form $form, string $provider, array $settings): void
    {
        $currentSettings = $form->settings ?? [];

        if (! isset($currentSettings['integrations'])) {
            $currentSettings['integrations'] = [];
        }

        $currentSettings['integrations'][$provider] = $settings;

        $form->update(['settings' => $currentSettings]);
    }

    /**
     * Update a specific integration setting for a form.
     */
    public function updateIntegrationSetting(Form $form, string $provider, string $key, mixed $value): void
    {
        $currentSettings = $form->settings ?? [];

        if (! isset($currentSettings['integrations'])) {
            $currentSettings['integrations'] = [];
        }

        if (! isset($currentSettings['integrations'][$provider])) {
            $currentSettings['integrations'][$provider] = [];
        }

        $currentSettings['integrations'][$provider][$key] = $value;

        $form->update(['settings' => $currentSettings]);
    }

    /**
     * Remove all integration settings for a specific provider.
     */
    public function removeIntegrationSettings(Form $form, string $provider): void
    {
        $currentSettings = $form->settings ?? [];

        if (isset($currentSettings['integrations'][$provider])) {
            unset($currentSettings['integrations'][$provider]);
            $form->update(['settings' => $currentSettings]);
        }
    }

    /**
     * Check if a form has integration settings for a specific provider.
     */
    public function hasIntegration(Form $form, string $provider): bool
    {
        return ! empty($form->getSetting("integrations.{$provider}"));
    }

    /**
     * Get all integration providers configured for a form.
     *
     * @return array<int, string>
     */
    public function getConfiguredProviders(Form $form): array
    {
        $integrations = $form->getSetting('integrations', []);

        if (! is_array($integrations)) {
            return [];
        }

        return array_keys(array_filter($integrations, fn ($config) => ! empty($config)));
    }
}
