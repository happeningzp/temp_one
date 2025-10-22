<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Log;

class SmmLabaService
{
    const apiUrl = 'https://smmlaba.com/vkapi/v1/';
    private $curl;

    public $username;
    public $apikey;

    public function __construct()
    {
        $this->curlInit();
        $this->username = config('botman.api.smmlaba_username');
        $this->apikey = config('botman.api.smmlaba_token');
    }

    public function __destruct()
    {
        $this->curlClose();
    }

    private function curlInit()
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curl, CURLOPT_USERAGENT, 'Curl/Api');
    }

    private function curlClose()
    {
        curl_close($this->curl);
    }

    private function curlPost($url, array $postData = array())
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POST, count($postData));
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->getPostString($postData));
        $curlRes = curl_exec($this->curl);

        if (curl_error($this->curl)) {
            return false;
        } else {
            return $curlRes;
        }
    }

    private function getPostString(array $postData)
    {
        $post_string = '';
        foreach ($postData as $key => $value) {
            $post_string .= $key . '=' . $value . '&';
        }
        return rtrim($post_string, '&');
    }

    private function getInitArray()
    {
        return array('username' => $this->username, 'apikey' => $this->apikey);
    }

    private function postDecode($str)
    {
        if (!$str) return false;
        $result = json_decode($str);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $result;
        } else {
            return false;
        }
    }


    /** Стата по заказам */
    public function multiStatus($ids)
    {
        $ids = explode(",", $ids);

        $orders = [];
        foreach ($ids as $id) {
            $result = $this->check($id);

            if ($result->result == 'success') {
                $orders[$id]['status'] = 'Completed';

                //Сделка завершена - 5, Ожидание - 1
                if (in_array($result->message->status, [1, 5])) {
                    $orders[$id]['status'] = 'In progress';
                }

                //В обработке - 2
                if (in_array($result->message->status, [2, 17])) {
                    $orders[$id]['status'] = 'Pending';
                }

                //Возмещенный - 11, Отменено - 7
                if (in_array($result->message->status, [7, 11])) {
                    $orders[$id]['status'] = 'Canceled';
                }
            } else {
                $orders[$id]['status'] = 'Completed';
            }
        }
        return $orders;
    }

    public function check($orderid)
    {
        $data = $this->getInitArray();
        $data['action'] = 'check';
        $data['orderid'] = $orderid;
        return $this->postDecode($this->curlPost($this::apiUrl, $data));
    }

    /** Create new Order */
    public function order($params)
    {
        $data = $this->getInitArray();
        $data['action']  = 'add';
        $data['service'] = $params['service'];
        $data['url']     = $params['link'];
        $data['count']   = $params['quantity'];

        $resp = $this->postDecode($this->curlPost($this::apiUrl, $data));

        Log::info('api resp:', ['resp' => $resp]);

        if(isset($resp->message->orderid)) {
            return (object) ['order' => $resp->message->orderid];
        }

        return false;
    }


    public function services()
    {
        $data = $this->getInitArray();
        $data['action'] = 'services';
        return $this->postDecode($this->curlPost($this::apiUrl, $data));
    }
}
