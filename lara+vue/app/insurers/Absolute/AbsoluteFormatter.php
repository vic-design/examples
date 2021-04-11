<?php

namespace App\Insurers\Absolute;


use App\IntegrationFormatter;
use App\Models\DefaultTS;
use App\Models\TsDict;
use Carbon\Carbon;
use SoapVar;

/**
 * Class AbsoluteFormatter
 * @package App\Insurers\Absolute
 */
class AbsoluteFormatter extends IntegrationFormatter
{
    public $SID;

    /**
     * @param $methodName
     * @param $params
     * @return object
     */
    private function formatGeneral($methodName, $params):object
    {
        $formatted = [
            'data' => [
                'request' => [
                    'RequestIp' => env('ABSOLUTE_REQUEST_IP'),
                    'AppId' => AbsoluteDict::APP_ID,
                    'RequestToken' => strtoupper(hash('sha512', AbsoluteDict::APP_ID . '' . $methodName . env('ABSOLUTE_KEY'))),  //'15F771AA95F47B3BE0C92F5E0A6C30AB83538DF69A946862BC2E847E1690ABCCEF6B230542D413933CECA67AAC7046705ACB5C75A1679DFF5BA2AA9E97568A06',
                    'reqName' => $methodName,
                    'params' => $params
                ]
            ]
        ];

        $xml = str_replace('</root>' . PHP_EOL, '', str_replace('<?xml version="1.0"?>' . PHP_EOL . '<root>', '', array_to_xml($formatted, '')));

        $formatted = new SoapVar('<ns1:pData><![CDATA[' . $xml . ']]></ns1:pData>', \XSD_ANYXML);

        return (object)['pData' => $formatted];
    }

    /**
     * @return object
     */
    public function formatAuth():object
    {
        return $this->formatGeneral('Auth', [
            'Name' => env('ABSOLUTE_API_LOGIN'),
            'Pwd' => hash('sha512', env('ABSOLUTE_API_PASSWORD')),
        ]);
    }

    /**
     * @param string $SID
     * @return object
     */
    public function formatSessionCheck(string $SID):object
    {
        return $this->formatGeneral('CheckSession', [
            'Sid' => $SID
        ]);
    }

    /**
     * @param $insurer
     * @param $drivers
     * @param string|null $calcISN
     * @return object
     */
    public function formatCalculate($insurer, $drivers, string $calcISN = null):object
    {
        $request = $this->formatInsurer($insurer);

        if ($this->order->drivers != 'unlimited')
            $request['DRIVERS']['row'] = $this->formatDrivers($drivers);

        if ($calcISN) {
//            $request['CALCISN'] = $calcISN;
        }

        return $this->formatGeneral('USER_CALCEOSAGO', $request);
    }

    /**
     * @param $ISN
     * @return object
     */
    public function formatAgreement($ISN):object
    {
        return $this->formatGeneral('GetAgreementCalc', [
            'AGREEMENTCALCISN' => $ISN,
            'Sid' => $this->SID
        ]);
    }

    /**
     * @param string $city
     * @return object
     */
    public function formatGetCity(string $city):object
    {
        return $this->formatGeneral('GetCitiList', (object)[
            'SearchMask' => $city,
            'Sid' => $this->SID
        ]);
    }

    /**
     * @param int $ISN
     * @return object
     */
    public function formatGetDictList(int $ISN):object
    {
        return $this->formatGeneral('GetDictiList', [
            'DictiISN' => $ISN,
            'Sid' => $this->SID
        ]);
    }

    /**
     * @param $drivers
     * @return object|string
     */
    private function formatDrivers($drivers)
    {
        return $drivers === 'unlimited' ? '' : $this->formatDriver($drivers);
    }

    /**
     * @param $data
     * @return array
     */
    private function formatDriver($data):array
    {
        $drivers = [];
        foreach ($data as $driver) {
            $driverFormatted = [
//                'ISN' => $driver->ISN, //Нет, Число, Уникальный идентификатор «Лица допущенного к управлению». Каждое новое ЛДУ указывается в окружении тэгов <row>….</row> Если указан ISN данные по ЛДУ не обязательны к заполнению.
                'FIO' => $driver->fio->surname . ' ' . $driver->fio->name . ' ' . $driver->fio->patronymic, //'Антонов Сергей Андреевич', //Нет (Да если MULTIDRIVE = N ), Строка, ФИО Лица допущенного к управлению.
                'DATBER' => Carbon::parse($driver->birthday)->format('d.m.Y'), //'12.01.1992', //Нет (Да если MULTIDRIVE = N ), Дата, Дата рождения
                'DRIVDATEBEG' => Carbon::parse($driver->license->date)->format('d.m.Y'), //'21.12.2010', //Нет (Да если MULTIDRIVE = N ), Дата, Дата начала водительского стажа
                'SEX' => $driver->gender == 'Мужской' ? 'M' : 'F', //'М', //Нет (Да если MULTIDRIVE = N ), Строка, Пол
                'DOCSERIADRAV' => explode(' ', $driver->license->seriesNumber)[0], //'77УЕ', //Нет (Да если MULTIDRIVE = N ), Строка, Серия водительского удостоверения
                'DOCNODRAV' => explode(' ', $driver->license->seriesNumber)[1], //'450795', //Нет (Да если MULTIDRIVE = N ), Строка, Номер водительского удостоверения
                'DODATEDRAV' => Carbon::parse($driver->license->date)->format('d.m.Y'), //'21.12.2010', //Нет (Да если MULTIDRIVE = N ), Дата, Дата выдачи водительского удостоверения
            ];

            if (isset($driver->ISN)) {
                $driverFormatted['ISN'] = $driver->ISN;
            }

            $drivers[] = $driverFormatted;
        }
        return $drivers;
    }

    /**
     * @param $insurer
     * @return array
     */
    private function formatInsurer($insurer):array
    {
//        $model = DefaultTS::getInsurerModel(
//            Absolute::ALIAS, $this->order->transport->mark, $this->order->transport->model
//        );
        $model = TsDict::findModel(Absolute::ALIAS, $this->order->transport->mark, $this->order->transport->model);
        //TODO: set params
        $response = [
//            'CALCISN' => 0, //Число. Уникальный идентификатор котировки, указывается если необходимо сделать повторный расчёт.
//            'DATEBEG' => '', //Дата. Дата начала действия договора, если не указано, то автоматически подставляется текущая дата.
//            'CLIENTISN' => $insurer->ISN ?? '', //Число. Уникальный идентификатор Страхователя полученный при вызове метода «USER_AT_SUBJSEARCHORCREATION»
            'INSFIO' => $insurer->fio->surname . ' ' . $insurer->fio->name . ' ' . $insurer->fio->patronymic, // 'Федоров Василий Иванович', //Строка. ФИО страхователя (указывается даже в случае если указан ISN)
            'INSDATBER' => Carbon::parse($insurer->birthday)->format('d.m.Y'), //'12.01.1992', //Датa. Дата рождения Страхователя (указывается даже в случае если указан ISN)
            'INSSEX' => $insurer->gender == 'Мужской' ? 'M' : 'F', //'М', //Строка. Пол страхователя. M/F ?? M/Ж
            'VIN' => $this->order->transport->vin, //'KMHWP81HP3U542517', //Строка. VIN-код транспортного средства. Значение в поле VIN должно быть длиной 17 символов, возможен ввод цифр и латиницы, кроме букв O, I, Q или значение: "ОТСУТСТВУЕТ""
            'MODELISN' => $model->model_id,
            'MULTIDRIVE' => $this->order->drivers === 'unlimited' ? 'Y' : 'N', //Строка. Без ограничения по кол-ву допущенных к управлению ЛИЦ(Y/N)
            'POWER' => $this->order->transport->power, //103, //Число. Мощность, л.с.
//            'OWNERISN' => $insurer->OWNERISN, //Число. Уникальный идентификатор Собственника ТС (если не указан автоматически указывается Страхователь)
            //TODO change KLADR BACK!
//            'CITY_KLADR_ID' => '7700000000000', //$insurer->address->cityKladr, //
            'CITY_KLADR_ID' => $insurer->address->cityKladr,
            'RELEASEDATE' => Carbon::parse($this->order->transport->carDoc->date)->format('d.m.Y'), //'10.06.2018', //Дата. Дата выпуска ТС
            'Sid' => $this->SID, //login SID
        ];

        if (isset($insurer->ISN)) {
            $response['CLIENTISN'] = $insurer->ISN;
        }

        if (isset($insurer->OWNERISN)) {
            $response['OWNERISN'] = $insurer->OWNERISN;
        }

        if (!$this->order->transport->noLicensePlate) {
            $response['REGNO'] = $this->order->transport->licensePlate;
        }

        return $response;
    }

    /**
     * @param $request
     * @param $params
     * @return object
     */
    public function formatSaveAgreement($request, $params):object
    {
        $request = json_decode(json_encode($request), true);

        $transport = $this->order->transport;

        $carDoc = $transport->carDoc;
        $fillingFields = $request['AGROBJECT']['row']['AGROBJCAR'];
        $fillingFields['PtsClassISN'] = $params['PtsClassISN'] ?? null;
        $fillingFields['PtsSer'] = explode(' ', $carDoc->seriesNumber)[0];
        $fillingFields['PtsNo'] = explode(' ', $carDoc->seriesNumber)[1];
        $fillingFields['PtsDate'] = Carbon::parse($carDoc->date)->format('d.m.Y');

        $yearIssue = Carbon::createFromFormat('Y', $this->order->transport->yearIssue);
        if ($yearIssue->addYears(3) < Carbon::now()) {
            $fillingFields['OsmotrClassISN'] = $params['OsmotrClassISN'] ?? null;
            $fillingFields['OsmotrTalonNo'] = $transport->toDoc->seriesNumber;
            $fillingFields['OsmotrNextDate'] = Carbon::parse($transport->toDoc->endDate)->format('d.m.Y');
        }

        $fillingFields['BODYID'] = $transport->bodyNumber;
//        $fillingFields['ENGINEID'] = $transport->bodyNumber;
        $fillingFields['CHASSISID'] = $transport->chassisNumber;

//        $request->AGROBJCAR->ColorISN = $params['ColorISN'] ?? null;
//        $fillingFields['CarUseISN'] = $params['CarUseISN'] ?? null;

//        $fillingFields['PeriodBeg'] = $this->dateStart()->format('d.m.Y');
//        $fillingFields['PeriodEnd'] = $this->dateEnd()->format('d.m.Y');

        $fillingFields['USETRAILER'] = 'N';
        $fillingFields['USESPECIALSIGNAL'] = 'N';

        $request['AGROBJECT']['row']['AGROBJCAR'] = $fillingFields;
        $request['Sid'] = $this->SID;

        return $this->formatGeneral('SAVEAGREEMENTCALC', $request);
    }

    /**
     * @param string $agreementISN
     * @return object
     */
    public function formatAgreementCreation(string $agreementISN):object
    {
        return $this->formatGeneral('USER_AT_Create_EOSAGO', [
            'AGREEMENTCALCISN' => $agreementISN,
            'Sid' => $this->SID
        ]);
    }

    /**
     * @param int $agreementISN
     * @return object
     */
    public function formatGetLink4Payment(int $agreementISN):object
    {
        return $this->formatGeneral('USER_GetLink4Payment', [
            'AGRISN' => $agreementISN,
            'Sid' => $this->SID
        ]);
    }

    /**
     * @param int $agreementISN
     * @return object
     */
    public function formatGetAgreement(int $agreementISN):object
    {
        return $this->formatGeneral('GetAgreement', [
            'AgrISN' => $agreementISN,
            'Sid' => $this->SID
        ]);
    }

    /**
     * @param int $refISN
     * @return object
     */
    public function formatGetPictFiles(int $refISN):object
    {
        return $this->formatGeneral('GETPICTFILES', [
            'PICTTYPE' => AbsoluteDict::DOC_PICT_TYPE,
            'REFISN' => $refISN,
            'FILESINDB' => 'N',
            'Sid' => $this->SID
        ]);
    }

    /**
     * @param int $ISN
     * @param int $refISN
     * @return object
     */
    public function formatGetAttachmentData(int $ISN, int $refISN):object
    {
        return $this->formatGeneral('GETATTACHMENTDATA', [
            'ISN' => $ISN,
            'REFISN' => $refISN,
            'PICTTYPE' => AbsoluteDict::DOC_PICT_TYPE,
            'Sid' => $this->SID
        ]);
    }

    public function formatUserAtSubjectSearchOrCreate($person):object
    {
        $passport = isset($person->passport) ? explode(' ', $person->passport->seriesNumber) : null;
        $license = isset($person->license) ? explode(' ', $person->license->seriesNumber) : null;

        return $this->formatGeneral('USER_AT_SUBJSEARCHORCREATION', [
            'FULLNAME' => $person->fio->surname . ' ' . $person->fio->name . ' ' . $person->fio->patronymic, //Матвее Павел. Да. Строка. ФИО контрагента
            'BIRTHDAY' => Carbon::parse($person->birthday)->format('d.m.Y'), //12.01.1992. Да. Дата. Дата рождения контрагента
            'DRIVINGDATEBEG' => $person->license ? Carbon::parse($person->license->date)->format('d.m.Y') : '', //01.01.2019 //Нет //Дата //Дата начала водительского стажа //* Обязательно к заполнению для «Лиц допущенных к управлению ТС»
            'DOCSER' => isset($passport[0]) ? $passport[0] : '', //4500 //Нет //Строка //Серия паспорта
            'DOCNO' => isset($passport[1]) ? $passport[1] : '', //000001 //Нет //Строка //Номер паспорта
            'DOCDATE' => $passport ? Carbon::parse($person->passport->date)->format('d.m.Y') : null, //01.01.2019 //Да //Дата //Дата выдачи паспорта
            'EMAIL' => $this->order->user->email, //Нет //Строка //Адрес электронной почты. //*Обязательно к заполнению для «Страхователя». Используется для автоматической отправки оформленного договора после подтверждения оплаты.
            'DRIVEDOCSER' => isset($license[0]) ? $license[0] : '', //7700 //Нет //Строка //Серия Водительского удостоверения
            'DRIVEDOCNO' => isset($license[1]) ? $license[1] : '', //000001 //Нет //Строка //Номер Водительского удостоверения
            'DRIVEDOCDATE' => $person->license ? Carbon::parse($person->license->date)->format('d.m.Y') : '', //21.10.2010 //Нет //Дата //Дата выдачи Водительского удостоверения
//            'DRIVEHANDBY' => '', //ГИБДД //Нет //Строка //Орган, выдавший Водительское удостоверение
            'NUMMOBTEL' => $this->order->user->phone, //79999999999 //Да //Число //Номер телефона контрагента
            'DADATA' => [
                'STREET_KLADR_ID' => $person->address->streetKladr ?? '', //7700000200000000096 //Нет //Число //Код КЛАДР адреса регистрации – можно получить при выполнении метода GetStreetList //*Обязательно к заполнению для «Собственника ТС» и «Страхователя»
                'POSTAL_CODE' => $person->address->postalCode ?? '', //140140 //Нет //Число //Почтовый индекс
                'HOUSE' => $person->address->house ?? '', //11 //Нет //Строка //Номер дома
//                'BUILDING' => $insurer->address->, //Нет //Строка //Строение
                'FLAT' => $person->address->flat ?? '', //4 //Нет //Строка //Квартира
            ],
            'Sid' => $this->SID
        ]);
    }

    public function getPersonByType($type)
    {
        if ($type == static::ROLE_INSURER) {
            $insurer = collect($this->getSubjectsWithRoles())->filter(function ($person) {
                return collect($person->roles)->contains(static::ROLE_INSURER);
            })->first();
            return $insurer;
        } elseif ($type == static::ROLE_DRIVER) {
            if ($this->order->drivers != 'unlimited') {
                $drivers = collect($this->getSubjectsWithRoles())->filter(function ($person) {
                    return collect($person->roles)->contains(static::ROLE_DRIVER);
                });
                return $drivers;
            }
        } elseif ($type == static::ROLE_OWNER) {
            $owner = collect($this->getSubjectsWithRoles())->filter(function ($person) {
                return collect($person->roles)->contains(static::ROLE_OWNER);
            })->first();
            return $owner;
        }

        return null;
    }
}