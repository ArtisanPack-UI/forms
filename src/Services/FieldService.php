<?php

/**
 * Field service.
 *
 * Business logic layer for field CRUD operations. Handles creating,
 * updating, deleting, duplicating, and reordering form fields.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Services;

use ArtisanPackUI\Forms\Config\FieldTypes;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Field service class.
 *
 * Business logic layer for field CRUD operations. Handles creating,
 * updating, deleting, duplicating, and reordering form fields.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class FieldService
{
    /**
     * Creates a new field for a form with defaults based on type.
     *
     * @since 1.0.0
     *
     * @param Form                 $form   The form to add the field to.
     * @param string               $type   The field type.
     * @param int|null             $stepId The step ID for multi-step forms.
     * @param array<string, mixed> $data   Additional data to override defaults.
     *
     * @return FormField The created field.
     */
    public function create( Form $form, string $type, ?int $stepId = null, array $data = [] ): FormField
    {
        if ( ! FieldTypes::typeExists( $type ) ) {
            throw new InvalidArgumentException( "Invalid field type: {$type}" );
        }

        $defaults     = FieldTypes::getDefaults( $type );
        $maxSortOrder = $this->getMaxSortOrder( $form, $stepId );

        // Use custom label from data if provided, otherwise use defaults
        $label = $data['label'] ?? $defaults['label'] ?? 'New Field';

        $fieldData = array_merge( [
            'form_id'      => $form->id,
            'step_id'      => $stepId,
            'uuid'         => Str::uuid()->toString(),
            'type'         => $type,
            'name'         => $this->generateFieldName( $form, $label ),
            'label'        => $label,
            'placeholder'  => $defaults['placeholder'] ?? null,
            'is_required'  => false,
            'width'        => 'full',
            'sort_order'   => $maxSortOrder + 1,
            'field_config' => $defaults['field_config'] ?? null,
        ], $data );

        return FormField::create( $fieldData );
    }

    /**
     * Updates an existing field.
     *
     * @since 1.0.0
     *
     * @param FormField            $field The field to update.
     * @param array<string, mixed> $data  The updated field data.
     *
     * @throws InvalidArgumentException If an invalid field type is provided.
     * @throws RuntimeException If the field cannot be refreshed after update.
     *
     * @return FormField The updated field.
     *
     */
    public function update( FormField $field, array $data ): FormField
    {
        // Validate type if being changed
        if ( isset( $data['type'] ) && ! FieldTypes::typeExists( $data['type'] ) ) {
            throw new InvalidArgumentException( "Invalid field type: {$data['type']}" );
        }

        // If name is being changed, regenerate to ensure uniqueness
        if ( isset( $data['label'] ) && ! isset( $data['name'] ) ) {
            $data['name'] = $this->generateFieldName(
                $field->form,
                $data['label'],
                $field->id,
            );
        }

        $field->update( $data );

        $refreshed = $field->fresh();

        if ( null === $refreshed ) {
            throw new RuntimeException( "Failed to refresh field after update. Field ID: {$field->id}" );
        }

        return $refreshed;
    }

    /**
     * Deletes a field.
     *
     * @since 1.0.0
     *
     * @param FormField $field The field to delete.
     *
     * @return bool True on success.
     */
    public function delete( FormField $field ): bool
    {
        return (bool) $field->delete();
    }

    /**
     * Duplicates a field within the same form.
     *
     * @since 1.0.0
     *
     * @param FormField $field The field to duplicate.
     *
     * @return FormField The duplicated field.
     */
    public function duplicate( FormField $field ): FormField
    {
        return DB::transaction( function () use ( $field ): FormField {
            $maxSortOrder = $this->getMaxSortOrder( $field->form, $field->step_id );

            $newField       = $field->replicate();
            $newField->uuid = Str::uuid()->toString();
            $newField->name = $this->generateFieldName(
                $field->form,
                $field->label . ' (Copy)',
            );
            $newField->label      = $field->label . ' (Copy)';
            $newField->sort_order = $maxSortOrder + 1;
            $newField->save();

            return $newField;
        } );
    }

    /**
     * Reorders fields based on an array of UUIDs.
     *
     * @since 1.0.0
     *
     * @param Form                                  $form         The form containing the fields.
     * @param array<int, array{id: string}|string> $orderedUuids Array of field UUIDs in desired order.
     * @param int|null                             $stepId       The step ID for multi-step forms.
     *
     * @return void
     */
    public function reorder( Form $form, array $orderedUuids, ?int $stepId = null ): void
    {
        DB::transaction( function () use ( $form, $orderedUuids, $stepId ): void {
            $query = $form->fields();

            if ( null !== $stepId ) {
                $query->where( 'step_id', $stepId );
            } else {
                $query->whereNull( 'step_id' );
            }

            $fields = $query->get()->keyBy( 'uuid' );

            foreach ( $orderedUuids as $index => $item ) {
                // Handle both string UUIDs and objects with 'id' property (from drag-and-drop)
                $uuid = is_array( $item ) ? ( $item['id'] ?? null ) : $item;

                if ( null !== $uuid && isset( $fields[ $uuid ] ) ) {
                    $fields[ $uuid ]->update( ['sort_order' => $index + 1] );
                }
            }
        } );
    }

    /**
     * Moves a field to a different step.
     *
     * @since 1.0.0
     *
     * @param FormField $field  The field to move.
     * @param int|null  $stepId The target step ID or null for no step.
     *
     * @return FormField The updated field.
     */
    public function moveToStep( FormField $field, ?int $stepId ): FormField
    {
        $maxSortOrder = $this->getMaxSortOrder( $field->form, $stepId );

        $field->update( [
            'step_id'    => $stepId,
            'sort_order' => $maxSortOrder + 1,
        ] );

        $refreshed = $field->fresh();

        return $refreshed ?? $field;
    }

    /**
     * Gets a field by its UUID within a form.
     *
     * @since 1.0.0
     *
     * @param Form   $form The form to search in.
     * @param string $uuid The field UUID.
     *
     * @return FormField|null The field or null if not found.
     */
    public function getByUuid( Form $form, string $uuid ): ?FormField
    {
        return $form->fields()->where( 'uuid', $uuid )->first();
    }

    /**
     * Gets all fields for a form, optionally filtered by step.
     *
     * @since 1.0.0
     *
     * @param Form     $form   The form to get fields from.
     * @param int|null $stepId The step ID to filter by.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, FormField> The fields collection.
     */
    public function getFields( Form $form, ?int $stepId = null ): \Illuminate\Database\Eloquent\Collection
    {
        $query = $form->fields()->orderBy( 'sort_order' );

        if ( null !== $stepId ) {
            $query->where( 'step_id', $stepId );
        } else {
            $query->whereNull( 'step_id' );
        }

        return $query->get();
    }

    /**
     * Gets the maximum sort order for fields in a form/step.
     *
     * @since 1.0.0
     *
     * @param Form     $form   The form to check.
     * @param int|null $stepId The step ID to filter by.
     *
     * @return int The maximum sort order value.
     */
    protected function getMaxSortOrder( Form $form, ?int $stepId = null ): int
    {
        $query = $form->fields();

        if ( null !== $stepId ) {
            $query->where( 'step_id', $stepId );
        } else {
            $query->whereNull( 'step_id' );
        }

        return (int) $query->max( 'sort_order' );
    }

    /**
     * Generates a unique field name based on the label.
     *
     * @since 1.0.0
     *
     * @param Form     $form      The form to check for uniqueness.
     * @param string   $label     The field label to generate name from.
     * @param int|null $excludeId The field ID to exclude from uniqueness check.
     *
     * @return string The generated unique field name.
     */
    protected function generateFieldName( Form $form, string $label, ?int $excludeId = null ): string
    {
        $baseName = Str::slug( $label, '_' );

        if ( empty( $baseName ) ) {
            $baseName = 'field';
        }

        $name    = $baseName;
        $counter = 1;

        while ( $this->fieldNameExists( $form, $name, $excludeId ) ) {
            $name = $baseName . '_' . $counter;
            $counter++;
        }

        return $name;
    }

    /**
     * Checks if a field name already exists in the form.
     *
     * @since 1.0.0
     *
     * @param Form     $form      The form to check.
     * @param string   $name      The field name to check.
     * @param int|null $excludeId The field ID to exclude from the check.
     *
     * @return bool True if the name exists.
     */
    protected function fieldNameExists( Form $form, string $name, ?int $excludeId = null ): bool
    {
        $query = $form->fields()->where( 'name', $name );

        if ( null !== $excludeId ) {
            $query->where( 'id', '!=', $excludeId );
        }

        return $query->exists();
    }
}
