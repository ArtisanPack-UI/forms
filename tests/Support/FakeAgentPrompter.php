<?php

/**
 * Test fake for the AgentPrompter contract.
 *
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace Tests\Support;

use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;

/**
 * Records every prompter call and returns queued responses in order.
 *
 *
 * @since      1.2.0
 */
final class FakeAgentPrompter implements AgentPrompter
{
    /**
     * Recorded calls, in invocation order.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $calls = [];

    /**
     * Queued responses (FIFO).
     *
     * @var array<int, array<string, mixed>>
     */
    private array $queue = [];

    /**
     * Push a canned response onto the queue.
     *
     * @since 1.2.0
     *
     * @param  array<string, mixed>  $output  Structured output body.
     * @param  int  $inputTokens  Reported input tokens.
     * @param  int  $outputTokens  Reported output tokens.
     */
    public function queue( array $output, int $inputTokens = 100, int $outputTokens = 50 ): void
    {
        $this->queue[] = [
            'output'        => $output,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function prompt(
        Credentials $credentials,
        string $model,
        string $instructions,
        string|array $message,
        array $outputSchema,
    ): array {
        $this->calls[] = [
            'credentials'   => $credentials,
            'model'         => $model,
            'instructions'  => $instructions,
            'message'       => $message,
            'output_schema' => $outputSchema,
        ];

        if ( [] === $this->queue ) {
            return [
                'output'        => [],
                'input_tokens'  => 0,
                'output_tokens' => 0,
            ];
        }

        return array_shift( $this->queue );
    }
}
