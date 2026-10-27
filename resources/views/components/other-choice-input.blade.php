{{--
    The text input revealed by a selected "Other" choice.

    Rendered straight after the choice group, so it is the next tab stop after
    the "Other" radio or checkbox. Bound to the renderer's `otherValues`.

    Variables:
    - $field The FormField.
    - $error The validation error, or null.
--}}
<div class="mt-2">
    <label for="{{ $field->domId() }}-other-text" class="sr-only">
        {{ __( ':label, please specify', ['label' => $field->getOtherChoiceLabel()] ) }}
    </label>
    <input
        type="text"
        wire:model="otherValues.{{ $field->name }}"
        id="{{ $field->domId() }}-other-text"
        class="input input-bordered w-full @if($error) input-error @endif"
        maxlength="{{ \ArtisanPackUI\Forms\Models\FormField::OTHER_CHOICE_MAX_LENGTH }}"
        placeholder="{{ __( 'Please specify' ) }}"
        required
        aria-required="true"
        @if($error) aria-invalid="true" aria-describedby="{{ $field->domId('error') }}" @endif
        @if($field->isReadOnly()) readonly @endif
    />
</div>
