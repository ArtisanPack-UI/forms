@extends('forms::layouts.app')

@section('title', 'All Submissions')

@section('header')
    <div class="flex items-center justify-between">
        <div>
            <x-artisanpack-breadcrumbs :items="[
                ['label' => 'Forms', 'link' => route('forms.index')],
                ['label' => 'All Submissions'],
            ]" class="breadcrumbs text-sm" />
            <h1 class="mt-2 text-2xl font-bold">All Submissions</h1>
        </div>
    </div>
@endsection

@section('content')
    <livewire:submissions-list />
@endsection
