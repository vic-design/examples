<?php

namespace App\Http\Controllers;


use App\Events\B2cOrderCreated;
use App\Events\B2cUserNeedHelp;
use App\Helper;
use App\Http\Requests\OsagoNewForm;
use App\Http\Requests\OsagoCalcForm;
use App\Insurers\EnergoGarant\EnergoGarant;
use App\Models\Company;
use App\Models\ContactVerification;
use App\Models\DefaultTS;
use App\Models\EgarantCompany;
use App\Models\EgarantLink;
use App\Models\Order;
use App\Models\OrderProcessing;
use App\OsagoFactory;
use App\Services\ErrorAliasingService;
use App\Traits\EditGuard;
use App\User;
use App\ViewModels\CompaniesListView;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Sentry\Laravel\LogChannel;


class OsagoCalcController extends Controller
{
    use EditGuard;

    protected $formView = 'osago-calc-form-steps';

    public $errorAliaser;
    public $companiesList;
    public $order;

    public function __construct(ErrorAliasingService $errorAliaser, CompaniesListView $companiesList)
    {
        $this->errorAliaser = $errorAliaser;
        $this->companiesList = $companiesList;
    }

    /**
     * @param string|null $mark
     * @param string|null $model
     * @return array|Factory|View
     */
    public function newForm(string $mark = null, string $model = null)
    {
        $config = OsagoNewForm::defaultConfig();

        if ($mark) {
            // haxx
            $mark = Helper::LadaHackDecode($mark);
            if ($model) {
                $car = DefaultTS::getModel($mark, $model) or abort(404);
                $config['car'] = [
                    'mark' => $car->mark,
                    'model' => $car->model,
                    'allModels' => DefaultTS::modelsList($mark)
                ];
            } else {
                $mark = DefaultTS::getMark($mark) or abort(404);
                $config['car'] = [
                    'mark' => $mark,
                    'model' => null,
                    'allModels' => DefaultTS::modelsList($mark)
                ];
            }
        }

        return view($this->formView, [
            'params' => [
                'uuid' => null,
                'config' => $config,
                'orderData' => null,
                'companiesList' => null,
            ]
        ]);
    }

    /**
     * @param string $orderUuid
     * @return array|Factory|View
     */
    public function uploadForm(string $orderUuid)
    {
        $this->order = Order::whereUuid($orderUuid)->first() or abort(404);

        // TODO ref
        if ($this->user()) {
            $this->canEditGuard($this->user(), $this->order());
        }

        return view($this->formView, [
            'params' => [
                'uuid' => $this->order()->uuid,
                'config' => OsagoNewForm::defaultConfig(),
                'site' => $this->order()->site,
                'orderData' => $this->order()->formData(),
                'companiesList' => $this->user()->isBackpackAdmin()
                    ? $this->companiesList->withLastResults($this->order())
                    : [],
            ]
        ]);
    }

    /**
     * @param OsagoCalcForm $request
     * @return array
     */
    public function createB2c(OsagoCalcForm $request)
    {
        // TODO перенести в middleware
        Log::channel('form')->info(PHP_EOL . $request->getContent() . PHP_EOL . PHP_EOL);

        $form = $request->validatedObject();

        $order = new Order;
        $order->init(Order::PRODUCT_OSAGO_NEW, $form, Order::STATUS_CALC);

        if (!$order->save()) {
            abort(500, 'Can\'t save Order!');
        }

        event(new B2cOrderCreated($order));

        $precalcInsurers = explode(',', env('USED_PRECALC_INSURERS'));
        shuffle($precalcInsurers);
        $precalcInsurerAlias = $precalcInsurers[0];

        return [
            'uuid' => $order->uuid,
            'precalc_alias' => $precalcInsurerAlias,
            'companiesList' => $this->companiesList->format($order),
        ];
    }

    /**
     * @param OsagoCalcForm $request
     * @return array
     */
    public function createDraft(OsagoCalcForm $request)
    {
        $form = $request->validatedObject();

        $data = $request->get('user');
        $user = User::where('phone', $data['phone'])->first();
        if(!$user) {
            User::register('', $data['phone'], '');
            ContactVerification::create([
                'contact' => '',
                'code' => rand(10000, 99999),
                'service_response' => '',
                'status' => ContactVerification::STATUS_PENDING,
                'attempts' => 0
            ]);
        }

        $order = new Order;
        $order->init(Order::PRODUCT_OSAGO_NEW, $form, Order::STATUS_CALC);

        if (!$order->save()) {
            abort(500, 'Can\'t save Order!');
        }

        event(new B2cOrderCreated($order));

        return [
            'uuid' => $order->uuid,
            'userChecked' => Auth::check(),
            'status' => 'ok',
        ];
    }

    /**
     * @param string $orderUuid
     * @param OsagoCalcForm $request
     * @return array
     */
    public function update(string $orderUuid, OsagoCalcForm $request)
    {
        // TODO перенести в middleware
        Log::channel('form')->info(PHP_EOL . $request->getContent() . PHP_EOL . PHP_EOL);

        $this->order = Order::whereUuid($orderUuid)->first() or abort(404);

        // TODO ref
        if ($this->user()) {
            $this->canEditGuard($this->user(), $this->order());
        }

        $formJson = $request->validatedJson();
        $this->order->form_data = $formJson;
        $this->order->status = Order::STATUS_CALC;

        if (!$this->order->save()) {
            abort(500, 'Can\'t save Order!');
        }

        $precalcInsurers = explode(',', env('USED_PRECALC_INSURERS'));
        shuffle($precalcInsurers);
        $precalcInsurerAlias = $precalcInsurers[0];

        return [
            'uuid' => $this->order->uuid,
            'precalc_alias' => $precalcInsurerAlias,
            'companiesList' => $this->companiesList->format($this->order()),
        ];
    }

    /**
     * @param string $orderUuid
     * @param string $alias
     * @return array
     */
    public function process(string $orderUuid, string $alias)
    {
        $order = Order::where([
            'uuid' => $orderUuid,
            'status' => Order::STATUS_CALC
        ])->first();

        $calc = OsagoFactory::createPreCalculator($alias);

        if ($order === null || $calc === null) abort(404);

        try {
            $coeffs = $calc->preCalculate($order);
        } catch (Exception $e) {
            dd($e, $calc->log());
            // abort(500, $e->getMessage());
        }

        // coeffs = {"Tb":4118,"Kt":2,"Kbm":0.5,"Kvs":0.96,"Ks":1,"Kp":1,"Km":1.4,"Kn":1,"Ko":1,"Kpr":1}
        return ['coeffs' => $coeffs];
    }

    /**
     * @param string $orderUuid
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     */
    public function checkUser(string $orderUuid)
    {
        $order = Order::whereUuid($orderUuid)->first() or abort(404);
        $user = Auth::user();

        // Админ не должен присваивать ордеры, у которых еще нет пользователя
        if ($order->user_id === null && $user && ($user->isB2bAdmin() || $user->isBackpackAdmin())) {
            abort(403);
        }

        if ($user === null) {
            $form = $order->formData();
            $userPhone = $form->user->phone;

            // Ищем юзера с указанным номером
            $user = User::wherePhone($userPhone)
                ->whereSite(User::B2C)
                ->first();
            // Создаем юзера, если такого нет
            if ($user === null) {
                User::register('', $userPhone, $order->findUserName());
            }

            // Редиректим пользователя на логин по смс, с дальнейшим редиректом сюда
            session(['login_redirect' => route('osago.calc.continue', ['order' => $order->uuid])]);
            return redirect(route('auth.create', ['phone' => $userPhone]));
        }

        if ($order->user_id === null) {
            $order->user_id = $user->id;
        }

        $order->save();

        // для формы предрассчета с шагами редирект на нее же, для обычной - на полную
        return redirect(route('osago.calc.form' /*'osago.new.form'*/, ['order' => $order->uuid]));
    }

    /**
     * @param Request $request
     * @return string
     */
    public function calcHelp(Request $request)
    {
        event(new B2cUserNeedHelp($request->post('Phone')));

        return 'Спасибо! С вами свяжутся в ближайшее время.';
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
     * Запрос на создание черновика в системе Е-Гарант.
     * Компании для расчета выбираются на основании компаний, доступных пользователю.
     * Если пользователю доступно больше одной компании, то расчеты идут последовательно.
     * @param string $orderUuid
     * @return string
     */
    public function orderEgarant(string $orderUuid): string
    {
        $order = Order::whereUuid($orderUuid)->first();
        if (!$order || !$order->user()->first()) {
            abort(404);
        }

        // у пользователя, создавшего заказ, всегда будет какая-то компания
        $egCompany = $order->user()->first()->userEgarantCompany();
        $alias = '';
        if (!$egCompany) {
            $emptyLink = EgarantLink::getEmptyLinkObject($alias);
        } else {
            $alias = $egCompany->alias;
            $emptyLink = EgarantLink::getEmptyLinkObject($alias);
            $emptyLink->image = env('APP_URL') . '/' . $egCompany->image;
        }

        $egCalcCompanies = [];
        foreach (EgarantCompany::all() as $item) {
            if ($item->alias !== $alias && OsagoFactory::canUseEgarant($alias)) {
                $egCalcCompanies[] = $item;
            }
        }

        foreach ($egCalcCompanies as $calc) {
            try {
                $client = OsagoFactory::createEgarantClient($calc->alias);
                if (!$client) {
                    $emptyLink->errors = 'Расчет не поддерживается.';
                    continue;
                }
                return json_encode($client->createEgarantProject($order, Company::whereAlias($calc->alias)->first()));
            } catch (Exception $ex) {
                $emptyLink->errors = $ex->getMessage();
            }
        }

//            TODO: Старая логика для расчета заявки на е-гарант с помощью уже совершенного расчета, может пригодиться
//            $op = $order
//                ->processings()
//                ->join('companies', 'order_processings.company_id', '=', 'companies.id')
//                ->select(['order_processings.*'])
//                ->where('companies.alias', '=', $alias)
//                ->latest('order_processings.updated_at')->limit(1)->first();

//            $op = new OrderProcessing();
//            $op->init($order, $company);
//
//            if (!$op) {
//                $emptyLink->errors = 'Невозможно получить ссылку на Е-Гарант';
//                return json_encode($emptyLink);
//            }

        return json_encode($emptyLink);
    }
}
