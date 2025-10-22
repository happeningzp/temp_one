<?php

namespace App\Http\Conversations;

use App\Exceptions\BotException;
use App\Models\UserBot;
use App\Services\KeyboardService;
use App\Services\OrderService;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Illuminate\Support\Facades\Log;

class CreateOrderConversation extends ParentConversation
{
    protected $url;
    protected $count;
    protected $userData;
    protected $price;
    protected $min;
    protected $max;
    protected $urlMask;
    protected $serviceData;
    protected $firstQuestion;

    public function __construct(UserBot $userData, $serviceData)
    {
        $this->userData = $userData;
        $this->url      = false;
        $this->count    = 0;

        $this->price   = $serviceData['price']   ?? 0.5;
        $this->min     = $serviceData['min']     ?? 10;
        $this->max     = $serviceData['max']     ?? 100000;
        $this->urlMask = $serviceData['urlMask'] ?? 'https://';
        $this->serviceData = $serviceData;


        $this->firstQuestion = Question::create(
            "☁ Send link to a page on social network ⬇️ \r\n\r\n" .
            "❗ Page must be open for all users ❗ \r\n" .
            "Link example: " . $this->urlMask . '...'
        );

        parent::__construct();
    }

    public function askUrl()
    {
        $this->ask($this->firstQuestion, function (Answer $answer) {
            $this->botmanService->logMessage($answer->getText(), $this->userData->user_id);

            $url = OrderService::checkUrl($answer->getText(), $this->urlMask);

            if (!$url) {
                $this->say('Check link and try again.', $this->cancelKeyboard);
                $this->repeat();
            } else {
                $this->url = $url;
                $this->askCount();
            }
        });
    }

    public function askCount()
    {
        $howMuchSubscribers = OrderService::getSubsCount($this->userData->balance, $this->price);

        if($howMuchSubscribers == 0 || $howMuchSubscribers < $this->min) {
            $this->say(
                "Your balance is not enough for the minimum order.  \r\n\r\n".
                "Add balance to account and try again 👇", $this->mainKeyboard);
            return;
        }

        $question = Question::create(
            "Your balance is enough for {$howMuchSubscribers} subs/views/likes/etc. 👥" .
            "\r\nEnter quantity for order (from ". $this->min ." to ". $this->max .") 👇🏼"
        )->callbackId('ask_subscribers_count');

        $this->ask($question, function (Answer $answer) {
            $this->botmanService->logMessage($answer->getText(), $this->userData->user_id);

            try {
                $orderId = $this->orderService->createOrder(
                    $this->userData, $this->url, $answer->getText(), $this->serviceData
                );
            } catch (BotException $e) {
                $this->say($e->getMessage());
                $this->repeat();
                return;
            }
            catch (\Throwable $e) {
                $this->say($e->getMessage());
                $this->say('An error has occurred. Try again.');
                $this->repeat();
                return;
            }

            $this->say(
                "👍🏻 Your order for " . $this->url . " successfully created! \r\n" .
                "Order ID: " . $orderId . "\r\n",
                $this->mainKeyboard + ['disable_web_page_preview' => 'true']);
        });
    }

    /**
     * Start the conversation
     */
    public function run()
    {
        $this->askUrl();
    }
}
