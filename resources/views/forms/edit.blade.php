@extends('forms::layouts.app')

@section('title', __( 'Edit :name', ['name' => $form->name] ))

@section('header')
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <x-artisanpack-button
                link="{{ route('forms.index') }}"
                icon="o-arrow-left"
                color="ghost"
                class="btn-sm"
            />
            <div>
                <h1 class="text-2xl font-semibold">{{ $form->name }}</h1>
                <p class="text-sm text-base-content/60">{{ $form->slug }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if($form->is_active)
                <x-artisanpack-badge :value="__( 'Active' )" color="success" />
            @else
                <x-artisanpack-badge :value="__( 'Inactive' )" color="neutral" />
            @endif
        </div>
    </div>
@endsection

@section('content')
    <x-artisanpack-tabs selected="fields">
        {{-- Fields Tab --}}
        <x-artisanpack-tab name="fields" :label="__( 'Fields' )" icon="o-clipboard-document-list">
            <livewire:form-builder :form="$form" />
        </x-artisanpack-tab>

        {{-- Settings Tab --}}
        <x-artisanpack-tab name="settings" :label="__( 'Settings' )" icon="o-cog-6-tooth">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Settings Form --}}
                <div class="lg:col-span-2">
                    <x-artisanpack-card :title="__( 'Form Settings' )" separator>
                        <x-artisanpack-form action="{{ route('forms.update', $form) }}" method="PUT">
                            <div class="space-y-6">
                                {{-- Form Name --}}
                                <x-artisanpack-input
                                    :label="__( 'Form Name' )"
                                    name="name"
                                    :value="old('name', $form->name)"
                                    required
                                />

                                {{-- Slug --}}
                                <x-artisanpack-input
                                    :label="__( 'Slug' )"
                                    name="slug"
                                    :value="old('slug', $form->slug)"
                                    :hint="__( 'URL-friendly identifier for the form' )"
                                />

                                {{-- Description --}}
                                <x-artisanpack-textarea
                                    :label="__( 'Description' )"
                                    name="description"
                                    rows="3"
                                >{{ old('description', $form->description) }}</x-artisanpack-textarea>

                                {{-- Multi-Step Settings --}}
                                <div class="space-y-4 border-t border-base-300 pt-6">
                                    <h3 class="text-sm font-medium">{{ __( 'Multi-Step Form' ) }}</h3>

                                    <x-artisanpack-checkbox
                                        :label="__( 'Enable multi-step form' )"
                                        name="is_multi_step"
                                        :checked="old('is_multi_step', $form->is_multi_step)"
                                    />

                                    <x-artisanpack-checkbox
                                        :label="__( 'Show progress bar' )"
                                        name="show_progress_bar"
                                        :checked="old('show_progress_bar', $form->show_progress_bar)"
                                    />

                                    <x-artisanpack-checkbox
                                        :label="__( 'Allow step navigation (users can jump between steps)' )"
                                        name="allow_step_navigation"
                                        :checked="old('allow_step_navigation', $form->allow_step_navigation)"
                                    />
                                </div>

                                {{-- Submit Button Text --}}
                                <x-artisanpack-input
                                    :label="__( 'Submit Button Text' )"
                                    name="submit_button_text"
                                    :value="old('submit_button_text', $form->submit_button_text)"
                                    :placeholder="__( 'Submit' )"
                                />

                                {{-- Success Message --}}
                                <x-artisanpack-textarea
                                    :label="__( 'Success Message' )"
                                    name="success_message"
                                    rows="2"
                                    :placeholder="__( 'Thank you for your submission!' )"
                                >{{ old('success_message', $form->success_message) }}</x-artisanpack-textarea>

                                {{-- Redirect URL --}}
                                <x-artisanpack-input
                                    :label="__( 'Redirect URL' )"
                                    name="redirect_url"
                                    type="url"
                                    :value="old('redirect_url', $form->redirect_url)"
                                    placeholder="https://example.com/thank-you"
                                    :hint="__( 'Optional. Redirect users to this URL after submission.' )"
                                />

                                {{-- Status Toggle --}}
                                <x-artisanpack-checkbox
                                    :label="__( 'Form is active and accepting submissions' )"
                                    name="is_active"
                                    :checked="old('is_active', $form->is_active)"
                                />
                            </div>

                            <x-slot:actions>
                                <x-artisanpack-button
                                    link="{{ route('forms.index') }}"
                                    :label="__( 'Cancel' )"
                                    color="ghost"
                                />
                                <x-artisanpack-button
                                    type="submit"
                                    :label="__( 'Save Changes' )"
                                    color="primary"
                                    icon="o-check"
                                />
                            </x-slot:actions>
                        </x-artisanpack-form>
                    </x-artisanpack-card>
                </div>

                {{-- Sidebar --}}
                <div class="space-y-6">
                    {{-- Form Info Card --}}
                    <x-artisanpack-card :title="__( 'Form Info' )">
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">{{ __( 'Fields' ) }}</dt>
                                <dd class="mt-1 text-sm">{{ $form->fields_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">{{ __( 'Total Submissions' ) }}</dt>
                                <dd class="mt-1 text-sm">{{ $form->total_submissions_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">{{ __( 'Unread Submissions' ) }}</dt>
                                <dd class="mt-1 text-sm">{{ $form->unread_submissions_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">{{ __( 'Created' ) }}</dt>
                                <dd class="mt-1 text-sm">{{ $form->created_at->format('M j, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">{{ __( 'Last Updated' ) }}</dt>
                                <dd class="mt-1 text-sm">{{ $form->updated_at->format('M j, Y') }}</dd>
                            </div>
                        </dl>
                    </x-artisanpack-card>

                    {{-- Danger Zone --}}
                    <x-artisanpack-card :title="__( 'Danger Zone' )" class="border-error/20">
                        <p class="text-sm text-base-content/60 mb-4">
                            {{ __( 'Deleting this form will permanently remove all fields, submissions, and notifications associated with it.' ) }}
                        </p>
                        <form action="{{ route('forms.destroy', $form) }}" method="POST" onsubmit="return confirm('{{ __( 'Are you sure you want to delete this form? This action cannot be undone.' ) }}');">
                            @csrf
                            @method('DELETE')
                            <x-artisanpack-button
                                type="submit"
                                :label="__( 'Delete Form' )"
                                color="error"
                                icon="o-trash"
                            />
                        </form>
                    </x-artisanpack-card>
                </div>
            </div>
        </x-artisanpack-tab>

        {{-- Notifications Tab --}}
        <x-artisanpack-tab name="notifications" :label="__( 'Notifications' ) . ' (' . $form->notifications()->count() . ')'" icon="o-bell">
            <livewire:notification-editor :form="$form" />
        </x-artisanpack-tab>
    </x-artisanpack-tabs>
@endsection
