<?php

namespace App\Jobs;

use App\Services\BotmanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageSender implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $messageSource;
    public $senderTelegramId;
    public $service;
    public $startId;
    public $success;
    public $err;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($messageSource, $senderTelegramId, $startId, $success, $err)
    {
        $this->messageSource = $messageSource;
        $this->senderTelegramId = $senderTelegramId;
        $this->startId = $startId;
        $this->success = $success;
        $this->err = $err;
        $this->service = new BotmanService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $this->service->messageSender($this->messageSource, $this->senderTelegramId, $this->startId, $this->success, $this->err);
        } catch (\Throwable $e) {
            Log::error('Job MessageSender error: ', ['error' => $e->getMessage()]);
        }
    }
}
