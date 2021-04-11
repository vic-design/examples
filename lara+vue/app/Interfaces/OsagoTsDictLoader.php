<?php


namespace App\Interfaces;


use App\Exceptions\InsurerApiException;

interface OsagoTsDictLoader
{
    /**
     * @param array $options
     */
    public function loadTsDict(array $options);

}
