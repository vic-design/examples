<?php

namespace App\Insurers\Absolute;


use App\Exceptions\InsurerApiException;
use App\Interfaces\OsagoCalculator;
use App\Interfaces\OsagoPayer;
use App\Interfaces\OsagoPaymentInfoResolver;
use App\Interfaces\OsagoPaymentsToCheckFinder;
use App\Interfaces\OsagoPolicyLoader;
use App\Interfaces\OsagoTsDictLoader;
use App\Models\Company;
use App\Models\OrderProcessing;
use App\Models\TsDict;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class Absolute
 * @package App\Insurers\Absolute
 */
class Absolute implements
    OsagoCalculator,
    OsagoPayer,
    OsagoPaymentInfoResolver,
    OsagoPolicyLoader,
    OsagoPaymentsToCheckFinder,
    OsagoTsDictLoader
{

    public const ALIAS = 'absolute';

    /** @var Company|null */
    public $company;
    public $client;
    /** @var  AbsoluteFormatter */
    public $formatter;

    /**
     * Absolute constructor.
     */
    public function __construct()
    {
        $this->company = Company::whereAlias(self::ALIAS)->first();
    }

    /**
     * @return array
     */
    public function log() : array
    {
        return $this->client()->log();
    }

    /**
     * login and get Sid for next requests
     *
     * @param OrderProcessing $op
     */
    private function login(OrderProcessing $op = null)
    {
        $dataSID = $op ? json_decode($op->data) : null;
        $SID = $dataSID && $dataSID->SID ? $dataSID->SID : null;

        if ($SID) {
            $sessionRequest = $this->formatter->formatSessionCheck($SID);
            $response = $this->client()->call('ExecProc', $sessionRequest);

            if ($xml = $this->getAPIResult($response)) {
                if ($xml->result && $xml->result->ScvMode) {
                    $this->formatter->SID = $SID;
                    return;
                }
            }
        }

        $loginRequest = $this->formatter->formatAuth();
        $response = $this->client()->call('ExecProc', $loginRequest);

        if ($xml = $this->getAPIResult($response)) {
            if ($xml->result && $xml->result->Sid) {
                $this->formatter->SID = (string)$xml->result->Sid;
                if($op) {
                    $op->update([
                        'data' => json_encode(['SID' => $this->formatter->SID])
                    ]);
                }
            }
        }
    }

    /**
     * @param OrderProcessing $op
     * @throws InsurerApiException
     */
    public function calculate(OrderProcessing $op)
    {
        $params = [];
        $this->client = new AbsoluteClient();
        $this->formatter = new AbsoluteFormatter($op, $params);

        $formData = $op->order()->formData();

        $this->login($op);

        //USER_CALCEOSAGO – расчет стоимости договора
        $request = $this->formatter->formatCalculate($formData->insurer, $formData->drivers);
        $response = $this->client()->call('ExecProc', $request);
        if ($xml = $this->getAPIResult($response)) {
            if ($xml->error) {
//                dd('USER_CALCEOSAGO', $xml);
                throw new InsurerApiException((string)$xml->error->text);
            }

            if ($xml->result && $xml->result->CALCISN) {
                $calcIsn = (string)$xml->result->CALCISN;

                //GetAgreementCalc - Получение данных по экспресс- котировке
                $request = $this->formatter->formatAgreement($calcIsn);
                $response = $this->client()->call('ExecProc', $request);
                if ($xml = $this->getAPIResult($response)) {
                    if ($xml->error) {
                        //dd('GetAgreementCalc', $xml);
                        throw new InsurerApiException((string)$xml->error->text);
                    }

                    if ($xml->result && $xml->result->AgreementCalc && $xml->result->AgreementCalc->row) {
                        $agreement = $xml->result->AgreementCalc->row;
                        $agrCond = $agreement->AGROBJECT->row->AGRCOND->row;
                        $op->update([
                            'contract_id' => $calcIsn,
                            'premium' => (float)str_replace(',', '.', $agrCond->PremiumSum),
                            'status' => OrderProcessing::STATUS_PROCESSING_COMPLETED,
                            'start_date' => $this->formatter->dateStart()->format("Y-m-d"),
                            'end_date' => $this->formatter->datePeriodEnd()->format("Y-m-d"),
                            'period' => $this->formatter->term,
                            'base' => (float)str_replace(',', '.', $agrCond->AGRCONDOSAGO->TB),
                            'kbm' => (float)str_replace(',', '.', $agrCond->AGRCONDOSAGO->KBM),
                        ]);

                    }
                }
            }
        }

    }

    /**
     * @param OrderProcessing $op
     * @return string
     * @throws InsurerApiException
     */
    public function payment(OrderProcessing $op) : string
    {
        $this->client = new AbsoluteClient();
        $this->formatter = new AbsoluteFormatter($op);

        $this->login();

        //USER_AT_SUBJSEARCHORCREATION – поиск или создание контрагента
        $insurer = $this->formatter->getPersonByType('insurer');
        $request = $this->formatter->formatUserAtSubjectSearchOrCreate($insurer);
        $response = $this->client()->call('ExecProc', $request);
        if ($xml = $this->getAPIResult($response)) {
            if ($xml->error) {
//                dd('USER_AT_SUBJSEARCHORCREATION insurer', $xml);
                throw new InsurerApiException((string)$xml->error->text);
            }

            if ($xml->result) {
                $insurer->ISN = (int)$xml->result->ISN;

                $ownerIsInsurer = false;
                $owner = $this->formatter->getPersonByType('owner');
                if (in_array('insurer', $owner->roles)) {
                    $ownerIsInsurer = true;
                } else {
                    $request = $this->formatter->formatUserAtSubjectSearchOrCreate($owner);
                    $response = $this->client()->call('ExecProc', $request);
                    $xml = $this->getAPIResult($response);
                }
                if (isset($xml) || $ownerIsInsurer) {
                    if (isset($xml) && $xml->error) {
//                        dd('USER_AT_SUBJSEARCHORCREATION owner', $xml);
                        throw new InsurerApiException((string)$xml->error->text);
                    }

                    if ((isset($xml) && $xml->result) || $ownerIsInsurer) {
                        $insurer->OWNERISN = $ownerIsInsurer ? $insurer->ISN : (int)$xml->result->ISN;

                        $drivers = $this->formatter->getPersonByType('driver');
                        foreach ($drivers as $key => $driver) {
                            $driverIsInsurer = false;
                            $driverIsOwner = false;
                            if (in_array('insurer', $driver->roles)) {
                                $driverIsInsurer = true;
                            } elseif (in_array('owner', $driver->roles)) {
                                $driverIsOwner = true;
                            } else {
                                $request = $this->formatter->formatUserAtSubjectSearchOrCreate($driver);
                                $response = $this->client()->call('ExecProc', $request);
                                $xml = $this->getAPIResult($response);
                            }
                            if (isset($xml) || $driverIsInsurer || $driverIsOwner) {
                                if (isset($xml) && $xml->error) {
//                                    dd('USER_AT_SUBJSEARCHORCREATION driver', $xml, $driver);
                                    throw new InsurerApiException((string)$xml->error->text);
                                }

                                if ((isset($xml) && $xml->result && !isset($driver->ISN))
                                    || $driverIsInsurer || $driverIsOwner
                                ) {
                                    $drivers[$key]->ISN = $driverIsInsurer ? $insurer->ISN : $driverIsOwner
                                        ? $insurer->OWNERISN : (int)$xml->result->ISN;
                                }
                            }
                        }

                        //USER_CALCEOSAGO – расчет стоимости договора
                        $request = $this->formatter->formatCalculate($insurer, $drivers, $op->contract_id);
                        $response = $this->client()->call('ExecProc', $request);
                        if ($xml = $this->getAPIResult($response)) {
                            if ($xml->error) {
//                                dd('USER_CALCEOSAGO', $xml);
                                throw new InsurerApiException((string)$xml->error->text);
                            }

                            if ($xml->result && $xml->result->CALCISN) {
                                $calcIsn = (string)$xml->result->CALCISN;

                                //GetAgreementCalc - Получение данных по экспресс- котировке
                                $request = $this->formatter->formatAgreement($calcIsn);
                                $response = $this->client()->call('ExecProc', $request);
                                if ($xml = $this->getAPIResult($response)) {
                                    if ($xml->error) {
//                                        dd('GetAgreementCalc', $xml);
                                        throw new InsurerApiException((string)$xml->error->text);
                                    }

                                    if ($xml->result && $xml->result->AgreementCalc && $xml->result->AgreementCalc->row) {
                                        $agreement = $xml->result->AgreementCalc->row;
                                        $drivers = array_values(collect(((array)$agreement->AGRROLE)['row'])
                                            ->where('ClassISN', '=', AbsoluteDict::PERSON_ROLE_DRIVER)->toArray());

                                        //get PTS type ISN
                                        $saveParams['PtsClassISN'] = AbsoluteDict::DOC_CAR_TYPES[$op->order()->formData()->transport->carDoc->type];
                                        //get TO type ISN
                                        $saveParams['OsmotrClassISN'] = AbsoluteDict::DOC_TO_TYPES[$op->order()->formData()->transport->toDoc->type];
                                        //get UseType type ISN
                                        //$saveParams['CarUseISN'] = AbsoluteDict::USE_GOALS[0]['ISN'];
                                        //$saveParams['CarUseName'] = AbsoluteDict::USE_GOALS[0]['SHORTNAME'];

                                        //SAVEAGREEMENTCALC – внесение информации в котировку
                                        $request = $this->formatter->formatSaveAgreement($agreement, $saveParams);
                                        $response = $this->client()->call('ExecProc', $request);
                                        if ($xml = $this->getAPIResult($response)) {
                                            if ($xml->error) {
//                                                dd('SAVEAGREEMENTCALC', $xml);
                                                throw new InsurerApiException((string)$xml->error->text);
                                            }

                                            if ($xml->result && $xml->result->AGREEMENTCALCISN) {

                                                //USER_AT_Create_EOSAGO – сохранение проекта полиса в РСА и создание договора
                                                $request = $this->formatter->formatAgreementCreation((string)$xml->result->AGREEMENTCALCISN);
                                                $response = $this->client()->call('ExecProc', $request);
                                                if ($xml = $this->getAPIResult($response)) {
                                                    if ($xml->error) {
//                                                        dd('USER_AT_Create_EOSAGO', $xml);
                                                        throw new InsurerApiException((string)$xml->error->text);
                                                    }

                                                    if ($xml->result) {
                                                        $agrCond = $agreement->AGROBJECT->row->AGRCOND->row;
                                                        $op->update([
                                                            'contract_id' => (string)$xml->result->AGRISN,
                                                            'premium' => (float)str_replace(',', '.', $xml->result->PREMIUMSUM),
                                                            'status' => OrderProcessing::STATUS_PROCESSING_COMPLETED,
                                                            'start_date' => $this->formatter->dateStart()->format("Y-m-d"),
                                                            'end_date' => $this->formatter->datePeriodEnd()->format("Y-m-d"),
                                                            'period' => $this->formatter->term,
                                                            'base' => (float)str_replace(',', '.', $agrCond->AGRCONDOSAGO->TB),
                                                            'kbm' => (float)str_replace(',', '.', $agrCond->AGRCONDOSAGO->KBM),
                                                            'check_insurer_id' => (string)$agreement->CLIENTISN,
                                                            'check_owner_id' => (string)$agreement->AGROBJECT->row->AGROBJCAR->OwnerISN,
                                                            'check_driver1_id' => isset($drivers[0]) ? (string)$drivers[0]->SUBJINFO->ISN : null,
                                                            'check_driver2_id' => isset($drivers[1]) ? (string)$drivers[1]->SUBJINFO->ISN : null,
                                                            'check_driver3_id' => isset($drivers[2]) ? (string)$drivers[2]->SUBJINFO->ISN : null,
                                                            'check_driver4_id' => isset($drivers[3]) ? (string)$drivers[3]->SUBJINFO->ISN : null,
                                                        ]);

                                                        //USER_GetLink4Payment – получение ссылки на оплату
                                                        $request = $this->formatter->formatGetLink4Payment($op->contract_id);
                                                        $response = $this->client()->call('ExecProc', $request);

                                                        if ($xml = $this->getAPIResult($response)) {
                                                            if ($xml->error) {
//                                                                dd('USER_GetLink4Payment', $xml);
                                                                throw new InsurerApiException((string)$xml->error->text);
                                                            }

                                                            if ($xml->result && !empty($xml->result->LinkForPayment)) {
                                                                $op->update([
                                                                    'status' => OrderProcessing::STATUS_WAITING_PAYMENT,
                                                                    'payment_url' => (string)$xml->result->LinkForPayment,
                                                                    'payment_id' => (string)$xml->result->OrderId,
                                                                ]);

                                                                return (string)$xml->result->LinkForPayment;
                                                            } else {
//                                                                dd('USER_GetLink4Payment', $xml);
                                                                throw new InsurerApiException((string)$xml->error->text);
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param $id
     * @param Request|null $request
     * @return OrderProcessing|null
     */
    public function resolvePaymentInfo($id, Request $request = null) : ?OrderProcessing
    {
        return OrderProcessing::where([
            'company_id' => $this->company->id,
            'contract_id' => $id,
        ])->latest()->first();
    }

    /**
     * @param OrderProcessing $op
     * @param Request|null $request
     * @throws InsurerApiException
     */
    public function loadPolicy(OrderProcessing $op, Request $request = null)
    {
        $policePath = storage_path('app/polices/' . $op->id . '.pdf');

        if (!file_exists($policePath)) {
            $this->client = new AbsoluteClient();
            $this->formatter = new AbsoluteFormatter($op);

            $this->login($op);

            //GetAgreement
            $request = $this->formatter->formatGetAgreement($op->contract_id);
            $response = $this->client()->call('ExecProc', $request);
            if ($xml = $this->getAPIResult($response)) {
                if ($xml->error) {
                    throw new InsurerApiException((string)$xml->error->text);
                }

                if ($xml->result) {
                    $order = collect($xml->result->Agreement->row->DOCS->row)->last();
                    $status = (int)$order->STATUSISN;
                    if ($status == AbsoluteDict::ORDER_COMPLETE) {

                        $refISN = (string)$order->ISN;

                        //GETPICTFILES
                        $request = $this->formatter->formatGetPictFiles($refISN);
                        $response = $this->client()->call('ExecProc', $request);

                        if ($xml = $this->getAPIResult($response)) {
                            if ($xml->error) {
                                throw new InsurerApiException((string)$xml->error->text);
                            }

                            if ($xml->result) {

                                $docISN = (int)collect($xml->result->ROWSET->row)->last()->ISN;

                                //GETATTACHMENTDATA
                                $request = $this->formatter->formatGetAttachmentData($docISN, $refISN);
                                $response = $this->client()->call('ExecProc', $request);

                                if ($xml = $this->getAPIResult($response)) {
                                    if ($xml->error) {
                                        throw new InsurerApiException((string)$xml->error->text);
                                    }

                                    if ($xml->result) {
                                        if (isset($xml->result->Header->resultInfo) && $xml->result->resultInfo->status === 'ERROR') {
                                            throw new InsurerApiException($xml->result->Header->resultInfo->errorInfo->descr);
                                        }

                                        file_put_contents($policePath, base64_decode($xml->result->FILEDATA));

                                        $op->setStatusSuccess();
                                    }
                                }
                            }
                        }
                    } elseif ($status == AbsoluteDict::ORDER_PROCESSING) {
                        $op->update([
                            'status' => OrderProcessing::STATUS_WAITING_PAYMENT_APPROVEMENT,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param int $minutesBack
     * @return Collection
     */
    public function findPaymentsToCheck(int $minutesBack) : Collection
    {
        return OrderProcessing::whereCompanyId($this->company->id)
            ->whereIn('status', [OrderProcessing::STATUS_WAITING_PAYMENT_APPROVEMENT, OrderProcessing::STATUS_WAITING_PAYMENT])
            ->where('updated_at', '>', Carbon::now('Europe/Moscow')->subMinutes($minutesBack)->toDateTimeString())
            ->get();
    }


    public function validate(OrderProcessing $op)
    {
        //TODO implemented
    }

    /**
     * @param array $options
     */
    public function loadTsDict(array $options)
    {
        $this->client = new AbsoluteClient();
        /** @var Company $company */
        $company = Company::alias(self::ALIAS)->first();
        $op = new OrderProcessing();
        $op->order_id = 1;
        $op->company_id = $company->id;
        $this->formatter = new AbsoluteFormatter($op, []);

        $this->login();

        $marks = [];
        $request = $this->formatter->formatGetDictList(AbsoluteDict::DICT_MARK_MODEL);
        $response = $this->client()->call('ExecProc', $request);
        if ($xml = $this->getAPIResult($response))
            if ($xml->result && $xml->result->ROWSET && isset(((array)$xml->result->ROWSET)['row']))
                $marks = ((array)$xml->result->ROWSET)['row'];

        foreach ($marks as $mark) {
            $dictRequest = $this->formatter->formatGetDictList((int)$mark->ISN);
            $response = $this->client()->call('ExecProc', $dictRequest);

            $models = [];
            if ($xml = $this->getAPIResult($response))
                if ($xml->result && $xml->result->ROWSET && isset(((array)$xml->result->ROWSET)['row']))
                    $models = ((array)$xml->result->ROWSET)['row'];

            foreach ($models as $model) {
                if (isset($mark->FULLNAME) && isset($model->FULLNAME) && isset($model->ISN))
                    echo TsDict::addModel(
                        self::ALIAS, $mark->FULLNAME ?? '', $model->FULLNAME ?? '', $model->ISN ?? '', $mark->ISN ?? null
                    );
            }
        }
    }

    /**
     * @return AbsoluteClient
     */
    public function client(): AbsoluteClient
    {
        return $this->client;
    }

    /**
     * @param $response
     * @return null|\SimpleXMLElement
     */
    private function getAPIResult($response)
    {
        if ($response && $response->ExecProcResult && $response->ExecProcResult->any) {
            return simplexml_load_string($response->ExecProcResult->any);
        }

        return null;
    }
}