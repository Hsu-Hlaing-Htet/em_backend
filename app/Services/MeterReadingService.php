<?php

namespace App\Services;

class MeterReadingService
{
    /**
     * @return array{usage:float,calculated_amount:float}
     */
    public function calculate(float $previous, float $current, float $ratePerUnit): array
    {
        $usage = max($current - $previous, 0);

        return [
            'usage' => round($usage, 2),
            'calculated_amount' => round($usage * $ratePerUnit, 2),
        ];
    }
}
