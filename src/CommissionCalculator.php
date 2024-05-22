<?php

declare(strict_types=1);

namespace App;

use Exception;

class CommissionCalculator
{
    private array $exchangeRates;

    public function __construct($exchangeRates)
    {
        $this->exchangeRates = $exchangeRates;
    }

    public function calculate($operations): array
    {
        $fees = [];
        $weeklyWithdrawals = [];

        foreach ($operations as $operation) {
            [$date, $userId, $userType, $operationType, $amount, $currency] = $operation;

            if ($operationType === 'deposit') {
                $fee = $this->calculateDepositFee($amount);
            } elseif ($operationType === 'withdraw') {
                $fee = $this->calculateWithdrawFee($userId, $userType, $amount, $currency, $date, $weeklyWithdrawals);
            } else {
                throw new Exception("Unknown operation type: $operationType");
            }

            $fees[] = number_format($fee, $this->getDecimalPlaces($currency), '.', '');
        }

        return $fees;
    }

    private function calculateDepositFee($amount): float|int
    {
        return ceil($amount * 0.0003 * 100) / 100;
    }

    private function calculateWithdrawFee($userId, $userType, $amount, $currency, $date, &$weeklyWithdrawals): float|int
    {
        $amountInEur = $this->convertToBaseCurrency($amount, $currency);
        $week = $this->getWeek($date);

        if ($userType === 'private') {
            if (!isset($weeklyWithdrawals[$userId])) {
                $weeklyWithdrawals[$userId] = [];
            }

            if (!isset($weeklyWithdrawals[$userId][$week])) {
                $weeklyWithdrawals[$userId][$week] = ['count' => 0, 'amount' => 0];
            }

            if ($weeklyWithdrawals[$userId][$week]['count'] < 3 && $weeklyWithdrawals[$userId][$week]['amount'] + $amountInEur <= 1000) {
                $weeklyWithdrawals[$userId][$week]['count']++;
                $weeklyWithdrawals[$userId][$week]['amount'] += $amountInEur;
                return 0;
            } else {
                $exceedAmount = max(0, $amountInEur + $weeklyWithdrawals[$userId][$week]['amount'] - 1000);
                $fee = $exceedAmount * 0.003;
                $weeklyWithdrawals[$userId][$week]['amount'] += $amountInEur;
                $weeklyWithdrawals[$userId][$week]['count']++;
                return ceil($fee * 100) / 100;
            }
        } elseif ($userType === 'business') {
            return ceil($amount * 0.005 * 100) / 100;
        }

        throw new Exception("Unknown user type: $userType");
    }

    private function convertToBaseCurrency($amount, $currency)
    {
        if ($currency === getenv("BASE_CURRENCY")) {
            return $amount;
        }

        if (!isset($this->exchangeRates[$currency])) {
            throw new Exception("Unsupported currency: $currency");
        }

        return $amount / $this->exchangeRates[$currency];
    }

    // getDecimalPlaces function is used to determine the number of decimal places to which the commission
    // fee should be rounded up, based on the currency of the operation. Different currencies have different
    // conventions for decimal places:
    // JPY (Japanese Yen) typically does not use any decimal places.
    // USD (United States Dollar) and EUR (Euro) use two decimal places
    private function getDecimalPlaces($currency): int
    {
        return match ($currency) {
            'JPY' => 0,
            default => 2,
        };
    }

    private function getWeek($date): string
    {
        $dt = new \DateTime($date);
        return $dt->format("oW"); // "o" gives the ISO-8601 year number, "W" gives the week number
    }
}
