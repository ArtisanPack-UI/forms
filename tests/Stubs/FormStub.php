<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Form Stub Component
 *
 * A stub component that replaces the artisanpack-form component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class FormStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $action = null,
        public string $method = 'POST',
        public bool $noValidate = false,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view( 'stubs.form' );
    }
}
