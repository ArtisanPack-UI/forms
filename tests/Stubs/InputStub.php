<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Input Stub Component
 *
 * A stub component that replaces the artisanpack-input component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class InputStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $label = null,
        public ?string $type = 'text',
        public ?string $name = null,
        public ?string $placeholder = null,
        public ?string $hint = null,
        public ?string $error = null,
        public bool $required = false,
        public ?string $prefix = null,
        public ?string $suffix = null,
        public mixed $prepend = null,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view( 'stubs.input' );
    }
}
