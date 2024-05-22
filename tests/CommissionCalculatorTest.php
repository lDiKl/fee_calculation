<?php

use PHPUnit\Framework\TestCase;
use App\CommissionCalculator;

class CommissionCalculatorTest extends TestCase
{
    public function testCalculate()
    {
        $rates = [
            'USD' => 1.083655,
            'JPY' => 169.612578,
            'EUR' => 1.0
        ];

        $calculator = new CommissionCalculator($rates);

        $fileCSV = file_get_contents('public/input.csv', true);

        $csv = new \ParseCsv\Csv();
        $csv->encoding('UTF-8', 'UTF-8');
        $csv->delimiter = ",";
        $csv->heading = false;
        $csv->parseFile($fileCSV);

        $expectedOutput = [
            "0.60",
            "3.60",
            "0.00",
            "0.06",
            "1.50",
            "0",
            "0.54",
            "0.81",
            "1.11",
            "3.00",
            "0.00",
            "0.00",
            "50"
        ];

        $actualOutput = $calculator->calculate($csv->data);

        $this->assertEquals($expectedOutput, $actualOutput);
    }
}
