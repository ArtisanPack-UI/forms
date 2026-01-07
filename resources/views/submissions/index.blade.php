@extends('forms::layouts.app')

@section('title', $form->name . ' Submissions')

@section('header')
    <div class="flex items-center justify-between">
        <div>
            <x-artisanpack-breadcrumbs :items="[
                ['label' => 'Forms', 'link' => route('forms.index')],
                ['label' => $form->name, 'link' => route('forms.edit', $form)],
                ['label' => 'Submissions'],
            ]" class="breadcrumbs text-sm" />
            <h1 class="mt-2 text-2xl font-bold">{{ $form->name }} Submissions</h1>
        </div>
        <div class="flex items-center gap-3">
            <x-artisanpack-button
                link="{{ route('forms.submissions.export', $form) }}"
                label="Export All"
                icon="o-arrow-down-tray"
                color="ghost"
            />
            <x-artisanpack-button
                link="{{ route('forms.edit', $form) }}"
                label="Edit Form"
                color="primary"
            />
        </div>
    </div>
@endsection

@section('content')
    <livewire:submissions-list :form-id="$form->id" />
@endsection
