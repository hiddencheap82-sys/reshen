<?php

declare(strict_types=1);

namespace App\Support;

final class Str
{
    private const TOKEN_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** Short, unguessable, URL-safe token for public links (e.g. "x7k2m9pf"). */
    public static function token(int $length = 12): string
    {
        $alphabet = self::TOKEN_ALPHABET;
        $max = strlen($alphabet) - 1;
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[random_int(0, $max)];
        }

        return $token;
    }

    public static function slug(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return mb_strtolower($value);
    }

    public static function otp(int $digits = 5): string
    {
        $max = (int) str_repeat('9', $digits);
        $min = (int) ('1' . str_repeat('0', $digits - 1));

        return (string) random_int($min, $max);
    }
}
