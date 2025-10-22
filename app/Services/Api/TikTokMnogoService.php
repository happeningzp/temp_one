<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Log;

class TikTokMnogoService
{
    const apiUrl = 'https://tiktokmnogo.com/api.php?method=';
    private $curl;

    public $apikey;

    public function __construct()
    {
        $this->curlInit();
        $this->apikey = config('botman.api.tiktokmnogo');
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
        curl_setopt($this->curl, CURLOPT_URL, $url .'&'. http_build_query($postData));
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
        return array('apikey' => $this->apikey);
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
    public function multiStatus($orderIds)
    {
        $data = $this->getInitArray();
        $data['orders_ids'] = $orderIds;
        $resp = $this->postDecode($this->curlPost($this::apiUrl . 'get_orders_by_id', $data));

        if(!isset($resp->detail) && $resp) {
            $orders = [];
            foreach ($resp as $order) {
                if ($order->status == 'success') {
                    $orders[$order->id]['status'] = 'Completed';
                } elseif ($order->status == 'canceled') {
                    $orders[$order->id]['status'] = 'Canceled';
                } else {
                    $orders[$order->id]['status'] = 'Completed';
                }
            }
            return $orders;
        }
        return false;
    }

    /** Create new Order */
    public function order($params)
    {
        $data = $this->getInitArray();
        $data['type']   = $params['service'];
        $data['url']    = $params['link'];
        $data['amount'] = $params['quantity'];
        $data['hours']  = 0;

        $resp = $this->postDecode($this->curlPost($this::apiUrl.'create_order', $data));

        if(isset($resp->id)) {
            return (object) ['order' => $resp->id];
        }

        return false;
    }
}
