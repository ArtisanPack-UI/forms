@extends('forms::layouts.app')

@section('title', 'Edit ' . $form->name)

@section('header')
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <a href="{{ route('forms.index') }}" class="mr-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $form->name }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $form->slug }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($form->is_active)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    Active
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                    Inactive
                </span>
            @endif
        </div>
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Settings Form -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">Form Settings</h2>
                </div>
                <form action="{{ route('forms.update', $form) }}" method="POST" class="p-6 space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Form Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Form Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name', $form->name) }}"
                               required
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Slug -->
                    <div>
                        <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Slug
                        </label>
                        <input type="text"
                               name="slug"
                               id="slug"
                               value="{{ old('slug', $form->slug) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('slug') border-red-500 @enderror">
                        @error('slug')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <textarea name="description"
                                  id="description"
                                  rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('description') border-red-500 @enderror">{{ old('description', $form->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Submit Button Text -->
                    <div>
                        <label for="submit_button_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Submit Button Text
                        </label>
                        <input type="text"
                               name="submit_button_text"
                               id="submit_button_text"
                               value="{{ old('submit_button_text', $form->submit_button_text) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('submit_button_text') border-red-500 @enderror"
                               placeholder="Submit">
                        @error('submit_button_text')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Success Message -->
                    <div>
                        <label for="success_message" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Success Message
                        </label>
                        <textarea name="success_message"
                                  id="success_message"
                                  rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('success_message') border-red-500 @enderror"
                                  placeholder="Thank you for your submission!">{{ old('success_message', $form->success_message) }}</textarea>
                        @error('success_message')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Redirect URL -->
                    <div>
                        <label for="redirect_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Redirect URL
                        </label>
                        <input type="url"
                               name="redirect_url"
                               id="redirect_url"
                               value="{{ old('redirect_url', $form->redirect_url) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('redirect_url') border-red-500 @enderror"
                               placeholder="https://example.com/thank-you">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Optional. Redirect users to this URL after submission.</p>
                        @error('redirect_url')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status Toggle -->
                    <div class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox"
                               name="is_active"
                               id="is_active"
                               value="1"
                               {{ old('is_active', $form->is_active) ? 'checked' : '' }}
                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded">
                        <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            Form is active and accepting submissions
                        </label>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('forms.index') }}"
                           class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Cancel
                        </a>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Form Info Card -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Form Info</h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fields</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $form->fields_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Submissions</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $form->total_submissions_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Unread Submissions</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $form->unread_submissions_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $form->created_at->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Updated</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $form->updated_at->format('M j, Y') }}</dd>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                <div class="px-6 py-4 border-b border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 rounded-t-lg">
                    <h3 class="text-lg font-medium text-red-800 dark:text-red-200">Danger Zone</h3>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Deleting this form will permanently remove all fields, submissions, and notifications associated with it.
                    </p>
                    <form action="{{ route('forms.destroy', $form) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this form? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Delete Form
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
