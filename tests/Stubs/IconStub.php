<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Icon Stub Component
 *
 * A stub component that replaces the artisanpack-icon component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class IconStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $name = '',
        public string $class = '',
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): string
    {
        $escapedClass = htmlspecialchars( $this->class, ENT_QUOTES, 'UTF-8' );
        $escapedName  = htmlspecialchars( $this->name, ENT_QUOTES, 'UTF-8' );

        return "<span class=\"icon-stub {$escapedClass}\" data-icon=\"{$escapedName}\"></span>";
    }
}
