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
use App\Jobs\{DownloadInstagramJob, DownloadTikTokJob, DownloadPinterestJob, DownloadXJob, DownloadYouTubeAudioJob};
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
                $lang = $bot->user()?->language ?? 'ru';

                if (preg_match('~instagram\.com~i', $url)) {
                    $this->downloadInstagram($bot, $url);
                } elseif (preg_match('~tiktok\.com~i', $url)) {
                    $this->downloadTikTok($bot, $url);
                } elseif (preg_match('~pinterest\.com|pin\.it~i', $url)) {
                    $this->downloadPinterest($bot, $url);
                } elseif (preg_match('~x\.com|twitter\.com~i', $url)) {
                    $this->downloadX($bot, $url);
                } elseif (preg_match('~tiktok\.com~i', $url, $matches)) {
                    $this->downloadTikTok($bot, $url);
                } elseif (preg_match('~(?:youtube\.com|youtu\.be)~i', $url)) {
                    $hash = md5($url);
                    cache()->put('yt_url_' . $hash, $url, now()->addMinutes(30));

                    $keyboard = InlineKeyboardMarkup::make()->addRow(
                        InlineKeyboardButton::make('🎵 Аудио', callback_data: "yt:audio:$hash"),
                        InlineKeyboardButton::make('📺 Видео', callback_data: "yt:video:$hash"),
                    );
                    $messageId = $bot->message()->message_id;
                    $chatId = $bot->chatId();
                    $bot->sendMessage(__('Messages.choose_download_type', [], $lang), reply_markup: $keyboard, reply_to_message_id: $messageId);
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
        DownloadTikTokJob::dispatch($bot->userId(), $url, 'instagram', $messageId, $chatId, $statusMsgId);
    }

    public function downloadPinterest(Nutgram $bot, string $url): void
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
        DownloadPinterestJob::dispatch($bot->userId(), $url, 'pinterest', $messageId, $chatId, $statusMsgId);
    }


    public function downloadX(Nutgram $bot, string $url): void
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
        DownloadInstagramJob::dispatch($bot->userId(), $url, 'x', $messageId, $chatId, $statusMsgId);
    }

    /**
     * Обработка выбора "Аудио" — скачивание и отправка
     */
    public function downloadYoutubeAudio(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;

        if (!preg_match('~^yt:audio:([a-f0-9]+)$~', $data, $m)) {
            $bot->sendMessage('❌ Неверный формат.');
            return;
        }
        $hash = $m[1];
        $url = cache()->get('yt_url_' . $hash);

        if (!$url) {
            $bot->sendMessage('❌ Ссылка устарела или не найдена.');
            return;
        }
        $lang = $this->getUserLang($bot);
        $chatId = $bot->chatId();
        $messageId = $bot->callbackQuery()?->message?->message_id;
        $cache = ContentCache::where('content_link', $url)->where('formats', 'audio')->first();

        if ($cache && $cache->file_id) {
            $media = InputMediaAudio::make($cache->file_id);
            $media->caption = __('messages.your_audio_file', [], $lang);
            $bot->editMessageMedia(media: $media, chat_id: $chatId, message_id: $messageId);
            return;
        }

        // Если нет в кеше — ставим в очередь
        $statusMsg = $bot->editMessageText(
            text: '⏳ ' . __('Messages.queued', [], $lang),
            chat_id: $chatId,
            message_id: $messageId
        );
        DownloadYouTubeAudioJob::dispatch(
            userId: $bot->userId(),
            url: $url,
            type: 'audio',
            messageId: $messageId,
            chatId: $chatId,
            statusMsgId: $statusMsg->message_id
        );
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
        $needed_heights = [480, 720, 1080, 1440, 2160];
        $keyboard = InlineKeyboardMarkup::make();

        // Сначала отсортируем и сгруппируем по height
        $grouped = collect($formats)
            ->filter(fn($f) => in_array($f['height'], $needed_heights))
            ->groupBy('height');

        foreach ($needed_heights as $height) {
            if (!isset($grouped[$height])) continue;

            // Берём самый «тяжёлый» формат с этим разрешением (если вдруг несколько)
            $best = collect($grouped[$height])
                ->sortByDesc('filesize')
                ->first();

            $itag = $best['itag'];
            $sizeMB = round($best['filesize'] / 1048576); // в мегабайтах
            $label = "{$sizeMB}MB | {$height}p";
            $callback = "yt:format:$itag:$hash";

            $keyboard->addRow(
                InlineKeyboardButton::make($label, callback_data: $callback)
            );
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
