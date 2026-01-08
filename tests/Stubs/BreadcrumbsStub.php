<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Breadcrumbs Stub Component
 *
 * A stub component that replaces the artisanpack-breadcrumbs component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class BreadcrumbsStub extends Component
{
    /**
     * Create a new component instance.
     *
     * @param  array<int, array<string, string>>  $items
     */
    public function __construct(
        public array $items = [],
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('stubs.breadcrumbs');
    }
}
