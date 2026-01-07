<?php

declare(strict_types=1);

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\Forms\Services\SubmissionService;
use Illuminate\Support\Facades\Log;

describe('Security Logging', function (): void {
    beforeEach(function (): void {
        $this->service = app(SubmissionService::class);
    });

    describe('spam detection logging', function (): void {
        it('logs honeypot trigger events', function (): void {
            Log::shouldReceive('warning')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, '[Forms Security] honeypot_triggered')
                        && isset($context['event_type'])
                        && $context['event_type'] === 'honeypot_triggered';
                });

            $this->service->isSpam('bot-filled-value', time() - 10, 1, '192.168.1.1');
        });

        it('logs fast submission events', function (): void {
            Log::shouldReceive('warning')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, '[Forms Security] submission_too_fast')
                        && isset($context['elapsed_seconds']);
                });

            $this->service->isSpam('', time() - 1, 1, '192.168.1.1');
        });

        it('does not log when submission is valid', function (): void {
            Log::shouldReceive('warning')->never();

            $result = $this->service->isSpam('', time() - 10, 1, '192.168.1.1');

            expect($result)->toBeFalse();
        });

        it('respects logging disabled config', function (): void {
            config(['artisanpack.forms.security.logging_enabled' => false]);

            Log::shouldReceive('warning')->never();

            $this->service->isSpam('bot-value', time() - 10, 1, '192.168.1.1');
        });
    });

    describe('rate limiting logging', function (): void {
        it('logs rate limit exceeded events', function (): void {
            $form = Form::factory()->create();
            $ipAddress = '192.168.1.100';

            // Exhaust rate limit
            for ($i = 0; $i < 5; $i++) {
                $this->service->recordAttempt($ipAddress, $form->id);
            }

            Log::shouldReceive('warning')
                ->once()
                ->withArgs(function ($message, $context) {
                    return str_contains($message, '[Forms Security] rate_limit_exceeded')
                        && isset($context['max_attempts']);
                });

            $this->service->isRateLimited($ipAddress, $form->id);
        });

        it('does not log rate limiting when not exceeded', function (): void {
            $form = Form::factory()->create();

            Log::shouldReceive('warning')->never();

            $result = $this->service->isRateLimited('192.168.1.200', $form->id);

            expect($result)->toBeFalse();
        });
    });
});
