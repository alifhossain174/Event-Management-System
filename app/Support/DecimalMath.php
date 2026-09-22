<?php

namespace App\Support;

use InvalidArgumentException;

final class DecimalMath
{
    public const SCALE = 4;

    public static function normalize(string|int $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > self::SCALE) {
            throw new InvalidArgumentException('Money values may contain at most four decimal places.');
        }

        $integer = ltrim($matches[2], '0') ?: '0';
        $fraction = str_pad($fraction, self::SCALE, '0');
        $sign = $matches[1] === '-' && ($integer !== '0' || trim($fraction, '0') !== '') ? '-' : '';

        return $sign.$integer.'.'.$fraction;
    }

    public static function add(string|int $left, string|int $right): string
    {
        [$leftNegative, $leftDigits] = self::parts($left);
        [$rightNegative, $rightDigits] = self::parts($right);

        if ($leftNegative === $rightNegative) {
            return self::format($leftNegative, self::addUnsigned($leftDigits, $rightDigits));
        }

        $comparison = self::compareUnsigned($leftDigits, $rightDigits);
        if ($comparison === 0) {
            return '0.0000';
        }

        return $comparison > 0
            ? self::format($leftNegative, self::subtractUnsigned($leftDigits, $rightDigits))
            : self::format($rightNegative, self::subtractUnsigned($rightDigits, $leftDigits));
    }

    public static function subtract(string|int $left, string|int $right): string
    {
        $right = self::normalize($right);

        return self::add($left, str_starts_with($right, '-') ? substr($right, 1) : '-'.$right);
    }

    /** @param iterable<string|int> $values */
    public static function sum(iterable $values): string
    {
        $total = '0.0000';
        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return $total;
    }

    public static function compare(string|int $left, string|int $right): int
    {
        [$leftNegative, $leftDigits] = self::parts($left);
        [$rightNegative, $rightDigits] = self::parts($right);

        if ($leftNegative !== $rightNegative) {
            return $leftNegative ? -1 : 1;
        }

        $comparison = self::compareUnsigned($leftDigits, $rightDigits);

        return $leftNegative ? -$comparison : $comparison;
    }

    public static function multiply(string|int $left, string|int $right): string
    {
        [$leftNegative, $leftDigits] = self::scaledParts($left, self::SCALE);
        [$rightNegative, $rightDigits] = self::scaledParts($right, self::SCALE);

        return self::format($leftNegative !== $rightNegative, self::roundScale(
            self::multiplyUnsigned($leftDigits, $rightDigits),
            self::SCALE * 2,
            self::SCALE,
        ));
    }

    public static function percentage(string|int $amount, string|int $rate): string
    {
        [$amountNegative, $amountDigits] = self::scaledParts($amount, self::SCALE);
        [$rateNegative, $rateDigits] = self::scaledParts($rate, 6);

        return self::format($amountNegative !== $rateNegative, self::roundScale(
            self::multiplyUnsigned($amountDigits, $rateDigits),
            self::SCALE + 6 + 2,
            self::SCALE,
        ));
    }

    public static function negate(string|int $value): string
    {
        $value = self::normalize($value);

        return $value === '0.0000' ? $value : (str_starts_with($value, '-') ? substr($value, 1) : '-'.$value);
    }

    /** @return array{bool, string} */
    private static function parts(string|int $value): array
    {
        $normalized = self::normalize($value);
        $negative = str_starts_with($normalized, '-');
        $digits = str_replace('.', '', ltrim($normalized, '-'));

        return [$negative, ltrim($digits, '0') ?: '0'];
    }

    /** @return array{bool, string} */
    private static function scaledParts(string|int $value, int $scale): array
    {
        $value = trim((string) $value);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }
        $fraction = $matches[3] ?? '';
        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException("Decimal value may contain at most {$scale} decimal places.");
        }
        $digits = (ltrim($matches[2], '0') ?: '0').str_pad($fraction, $scale, '0');

        return [$matches[1] === '-' && trim($digits, '0') !== '', ltrim($digits, '0') ?: '0'];
    }

    private static function format(bool $negative, string $digits): string
    {
        $digits = str_pad(ltrim($digits, '0') ?: '0', self::SCALE + 1, '0', STR_PAD_LEFT);
        $integer = substr($digits, 0, -self::SCALE);
        $fraction = substr($digits, -self::SCALE);
        $isZero = trim($digits, '0') === '';

        return ($negative && ! $isZero ? '-' : '').$integer.'.'.$fraction;
    }

    private static function compareUnsigned(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    private static function addUnsigned(string $left, string $right): string
    {
        $left = strrev($left);
        $right = strrev($right);
        $carry = 0;
        $result = '';
        $length = max(strlen($left), strlen($right));

        for ($index = 0; $index < $length; $index++) {
            $sum = (int) ($left[$index] ?? 0) + (int) ($right[$index] ?? 0) + $carry;
            $result .= (string) ($sum % 10);
            $carry = intdiv($sum, 10);
        }

        if ($carry) {
            $result .= (string) $carry;
        }

        return strrev($result);
    }

    private static function subtractUnsigned(string $left, string $right): string
    {
        $left = strrev($left);
        $right = strrev($right);
        $borrow = 0;
        $result = '';

        for ($index = 0; $index < strlen($left); $index++) {
            $digit = (int) $left[$index] - (int) ($right[$index] ?? 0) - $borrow;
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result .= (string) $digit;
        }

        return strrev(rtrim($result, '0')) ?: '0';
    }

    private static function multiplyUnsigned(string $left, string $right): string
    {
        $result = '0';
        foreach (array_reverse(str_split($right)) as $position => $rightDigit) {
            $carry = 0;
            $row = '';
            foreach (array_reverse(str_split($left)) as $leftDigit) {
                $product = ((int) $leftDigit * (int) $rightDigit) + $carry;
                $row = ($product % 10).$row;
                $carry = intdiv($product, 10);
            }
            if ($carry) {
                $row = $carry.$row;
            }
            $result = self::addUnsigned($result, $row.str_repeat('0', $position));
        }

        return ltrim($result, '0') ?: '0';
    }

    private static function roundScale(string $digits, int $fromScale, int $toScale): string
    {
        $digits = str_pad($digits, $fromScale + 1, '0', STR_PAD_LEFT);
        $drop = $fromScale - $toScale;
        if ($drop <= 0) {
            return $digits.str_repeat('0', -$drop);
        }

        $kept = substr($digits, 0, -$drop) ?: '0';
        $firstDropped = (int) substr($digits, -$drop, 1);

        return $firstDropped >= 5 ? self::addUnsigned($kept, '1') : $kept;
    }
}
