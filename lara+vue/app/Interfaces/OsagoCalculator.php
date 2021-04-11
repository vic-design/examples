<?php


namespace App\Interfaces;


use App\Exceptions\InsurerApiException;
use App\Models\OrderProcessing;

interface OsagoCalculator extends OsagoApiLogger
{
    /**
     * @param OrderProcessing $op
     * @throws InsurerApiException
     */
    public function calculate(OrderProcessing $op);

}
