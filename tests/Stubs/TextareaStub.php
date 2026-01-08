<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Textarea Stub Component
 *
 * A stub component that replaces the artisanpack-textarea component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class TextareaStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $label = null,
        public ?string $name = null,
        public ?string $placeholder = null,
        public ?string $hint = null,
        public ?string $error = null,
        public bool $required = false,
        public int $rows = 4,
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view( 'stubs.textarea' );
    }
}
