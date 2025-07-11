<?php

namespace App\Jobs;

use SergiX44\Nutgram\Nutgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Input\{InputMediaAudio, InputMediaVideo, InputMediaPhoto, InputMediaDocument};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Services\YouTubeService;
use App\Models\{TelegramUser, ContentCache};


class DownloadYouTubeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId, $url, $type, $messageId, $chatId, $statusMsgId;

    public function __construct($userId, $url, $type, $messageId, $chatId, $statusMsgId)
    {
        $this->userId = $userId;
        $this->url = $url;
        $this->type = $type;
        $this->messageId = $messageId;
        $this->chatId = $chatId;
        $this->statusMsgId = $statusMsgId;
    }

    public function handle(Nutgram $bot, YouTubeService $yt_service): void
    {
        $lang = $bot->user()?->language ?? 'ru';

        // 1. Проверка кеша
        $cache = ContentCache::where('content_link', $this->url)
            ->where('formats', 'audio')
            ->first();

        if ($cache && $cache->file_id) {
            $bot->editMessageMedia(
                InputMediaAudio::make($cache->file_id)->caption(__('messages.your_audio_file', [], $lang)),
                chat_id: $this->chatId,
                message_id: $this->statusMsgId
            );
            return;
        }

        // 2. Скачиваем
        $result = $yt_service->fetch($this->url, 'audio');
        if (
            !$result ||
            empty($result['path']) ||
            !file_exists($result['path']) ||
            filesize($result['path']) === 0
        ) {
            $bot->editMessageText(
                __('messages.post_download_error', [], $lang),
                chat_id: $this->chatId,
                message_id: $this->statusMsgId
            );
            return;
        }

        // 3. Отправка в кеш-канал
        $cacheChannel = config('nutgram.cache_channel');
        $file_id = null;
        $sent = null;
        try {
            $sent = $bot->sendAudio(
                InputFile::make($result['path']),
                chat_id: $cacheChannel,
                caption: __('messages.your_audio_file', [], $lang)
            );
            $file_id = $sent->audio?->file_id;
            Log::info('Файл для отправки в очередь', [
                'path' => $result['path'],
                'file_exists' => file_exists($result['path']),
                'filesize' => file_exists($result['path']) ? filesize($result['path']) : null,
            ]);

        } catch (\Throwable $e) {
            Log::warning('sendAudio to cache channel failed: ' . $e->getMessage());
        }

        // Кэшируем только если отправка прошла успешно
        if ($file_id) {
            ContentCache::updateOrCreate(
                ['content_link' => $this->url],
                [
                    'title'        => $result['title'] ?? 'Audio',
                    'formats'      => 'audio',
                    'chat_id'      => $cacheChannel,
                    'message_id'   => $sent?->message_id,
                    'file_id'      => $file_id,
                    'quality'      => 'audio',
                ]
            );
            $media = InputMediaAudio::make($file_id);
            $media->caption = __('messages.your_audio_file', [], $lang);
            $bot->editMessageMedia(
                media: $media,
                chat_id: $this->chatId,
                message_id: $this->statusMsgId
            );
        } else {
            $bot->editMessageText(
                __('messages.post_download_error', [], $lang),
                chat_id: $this->chatId,
                message_id: $this->statusMsgId
            );
        }
    }
}
