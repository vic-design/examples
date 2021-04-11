<?php


namespace App\Interfaces;


use App\Models\OrderProcessing;
use Illuminate\Http\Request;

interface OsagoPaymentInfoResolver
{
    /**
     * @param $id
     * @param Request|null $request
     * @return OrderProcessing|null
     */
    public function resolvePaymentInfo($id, Request $request = null) : ?OrderProcessing;

}
