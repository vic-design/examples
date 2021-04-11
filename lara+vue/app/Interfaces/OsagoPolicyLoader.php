<?php


namespace App\Interfaces;


use App\Exceptions\InsurerApiException;
use App\Models\OrderProcessing;
use Illuminate\Http\Request;

interface OsagoPolicyLoader extends OsagoApiLogger
{
    /**
     * @param OrderProcessing $op
     * @param Request $request
     * @throws InsurerApiException
     */
    public function loadPolicy(OrderProcessing $op, Request $request = null);
}
