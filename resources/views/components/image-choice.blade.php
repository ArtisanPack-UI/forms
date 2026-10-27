{{--
    One image choice of a radio or checkbox group (since 1.6.0): a card with
    the option's image above its input and label (see ChoiceImages). The
    input (the slot) is named by the label alone through `aria-labelledby`,
    so the label stays the accessible name; the image's alt text describes
    what it shows. An option without an image shows a blank placeholder.

    Variables:
    - $image   The image URL, or null.
    - $alt     The image's alt text.
    - $label   The option label.
    - $labelId The label's ID, which the input's `aria-labelledby` names.
--}}
<label class="flex cursor-pointer flex-col overflow-hidden rounded-box border-2 border-base-300 bg-base-100 transition-colors hover:border-primary/50 has-[:checked]:border-primary has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
    @if(null !== $image)
        <img src="{{ $image }}" alt="{{ $alt }}" class="aspect-square w-full bg-base-200 object-cover" loading="lazy" />
    @else
        <span class="block aspect-square w-full bg-base-200" aria-hidden="true"></span>
    @endif
    <span class="flex items-center gap-2 p-3">
        {{ $slot }}
        <span id="{{ $labelId }}" class="label-text">{{ $label }}</span>
    </span>
</label>
