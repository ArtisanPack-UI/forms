@extends('forms::layouts.app')

@section('title', __( 'All Submissions' ))

@section('header')
    <div class="flex items-center justify-between">
        <div>
            <x-artisanpack-breadcrumbs :items="[
                ['label' => __( 'Forms' ), 'link' => route('forms.index')],
                ['label' => __( 'All Submissions' )],
            ]" class="breadcrumbs text-sm" />
            <h1 class="mt-2 text-2xl font-bold">{{ __( 'All Submissions' ) }}</h1>
        </div>
    </div>
@endsection

@section('content')
    <livewire:submissions-list />
@endsection
