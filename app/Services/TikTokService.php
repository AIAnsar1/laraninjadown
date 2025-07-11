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

        // Удалим старые файлы (старше 10 минут)
        collect(glob($outputPath . '/*'))->each(function ($file) {
            if (filemtime($file) < now()->subMinutes(10)->getTimestamp()) {
                unlink($file);
            }
        });

        $filenameTemplate = '%(id)s.%(ext)s'; // безопаснее и короче
        $cookiesPath = storage_path('cookies.txt');

        $command = [
            $this->ytBin,
            '--no-warnings',
            '--quiet',
            '--restrict-filenames',
            '--no-playlist',
            '--external-downloader=aria2c',
            '--external-downloader-args=aria2c:-x 16 -k 1M',
            '--user-agent=Mozilla/5.0 (Linux; Android 10; SM-G970F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            '-f', 'best', // безопасный и универсальный
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

        // Найти последние файлы за последние 2 минуты
        $files = glob($outputPath . '/*');
        logger()->info('yt-dlp output dir', ['files' => $files]);

        $latestFiles = collect($files)
            ->map(fn($path) => ['path' => $path, 'time' => filemtime($path)])
            ->sortByDesc('time')
            ->filter(fn($file) => now()->timestamp - $file['time'] <= 120)
            ->pluck('path')
            ->values();

        if ($latestFiles->isEmpty()) {
            logger()->warning("yt-dlp не вернул файл для URL: $url");
            return false;
        }

        $filePath = $latestFiles->first();

        logger()->info('yt-dlp latest file', ['file' => $filePath]);

        return [
            'path'     => $filePath,
            'title'    => basename($filePath),
            'ext'      => pathinfo($filePath, PATHINFO_EXTENSION),
            'url'      => $url,
            'tt_type'  => 'video',
        ];
    }
}
