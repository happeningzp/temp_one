<?php

namespace App\Services;

use App\Exceptions\BotException;
use App\Models\Order;
use App\Services\Api\BigsmmService;
use App\Services\Api\SmmLabaService;
use App\Services\Api\TikTokMnogoService;
use App\Services\Api\WosService;
use BotMan\Drivers\Telegram\TelegramDriver;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public $service;

    public function __construct()
    {
        $this->service = new BotmanService();
    }

    /**
     * Вернет количество подписчиков\просмотров, на которых хватит баланса
     * @param $balance
     * @param $price
     * @return false|float
     */
    public static function getSubsCount($balance, $price)
    {
        if($price > 0) return floor($balance/$price);
        return $balance / 0.1;
    }


    /**
     * Проверить ссылку по маске
     * @param $url
     * @param $mask
     * @return bool
     */
    public static function checkUrl($url, $mask)
    {
        $pos = stripos($url, $mask);
        if ($pos === false) {
            return false;
        }

        return $url;
    }





    /**
     * Создание нового заказа
     * @param $userData
     * @param $url
     * @param $count
     * @param $serviceData
     * @throws BotException
     * @return int
     */
    public function createOrder($userData, $url, $count, $serviceData)
    {
        $botman = app('botman');
        $price = $serviceData['price'];

        if ($this->getSubsCount($userData->balance, $price) < $count) {
            throw new BotException("💸 Insufficient funds in the account. \r\n🔄 Add money to your account and try again.");
        }

        if ($count < $serviceData['min'] || $count > $serviceData['max']) {
            throw new BotException("Enter value from " . $serviceData['min'] . " to " . $serviceData['max']);
        }

        $order = new Order();
        $order->user_id  = $userData->user_id;
        $order->url      = $url;
        $order->price    = $count * $price;
        $order->count    = $count;
        $order->service_name = $serviceData['name'];

        $apiData = [
            'service'  => $serviceData['id'],
            'quantity' => $count,
            'link'     => $url,
            'name'     => 'user_id:' . $userData->user_id
        ];

        try {
            switch ($serviceData['api']) {
                case 'bigsmm':
                    $apiService = new BigsmmService();
                    break;
                case 'wos':
                    $apiService = new WosService();
                    break;
                case 'smmlaba':
                    $apiService = new SmmLabaService();
                    break;
                case 'tiktokmnogo':
                    $apiService = new TikTokMnogoService();
                    break;
            }


            $data = $apiService->order($apiData);
            if (isset($data->order)) {
                $data->service    = $serviceData['api'];
                $data->service_id = $serviceData['id'];

                $order->comment = json_encode($data);
                $order->status  = 1;

                try {
                    $order->saveOrFail();
                } catch (\Throwable $e) {
                    Log::error('Cant save new order', ['error' => $e->getMessage()]);
                    throw new BotException("An error has occurred. \r\nContact Support. Error #101");
                }
                $this->service->correctUserBalance($userData->id, -$order->price, false, 'Создание заказа: ' . $url);

                return $order->id;
            } else {
                Log::error('Can\'t Create Order By Api: ', ['api_resp' => $data]);

                $botman->say('Ошибка при создании заказа, API: '.$serviceData['api']. '. Info: ' . json_encode($data), config('botman.telegram.notificationTelegramUserId'), TelegramDriver::class);

                /**
                 * Опоповещение о необходимости пополнить счет в сервисе ресейла
                 */
                if (isset($data->errorcode) && $data->errorcode == -7) {
                    $botman->say('Необходимо пополнить счет в сервисе ресейла: ' . $serviceData['api'], config('botman.telegram.notificationTelegramUserId'), TelegramDriver::class);
                }

                throw new BotException("An error has occurred. \r\nContact Support. Error #102");
            }
        }
        catch (BotException $e) {
            throw $e;
        }
        catch (\Throwable $e) {
            Log::error('Can\'t Create Order By Api: ', ['error' => $e, 'serviceData' => $serviceData]);

            $botman->say('Ошибка при создании заказа, API:' . $serviceData['api'], config('botman.telegram.notificationTelegramUserId'), TelegramDriver::class);
            if(isset($data)) $botman->say('Ответ сервиса: ' . $data, config('botman.telegram.notificationTelegramUserId'), TelegramDriver::class);

            throw new BotException("An error has occurred. \r\nContact Support. Error #103");
        }
    }

}
