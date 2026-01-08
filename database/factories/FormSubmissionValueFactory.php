<?php
/**
 * Form submission value model factory.
 *
 * Provides factory methods for creating FormSubmissionValue model instances
 * during testing, including states for email fields, array values, and file fields.
 *
 * @package    ArtisanPack_UI
 * @subpackage Forms
 * @author     Jacob Martella <support@artisanpackui.dev>
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Database\Factories;

use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormSubmissionValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Form submission value model factory class.
 *
 * @extends Factory<FormSubmissionValue>
 *
 * @since 1.0.0
 */
class FormSubmissionValueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FormSubmissionValue>
     */
    protected $model = FormSubmissionValue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fieldName = Str::slug(fake()->words(2, true), '_');

        return [
            'submission_id' => FormSubmission::factory(),
            'field_id' => null,
            'field_name' => $fieldName,
            'field_label' => ucwords(str_replace('_', ' ', $fieldName)),
            'field_type' => 'text',
            'value' => fake()->sentence(),
            'value_array' => null,
            'upload_id' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the value is for an email field.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_name' => 'email',
            'field_label' => 'Email Address',
            'field_type' => 'email',
            'value' => fake()->email(),
        ]);
    }

    /**
     * Indicate that the value is an array (checkbox group, multi-select).
     */
    public function array(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'checkbox_group',
            'value' => null,
            'value_array' => fake()->randomElements(['Option 1', 'Option 2', 'Option 3'], 2),
        ]);
    }

    /**
     * Indicate that the value is for a file field.
     */
    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'file',
            'value' => null,
        ]);
    }
}
