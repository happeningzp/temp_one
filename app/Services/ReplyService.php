<?php

namespace App\Services;

use App\Http\Conversations\CreateOrderConversation;
use App\Http\Conversations\PaymentConversation;
use BotMan\BotMan\BotMan;
use BotMan\Drivers\Telegram\TelegramDriver;
use Illuminate\Support\Facades\Log;

class ReplyService
{
    public $botName;
    public $service;
    public $keyboardService;
    public $ordersService;

    public $mainKeyboard;
    public $cancelKeyboard;
    public $ordersKeyboardDone;
    public $ordersKeyboardActive;
    public $subscribeKeyboard;

    public function __construct()
    {
        $this->botName = config('botman.telegram')['botname'];

        $this->service         = new BotmanService();
        $this->keyboardService = new KeyboardService();
        $this->ordersService   = new OrdersService();

        $this->subscribeKeyboard = $this->keyboardService->getSubscribeKeyboard();
        $this->mainKeyboard      = $this->keyboardService->getMainKeyboard();
        $this->cancelKeyboard    = $this->keyboardService->getCancelKeyboard();
        $this->ordersKeyboardActive = $this->keyboardService->getOrdersKeyboard('active');
        $this->ordersKeyboardDone   = $this->keyboardService->getOrdersKeyboard('done');
    }









    /**
     * Старт и Старт + рефка
     * @param  BotMan $bot
     * @param bool $ref_id
     */
    public function handleStart($bot, $ref_id = false)
    {
        $user = $this->service->getOrRegisterUser($bot->getUser(), $ref_id);
        //$isSubscribe = $this->service->checkSubscribe($bot->getUser()->getId());

        if ($user->wasRecentlyCreated) {
            $bot->reply(
                "👋 Welcome, i’m FLYSMM Bot with me you can improve your social media accounts. \r\n\r\n" .

                "It's very simple: \r\n" .
                "1️⃣ Get new subscripbers \r\n" .
                "2️⃣ Get likes, views, comments, etc\r\n" .

                "Add money to your balance and make first order. \r\n\r\n" .

                "All Social Medias.\r\n\r\n" .

                "Support: \r\n" .
                "@PeopleSupport",
                $this->mainKeyboard
            );

//            $bot->reply(
//                "Add money to your balance and make first order! :)",
//                $this->mainKeyboard
//            );

//            if($isSubscribe) {
//            } else {
//                if(!$isSubscribe) $this->sendSubscribeMessage($bot);
//            }
        } else {
            $bot->reply(
                "👋 Welcome, i’m FLYSMM Bot with me you can improve your social media accounts. \r\n\r\n" .

                "It's very simple: \r\n" .
                "1️⃣ Get new subscripbers \r\n" .
                "2️⃣ Get likes, views, comments, etc\r\n" .

                "Add money to your balance and make first order. \r\n\r\n" .

                "All Social Medias.\r\n\r\n" .

                "Support: \r\n" .
                "@PeopleSupport",
                $this->mainKeyboard
            );

            //if(!$isSubscribe) $this->sendSubscribeMessage($bot);
        }
    }





    /**
     * Отправить сообщение о необходимости подписки на канал
     * @param  BotMan $bot
     * @throws mixed
     */
    public function sendSubscribeMessage($bot)
    {
        $bot->reply(
            "Для дальнейшей работы с ботом - вам необходимо подписаться на канал",
            $this->subscribeKeyboard
        );
    }




    /**
     * Нажатие кнопки "Проверить подписку" при первом контакте
     * @param  BotMan $bot
     * @throws mixed
     * @return mixed
     */
    public function handleCheckSubscribe($bot)
    {
        $parameters = array_merge([
            'chat_id' => $bot->getMessage()->getPayload()['chat']['id'],
            'message_id' => $bot->getMessage()->getPayload()['message_id'],
            'text' => '👍🏼 Excellent! You can use the services of the bot! ⤵️',
        ]);
        $bot->sendRequest('editMessageText', $parameters);

        $bot->reply(
            '💸 Before ordering the service - add money to your account 💸',
            $this->mainKeyboard
        );
    }





    /**
     * Возврат в главное меню
     * @param  BotMan $bot
     */
    public function handleBackToMainMenu($bot)
    {
        $bot->reply(
            "• If bot does't respond - enter /restart" .
            "\r\n\r\n" .
            "👉 Select menu item:",
            $this->mainKeyboard);
    }





    /**
     * Перезагрузка бота (/restart)
     * @param  BotMan $bot
     */
    public function handleRestart($bot)
    {
        $bot->reply(
            "Bot restarted ✅",
            $this->mainKeyboard);
//        $isSubscribe = $this->service->checkSubscribe($bot->getUser()->getId());
//        if(!$isSubscribe) $this->sendSubscribeMessage($bot);
    }




    /**
     * Вывод главного меню
     * @param  BotMan $bot
     */
    public function handleMenu($bot)
    {
        $bot->reply("Main menu ✅", $this->mainKeyboard);
    }





    /**
     * Поддержка
     * @param  BotMan $bot
     */
    public function handleSupport($bot)
    {
        $bot->reply(
            "Bot🤖 works as automatically as possible, 24/7/365, so feel free to use it! \r\n\r\n" .
            "But if something goes wrong, please contact our technical support right away.  \r\n".
            "Technical support @PeopleSupport 👨💻 \r\n" .
            "9:00 AM to 11:00 PM",
            $this->mainKeyboard);
    }





    /**
     * Стата по рефералам
     * @param  BotMan $bot
     */
    public function handleRefStat($bot)
    {
        $referral_stat = $this->service->getReferralStat($bot->getUser()->getId());
        $bot->reply(
            "Attract new users and get 10% of their spending on services. \r\n" .
                        "FOREVER. \r\n\r\n" .
            "👨🏻‍💻 You have attracted " . $referral_stat['count'] . "  new users. \r\n\r\n" .
            "💲 Earnings from referrals: " . $referral_stat['amount'] . " $.\r\n\r\n" .
            "Your referral link 👇🏼");

        $bot->reply("https://t.me/". $this->botName ."?start=" . $bot->getUser()->getId(),
            $this->mainKeyboard);
    }





    /**
     * Пополнение баланса от админа - юзерам
     * @param  BotMan $bot
     * @param $user_id
     * @param $amount
     * @throws mixed
     */
    public function handleAddFunds($bot, $user_id, $amount)
    {
        $result = $this->service->addFundsByAdmin($bot, $bot->getUser(), $user_id, $amount);
        if(isset($result['error'])) {
            $bot->reply($result['error']);
        } else {
            $bot->say($amount . ' $ credited to your account.', $user_id);
            $bot->reply('Счет для пользователя ' . $user_id . ' на сумму ' . $amount . '$. был успешно пополнен.');
        }
    }

    /**
     * Отправит положительный ответ на запрос разрешения проведения платежа пользователем
     * @param $bot
     * @param $invoice_id
     */
    public function invoiceConfirm($bot, $invoice_id)
    {
        try {
            $bot->sendRequest('answerPreCheckoutQuery', [
                'pre_checkout_query_id' => $invoice_id,
                'ok' => 'true',
            ]);
        } catch (\Throwable $e) {
            Log::error('invoiceConfirm error', ['err' => $e->getMessage()]);
        }
    }

    /**
     * Коллбек успешного платежа
     * @param $bot
     * @param $invoiceData
     */
    public function paymentConfirmation($bot, $invoiceData)
    {
        try {
            $paymentService = (new PaymentsService())->callbackFromInvoice($invoiceData);
            $bot->reply($paymentService['amount'] . $paymentService['curr'] . ' credited to your account.',
                $this->mainKeyboard);

            $bot->say('Счет пользователя '.$paymentService['chat_id'].' пополнен на ' . $paymentService['amount'] . $paymentService['curr'], config('botman.telegram.notificationTelegramUserId'));
        } catch (\Throwable $e) {
            Log::error('ReplyService, paymentConfirmation', ['error' => $e->getMessage()]);
        }
    }





    /**
     * Вывод баланса
     * @param  BotMan $bot
     */
    public function handleBalance($bot)
    {
        $balance = $this->service->getUserBalance($bot->getUser()->getId());
        $bot->reply(
            'Your Telegram ID: ' . $bot->getUser()->getId() . "\r\n\r\n" .
            '💰 Your balance - ' . round($balance, 2) . ' USD',
            $this->mainKeyboard);
    }





    /**
     * Диалог пополнения счета
     * @param  BotMan $bot
     */
    public function handlePayment($bot)
    {
        $this->startConversation($bot, new PaymentConversation($bot->getUser()->getId(), $bot->getUser()), '💳 Add Balance');
    }




    /**
     * Вывод категорий для заказа (выбор соц сети)
     * @param  BotMan $bot
     */
    public function handleOrderStepFirst($bot)
    {
        $services = ParseService::getServices();
        $keyboard = KeyboardService::getInlineOrdersKeyboard($services);
        $bot->reply("Choose the social media 👇", $keyboard);
    }

    /**
     * Возврат в начальное меню раздела заказов (выбор соц.сети)
     * @param  BotMan $bot
     * @throws
     */
    public function handleOrderBackToStepFirst($bot)
    {
        $services = ParseService::getServices();
        $keyboard = KeyboardService::getInlineOrdersKeyboard($services);

        $parameters = array_merge([
            'chat_id' => $bot->getMessage()->getPayload()['chat']['id'],
            'message_id' => $bot->getMessage()->getPayload()['message_id'],
            'text' => "Choose the social media 👇",
        ], $keyboard);
        $bot->sendRequest('editMessageText', $parameters);
    }

    /**
     * Вывод категорий для заказа (выбор услуги)
     * @param  BotMan $bot
     * @param $network
     * @throws
     */
    public function handleOrderStepSecond($bot, $network)
    {
        $services = ParseService::getServices();

        if(isset($services[$network])) $services = $services[$network];
        else $bot->reply('An error occurred. Try again.', $this->mainKeyboard);

        $keyboard = KeyboardService::getInlineOrdersKeyboard($services, $network);

        $parameters = array_merge([
            'chat_id' => $bot->getMessage()->getPayload()['chat']['id'],
            'message_id' => $bot->getMessage()->getPayload()['message_id'],
            'text' => "Choose the service 👇",
        ], $keyboard);
        $bot->sendRequest('editMessageText', $parameters);
    }

    /**
     * Запуск диалога на создание заказа
     * @param  BotMan $bot
     * @param $network /соц сеть
     * @param $service /сервис соц сети
     * @throws
     */
    public function handleOrderStepThird($bot, $network, $service)
    {
        $services = ParseService::getServices();
        if(!isset($services[$network][$service])) $bot->reply('An error occurred. Try again.', $this->mainKeyboard);

        $user = $this->service->getOrRegisterUser($bot->getUser());
        $this->startConversation($bot, new CreateOrderConversation($user, $services[$network][$service]), 'Making order');
    }



    /**
     * Вывод созданных заказов
     * @param  BotMan $bot
     * @throws
     */
    public function handleOrderStat($bot)
    {
        $orders = $this->ordersService->getOrdersActive($bot->getUser()->getId());
        $bot->reply(
            "👥 Your active orders: \r\n \r\n" . $orders,
            $this->ordersKeyboardActive + ['disable_web_page_preview' => 'true']
        );
    }


    /**
     * Мои заказы: нажатия по кнопкам под постом
     * @param BotMan $bot
     * @param $status /active || done
     */
    public function handleOrdersWithStatus($bot, $status = 'active')
    {
        $this->ordersService->getOrdersWithStatus($bot, $bot->getUser()->getId(), $status);
    }





    /**
     * Загрузит необходимый диалог
     * @param  BotMan $bot
     * @param  $conversation
     * @param  $cancelButtonsText
     */
    public function startConversation(BotMan $bot, $conversation, $cancelButtonsText = false)
    {
        if ($cancelButtonsText) {
            $bot->reply($cancelButtonsText, $this->cancelKeyboard);
        }
        $bot->startConversation($conversation);
    }
}
