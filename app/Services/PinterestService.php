<?php

namespace App\Services;

use App\Traits\TempDirectoryTrait;
use YoutubeDl\{YoutubeDl, Options};
use YoutubeDl\Exception\YoutubeDlException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PinterestService extends BaseService
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
        $this->createTempDirectory('pinterest');
        $outputDir = $this->getTempDirectory();

        $outputTemplate = $outputDir . '/%(title)s.%(ext)s';
        $cookiesPath = storage_path('cookies.txt');

        $cmd = [
            $this->ytBin,
            '--no-warnings',
            '--quiet',
            '--restrict-filenames',
            '--no-playlist',
            '--external-downloader=aria2c',
            '--external-downloader-args=aria2c:-x 16 -k 1M',
            '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
            '-o', $outputTemplate,
            $url,
        ];

        if (file_exists($cookiesPath)) {
            $cmd[] = '--cookies=' . $cookiesPath;
        }

        Log::info('yt-dlp command', ['command' => implode(' ', $cmd)]);

        $process = new \Symfony\Component\Process\Process($cmd);
        $process->run();

        Log::info('yt-dlp stdout', ['stdout' => $process->getOutput()]);
        Log::info('yt-dlp stderr', ['stderr' => $process->getErrorOutput()]);

        if (!$process->isSuccessful()) {
            Log::error("yt-dlp failed: " . $process->getErrorOutput());
            $this->cleanupTempDirectory();
            return false;
        }

        $files = glob($outputDir . '/*');

        // Фильтруем файлы, созданные за последние 2 минуты
        $latestTime = time();
        $recentFiles = array_filter($files, function ($file) use ($latestTime) {
            return $latestTime - filemtime($file) <= 120;
        });

        // Оставляем только видео/изображения
        $mediaFiles = array_filter($recentFiles, function ($file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            return in_array($ext, ['mp4', 'mkv', 'webm', 'jpg', 'jpeg', 'png']);
        });

        if (empty($mediaFiles)) {
            Log::warning("yt-dlp не вернул медиа-файлов для: $url");
            $this->cleanupTempDirectory();
            return false;
        }

        // Один файл
        if (count($mediaFiles) === 1) {
            $path = array_values($mediaFiles)[0];
            return [
                'path' => $path,
                'ext' => pathinfo($path, PATHINFO_EXTENSION),
                'type' => $this->detectType($path),
                'title' => pathinfo($path, PATHINFO_FILENAME),
                'url' => $url,
                'temp_dir' => $this->tempDir, // Передаем путь к временной директории для последующей очистки
            ];
        }

        // Несколько файлов
        $paths = [];
        $exts = [];
        foreach ($mediaFiles as $file) {
            $paths[] = $file;
            $exts[] = pathinfo($file, PATHINFO_EXTENSION);
        }

        return [
            'paths' => $paths,
            'exts' => $exts,
            'types' => array_map([$this, 'detectType'], $paths),
            'title' => Str::slug(pathinfo($paths[0], PATHINFO_FILENAME)),
            'url' => $url,
            'temp_dir' => $this->tempDir, // Передаем путь к временной директории для последующей очистки
        ];
    }

    private function detectType(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mkv', 'webm']) ? 'video' : 'photo';
    }

    /**
     * Очищает временные файлы после использования
     */
    public function cleanup(): void
    {
        $this->cleanupTempDirectory();
    }
}

