<?php

namespace App\Http\Controllers;

use App\Models\TelegramUser;
use Illuminate\Http\Request;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\{InlineKeyboardButton, InlineKeyboardMarkup};
use App\Services\{TikTokService, YouTubeService, PinterestService, InstagramService, FacebookService, OkService, VimeoService};
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Input\{InputMediaAudio, InputMediaVideo, InputMediaPhoto, InputMediaDocument};
use App\Models\ContentCache;
use App\Jobs\DownloadInstagramJob;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    /**
     * @var YouTubeService
     * @var InstagramService
     * @var TikTokService
     * @var PinterestService
     */
    private YouTubeService $yt_service;
    private InstagramService $ig_service;
    private TikTokService $tt_service;
    private PinterestService $pt_service;

    /**
     * @param YouTubeService $yt_service
     * @param InstagramService $ig_service
     * @param TikTokService $tt_service
     * @param PinterestService $pt_service
     */
    public function __construct(YouTubeService $yt_service, InstagramService $ig_service, TikTokService $tt_service, PinterestService $pt_service)
    {
        $this->yt_service = $yt_service;
        $this->ig_service = $ig_service;
        $this->tt_service = $tt_service;
        $this->pt_service = $pt_service;
    }

    /**
     * /start
     */
    public function start(Nutgram $bot): void
    {
        // Клавиатура создаётся «флюидно», так проще читать
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
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
     * Регистрацию хэ��длера см. ниже
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

        if (preg_match_all('~https?://\S+~', $text, $matches))
            {
            foreach ($matches[0] as $url)
            {
                $url = trim($url);

                if (preg_match('~instagram\.com~i', $url)) {
                    $this->downloadInstagram($bot, $url);
                } elseif (preg_match('~tiktok\.com~i', $url)) {
                    $this->downloadTikTok($bot, $url);
                } elseif (preg_match('~pinterest\.com|pin\.it~i', $url)) {
                    $this->downloadPinterest($bot, $url);
                } elseif (preg_match('~x\.com|twitter\.com~i', $url)) {
                    $this->downloadX($bot, $url);
                } elseif (preg_match('~vk\.com~i', $url)) {
                    $this->downloadVk($bot, $url);
                } elseif (preg_match('~ok\.ru~i', $url)) {
                    $this->downloadOk($bot, $url);
                } elseif (preg_match('~vimeo\.com~i', $url)) {
                    $this->downloadVimeo($bot, $url);
                } elseif (preg_match('~facebook\.com|fb\.watch~i', $url)) {
                    $this->downloadFacebook($bot, $url);
                }
            }
        }
    }

    public function downloadInstagram(Nutgram $bot, string $url): void
    {
        $lang = $bot->user()?->language ?? 'ru';
        $caption = __('Messages.instagram_video_downloaded', [], $lang);
        $messageId = $bot->message()->message_id;
        $chatId = $bot->chatId();
        $cache = ContentCache::where('content_link', $url)->first();

        if ($cache && $cache->file_id)
        {
            $bot->sendVideo($cache->file_id, caption: $caption, chat_id: $chatId, reply_to_message_id: $messageId);
            return;
        }
        $statusMsg = $bot->sendMessage('⏳ ' . __('Messages.queued', [], $lang), reply_to_message_id: $messageId);
        $statusMsgId = $statusMsg->message_id;
        DownloadInstagramJob::dispatch($bot->userId(), $url, 'instagram', $messageId, $chatId, $statusMsgId);
    }

    public function downloadTikTok(Nutgram $bot, string $url): void
    {
        $this->downloadMedia($bot, $url, 'TikTok', function($url) {
            return $this->tt_service->download($url);
        });
    }

    public function downloadPinterest(Nutgram $bot, string $url): void
    {
        $this->downloadMedia($bot, $url, 'Pinterest', function($url) {
            return $this->pt_service->download($url);
        });
    }

    public function downloadFacebook(Nutgram $bot, string $url): void
    {
        $this->downloadMedia($bot, $url, 'Facebook', function($url) {
            $service = app(FacebookService::class);
            return $service->download($url);
        });
    }

    public function downloadOk(Nutgram $bot, string $url): void
    {
        $this->downloadMedia($bot, $url, 'OK', function($url) {
            $service = app(OkService::class);
            return $service->download($url);
        });
    }

    public function downloadVimeo(Nutgram $bot, string $url): void
    {
        $this->downloadMedia($bot, $url, 'Vimeo', function($url) {
            $service = app(VimeoService::class);
            return $service->download($url);
        });
    }

    public function downloadVk(Nutgram $bot, string $url): void
    {
        $this->downloadMedia($bot, $url, 'VK', function($url) {
            $service = app(\App\Services\Vk::class);
            return $service->download($url);
        });
    }

    public function downloadX(Nutgram $bot, string $url): void
    {
        $this->downloadMedia($bot, $url, 'X (Twitter)', function($url) {
            $service = app(\App\Services\X::class);
            return $service->download($url);
        });
    }

    /**
     * Обработка выбора "Аудио" — скачивание и отправка
     */
    public function downloadYoutubeAudio(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;

        if (!preg_match('~^yt:audio:([a-f0-9]+)$~', $data, $m)) {
            $bot->sendMessage('Ошибка: нев��рный формат callback_data!');
            return;
        }

        $hash = $m[1];
        $url = cache()->get('yt_url_' . $hash);
        if (!$url) {
            $bot->sendMessage('Ошибка: ссылка не найдена (hash устарел или не сохранён).');
            return;
        }

        $lang = $this->getUserLang($bot);
        $chat_id = $bot->chatId();
        $message_id = $bot->callbackQuery()?->message?->message_id;

        // Меняем текст на "⏳ Скачивание..."
        $bot->editMessageText('⏳ Скачивание...', chat_id: $chat_id, message_id: $message_id);

        // Проверяем кеш
        $cache = ContentCache::where('content_link', $url)->where('formats', 'audio')->first();
        if ($cache && $cache->file_id) {
            $media = InputMediaAudio::make($cache->file_id);
            $media->caption = __('messages.your_audio_file', [], $lang);

            $bot->editMessageMedia(
                media: $media,
                chat_id: $chat_id,
                message_id: $message_id
            );
            return;
        }

        // Загружаем
        $result = $this->yt_service->fetch($url, 'audio');
        if ($result && file_exists($result['path'])) {
            $cacheChannel = config('nutgram.cache_channel');
            $msg = null;
            $file_id = null;

            try {
                $msg = $bot->sendAudio(
                    InputFile::make($result['path']),
                    chat_id: $cacheChannel,
                    caption: __('messages.your_audio_file', [], $lang)
                );
                $file_id = $msg->audio?->file_id;
            } catch (\Throwable $e) {
                Log::warning('sendAudio to cache channel failed: ' . $e->getMessage());
            }

            ContentCache::create([
                'title'        => $result['title'] ?? 'Audio',
                'content_link' => $url,
                'formats'      => 'audio',
                'chat_id'      => $cacheChannel,
                'message_id'   => $msg?->message_id,
                'file_id'      => $file_id,
                'quality'      => 'audio',
            ]);

            $media = $file_id
                ? InputMediaAudio::make($file_id)
                : InputMediaAudio::make(InputFile::make($result['path']));

            $media->caption = __('messages.your_audio_file', [], $lang);

            $bot->editMessageMedia(
                media: $media,
                chat_id: $chat_id,
                message_id: $message_id
            );
        } else {
            $bot->editMessageText(__('messages.post_download_error', [], $lang), chat_id: $chat_id, message_id: $message_id);
        }
    }

    /**
     * Обработка выбора "Видео" — показ форматов (размер | качество)
     */
    public function downloadYoutubeVideo(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;

        if (!preg_match('~^yt:video:([a-f0-9]+)$~', $data, $m)) {
            $bot->sendMessage('❌ Неверный формат.');
            return;
        }

        $hash = $m[1];
        $url = cache()->get('yt_url_' . $hash);
        if (!$url) {
            $bot->sendMessage('❌ Ссылка устарела или не найдена.');
            return;
        }

        $formats = $this->yt_service->getAvailableFormats($url);
        $keyboard = InlineKeyboardMarkup::make();
        $needed_heights = [320, 720, 1080, 2160];
        $added_heights = [];

        foreach ($formats as $format) {
            $itag = $format['itag'] ?? null;
            $height = $format['height'] ?? 0;
            $filesize = $format['filesize'] ?? 0;

            // Пропускаем, если нет itag, filesize или нужного разрешения
            if (!$itag || !$filesize || !in_array($height, $needed_heights)) {
                continue;
            }

            // Если такой height уже был — пропускаем (если нужно только 1 на высоту)
            if (in_array($height, $added_heights)) {
                continue;
            }

            $size = round($filesize / 1048576) . 'MB'; // в мегабайтах
            $label = "{$size} | {$height}p";
            $callback = "yt:format:$itag:$hash";

            $keyboard->addRow(
                InlineKeyboardButton::make($label, callback_data: $callback)
            );

            $added_heights[] = $height;
        }

        $bot->editMessageText('📺 Выберите формат видео:', reply_markup: $keyboard);
    }

    public function downloadYoutubeFormat(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;

        if (!preg_match('~^yt:format:(\d+):([a-f0-9]+)$~', $data, $m)) {
            $bot->sendMessage('Ошибка: неверный формат.');
            return;
        }

        $itag = $m[1];
        $hash = $m[2];
        $url = cache()->get('yt_url_' . $hash);
        $lang = $this->getUserLang($bot);
        $chat_id = $bot->chatId();
        $message_id = $bot->callbackQuery()?->message?->message_id;

        $bot->editMessageText('⏳ Скачивание...', chat_id: $chat_id, message_id: $message_id);

        // Кеш?
        $cache = ContentCache::where('content_link', $url)->where('quality', $itag)->first();
        if ($cache && $cache->file_id) {
            $media = InputMediaVideo::make($cache->file_id, __('messages.your_video_file', [], $lang));
            $bot->editMessageMedia(media: $media, chat_id: $chat_id, message_id: $message_id);
            return;
        }

        // Скачиваем видео
        $result = $this->yt_service->fetch($url, 'video', $itag);
        if (!$result || !file_exists($result['path'])) {
            $bot->editMessageText(__('messages.post_download_error', [], $lang), chat_id: $chat_id, message_id: $message_id);
            return;
        }

        $caption = __('messages.your_video_file', [], $lang);
        $cacheChannel = config('nutgram.cache_channel');
        $file_id = null;
        $message_id_cache = null;

        try {
            // ОТПРАВКА В КАНАЛ (без логирования объекта)
            $sent = $bot->sendVideo(
                InputFile::make($result['path']),
                chat_id: $cacheChannel,
                caption: $caption
            );
            $file_id = $sent->video?->file_id;
            $message_id_cache = $sent->message_id;
        } catch (\Throwable $e) {
            Log::channel('telegram')->warning('sendVideo to cache channel failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        ContentCache::create([
            'title'        => $result['title'] ?? null,
            'content_link' => $url,
            'quality'      => $itag,
            'formats'      => 'video',
            'chat_id'      => $cacheChannel,
            'message_id'   => $message_id_cache,
            'file_id'      => $file_id,
        ]);

        // Отправка пользователю
        $media = $file_id
            ? InputMediaVideo::make($file_id, $caption)
            : InputMediaVideo::make(InputFile::make($result['path']), $caption);

        try {
            $bot->editMessageMedia(
                media: $media,
                chat_id: $chat_id,
                message_id: $message_id
            );
        } catch (\Throwable $e) {
            Log::channel(channel: 'telegram')->warning('sendVideo to cache channel failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $bot->editMessageText(__('Messages.post_download_error', [], $lang), chat_id: $chat_id, message_id: $message_id);
        }
    }

    // Пример получения языка пользователя
    protected function getUserLang(Nutgram $bot)
    {
        $user = TelegramUser::where('user_id', $bot->user()->id)->first();
        return $user->language ?? 'ru';
    }
}
