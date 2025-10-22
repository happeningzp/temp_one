<?php

namespace App\Services;

use App\Jobs\MessageSender;
use App\Models\HistoryBalance;
use App\Models\HistoryMessage;
use App\Models\Referral;
use App\Models\UserBot;
use BotMan\BotMan\Interfaces\UserInterface;
use BotMan\Drivers\Telegram\TelegramDriver;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BotmanService
{
    /**
     * По ID скорректирует баланс пользователю
     * @param $user_id
     * @param $amount
     * @param $isReferrals
     * @param $comment
     */
    public function correctUserBalance($user_id, $amount, $isReferrals = false, $comment = false)
    {
        $user = UserBot::where('id', '=', $user_id)->first();
        $user->balance = $user->balance + $amount;
        $user->save();

        if ($comment) {
            /** Запись инфы о транзе */
            HistoryBalance::create([
                'user_id' => $user->user_id,
                'amount'  => $amount,
                'comment' => $comment
            ]);
        }

        if ($user->ref_id > 0 && $isReferrals) {
            /** Зачисление рефереру 10% от затрат */
            $ref_amount = round((abs($amount) / 10), 2);
            $referrer = UserBot::query()->where('user_id', '=', $user->ref_id)->first();
            $referrer->balance = $referrer->balance + $ref_amount;
            $referrer->save();

            /** Запись инфы о транзе */
            HistoryBalance::create([
                'user_id' => $user->ref_id,
                'amount'  => $ref_amount,
                'comment' => 'Реферальное зачисление от пользователя: ' . $user->user_id
            ]);

            /** Запись в стату о рефералах */
            $ref_stat = Referral::query()->where('user_id', '=', $user->user_id)->first();
            $ref_stat->amount = $ref_stat->amount + $ref_amount;
            $ref_stat->save();
        }
    }

    /**
     * Return user balance
     * @param $user_id
     * @return mixed
     */
    public function getUserBalance($user_id) {
        $user = UserBot::where('user_id', '=', $user_id)->first();
        if($user) return $user->balance;
        else return 0;
    }

    public function getReferralStat($user_id) {
        $stat['count'] = UserBot::query()->where('ref_id', '=', $user_id)->count('id');
        $stat['amount'] = Referral::query()->where('referral_id','=', $user_id)->sum('amount');
        $stat['amount'] = round($stat['amount'], 2);
        return $stat;
    }

    /**
     * Зарегистрирует и вернет пользователя.
     * @param UserInterface $data
     * @param $refId
     * @param $refSource
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function getOrRegisterUser(UserInterface $data, $refId = false, $refSource = '')
    {
        if ($refId) {
            if (stripos($refId, '_') > 0) {
                $refSource = substr($refId, stripos($refId, '_') + 1);
                $refId = substr($refId, 0, stripos($refId, '_'));
            }
        }

        $first_name = preg_replace('/\PL /u', '', $data->getFirstName());
        $last_name = preg_replace('/\PL /u', '', $data->getLastName());

        $user = UserBot::query()
            ->firstOrCreate([
                'user_id' => $data->getId(),
            ], [
                'user_id' => $data->getId(),
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'username'   => $data->getUsername(),
                'ref_id'     => (int) $refId,
                'balance'    => 0
            ]);

        if ($refId > 0 && $user->wasRecentlyCreated) {
            Referral::query()
                ->firstOrCreate([
                    'user_id' => $user->user_id,
                ], [
                    'user_id' => $user->user_id,
                    'referral_id' => $refId,
                    'source' => $refSource
                ]);
        }

        return $user;
    }


    /**
     * Проверка подписки пользователя на канал
     * @param $user_id
     * @return mixed
     * @throws
     */
    public function checkSubscribe($user_id) {
        $token = config('botman.telegram.token');
        $channel = config('botman.telegram.channel');

        $apiUrl = 'https://api.telegram.org/bot'. $token .'/getChatMember?user_id=' . $user_id . '&chat_id=@' . $channel;

        $r = new Client();

        $resp = $r->get($apiUrl);
        $resp = json_decode($resp->getBody());

        if( isset($resp->ok) && isset($resp->result) && in_array($resp->result->status, ['member', 'creator']) ) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Пополнение счета админом
     * @param $bot
     * @param $user
     * @param $user_id
     * @param $amount
     * @return array|bool
     */
    public function addFundsByAdmin($bot, $user, $user_id, $amount)
    {
        $sender = $this->getOrRegisterUser($user);
        if($sender->is_admin == 0) return ['error' => 'Доступно только администраторам.'];

        $user = UserBot::query()
                ->where('user_id', '=', $user_id)
                ->first();

        if(!isset($user)) return ['error' => 'Пользователь не найден.'];

        try {
            $user->balance = $user->balance + $amount;
            $user->save();

            /** Запись инфы о транзе */
            HistoryBalance::create([
                'user_id' => $user->user_id,
                'amount'  => $amount,
                'comment' => 'Зачисление администратором'
            ]);

        } catch (\Throwable $error) {
            Log::error('BotmanService->addFundsByAdmin: ', [$error->getMessage()]);
            return ['error' => 'Произошла ошибка при пополнении баланса.'];
        }

        return true;
    }


    /**
     * /repost. message sender
     * @param $bot
     */
    public function sendMessagesToAllUsers($bot)
    {
        $sender = $this->getOrRegisterUser($bot->getUser());
        if($sender->is_admin == 0) {
            $bot->reply('Доступно только администраторам.');
            die;
        }

        /** Телеграм дублирует коллбеки. Здесь в базу заносится инфа о полученных команд на репост что бы не повторялось. */
        $repostMessageId = $bot->getMessage()->getPayload()['message_id'];
        $isNew = DB::table('repost')->select()->where('message_id', '=', $repostMessageId)->first();
        if(isset($isNew) && !empty($isNew)) {
            Log::info('Telegram retry... zaebal');
            return;
        }
        else DB::table('repost')->insert(['message_id' => $repostMessageId]);
        /** */

        $messageSource = $bot->getMessage()->getPayload()['reply_to_message'];
        MessageSender::dispatchAfterResponse($messageSource, $sender->user_id, 0, 0, 0);
        $bot->reply("Команда получена. Начинаю рассылку.");
    }


    /**
     * run by JOB
     * @param $messageSource
     * @param $senderTelegramId
     * @param $startId
     * @param $success
     * @param $err
     */
    public function messageSender($messageSource, $senderTelegramId, $startId, $success = 0, $err = 0)
    {
        if($startId >= 500000) return;
        if(!isset($startId)) $startId = 0;

        Log::info('run from id: ' . $startId);

        $bot   = app('botman');
        $token = config('botman.telegram.token');

        if(isset($messageSource['caption'])) $message = $messageSource['caption'];
        if(isset($messageSource['text']))    $message = $messageSource['text'];

        if(isset($messageSource['photo'][2])) $imageId = $messageSource['photo'][2]['file_id'];
        if(isset($messageSource['photo'][1])) $imageId = $messageSource['photo'][1]['file_id'];
        if(isset($messageSource['photo'][0])) $imageId = $messageSource['photo'][0]['file_id'];

        $entities = [];
        if(isset($messageSource['caption_entities'])) $entities = $messageSource['caption_entities'];
        if(isset($messageSource['entities']))         $entities = $messageSource['entities'];

        $users = UserBot::query()->select()->where('id', '>', $startId)->limit(1000)->get();
        if(count($users) == 0) {
            $bot->say("Рассылка окончена. Успешно: $success, неуспешно: $err", $senderTelegramId, TelegramDriver::class);
            return;
        }

        $bot->say("Рассылка запущена на " . count($users) . " пользователей. Start ID: ".$startId, $senderTelegramId, TelegramDriver::class);

        $api = new Client();

        foreach($users as $user) {
            if(isset($imageId)) {
                $url = 'https://api.telegram.org/bot' . $token . '/sendPhoto?';
                $params = [
                    'chat_id' => $user->user_id,
                    'caption' => $message,
                    'photo'   => $imageId,
                    'caption_entities' => json_encode($entities)
                ];
            } else {
                $url = 'https://api.telegram.org/bot' . $token . '/sendMessage?';
                $params = [
                    'chat_id' => $user->user_id,
                    'text'    => $message,
                    'entities' => json_encode($entities),
                    'disable_web_page_preview' => 'true'
                ];
            }

            $params = http_build_query($params);

            try {
                $api->get($url.$params)->getBody();
                $success++;
                //Log::info('Message Successful Send', ['chat_id' => $user->user_id]);
            } catch (\Throwable $e) {
                $err++;
                //Log::error('Error send Message', ['chat_id' => $user->user_id]);
            }
        }

        MessageSender::dispatch($messageSource, $senderTelegramId, $startId+1000, $success, $err);
    }



    /**
     * Вернет айди админов
     * @return array
     */
    public static function getAdminIds()
    {
        return UserBot::query()->where('is_admin', '=', '1')->get('user_id')->toArray();
    }



    /**
     * @param $message
     * @param $userId
     */
    public function logMessage($message, $userId)
    {
        $message = preg_replace('/\PL /u', '', $message);
        try {
            $historyMessage = new HistoryMessage();
            $historyMessage->message = $message;
            $historyMessage->user_id = $userId;
            $historyMessage->save();
        } catch (\Throwable $e) {
            Log::error('Error on log message: ' . $e->getMessage());
        }
    }
}
