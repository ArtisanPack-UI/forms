<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Menu Stub Component
 *
 * A stub component that replaces the artisanpack-menu component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class MenuStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $icon = null,
        public bool $activatable = false,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('stubs.menu');
    }
}
