{{--
    A paragraph of descriptive text. Since 1.6.0 it keeps basic formatting
    (bold, italic, underline and links), sanitized with kses(); see
    FormField::getParagraphHtml().
--}}
@props(['field'])

<div
    class="prose prose-sm max-w-none {{ $field->css_classes }}"
    id="paragraph-{{ $field->name }}"
>
    {!! $field->getParagraphHtml() !!}
</div>
