<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Api\BigsmmService;
use App\Services\Api\SmmLabaService;
use App\Services\Api\TikTokMnogoService;
use App\Services\Api\WosService;
use BotMan\BotMan\BotMan;
use Carbon\Carbon;


class OrdersService
{
    public $ordersKeyboard;
    public $statuses;

    public function __construct()
    {
        $this->ordersKeyboard['active'] = (new KeyboardService)->getOrdersKeyboard('active');
        $this->ordersKeyboard['done']   = (new KeyboardService)->getOrdersKeyboard('done');

        $this->statuses = [
            'Pending'     => 0,
            'In progress' => 1,
            'Completed'   => 2,
            'Partial'     => 3,
            'Canceled'    => 4
        ];
    }


    /**
     * Вернет активные заказы
     * @param $user_id
     * @return string
     */
    public function getOrdersActive($user_id)
    {
        /** Повторная выборка, т.к статусы могли быть изменены */
        $orders = Order::query()
            ->where('user_id', '=', $user_id)
            ->where('status', '<', 2)
            ->orderBy('id')
            ->get();

        return $this->getPrettyList($orders);
    }


    public function getOrdersDone($user_id)
    {
        $orders = Order::query()
            ->where('user_id', '=', $user_id)
            ->where('status', '>', 1)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $orders = $orders->sortBy('id');

        return $this->getPrettyList($orders);
    }


    /**
     * Срабатывает при нажатии иконки под записью статиситки. Редактирует пост, обновляя информацию
     * @param BotMan $bot
     * @param $user_id
     * @param $status
     * @throws
     */
    public function getOrdersWithStatus($bot, $user_id, $status = 'done')
    {
        if($status == 'done') {
            $text = "👥 Your completed orders: \r\n \r\n" . $this->getOrdersDone($user_id);
        } else {
            $text = "👥 Your active orders: \r\n \r\n" . $this->getOrdersActive($user_id);
        }

        $parameters = array_merge([
            'chat_id' => $bot->getMessage()->getPayload()['chat']['id'],
            'message_id' => $bot->getMessage()->getPayload()['message_id'],
            'disable_web_page_preview' => 'true',
            'text' => $text,
        ], $this->ordersKeyboard[$status]);
        $bot->sendRequest('editMessageText', $parameters);
    }


    /**
     * Преобразует обьекты статистики в готовую строку
     * @param $orders
     * @return string
     */
    public function getPrettyList($orders)
    {
        if (count($orders) == 0) {
            return "There are no orders in this category.";
        }

        $statuses = [
            '0' => 'Pending 👌',
            '1' => 'In progress ▶️',
            '2' => 'Completed ⏹',
            '3' => 'Banned 🚫',
            '4' => 'Canceled/Refund ↩️'
        ];

        $ordersHandler = [];
        foreach ($orders as $order) {
            $order->created = Carbon::parse($order->created_at)->format('d M H:i');
            $ordersHandler[] = $order->created . " • " . $statuses[$order->status] . "\r\n" .
                    " • Ordered:  $order->count • \r\n" .
                    " • Price: $order->price $ • \r\n" .
                    " • " . $order->url . "\r\n" .
                    " • Service: " . $order->service_name;
        }

        $orders = implode("\r\n\r\n", $ordersHandler);

        return $orders;
    }




    public function updateOrderStatusesForCron()
    {
        $orders = Order::query()
            ->where('status', '<', 2)
            ->orderBy('id')
            ->get();

        /** @var array $handleOrders массив с двумя подмассивами, один содержит апи Bigsmm, другой Wos */
        $handleOrders = [];
        foreach ($orders as $order) {
            if(!$order->comment) continue;

            $apiData = json_decode($order->comment);
            $handleOrders[$apiData->service]['orders_list'][$apiData->order] = ['id' => $order->id];
            $handleOrders[$apiData->service]['orders_ids'][]  = $apiData->order;
        }

        /** Здесь идет перебор массива и делается запрос к апи */
        foreach ($handleOrders as $api => $data) {
            $ids = implode(",", $data['orders_ids']);

            if($api == 'bigsmm')      $apiService = new BigsmmService();
            if($api == 'wos')         $apiService = new WosService();
            if($api == 'tiktokmnogo') $apiService = new TikTokMnogoService();
            if($api == 'smmlaba')     $apiService = new SmmLabaService();

            $resp = $apiService->multiStatus($ids);

            if(!isset($resp)) continue;

            /** Обновляю статусы для заказов */
            foreach($resp as $orderId => $orderDetails) {
                if(!isset($orderDetails->status)) continue;
                Order::query()
                    ->where('id', $data['orders_list'][$orderId]['id'])
                    ->update(['status' => $this->statuses[$orderDetails->status]]);
            }
        }

        return true;
    }
}
