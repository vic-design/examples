<?php

namespace App\Http\Controllers;


use App\Http\Requests\OsagoNewForm;
use App\Http\Requests\OsagoNewSaveForm;
use App\Exceptions\InsurerApiException;
use App\Insurers\EnergoGarant\EnergoGarant;
use App\Models\Company;
use App\Models\EgarantCompany;
use App\Models\EgarantLink;
use App\Models\Order;
use App\Models\OrderProcessing;
use App\OsagoFactory;
use App\Services\ErrorAliasingService;
use App\Services\ParserAPIService;
use App\Traits\EditGuard;
use App\Traits\Segmented;
use App\User;
use App\ViewModels\CompaniesListView;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;


class OsagoNewController extends Controller
{
    use EditGuard;
    use Segmented;

    public $errorAliaser;
    public $companiesList;
    public $order;

    public function __construct(ErrorAliasingService $errorAliaser, CompaniesListView $companiesList)
    {
        $this->middleware('auth');
        $this->middleware('auth.b2b')->only(['newForm', 'createB2b', 'createDraftB2b']);

        $this->errorAliaser = $errorAliaser;
        $this->companiesList = $companiesList;
    }

    /**
     * @return array|Factory|View
     */
    public function newForm()
    {
        $egarantCompany = $this->user()->userEgarantCompany();

        return view('osago-form', [
            'params' => [
                'uuid' => null,
                'config' => OsagoNewForm::defaultConfig(),
                'site' => $this->user()->site,
                'orderData' => null,
                'companiesList' => null,
                'isEgarant' => $this->user()->isEgarantUser(),
                'egarantLink' => null,
                'egarantCompanyImg' => env('APP_URL') . '/' . ($egarantCompany && $egarantCompany->image ? $egarantCompany->image : 'img/egarant.png')
            ]
        ]);
    }

    /**
     * @param string $orderUuid
     * @return View
     */
    public function uploadForm(string $orderUuid)
    {
        $this->order = Order::whereUuid($orderUuid)->first() or abort(404);
        $this->canEditGuard($this->user(), $this->order());

        $egarantCompany = $this->order->user()->first()->userEgarantCompany();

        return view('osago-form', [
            'params' => [
                'uuid' => $this->order()->uuid,
                'config' => OsagoNewForm::defaultConfig(),
                'site' => $this->user()->site,
                'orderData' => $this->order()->formData(),
                'companiesList' => $this->companiesList->withLastResults($this->order()),
                'isEgarant' => $this->order()->is_egarant && ($this->user()->isEgarantUser() || $this->user()->isB2bAdmin()),
                'egarantLink' => $this->order()->getEgarantLink(),
                'egarantCompanyImg' => env('APP_URL') . '/' . ($egarantCompany && $egarantCompany->image ? $egarantCompany->image : 'img/egarant.png')
            ]
        ]);
    }

    /**
     * @param OsagoNewForm $request
     * @return array
     */
    public function createB2b(OsagoNewForm $request)
    {
        // TODO перенести в middleware
        Log::channel('form')->info(PHP_EOL . $request->getContent() . PHP_EOL . PHP_EOL);

        $form = $request->validatedObject();

        $order = new Order;
        $order->init(Order::PRODUCT_OSAGO_NEW, $form, Order::STATUS_DRAFT_ACCEPTED);

        if (!$order->save()) {
            abort(500, 'Can\'t save Order!');
        }

        return [
            'uuid' => $order->uuid,
            'companiesList' => $this->companiesList->format($order),
        ];
    }

    /**
     * @param OsagoNewSaveForm $request
     * @return array
     */
    public function createDraftB2b(OsagoNewSaveForm $request)
    {
        $form = $request->validatedObject();

        $order = new Order;
        $order->init(Order::PRODUCT_OSAGO_NEW, $form, Order::STATUS_DRAFT);

        if (!$order->save()) {
            abort(500, 'Can\'t save Order!');
        }

        return [
            'uuid' => $order->uuid,
            'status' => 'ok',
        ];
    }

    /**
     * @param string $orderUuid
     * @param OsagoNewSaveForm $request
     * @return array
     */
    function updateDraft(string $orderUuid, OsagoNewSaveForm $request)
    {
        $this->order = Order::whereUuid($orderUuid)->first() or abort(404);

        $this->canEditGuard($this->user(), $this->order());
        $this->canBeUpdatedGuard();

        $formJson = $request->validatedJson();
        $this->order->form_data = $formJson;

        $this->order->status = Order::STATUS_DRAFT;

        if (!$this->order->save()) {
            abort(500, 'Can\'t save Order!');
        }

        return ['status' => 'ok'];
    }

    /**
     * @param string $orderUuid
     * @param OsagoNewForm $request
     * @return array
     */
    function update(string $orderUuid, OsagoNewForm $request)
    {
        // TODO перенести в middleware
        Log::channel('form')->info(PHP_EOL . $request->getContent() . PHP_EOL . PHP_EOL);

        $this->order = Order::whereUuid($orderUuid)->first() or abort(404);

        $this->canEditGuard($this->user(), $this->order());
        $this->canBeUpdatedGuard();

        $formJson = $request->validatedJson();
        $this->order->form_data = $formJson;

        $this->order->status = Order::STATUS_DRAFT_ACCEPTED;

        if (!$this->order->save()) {
            abort(500, 'Can\'t save Order!');
        }

        return [
            'uuid' => $this->order->uuid,
            'companiesList' => $this->companiesList->format($this->order()),
        ];
    }

    /**
     * @param string $orderUuid
     * @param string $alias
     * @return array
     * @throws Exception
     */
    function process(string $orderUuid, string $alias)
    {
        if (!in_array($alias, OsagoFactory::usedAliases())) abort(404);

        $order = Order::whereUuid($orderUuid)
            ->whereIn('status', [Order::STATUS_DRAFT_ACCEPTED, Order::STATUS_APPROVED_BY_INSURER])
            ->first();

        $company = Company::whereAlias($alias)->first();

        $calculator = OsagoFactory::createCalculator($alias);

        if ($order === null || $company === null || $calculator === null) abort(404);

        // TODO тут лучше сначала искать OrderProcessing в статусе "обрабатывается",
        // чтоб не начинать повторно обрабатывать

        $op = new OrderProcessing();
        $op->init($order, $company);

        try {
            $this->applySegmentation($op->order()->formData(), Auth::user(), $company->id);
            $calculator->calculate($op);
            $this->applySegmentationToKbm(floatval($op->kbm), Auth::user(), $company->id);

        } catch (InsurerApiException $e) {
            $op->saveError($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $this->errorAliaser->getAlias($op),
                'isEgarant' => OsagoFactory::canUseEgarant($alias)
            ], 500);
        } catch (Exception $e) {
            $op->saveError($e->getMessage());
            abort(500, $this->errorAliaser->getAlias($op));
        }

        if ($op->premium && $op->order()->status !== Order::STATUS_SUCCESS) {
            $op->order()->update(['status' => Order::STATUS_APPROVED_BY_INSURER]);
        }

        return [
            'company' => $this->companiesList->company($op),
            'orderId' => $op->id,
        ];
    }


    /**
     * @return User|null
     */
    protected function user(): ?User
    {
        return Auth::user();
    }

    /**
     * @return Order|null
     */
    protected function order(): ?Order
    {
        return $this->order;
    }

    protected function canBeUpdatedGuard()
    {
        if ($this->order->status !== Order::STATUS_SUCCESS) return;

        abort(500, 'Невозможно обновить данные для выпущенного полиса');
    }

    /**
     * @param string $vin
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function checkTS(string $vin)
    {
        $response = (new ParserAPIService(env('PARSER_API_GIBDD')))->checkGIBDD($vin);
        return $response ? response()->json($response) : null;
    }

    /**
     * @param string $number
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function checkInsurance(string $number)
    {
        $response = (new ParserAPIService(env('PARSER_API_RSA')))->checkRSA($number);
        return $response ? response()->json($response) : null;
    }

    /**
     * @param string $number
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function checkDCard(string $number)
    {
        $response = (new ParserAPIService(env('PARSER_API_EAISTO')))->checkDCard($number);
        return $response ? response()->json($response) : null;
    }
}
