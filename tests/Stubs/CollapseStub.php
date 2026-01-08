<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Collapse Stub Component
 *
 * A stub component that replaces the artisanpack-collapse component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class CollapseStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $title = null,
        public bool $open = false,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('stubs.collapse');
    }
}
