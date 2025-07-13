<?php

namespace App\Services;

use App\Traits\TempDirectoryTrait;
use YoutubeDl\{YoutubeDl, Options};
use YoutubeDl\Exception\YoutubeDlException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class TikTokService extends BaseService
{
    use TempDirectoryTrait;

    protected string $ytBin;

    public function __construct()
    {
        $this->ytBin = '/usr/local/bin/yt-dlp';
    }

    public function download(string $url): array|false
    {
        // Создаем временную директорию
        $this->createTempDirectory('tiktok');
        $outputPath = $this->getTempDirectory();

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

        Log::info('yt-dlp command', ['command' => implode(' ', $command)]);

        $process = new Process($command);
        $process->run();

        Log::info('yt-dlp stdout', ['stdout' => $process->getOutput()]);
        Log::info('yt-dlp stderr', ['stderr' => $process->getErrorOutput()]);

        if (!$process->isSuccessful()) {
            Log::error("yt-dlp failed: " . $process->getErrorOutput());
            $this->cleanupTempDirectory();
            return false;
        }

        // Найти последние файлы за последние 2 минуты
        $files = glob($outputPath . '/*');
        Log::info('yt-dlp output dir', ['files' => $files]);

        $latestFiles = collect($files)
            ->map(fn($path) => ['path' => $path, 'time' => filemtime($path)])
            ->sortByDesc('time')
            ->filter(fn($file) => now()->timestamp - $file['time'] <= 120)
            ->pluck('path')
            ->values();

        if ($latestFiles->isEmpty()) {
            Log::warning("yt-dlp не вернул файл для URL: $url");
            $this->cleanupTempDirectory();
            return false;
        }

        $filePath = $latestFiles->first();

        Log::info('yt-dlp latest file', ['file' => $filePath]);

        return [
            'path'     => $filePath,
            'title'    => basename($filePath),
            'ext'      => pathinfo($filePath, PATHINFO_EXTENSION),
            'url'      => $url,
            'tt_type'  => 'video',
            'temp_dir' => $this->tempDir, // Передаем путь к временной директории для последующей очистки
        ];
    }

    /**
     * Очищает временные файлы после использования
     */
    public function cleanup(): void
    {
        $this->cleanupTempDirectory();
    }
}
