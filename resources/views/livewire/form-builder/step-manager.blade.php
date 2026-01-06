<div class="step-manager mb-4">
    <div class="flex items-center gap-2">
        {{-- Step tabs --}}
        <div class="tabs tabs-boxed flex-1 bg-base-200">
            @foreach ($this->steps as $step)
                <button
                    type="button"
                    wire:key="step-{{ $step->id }}"
                    wire:click="selectStep({{ $step->id }})"
                    class="tab {{ $activeStepId === $step->id ? 'tab-active' : '' }}"
                    title="{{ $step->title }}"
                >
                    <span class="flex items-center gap-2">
                        <span class="truncate max-w-24">{{ $step->title }}</span>
                        <span class="badge badge-ghost badge-xs">{{ $step->fields_count }}</span>
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Add step button --}}
        <button
            type="button"
            wire:click="addStep"
            class="btn btn-ghost btn-sm"
            title="Add new step"
        >
            <x-artisanpack-icon name="o-plus" class="h-4 w-4" />
            Add Step
        </button>
    </div>

    {{-- Current step settings --}}
    @if ($activeStepId)
        @php
            $activeStep = $this->steps->firstWhere('id', $activeStepId);
        @endphp

        @if ($activeStep)
            <div
                class="mt-3 rounded-lg bg-base-200 p-3"
                x-data="{ showSettings: false }"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium">{{ $activeStep->title }}</span>
                        @if ($activeStep->description)
                            <span class="text-xs text-base-content/50">&middot; {{ Str::limit($activeStep->description, 50) }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            @click="showSettings = !showSettings"
                            class="btn btn-ghost btn-xs"
                            title="Step settings"
                        >
                            <x-artisanpack-icon name="o-cog-6-tooth" class="h-4 w-4" />
                        </button>
                        @if ($this->steps->count() > 1)
                            <button
                                type="button"
                                wire:click="deleteStep({{ $activeStep->id }})"
                                wire:confirm="Are you sure you want to delete this step? Fields will be moved to another step."
                                class="btn btn-ghost btn-xs text-error"
                                title="Delete step"
                            >
                                <x-artisanpack-icon name="o-trash" class="h-4 w-4" />
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Step settings (collapsible) --}}
                <div x-show="showSettings" x-cloak class="mt-3 space-y-3 border-t border-base-300 pt-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-control">
                            <label class="label" for="step-title-{{ $activeStep->id }}">
                                <span class="label-text text-xs">Step Title</span>
                            </label>
                            <input
                                type="text"
                                id="step-title-{{ $activeStep->id }}"
                                class="input input-sm input-bordered w-full"
                                value="{{ $activeStep->title }}"
                                x-on:change="$wire.updateStep({{ $activeStep->id }}, { title: $event.target.value })"
                            />
                        </div>
                        <div class="form-control">
                            <label class="label" for="step-description-{{ $activeStep->id }}">
                                <span class="label-text text-xs">Description</span>
                            </label>
                            <input
                                type="text"
                                id="step-description-{{ $activeStep->id }}"
                                class="input input-sm input-bordered w-full"
                                value="{{ $activeStep->description }}"
                                x-on:change="$wire.updateStep({{ $activeStep->id }}, { description: $event.target.value })"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-control">
                            <label class="label" for="step-next-btn-{{ $activeStep->id }}">
                                <span class="label-text text-xs">Next Button Text</span>
                            </label>
                            <input
                                type="text"
                                id="step-next-btn-{{ $activeStep->id }}"
                                class="input input-sm input-bordered w-full"
                                value="{{ $activeStep->next_button_text }}"
                                x-on:change="$wire.updateStep({{ $activeStep->id }}, { next_button_text: $event.target.value })"
                            />
                        </div>
                        <div class="form-control">
                            <label class="label" for="step-prev-btn-{{ $activeStep->id }}">
                                <span class="label-text text-xs">Previous Button Text</span>
                            </label>
                            <input
                                type="text"
                                id="step-prev-btn-{{ $activeStep->id }}"
                                class="input input-sm input-bordered w-full"
                                value="{{ $activeStep->prev_button_text }}"
                                x-on:change="$wire.updateStep({{ $activeStep->id }}, { prev_button_text: $event.target.value })"
                            />
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
