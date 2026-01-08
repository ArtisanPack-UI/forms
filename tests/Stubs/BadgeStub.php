<?php

declare( strict_types=1 );

namespace Tests\Stubs;

use Illuminate\View\Component;

/**
 * Badge Stub Component
 *
 * A stub component that replaces the artisanpack-badge component
 * in tests where the livewire-ui-components package is not available.
 *
 * @since 1.0.0
 */
class BadgeStub extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ?string $value = null,
        public ?string $color = null,
        public ?string $class = '',
    ) {
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): string
    {
        $escapedValue = htmlspecialchars( (string) $this->value, ENT_QUOTES, 'UTF-8' );
        $escapedClass = htmlspecialchars( (string) $this->class, ENT_QUOTES, 'UTF-8' );
        $colorClass   = $this->color ? ' badge-' . htmlspecialchars( $this->color, ENT_QUOTES, 'UTF-8' ) : '';

        return "<span class=\"badge-stub{$colorClass} {$escapedClass}\">{$escapedValue}</span>";
    }
}
