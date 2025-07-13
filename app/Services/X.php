<?php

namespace App\Services;

use App\Traits\TempDirectoryTrait;
use YoutubeDl\{YoutubeDl, Options};
use YoutubeDl\Exception\YoutubeDlException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class X extends BaseService
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
        $this->createTempDirectory('x');
        $outputPath = $this->getTempDirectory();

        $filenameTemplate = '%(title)s.%(ext)s';
        $cookiesPath = storage_path('cookies_x.txt'); // если авторизация нужна

        $command = [
            $this->ytBin,
            '--no-warnings',
            '--quiet',
            '--restrict-filenames',
            '--no-playlist',
            '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
            '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/mp4',
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

        $files = glob($outputPath . '/*');
        Log::info('yt-dlp output dir', ['files' => $files]);

        $latestFile = collect($files)
            ->map(fn($path) => ['path' => $path, 'time' => filemtime($path)])
            ->sortByDesc('time')
            ->first();

        if (!$latestFile) {
            Log::warning("yt-dlp не вернул файл для URL: $url. Содержимое папки:", $files);
            $this->cleanupTempDirectory();
            return false;
        }

        return [
            'paths' => [$latestFile['path']],
            'exts' => [pathinfo($latestFile['path'], PATHINFO_EXTENSION)],
            'type' => 'video',
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
