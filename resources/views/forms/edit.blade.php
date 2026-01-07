@extends('forms::layouts.app')

@section('title', 'Edit ' . $form->name)

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
                <x-artisanpack-badge value="Active" color="success" />
            @else
                <x-artisanpack-badge value="Inactive" color="neutral" />
            @endif
        </div>
    </div>
@endsection

@section('content')
    <x-artisanpack-tabs selected="fields">
        {{-- Fields Tab --}}
        <x-artisanpack-tab name="fields" label="Fields" icon="o-clipboard-document-list">
            <livewire:form-builder :form="$form" />
        </x-artisanpack-tab>

        {{-- Settings Tab --}}
        <x-artisanpack-tab name="settings" label="Settings" icon="o-cog-6-tooth">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main Settings Form --}}
                <div class="lg:col-span-2">
                    <x-artisanpack-card title="Form Settings" separator>
                        <x-artisanpack-form action="{{ route('forms.update', $form) }}" method="PUT">
                            <div class="space-y-6">
                                {{-- Form Name --}}
                                <x-artisanpack-input
                                    label="Form Name"
                                    name="name"
                                    :value="old('name', $form->name)"
                                    required
                                />

                                {{-- Slug --}}
                                <x-artisanpack-input
                                    label="Slug"
                                    name="slug"
                                    :value="old('slug', $form->slug)"
                                    hint="URL-friendly identifier for the form"
                                />

                                {{-- Description --}}
                                <x-artisanpack-textarea
                                    label="Description"
                                    name="description"
                                    rows="3"
                                >{{ old('description', $form->description) }}</x-artisanpack-textarea>

                                {{-- Multi-Step Settings --}}
                                <div class="space-y-4 border-t border-base-300 pt-6">
                                    <h3 class="text-sm font-medium">Multi-Step Form</h3>

                                    <x-artisanpack-checkbox
                                        label="Enable multi-step form"
                                        name="is_multi_step"
                                        :checked="old('is_multi_step', $form->is_multi_step)"
                                    />

                                    <x-artisanpack-checkbox
                                        label="Show progress bar"
                                        name="show_progress_bar"
                                        :checked="old('show_progress_bar', $form->show_progress_bar)"
                                    />

                                    <x-artisanpack-checkbox
                                        label="Allow step navigation (users can jump between steps)"
                                        name="allow_step_navigation"
                                        :checked="old('allow_step_navigation', $form->allow_step_navigation)"
                                    />
                                </div>

                                {{-- Submit Button Text --}}
                                <x-artisanpack-input
                                    label="Submit Button Text"
                                    name="submit_button_text"
                                    :value="old('submit_button_text', $form->submit_button_text)"
                                    placeholder="Submit"
                                />

                                {{-- Success Message --}}
                                <x-artisanpack-textarea
                                    label="Success Message"
                                    name="success_message"
                                    rows="2"
                                    placeholder="Thank you for your submission!"
                                >{{ old('success_message', $form->success_message) }}</x-artisanpack-textarea>

                                {{-- Redirect URL --}}
                                <x-artisanpack-input
                                    label="Redirect URL"
                                    name="redirect_url"
                                    type="url"
                                    :value="old('redirect_url', $form->redirect_url)"
                                    placeholder="https://example.com/thank-you"
                                    hint="Optional. Redirect users to this URL after submission."
                                />

                                {{-- Status Toggle --}}
                                <x-artisanpack-checkbox
                                    label="Form is active and accepting submissions"
                                    name="is_active"
                                    :checked="old('is_active', $form->is_active)"
                                />
                            </div>

                            <x-slot:actions>
                                <x-artisanpack-button
                                    link="{{ route('forms.index') }}"
                                    label="Cancel"
                                    color="ghost"
                                />
                                <x-artisanpack-button
                                    type="submit"
                                    label="Save Changes"
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
                    <x-artisanpack-card title="Form Info">
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">Fields</dt>
                                <dd class="mt-1 text-sm">{{ $form->fields_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">Total Submissions</dt>
                                <dd class="mt-1 text-sm">{{ $form->total_submissions_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">Unread Submissions</dt>
                                <dd class="mt-1 text-sm">{{ $form->unread_submissions_count }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">Created</dt>
                                <dd class="mt-1 text-sm">{{ $form->created_at->format('M j, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-base-content/60">Last Updated</dt>
                                <dd class="mt-1 text-sm">{{ $form->updated_at->format('M j, Y') }}</dd>
                            </div>
                        </dl>
                    </x-artisanpack-card>

                    {{-- Danger Zone --}}
                    <x-artisanpack-card title="Danger Zone" class="border-error/20">
                        <p class="text-sm text-base-content/60 mb-4">
                            Deleting this form will permanently remove all fields, submissions, and notifications associated with it.
                        </p>
                        <form action="{{ route('forms.destroy', $form) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this form? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <x-artisanpack-button
                                type="submit"
                                label="Delete Form"
                                color="error"
                                icon="o-trash"
                            />
                        </form>
                    </x-artisanpack-card>
                </div>
            </div>
        </x-artisanpack-tab>

        {{-- Notifications Tab --}}
        <x-artisanpack-tab name="notifications" label="Notifications ({{ $form->notifications()->count() }})" icon="o-bell">
            <livewire:notification-editor :form="$form" />
        </x-artisanpack-tab>
    </x-artisanpack-tabs>
@endsection
