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

class DownloadYoutubeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId, $url, $formatString, $messageId, $chatId, $statusMsgId;

    public function __construct($userId, $url, $formatString, $messageId, $chatId, $statusMsgId)
    {
        $this->userId = $userId;
        $this->url = $url;
        $this->formatString = $formatString;
        $this->messageId = $messageId;
        $this->chatId = $chatId;
        $this->statusMsgId = $statusMsgId;
    }

    public function handle(Nutgram $bot, YouTubeService $yt_service): void
    {
        $user = TelegramUser::where('user_id', $this->userId)->first();
        $lang = $user->language ?? 'ru';
        $bot->editMessageText(__('messages.downloading', [], $lang),chat_id: $this->chatId,message_id: $this->statusMsgId);
        $formats = $yt_service->getAvailableFormats($this->url);
        $formatMeta = collect($formats)->firstWhere('format_string', $this->formatString);
        $heightText = 'unknown';
        $sizeStr = 'unknown';

        if ($formatMeta)
        {
            $heightText = $formatMeta['height'] . 'p';
            $sizeStr = $formatMeta['size_str'];
        }

        // Если не удалось, fallback на ID
        if ($heightText === 'unknown' && strpos($this->formatString, '+') !== false) {
            $videoFormat = explode('+', $this->formatString)[0];
            $heightText = [
                '18' => '360p',
                '135' => '480p',
                '136' => '720p',
                '137' => '1080p',
                '271' => '1440p',
                '313' => '2160p',
            ][$videoFormat] ?? "{$videoFormat}p";
        }
        $caption = "📺 {$heightText}  💾 {$sizeStr} | " . __('messages.your_video_file', [], $lang);
        $cache = ContentCache::where('content_link', $this->url)
            ->where('formats', 'video')->where('quality', $this->formatString)->first();

        if ($cache && $cache->file_id)
        {
            $media = InputMediaVideo::make($cache->file_id);
            $media->caption = $caption;
            $bot->editMessageMedia(media: $media,chat_id: $this->chatId,message_id: $this->statusMsgId);
            return;
        }
        Log::info('YT Download: format_string = ' . $this->formatString);
        $result = $yt_service->fetch($this->url, 'video', $this->formatString);

        if (!$result || empty($result['path']) || !file_exists($result['path']) || filesize($result['path']) === 0)
        {
            $bot->editMessageText(__('messages.post_download_error', [], $lang),chat_id: $this->chatId,message_id: $this->statusMsgId);
            return;
        }
        $cacheChannel = config('nutgram.cache_channel');
        $file_id = null;
        $sent = null;

        try {
            $sent = $bot->sendVideo(InputFile::make($result['path']),chat_id: $cacheChannel);
            $file_id = $sent->video?->file_id;
        } catch (\Throwable $e) {
            Log::warning('sendVideo to cache channel failed: ' . $e->getMessage());
        }

        if ($file_id)
        {
            ContentCache::updateOrCreate(
                ['content_link' => $this->url, 'quality' => $this->formatString],
                [
                    'title'      => $result['title'] ?? 'Video',
                    'formats'    => 'video',
                    'chat_id'    => $cacheChannel,
                    'message_id' => $sent?->message_id,
                    'file_id'    => $file_id,
                    'quality'    => $this->formatString,
                ]
            );
        }
        ContentCache::flushQueryCache(['content_cache']);
        $media = $file_id ? InputMediaVideo::make($file_id) : InputMediaVideo::make(InputFile::make($result['path']));
        $media->caption = $caption;

        try {
            $bot->editMessageMedia(media: $media,chat_id: $this->chatId,message_id: $this->statusMsgId);
        } catch (\Throwable $e) {
            Log::warning('Ошибка при отправке видео пользователю: ' . $e->getMessage());
            $bot->editMessageText(__('messages.post_download_error', [], $lang),chat_id: $this->chatId,message_id: $this->statusMsgId);
        }

        // Очищаем временные файлы
        if (isset($result['temp_dir'])) {
            $yt_service->cleanup();
        }
    }
}
