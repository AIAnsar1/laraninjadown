<?php

namespace App\Services;


use App\Services\BaseService;
use YoutubeDl\Options;
use YoutubeDl\YoutubeDl;
use YoutubeDl\Exception\YoutubeDlException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;




class InstagramService extends BaseService
{
    protected string $ytBin;

    public function __construct()
    {
        $this->ytBin = '/usr/local/bin/yt-dlp';
    }

    public function download($url)
    {
        $outputDir = storage_path('app/instagram');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // 📌 Уникальное имя для каждой ссылки
        $hash = md5($url);
        $outputTemplate = "{$outputDir}/{$hash}.%(ext)s";

        // 📥 Скачиваем
        $cmd = "{$this->ytBin} --no-warnings --quiet --restrict-filenames --no-playlist --external-downloader=aria2c --external-downloader-args='-x 16 -k 1M' -o '{$outputTemplate}' '{$url}'";
        exec($cmd, $out, $code);

        if ($code !== 0) {
            \Log::error("yt-dlp вернул код $code при скачивании $url");
            return null;
        }

        // 📁 Находим скачанные файлы
        $files = glob("{$outputDir}/{$hash}.*");

        if (empty($files)) {
            return null;
        }

        // 🎥 Оставляем только видео
        $videoFiles = array_filter($files, function ($file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            return in_array($ext, ['mp4', 'mkv', 'webm']);
        });

        if (empty($videoFiles)) {
            return null;
        }

        if (count($videoFiles) === 1) {
            $path = $videoFiles[0];
            return [
                'path' => $path,
                'ext' => pathinfo($path, PATHINFO_EXTENSION),
                'type' => 'video',
                'title' => $hash,
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
        ];
    }
}
