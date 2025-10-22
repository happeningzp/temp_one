<?php

namespace App\Http\Controllers;

use App\Services\OrdersService;
use App\Services\ParseService;

class CronController extends Controller
{

    /**
     * Update order statuses by API
     */
    public function updateStatuses()
    {
        $service = new OrdersService();
        $service->updateOrderStatusesForCron();
    }

    /**
     * Update services data by API
     */
    public function updateServices()
    {
        $service = new ParseService();
        $service->updateServices();
    }

}
