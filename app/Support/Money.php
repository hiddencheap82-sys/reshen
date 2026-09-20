<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Integer Rial value object. Floating point in a percentage-payout
 * calculation accumulates rounding error and starts arguments with the
 * barber over money — exactly the problem the product exists to remove.
 * Percentage rounding always favors the staff member (round up their share).
 */
final class Money
{
    private function __construct(public readonly int $rials)
    {
    }

    public static function fromRials(int $rials): self
    {
        return new self($rials);
    }

    public static function fromToman(int $toman): self
    {
        return new self($toman * 10);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(Money $other): self
    {
        return new self($this->rials + $other->rials);
    }

    public function subtract(Money $other): self
    {
        return new self($this->rials - $other->rials);
    }

    /** Percentage share, rounded up in the staff's favor. */
    public function percentOf(float $percent): self
    {
        return new self((int) ceil($this->rials * $percent / 100));
    }

    public function toToman(): int
    {
        return intdiv($this->rials, 10);
    }

    public function isNegative(): bool
    {
        return $this->rials < 0;
    }

    public function isZero(): bool
    {
        return $this->rials === 0;
    }

    /** Persian-digit, comma-grouped Toman string for display — e.g. "۱٬۲۵۰٬۰۰۰ تومان". */
    public function formatToman(): string
    {
        $toman = $this->toToman();
        $formatted = number_format($toman, 0, '.', '٬');

        return Jalali::toPersianDigits($formatted) . ' تومان';
    }

    public function formatRials(): string
    {
        $formatted = number_format($this->rials, 0, '.', '٬');

        return Jalali::toPersianDigits($formatted) . ' ریال';
    }
}
