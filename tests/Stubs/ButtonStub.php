<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Button Stub Component
 *
 * A stub component that replaces the artisanpack-button component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class ButtonStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $type = 'button',
        public ?string $color = null,
        public ?string $size = null,
        public bool $disabled = false,
        public ?string $icon = null,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view( 'stubs.button' );
    }
}
