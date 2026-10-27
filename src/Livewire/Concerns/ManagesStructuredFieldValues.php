<?php

/**
 * List and ranking actions shared by the Livewire components that render field partials.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Livewire\Concerns;

use ArtisanPackUI\Forms\FieldTypes\ListFieldType;
use ArtisanPackUI\Forms\FieldTypes\RankingFieldType;
use ArtisanPackUI\Forms\Models\FormField;

/**
 * Adds the list row and ranking actions the list and ranking field partials call.
 *
 * The partials call `addListRow()`, `removeListRow()` and `moveRankingItem()`
 * on whichever component renders them: the public FormRenderer, and the
 * SubmissionDetail page for administrative fields. Both keep the values in
 * `$formData`, keyed by field name. The component decides which fields the
 * actions may change (structuredValueField()) and what happens after a change
 * (structuredValueChanged()).
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.6.0
 */
trait ManagesStructuredFieldValues
{
    /**
     * Adds an empty row to a list field, up to its max rows.
     *
     * Dispatches `forms-list-row-added` with the field and the new row's
     * index, so the partial can focus the row and announce it.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The list field's name.
     */
    public function addListRow( string $fieldName ): void
    {
        $field = $this->structuredValueField( $fieldName, ListFieldType::TYPE );

        if ( null === $field ) {
            return;
        }

        $rows = $this->listRows( $field );

        if ( count( $rows ) >= ListFieldType::maxRows( $field ) ) {
            return;
        }

        $rows[]                       = array_fill_keys( array_column( ListFieldType::columns( $field ), 'key' ), '' );
        $this->formData[ $fieldName ] = $rows;

        $this->structuredValueChanged( $fieldName );

        $this->dispatch( 'forms-list-row-added', field: $fieldName, index: count( $rows ) - 1 );
    }

    /**
     * Removes a row from a list field, keeping one empty row when it was the last.
     *
     * Clears the field's errors, since the rows after it move up, and
     * dispatches `forms-list-row-removed` with the field and the removed row's
     * index.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The list field's name.
     * @param  int  $index  The row's index.
     */
    public function removeListRow( string $fieldName, int $index ): void
    {
        $field = $this->structuredValueField( $fieldName, ListFieldType::TYPE );

        if ( null === $field ) {
            return;
        }

        $rows = $this->listRows( $field );

        if ( ! array_key_exists( $index, $rows ) ) {
            return;
        }

        unset( $rows[ $index ] );

        $this->formData[ $fieldName ] = [] === $rows
            ? [array_fill_keys( array_column( ListFieldType::columns( $field ), 'key' ), '' )]
            : array_values( $rows );

        $this->structuredValueChanged( $fieldName );

        foreach ( $this->getErrorBag()->keys() as $key ) {
            if ( "formData.{$fieldName}" === $key || str_starts_with( (string) $key, "formData.{$fieldName}." ) ) {
                $this->resetValidation( $key );
            }
        }

        $this->dispatch( 'forms-list-row-removed', field: $fieldName, index: $index );
    }

    /**
     * Moves one choice of a ranking field to a new position.
     *
     * Positions count from 0. Dispatches `forms-ranking-moved` with the
     * field, the choice's value and its new position, so the partial can keep
     * focus on the moved choice and announce where it went. Read-only
     * rankings are not changed.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The ranking field's name.
     * @param  int  $from  The choice's position.
     * @param  int  $to  The position to move it to.
     */
    public function moveRankingItem( string $fieldName, int $from, int $to ): void
    {
        $field = $this->structuredValueField( $fieldName, RankingFieldType::TYPE );

        if ( null === $field || $field->isReadOnly() ) {
            return;
        }

        $order = RankingFieldType::order( $field, $this->formData[ $fieldName ] ?? [] );
        $moved = RankingFieldType::move( $order, $from, $to );

        if ( $moved === $order ) {
            return;
        }

        $this->formData[ $fieldName ] = $moved;

        $this->structuredValueChanged( $fieldName );

        $this->resetValidation( "formData.{$fieldName}" );

        $this->dispatch( 'forms-ranking-moved', field: $fieldName, value: $moved[ $to ], position: $to );
    }

    /**
     * Gets a field the list and ranking actions may change.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The field's name.
     * @param  string  $type  The field type the action expects (`list` or `ranking`).
     *
     * @return FormField|null The field, or null when the component has no such field of that type.
     */
    abstract protected function structuredValueField( string $fieldName, string $type ): ?FormField;

    /**
     * Runs after a list or ranking action changed a field's value.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The field's name.
     */
    abstract protected function structuredValueChanged( string $fieldName ): void;

    /**
     * Gets a list field's rows from the form data, numbered from 0.
     *
     * A list with no rows yet has the rows the partial shows for it
     * (ListFieldType::initialRows()), so adding a row adds to those.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The list field.
     *
     * @return list<array<string, mixed>> The rows.
     */
    protected function listRows( FormField $field ): array
    {
        $rows = $this->formData[ $field->name ] ?? [];
        $rows = is_array( $rows ) ? array_values( array_filter( $rows, 'is_array' ) ) : [];

        return [] === $rows ? ListFieldType::initialRows( $field ) : $rows;
    }
}
