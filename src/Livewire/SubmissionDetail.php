<?php

/**
 * Submission detail Livewire component.
 *
 * Displays detailed information about a form submission including
 * field values, metadata, and admin actions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Livewire;

use ArtisanPackUI\Forms\Livewire\Concerns\ManagesStructuredFieldValues;
use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Submission detail Livewire component class.
 *
 * Displays detailed information about a form submission including
 * field values, metadata, and admin actions.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 *
 * @since      1.0.0
 */
class SubmissionDetail extends Component
{
    use ManagesStructuredFieldValues;
    use WithFileUploads;

    /**
     * The submission ID.
     *
     * Locked so the client cannot point the component's actions at another
     * submission.
     */
    #[Locked]
    public int $submissionId;

    /**
     * The admin notes text.
     */
    public string $adminNotes = '';

    /**
     * Values of the form's administrative fields, keyed by field name.
     *
     * Named like the renderer's property so the field partials bind to it.
     *
     * @since 1.6.0
     *
     * @var array<string, mixed>
     */
    public array $formData = [];

    /**
     * Text typed into an administrative field's "Other" choice, keyed by field name.
     *
     * @since 1.6.0
     *
     * @var array<string, string>
     */
    public array $otherValues = [];

    /**
     * Files uploaded to the administrative file fields, keyed by field name.
     *
     * A file replaces the field's stored file(s) when the fields are saved;
     * a multi-file field takes a list.
     *
     * @since 1.6.0
     *
     * @var array<string, mixed>
     */
    public array $adminFiles = [];

    /**
     * The name of the administrative file just removed, announced to screen readers.
     *
     * @since 1.6.0
     */
    public ?string $removedFileName = null;

    /**
     * Whether the administrative fields were just saved.
     *
     * @since 1.6.0
     */
    public bool $administrativeFieldsSaved = false;

    /**
     * Initialize the component.
     *
     * Requires the `view` ability on the submission (since 1.6.0), which
     * also covers marking it read.
     */
    public function mount( FormSubmission $submission ): void
    {
        Gate::authorize( 'view', $submission );

        $this->submissionId = $submission->id;
        $this->adminNotes   = $submission->admin_notes ?? '';

        $this->fillAdministrativeValues( $submission );

        // Mark as read when viewed
        if ( ! $submission->is_read ) {
            $submission->markAsRead();
        }
    }

    /**
     * Get the submission model.
     */
    #[Computed]
    public function submission(): ?FormSubmission
    {
        return FormSubmission::with( ['form', 'values.field', 'uploads.field'] )
            ->find( $this->submissionId );
    }

    /**
     * Get the form model.
     */
    #[Computed]
    public function form(): ?Form
    {
        return $this->submission?->form;
    }

    /**
     * Get the form's administrative fields, which admins fill in here.
     *
     * Fields masked in the admin are left out unless the user may reveal
     * them, since the input would show the stored value.
     *
     * @since 1.6.0
     *
     * @return Collection<int, FormField>
     */
    #[Computed]
    public function administrativeFields(): Collection
    {
        $form = $this->form;

        if ( null === $form ) {
            return new Collection();
        }

        return $form->fields()
            ->orderBy( 'sort_order' )
            ->get()
            ->filter( fn ( FormField $field ): bool => $this->isEditableAdministrativeField( $field ) )
            ->each( fn ( FormField $field ): FormField => $field->withSettingsForm( $form ) )
            ->values();
    }

    /**
     * Get the stored files of each administrative file field, keyed by field ID.
     *
     * @since 1.6.0
     *
     * @return array<int, list<FormUpload>>
     */
    #[Computed]
    public function administrativeFileUploads(): array
    {
        $submission = $this->submission;
        $uploads    = [];

        if ( null === $submission ) {
            return [];
        }

        $service = app( SubmissionService::class );

        foreach ( $this->administrativeFields as $field ) {
            if ( $field->is_file_field ) {
                $uploads[ $field->id ] = $service->administrativeFileValue( $submission, $field )?->getUploads() ?? [];
            }
        }

        return $uploads;
    }

    /**
     * Get the uploads the attachments list shows.
     *
     * Files of "Do not store" fields and files masked from the user are
     * left out (since 1.6.0).
     *
     * @return SupportCollection<int, FormUpload>
     */
    #[Computed]
    public function attachments(): SupportCollection
    {
        return FieldPrivacy::visibleUploads( $this->submission?->uploads ?? [] );
    }

    /**
     * Whether the current user may see values of fields masked in the admin.
     *
     * @since 1.6.0
     */
    #[Computed]
    public function canRevealMaskedValues(): bool
    {
        return FieldPrivacy::canReveal();
    }

    /**
     * Get the previous submission.
     */
    #[Computed]
    public function previousSubmission(): ?FormSubmission
    {
        if ( ! $this->submission ) {
            return null;
        }

        return FormSubmission::where( 'form_id', $this->submission->form_id )
            ->where( 'id', '<', $this->submissionId )
            ->orderBy( 'id', 'desc' )
            ->first();
    }

    /**
     * Get the next submission.
     */
    #[Computed]
    public function nextSubmission(): ?FormSubmission
    {
        if ( ! $this->submission ) {
            return null;
        }

        return FormSubmission::where( 'form_id', $this->submission->form_id )
            ->where( 'id', '>', $this->submissionId )
            ->orderBy( 'id', 'asc' )
            ->first();
    }

    /**
     * Save admin notes (auto-save on change).
     *
     * Requires the `update` ability on the submission (since 1.6.0).
     */
    public function updatedAdminNotes(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            Gate::authorize( 'update', $submission );

            $submission->update( ['admin_notes' => $this->adminNotes] );
        }
    }

    /**
     * Validates and saves the administrative field values.
     *
     * Requires the `update` ability on the submission. Since 1.6.0, a file
     * uploaded to an administrative file field replaces the field's stored
     * file(s), checked with the field's own file rules.
     *
     * @since 1.6.0
     */
    public function saveAdministrativeFields(): void
    {
        $this->administrativeFieldsSaved = false;

        $submission = FormSubmission::with( 'form.fields' )->find( $this->submissionId );

        if ( null === $submission || null === $submission->form ) {
            return;
        }

        Gate::authorize( 'update', $submission );

        $service  = app( SubmissionService::class );
        $fields   = $submission->form->fields->filter( fn ( FormField $field ): bool => $this->isEditableAdministrativeField( $field ) );
        $resolved = $service->resolveOtherChoices( $fields, $this->formData, $this->otherValues );
        $rules    = array_merge(
            $service->buildAdministrativeValidationRules( $fields, $submission ),
            $service->buildAdministrativeFileValidationRules( $fields, $submission ),
        );

        if ( [] !== $rules ) {
            $this->withValidator( function ( Validator $validator ) use ( $resolved ): void {
                $validator->setData( array_replace( $validator->getData(), ['formData' => $resolved] ) );
            } )->validate( $rules, $this->administrativeValidationMessages( $fields ) );
        }

        $service->updateAdministrativeValues( $submission, array_intersect_key( $resolved, $fields->keyBy( 'name' )->all() ) );
        $service->updateAdministrativeFiles( $submission, array_intersect_key( $this->adminFiles, $fields->keyBy( 'name' )->all() ) );

        $this->reset( 'adminFiles', 'removedFileName' );

        $this->administrativeFieldsSaved = true;

        unset( $this->submission, $this->administrativeFileUploads, $this->attachments );
    }

    /**
     * Removes one file from an administrative file field.
     *
     * Requires the `update` ability on the submission. The file must belong
     * to the submission and to an administrative file field the user edits.
     *
     * @since 1.6.0
     *
     * @param  int  $uploadId  The upload's ID.
     */
    public function removeAdministrativeUpload( int $uploadId ): void
    {
        $submission = FormSubmission::with( 'form.fields' )->find( $this->submissionId );

        if ( null === $submission ) {
            return;
        }

        Gate::authorize( 'update', $submission );

        $upload = FormUpload::query()->where( 'submission_id', $submission->id )->find( $uploadId );
        $field  = null === $upload ? null : $submission->form?->fields->firstWhere( 'id', $upload->field_id );

        if ( null === $upload || ! $field instanceof FormField || ! $this->isEditableAdministrativeField( $field ) ) {
            return;
        }

        $name = $upload->original_name;

        if ( app( SubmissionService::class )->removeAdministrativeUpload( $submission, $upload ) ) {
            $this->removedFileName           = $name;
            $this->administrativeFieldsSaved = false;
        }

        unset( $this->submission, $this->administrativeFileUploads, $this->attachments );
    }

    /**
     * Toggle the starred status.
     *
     * Requires the `update` ability on the submission (since 1.6.0).
     */
    public function toggleStar(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            Gate::authorize( 'update', $submission );

            $submission->toggleStar();
            unset( $this->submission ); // Clear cached computed property
        }
    }

    /**
     * Toggle the spam status.
     *
     * Requires the `markAsSpam` ability on the submission (since 1.6.0).
     */
    public function toggleSpam(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            Gate::authorize( 'markAsSpam', $submission );

            $submission->toggleSpam();
            unset( $this->submission ); // Clear cached computed property
        }
    }

    /**
     * Mark the submission as read.
     *
     * Requires the `update` ability on the submission (since 1.6.0).
     */
    public function markAsRead(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            Gate::authorize( 'update', $submission );

            $submission->markAsRead();
            unset( $this->submission ); // Clear cached computed property
        }
    }

    /**
     * Mark the submission as unread.
     *
     * Requires the `update` ability on the submission (since 1.6.0).
     */
    public function markAsUnread(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            Gate::authorize( 'update', $submission );

            $submission->markAsUnread();
            unset( $this->submission ); // Clear cached computed property
        }
    }

    /**
     * Delete the submission.
     *
     * Requires the `delete` ability on the submission (since 1.6.0).
     */
    public function delete(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            Gate::authorize( 'delete', $submission );

            $formSlug = $submission->form->slug;
            $submission->delete();

            session()->flash( 'success', __( 'Submission deleted successfully.' ) );

            $this->redirect( route( 'forms.submissions.index', $formSlug ) );
        }
    }

    /**
     * Navigate to the previous submission.
     */
    public function goToPrevious(): void
    {
        if ( $this->previousSubmission ) {
            $this->redirect( route( 'forms.submissions.show', [
                $this->form->slug,
                $this->previousSubmission,
            ] ) );
        }
    }

    /**
     * Navigate to the next submission.
     */
    public function goToNext(): void
    {
        if ( $this->nextSubmission ) {
            $this->redirect( route( 'forms.submissions.show', [
                $this->form->slug,
                $this->nextSubmission,
            ] ) );
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view( 'forms::livewire.submission-detail' );
    }

    /**
     * Gets an administrative list or ranking field the user edits here.
     *
     * Used by the list and ranking actions (ManagesStructuredFieldValues),
     * which change only $formData; saveAdministrativeFields() stores it.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The field's name.
     * @param  string  $type  The field type the action expects.
     *
     * @return FormField|null The field, or null when there is no such administrative field.
     */
    protected function structuredValueField( string $fieldName, string $type ): ?FormField
    {
        $field = $this->administrativeFields->firstWhere( 'name', $fieldName );

        return $field instanceof FormField && $type === $field->type ? $field : null;
    }

    /**
     * Marks the administrative fields unsaved after a list or ranking action.
     *
     * @since 1.6.0
     *
     * @param  string  $fieldName  The field's name.
     */
    protected function structuredValueChanged( string $fieldName ): void
    {
        $this->administrativeFieldsSaved = false;
    }

    /**
     * Checks whether the current user edits a field here.
     *
     * @since 1.6.0
     *
     * @param  FormField  $field  The field.
     *
     * @return bool True for an administrative field that is not masked from the user.
     */
    protected function isEditableAdministrativeField( FormField $field ): bool
    {
        return $field->isAdministrative() && ( ! $field->isMaskedInAdmin() || FieldPrivacy::canReveal() );
    }

    /**
     * Gets the validation messages for the administrative fields.
     *
     * A file field's messages are keyed under `adminFiles`, where its files
     * are bound.
     *
     * @since 1.6.0
     *
     * @param  iterable<FormField>  $fields  The administrative fields.
     *
     * @return array<string, string> The messages.
     */
    protected function administrativeValidationMessages( iterable $fields ): array
    {
        $messages = [];

        foreach ( $fields as $field ) {
            $prefix = $field->is_file_field ? 'adminFiles' : 'formData';

            foreach ( $field->buildValidationMessages() as $rule => $message ) {
                $messages[ "{$prefix}.{$field->name}.{$rule}" ] = $message;
            }
        }

        return $messages;
    }

    /**
     * Fills $formData and $otherValues from the submission's stored administrative values.
     *
     * Text stored for an "Other" choice selects the choice and fills its input.
     *
     * @since 1.6.0
     *
     * @param  FormSubmission  $submission  The submission.
     */
    protected function fillAdministrativeValues( FormSubmission $submission ): void
    {
        $submission->loadMissing( ['form.fields', 'values'] );

        foreach ( $submission->form?->fields ?? [] as $field ) {
            // A file field shows its stored files instead (see administrativeFileUploads())
            if ( ! $this->isEditableAdministrativeField( $field ) || $field->is_file_field ) {
                continue;
            }

            $stored = $submission->values->firstWhere( 'field_id', $field->id );
            $value  = $stored?->value_array ?? $stored?->value;

            if ( in_array( $field->type, FormField::ACCEPTANCE_TYPES, true ) ) {
                $value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            } elseif ( in_array( $field->type, ['checkbox_group', 'select_multiple'], true ) ) {
                $value = is_array( $value ) ? $value : [];
            }

            if ( is_array( $value ) ) {
                foreach ( $value as $index => $item ) {
                    if ( is_scalar( $item ) && $field->isOtherChoiceValue( (string) $item ) ) {
                        $this->otherValues[ $field->name ] = (string) $item;
                        $value[ $index ]                   = FormField::OTHER_CHOICE_VALUE;
                    }
                }
            } elseif ( is_string( $value ) && $field->isOtherChoiceValue( $value ) ) {
                $this->otherValues[ $field->name ] = $value;
                $value                             = FormField::OTHER_CHOICE_VALUE;
            }

            $this->formData[ $field->name ] = $value ?? '';
        }
    }
}
