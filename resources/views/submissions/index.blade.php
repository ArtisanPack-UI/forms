@extends('forms::layouts.app')

@section('title', __( ':name Submissions', ['name' => $form->name] ))

@section('header')
    <div class="flex items-center justify-between">
        <div>
            <x-artisanpack-breadcrumbs :items="[
                ['label' => __( 'Forms' ), 'link' => route('forms.index')],
                ['label' => $form->name, 'link' => route('forms.edit', $form)],
                ['label' => __( 'Submissions' )],
            ]" class="breadcrumbs text-sm" />
            <h1 class="mt-2 text-2xl font-bold">{{ __( ':name Submissions', ['name' => $form->name] ) }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <x-artisanpack-button
                link="{{ route('forms.submissions.export', $form) }}"
                :label="__( 'Export All' )"
                icon="o-arrow-down-tray"
                color="ghost"
            />
            <x-artisanpack-button
                link="{{ route('forms.edit', $form) }}"
                :label="__( 'Edit Form' )"
                color="primary"
            />
        </div>
    </div>
@endsection

@section('content')
    <livewire:submissions-list :form-id="$form->id" />
@endsection
