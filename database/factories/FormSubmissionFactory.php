<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Database\Factories;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for the FormSubmission model.
 *
 * @extends Factory<FormSubmission>
 *
 * @since 1.0.0
 */
class FormSubmissionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FormSubmission>
     */
    protected $model = FormSubmission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'submission_number' => null, // Will be auto-generated
            'page_url' => fake()->url(),
            'referrer_url' => fake()->optional()->url(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'is_read' => false,
            'is_spam' => false,
            'is_starred' => false,
            'admin_notes' => null,
        ];
    }

    /**
     * Indicate that the submission has been read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Indicate that the submission is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    /**
     * Indicate that the submission is spam.
     */
    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_spam' => true,
        ]);
    }

    /**
     * Indicate that the submission is starred.
     */
    public function starred(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_starred' => true,
        ]);
    }

    /**
     * Create a submission with values.
     */
    public function withValues(int $count = 3): static
    {
        return $this->afterCreating(function (FormSubmission $submission) use ($count): void {
            \ArtisanPackUI\Forms\Models\FormSubmissionValue::factory()
                ->count($count)
                ->for($submission, 'submission')
                ->create();
        });
    }

    /**
     * Create a submission with an admin note.
     */
    public function withNotes(?string $notes = null): static
    {
        return $this->state(fn (array $attributes) => [
            'admin_notes' => $notes ?? fake()->paragraph(),
        ]);
    }

    /**
     * Create an old submission for testing pruning.
     */
    public function old(int $daysAgo = 90): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now()->subDays($daysAgo),
            'updated_at' => now()->subDays($daysAgo),
        ]);
    }
}
