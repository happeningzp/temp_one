<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\UserBot;
use BotMan\Drivers\Telegram\TelegramDriver;
use Illuminate\Support\Facades\Log;

class PaymentsService
{
    public $botmanService;

    public function __construct()
    {
        $this->botmanService = new BotmanService();
    }


    public function makeInvoice($amount)
    {
        $invoice = [
            'provider_token' => config('services.stripe.live'),

            'title'       => 'Add balance',
            'description' => 'Click the button and enter your payment details.',
            'payload'     => uniqid(),
            'currency'    => 'USD',
            'prices'      => json_encode([
                [
                    'label'  => 'Tips',
                    'amount' => $amount * 100,
                ],
            ]),
        ];

        return $invoice;
    }

    public function callbackFromInvoice($invoiceData)
    {
        $amount = round($invoiceData->successful_payment->total_amount/100, 2);

        $payment = Payment::firstOrCreate(
            ['payment_id' => $invoiceData->successful_payment->invoice_payload],
            ['user_id' => $invoiceData->chat->id, 'amount' => $amount, 'service' => 'stripe', 'data' => $invoiceData->successful_payment->currency]
        );
        Log::info('Payment add: ', ['payment' => $payment]);

        if (!$payment->wasRecentlyCreated) {
            /** платеж с таким айди уже существовал */
            Log::alert('Payment already isset: ', [$invoiceData]);
            throw new \Exception('Payment already isset');
        }

        try {
            $user = UserBot::query()->where('user_id', '=', $invoiceData->chat->id)->get()->first();
            Log::info('Sum added: ', ['user' => $user]);
        } catch (\Throwable $e) {
            Log::alert('User not found', [$invoiceData]);
            throw new \Exception('User not found');
        }

        $this->botmanService->correctUserBalance($user->id, $amount, true, 'Пополнение счета');

        return [
            'chat_id' => $invoiceData->chat->id,
            'amount' => $amount,
            'curr'   => $invoiceData->successful_payment->currency,
        ];
    }





    /**
     * Все что ниже - по старой платежной системе и этот код более не используется
     */



    /**
     * Отправка заявки на оплату
     * @param $amount
     * @param $user_id
     * @return string
     */
//    public function makeLink($amount, $user_id)
//    {
//        $url = 'https://payok.io/pay?';
//        $params = [
//            'amount'   => $amount,
//            'payment'  => $amount . rand(100, 999),
//            'shop'     => config('services.payok.shop_id'),
//            'currency' => 'RUB',
//            'desc'     => 'Пополнение счета',
//            'secret '  => config('services.payok.secret')
//        ];
//        //amount, payment, shop, currency, desc, и secret
//        $params['sign']    = md5(implode ( '|', $params));
//        $params['user_id'] = $user_id;
//
//        return $url . http_build_query($params);
//    }

    /**
     * $data['data'] - платежная система\и\или другие данные о платеже
     * $data['amount'] - сумма платежа в рублях
     * $data['user_id'] - айди юзера в телеге
     * $data['payment_id'] - айди платежа в платежной системе
     * @param $requestData
     * @throws \Exception
     */
    public function paymentCallback($requestData)
    {
        $data = [
            'secret'     => config('services.payok.secret'),
            'desc'       => $requestData['desc'],
            'currency'   => $requestData['currency'],
            'shop'       => $requestData['shop'],
            'payment_id' => $requestData['payment_id'],
            'amount'     => $requestData['amount']
        ];

        $sign = md5(implode('|', $data));

        if ($sign != $requestData['sign']) {
            Log::error('Sing error', ['my' => $sign, 'server' => $requestData['sign']]);
            die('Подпись не совпадает.');
        }

        $payment = Payment::firstOrCreate(
            ['payment_id' => $data['payment_id']],
            ['user_id' => $requestData['custom']['user_id'], 'amount' => $data['amount'], 'service' => 'payok', 'data' => '']
        );
        Log::info('Payment add: ', ['payment' => $payment]);

        if (!$payment->wasRecentlyCreated) {
            /** платеж с таким айди уже существовал */
            Log::alert('Payment already isset: ', $data);
            throw new \Exception('Payment already isset');
        }

        try {
            $user = UserBot::query()->where('user_id', '=', $requestData['custom']['user_id'])->get()->first();
            Log::info('Sum added: ', ['user' => $user]);
        } catch (\Throwable $e) {
            Log::alert('User not found', $data);
            throw new \Exception('User not found');
        }

        $this->botmanService->correctUserBalance($user->id, $data['amount'], true, 'Пополнение счета');

        $botman = app('botman');
        $botman->say('🤑 Ваш счет пополнен на '.$data['amount'].' руб.', $requestData['custom']['user_id'], TelegramDriver::class);

        $botman->say('Счет пользователя '.$requestData['custom']['user_id'].' пополнен на ' . $data['amount'] . '₽', config('botman.telegram.notificationTelegramUserId'), TelegramDriver::class);
    }
}
