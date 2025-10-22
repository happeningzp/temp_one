<?php

namespace App\Http\Conversations;

use App\Services\BotmanService;
use App\Services\KeyboardService;
use App\Services\OrderService;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;

class ParentConversation extends Conversation
{
    protected $keyboardService;
    protected $cancelKeyboard;
    protected $mainKeyboard;
    protected $ordersKeyboard;
    protected $botmanService;
    protected $orderService;

    public function __construct()
    {
        $this->botmanService   = new BotmanService();
        $this->orderService    = new OrderService();
        $this->keyboardService = new KeyboardService();
        $this->cancelKeyboard  = $this->keyboardService->getCancelKeyboard();
        $this->mainKeyboard    = $this->keyboardService->getMainKeyboard();
        $this->ordersKeyboard  = $this->keyboardService->getOrdersKeyboard();
    }

    public function run() {

    }
}
