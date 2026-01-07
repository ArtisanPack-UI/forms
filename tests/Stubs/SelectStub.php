<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Select Stub Component
 *
 * A stub component that replaces the artisanpack-select component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class SelectStub extends Component
{
    /**
     * Create a new component instance.
     *
     * @param  array<int, array<string, string>>|null  $options
     */
    public function __construct(
        public ?string $label = null,
        public ?string $name = null,
        public ?string $placeholder = null,
        public ?string $hint = null,
        public ?string $error = null,
        public bool $required = false,
        public ?array $options = null,
        public ?string $optionValue = 'value',
        public ?string $optionLabel = 'label',
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('stubs.select');
    }
}
