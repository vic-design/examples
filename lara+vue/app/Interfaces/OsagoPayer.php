<?php


namespace App\Interfaces;


use App\Models\OrderProcessing;

interface OsagoPayer
{
    /**
     * @param OrderProcessing $op
     * @return string
     */
    public function payment(OrderProcessing $op) : string;

    /**
     * Проверить, можем ли мы оплатить, если есть какие-то ошибки, то возникнет ошибка
     * @param OrderProcessing $op
     * @return mixed
     */
    public function validate(OrderProcessing $op);
}
