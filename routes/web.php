<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BotManController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run/cron/statuses', [\App\Http\Controllers\CronController::class, 'updateStatuses']);
Route::get('/run/cron/services', [\App\Http\Controllers\CronController::class, 'updateServices']);

//botman:telegram:register
Route::match(['get', 'post'], '/botman', [BotManController::class, 'handle']);

/** Callback с главного сайта, при получении платежа */
//Route::any('/pay/callback', [\App\Http\Controllers\PaymentController::class, 'paymentCallback']);

//Route::any('/migrate', function() {
//    try {
//        Artisan::call('migrate:fresh');
//        echo 'OK';
//    } catch (\Throwable $e) {
//        echo 'Error: ' . $e->getMessage();
//    }
//});

Route::any('/cache', function() {
    try {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('botman:install-driver telegram');
        //Artisan::call('queue:work --queue=high,default,sender');
        echo 'OK';
    } catch (\Throwable $e) {
        echo 'Error: ' . $e->getMessage();
    }
});

Route::get('/tg', function () {
    Artisan::call('botman:install-driver telegram');
});

Route::get('/migrate', function () {
    Artisan::call('migrate');
});

//Route::any('/jobs', function(){
//    Artisan::call('queue:work --queue=high,default,sender');
//});
