@extends('forms::layouts.app')

@section('title', 'Create Form')

@section('header')
    <div class="flex items-center gap-4">
        <x-artisanpack-button
            link="{{ route('forms.index') }}"
            icon="o-arrow-left"
            color="ghost"
            class="btn-sm"
        />
        <h1 class="text-2xl font-semibold">Create New Form</h1>
    </div>
@endsection

@section('content')
    <x-artisanpack-card>
        <x-artisanpack-form action="{{ route('forms.store') }}" method="POST">
            <div class="space-y-6">
                {{-- Form Name --}}
                <x-artisanpack-input
                    label="Form Name"
                    name="name"
                    :value="old('name')"
                    required
                    autofocus
                    placeholder="Contact Form"
                />

                {{-- Slug --}}
                <x-artisanpack-input
                    label="Slug"
                    name="slug"
                    :value="old('slug')"
                    placeholder="contact-form"
                    hint="Leave blank to auto-generate from the name."
                />

                {{-- Description --}}
                <x-artisanpack-textarea
                    label="Description"
                    name="description"
                    rows="3"
                    placeholder="A brief description of this form..."
                >{{ old('description') }}</x-artisanpack-textarea>
            </div>

            <x-slot:actions>
                <x-artisanpack-button
                    link="{{ route('forms.index') }}"
                    label="Cancel"
                    color="ghost"
                />
                <x-artisanpack-button
                    type="submit"
                    label="Create Form"
                    color="primary"
                    icon="o-plus"
                />
            </x-slot:actions>
        </x-artisanpack-form>
    </x-artisanpack-card>
@endsection
