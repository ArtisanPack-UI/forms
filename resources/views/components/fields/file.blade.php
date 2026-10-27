{{--
    A file input. With "Allow multiple files" on (since 1.6.0) it is a drop
    zone around a normal, keyboard-operable `multiple` file input: files
    dropped on the zone or chosen with the input are uploaded one by one,
    each under its own key in `files.{name}` so it can be removed on its own
    (FormRenderer::removeFile()). Each file shows its progress and a remove
    button; a polite live region announces when uploads start and finish.
    The list and live region are managed by Alpine, so Livewire leaves them
    alone (`wire:ignore`). Since 1.6.0 the list starts with the files already
    uploaded (`$files`), so it survives a step change or a show/hide; a
    single-file field shows its uploaded file with a remove button.

    Since 1.6.0 the note under the input lists the allowed types next to the
    size limits (FormField::getFileLimitsHint()), and the input's
    `aria-describedby` references it.
--}}
@props(['field', 'error' => null, 'files' => null])

@php
    $uploadedFiles = collect(is_array($files) ? $files : [])
        ->filter(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile);
    $uploadedFile = $files instanceof \Illuminate\Http\UploadedFile ? $files : null;
    $allowedTypes = $field->getFileAllowedTypes();
    $accept = !empty($allowedTypes) ? implode(',', array_map(fn($type) => '.' . $type, $allowedTypes)) : null;
    $multiple = $field->allowsMultipleFiles();
    $maxFiles = $field->getFileMaxCount();
    $limitsHint = $field->getFileLimitsHint();
@endphp

@component('forms::components.field-layout', ['field' => $field, 'error' => $error, 'labelFor' => 'field-' . $field->name])
@if($multiple)
<div
    x-data="{
        items: @js($uploadedFiles->map(fn ($file, $key) => [
            'key' => (string) $key,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'progress' => 100,
            'status' => 'done',
        ])->values()->all()),
        dragging: false,
        announcement: '',
        maxFiles: @js($maxFiles),
        field: @js($field->name),
        messages: @js([
            'uploading' => __( 'Uploading :name…' ),
            'uploaded' => __( ':name uploaded.' ),
            'failed' => __( ':name could not be uploaded.' ),
            'removed' => __( ':name removed.' ),
            'tooMany' => __( 'You may upload no more than :max files.' ),
        ]),
        say(message, name) {
            this.announcement = message.replace(':name', name).replace(':max', this.maxFiles ?? '');
        },
        add(fileList) {
            for (const file of Array.from(fileList ?? [])) {
                if (this.maxFiles !== null && this.items.length >= this.maxFiles) {
                    this.say(this.messages.tooMany, '');

                    break;
                }

                this.upload(file);
            }
        },
        upload(file) {
            const item = { key: 'f' + Math.random().toString(36).slice(2, 12), name: file.name, size: file.size, progress: 0, status: 'uploading' };

            this.items.push(item);
            this.say(this.messages.uploading, file.name);

            this.$wire.upload(
                'files.' + this.field + '.' + item.key,
                file,
                () => { this.update(item.key, { progress: 100, status: 'done' }); this.say(this.messages.uploaded, file.name); },
                () => { this.update(item.key, { status: 'failed' }); this.say(this.messages.failed, file.name); },
                (event) => { this.update(item.key, { progress: event.detail.progress }); },
            );
        },
        update(key, changes) {
            const item = this.items.find((entry) => entry.key === key);

            if (item) {
                Object.assign(item, changes);
            }
        },
        remove(item) {
            if (item.status === 'uploading') {
                this.$wire.cancelUpload('files.' + this.field + '.' + item.key);
            } else if (item.status === 'done') {
                this.$wire.removeFile(this.field, item.key);
            }

            this.items = this.items.filter((entry) => entry.key !== item.key);
            this.say(this.messages.removed, item.name);
            this.$refs.input.focus();
        },
        size(bytes) {
            return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
        },
    }"
    x-on:form-reset.window="items = []"
    class="flex flex-col gap-3 {{ $field->css_classes }}"
>
    <div
        class="rounded-box border-2 border-dashed p-6 text-center transition-colors"
        x-bind:class="dragging ? 'border-primary bg-primary/5' : 'border-base-300'"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="dragging = false; add($event.dataTransfer.files)"
    >
        <p class="mb-3 text-sm text-base-content/70">{{ __( 'Drag and drop files here, or choose them below.' ) }}</p>
        <input
            type="file"
            multiple
            x-ref="input"
            id="field-{{ $field->name }}"
            class="file-input file-input-bordered w-full @if($error) file-input-error @endif"
            x-on:change="add($event.target.files); $event.target.value = ''"
            @if($accept) accept="{{ $accept }}" @endif
            @if($field->is_required) aria-required="true" @endif
            @if($error) aria-invalid="true" @endif
            @unless(filled($field->label)) aria-label="{{ \Illuminate\Support\Str::headline($field->name) }}" @endunless
            @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
        />
    </div>

    <ul wire:ignore class="flex flex-col gap-2" x-show="items.length > 0" aria-label="{{ __( 'Files for :field', ['field' => filled($field->label) ? $field->label : \Illuminate\Support\Str::headline($field->name)] ) }}">
        <template x-for="item in items" x-bind:key="item.key">
            <li class="flex items-center gap-3 rounded-box bg-base-200 p-2">
                <div class="min-w-0 grow">
                    <p class="truncate text-sm font-medium" x-text="item.name"></p>
                    <div class="flex items-center gap-2">
                        <progress
                            class="progress w-full"
                            x-bind:class="item.status === 'failed' ? 'progress-error' : 'progress-primary'"
                            max="100"
                            x-bind:value="item.progress"
                            x-bind:aria-label="@js(__( 'Upload progress for :name' )).replace(':name', item.name)"
                        ></progress>
                        <span class="shrink-0 text-xs text-base-content/60" x-text="item.status === 'failed' ? @js(__( 'Failed' )) : size(item.size)"></span>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-ghost btn-sm btn-square"
                    x-on:click="remove(item)"
                    x-bind:aria-label="@js(__( 'Remove :name' )).replace(':name', item.name)"
                >
                    <x-artisanpack-icon name="o-x-mark" class="h-4 w-4" aria-hidden="true" />
                </button>
            </li>
        </template>
    </ul>

    <div wire:ignore class="sr-only" aria-live="polite" aria-atomic="true" x-text="announcement"></div>
</div>

@if($limitsHint)
    <p class="label" id="limits-{{ $field->name }}">
        <span class="label-text-alt text-base-content/60">{{ $limitsHint }}</span>
    </p>
@endif
@else
<input
    type="file"
    wire:model="files.{{ $field->name }}"
    id="field-{{ $field->name }}"
    class="file-input file-input-bordered w-full {{ $field->css_classes }} @error('files.' . $field->name) file-input-error @enderror"
    @if($accept) accept="{{ $accept }}" @endif
    @if($field->is_required) @if(null === $uploadedFile) required @endif aria-required="true" @endif
    @if($error) aria-invalid="true" @endif
    @unless(filled($field->label)) aria-label="{{ \Illuminate\Support\Str::headline($field->name) }}" @endunless
    @if($field->getDescribedBy($error)) aria-describedby="{{ $field->getDescribedBy($error) }}" @endif
/>

@if($uploadedFile)
    <div class="mt-2 flex items-center gap-3 rounded-box bg-base-200 p-2" data-forms-uploaded-file>
        <p class="min-w-0 grow truncate text-sm">
            <span class="sr-only">{{ __( 'Uploaded file:' ) }}</span>
            {{ $uploadedFile->getClientOriginalName() }}
        </p>
        <button
            type="button"
            class="btn btn-ghost btn-sm btn-square"
            wire:click="$set(@js('files.' . $field->name), null)"
            aria-label="{{ __( 'Remove :name', ['name' => $uploadedFile->getClientOriginalName()] ) }}"
        >
            <x-artisanpack-icon name="o-x-mark" class="h-4 w-4" aria-hidden="true" />
        </button>
    </div>
@endif

<div wire:loading wire:target="files.{{ $field->name }}" class="mt-2">
    <span class="loading loading-spinner loading-sm"></span>
    <span class="text-sm text-base-content/70">{{ __( 'Uploading...' ) }}</span>
</div>

@if($limitsHint)
    <p class="label" id="limits-{{ $field->name }}">
        <span class="label-text-alt text-base-content/60">{{ $limitsHint }}</span>
    </p>
@endif
@endif
@endcomponent
