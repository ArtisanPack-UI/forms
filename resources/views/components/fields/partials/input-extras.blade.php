{{--
    The notes shown under a text-like input (since 1.6.0), each referenced by
    the input's `aria-describedby` (see FormField::getDescribedBy()):

    - the input mask's expected format (`mask-{name}`);
    - the number field's prefix and suffix, for screen readers only, since the
      input component shows them beside the input (`affix-{name}`).

    The character counter wraps the input instead (see counter.blade.php).
--}}
@if(null !== ($maskHint = $field->getInputMaskHint()))
    <p class="label-text-alt text-base-content/70 mt-1" id="{{ $field->domId('mask') }}">{{ $maskHint }}</p>
@endif

@if(null !== ($affixDescription = $field->getAffixDescription()))
    <span class="sr-only" id="{{ $field->domId('affix') }}">{{ $affixDescription }}</span>
@endif
