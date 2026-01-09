@php
    $field = $this->selectedField;
    $hasConditions = $field->has_conditional_logic;
    $conditionalLogic = $field->conditional_logic ?? \ArtisanPackUI\Forms\Config\ConditionalLogic::getDefaultStructure();
    $availableFields = $this->availableConditionFields;
@endphp

<div
    wire:key="condition-builder-{{ $field->uuid }}"
    x-data="{
        enabled: @js($hasConditions),
        logic: @js($conditionalLogic),
        availableFields: @js($availableFields->map(fn($f) => [
            'name' => $f->name,
            'label' => $f->label,
            'type' => $f->type,
            'uuid' => $f->uuid,
            'options' => $f->options,
        ])->values()->all()),
        operators: @js($this->conditionOperators),
        actions: @js($this->conditionActions),
        logicTypes: @js($this->conditionLogicTypes),

        init() {
            if (!this.logic.rules) {
                this.logic.rules = [];
            }
        },

        toggleConditions() {
            if (this.enabled) {
                if (!this.logic.rules || this.logic.rules.length === 0) {
                    this.logic = { action: 'show', logic: 'all', rules: [] };
                }
                this.updateConditions();
            } else {
                $wire.clearConditionalLogic();
            }
        },

        addRule() {
            this.logic.rules.push({ field: '', operator: 'equals', value: '' });
        },

        removeRule(index) {
            this.logic.rules.splice(index, 1);
            this.updateConditions();
        },

        updateConditions() {
            if (this.enabled &amp;&amp; this.logic.rules &amp;&amp; this.logic.rules.length &gt; 0) {
                const validRules = this.logic.rules.filter(rule =&gt; rule.field);
                if (validRules.length &gt; 0) {
                    $wire.updateConditionalLogic({
                        action: this.logic.action,
                        logic: this.logic.logic,
                        rules: validRules
                    });
                }
            }
        },

        getOperatorsForField(fieldName) {
            const field = this.availableFields.find(f =&gt; f.name === fieldName);
            if (!field) return this.operators;
            return Object.fromEntries(
                Object.entries(this.operators).filter(([key, op]) =&gt;
                    op.supports_types.includes(field.type)
                )
            );
        },

        operatorNeedsValue(operator) {
            const op = this.operators[operator];
            return op ? op.needs_value : true;
        },

        fieldHasOptions(fieldName) {
            const field = this.availableFields.find(f =&gt; f.name === fieldName);
            return field &amp;&amp; field.options &amp;&amp; field.options.length &gt; 0;
        },

        getFieldOptions(fieldName) {
            const field = this.availableFields.find(f =&gt; f.name === fieldName);
            return field ? (field.options || []) : [];
        },

        getValuePlaceholder(operator) {
            const op = this.operators[operator];
            if (!op) return 'Enter value...';
            switch (op.value_type) {
                case 'number': return 'Enter a number...';
                case 'list': return 'Enter values (comma-separated)...';
                default: return 'Enter value...';
            }
        },

        getRuleDescription(rule) {
            const field = this.availableFields.find(f =&gt; f.name === rule.field);
            const fieldLabel = field ? field.label : rule.field;
            const op = this.operators[rule.operator];
            const opLabel = op ? op.label.toLowerCase() : rule.operator;
            if (!op || !op.needs_value) {
                return fieldLabel + ' ' + opLabel;
            }
            return fieldLabel + ' ' + opLabel + ' [' + rule.value + ']';
        }
    }"
    class="space-y-4"
>
    {{-- Enable/Disable Toggle --}}
    <div class="form-control">
        <label class="label cursor-pointer justify-start gap-3">
            <input
                type="checkbox"
                class="toggle toggle-sm toggle-primary"
                x-model="enabled"
                @change="toggleConditions()"
            />
            <span class="label-text font-medium">{{ __( 'Enable conditional logic' ) }}</span>
        </label>
    </div>

    {{-- Condition Builder (shown when enabled) --}}
    <div x-show="enabled" x-cloak class="space-y-4">
        {{-- Action Selector --}}
        <div class="form-control">
            <label class="label" for="condition-action">
                <span class="label-text text-xs">{{ __( 'Action' ) }}</span>
            </label>
            <select
                id="condition-action"
                class="select select-sm select-bordered w-full"
                x-model="logic.action"
                @change="updateConditions()"
            >
                <template x-for="(config, key) in actions" :key="key">
                    <option :value="key" x-text="config.label" :selected="logic.action === key"></option>
                </template>
            </select>
        </div>

        {{-- Logic Type Selector --}}
        <div class="form-control">
            <label class="label" for="condition-logic">
                <span class="label-text text-xs">{{ __( 'When' ) }}</span>
            </label>
            <select
                id="condition-logic"
                class="select select-sm select-bordered w-full"
                x-model="logic.logic"
                @change="updateConditions()"
            >
                <template x-for="(config, key) in logicTypes" :key="key">
                    <option :value="key" x-text="config.label" :selected="logic.logic === key"></option>
                </template>
            </select>
        </div>

        {{-- Rules --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <span class="label-text text-xs font-medium">{{ __( 'Conditions' ) }}</span>
                <button
                    type="button"
                    class="btn btn-xs btn-ghost"
                    @click="addRule()"
                    :disabled="availableFields.length === 0"
                >
                    <x-artisanpack-icon name="o-plus" class="h-3 w-3" />
                    {{ __( 'Add' ) }}
                </button>
            </div>

            {{-- No fields available warning --}}
            <template x-if="availableFields.length === 0">
                <div class="alert alert-warning py-2 text-xs">
                    <x-artisanpack-icon name="o-exclamation-triangle" class="h-4 w-4" />
                    <span>{{ __( 'No fields available for conditions. Add more fields to the form first.' ) }}</span>
                </div>
            </template>

            {{-- Rule List --}}
            <template x-for="(rule, index) in logic.rules" :key="index">
                <div class="rounded-lg bg-base-300 p-3 space-y-2">
                    <div class="flex items-start justify-between">
                        <span class="badge badge-sm badge-neutral" x-text="'Rule ' + (index + 1)"></span>
                        <button
                            type="button"
                            class="btn btn-xs btn-ghost btn-circle text-error"
                            @click="removeRule(index)"
                            title="{{ __( 'Remove rule' ) }}"
                        >
                            <x-artisanpack-icon name="o-x-mark" class="h-3 w-3" />
                        </button>
                    </div>

                    {{-- Target Field --}}
                    <div class="form-control">
                        <label class="label py-0">
                            <span class="label-text text-xs">{{ __( 'Field' ) }}</span>
                        </label>
                        <select
                            class="select select-xs select-bordered w-full"
                            x-model="rule.field"
                            @change="updateConditions()"
                        >
                            <option value="">{{ __( 'Select a field...' ) }}</option>
                            <template x-for="f in availableFields" :key="f.name">
                                <option :value="f.name" x-text="f.label" :selected="rule.field === f.name"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Operator --}}
                    <div class="form-control">
                        <label class="label py-0">
                            <span class="label-text text-xs">{{ __( 'Operator' ) }}</span>
                        </label>
                        <select
                            class="select select-xs select-bordered w-full"
                            x-model="rule.operator"
                            @change="updateConditions()"
                        >
                            <template x-for="(config, key) in getOperatorsForField(rule.field)" :key="key">
                                <option :value="key" x-text="config.label" :selected="rule.operator === key"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Value Input (conditional based on operator) --}}
                    <template x-if="operatorNeedsValue(rule.operator)">
                        <div class="form-control">
                            <label class="label py-0">
                                <span class="label-text text-xs">{{ __( 'Value' ) }}</span>
                            </label>

                            {{-- For fields with options (select/radio), show dropdown --}}
                            <template x-if="fieldHasOptions(rule.field)">
                                <select
                                    class="select select-xs select-bordered w-full"
                                    x-model="rule.value"
                                    @change="updateConditions()"
                                >
                                    <option value="">{{ __( 'Select a value...' ) }}</option>
                                    <template x-for="option in getFieldOptions(rule.field)" :key="option.value">
                                        <option :value="option.value" x-text="option.label" :selected="rule.value === option.value"></option>
                                    </template>
                                </select>
                            </template>

                            {{-- For other fields, show text input --}}
                            <template x-if="!fieldHasOptions(rule.field)">
                                <input
                                    type="text"
                                    class="input input-xs input-bordered w-full"
                                    x-model="rule.value"
                                    @input.debounce.300ms="updateConditions()"
                                    :placeholder="getValuePlaceholder(rule.operator)"
                                />
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- No rules message --}}
            <template x-if="logic.rules.length === 0 &amp;&amp; availableFields.length &gt; 0">
                <div class="text-center py-4 text-base-content/50 text-xs">
                    <p>{{ __( 'No conditions added yet.' ) }}</p>
                    <p>{{ __( 'Click "Add" to create a condition.' ) }}</p>
                </div>
            </template>
        </div>

        {{-- Preview --}}
        <template x-if="logic.rules.length &gt; 0">
            <div class="rounded-lg bg-base-300/50 p-3">
                <p class="text-xs text-base-content/70">
                    <span class="font-medium" x-text="logic.action === 'show' ? '{{ __( 'Show' ) }}' : '{{ __( 'Hide' ) }}'"></span>
                    {{ __( 'this field when' ) }}
                    <span class="font-medium" x-text="logic.logic === 'all' ? '{{ __( 'all' ) }}' : '{{ __( 'any' ) }}'"></span>
                    {{ __( 'of the following conditions are met:' ) }}
                </p>
                <ul class="list-disc list-inside mt-2 text-xs text-base-content/60">
                    <template x-for="(rule, index) in logic.rules" :key="index">
                        <li x-text="getRuleDescription(rule)"></li>
                    </template>
                </ul>
            </div>
        </template>
    </div>
</div>
