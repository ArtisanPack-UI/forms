<?php
/**
 * Form notification model factory.
 *
 * Provides factory methods for creating FormNotification model instances
 * during testing, including states for admin notifications, autoresponders,
 * custom notifications, and conditional logic.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Database\Factories;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Form notification model factory class.
 *
 * @extends Factory<FormNotification>
 *
 * @since 1.0.0
 */
class FormNotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FormNotification>
     */
    protected $model = FormNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'type' => FormNotification::TYPE_ADMIN,
            'name' => 'Admin Notification',
            'to_email' => fake()->email(),
            'to_field' => null,
            'cc_emails' => null,
            'bcc_emails' => null,
            'reply_to_email' => null,
            'reply_to_field' => null,
            'from_name' => fake()->name(),
            'from_email' => fake()->email(),
            'subject' => 'New Form Submission: {form_name}',
            'message' => fake()->paragraph(),
            'conditional_logic' => null,
            'include_submission_data' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that this is an admin notification.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FormNotification::TYPE_ADMIN,
            'name' => 'Admin Notification',
            'to_email' => fake()->email(),
            'include_submission_data' => true,
        ]);
    }

    /**
     * Indicate that this is an autoresponder notification.
     */
    public function autoresponder(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FormNotification::TYPE_AUTORESPONDER,
            'name' => 'Thank You Email',
            'to_email' => null,
            'to_field' => 'email',
            'subject' => 'Thank you for your submission',
            'message' => 'We have received your submission and will be in touch soon.',
            'include_submission_data' => false,
        ]);
    }

    /**
     * Indicate that this is a custom notification.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FormNotification::TYPE_CUSTOM,
            'name' => 'Custom Notification',
        ]);
    }

    /**
     * Indicate that the notification is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the notification has conditional logic.
     */
    public function withConditions(): static
    {
        return $this->state(fn (array $attributes) => [
            'conditional_logic' => [
                'action' => 'send',
                'logic' => 'all',
                'rules' => [
                    [
                        'field' => 'some_field',
                        'operator' => 'equals',
                        'value' => 'some_value',
                    ],
                ],
            ],
        ]);
    }
}
