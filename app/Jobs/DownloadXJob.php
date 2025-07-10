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
use App\Services\X;
use App\Models\{TelegramUser, ContentCache};

class DownloadXJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId, $url, $type, $messageId, $chatId, $statusMsgId;

    /**
     * @var X
     */
    private X $x_service;

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
    public function handle(Nutgram $bot, X $x_service)
    {
        $lang = $bot->user()?->language ?? 'ru';
        $caption = __('Messages.instagram_video_downloaded', [], $lang);
        $result = $x_service->download($this->url);

        if (!$result) {
            $bot->editMessageText(__('Messages.no_video_found', [], $lang), chat_id: $this->chatId, message_id: $this->statusMsgId);
            return;
        }

        $paths = [];
        if (isset($result['path'])) {
            $paths[] = $result['path'];
        } elseif (isset($result['paths'])) {
            $paths = $result['paths'];
        }

        if (empty($paths)) {
            $bot->editMessageText(__('Messages.no_video_found', [], $lang), chat_id: $this->chatId, message_id: $this->statusMsgId);
            return;
        }

        $cacheChannel = config('nutgram.cache_channel');
        $fileId = null;
        $msg = null;

        foreach ($paths as $path) {
            try {
                // Загружаем в приватный канал
                $msg = $bot->sendVideo(
                    InputFile::make($path),
                    chat_id: $cacheChannel,
                );
                $fileId = $msg->video->file_id ?? $msg->document->file_id ?? $msg->animation->file_id ?? null;
                if ($fileId) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::error("Ошибка при отправке видео в приватный канал: " . $e->getMessage());
                continue;
            }
        }

        if ($fileId) {
            // Кэшируем file_id
            ContentCache::updateOrCreate(
                ['content_link' => $this->url],
                [
                    'file_id' => $fileId,
                    'formats' => 'video',
                    'title' => md5($this->url),
                    'quality' => 'video',
                    'chat_id' => $cacheChannel,
                    'message_id' => $msg?->message_id,
                ]
            );
            $bot->editMessageMedia(
                media: InputMediaVideo::make($fileId, caption: $caption),
                chat_id: $this->chatId,
                message_id: $this->statusMsgId
            );
        } else {
            $bot->editMessageText('❌ ' . __('Messages.download_error', [], $lang), chat_id: $this->chatId, message_id: $this->statusMsgId);
        }
    }
}
