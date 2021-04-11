<?php

namespace App\Insurers\Absolute;

use App\Exceptions\InsurerApiException;
use App\LoggableSoapClient;
use SoapFault;
use SoapVar;
use SoapHeader;

/**
 * Class AbsoluteClient
 * @package App\Insurers\Absolute
 */
class AbsoluteClient
{
    public $soapClient;

    /**
     * AbsoluteClient constructor.
     * @throws InsurerApiException
     */
    public function __construct()
    {
        ini_set('default_socket_timeout', 300);

        try {
            $this->soapClient = new LoggableSoapClient(['absolute'], env('ABSOLUTE_API_WDSL'), [
                'keep_alive' => true,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ]),
                'login' => env('ABSOLUTE_LOGIN'),
                'password' => env('ABSOLUTE_PASS'),
                'trace' => true,
                'use' => SOAP_ENCODED,
                'soap_version' => SOAP_1_2,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'encoding' => 'UTF-8',
                'uri' => 'http://www.w3.org/2003/05/soap-envelope',
                'style' => SOAP_RPC,
            ]);
        } catch (SoapFault $e) {
            throw new InsurerApiException($e);
        }
    }

    /**
     * @return array
     */
    public function log()
    {
        return $this->soapClient ? $this->soapClient->logs : [];
    }

    /**
     * @param string $method
     * @param $body
     * @return mixed
     * @throws InsurerApiException
     */
    public function call(string $method, $body)
    {
        if (is_string($body)) {
            $body = new SoapVar(array($body), XSD_ANYXML);
        }

        try {
            $response = $this->soapClient->__soapCall($method, [$body]);
            return $response;
        } catch (\SoapFault $e) {
            dd($this->soapClient->__getLastRequestHeaders(), $this->soapClient->__getLastRequest(), $e->getMessage());
            \Log::error($e->getMessage(), [$e->getCode(), $e->getTrace()]);
        }
    }
}
