<?php

namespace App\Services;

use BotMan\Drivers\Telegram\Extensions\Keyboard;
use BotMan\Drivers\Telegram\Extensions\KeyboardButton;

class KeyboardService
{
    /**
     * Кнопки для ссылки на канал и проверки подписки
     */
    public function getSubscribeKeyboard()
    {
        $channel = config('botman.telegram.channel');

        return Keyboard::create()->type( Keyboard::TYPE_INLINE )->oneTimeKeyboard(false)
            ->addRow(
                KeyboardButton::create('➡️ Перейти на канал')->url('https://t.me/'.$channel)
            )
            ->addRow(
                KeyboardButton::create('✔️ Проверить подписку')->callbackData('subscribe_check')
            )
            ->toArray();
    }


    /**
     * @return array
     */
    public function getMainKeyboard()
    {
        return Keyboard::create()->type( Keyboard::TYPE_KEYBOARD )
            ->addRow(
                KeyboardButton::create("🛒 New order")
            )
            ->addRow(
                KeyboardButton::create("💳 Add balance")
            )
            ->addRow(
                KeyboardButton::create("💵 Balance"),
                KeyboardButton::create("⚙ Orders")
            )
            ->addRow(
                KeyboardButton::create("👨🏻‍💻 Referrals"),
                KeyboardButton::create("🦸‍♂️ Support")
            )
            ->resizeKeyboard(true)
            ->toArray();
    }

    /**
     * Кнопка отмена
     * @return array
     */
    public function getCancelKeyboard()
    {
        return Keyboard::create()->type( Keyboard::TYPE_KEYBOARD )
            ->addRow(
                KeyboardButton::create("🔚 Main menu")
            )
            ->oneTimeKeyboard(true)
            ->resizeKeyboard(true)
            ->toArray();
    }


    /**
     * Кнопки для статистики заказов | Active
     * @param $type
     * @return mixed
     */
    public function getOrdersKeyboard($type = 'active')
    {
        $menu = Keyboard::create()->type(Keyboard::TYPE_INLINE)->oneTimeKeyboard(false);
        if($type == 'active') {
            $menu->addRow(
                KeyboardButton::create("✔ Active")->callbackData('orders_active'),
                KeyboardButton::create("Completed")->callbackData('orders_done')
            );
        } else {
            $menu->addRow(
                KeyboardButton::create("Active")->callbackData('orders_active'),
                KeyboardButton::create("✔ Completed")->callbackData('orders_done')
            );
        }
        return $menu->toArray();
    }

    /**
     * Кнопка для ссылки на пополнение счета
     * @param $url
     * @return array
     */
    public function getPaymentKeyboard($url)
    {
        return Keyboard::create()->type(Keyboard::TYPE_INLINE)->oneTimeKeyboard(false)
            ->addRow(
                KeyboardButton::create('Pay')->url($url)
            )
            ->toArray();
    }



    public static function getInlineOrdersKeyboard($data, $network = null)
    {
        $menu = Keyboard::create()->type( Keyboard::TYPE_INLINE )->oneTimeKeyboard(true);

        if(isset($data) && !empty($data)) {
            foreach($data as $name => $item)
            {
                if(isset($item['name'])) {
                    /** Если это категория соц.сети */
                    $menu->addRow(KeyboardButton::create($item['name'])->callbackData('create_order '. $network .' service ' . $name));
                } else {
                    /** Если это выбор соц.сети */
                    $menu->addRow(KeyboardButton::create($name)->callbackData('create_order network ' . $name));
                }
            }
        }

        if(!is_null($network)) $menu->addRow(KeyboardButton::create('↩️ Back')->callbackData('create_order__back_to_main_menu'));

        return $menu->toArray();
    }
}
