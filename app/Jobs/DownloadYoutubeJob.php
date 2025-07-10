<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;

class DownloadYoutubeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId, $url, $messageId, $chatId, $statusMsgId;

    public function __construct($userId, $url, $messageId, $chatId, $statusMsgId)
    {
        $this->userId = $userId;
        $this->url = $url;
        $this->messageId = $messageId;
        $this->chatId = $chatId;
        $this->statusMsgId = $statusMsgId;
    }

    public function handle()
    {
        $bot = app(Nutgram::class);
        $userQueueKey = "user_queue_{$this->userId}";
        $activeKey = "active_download_{$this->userId}";
        $statusKey = "download_status_{$this->userId}_" . md5($this->url);
        $bot->editMessageText('⏳ Скачивание...', chat_id: $this->chatId, message_id: $this->statusMsgId);
        $service = app(\App\Services\YouTubeService::class);
        $result = $service->download($this->url);
        if ($result && file_exists($result['path'])) {
            $bot->editMessageMedia(
                \SergiX44\Nutgram\Telegram\Types\InputMedia\InputMediaVideo::make()
                    ->media(new InputFile($result['path']))
                    ->caption('Ваше видео готово!'),
                chat_id: $this->chatId,
                message_id: $this->statusMsgId
            );
            @unlink($result['path']);
        } else {
            $bot->editMessageText('❌ Ошибка при скачивании видео.', chat_id: $this->chatId, message_id: $this->statusMsgId);
        }
        // Удаляем active_download
        cache()->forget($activeKey);
        // Берём следующую ссылку из очереди
        $queue = cache()->get($userQueueKey, []);
        if (!empty($queue)) {
            $next = array_shift($queue);
            cache()->put($userQueueKey, $queue, 600);
            $nextStatusKey = "download_status_{$this->userId}_" . md5($next['url']);
            // Меняем статус на "Скачивание..."
            $bot->editMessageText('⏳ Скачивание...', chat_id: $next['chat_id'], message_id: cache()->get($nextStatusKey));
            // Ставим active_download
            cache()->put($activeKey, $next['url'], 600);
            // Диспатчим следующий Job
            \App\Jobs\DownloadYoutubeJob::dispatch(
                $this->userId, $next['url'], $next['message_id'], $next['chat_id'], cache()->get($nextStatusKey)
            )->onQueue('youtube');
        }
    }
}
