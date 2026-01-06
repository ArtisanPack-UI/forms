<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FieldService
 *
 * Business logic layer for field CRUD operations. Handles creating,
 * updating, deleting, duplicating, and reordering form fields.
 *
 * @since 1.0.0
 */
class FieldService
{
    /**
     * Create a new field for a form with defaults based on type.
     *
     * @param  array<string, mixed>  $data  Additional data to override defaults.
     */
    public function create(Form $form, string $type, ?int $stepId = null, array $data = []): FormField
    {
        if (! FieldTypes::typeExists($type)) {
            throw new \InvalidArgumentException("Invalid field type: {$type}");
        }

        $defaults = FieldTypes::getDefaults($type);
        $maxSortOrder = $this->getMaxSortOrder($form, $stepId);

        // Use custom label from data if provided, otherwise use defaults
        $label = $data['label'] ?? $defaults['label'] ?? 'New Field';

        $fieldData = array_merge([
            'form_id' => $form->id,
            'step_id' => $stepId,
            'uuid' => Str::uuid()->toString(),
            'type' => $type,
            'name' => $this->generateFieldName($form, $label),
            'label' => $label,
            'placeholder' => $defaults['placeholder'] ?? null,
            'is_required' => false,
            'width' => 'full',
            'sort_order' => $maxSortOrder + 1,
            'field_config' => $defaults['field_config'] ?? null,
        ], $data);

        return FormField::create($fieldData);
    }

    /**
     * Update an existing field.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException If an invalid field type is provided.
     * @throws \RuntimeException If the field cannot be refreshed after update.
     */
    public function update(FormField $field, array $data): FormField
    {
        // Validate type if being changed
        if (isset($data['type']) && ! FieldTypes::typeExists($data['type'])) {
            throw new \InvalidArgumentException("Invalid field type: {$data['type']}");
        }

        // If name is being changed, regenerate to ensure uniqueness
        if (isset($data['label']) && ! isset($data['name'])) {
            $data['name'] = $this->generateFieldName(
                $field->form,
                $data['label'],
                $field->id
            );
        }

        $field->update($data);

        $refreshed = $field->fresh();

        if ($refreshed === null) {
            throw new \RuntimeException("Failed to refresh field after update. Field ID: {$field->id}");
        }

        return $refreshed;
    }

    /**
     * Delete a field.
     */
    public function delete(FormField $field): bool
    {
        return (bool) $field->delete();
    }

    /**
     * Duplicate a field within the same form.
     */
    public function duplicate(FormField $field): FormField
    {
        return DB::transaction(function () use ($field): FormField {
            $maxSortOrder = $this->getMaxSortOrder($field->form, $field->step_id);

            $newField = $field->replicate();
            $newField->uuid = Str::uuid()->toString();
            $newField->name = $this->generateFieldName(
                $field->form,
                $field->label.' (Copy)'
            );
            $newField->label = $field->label.' (Copy)';
            $newField->sort_order = $maxSortOrder + 1;
            $newField->save();

            return $newField;
        });
    }

    /**
     * Reorder fields based on an array of UUIDs.
     *
     * @param  array<int, string|array{id: string}>  $orderedUuids  Array of field UUIDs in desired order (can be strings or objects with 'id' key).
     */
    public function reorder(Form $form, array $orderedUuids, ?int $stepId = null): void
    {
        DB::transaction(function () use ($form, $orderedUuids, $stepId): void {
            $query = $form->fields();

            if ($stepId !== null) {
                $query->where('step_id', $stepId);
            } else {
                $query->whereNull('step_id');
            }

            $fields = $query->get()->keyBy('uuid');

            foreach ($orderedUuids as $index => $item) {
                // Handle both string UUIDs and objects with 'id' property (from drag-and-drop)
                $uuid = is_array($item) ? ($item['id'] ?? null) : $item;

                if ($uuid !== null && isset($fields[$uuid])) {
                    $fields[$uuid]->update(['sort_order' => $index + 1]);
                }
            }
        });
    }

    /**
     * Move a field to a different step.
     */
    public function moveToStep(FormField $field, ?int $stepId): FormField
    {
        $maxSortOrder = $this->getMaxSortOrder($field->form, $stepId);

        $field->update([
            'step_id' => $stepId,
            'sort_order' => $maxSortOrder + 1,
        ]);

        $refreshed = $field->fresh();

        return $refreshed ?? $field;
    }

    /**
     * Get a field by its UUID within a form.
     */
    public function getByUuid(Form $form, string $uuid): ?FormField
    {
        return $form->fields()->where('uuid', $uuid)->first();
    }

    /**
     * Get all fields for a form, optionally filtered by step.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, FormField>
     */
    public function getFields(Form $form, ?int $stepId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $form->fields()->orderBy('sort_order');

        if ($stepId !== null) {
            $query->where('step_id', $stepId);
        } else {
            $query->whereNull('step_id');
        }

        return $query->get();
    }

    /**
     * Get the maximum sort order for fields in a form/step.
     */
    protected function getMaxSortOrder(Form $form, ?int $stepId = null): int
    {
        $query = $form->fields();

        if ($stepId !== null) {
            $query->where('step_id', $stepId);
        } else {
            $query->whereNull('step_id');
        }

        return (int) $query->max('sort_order');
    }

    /**
     * Generate a unique field name based on the label.
     */
    protected function generateFieldName(Form $form, string $label, ?int $excludeId = null): string
    {
        $baseName = Str::slug($label, '_');

        if (empty($baseName)) {
            $baseName = 'field';
        }

        $name = $baseName;
        $counter = 1;

        while ($this->fieldNameExists($form, $name, $excludeId)) {
            $name = $baseName.'_'.$counter;
            $counter++;
        }

        return $name;
    }

    /**
     * Check if a field name already exists in the form.
     */
    protected function fieldNameExists(Form $form, string $name, ?int $excludeId = null): bool
    {
        $query = $form->fields()->where('name', $name);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
