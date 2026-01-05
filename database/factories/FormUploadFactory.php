<?php

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Database\Factories;

use ArtisanPackUI\Forms\Models\FormSubmission;
use ArtisanPackUI\Forms\Models\FormUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for the FormUpload model.
 *
 * @extends Factory<FormUpload>
 *
 * @since 1.0.0
 */
class FormUploadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FormUpload>
     */
    protected $model = FormUpload::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalName = fake()->word().'.pdf';
        $storedName = Str::uuid().'_'.$originalName;

        return [
            'submission_id' => FormSubmission::factory(),
            'field_id' => null,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'disk' => 'form-uploads',
            'path' => 'uploads/'.$storedName,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1048576), // 1KB to 1MB
        ];
    }

    /**
     * Indicate that the upload is an image.
     */
    public function image(): static
    {
        return $this->state(function (array $attributes) {
            $originalName = fake()->word().'.jpg';
            $storedName = Str::uuid().'_'.$originalName;

            return [
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'path' => 'uploads/'.$storedName,
                'mime_type' => 'image/jpeg',
            ];
        });
    }

    /**
     * Indicate that the upload is a PDF document.
     */
    public function pdf(): static
    {
        return $this->state(function (array $attributes) {
            $originalName = fake()->word().'.pdf';
            $storedName = Str::uuid().'_'.$originalName;

            return [
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'path' => 'uploads/'.$storedName,
                'mime_type' => 'application/pdf',
            ];
        });
    }

    /**
     * Indicate that the upload is a Word document.
     */
    public function document(): static
    {
        return $this->state(function (array $attributes) {
            $originalName = fake()->word().'.docx';
            $storedName = Str::uuid().'_'.$originalName;

            return [
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'path' => 'uploads/'.$storedName,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];
        });
    }

    /**
     * Set a specific file size.
     */
    public function size(int $bytes): static
    {
        return $this->state(fn (array $attributes) => [
            'size' => $bytes,
        ]);
    }
}
