<?php

/**
 * Calculation formula support.
 *
 * @author     Jacob Martella <support@artisanpackui.dev>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Forms\Support;

/**
 * Parses and evaluates a calculation field's formula, such as `{quantity} * {price}`.
 *
 * A formula is numbers, field merge tags (`{field_name}`), `+ - * /`,
 * parentheses and spaces. It is read by a small recursive descent parser,
 * never `eval`, so nothing else in a formula can run. Multiplication and
 * division bind tighter than addition and subtraction, and a leading `-` or
 * `+` negates or keeps the term after it.
 *
 * Each merge tag reads that field's value: a number, a numeric string, the
 * sum of a list of numbers (a checkbox group whose option values are
 * prices), true as 1, and anything else (blank, text) as 0. Division by zero
 * gives no result. `resources/js/shared/calculation.ts` mirrors this for
 * React and Vue.
 *
 * @since 1.6.0
 */
class Calculation
{
    /**
     * The longest formula accepted.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_LENGTH = 1000;

    /**
     * The deepest nesting of parentheses and signs accepted.
     *
     * @since 1.6.0
     *
     * @var int
     */
    public const MAX_DEPTH = 32;

    /**
     * Matches one token: a number, a merge tag, an operator or a parenthesis.
     *
     * @since 1.6.0
     *
     * @var string
     */
    protected const TOKEN_PATTERN = '/\G\s*(?:(\d+(?:\.\d+)?|\.\d+)|\{([A-Za-z0-9_\-]+)\}|([-+*\/()]))/';

    /**
     * The formula's tokens, while parsing.
     *
     * @since 1.6.0
     *
     * @var list<array{type: string, value: string}>
     */
    protected array $tokens = [];

    /**
     * The index of the next token, while parsing.
     *
     * @since 1.6.0
     */
    protected int $position = 0;

    /**
     * Whether the formula is only being checked, so a division by zero is not an error.
     *
     * @since 1.6.0
     */
    protected bool $checking = false;

    /**
     * Reads the value of a merge tag.
     *
     * @since 1.6.0
     *
     * @var callable(string): mixed
     */
    protected $resolve;

    /**
     * Creates a parser for one evaluation.
     *
     * @since 1.6.0
     *
     * @param  callable(string): mixed  $resolve  Reads a field's value by name.
     */
    protected function __construct( callable $resolve )
    {
        $this->resolve = $resolve;
    }

    /**
     * Splits a formula into tokens.
     *
     * @since 1.6.0
     *
     * @param  string  $formula  The formula.
     *
     * @return list<array{type: string, value: string}>|null The tokens (`number`, `field` or `operator`), or null when the formula holds anything else or is too long.
     */
    public static function tokenize( string $formula ): ?array
    {
        if ( mb_strlen( $formula ) > self::MAX_LENGTH ) {
            return null;
        }

        $tokens = [];
        $offset = 0;
        $length = strlen( $formula );

        while ( $offset < $length ) {
            if ( '' === trim( substr( $formula, $offset ) ) ) {
                break;
            }

            if ( 1 !== preg_match( self::TOKEN_PATTERN, $formula, $match, 0, $offset ) ) {
                return null;
            }

            $offset += strlen( $match[0] );

            if ( '' !== ( $match[1] ?? '' ) ) {
                $tokens[] = ['type' => 'number', 'value' => $match[1]];
            } elseif ( '' !== ( $match[2] ?? '' ) ) {
                $tokens[] = ['type' => 'field', 'value' => $match[2]];
            } else {
                $tokens[] = ['type' => 'operator', 'value' => $match[3]];
            }
        }

        return $tokens;
    }

    /**
     * Gets the field names a formula's merge tags refer to.
     *
     * @since 1.6.0
     *
     * @param  string  $formula  The formula.
     *
     * @return list<string> The names, each once, in the order they first appear; empty when the formula does not tokenize.
     */
    public static function references( string $formula ): array
    {
        $names = [];

        foreach ( self::tokenize( $formula ) ?? [] as $token ) {
            if ( 'field' === $token['type'] && ! in_array( $token['value'], $names, true ) ) {
                $names[] = $token['value'];
            }
        }

        return $names;
    }

    /**
     * Checks a formula and explains the first problem.
     *
     * @since 1.6.0
     *
     * @param  string  $formula  The formula.
     * @param  list<string>|null  $fieldNames  The names a merge tag may use, or null to accept any.
     *
     * @return string|null The problem, or null when the formula is valid.
     */
    public static function check( string $formula, ?array $fieldNames = null ): ?string
    {
        if ( '' === trim( $formula ) ) {
            return __( 'Enter a formula, such as {quantity} * {price}.' );
        }

        if ( mb_strlen( $formula ) > self::MAX_LENGTH ) {
            return __( 'The formula may not be longer than :max characters.', ['max' => self::MAX_LENGTH] );
        }

        if ( null === self::tokenize( $formula ) ) {
            return __( 'The formula may only use numbers, field merge tags such as {price}, + - * / and parentheses.' );
        }

        if ( null !== $fieldNames ) {
            foreach ( self::references( $formula ) as $name ) {
                if ( ! in_array( $name, $fieldNames, true ) ) {
                    return __( 'The formula refers to {:name}, which is not a field on this form.', ['name' => $name] );
                }
            }
        }

        if ( ! ( new self( fn (): int => 1 ) )->parses( $formula ) ) {
            return __( 'The formula is incomplete or has an operator or parenthesis out of place.' );
        }

        return null;
    }

    /**
     * Evaluates a formula.
     *
     * @since 1.6.0
     *
     * @param  string  $formula  The formula.
     * @param  callable(string): mixed  $resolve  Reads a field's value by name.
     *
     * @return float|null The result, or null when the formula is not valid, divides by zero or overflows.
     */
    public static function evaluate( string $formula, callable $resolve ): ?float
    {
        return ( new self( $resolve ) )->run( $formula );
    }

    /**
     * Reads a field value as a number.
     *
     * @since 1.6.0
     *
     * @param  mixed  $value  The value.
     *
     * @return float The number: the value, the sum of a list's numbers, 1 for true, otherwise 0.
     */
    public static function toNumber( mixed $value ): float
    {
        if ( is_int( $value ) || is_float( $value ) ) {
            return is_finite( (float) $value ) ? (float) $value : 0.0;
        }

        if ( true === $value ) {
            return 1.0;
        }

        if ( is_string( $value ) ) {
            $value = trim( $value );

            return is_numeric( $value ) && is_finite( (float) $value ) ? (float) $value : 0.0;
        }

        if ( is_array( $value ) && array_is_list( $value ) ) {
            return array_sum( array_map( fn ( mixed $item ): float => is_array( $item ) ? 0.0 : self::toNumber( $item ), $value ) );
        }

        return 0.0;
    }

    /**
     * Formats a result with a fixed number of decimal places.
     *
     * Rounds half away from zero, like `round()`. `formatCalculation()` in
     * `resources/js/shared/calculation.ts` matches.
     *
     * @since 1.6.0
     *
     * @param  float|null  $result  The result.
     * @param  int  $decimals  The decimal places.
     *
     * @return string The formatted result, or an empty string for no result.
     */
    public static function format( ?float $result, int $decimals ): string
    {
        if ( null === $result ) {
            return '';
        }

        $formatted = number_format( round( $result, $decimals ), $decimals, '.', '' );

        // Negative zero ("-0.00") reads as zero
        return 0.0 === (float) $formatted ? number_format( 0, $decimals, '.', '' ) : $formatted;
    }

    /**
     * Parses and evaluates a formula.
     *
     * @since 1.6.0
     *
     * @param  string  $formula  The formula.
     *
     * @return float|null The result, or null when it has none.
     */
    protected function run( string $formula ): ?float
    {
        $tokens = self::tokenize( $formula );

        if ( null === $tokens || [] === $tokens ) {
            return null;
        }

        $this->tokens   = $tokens;
        $this->position = 0;

        $result = $this->expression( 0 );

        if ( null === $result || $this->position !== count( $this->tokens ) || ! is_finite( $result ) ) {
            return null;
        }

        return $result;
    }

    /**
     * Checks whether a formula parses, whatever its values.
     *
     * @since 1.6.0
     *
     * @param  string  $formula  The formula.
     *
     * @return bool True when it parses.
     */
    protected function parses( string $formula ): bool
    {
        $tokens = self::tokenize( $formula );

        if ( null === $tokens || [] === $tokens ) {
            return false;
        }

        $this->tokens   = $tokens;
        $this->position = 0;
        $this->checking = true;

        return null !== $this->expression( 0 ) && $this->position === count( $this->tokens );
    }

    /**
     * Parses a sum or difference of terms.
     *
     * @since 1.6.0
     *
     * @param  int  $depth  The nesting depth.
     *
     * @return float|null The value, or null on an error.
     */
    protected function expression( int $depth ): ?float
    {
        $value = $this->term( $depth );

        while ( null !== $value && $this->peekOperator( ['+', '-'] ) ) {
            $operator = $this->tokens[ $this->position++ ]['value'];
            $right    = $this->term( $depth );

            if ( null === $right ) {
                return null;
            }

            $value = '+' === $operator ? $value + $right : $value - $right;
        }

        return $value;
    }

    /**
     * Parses a product or quotient of factors.
     *
     * @since 1.6.0
     *
     * @param  int  $depth  The nesting depth.
     *
     * @return float|null The value, or null on an error or a division by zero.
     */
    protected function term( int $depth ): ?float
    {
        $value = $this->factor( $depth );

        while ( null !== $value && $this->peekOperator( ['*', '/'] ) ) {
            $operator = $this->tokens[ $this->position++ ]['value'];
            $right    = $this->factor( $depth );

            if ( null === $right ) {
                return null;
            }

            if ( '/' === $operator && 0.0 === $right ) {
                if ( $this->checking ) {
                    continue;
                }

                return null;
            }

            $value = '*' === $operator ? $value * $right : $value / $right;
        }

        return $value;
    }

    /**
     * Parses a number, a merge tag, a signed factor or a parenthesized expression.
     *
     * @since 1.6.0
     *
     * @param  int  $depth  The nesting depth.
     *
     * @return float|null The value, or null on an error.
     */
    protected function factor( int $depth ): ?float
    {
        if ( $depth > self::MAX_DEPTH ) {
            return null;
        }

        $token = $this->tokens[ $this->position ] ?? null;

        if ( null === $token ) {
            return null;
        }

        $this->position++;

        if ( 'number' === $token['type'] ) {
            return (float) $token['value'];
        }

        if ( 'field' === $token['type'] ) {
            return self::toNumber( ( $this->resolve )( $token['value'] ) );
        }

        if ( '-' === $token['value'] || '+' === $token['value'] ) {
            $value = $this->factor( $depth + 1 );

            return null === $value || '+' === $token['value'] ? $value : -$value;
        }

        if ( '(' === $token['value'] ) {
            $value = $this->expression( $depth + 1 );

            if ( null === $value || ! $this->peekOperator( [')'] ) ) {
                return null;
            }

            $this->position++;

            return $value;
        }

        return null;
    }

    /**
     * Checks whether the next token is one of the given operators.
     *
     * @since 1.6.0
     *
     * @param  list<string>  $operators  The operators.
     *
     * @return bool True when it is.
     */
    protected function peekOperator( array $operators ): bool
    {
        $token = $this->tokens[ $this->position ] ?? null;

        return null !== $token && 'operator' === $token['type'] && in_array( $token['value'], $operators, true );
    }
}
