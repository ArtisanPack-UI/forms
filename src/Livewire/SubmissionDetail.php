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

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Services\SubmissionService;
use ArtisanPackUI\Forms\Support\FieldPrivacy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

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
     * Whether the administrative fields were just saved.
     *
     * @since 1.6.0
     */
    public bool $administrativeFieldsSaved = false;

    /**
     * Initialize the component.
     */
    public function mount( FormSubmission $submission ): void
    {
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
        return FormSubmission::with( ['form', 'values.field', 'uploads'] )
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
     */
    public function updatedAdminNotes(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            $submission->update( ['admin_notes' => $this->adminNotes] );
        }
    }

    /**
     * Validates and saves the administrative field values.
     *
     * Requires the `update` ability on the submission.
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
        $rules    = $service->buildAdministrativeValidationRules( $fields, $submission );

        if ( [] !== $rules ) {
            $this->withValidator( function ( Validator $validator ) use ( $resolved ): void {
                $validator->setData( array_replace( $validator->getData(), ['formData' => $resolved] ) );
            } )->validate( $rules, $service->buildValidationMessages( $this->administrativeFields ) );
        }

        $service->updateAdministrativeValues( $submission, array_intersect_key( $resolved, $fields->keyBy( 'name' )->all() ) );

        $this->administrativeFieldsSaved = true;

        unset( $this->submission );
    }

    /**
     * Toggle the starred status.
     */
    public function toggleStar(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            $submission->toggleStar();
            unset( $this->submission ); // Clear cached computed property
        }
    }

    /**
     * Toggle the spam status.
     */
    public function toggleSpam(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            $submission->toggleSpam();
            unset( $this->submission ); // Clear cached computed property
        }
    }

    /**
     * Mark the submission as read.
     */
    public function markAsRead(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            $submission->markAsRead();
            unset( $this->submission ); // Clear cached computed property
        }
    }

    /**
     * Mark the submission as unread.
     */
    public function markAsUnread(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
            $submission->markAsUnread();
            unset( $this->submission ); // Clear cached computed property
        }
    }

    /**
     * Delete the submission.
     */
    public function delete(): void
    {
        $submission = FormSubmission::find( $this->submissionId );

        if ( $submission ) {
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
            if ( ! $this->isEditableAdministrativeField( $field ) ) {
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
