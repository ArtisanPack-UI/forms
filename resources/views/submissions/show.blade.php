@extends('forms::layouts.app')

@section('title', $submission->submission_number)

@section('header')
    <div class="flex items-center justify-between">
        <div>
            <x-artisanpack-breadcrumbs :items="[
                ['label' => 'Forms', 'link' => route('forms.index')],
                ['label' => $form->name, 'link' => route('forms.edit', $form)],
                ['label' => 'Submissions', 'link' => route('forms.submissions.index', $form)],
                ['label' => $submission->submission_number],
            ]" class="breadcrumbs text-sm" />
            <h1 class="mt-2 text-2xl font-bold">{{ $submission->submission_number }}</h1>
        </div>
    </div>
@endsection

@section('content')
    <livewire:submission-detail :submission="$submission" />
@endsection
