<?php

namespace App\Services;

use App\Models\ServiceData;
use Illuminate\Support\Facades\Log;

class ParseService
{
    /**
     * Get services and check it status
     * @return array|mixed
     */
    public static function getServices()
    {
        $data = self::parseJson();
        $servicesData = ServiceData::all();
        $services     = [];

        if ($servicesData->isEmpty()) return $data;

        foreach ($servicesData as $service) {
            $services[$service->name] = json_decode($service->data, true);
        }

        foreach ($data as $networkName => $network) {
            if (empty($network)) continue;
            foreach ($network as $itemName => $itemData) {
                if ($itemData['api'] == 'tiktokmnogo') continue;
                if (!in_array($itemData['id'], $services[$itemData['api']])) {
                    unset($data[$networkName][$itemName]);
                }
            }
        }

        return $data;
    }

    /**
     * Get services from .json config file
     * @return array|mixed
     */
    private static function parseJson()
    {
        try {
            $fileUrl  = "../prices.json";
            $file     = fopen($fileUrl, "r");
            $fileData = fread($file, filesize($fileUrl));
            return json_decode($fileData, true);
        } catch (\Throwable $error) {
            Log::critical('ParseService: ', ['error' => $error->getMessage()]);
            return [];
        }
    }

    /**
     * Update actual services by API
     */
    public function updateServices()
    {
        /** SMM LABA */
        $smmlabaApi = new \App\Services\Api\SmmLabaService();
        $result = $smmlabaApi->services();
        if($result->result == 'success' && empty($result->error) && !empty($result->message)) {
            $services = [];
            foreach($result->message as $service) {
                if(isset($service->service)) $services[] = $service->service;
            }

            $data = json_encode($services);
            ServiceData::query()->updateOrInsert(['name' => 'smmlaba'], ['data' => $data]);
        }

        /** BigSMM */
        $bigsmmApi = new \App\Services\Api\BigsmmService();
        $result = $bigsmmApi->services();
        if(!empty($result)) {
            $services = [];
            foreach($result as $service) {
                if(isset($service->service)) $services[] = $service->service;
            }

            $data = json_encode($services);
            ServiceData::query()->updateOrInsert(['name' => 'bigsmm'], ['data' => $data]);
        }

        /** Wos */
        $wosApi = new \App\Services\Api\WosService();
        $result = $wosApi->services();
        if(!empty($result)) {
            $services = [];
            foreach($result as $service) {
                if(isset($service->service)) $services[] = $service->service;
            }

            $data = json_encode($services);
            ServiceData::query()->updateOrInsert(['name' => 'wos'], ['data' => $data]);
        }
    }
}


