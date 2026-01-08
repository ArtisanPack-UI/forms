<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Checkbox Stub Component
 *
 * A stub component that replaces the artisanpack-checkbox component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class CheckboxStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $label = null,
        public ?string $name = null,
        public ?string $hint = null,
        public ?string $error = null,
        public bool $checked = false,
        public bool $required = false,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): string
    {
        $escapedLabel = htmlspecialchars( (string) $this->label, ENT_QUOTES, 'UTF-8' );
        $escapedName  = htmlspecialchars( (string) $this->name, ENT_QUOTES, 'UTF-8' );
        $checked      = $this->checked ? 'checked' : '';
        $required     = $this->required ? 'required' : '';

        return "<div class=\"checkbox-stub\"><input type=\"checkbox\" name=\"{$escapedName}\" {$checked} {$required} /><label>{$escapedLabel}</label></div>";
    }
}
