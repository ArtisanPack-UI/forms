@extends('forms::layouts.app')

@section('title', __( 'Create Form' ))

@section('header')
    <div class="flex items-center gap-4">
        <x-artisanpack-button
            link="{{ route('forms.index') }}"
            icon="o-arrow-left"
            color="ghost"
            class="btn-sm"
        />
        <h1 class="text-2xl font-semibold">{{ __( 'Create New Form' ) }}</h1>
    </div>
@endsection

@section('content')
    <x-artisanpack-card>
        <x-artisanpack-form action="{{ route('forms.store') }}" method="POST">
            <div class="space-y-6">
                {{-- Form Name --}}
                <x-artisanpack-input
                    :label="__( 'Form Name' )"
                    name="name"
                    :value="old('name')"
                    required
                    autofocus
                    :placeholder="__( 'Contact Form' )"
                />

                {{-- Slug --}}
                <x-artisanpack-input
                    :label="__( 'Slug' )"
                    name="slug"
                    :value="old('slug')"
                    placeholder="contact-form"
                    :hint="__( 'Leave blank to auto-generate from the name.' )"
                />

                {{-- Description --}}
                <x-artisanpack-textarea
                    :label="__( 'Description' )"
                    name="description"
                    rows="3"
                    :placeholder="__( 'A brief description of this form...' )"
                >{{ old('description') }}</x-artisanpack-textarea>
            </div>

            <x-slot:actions>
                <x-artisanpack-button
                    link="{{ route('forms.index') }}"
                    :label="__( 'Cancel' )"
                    color="ghost"
                />
                <x-artisanpack-button
                    type="submit"
                    :label="__( 'Create Form' )"
                    color="primary"
                    icon="o-plus"
                />
            </x-slot:actions>
        </x-artisanpack-form>
    </x-artisanpack-card>
@endsection
