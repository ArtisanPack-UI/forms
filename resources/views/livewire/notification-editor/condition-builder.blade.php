@php
    $hasConditions = $notification->has_conditional_logic;
    $conditionalLogic = $notification->conditional_logic ?? [
        'action' => 'send',
        'logic' => 'all',
        'rules' => [],
    ];
    $availableFields = $this->availableConditionFields;
@endphp

<div
    x-data="notificationConditionBuilder({
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
    })"
    class="space-y-4"
>
    {{-- Enable/Disable Toggle --}}
    <div class="form-control">
        <label class="label cursor-pointer justify-start gap-4">
            <input
                type="checkbox"
                class="toggle toggle-primary"
                x-model="enabled"
                @change="toggleConditions()"
            />
            <div>
                <span class="label-text font-medium">Enable conditional sending</span>
                <p class="text-xs text-base-content/60">Only send this notification when conditions are met</p>
            </div>
        </label>
    </div>

    {{-- Condition Builder (shown when enabled) --}}
    <template x-if="enabled">
        <div class="space-y-4">
            {{-- Action Selector --}}
            <div class="form-control">
                <label class="label" for="notification-condition-action">
                    <span class="label-text">Action</span>
                </label>
                <select
                    id="notification-condition-action"
                    class="select select-bordered w-full"
                    x-model="logic.action"
                    @change="updateConditions()"
                >
                    <template x-for="(config, key) in actions" :key="key">
                        <option :value="key" x-text="config.label" :selected="logic.action === key"></option>
                    </template>
                </select>
                <label class="label">
                    <span class="label-text-alt text-base-content/50" x-text="actions[logic.action]?.description || ''"></span>
                </label>
            </div>

            {{-- Logic Type Selector --}}
            <div class="form-control">
                <label class="label" for="notification-condition-logic">
                    <span class="label-text">When</span>
                </label>
                <select
                    id="notification-condition-logic"
                    class="select select-bordered w-full"
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
                    <span class="label-text font-medium">Conditions</span>
                    <button
                        type="button"
                        class="btn btn-xs btn-ghost"
                        @click="addRule()"
                        :disabled="availableFields.length === 0"
                    >
                        <x-artisanpack-icon name="o-plus" class="h-3 w-3" />
                        Add Condition
                    </button>
                </div>

                {{-- No fields available warning --}}
                <template x-if="availableFields.length === 0">
                    <div class="alert alert-warning">
                        <x-artisanpack-icon name="o-exclamation-triangle" class="h-5 w-5" />
                        <span>No fields available for conditions. Add fields to the form first.</span>
                    </div>
                </template>

                {{-- Rule List --}}
                <template x-for="(rule, index) in logic.rules" :key="index">
                    <div class="rounded-lg bg-base-300 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="badge badge-neutral" x-text="'Condition ' + (index + 1)"></span>
                            <button
                                type="button"
                                class="btn btn-xs btn-ghost text-error"
                                @click="removeRule(index)"
                                title="Remove condition"
                            >
                                <x-artisanpack-icon name="o-x-mark" class="h-4 w-4" />
                            </button>
                        </div>

                        {{-- Target Field --}}
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text text-sm">Field</span>
                            </label>
                            <select
                                class="select select-sm select-bordered w-full"
                                x-model="rule.field"
                                @change="updateConditions()"
                            >
                                <option value="">Select a field...</option>
                                <template x-for="f in availableFields" :key="f.name">
                                    <option :value="f.name" x-text="f.label" :selected="rule.field === f.name"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Operator --}}
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text text-sm">Operator</span>
                            </label>
                            <select
                                class="select select-sm select-bordered w-full"
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
                                <label class="label">
                                    <span class="label-text text-sm">Value</span>
                                </label>

                                {{-- For fields with options (select/radio), show dropdown --}}
                                <template x-if="fieldHasOptions(rule.field)">
                                    <select
                                        class="select select-sm select-bordered w-full"
                                        x-model="rule.value"
                                        @change="updateConditions()"
                                    >
                                        <option value="">Select a value...</option>
                                        <template x-for="option in getFieldOptions(rule.field)" :key="option.value">
                                            <option :value="option.value" x-text="option.label" :selected="rule.value === option.value"></option>
                                        </template>
                                    </select>
                                </template>

                                {{-- For other fields, show text input --}}
                                <template x-if="!fieldHasOptions(rule.field)">
                                    <input
                                        type="text"
                                        class="input input-sm input-bordered w-full"
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
                <template x-if="logic.rules.length === 0 && availableFields.length > 0">
                    <div class="py-6 text-center text-base-content/50">
                        <x-artisanpack-icon name="o-funnel" class="mx-auto mb-2 h-8 w-8" />
                        <p class="text-sm">No conditions added yet.</p>
                        <p class="text-xs">Click "Add Condition" to create a condition.</p>
                    </div>
                </template>
            </div>

            {{-- Preview --}}
            <template x-if="logic.rules.length > 0">
                <div class="rounded-lg bg-info/10 p-4">
                    <div class="flex items-start gap-3">
                        <x-artisanpack-icon name="o-information-circle" class="mt-0.5 h-5 w-5 text-info" />
                        <div>
                            <p class="text-sm font-medium text-info-content">
                                <span x-text="logic.action === 'send' ? 'Send' : 'Skip'"></span>
                                this notification when
                                <span x-text="logic.logic === 'all' ? 'all' : 'any'"></span>
                                of these conditions are met:
                            </p>
                            <ul class="mt-2 list-inside list-disc text-sm text-base-content/70">
                                <template x-for="(rule, index) in logic.rules" :key="index">
                                    <li x-text="getRuleDescription(rule)"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('notificationConditionBuilder', (config) => ({
            enabled: config.enabled,
            logic: config.logic,
            availableFields: config.availableFields,
            operators: config.operators,
            actions: config.actions,
            logicTypes: config.logicTypes,

            init() {
                // Ensure logic has the correct structure
                if (!this.logic.rules) {
                    this.logic.rules = [];
                }
            },

            toggleConditions() {
                if (this.enabled) {
                    // Initialize with default structure if needed
                    if (!this.logic.rules || this.logic.rules.length === 0) {
                        this.logic = {
                            action: 'send',
                            logic: 'all',
                            rules: []
                        };
                    }
                    this.updateConditions();
                } else {
                    // Clear conditions
                    this.$wire.clearConditionalLogic();
                }
            },

            addRule() {
                this.logic.rules.push({
                    field: '',
                    operator: 'equals',
                    value: ''
                });
            },

            removeRule(index) {
                this.logic.rules.splice(index, 1);
                this.updateConditions();
            },

            updateConditions() {
                // Only process if enabled
                if (!this.enabled) return;

                // Filter out incomplete rules (rules without a field selected)
                const validRules = (this.logic.rules || []).filter(rule => rule.field);

                if (validRules.length > 0) {
                    // Update with valid rules
                    this.$wire.updateConditionalLogic({
                        action: this.logic.action,
                        logic: this.logic.logic,
                        rules: validRules
                    });
                } else {
                    // Clear conditional logic when enabled but no valid rules remain
                    this.$wire.clearConditionalLogic();
                }
            },

            getOperatorsForField(fieldName) {
                const field = this.availableFields.find(f => f.name === fieldName);
                if (!field) return this.operators;

                // Filter operators based on field type
                return Object.fromEntries(
                    Object.entries(this.operators).filter(([key, op]) =>
                        op.supports_types.includes(field.type)
                    )
                );
            },

            operatorNeedsValue(operator) {
                const op = this.operators[operator];
                return op ? op.needs_value : true;
            },

            fieldHasOptions(fieldName) {
                const field = this.availableFields.find(f => f.name === fieldName);
                return field && field.options && field.options.length > 0;
            },

            getFieldOptions(fieldName) {
                const field = this.availableFields.find(f => f.name === fieldName);
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
                const field = this.availableFields.find(f => f.name === rule.field);
                const fieldLabel = field ? field.label : rule.field;
                const op = this.operators[rule.operator];
                const opLabel = op ? op.label.toLowerCase() : rule.operator;

                if (!op || !op.needs_value) {
                    return `"${fieldLabel}" ${opLabel}`;
                }

                return `"${fieldLabel}" ${opLabel} "${rule.value}"`;
            }
        }));
    });
</script>
