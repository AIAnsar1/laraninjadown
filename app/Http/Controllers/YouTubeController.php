<?php

namespace App\Http\Controllers;

use SergiX44\Nutgram\Conversations\InlineMenu;
use SergiX44\Nutgram\Nutgram;
use App\Models\TelegramUser;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Telegram\Types\Keyboard\{InlineKeyboardButton, InlineKeyboardMarkup};
use App\Services\YouTubeService;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use App\Models\ContentCache;
use Illuminate\Support\Facades\Log;

class YouTubeController extends InlineMenu
{
    /**
     * @var YouTubeService
     */
    private YouTubeService $yt_service;

    /**
     * @param YouTubeService $yt_service
     */
    public function __construct(YouTubeService $yt_service)
    {
        $this->yt_service = $yt_service;
    }
    /**
     * /start
     */
    public function start(Nutgram $bot): void
    {
        // Клавиатура создаётся «флюидно», так проще читать
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🇺🇿 UZ', callback_data: 'lang:uz'),
                InlineKeyboardButton::make('🇷🇺 RU', callback_data: 'lang:ru'),
                InlineKeyboardButton::make('🇬🇧 ENG', callback_data: 'lang:eng'),
            );

        $bot->sendMessage(
            text: "Выберите язык / Choose your language / Iltimos Bot Tilini Tanlagn",   // дефолтное приветствие
            reply_markup: $keyboard
        );
    }

    /**
     * Обработка нажатия на кнопку языка
     * Регистрацию хэндлера см. ниже
     */
    public function setLanguage(Nutgram $bot): void
    {
        $user      = $bot->user();
        $callback  = $bot->callbackQuery();
        $lang      = str_replace('lang:', '', $callback->data);

        // сохраняем / обновляем пользователя
        TelegramUser::updateOrCreate(
            ['user_id' => $user->id],
            [
                'username' => $user->username,
                'name'     => $user->first_name,
                'surname'  => $user->last_name,
                'language' => $lang,
            ]
        );

        // уведомляем о выборе (одно короткое всплывающее сообщение)
        $bot->answerCallbackQuery(
            text: __('messages.language_selected', locale: $lang)
        );

        // Меняем текст исходного сообщения
        $bot->editMessageText(
            text: __('messages.language_selected', locale: $lang)
        );
    }

    public function handleLink(Nutgram $bot)
    {
        $text = $bot->message()?->text;

        if (!$text || !preg_match('~^https?://~', $text)) {
            return; // Не ссылка — игнорируем
        }

        // YouTube
        if (preg_match('~(youtube\.com|youtu\.be|youtube\.com/shorts)~i', $text)) {
            $hash = substr(preg_replace('/[^a-zA-Z0-9]/', '', hash('crc32', $text)), 0, 8);
            $bot->set('yt_url_' . $hash, $text);

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(
                        text: '🎬 Видео',
                        callback_data: 'yt:video:' . $hash
                    ),
                    InlineKeyboardButton::make(
                        text: '🎵 Аудио',
                        callback_data: 'yt:audio:' . $hash
                    )
                );

            $bot->sendMessage(
                __('messages.choose_download_type', [], $this->getUserLang($bot)),
                reply_markup: $keyboard
            );
            return;
        }
        // Если не поддерживается
        $bot->sendMessage(__('messages.unsupported_link', [], $this->getUserLang($bot)));
    }


    /**
     * Обработка выбора "Аудио" — скачивание и отправка
     */
    public function downloadYoutubeAudio(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;
        Log::info('downloadYoutubeAudio called', ['data' => $data]);
        if (!preg_match('~^yt:audio:([a-f0-9]+)$~', $data, $m)) {
            $bot->sendMessage('Ошибка: неверный формат callback_data!');
            return;
        }
        $hash = $m[1];
        $url = $bot->get('yt_url_' . $hash);
        if (!$url) {
            $bot->sendMessage('Ошибка: ссылка не найдена (hash устарел или не сохранён).');
            return;
        }
        $lang = $this->getUserLang($bot);
        // Кеш
        $cache = ContentCache::where('content_link', $url)->where('formats', 'audio')->first();
        if ($cache && $cache->file_id) {
            $bot->sendAudio($cache->file_id, caption: __('messages.your_audio_file', [], $lang));
            return;
        }
        $result = $this->yt_service->fetch($url, 'audio');
        if ($result && file_exists($result['path'])) {
            $cacheChannel = config('nutgram.cache_channel');
            $msg = $bot->sendAudio(InputFile::make($result['path']), chat_id: $cacheChannel, caption: __('messages.your_audio_file', [], $lang));
            $file_id = $msg->audio?->file_id;
            ContentCache::create([
                'title'        => $result['title'] ?? null,
                'content_link' => $url,
                'formats'      => 'audio',
                'chat_id'      => $cacheChannel,
                'message_id'   => $msg->message_id,
                'file_id'      => $file_id,
            ]);
            if ($file_id) {
                $bot->sendAudio($file_id, caption: __('messages.your_audio_file', [], $lang));
            } else {
                $bot->sendMessage(__('messages.post_download_error', [], $lang));
            }
        } else {
            $bot->sendMessage(__('messages.post_download_error', [], $lang));
        }
    }

    /**
     * Обработка выбора "Видео" — показ форматов (размер | качество)
     */
    public function downloadYoutubeVideo(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;
        Log::info('downloadYoutubeVideo called', ['data' => $data]);
        if (!preg_match('~^yt:video:([a-f0-9]+)$~', $data, $m)) {
            $bot->sendMessage('Ошибка: неверный формат callback_data!');
            return;
        }
        $hash = $m[1];
        $url = $bot->get('yt_url_' . $hash);
        if (!$url) {
            $bot->sendMessage('Ошибка: ссылка не найдена (hash устарел или не сохранён).');
            return;
        }
        $formats = $this->yt_service->getAvailableFormats($url);
        $keyboard = InlineKeyboardMarkup::make();
        $used_heights = [];
        $max_buttons = 5;
        $added = 0;
        foreach ($formats as $format) {
            $itag = preg_replace('/[^0-9]/', '', $format['itag']);
            $label = trim($format['label']);
            if (preg_match('~(\d+)p~', $label, $m)) {
                $height = (int)$m[1];
            } else {
                $height = null;
            }
            $callback = "yt:format:$itag:$hash";
            if (
                $height &&
                !in_array($height, $used_heights) &&
                strlen($callback) <= 64 &&
                $itag !== '' &&
                $label !== '' &&
                $added < $max_buttons
            ) {
                $keyboard->addRow(
                    InlineKeyboardButton::make($label, callback_data: $callback)
                );
                $used_heights[] = $height;
                $added++;
            }
        }
        $bot->sendMessage('Выберите формат видео:', reply_markup: $keyboard);
    }

    public function downloadYoutubeFormat(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;
        if (preg_match('~^yt:format:(\d+):([a-f0-9]+)$~', $data, $m)) {
            $itag = $m[1];
            $hash = $m[2];
            $url = $bot->get('yt_url_' . $hash);
        } else {
            $bot->sendMessage(__('messages.error_processing', [], $this->getUserLang($bot)));
            return;
        }
        $lang = $this->getUserLang($bot);
        // Кеш
        $cache = ContentCache::where('content_link', $url)->where('quality', $itag)->first();
        if ($cache && $cache->file_id) {
            $caption = __('messages.your_video_file', [], $lang);
            $bot->sendVideo($cache->file_id, caption: $caption);
            return;
        }
        // Скачиваем
        $result = $this->yt_service->fetch($url, 'video', $itag);
        if ($result && file_exists($result['path'])) {
            $caption = __('messages.your_video_file', [], $lang);
            $cacheChannel = config('nutgram.cache_channel');
            $msg = $bot->sendVideo(InputFile::make($result['path']), chat_id: $cacheChannel, caption: $caption);
            $file_id = $msg->video?->file_id;
            ContentCache::create([
                'title'        => $result['title'] ?? null,
                'content_link' => $url,
                'quality'      => $itag,
                'formats'      => 'video',
                'chat_id'      => $cacheChannel,
                'message_id'   => $msg->message_id,
                'file_id'      => $file_id,
            ]);
            if ($file_id) {
                $bot->sendVideo($file_id, caption: $caption);
            } else {
                $bot->sendMessage(__('messages.post_download_error', [], $lang));
            }
        } else {
            $bot->sendMessage(__('messages.post_download_error', [], $lang));
        }
    }
    // Пример получения языка пользователя
    protected function getUserLang(Nutgram $bot)
    {
        $user = TelegramUser::where('user_id', $bot->user()->id)->first();
        return $user->language ?? 'ru';
    }
}
