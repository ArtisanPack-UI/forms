{{--
    The renderer's fields, in order, with each section's fields grouped in
    its fieldset (since 1.6.0; see Support\Sections).

    Variables:
    - $fields The fields of the current step (or the whole form), in order.

    The parent view's $formData, $hiddenFields and $errors are in scope.
--}}
@foreach(\ArtisanPackUI\Forms\Support\Sections::segments($fields) as $segment)
    @if($segment['section'])
        @include('forms::components.fields.section', [
            'field' => $segment['section'],
            'members' => $segment['fields'],
            'renderMembers' => true,
        ])
    @else
        @foreach($segment['fields'] as $field)
            @include('forms::livewire.form-renderer.field', ['field' => $field])
        @endforeach
    @endif
@endforeach
