{{--
    An administrative file field on the submission detail (since 1.6.0).

    Lists the stored file(s), each with a download link and a remove button
    that asks for confirmation, then a labelled file input bound to
    `adminFiles.{name}`. A file chosen there replaces the stored file(s) when
    the administrative fields are saved. A polite live region announces when
    the upload starts, its progress and when it finishes or fails.
--}}

@php
    $inputId = 'admin-file-' . $field->name;
    $label = filled($field->label) ? $field->label : \Illuminate\Support\Str::headline($field->name);
    $multiple = $field->allowsMultipleFiles();
    $allowedTypes = $field->getFileAllowedTypes();
    $accept = ! empty($allowedTypes) ? implode(',', array_map(fn ($type) => '.' . $type, $allowedTypes)) : null;
    $limitsHint = $field->getFileLimitsHint();
    $error = $errors->first('adminFiles.' . $field->name) ?: $errors->first('adminFiles.' . $field->name . '.*');
    $describedBy = trim(($limitsHint ? $inputId . '-limits ' : '') . ($error ? $inputId . '-error' : ''));
@endphp

<div
    class="form-control w-full gap-2"
    x-data="{
        progress: null,
        announcement: '',
        messages: @js([
            'start' => __( 'Uploading files for :field…', ['field' => $label] ),
            'progress' => __( 'Upload :percent% complete.' ),
            'finish' => __( 'Upload finished. Save the administrative fields to keep it.' ),
            'error' => __( 'The upload failed.' ),
        ]),
    }"
    x-on:livewire-upload-start="progress = 0; announcement = messages.start"
    x-on:livewire-upload-progress="progress = $event.detail.progress; if (progress % 25 === 0) announcement = messages.progress.replace(':percent', progress)"
    x-on:livewire-upload-finish="progress = null; announcement = messages.finish"
    x-on:livewire-upload-error="progress = null; announcement = messages.error"
>
    <label class="label" for="{{ $inputId }}">
        <span class="label-text font-medium">
            {{ $label }}
            @if ($field->is_required)
                <span class="text-error" aria-hidden="true">*</span>
            @endif
        </span>
    </label>

    @if (count($uploads) > 0)
        <ul class="flex flex-col gap-2" aria-label="{{ __( 'Current files for :field', ['field' => $label] ) }}">
            @foreach ($uploads as $upload)
                <li class="flex items-center gap-3 rounded-box bg-base-200 p-2" wire:key="admin-upload-{{ $upload->id }}">
                    <x-artisanpack-icon name="o-paper-clip" class="h-5 w-5 opacity-40" aria-hidden="true" />
                    <a href="{{ route('forms.uploads.download', [$this->form, $this->submission, $upload]) }}"
                       class="link link-primary min-w-0 grow truncate text-sm"
                       target="_blank">
                        {{ $upload->original_name }}
                    </a>
                    <span class="shrink-0 text-xs opacity-60">{{ $upload->humanFileSize() }}</span>
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        wire:click="removeAdministrativeUpload({{ $upload->id }})"
                        wire:confirm="{{ __( 'Remove :name? The file will be deleted.', ['name' => $upload->original_name] ) }}"
                        aria-label="{{ __( 'Remove :name', ['name' => $upload->original_name] ) }}"
                    >
                        <x-artisanpack-icon name="o-trash" class="h-4 w-4" aria-hidden="true" />
                        <span aria-hidden="true">{{ __( 'Remove' ) }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
        <p class="text-xs opacity-60">
            {{ $multiple ? __( 'Choosing new files replaces the current ones.' ) : __( 'Choosing a new file replaces the current one.' ) }}
        </p>
    @endif

    <input
        type="file"
        id="{{ $inputId }}"
        wire:model="adminFiles.{{ $field->name }}"
        class="file-input file-input-bordered w-full @if ($error) file-input-error @endif"
        @if ($multiple) multiple @endif
        @if ($accept) accept="{{ $accept }}" @endif
        @if ($field->is_required && count($uploads) === 0) aria-required="true" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
    />

    <progress
        class="progress progress-primary w-full"
        max="100"
        x-show="progress !== null"
        x-cloak
        x-bind:value="progress ?? 0"
        aria-label="{{ __( 'Upload progress for :field', ['field' => $label] ) }}"
    ></progress>

    <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="announcement"></div>

    @if ($limitsHint)
        <p class="text-xs opacity-60" id="{{ $inputId }}-limits">{{ $limitsHint }}</p>
    @endif

    @if ($error)
        <p class="text-sm text-error" id="{{ $inputId }}-error">{{ $error }}</p>
    @endif
</div>
