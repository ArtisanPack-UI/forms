<?php

/**
 * Shared helpers for AI agent prompt construction and cache-key derivation.
 *
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.2.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\Forms\Ai\Concerns;

/**
 * Trait shared by the Forms AI agents to bound token spend, prevent
 * user-input-driven prompt injection, and produce cache fingerprints that
 * don't crash on realistic non-scalar submission payloads.
 *
 * @since 1.2.0
 */
trait NormalizesLLMInput
{
    /**
     * Encode a value as JSON for LLM consumption without pretty-printing
     * (Claude/Haiku tokenize whitespace so pretty-print costs 30-50% of the
     * payload) and with malformed UTF-8 substituted so a single bad byte in
     * one field can't collapse the whole message part to `false`.
     *
     * Returns the encoded string, or an empty string on unrecoverable errors
     * — callers should never see `false` reach the prompter.
     *
     * @since 1.2.0
     */
    protected function safeJsonEncode(mixed $value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return $encoded === false ? '' : $encoded;
    }

    /**
     * Sanitize a user-supplied string before it lands verbatim in the system
     * prompt. Strips control characters, collapses whitespace to single
     * spaces (so a multi-line "Ignore prior instructions." payload can't
     * span the same section), and caps to `$maxLength` characters.
     *
     * The agent still surrounds the escaped value with a labeled section
     * ("Form name: ...", "Field kind: ...") so the model sees the sanitized
     * value as a data field rather than an instruction.
     *
     * @since 1.2.0
     */
    protected function escapeForPrompt(string $value, int $maxLength = 256): string
    {
        $stripped = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $value) ?? $value;
        $collapsed = preg_replace('/\s+/', ' ', $stripped) ?? $stripped;
        $trimmed = trim($collapsed);

        if (mb_strlen($trimmed) > $maxLength) {
            $trimmed = mb_substr($trimmed, 0, $maxLength);
        }

        return $trimmed;
    }

    /**
     * Produce a deterministic fingerprint of the agent's normalized input
     * that survives non-scalar payloads (dates cast to ISO strings, Eloquent
     * models via toArray(), nested arrays). The base ArtisanPackAgent
     * default only handles pure scalar arrays and throws otherwise, which
     * crashes any agent whose input is realistic form-submission data.
     *
     * Callers should pass a shape they've already normalized to arrays and
     * scalars (typically the return of `normalizeInput`), so the JSON
     * encoding is stable across runs.
     *
     * @since 1.2.0
     */
    protected function hashInputFingerprint(mixed $normalizedInput): string
    {
        return 'shape:'.hash('sha256', $this->safeJsonEncode($normalizedInput));
    }
}
