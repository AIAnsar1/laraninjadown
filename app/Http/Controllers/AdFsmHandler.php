<?php

namespace App\Http\Controllers;

use Nutgram\Nutgram;
use Illuminate\Support\Facades\Cache;
use App\Models\User;


class AdFsmHandler
{
    public static function handle(Nutgram $bot): void
    {
        $userId = $bot->userId();
        $fsm = Cache::get("ad_fsm_$userId");

        if (!$fsm || !isset($fsm['step'])) return;

        switch ($fsm['step']) {
            case 'waiting_for_lang':
                self::handleLanguage($bot, $fsm);
                break;

            case 'waiting_for_media':
                self::handleMedia($bot, $fsm);
                break;

            case 'waiting_for_text':
                self::handleText($bot, $fsm);
                break;

            case 'waiting_for_url':
                self::handleUrl($bot, $fsm);
                break;

            case 'waiting_for_button_text':
                self::handleButtonText($bot, $fsm);
                break;
        }
    }

    protected static function handleLanguage(Nutgram $bot, &$fsm): void
    {
        $text = $bot->message()?->text;
        if ($text === '🇷🇺 Русский') {
            $fsm['lang'] = 'ru';
        } elseif ($text === '🇺🇸 English') {
            $fsm['lang'] = 'en';
        } else {
            $bot->sendMessage('❗ Пожалуйста, выберите язык с клавиатуры.');
            return;
        }

        $fsm['step'] = 'waiting_for_media';
        Cache::put("ad_fsm_{$bot->userId()}", $fsm);
        $bot->sendMessage('📎 Отправьте медиа (фото/видео/гиф) или напишите /skip:');
    }

    protected static function handleMedia(Nutgram $bot, &$fsm): void
    {
        $media = null;

        if ($bot->message()?->photo) {
            $fsm['media_type'] = 'photo';
            $fsm['media'] = $bot->message()->photo[count($bot->message()->photo)-1]->file_id;
        } elseif ($bot->message()?->video) {
            $fsm['media_type'] = 'video';
            $fsm['media'] = $bot->message()->video->file_id;
        } elseif ($bot->message()?->animation) {
            $fsm['media_type'] = 'animation';
            $fsm['media'] = $bot->message()->animation->file_id;
        } elseif (strtolower($bot->message()->text) === '/skip') {
            $fsm['media_type'] = 'none';
        } else {
            $bot->sendMessage('❗ Пожалуйста, отправьте медиафайл или напишите /skip.');
            return;
        }

        $fsm['step'] = 'waiting_for_text';
        Cache::put("ad_fsm_{$bot->userId()}", $fsm);
        $bot->sendMessage('📝 Введите текст рекламы:');
    }

    protected static function handleText(Nutgram $bot, &$fsm): void
    {
        $fsm['ad_text'] = $bot->message()->text;
        $fsm['step'] = 'waiting_for_url';
        Cache::put("ad_fsm_{$bot->userId()}", $fsm);
        $bot->sendMessage('🔗 Введите ссылку:');
    }

    protected static function handleUrl(Nutgram $bot, &$fsm): void
    {
        $url = $bot->message()->text;
        if (!str_starts_with($url, 'http')) {
            $bot->sendMessage('❗ Введите корректную ссылку (с http/https).');
            return;
        }

        $fsm['button_url'] = $url;
        $fsm['step'] = 'waiting_for_button_text';
        Cache::put("ad_fsm_{$bot->userId()}", $fsm);
        $bot->sendMessage('📎 Введите название кнопки:');
    }

    protected static function handleButtonText(Nutgram $bot, &$fsm): void
    {
        $fsm['button_text'] = $bot->message()->text;
        $fsm['step'] = 'confirm_and_send';
        Cache::put("ad_fsm_{$bot->userId()}", $fsm);

        $markup = InlineKeyboardMarkup::make()->addRow(
            InlineKeyboardButton::make($fsm['button_text'], url: $fsm['button_url'])
        );

        if ($fsm['media_type'] === 'photo') {
            $bot->sendPhoto($fsm['media'])
                ->caption($fsm['ad_text'])
                ->replyMarkup($markup)
                ->send();
        } elseif ($fsm['media_type'] === 'video') {
            $bot->sendVideo($fsm['media'])
                ->caption($fsm['ad_text'])
                ->replyMarkup($markup)
                ->send();
        } elseif ($fsm['media_type'] === 'animation') {
            $bot->sendAnimation($fsm['media'])
                ->caption($fsm['ad_text'])
                ->replyMarkup($markup)
                ->send();
        } else {
            $bot->sendMessage($fsm['ad_text'])
                ->replyMarkup($markup);
        }

        $bot->sendMessage('✅ Всё готово. Отправить?');
    }
}




















