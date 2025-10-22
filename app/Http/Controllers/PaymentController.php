<?php

namespace App\Http\Controllers;

use App\Services\PaymentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{


    /**
     * Обработчик платежа. PAYOK.IO
     * @param Request $request
     * @return mixed
     */
    public function paymentCallback(Request $request)
    {
        Log::info('new payment', $request->all());
        try {
            (new PaymentsService())->paymentCallback($request->all());
        } catch (\Throwable $e) {
            return response('ERROR: ' . $e->getMessage(), 500);
        }
    }
}
