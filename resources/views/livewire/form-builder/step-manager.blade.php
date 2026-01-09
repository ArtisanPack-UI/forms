<div class="step-manager">
    {{-- Hidden instructions for aria-describedby --}}
    <div id="step-listbox-instructions" class="sr-only">
        {{ __( 'Use arrow keys to navigate between steps. Press Alt plus left or right arrow to reorder steps. Drag and drop is also available for reordering. Press Enter or click to select a step for editing.' ) }}
    </div>

    {{-- ARIA live region for drag/reorder announcements --}}
    <div
        id="step-reorder-announcer"
        class="sr-only"
        role="status"
        aria-live="assertive"
        aria-atomic="true"
    ></div>

    <div class="flex items-center gap-2">
        {{-- Step tabs with drag-to-reorder and keyboard accessibility --}}
        <div
            class="tabs tabs-boxed flex-1 bg-base-300"
            role="listbox"
            aria-label="{{ __( 'Form steps' ) }}"
            aria-describedby="step-listbox-instructions"
            aria-orientation="horizontal"
            :aria-activedescendant="'step-option-' + {{ $activeStepId ?? 'null' }}"
            x-data="{
                dragging: null,
                dragOver: null,
                steps: @js($this->steps->pluck('id')->toArray()),
                stepTitles: @js($this->steps->pluck('title', 'id')->toArray()),
                focusedIndex: 0,

                init() {
                    // Sync Alpine steps array when Livewire adds/deletes steps
                    $wire.on('step-added', () => this.syncStepsFromDom());
                    $wire.on('step-deleted', () => this.syncStepsFromDom());
                },

                // Re-populate steps array from DOM data attributes
                syncStepsFromDom() {
                    this.$nextTick(() => {
                        const stepElements = this.$el.querySelectorAll('[data-step-id]');
                        this.steps = Array.from(stepElements).map(el => parseInt(el.dataset.stepId, 10));
                    });
                },

                // Announce to screen readers
                announce(message) {
                    const announcer = document.getElementById('step-reorder-announcer');
                    if (announcer) {
                        announcer.textContent = '';
                        // Use setTimeout to ensure the announcement is read
                        setTimeout(() => {
                            announcer.textContent = message;
                        }, 50);
                    }
                },

                // Get step title by ID
                getStepTitle(stepId) {
                    return this.stepTitles[stepId] || 'Step';
                },

                // Get step position (1-indexed)
                getStepPosition(stepId) {
                    return this.steps.indexOf(stepId) + 1;
                },

                // Drag and drop handlers
                handleDragStart(e, stepId) {
                    this.dragging = stepId;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', stepId);
                    this.announce(`Picked up ${this.getStepTitle(stepId)}, position ${this.getStepPosition(stepId)} of ${this.steps.length}. Use arrow keys to move, drop to place.`);
                },

                handleDragOver(e, stepId) {
                    e.preventDefault();
                    if (this.dragging !== stepId && this.dragOver !== stepId) {
                        this.dragOver = stepId;
                        this.announce(`Over ${this.getStepTitle(stepId)}, position ${this.getStepPosition(stepId)}`);
                    }
                },

                handleDragLeave() {
                    this.dragOver = null;
                },

                handleDrop(e, targetId) {
                    e.preventDefault();
                    if (this.dragging && this.dragging !== targetId) {
                        const dragIndex = this.steps.indexOf(this.dragging);
                        const targetIndex = this.steps.indexOf(targetId);
                        const draggedTitle = this.getStepTitle(this.dragging);

                        this.steps.splice(dragIndex, 1);
                        this.steps.splice(targetIndex, 0, this.dragging);

                        $wire.dispatch('steps-reordered', { orderedIds: this.steps });
                        this.announce(`Dropped ${draggedTitle} at position ${targetIndex + 1} of ${this.steps.length}`);
                    }
                    this.dragging = null;
                    this.dragOver = null;
                },

                handleDragEnd() {
                    if (this.dragging) {
                        this.announce('Reorder cancelled');
                    }
                    this.dragging = null;
                    this.dragOver = null;
                },

                // Keyboard reorder handlers
                moveUp(stepId) {
                    const currentIndex = this.steps.indexOf(stepId);
                    if (currentIndex > 0) {
                        const stepTitle = this.getStepTitle(stepId);
                        this.steps.splice(currentIndex, 1);
                        this.steps.splice(currentIndex - 1, 0, stepId);
                        $wire.dispatch('steps-reordered', { orderedIds: this.steps });
                        this.focusedIndex = currentIndex - 1;
                        this.$nextTick(() => this.focusStep(this.focusedIndex));
                        this.announce(`${stepTitle} moved to position ${currentIndex} of ${this.steps.length}`);
                    }
                },

                moveDown(stepId) {
                    const currentIndex = this.steps.indexOf(stepId);
                    if (currentIndex < this.steps.length - 1) {
                        const stepTitle = this.getStepTitle(stepId);
                        this.steps.splice(currentIndex, 1);
                        this.steps.splice(currentIndex + 1, 0, stepId);
                        $wire.dispatch('steps-reordered', { orderedIds: this.steps });
                        this.focusedIndex = currentIndex + 1;
                        this.$nextTick(() => this.focusStep(this.focusedIndex));
                        this.announce(`${stepTitle} moved to position ${currentIndex + 2} of ${this.steps.length}`);
                    }
                },

                // Keyboard navigation
                handleKeydown(e, stepId, index) {
                    // Alt + Arrow keys for reordering
                    if (e.altKey && e.key === 'ArrowLeft') {
                        e.preventDefault();
                        this.moveUp(stepId);
                    } else if (e.altKey && e.key === 'ArrowRight') {
                        e.preventDefault();
                        this.moveDown(stepId);
                    }
                    // Plain arrow keys for focus navigation
                    else if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        this.focusPrevious(index);
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        this.focusNext(index);
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        this.focusStep(0);
                    } else if (e.key === 'End') {
                        e.preventDefault();
                        this.focusStep(this.steps.length - 1);
                    }
                },

                focusPrevious(currentIndex) {
                    const newIndex = currentIndex > 0 ? currentIndex - 1 : this.steps.length - 1;
                    this.focusStep(newIndex);
                },

                focusNext(currentIndex) {
                    const newIndex = currentIndex < this.steps.length - 1 ? currentIndex + 1 : 0;
                    this.focusStep(newIndex);
                },

                focusStep(index) {
                    this.focusedIndex = index;
                    const stepOptions = this.$el.querySelectorAll('[role=option]');
                    if (stepOptions[index]) {
                        stepOptions[index].focus();
                    }
                },

                canMoveUp(index) {
                    return index &gt; 0;
                },

                canMoveDown(index) {
                    return index &lt; this.steps.length - 1;
                }
            }"
        >
            @foreach ($this->steps as $index => $step)
                <div
                    wire:key="step-wrapper-{{ $step->id }}"
                    data-step-id="{{ $step->id }}"
                    id="step-option-{{ $step->id }}"
                    class="relative group"
                    role="option"
                    aria-roledescription="reorderable step"
                    aria-selected="{{ $activeStepId === $step->id ? 'true' : 'false' }}"
                    aria-posinset="{{ $index + 1 }}"
                    aria-setsize="{{ $this->steps->count() }}"
                    tabindex="{{ $index === 0 ? '0' : '-1' }}"
                    @keydown="handleKeydown($event, {{ $step->id }}, {{ $index }})"
                    @click="$wire.selectStep({{ $step->id }})"
                    @focus="focusedIndex = {{ $index }}"
                    draggable="true"
                    @dragstart="handleDragStart($event, {{ $step->id }})"
                    @dragover="handleDragOver($event, {{ $step->id }})"
                    @dragleave="handleDragLeave()"
                    @drop="handleDrop($event, {{ $step->id }})"
                    @dragend="handleDragEnd()"
                    :class="{
                        'opacity-50': dragging === {{ $step->id }},
                        'ring-2 ring-primary ring-offset-2': dragOver === {{ $step->id }}
                    }"
                >
                    {{-- Step tab content (presentational) --}}
                    <span class="tab gap-2 {{ $activeStepId === $step->id ? 'tab-active' : '' }} pr-8 cursor-pointer">
                        <span class="badge badge-sm {{ $activeStepId === $step->id ? 'badge-primary' : 'badge-ghost' }}" aria-hidden="true">
                            {{ $index + 1 }}
                        </span>
                        <span class="truncate max-w-20">{{ $step->title }}</span>
                        <span class="badge badge-ghost badge-xs" aria-label="{{ $step->fields_count }} fields">{{ $step->fields_count }}</span>
                    </span>
                </div>
            @endforeach
        </div>

        {{-- Reorder toolbar (outside listbox for proper ARIA structure) --}}
        <div
            role="toolbar"
            aria-label="{{ __( 'Step reorder controls' ) }}"
            aria-controls="step-listbox"
            class="flex items-center"
            x-data="{
                activeStepId: @js($activeStepId),
                getActiveIndex() {
                    const steps = @js($this->steps->pluck('id')->toArray());
                    return steps.indexOf(this.activeStepId);
                },
                canMoveLeft() {
                    return this.getActiveIndex() > 0;
                },
                canMoveRight() {
                    const steps = @js($this->steps->pluck('id')->toArray());
                    return this.getActiveIndex() < steps.length - 1;
                }
            }"
        >
            @if ($this->steps->count() > 1)
                <button
                    type="button"
                    class="btn btn-ghost btn-xs p-0 min-h-0 h-6 w-6"
                    title="{{ __( 'Move selected step left (Alt + Left Arrow)' ) }}"
                    aria-label="{{ __( 'Move selected step left' ) }}"
                    wire:click="$parent.dispatch('move-step-left')"
                    x-on:click="$dispatch('move-step-left')"
                    :disabled="!canMoveLeft()"
                    :class="{ 'opacity-30 cursor-not-allowed': !canMoveLeft() }"
                >
                    <x-artisanpack-icon name="o-chevron-left" class="h-4 w-4" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="btn btn-ghost btn-xs p-0 min-h-0 h-6 w-6"
                    title="{{ __( 'Move selected step right (Alt + Right Arrow)' ) }}"
                    aria-label="{{ __( 'Move selected step right' ) }}"
                    wire:click="$parent.dispatch('move-step-right')"
                    x-on:click="$dispatch('move-step-right')"
                    :disabled="!canMoveRight()"
                    :class="{ 'opacity-30 cursor-not-allowed': !canMoveRight() }"
                >
                    <x-artisanpack-icon name="o-chevron-right" class="h-4 w-4" aria-hidden="true" />
                </button>
            @endif
        </div>

        {{-- Add step button --}}
        <button
            type="button"
            wire:click="addStep"
            class="btn btn-ghost btn-sm"
            title="{{ __( 'Add new step' ) }}"
            aria-label="{{ __( 'Add new step to form' ) }}"
        >
            <x-artisanpack-icon name="o-plus" class="h-4 w-4" aria-hidden="true" />
            {{ __( 'Add Step' ) }}
        </button>
    </div>

    {{-- Current step settings --}}
    @if ($activeStepId)
        @php
            $activeStep = $this->steps->firstWhere('id', $activeStepId);
        @endphp

        @if ($activeStep)
            <div
                class="mt-3 rounded-lg bg-base-300 p-3"
                x-data="{ showSettings: false }"
                role="region"
                aria-label="Settings for {{ $activeStep->title }}"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-artisanpack-icon name="o-squares-2x2" class="h-4 w-4 text-base-content/50" aria-hidden="true" />
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
                            :aria-expanded="showSettings"
                            aria-controls="step-settings-{{ $activeStep->id }}"
                            :class="{ 'btn-active': showSettings }"
                        >
                            <x-artisanpack-icon name="o-cog-6-tooth" class="h-4 w-4" aria-hidden="true" />
                            <span class="text-xs">{{ __( 'Settings' ) }}</span>
                        </button>
                        @if ($this->steps->count() > 1)
                            <button
                                type="button"
                                wire:click="deleteStep({{ $activeStep->id }})"
                                wire:confirm="{{ __( 'Are you sure you want to delete this step? Fields will be moved to another step.' ) }}"
                                class="btn btn-ghost btn-xs text-error"
                                title="{{ __( 'Delete step' ) }}"
                                aria-label="Delete {{ $activeStep->title }}"
                            >
                                <x-artisanpack-icon name="o-trash" class="h-4 w-4" aria-hidden="true" />
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Step settings (collapsible) --}}
                <div
                    x-show="showSettings"
                    x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="mt-3 space-y-3 border-t border-base-content/10 pt-3"
                    id="step-settings-{{ $activeStep->id }}"
                    role="group"
                    aria-label="{{ __( 'Step configuration options' ) }}"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-control">
                            <label class="label" for="step-title-{{ $activeStep->id }}">
                                <span class="label-text text-xs">{{ __( 'Step Title' ) }}</span>
                            </label>
                            <input
                                type="text"
                                id="step-title-{{ $activeStep->id }}"
                                class="input input-sm input-bordered w-full"
                                value="{{ $activeStep->title }}"
                                x-on:change="$wire.updateStep({{ $activeStep->id }}, { title: $event.target.value })"
                                aria-describedby="step-title-help-{{ $activeStep->id }}"
                            />
                            <span id="step-title-help-{{ $activeStep->id }}" class="sr-only">
                                {{ __( 'The title displayed for this step in the progress indicator' ) }}
                            </span>
                        </div>
                        <div class="form-control">
                            <label class="label" for="step-description-{{ $activeStep->id }}">
                                <span class="label-text text-xs">{{ __( 'Description' ) }} <span class="text-base-content/40">({{ __( 'optional' ) }})</span></span>
                            </label>
                            <input
                                type="text"
                                id="step-description-{{ $activeStep->id }}"
                                class="input input-sm input-bordered w-full"
                                value="{{ $activeStep->description }}"
                                placeholder="{{ __( 'Brief description of this step' ) }}"
                                x-on:change="$wire.updateStep({{ $activeStep->id }}, { description: $event.target.value })"
                            />
                        </div>
                    </div>

                    <div class="divider text-xs text-base-content/40 my-2" role="separator">{{ __( 'Navigation Buttons' ) }}</div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="form-control">
                            <label class="label" for="step-prev-btn-{{ $activeStep->id }}">
                                <span class="label-text text-xs">{{ __( 'Previous Button Text' ) }}</span>
                            </label>
                            <input
                                type="text"
                                id="step-prev-btn-{{ $activeStep->id }}"
                                class="input input-sm input-bordered w-full"
                                value="{{ $activeStep->prev_button_text }}"
                                placeholder="{{ __( 'Previous' ) }}"
                                x-on:change="$wire.updateStep({{ $activeStep->id }}, { prev_button_text: $event.target.value })"
                            />
                            <label class="label">
                                <span class="label-text-alt text-xs text-base-content/40">{{ __( 'Shown on all steps except the first' ) }}</span>
                            </label>
                        </div>
                        <div class="form-control">
                            <label class="label" for="step-next-btn-{{ $activeStep->id }}">
                                <span class="label-text text-xs">{{ __( 'Next Button Text' ) }}</span>
                            </label>
                            <input
                                type="text"
                                id="step-next-btn-{{ $activeStep->id }}"
                                class="input input-sm input-bordered w-full"
                                value="{{ $activeStep->next_button_text }}"
                                placeholder="{{ __( 'Next' ) }}"
                                x-on:change="$wire.updateStep({{ $activeStep->id }}, { next_button_text: $event.target.value })"
                            />
                            <label class="label">
                                <span class="label-text-alt text-xs text-base-content/40">{{ __( 'Shown on all steps except the last' ) }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Help text with keyboard instructions --}}
    <div class="mt-2 text-xs text-base-content/40" role="note">
        <x-artisanpack-icon name="o-information-circle" class="inline h-3 w-3" aria-hidden="true" />
        {{ __( 'Drag tabs or use Alt+Arrow keys to reorder. Click a step to edit its fields.' ) }}
    </div>
</div>
