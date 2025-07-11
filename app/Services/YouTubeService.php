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
    protected string $ytBin;
    protected string $downloadPath;

    public function __construct()
    {
        $this->ytBin = config('services.ytdlp.bin', '/usr/local/bin/yt-dlp');
        $this->downloadPath = storage_path('app/youtube');
    }

    /**
     * Скачивает аудио или видео
     */
    public function fetch(string $url, string $type = 'video', ?string $format = null): array|false
    {
        if (!is_dir($this->downloadPath)) {
            mkdir($this->downloadPath, 0777, true);
        }

        $filenameTemplate = '%(title)s.%(ext)s';
        $outputPath = $this->downloadPath;

        $command = [
            $this->ytBin,
            '--no-warnings',
            '--quiet',
            '--no-playlist',
            '--restrict-filenames',
            '--merge-output-format', 'mp4',
            '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
            '-o', "$outputPath/$filenameTemplate",
        ];

        // Аудио или видео
        if ($type === 'audio') {
            $command[] = '--extract-audio';
            $command[] = '--audio-format=' . ($format ?? 'mp3');
            $command[] = '--audio-quality=0';
        } else {
            if ($format) {
                $command[] = '-f';
                $command[] = $format; // например: 'bestvideo[height=720]+bestaudio/best'
            }
        }

        $command[] = $url;

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            logger()->error("yt-dlp failed: " . $process->getErrorOutput());
            return false;
        }

        // Найдём последний скачанный файл
        $files = array_filter(glob("$outputPath/*"), fn($f) => !str_ends_with($f, '.json'));

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
            'type' => $type,
            'url' => $url,
        ];
    }

    /**
     * Получить доступные форматы: height + размер
     */
    public function getAvailableFormats(string $url): array
    {
        $command = [
            $this->ytBin,
            '--no-warnings',
            '--skip-download',
            '--print-json',
            '--no-playlist',
            '--restrict-filenames',
            '--user-agent=Mozilla/5.0',
            $url
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            logger()->error("yt-dlp format list failed: " . $process->getErrorOutput());
            return [];
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);
        if (!is_array($data) || !isset($data['formats'])) {
            return [];
        }

        $formats = [];

        foreach ($data['formats'] as $format) {
            $itag = $format['format_id'] ?? null;
            $height = $format['height'] ?? null;
            $filesize = $format['filesize'] ?? null;

            if ($itag && $height && $filesize) {
                $formats[] = [
                    'itag' => $itag,
                    'height' => $height,
                    'filesize' => $filesize,
                ];
            }
        }

        return $formats;
    }

    public function isYoutubeUrl(string $url): bool
    {
        return preg_match('~^https?://(www\.)?(youtube\.com|youtu\.be)/~i', $url);
    }
}
