<div class="artisanpack-form-renderer">
    @if($isSubmitted)
        {{-- Success Message --}}
        <div class="alert alert-success">
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
        <form wire:submit="submit" class="space-y-6">
            {{-- Form Title & Description --}}
            @if($form->show_title && $form->title)
                <h2 class="text-2xl font-bold">{{ $form->title }}</h2>
            @endif

            @if($form->description)
                <p class="text-base-content/70">{{ $form->description }}</p>
            @endif

            {{-- Error Message --}}
            @if($errorMessage)
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errorMessage }}</span>
                </div>
            @endif

            {{-- Multi-step Progress --}}
            @if($form->is_multi_step)
                <div class="steps w-full mb-6">
                    @foreach($this->steps as $index => $step)
                        <button
                            type="button"
                            wire:click="goToStep({{ $index }})"
                            class="step {{ $index <= $currentStepIndex ? 'step-primary' : '' }}"
                            @if($index > $currentStepIndex + 1) disabled @endif
                        >
                            {{ $step->title }}
                        </button>
                    @endforeach
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
                    <div class="step-content">
                        @if($this->currentStep->title)
                            <h3 class="text-xl font-semibold mb-4">{{ $this->currentStep->title }}</h3>
                        @endif

                        @if($this->currentStep->description)
                            <p class="text-base-content/70 mb-4">{{ $this->currentStep->description }}</p>
                        @endif

                        <div class="space-y-4">
                            @foreach($this->currentFields as $field)
                                @if($this->isFieldVisible($field->name))
                                    <div wire:key="field-{{ $field->uuid }}">
                                        @include('forms::components.fields.' . $field->type, [
                                            'field' => $field,
                                            'value' => $formData[$field->name] ?? null,
                                            'error' => $errors->first('formData.' . $field->name),
                                        ])
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Step Navigation --}}
                    <div class="flex justify-between mt-6">
                        @if($currentStepIndex > 0)
                            <x-artisanpack-button
                                type="button"
                                wire:click="previousStep"
                                color="ghost"
                            >
                                Previous
                            </x-artisanpack-button>
                        @else
                            <div></div>
                        @endif

                        @if($this->isLastStep)
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
                        @else
                            <x-artisanpack-button
                                type="button"
                                wire:click="nextStep"
                                color="primary"
                            >
                                Next
                            </x-artisanpack-button>
                        @endif
                    </div>
                @endif
            @else
                {{-- Single-step: Show all fields --}}
                <div class="space-y-4">
                    @foreach($this->currentFields as $field)
                        @if($this->isFieldVisible($field->name))
                            <div wire:key="field-{{ $field->uuid }}">
                                @include('forms::components.fields.' . $field->type, [
                                    'field' => $field,
                                    'value' => $formData[$field->name] ?? null,
                                    'error' => $errors->first('formData.' . $field->name),
                                ])
                            </div>
                        @endif
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
