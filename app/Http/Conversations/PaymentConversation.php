<?php

namespace App\Http\Conversations;

use App\Exceptions\BotException;
use App\Models\UserBot;
use App\Services\PaymentsService;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Facades\Log;

class PaymentConversation extends ParentConversation
{
    protected $user;
    protected $user_id;

    public $sum;
    public $keyboard;
    public $paymentService;

    /**
     * Айди юзера
     * @param $user_id
     * @param $user
     */
    public function __construct($user_id, $user)
    {
        $this->user = $user;
        $this->user_id = $user_id;
        $this->sum     = 0;
        $this->paymentService = new PaymentsService();
        parent::__construct();
    }

    public function askSum()
    {
        $question = Question::create(
            "💸 Enter the recharge amount ($) \r\n\r\n" .
            "Minimum: 5$"
        );

        $this->ask($question, function (Answer $answer) {
            try {
                $this->botmanService->logMessage($answer->getText(), $this->user_id);
            } catch (\Throwable $e) {
                Log::info($e->getMessage());
            }


            $this->sum = (int) $answer->getText();

            $user = $this->botmanService->getOrRegisterUser($this->user);

            if( $this->sum < 5 && $user->is_admin == 0 ) {
                $this->repeat();
                return;
            }

            //$this->askSumRepeat();

            $invoice = $this->paymentService->makeInvoice($this->sum);
            try {
                $resp = $this->bot->sendRequest('sendInvoice', $invoice);
                Log::info('Resp', [$resp]);
            } catch (\Throwable $e) {
                Log::error('Err', ['err' => $e->getMessage()]);
            }
        });
    }

//    public function askSumRepeat()
//    {
//        $question = Question::create(
//            "If the amount was entered incorrectly - enter another value."
//        );
//
//        $this->ask($question, function (Answer $answer) {
//            try {
//                $this->botmanService->logMessage($answer->getText(), $this->user_id);
//            } catch (\Throwable $e) {
//                Log::info($e->getMessage());
//            }
//
//            $this->sum = (int) $answer->getText();
//
//            $url = $this->paymentService->makeLink($this->sum, $this->user_id);
//
//            $this->keyboard = $this->keyboardService->getPaymentKeyboard($url);
//
//            $this->repeat();
//
//            $this->say(
//                "💸 Нажмите кнопку ниже для перехода на страницу оплаты",
//                $this->keyboard
//            );
//        });
//    }

    /**
     * Start the conversation
     */
    public function run()
    {
        $this->askSum();
    }
}
