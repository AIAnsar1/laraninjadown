<?php

namespace App\Services;


use App\Services\BaseService;
use YoutubeDl\YoutubeDl;
use YoutubeDl\Exception\YoutubeDlException;
use YoutubeDl\Options;
use YoutubeDl\Entity\Video;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class YouTubeService extends BaseService
{
    private YoutubeDl $yt;
    private string $downloadPath;

    public function __construct()
    {
        $this->yt = new YoutubeDl();
        // путь к yt‑dlp (лучше брать из .env)
        $this->yt->setBinPath(config('services.ytdlp.bin', '/usr/local/bin/yt-dlp'));

        $this->downloadPath = storage_path('app/youtube');
    }

    /**
     * Скачивает аудио или видео.
     *
     * @param string      $url     ссылка на ролик
     * @param 'audio'|'video' $type    тип контента
     * @param string|null $format  mp3/mp4/webm/«best» — любой spec yt‑dlp
     * @return array|null
     */
    public function fetch(string $url, string $type = 'video', ?string $format = null): ?array
    {
        try {
            $opts = Options::create()
                ->downloadPath($this->downloadPath)
                ->output('%(title)s.%(ext)s')
                ->url($url);

            if ($type === 'audio') {
                $opts = $opts
                    ->extractAudio(true)
                    ->audioFormat($format ?? 'mp3')
                    ->audioQuality('0');           // «best»
            } else { // video
                if ($format) {
                    $opts = $opts->format($format); // пример: 'mp4' или 'bestvideo+bestaudio'
                }
            }

            /** @var Video $video */
            $video = $this->yt->download($opts)->getVideos()[0];

            if ($video->getError()) {
                throw new \RuntimeException($video->getError());
            }

            return [
                'path'   => $video->getFile()->getRealPath(),
                'title'  => $video->getTitle(),
                'ext'    => $video->getExt(),
                'url'    => $url,
                'type'   => $type,
                'size'   => $video->getFilesize(), // bytes
            ];
        } catch (\Throwable $e) {
            report($e); // Laravel helper
            return null;
        }
    }

    /**
     * Получить доступные форматы видео (mp4 с высотой)
     * @param string $url
     * @return array
     */
    public function getAvailableFormats(string $url): array
    {
        try {
            $video = $this->yt->download(
                Options::create()
                    ->url($url)
                    ->downloadPath(storage_path('app/temp')) // обязательно!
                    ->skipDownload(true)
                    ->output('%(title)s.%(ext)s')
            )->getVideos()[0];

            if ($video->getError()) {
                Log::error('YT ERROR: ' . $video->getError());
                return [];
            }

            $formats = [];
            foreach ($video->getFormats() as $format) {
                if (!$format->getFormatId()) continue;

                $formats[] = [
                    'itag'     => $format->getFormatId(),
                    'height'   => $format->getHeight() ?? 0,
                    'filesize' => $format->getFilesize() ?? 0,
                ];
            }


            return $formats;
        } catch (\Throwable $e) {
            Log::error('getAvailableFormats EXCEPTION: ' . $e->getMessage());
            return [];
        }
    }

    public function download(string $url): array|false
    {
        $outputPath = storage_path('app/youtube');
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
            logger()->info("Создана папка для загрузки: $outputPath");
        }

        // Приведение shorts → watch?v=
        if (str_contains($url, 'youtube.com/shorts/')) {
            $url = preg_replace('#shorts/([^?/]+)#', 'watch?v=$1', $url);
        }

        // Очистка предыдущих .json-файлов
        foreach (glob($outputPath . '/*.json') as $f) {
            unlink($f);
        }

        $filenameTemplate = '%(title)s.%(ext)s';
        $cookiesPath = storage_path('cookies.txt');
        $command = [
            '/usr/local/bin/yt-dlp',
            '--no-warnings',
            '--quiet',
            '--restrict-filenames',
            '--no-playlist',
            '--merge-output-format', 'mp4',
            '--external-downloader=aria2c',
            '--external-downloader-args=aria2c:-x 16 -k 1M',
            '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
            '-o', $outputPath . '/' . $filenameTemplate,
            $url,
        ];

        if (file_exists($cookiesPath)) {
            $command[] = '--cookies=' . $cookiesPath;
        }

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            logger()->error("yt-dlp failed: " . $process->getErrorOutput());
            return false;
        }

        // Получаем только медиа (а не json-файлы)
        $files = array_filter(glob($outputPath . '/*'), fn($f) => !str_ends_with($f, '.json'));

        $latestFile = collect($files)
            ->map(fn($path) => ['path' => $path, 'time' => filemtime($path)])
            ->sortByDesc('time')
            ->first();

        if (!$latestFile) {
            return false;
        }

        return [
            'path' => $latestFile['path'],
            'title' => basename($latestFile['path']),
            'ext' => pathinfo($latestFile['path'], PATHINFO_EXTENSION),
            'url' => $url,
            'yt_type' => 'video',
        ];
    }


    /**
     * Проверка, является ли ссылка YouTube/Shorts
     */
    public function isYoutubeUrl(string $url): bool
    {
        return preg_match('~^https?://(www\.)?(youtube\.com|youtu\.be)/~i', $url);
    }
}
