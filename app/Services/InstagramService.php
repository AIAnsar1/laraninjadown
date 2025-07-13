<?php

namespace App\Services;

use App\Traits\TempDirectoryTrait;
use Illuminate\Support\Facades\Log;

class InstagramService extends BaseService
{
    use TempDirectoryTrait;

    protected string $ytBin;

    public function __construct()
    {
        $this->ytBin = '/usr/local/bin/yt-dlp';
    }

    public function download($url)
    {
        // Создаем временную директорию
        $this->createTempDirectory('instagram');
        $outputDir = $this->getTempDirectory();

        // 📌 Уникальное имя для каждой ссылки
        $hash = md5($url);
        $outputTemplate = "{$outputDir}/{$hash}.%(ext)s";

        // 📥 Скачиваем
        $cmd = "{$this->ytBin} --no-warnings --quiet --restrict-filenames --no-playlist --external-downloader=aria2c --external-downloader-args='-x 16 -k 1M' '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0' -o '{$outputTemplate}' '{$url}'";
        exec($cmd, $out, $code);

        if ($code !== 0) {
            Log::error("yt-dlp вернул код $code при скачивании $url");
            $this->cleanupTempDirectory();
            return null;
        }

        // 📁 Находим скачанные файлы
        $files = glob("{$outputDir}/{$hash}.*");

        if (empty($files)) {
            $this->cleanupTempDirectory();
            return null;
        }

        // 🎥 Оставляем только видео
        $videoFiles = array_filter($files, function ($file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            return in_array($ext, ['mp4', 'mkv', 'webm']);
        });

        if (empty($videoFiles)) {
            $this->cleanupTempDirectory();
            return null;
        }

        if (count($videoFiles) === 1) {
            $path = $videoFiles[0];
            return [
                'path' => $path,
                'ext' => pathinfo($path, PATHINFO_EXTENSION),
                'type' => 'video',
                'title' => $hash,
                'temp_dir' => $this->tempDir, // Передаем путь к временной директории для последующей очистки
            ];
        }

        // 📦 Несколько файлов
        return [
            'paths' => array_values($videoFiles),
            'exts' => array_map(function ($file) {
                return pathinfo($file, PATHINFO_EXTENSION);
            }, $videoFiles),
            'types' => array_fill(0, count($videoFiles), 'video'),
            'title' => $hash,
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
