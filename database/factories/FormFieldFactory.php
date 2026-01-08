<?php
/**
 * Form field model factory.
 *
 * Provides factory methods for creating FormField model instances
 * during testing, including states for different field types
 * (text, email, textarea, select, file, time) and configurations.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Database\Factories;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Form field model factory class.
 *
 * @extends Factory<FormField>
 *
 * @since 1.0.0
 */
class FormFieldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FormField>
     */
    protected $model = FormField::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->words(2, true);

        return [
            'form_id' => Form::factory(),
            'step_id' => null,
            'uuid' => Str::uuid()->toString(),
            'name' => Str::slug($label, '_'),
            'type' => 'text',
            'label' => ucwords($label),
            'placeholder' => fake()->optional()->sentence(3),
            'help_text' => fake()->optional()->sentence(),
            'is_required' => false,
            'validation_rules' => null,
            'field_config' => null,
            'default_value' => null,
            'conditional_logic' => null,
            'width' => 'full',
            'css_classes' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the field is a text field.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
        ]);
    }

    /**
     * Indicate that the field is an email field.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'email',
            'name' => 'email',
            'label' => 'Email Address',
            'placeholder' => 'you@example.com',
        ]);
    }

    /**
     * Indicate that the field is a textarea field.
     */
    public function textarea(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'textarea',
            'field_config' => ['rows' => 4],
        ]);
    }

    /**
     * Indicate that the field is a select field.
     */
    public function select(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'select',
            'field_config' => [
                'options' => [
                    ['value' => 'option1', 'label' => 'Option 1'],
                    ['value' => 'option2', 'label' => 'Option 2'],
                    ['value' => 'option3', 'label' => 'Option 3'],
                ],
            ],
        ]);
    }

    /**
     * Indicate that the field is a file upload field.
     */
    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'field_config' => [
                'allowed_types' => ['pdf', 'doc', 'docx'],
                'max_size' => 5120,
            ],
        ]);
    }

    /**
     * Indicate that the field is a time field.
     */
    public function time(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'time',
            'name' => 'appointment_time',
            'label' => 'Appointment Time',
            'field_config' => [
                'step' => 60,
            ],
        ]);
    }

    /**
     * Indicate that the field is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Indicate that the field has conditional logic.
     */
    public function withConditions(): static
    {
        return $this->state(fn (array $attributes) => [
            'conditional_logic' => [
                'action' => 'show',
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

    /**
     * Set the field width.
     */
    public function width(string $width): static
    {
        return $this->state(fn (array $attributes) => [
            'width' => $width,
        ]);
    }
}
