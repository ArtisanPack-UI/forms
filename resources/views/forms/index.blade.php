@extends('forms::layouts.app')

@section('title', 'All Forms')

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">Forms</h1>
        <x-artisanpack-button
            link="{{ route('forms.create') }}"
            label="Create Form"
            icon="o-plus"
            color="primary"
        />
    </div>
@endsection

@section('content')
    <livewire:forms-list />
@endsection
