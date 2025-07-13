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
use App\Jobs\{DownloadInstagramJob, DownloadTikTokJob, DownloadPinterestJob, DownloadXJob, DownloadYouTubeAudioJob, DownloadYouTubeJob};
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
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
        $lang = $bot->user()?->language ?? 'ru';
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(__('messages.btn_lang_ru', [], $lang), callback_data: 'lang:ru'),
                InlineKeyboardButton::make(__('messages.btn_lang_en', [], $lang), callback_data: 'lang:en'),
            );
        $bot->sendMessage(text: __('messages.choose_language', [], $lang),reply_markup: $keyboard);
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
        $bot->answerCallbackQuery(text: __('messages.language_selected', [], $lang));
        $bot->editMessageText(text: __('messages.language_selected', [], $lang));
    }

    public function handleLink(Nutgram $bot)
    {
        $text = $bot->message()?->text;
        $lang = $bot->user()?->language ?? 'ru';
        $messageId = $bot->message()->message_id;

        if (preg_match_all('~https?://\S+~', $text, $matches)) {
            $first = true;
            foreach ($matches[0] as $url) {
                $url = trim($url);
                if (preg_match('~instagram\.com~i', $url)) {
                    $this->downloadInstagram($bot, $url, $first);
                } elseif (preg_match('~tiktok\.com~i', $url)) {
                    $this->downloadTikTok($bot, $url, $first);
                } elseif (preg_match('~pinterest\.com|pin\.it~i', $url)) {
                    $this->downloadPinterest($bot, $url, $first);
                } elseif (preg_match('~x\.com|twitter\.com~i', $url)) {
                    $this->downloadX($bot, $url, $first);
                } elseif (preg_match('~(?:youtube\.com|youtu\.be)~i', $url)) {
                    $hash = md5($url);
                    cache()->put('yt_url_' . $hash, $url, now()->addMinutes(30));
                    $keyboard = InlineKeyboardMarkup::make()->addRow(
                        InlineKeyboardButton::make(__('messages.btn_audio', [], $lang), callback_data: "yt:audio:$hash"),
                        InlineKeyboardButton::make(__('messages.btn_video', [], $lang), callback_data: "yt:video:$hash"),
                    );
                    $bot->sendMessage(__('messages.choose_download_type', [], $lang), reply_markup: $keyboard, reply_to_message_id: $messageId);
                } else {
                    $bot->sendMessage(__('messages.unsupported_link', [], $lang), reply_to_message_id: $messageId);
                }
                $first = false;
            }
                    } else {
            // Только если пользователь уже есть в БД — показываем подсказку
            $exists = TelegramUser::where('user_id', $bot->userId())->get()->exists();
            if ($exists) {
                $bot->sendMessage(__('messages.send_link_hint', [], $lang), reply_to_message_id: $messageId);
            }
        }
    }

    public function downloadInstagram(Nutgram $bot, string $url): void
    {
        $lang = $bot->user()?->language ?? 'ru';
        $caption = __('messages.instagram_video_downloaded', [], $lang);
        $messageId = $bot->message()->message_id;
        $chatId = $bot->chatId();
        $cache = ContentCache::where('content_link', $url)->get()->first();

        if ($cache && $cache->file_id)
        {
            $bot->sendVideo($cache->file_id, caption: $caption, chat_id: $chatId, reply_to_message_id: $messageId);
            return;
        }
        $statusMsg = $bot->sendMessage(__('messages.queued', [], $lang), reply_to_message_id: $messageId);
        $statusMsgId = $statusMsg->message_id;
        DownloadInstagramJob::dispatch($bot->userId(), $url, 'instagram', $messageId, $chatId, $statusMsgId)->onQueue('instagram');
    }

    public function downloadTikTok(Nutgram $bot, string $url): void
    {
        $lang = $bot->user()?->language ?? 'ru';
        $caption = __('messages.tiktok_media_downloaded', [], $lang);
        $messageId = $bot->message()->message_id;
        $chatId = $bot->chatId();
        $cache = ContentCache::where('content_link', $url)->first();

        if ($cache && $cache->file_id)
        {
            $bot->sendVideo($cache->file_id, caption: $caption, chat_id: $chatId, reply_to_message_id: $messageId);
            return;
        }
        $statusMsg = $bot->sendMessage(__('messages.queued', [], $lang), reply_to_message_id: $messageId);
        $statusMsgId = $statusMsg->message_id;
        DownloadTikTokJob::dispatch($bot->userId(), $url, 'tiktok', $messageId, $chatId, $statusMsgId)->onQueue('tiktok');
    }

    public function downloadPinterest(Nutgram $bot, string $url): void
    {
        $lang = $bot->user()?->language ?? 'ru';
        $caption = __('messages.pinterest_media_downloaded', [], $lang);
        $messageId = $bot->message()->message_id;
        $chatId = $bot->chatId();
        $cache = ContentCache::where('content_link', $url)->get()->first();

        if ($cache && $cache->file_id)
        {
            $bot->sendVideo($cache->file_id, caption: $caption, chat_id: $chatId, reply_to_message_id: $messageId);
            return;
        }
        $statusMsg = $bot->sendMessage(__('messages.queued', [], $lang), reply_to_message_id: $messageId);
        $statusMsgId = $statusMsg->message_id;
        DownloadPinterestJob::dispatch($bot->userId(), $url, 'pinterest', $messageId, $chatId, $statusMsgId)->onQueue('pinterest');
    }


    public function downloadX(Nutgram $bot, string $url): void
    {
        $lang = $bot->user()?->language ?? 'ru';
        $caption = __('messages.x_video_downloaded', [], $lang);
        $messageId = $bot->message()->message_id;
        $chatId = $bot->chatId();
        $cache = ContentCache::where('content_link', $url)->get()->first();

        if ($cache && $cache->file_id)
        {
            $bot->sendVideo($cache->file_id, caption: $caption, chat_id: $chatId, reply_to_message_id: $messageId);
            return;
        }
        $statusMsg = $bot->sendMessage(__('messages.queued', [], $lang), reply_to_message_id: $messageId);
        $statusMsgId = $statusMsg->message_id;
        DownloadXJob::dispatch($bot->userId(), $url, 'x', $messageId, $chatId, $statusMsgId)->onQueue('x');
    }

    /**
     * Обработка выбора "Аудио" — скачивание и отправка
     */
    public function downloadYoutubeAudio(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;
        $lang = $this->getUserLang($bot);
        $messageId = $bot->callbackQuery()?->message?->message_id;
        $chatId = $bot->chatId();
        // Меняем текст на 'Обработка...'
        $bot->editMessageText(__('messages.processing', [], $lang), chat_id: $chatId, message_id: $messageId);
        if (!preg_match('~^yt:audio:([a-f0-9]+)$~', $data, $m))
        {
            $bot->sendMessage(__('messages.invalid_format', [], $lang));
            return;
        }
        $hash = $m[1];
        $url = cache()->get('yt_url_' . $hash);
        if (!$url)
        {
            $bot->sendMessage(__('messages.link_expired', [], $lang));
            return;
        }
        $cache = ContentCache::where('content_link', $url)->where('formats', 'audio')->get()->first();
        if ($cache && $cache->file_id)
        {
            $media = InputMediaAudio::make($cache->file_id);
            $media->caption = __('messages.your_audio_file', [], $lang);
            $bot->editMessageMedia(media: $media, chat_id: $chatId, message_id: $messageId);
            return;
        }
        $statusMsg = $bot->editMessageText(text: __('messages.queued', [], $lang),chat_id: $chatId,message_id: $messageId);
        DownloadYouTubeAudioJob::dispatch(userId: $bot->userId(),url: $url,type: 'audio',messageId: $messageId,chatId: $chatId,statusMsgId: $statusMsg->message_id)->onQueue('youtube-audio');
    }

    /**
     * Обработка выбора "Видео" — показ форматов (размер | качество)
     */
    public function downloadYoutubeVideo(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;
        $lang = $this->getUserLang($bot);
        $messageId = $bot->callbackQuery()?->message?->message_id;
        $chatId = $bot->chatId();
        $bot->editMessageText(__('messages.processing', [], $lang), chat_id: $chatId, message_id: $messageId);

        if (!preg_match('~^yt:video:([a-f0-9]+)$~', $data, $m))
        {
            $bot->sendMessage(__('messages.invalid_format', [], $lang));
            return;
        }
        $hash = $m[1];
        $url = cache()->get('yt_url_' . $hash);

        if (!$url)
        {
            $bot->sendMessage(__('messages.link_expired', [], $lang));
            return;
        }

        try {
        $formats = $this->yt_service->getAvailableFormats($url);
            Log::info('YouTube formats received: ' . count($formats));

            // Логируем
            foreach ($formats as $index => $format)
            {
                Log::info("Format {$index}: " . json_encode([
                    'height' => $format['height'] ?? 'unknown',
                    'size_str' => $format['size_str'] ?? 'unknown',
                    'format_string' => $format['format_string'] ?? 'unknown',
                ]));
            }

            if (empty($formats))
            {
                Log::warning('No YouTube formats found for URL: ' . $url);
                $bot->editMessageText(__('messages.no_video_found', [], $lang), chat_id: $chatId, message_id: $messageId);
                return;
            }
            $needed_heights = [480, 720, 1080, 1440, 2160];
        $keyboard = InlineKeyboardMarkup::make();
            $grouped = collect($formats)->filter(fn($f) => in_array($f['height'] ?? null, $needed_heights))->groupBy('height');
            $hasButtons = false;

            foreach ($needed_heights as $height)
            {
                if (!isset($grouped[$height]))
                {
                    continue;
                }
                $best = collect($grouped[$height])->sortByDesc('filesize')->first();
                $itag = $best['itag'] ?? null;
                $sizeStr = $best['size_str'] ?? 'unknown';

                if (!$itag)
                {
                continue;
                }
                $label = "{$sizeStr} | {$height}p";
                $callback = "yt:format:{$itag}:{$hash}";
                $keyboard->addRow(InlineKeyboardButton::make($label, callback_data: $callback));
                $hasButtons = true;
            }

            if ($hasButtons) {
                $bot->editMessageText(__('messages.select_video_format', [], $lang), reply_markup: $keyboard, chat_id: $chatId, message_id: $messageId);
            } else {
                Log::info('No preferred heights found, showing fallback formats');
                $fallbackKeyboard = InlineKeyboardMarkup::make();
                $fallbackFormats = collect($formats)->take(5);

                foreach ($fallbackFormats as $format)
                {
                    $height = $format['height'] ?? 'unknown';
                    $sizeStr = $format['size_str'] ?? 'unknown';
                    $itag = $format['itag'] ?? null;

                    if (!$itag)
                    {
                continue;
                    }
                    $label = "{$sizeStr} | {$height}p";
                    $callback = "yt:format:{$itag}:{$hash}";
                    $fallbackKeyboard->addRow(InlineKeyboardButton::make($label, callback_data: $callback));
                }

                if ($fallbackFormats->count() > 0) {
                    $bot->editMessageText(__('messages.select_video_format', [], $lang), reply_markup: $fallbackKeyboard, chat_id: $chatId, message_id: $messageId);
                } else {
                    $bot->editMessageText(__('messages.no_video_found', [], $lang), chat_id: $chatId, message_id: $messageId);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error getting YouTube formats: ' . $e->getMessage(), ['exception' => $e]);
            $bot->editMessageText(__('messages.post_download_error', [], $lang), chat_id: $chatId, message_id: $messageId);
        }
    }


    public function downloadYoutubeFormat(Nutgram $bot)
    {
        $bot->answerCallbackQuery();
        $data = $bot->callbackQuery()?->data;
        Log::info('YouTube format callback received: ' . $data);

        if (!preg_match('~^yt:format:(\d+):([a-f0-9]+)$~', $data, $m))
        {
            $lang = $this->getUserLang($bot);
            Log::warning('Invalid YouTube format callback pattern: ' . $data);
            $bot->sendMessage(__('messages.invalid_format', [], $lang));
            return;
        }
        $itag = $m[1];
        $hash = $m[2];
        $url = cache()->get('yt_url_' . $hash);
        $formatString = null;
        $formats = app(YouTubeService::class)->getAvailableFormats($url);
        $formatMatch = collect($formats)->firstWhere('itag', $itag);

        if ($formatMatch)
        {
            $formatString = $formatMatch['format_string'];
        }
        Log::info('YouTube format callback parsed: itag=' . $itag . ', hash=' . $hash . ', url=' . ($url ? 'found' : 'not found'));

        if (!$url)
        {
            $lang = $this->getUserLang($bot);
            $bot->sendMessage(__('messages.link_expired', [], $lang));
            return;
        }
        $lang = $this->getUserLang($bot);
        $chat_id = $bot->chatId();
        $message_id = $bot->callbackQuery()?->message?->message_id;
        $cache = ContentCache::where('content_link', $url)->where('formats', 'video')->where('quality', $itag)->get()->first();

        if ($cache && $cache->file_id)
        {
            $media = InputMediaVideo::make($cache->file_id);
            $media->caption = __('messages.your_video_file', [], $lang);
            $bot->editMessageMedia(media: $media, chat_id: $chat_id, message_id: $message_id);
            return;
        }
        $statusMsg = $bot->editMessageText(text: __('messages.queued', [], $lang),chat_id: $chat_id,message_id: $message_id);
        Log::info('Dispatching YouTube download job: itag=' . $itag . ', url=' . $url);
        DownloadYoutubeJob::dispatch(userId: $bot->userId(),url: $url,formatString: $formatString,messageId: $message_id,chatId: $chat_id,statusMsgId: $statusMsg->message_id)->onQueue('youtube');
    }
    protected function getUserLang(Nutgram $bot)
    {
        $user = TelegramUser::where('user_id', $bot->user()->id)->first();
        return $user->language ?? 'ru';
    }
}
