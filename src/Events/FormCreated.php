<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Events;

use ArtisanPackUI\Forms\Models\Form;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FormCreated Event
 *
 * Dispatched after a form is successfully created.
 * This event can be used for native Laravel event listeners.
 *
 * @since 1.0.0
 */
class FormCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The form instance.
     */
    public Form $form;

    /**
     * Create a new event instance.
     */
    public function __construct(Form $form)
    {
        $this->form = $form;
    }
}
