<?php

namespace App\Http\Controllers;

use App\Services\BotmanService;
use App\Services\KeyboardService;
use App\Services\OrdersService;
use App\Services\ReplyService;
use BotMan\BotMan\BotMan;
use Illuminate\Support\Facades\Log;

class BotManController extends Controller
{
    public $service;
    public $replyService;
    public $ordersService;

    public $keyboardService;
    public $mainKeyboard;
    public $cancelKeyboard;
    public $subscribeKeyboard;



    public function __construct()
    {
        $this->service         = new BotmanService();
        $this->keyboardService = new KeyboardService();
        $this->replyService    = new ReplyService();
        $this->ordersService   = new OrdersService();

        $this->subscribeKeyboard = $this->keyboardService->getSubscribeKeyboard();
        $this->mainKeyboard      = $this->keyboardService->getMainKeyboard();
        $this->cancelKeyboard    = $this->keyboardService->getCancelKeyboard();
    }


    /**
     * Place your BotMan logic here.
     */
    public function handle()
    {
        $botman = app('botman');

        /**
         * LOGGING
         */
        $botman->hears('', function ($bot) {
            $messageSource = json_decode($bot->getContent());
            if(isset($messageSource->message->text)) $this->service->logMessage($messageSource->message->text, $messageSource->message->chat->id);

            /** Перехват запроса на оплату инвойса и успешный ответ для юзера */
            if(isset($messageSource->pre_checkout_query->id)) $this->replyService->invoiceConfirm($bot, $messageSource->pre_checkout_query->id);

            /** Перехват коллбека об успешном платеже */
            if(isset($messageSource->message->successful_payment->total_amount)) $this->replyService->paymentConfirmation($bot, $messageSource->message);
        });


        /** Старт */
        $botman->hears('/start', function ($bot) {
            $this->replyService->handleStart($bot);
        });

        /** Старт по реферальной ссылке */
        $botman->hears('/start {ref_id}', function ($bot, $ref_id) {
            $this->replyService->handleStart($bot, $ref_id);
        });

        /** Нажатие кнопки проверки подписки при начале диалога */
        $botman->hears('subscribe_check', function ($bot) {
            //$this->replyService->handleCheckSubscribe($bot);
        });

        /** Возврат в главное меню и остановка диалога */
        $botman->hears('/menu', function ($bot) {
            $this->replyService->handleMenu($bot);
        })->stopsConversation();

        /** Запрос контактов поддержки */
        $botman->hears('🦸‍♂️ Support', function ($bot) {
            $this->replyService->handleSupport($bot);
        });

        /** Просмотр рефералов, статистики, получение ссылки */
        $botman->hears('👨🏻‍💻 Referrals', function ($bot) {
            $this->replyService->handleRefStat($bot);
        });



        /** Вывод категорий для заказов - Соц сети */
        $botman->hears('🛒 New order', function ($bot) {
            $this->replyService->handleOrderStepFirst($bot);
        });

            /** Вывод категорий для заказов - Услуги определенной соц сети */
            $botman->hears('create_order network {network}', function ($bot, $network) {
                $this->replyService->handleOrderStepSecond($bot, $network);
            });

                /** Возврат в предыдущее меню */
                $botman->hears('create_order__back_to_main_menu', function ($bot) {
                    $this->replyService->handleOrderBackToStepFirst($bot);
                 });

                /** Запуск диалога на создание заказа */
                $botman->hears('create_order {network} service {service}', function ($bot, $network, $service) {
                    $this->replyService->handleOrderStepThird($bot, $network, $service);
                });



        /** Просмотр баланса */
        $botman->hears('💵 Balance', function ($bot) {
            $this->replyService->handleBalance($bot);
        });

        /** Пополнить счет */
        $botman->hears('💳 Add balance', function ($bot) {
            $this->replyService->handlePayment($bot);
        });

        /** Возврат в главное меню */
        $botman->hears('🔚 Main menu', function($bot) {
            $this->replyService->handleBackToMainMenu($bot);
        })->stopsConversation();

        /** Перезагрузка бота */
        $botman->hears('/restart', function($bot) {
            $this->replyService->handleRestart($bot);
        })->stopsConversation();

        /** Пополнение счета администратором */
        $botman->hears('/plus {chat_id} {amount}', function ($bot, $chat_id, $amount) {
            $this->replyService->handleAddFunds($bot, $chat_id, $amount);
        });

        /** Рассылка пользователям */
        $botman->hears('/repost', function ($bot) {
            $this->service->sendMessagesToAllUsers($bot);
        });


        /**
         * Статистика заказов
         */
        $botman->hears('⚙ Orders', function ($bot) {
            $this->replyService->handleOrderStat($bot);
        });
            /** Кнопка статистики - активные заказы */
            $botman->hears('orders_active', function ($bot) {
                $this->replyService->handleOrdersWithStatus($bot, 'active');
            });
            /** Кнопка статистики - выполенные заказы */
            $botman->hears('orders_done', function ($bot) {
                $this->replyService->handleOrdersWithStatus($bot, 'done');
            });

        $botman->listen();
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
