<?php

namespace App\Services;

/**
 * Class DataAV100Service
 * @package App\Services
 */
class ParserAPIService
{
    /**
     * @var string
     */
    protected $url = '';

    const GIBDDRequestTypes = [
        'history', //'dtp', 'wanted', 'restrict'
    ];

    protected $eaisto_api = '{
        "diagnose_cards": [
         {
          "number": "201610101449162544920",
          "startDate": "10.10.2016",
          "endDate": "10.10.2017",
          "vin": "WBANX31040C174862",
          "regNumber": "Н934ЕС178"
          }
         ]
        }';

    protected $gibdd_api = '{
      "gibdd_done": 1, 
      "gibdd_history_done": 1, 
      "gibdd_accidents_done": 1, 
      "gibdd_searches_done": 1, 
      "gibdd_restrictions_done": 1,
      "history": { 
        "engineVolume": "3990.0",
        "color": "КРАСНЫЙ", 
        "bodyNumber": "Х9Н77А3ВJС0000513",
        "year": "2012",
        "engineNumber": "НС511259ХА01",
        "vin": "Х9Н77А3ВJС0000513",
        "model": "АФ 77А3ВJ",
        "category": "С",
        "type": "Грузовые автомобили фургоны",
        "powerHp": "132",
        "powerKwt": "97.09",
        "vehiclePassport": {
          "number": "62НО796550",
          "issue": "ООО \"ФОТОН МОТОР\""
        },
        "ownershipPeriods": [
          {
            "personType": "Физическое лицо",
            "from": "2013-09-04T00:00:00.000+04:00",
            "to": "2014-07-03T00:00:00.000+04:00"
          },
          {
            "personType": "Физическое лицо",
            "from": "2014-07-03T00:00:00.000+04:00",
            "to": null
          }
        ]
      }
    }';

    protected $rsa_api = '{
         "rsa_done": 1,
         "policies": [
         {
          "companyName": "ИНГОССТРАХ",
          "policyNumber": "0384201305",
          "policySerial": "ЕЕЕ",
          "policyIsRestrict": "1",
          "policyUnqId": "249399845",
          "vin": "JSAJTD54V00267888",
          "regNumber": "Р089УТ98",
          "bodyNumber": "",
          "mark":"Suzuki",
          "model":"GRAND VITARA",
          "createDate":"2018-07-17",
          "startDate":"2018-07-19",
          "endDate":"2019-07-19",
          "isTransit": 0,
          "isTrailerAllowed": 0,
          "purpose": "Личная",
          "insurantName": "И***** ИВАН ИВАНОВИЧ",
          "insurantDob": "1975-11-25",
          "ownerName": "И***** ИВАН ИВАНОВИЧ",
          "ownerDob": "1975-11-25",
          "kbm": "0.75",
          "location": "Тверская обл, г Тверь",
          "cost": "6426.00"
          }
         ]
        }';


    /**
     * ParserAPIService constructor.
     * @param string $type
     */
    public function __construct(string $type)
    {
        $this->url = env('PARSER_API_URL') . $type . '/?key=' . env('PARSER_API_KEY');
    }

    /**
     * @param string $number
     * @return mixed
     */
    public function checkDCard(string $number)
    {
        $url = $this->url . '&regNumber=' . $number;
        $response = json_decode(file_get_contents($url));
//        $response = json_decode($this->eaisto_api);
        if ($response && count($response->diagnose_cards) > 0) {
            return collect($response->diagnose_cards)->last();
        }

        return null;
    }

    public function checkGIBDD(string $vin)
    {
        $requestTypes = implode(',', static::GIBDDRequestTypes);
        $url = $this->url . '&vin=' . $vin . '&types=' . $requestTypes;
        $response = json_decode(file_get_contents($url));
//        $response = json_decode($this->gibdd_api);
        if ($response->gibdd_done && $response->gibdd_history_done && $response->history) {
            return $response->history;
        }

        return null;
    }

    /**
     * @param string $number
     * @return mixed|null
     */
    public function checkRSA(string $number)
    {
        $url = $this->url.'&regNumber='.$number;
        $response = json_decode(file_get_contents($url));
//        $response = json_decode($this->rsa_api);
        if($response && $response->rsa_done && count($response->policies) > 0) {
            return collect($response->policies)->last();
        }

        return null;
    }
}
