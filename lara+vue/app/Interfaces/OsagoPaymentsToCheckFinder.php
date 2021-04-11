<?php


namespace App\Interfaces;


use Illuminate\Support\Collection;

interface OsagoPaymentsToCheckFinder
{
    /**
     * @param int $minutesBack
     * @return Collection
     */
    public function findPaymentsToCheck(int $minutesBack) : Collection;

}
