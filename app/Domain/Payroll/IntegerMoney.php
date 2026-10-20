<?php

namespace App\Domain\Payroll;

use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

class IntegerMoney
{
    public static function sum(int ...$amounts): int
    {
        return self::rounded(BigRational::of(BigInteger::sum(...$amounts)), 'half_up');
    }

    // IDR minor units are sen (100 sen = 1 rupiah); no binary floating point is used.
    public static function rounded(BigRational $value, string $mode): int
    {
        $amount = $value->toScale(0, match ($mode) {
            'half_up' => RoundingMode::HalfUp, 'floor' => RoundingMode::Floor, 'ceiling' => RoundingMode::Ceiling
        })->toBigInteger();
        abort_if($amount->abs()->isGreaterThan('1000000000000000'), 422, 'Nominal melampaui batas perhitungan.');

        return $amount->toInt();
    }

    public static function ratio(int $value, int $numerator, int $denominator, string $rounding): int
    {
        return self::rounded(BigRational::of($value)->multipliedBy($numerator)->dividedBy($denominator), $rounding);
    }

    public static function format(int $minor): string
    {
        return BigInteger::of($minor)->quotient(100).'.'.str_pad((string) BigInteger::of($minor)->remainder(100), 2, '0', STR_PAD_LEFT);
    }
}
