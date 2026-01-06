<div
    class="artisanpack-form-renderer"
    x-data="formRenderer({
        formData: @entangle('formData'),
        fieldMap: @js($this->fieldMap),
        conditionalLogic: @js($this->conditionalLogicConfig),
        hiddenFields: @entangle('hiddenFields'),
        isMultiStep: @js($form->is_multi_step),
        currentStepIndex: @entangle('currentStepIndex'),
        totalSteps: @js($this->totalSteps)
    })"
    x-init="init()"
    @keydown.window="handleKeydown($event)"
>
    {{-- ARIA live region for step announcements --}}
    <div
        class="sr-only"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        x-ref="announcer"
    ></div>

    @if($isSubmitted)
        {{-- Success Message --}}
        <div class="alert alert-success" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $form->success_message ?? 'Thank you! Your submission has been received.' }}</span>
        </div>

        @if($form->allow_multiple_submissions ?? false)
            <div class="mt-4">
                <x-artisanpack-button
                    type="button"
                    wire:click="resetForm"
                    color="ghost"
                >
                    Submit Another Response
                </x-artisanpack-button>
            </div>
        @endif
    @else
        <form wire:submit="submit" class="space-y-6" x-ref="form">
            {{-- Form Title & Description --}}
            @if($form->show_title && $form->title)
                <h2 class="text-2xl font-bold">{{ $form->title }}</h2>
            @endif

            @if($form->description)
                <p class="text-base-content/70">{{ $form->description }}</p>
            @endif

            {{-- Error Message --}}
            @if($errorMessage)
                <div class="alert alert-error" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errorMessage }}</span>
                </div>
            @endif

            {{-- Multi-step Progress Indicator --}}
            @if($form->is_multi_step)
                <div class="mb-8" role="navigation" aria-label="Form progress">
                    {{-- Progress Bar --}}
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-base-content/70">
                                Step {{ $currentStepIndex + 1 }} of {{ $this->totalSteps }}
                            </span>
                            <span class="text-base-content/70">
                                {{ $this->progressPercentage }}% complete
                            </span>
                        </div>
                        <progress
                            class="progress progress-primary w-full"
                            value="{{ $this->progressPercentage }}"
                            max="100"
                            aria-label="Form progress: {{ $this->progressPercentage }}% complete"
                        ></progress>
                    </div>

                    {{-- Step Indicators --}}
                    <ul class="steps steps-horizontal w-full">
                        @foreach($this->steps as $index => $step)
                            <li
                                class="step {{ $index < $currentStepIndex ? 'step-primary' : ($index === $currentStepIndex ? 'step-primary' : '') }}"
                                data-content="{{ $index < $currentStepIndex ? '✓' : $index + 1 }}"
                            >
                                @if($form->allow_step_navigation && $index <= $currentStepIndex)
                                    <button
                                        type="button"
                                        wire:click="goToStep({{ $index }})"
                                        class="text-sm hover:underline focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded"
                                        @if($index === $currentStepIndex) aria-current="step" @endif
                                    >
                                        {{ $step->title }}
                                    </button>
                                @else
                                    <span
                                        class="text-sm {{ $index > $currentStepIndex ? 'text-base-content/50' : '' }}"
                                        @if($index === $currentStepIndex) aria-current="step" @endif
                                    >
                                        {{ $step->title }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Honeypot Field (spam protection) --}}
            <div class="hidden" aria-hidden="true" style="position: absolute; left: -9999px;">
                <label for="website_url">Leave this field empty</label>
                <input
                    type="text"
                    name="website_url"
                    id="website_url"
                    wire:model="honeypot"
                    tabindex="-1"
                    autocomplete="off"
                />
            </div>

            {{-- Form Fields --}}
            @if($form->is_multi_step)
                {{-- Multi-step: Show current step fields --}}
                @if($this->currentStep)
                    <div
                        class="step-content"
                        x-ref="stepContent"
                        role="region"
                        aria-label="Step {{ $currentStepIndex + 1 }}: {{ $this->currentStep->title }}"
                    >
                        @if($this->currentStep->title && !$form->show_title)
                            <h3 class="text-xl font-semibold mb-2">{{ $this->currentStep->title }}</h3>
                        @endif

                        @if($this->currentStep->description)
                            <p class="text-base-content/70 mb-4">{{ $this->currentStep->description }}</p>
                        @endif

                        <div class="space-y-4">
                            @foreach($this->currentFields as $field)
                                <div
                                    wire:key="field-{{ $field->uuid }}"
                                    x-show="isFieldVisible('{{ $field->name }}')"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 transform -translate-y-2"
                                    x-transition:enter-end="opacity-100 transform translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 transform translate-y-0"
                                    x-transition:leave-end="opacity-0 transform -translate-y-2"
                                    x-cloak
                                    :class="{ 'hidden': !isFieldVisible('{{ $field->name }}') }"
                                    :aria-hidden="!isFieldVisible('{{ $field->name }}')"
                                    :inert="!isFieldVisible('{{ $field->name }}')"
                                >
                                    @include('forms::components.fields.' . $field->type, [
                                        'field' => $field,
                                        'value' => $formData[$field->name] ?? null,
                                        'error' => $errors->first('formData.' . $field->name),
                                    ])
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Step Navigation --}}
                    <div class="flex justify-between items-center mt-8 pt-4 border-t border-base-300">
                        {{-- Previous Button --}}
                        <div>
                            @if(!$this->isFirstStep)
                                <x-artisanpack-button
                                    type="button"
                                    wire:click="previousStep"
                                    color="ghost"
                                    wire:loading.attr="disabled"
                                >
                                    <x-artisanpack-icon name="o-arrow-left" class="h-4 w-4 mr-1" />
                                    {{ $this->currentStep->prev_button_text ?? 'Previous' }}
                                </x-artisanpack-button>
                            @endif
                        </div>

                        {{-- Next/Submit Button --}}
                        <div>
                            @if($this->isLastStep)
                                <x-artisanpack-button
                                    type="submit"
                                    color="primary"
                                    wire:loading.attr="disabled"
                                    wire:target="submit"
                                >
                                    <span wire:loading.remove wire:target="submit">
                                        {{ $form->submit_button_text ?? 'Submit' }}
                                    </span>
                                    <span wire:loading wire:target="submit" class="flex items-center gap-2">
                                        <span class="loading loading-spinner loading-sm"></span>
                                        Submitting...
                                    </span>
                                </x-artisanpack-button>
                            @else
                                <x-artisanpack-button
                                    type="button"
                                    wire:click="nextStep"
                                    color="primary"
                                    wire:loading.attr="disabled"
                                    wire:target="nextStep"
                                >
                                    <span wire:loading.remove wire:target="nextStep">
                                        {{ $this->currentStep->next_button_text ?? 'Next' }}
                                        <x-artisanpack-icon name="o-arrow-right" class="h-4 w-4 ml-1 inline" />
                                    </span>
                                    <span wire:loading wire:target="nextStep" class="flex items-center gap-2">
                                        <span class="loading loading-spinner loading-sm"></span>
                                        Validating...
                                    </span>
                                </x-artisanpack-button>
                            @endif
                        </div>
                    </div>

                    {{-- Keyboard navigation hint --}}
                    <div class="mt-4 text-center text-xs text-base-content/40">
                        Press <kbd class="kbd kbd-xs">Enter</kbd> to continue
                        @if(!$this->isFirstStep)
                            or <kbd class="kbd kbd-xs">Escape</kbd> to go back
                        @endif
                    </div>
                @endif
            @else
                {{-- Single-step: Show all fields --}}
                <div class="space-y-4">
                    @foreach($this->currentFields as $field)
                        <div
                            wire:key="field-{{ $field->uuid }}"
                            x-show="isFieldVisible('{{ $field->name }}')"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform -translate-y-2"
                            x-transition:enter-end="opacity-100 transform translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform translate-y-0"
                            x-transition:leave-end="opacity-0 transform -translate-y-2"
                            x-cloak
                            :class="{ 'hidden': !isFieldVisible('{{ $field->name }}') }"
                            :aria-hidden="!isFieldVisible('{{ $field->name }}')"
                            :inert="!isFieldVisible('{{ $field->name }}')"
                        >
                            @include('forms::components.fields.' . $field->type, [
                                'field' => $field,
                                'value' => $formData[$field->name] ?? null,
                                'error' => $errors->first('formData.' . $field->name),
                            ])
                        </div>
                    @endforeach
                </div>

                {{-- Submit Button --}}
                <div class="mt-6">
                    <x-artisanpack-button
                        type="submit"
                        color="primary"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="submit">
                            {{ $form->submit_button_text ?? 'Submit' }}
                        </span>
                        <span wire:loading wire:target="submit" class="flex items-center gap-2">
                            <span class="loading loading-spinner loading-sm"></span>
                            Submitting...
                        </span>
                    </x-artisanpack-button>
                </div>
            @endif
        </form>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('formRenderer', (config) => ({
            formData: config.formData,
            fieldMap: config.fieldMap,
            conditionalLogic: config.conditionalLogic,
            hiddenFields: config.hiddenFields,
            isMultiStep: config.isMultiStep,
            currentStepIndex: config.currentStepIndex,
            totalSteps: config.totalSteps,

            init() {
                // Watch for form data changes
                this.$watch('formData', () => {
                    this.evaluateAllConditions();
                }, { deep: true });

                // Watch for step changes and announce
                this.$watch('currentStepIndex', (newVal, oldVal) => {
                    if (newVal !== oldVal) {
                        this.announceStepChange();
                        this.focusFirstField();
                    }
                });

                // Initial evaluation
                this.evaluateAllConditions();
            },

            /**
             * Handle keyboard navigation
             */
            handleKeydown(event) {
                if (!this.isMultiStep) return;

                // Don't interfere if event was already handled
                if (event.defaultPrevented) return;

                // Don't interfere with modals, dialogs, or components that capture their own keys
                if (event.target.closest('[aria-modal="true"], [role="dialog"], .modal, [data-ignore-global-keys]')) {
                    return;
                }

                // Don't interfere with form elements that need these keys
                const tagName = event.target.tagName.toLowerCase();
                const isTextarea = tagName === 'textarea';
                const isSelect = tagName === 'select';

                // Enter to advance (except in textarea or multi-line inputs)
                if (event.key === 'Enter' && !isTextarea && !isSelect) {
                    // Allow enter on submit buttons
                    if (event.target.type === 'submit') return;

                    // Don't advance if we're on the last step (let form submit naturally)
                    if (this.currentStepIndex >= this.totalSteps - 1) return;

                    event.preventDefault();
                    this.$wire.nextStep();
                }

                // Escape to go back
                if (event.key === 'Escape' && this.currentStepIndex > 0) {
                    event.preventDefault();
                    this.$wire.previousStep();
                }
            },

            /**
             * Announce step change for screen readers
             */
            announceStepChange() {
                const announcer = this.$refs.announcer;
                if (announcer) {
                    const stepNum = this.currentStepIndex + 1;
                    announcer.textContent = `Now on step ${stepNum} of ${this.totalSteps}`;
                }
            },

            /**
             * Focus the first visible field in the current step
             */
            focusFirstField() {
                this.$nextTick(() => {
                    const stepContent = this.$refs.stepContent;
                    if (stepContent) {
                        const firstInput = stepContent.querySelector(
                            'input:not([type="hidden"]):not([disabled]), ' +
                            'select:not([disabled]), ' +
                            'textarea:not([disabled])'
                        );
                        if (firstInput) {
                            firstInput.focus();
                        }
                    }
                });
            },

            /**
             * Evaluate all conditional logic rules
             */
            evaluateAllConditions() {
                for (const fieldName in this.conditionalLogic) {
                    const visible = this.evaluateCondition(fieldName);
                    this.hiddenFields[fieldName] = !visible;
                }
            },

            /**
             * Check if a field is visible
             */
            isFieldVisible(fieldName) {
                // If no conditional logic for this field, it's always visible
                if (!this.conditionalLogic[fieldName]) {
                    return true;
                }
                return !this.hiddenFields[fieldName];
            },

            /**
             * Evaluate conditional logic for a specific field
             */
            evaluateCondition(fieldName) {
                const logic = this.conditionalLogic[fieldName];
                if (!logic || !logic.rules || logic.rules.length === 0) {
                    return true;
                }

                const action = logic.action || 'show';
                const logicType = logic.logic || 'all';
                const rules = logic.rules;

                // Evaluate all rules
                const results = rules.map(rule => this.evaluateRule(rule));

                // Combine results based on logic type
                let conditionsMet;
                if (logicType === 'all') {
                    conditionsMet = results.every(r => r === true);
                } else {
                    conditionsMet = results.some(r => r === true);
                }

                // Return visibility based on action
                return action === 'show' ? conditionsMet : !conditionsMet;
            },

            /**
             * Evaluate a single condition rule
             */
            evaluateRule(rule) {
                const fieldRef = rule.field;
                const operator = rule.operator || 'equals';
                const ruleValue = rule.value || '';

                if (!fieldRef) return true;

                // Get field value (fieldRef could be field name or UUID)
                const fieldValue = this.getFieldValue(fieldRef);

                return this.compareValues(fieldValue, operator, ruleValue);
            },

            /**
             * Get field value by name or UUID
             */
            getFieldValue(fieldRef) {
                // Check if it's a direct field name
                if (this.formData[fieldRef] !== undefined) {
                    return this.formData[fieldRef];
                }

                // Check if it's a UUID - find the field name
                for (const [name, uuid] of Object.entries(this.fieldMap)) {
                    if (uuid === fieldRef) {
                        return this.formData[name];
                    }
                }

                return null;
            },

            /**
             * Compare values using the specified operator
             */
            compareValues(fieldValue, operator, ruleValue) {
                switch (operator) {
                    case 'equals':
                        return this.compareEquals(fieldValue, ruleValue);
                    case 'not_equals':
                        return !this.compareEquals(fieldValue, ruleValue);
                    case 'contains':
                        return typeof fieldValue === 'string' && fieldValue.includes(ruleValue);
                    case 'not_contains':
                        return typeof fieldValue === 'string' && !fieldValue.includes(ruleValue);
                    case 'starts_with':
                        return typeof fieldValue === 'string' && fieldValue.startsWith(ruleValue);
                    case 'ends_with':
                        return typeof fieldValue === 'string' && fieldValue.endsWith(ruleValue);
                    case 'is_empty':
                        return this.isEmpty(fieldValue);
                    case 'is_not_empty':
                        return !this.isEmpty(fieldValue);
                    case 'greater_than':
                        return this.isNumeric(fieldValue) && parseFloat(fieldValue) > parseFloat(ruleValue);
                    case 'less_than':
                        return this.isNumeric(fieldValue) && parseFloat(fieldValue) < parseFloat(ruleValue);
                    case 'greater_or_equal':
                        return this.isNumeric(fieldValue) && parseFloat(fieldValue) >= parseFloat(ruleValue);
                    case 'less_or_equal':
                        return this.isNumeric(fieldValue) && parseFloat(fieldValue) <= parseFloat(ruleValue);
                    case 'in':
                        return this.compareIn(fieldValue, ruleValue);
                    case 'not_in':
                        return !this.compareIn(fieldValue, ruleValue);
                    case 'checked':
                        return this.isChecked(fieldValue);
                    case 'unchecked':
                        return !this.isChecked(fieldValue);
                    case 'includes':
                        return Array.isArray(fieldValue) && fieldValue.includes(ruleValue);
                    case 'not_includes':
                        return Array.isArray(fieldValue) && !fieldValue.includes(ruleValue);
                    default:
                        return true;
                }
            },

            /**
             * Compare equality handling different types
             */
            compareEquals(fieldValue, ruleValue) {
                if (typeof fieldValue === 'boolean') {
                    return fieldValue === (ruleValue === 'true' || ruleValue === '1' || ruleValue === true);
                }
                if (this.isNumeric(fieldValue) && this.isNumeric(ruleValue)) {
                    return parseFloat(fieldValue) === parseFloat(ruleValue);
                }
                return String(fieldValue) === String(ruleValue);
            },

            /**
             * Check if value is empty
             */
            isEmpty(value) {
                if (value === null || value === undefined) return true;
                if (typeof value === 'string') return value.trim() === '';
                if (Array.isArray(value)) return value.length === 0;
                return !value;
            },

            /**
             * Check if value is numeric
             */
            isNumeric(value) {
                return !isNaN(parseFloat(value)) && isFinite(value);
            },

            /**
             * Check if value is in comma-separated list
             */
            compareIn(fieldValue, ruleValue) {
                if (typeof ruleValue !== 'string') return false;
                const list = ruleValue.split(',').map(v => v.trim());
                return list.includes(String(fieldValue));
            },

            /**
             * Check if checkbox is checked
             */
            isChecked(value) {
                if (typeof value === 'boolean') return value;
                if (typeof value === 'string') {
                    return ['true', '1', 'yes', 'on'].includes(value.toLowerCase());
                }
                return Boolean(value);
            }
        }));
    });
</script>
@endpush
