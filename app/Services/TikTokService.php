<?php

namespace App\Services;


use App\Services\BaseService;
use YoutubeDl\{YoutubeDl, Options};
use YoutubeDl\Exception\YoutubeDlException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class TikTokService extends BaseService
{
    protected string $ytBin;

    public function __construct()
    {
        $this->ytBin = '/usr/local/bin/yt-dlp';
    }

    public function download(string $url): array|false
    {
        $outputPath = storage_path('app/tiktok');

        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
            logger()->info("Создана папка для загрузки: $outputPath");
        }

        $filenameTemplate = '%(title)s.%(ext)s';
        $cookiesPath = storage_path('cookies.txt');

        $command = [
            $this->ytBin,
            '--no-warnings',
            '--quiet',
            '--restrict-filenames',
            '--no-playlist',
            '--external-downloader=aria2c',
            '--external-downloader-args=aria2c:-x 16 -k 1M',
            '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
            '-f', 'mp4',
            '-o', $outputPath . '/' . $filenameTemplate,
            $url,
        ];

        if (file_exists($cookiesPath)) {
            $command[] = '--cookies=' . $cookiesPath;
        }

        logger()->info('yt-dlp command', ['command' => implode(' ', $command)]);

        $process = new Process($command);
        $process->run();

        logger()->info('yt-dlp stdout', ['stdout' => $process->getOutput()]);
        logger()->info('yt-dlp stderr', ['stderr' => $process->getErrorOutput()]);

        if (!$process->isSuccessful()) {
            logger()->error("yt-dlp failed: " . $process->getErrorOutput());
            return false;
        }

        $files = glob($outputPath . '/*');
        logger()->info('yt-dlp output dir', ['files' => $files]);

        $latestFile = collect($files)
            ->map(fn($path) => ['path' => $path, 'time' => filemtime($path)])
            ->sortByDesc('time')
            ->first();

        if (!$latestFile) {
            logger()->warning("yt-dlp не вернул файл для URL: $url. Содержимое папки:", $files);
            return false;
        }

        logger()->info('yt-dlp latest file', ['file' => $latestFile]);

        return [
            'path' => $latestFile['path'],
            'title' => basename($latestFile['path']),
            'ext'   => pathinfo($latestFile['path'], PATHINFO_EXTENSION),
            'url'   => $url,
            'tt_type' => 'video',
        ];
    }
}
