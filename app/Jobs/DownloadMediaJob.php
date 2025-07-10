<?php

namespace App\Jobs;

use SergiX44\Nutgram\Nutgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Input\InputMediaAudio;
use SergiX44\Nutgram\Telegram\Types\Input\InputMediaVideo;
use SergiX44\Nutgram\Telegram\Types\Input\InputMediaPhoto;
use SergiX44\Nutgram\Telegram\Types\Input\InputMediaDocument;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;


class DownloadMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId, $url, $type, $messageId, $chatId, $statusMsgId;
    /**
     * Create a new job instance.
     */
    public function __construct($userId, $url, $type, $messageId, $chatId, $statusMsgId)
    {
        $this->userId = $userId;
        $this->url = $url;
        $this->type = $type;
        $this->messageId = $messageId;
        $this->chatId = $chatId;
        $this->statusMsgId = $statusMsgId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Log::info('DownloadMediaJob START', [
            'userId' => $this->userId,
            'url' => $this->url,
            'type' => $this->type,
            'messageId' => $this->messageId,
            'chatId' => $this->chatId,
            'statusMsgId' => $this->statusMsgId,
        ]);
        $bot = app(Nutgram::class);
        $lang = 'ru';
        if (method_exists($bot, 'user')) {
            $user = \App\Models\TelegramUser::where('user_id', $this->userId)->first();
            $lang = $user->language ?? 'ru';
        }
        $messageId = $this->messageId;
        $chatId = $this->chatId;
        $statusMsgId = $this->statusMsgId;
        $userQueueKey = "user_queue_{$this->userId}";
        $activeKey = "active_download_{$this->userId}";
        $statusKey = "download_status_{$this->userId}_" . md5($this->url);

        // Меняем статус на "Скачивание..."
        try {
            $bot->editMessageText('⏳ Скачивание...', chat_id: $chatId, message_id: $statusMsgId);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'message is not modified')) {
                Log::info("Telegram: сообщение не изменилось, ошибка подавлена.");
            } else {
                Log::error("Ошибка при редактировании: " . $e->getMessage());
                throw $e;
            }
        }

        // Проверяем кеш
        $cache = \App\Models\ContentCache::where('content_link', $this->url)->first();
        if ($cache && $cache->file_id) {
            $caption = '';
            switch ($this->type) {
                case 'instagram': $caption = __('messages.instagram_video_downloaded', [], $lang); break;
                case 'pinterest': $caption = __('messages.pinterest_video_downloaded', [], $lang); break;
                case 'tiktok': $caption = __('messages.tiktok_video_downloaded', [], $lang); break;
                case 'okcdn': $caption = '✅ Ваше видео с OK! @NinjaDownloaderBot'; break;
                // ... другие соцсети ...
            }
            try {
                if ($cache->formats === 'video') {
                    $bot->editMessageMedia(
                        new InputMediaVideo('video', $cache->file_id, $caption, 'HTML'),
                        chat_id: $chatId, message_id: $statusMsgId
                    );
                } elseif ($cache->formats === 'photo') {
                    $bot->editMessageMedia(
                        new InputMediaPhoto('photo', $cache->file_id, $caption, 'HTML'),
                        chat_id: $chatId, message_id: $statusMsgId
                    );
                } else {
                    $bot->editMessageMedia(
                        new InputMediaDocument('document', $cache->file_id, $caption, 'HTML'),
                        chat_id: $chatId, message_id: $statusMsgId
                    );
                }
            } catch (\Throwable $e) {
                $bot->deleteMessage($chatId, $statusMsgId);
                if ($cache->formats === 'video') {
                    $bot->sendVideo($cache->file_id, caption: $caption, chat_id: $chatId, reply_to_message_id: $messageId);
                } elseif ($cache->formats === 'photo') {
                    $bot->sendPhoto($cache->file_id, caption: $caption, chat_id: $chatId, reply_to_message_id: $messageId);
                } else {
                    $bot->sendDocument($cache->file_id, caption: $caption, chat_id: $chatId, reply_to_message_id: $messageId);
                }
            }
            // Удаляем active_download и двигаем очередь
            cache()->forget($activeKey);
            $queue = cache()->get($userQueueKey, []);
            if (!empty($queue)) {
                $next = array_shift($queue);
                cache()->put($userQueueKey, $queue, 600);
                $nextStatusKey = "download_status_{$this->userId}_" . md5($next['url']);
                try {
                    $bot->editMessageText('⏳ Скачивание...', chat_id: $next['chat_id'], message_id: cache()->get($nextStatusKey));
                } catch (\Throwable $e) {}
                cache()->put($activeKey, $next['url'], 600);
                \App\Jobs\DownloadMediaJob::dispatch(
                    $this->userId, $next['url'], $next['type'], $next['message_id'], $next['chat_id'], cache()->get($nextStatusKey)
                )->onQueue($next['type']);
            }
            return;
        }

        // Скачиваем
        try {
            $service = null;
            $caption = '';
            switch ($this->type) {
                case 'instagram':
                    $service = app(\App\Services\InstagramService::class);
                    $caption = __('messages.instagram_video_downloaded', [], $lang);
                    break;
                case 'tiktok':
                    $service = app(\App\Services\TikTokService::class);
                    $caption = __('messages.tiktok_video_downloaded', [], $lang);
                    break;
                case 'pinterest':
                    $service = app(\App\Services\PinterestService::class);
                    $caption = __('messages.pinterest_video_downloaded', [], $lang);
                    break;
                case 'vk':
                    $service = app(\App\Services\Vk::class);
                    $caption = __('messages.vk_video_downloaded', [], $lang);
                    break;
                case 'ok':
                    $service = app(\App\Services\OkService::class);
                    $caption = __('messages.ok_video_downloaded', [], $lang);
                    break;
                case 'facebook':
                    $service = app(\App\Services\FacebookService::class);
                    $caption = __('messages.facebook_video_downloaded', [], $lang);
                    break;
                case 'vimeo':
                    $service = app(\App\Services\VimeoService::class);
                    $caption = __('messages.vimeo_video_downloaded', [], $lang);
                    break;
                case 'x':
                    $service = app(\App\Services\X::class);
                    $caption = __('messages.x_video_downloaded', [], $lang);
                    break;
                case 'youtube':
                    $service = app(\App\Services\YouTubeService::class);
                    $caption = __('messages.youtube_video_downloaded', [], $lang);
                    break;
                default:
                    $service = null;
            }
            $result = $service ? $service->download($this->url) : null;
            if ($result && isset($result['paths'])) {
                // Только рилсы и сторис (видео)
                $media = [];
                foreach ($result['paths'] as $i => $path) {
                    $ext = strtolower($result['exts'][$i] ?? pathinfo($path, PATHINFO_EXTENSION));
                    // Только видео (mp4, mkv, webm)
                    if (in_array($ext, ['mp4', 'mkv', 'webm'])) {
                        $media[] = InputMediaVideo::make(InputFile::make($path));
                    }
                }
                if (!empty($media)) {
                    $bot->sendMediaGroup($media, chat_id: $chatId, reply_to_message_id: $messageId);
                } else {
                    $bot->editMessageText('В этом посте нет видео для скачивания.', chat_id: $chatId, message_id: $statusMsgId);
                }
            } elseif ($result && file_exists($result['path'])) {
                $ext = strtolower($result['ext']);
                // Только видео (mp4, mkv, webm)
                if (in_array($ext, ['mp4', 'mkv', 'webm'])) {
                    $msg = $bot->sendVideo(InputFile::make($result['path']), chat_id: $chatId, reply_to_message_id: $messageId);
                } else {
                    $bot->editMessageText('В этом посте нет видео для скачивания.', chat_id: $chatId, message_id: $statusMsgId);
                }
            } else {
                $bot->editMessageText('В этом посте нет видео для скачивания.', chat_id: $chatId, message_id: $statusMsgId);
            }
        } catch (\Throwable $e) {
            $bot->editMessageText('❌ Ошибка при скачивании поста.', chat_id: $chatId, message_id: $statusMsgId);
        }
        // Удаляем active_download и двигаем очередь
        cache()->forget($activeKey);
        $queue = cache()->get($userQueueKey, []);
        if (!empty($queue)) {
            $next = array_shift($queue);
            cache()->put($userQueueKey, $queue, 600);
            $nextStatusKey = "download_status_{$this->userId}_" . md5($next['url']);
            try {
                $bot->editMessageText('⏳ Скачивание...', chat_id: $next['chat_id'], message_id: cache()->get($nextStatusKey));
            } catch (\Throwable $e) {}
            cache()->put($activeKey, $next['url'], 600);
            \App\Jobs\DownloadMediaJob::dispatch(
                $this->userId, $next['url'], $next['type'], $next['message_id'], $next['chat_id'], cache()->get($nextStatusKey)
            )->onQueue($next['type']);
        }
    }
}
