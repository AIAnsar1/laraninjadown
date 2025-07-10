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
        $outputTemplate = $outputDir . '/%(title)s.%(ext)s';
        $cmd = "/usr/local/bin/yt-dlp --no-warnings --quiet --restrict-filenames --no-playlist --external-downloader=aria2c --external-downloader-args='-x 16 -k 1M' -o '{$outputTemplate}' '{$url}'";
        exec($cmd, $out, $code);
        if ($code !== 0) {
            return null;
        }
        $files = glob($outputDir . '/*');
        $latestFiles = [];
        $latestTime = 0;
        foreach ($files as $file) {
            if (filemtime($file) > $latestTime) {
                $latestTime = filemtime($file);
            }
        }
        foreach ($files as $file) {
            if ($latestTime - filemtime($file) <= 120) {
                $latestFiles[] = $file;
            }
        }
        // Оставляем только видео-файлы
        $videoFiles = [];
        foreach ($latestFiles as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'mkv', 'webm'])) {
                $videoFiles[] = $file;
            }
        }
        if (empty($videoFiles)) {
            return null;
        }
        if (count($videoFiles) === 1) {
            $path = $videoFiles[0];
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            return [
                'path' => $path,
                'ext' => $ext,
                'type' => 'video',
            ];
        }
        $paths = [];
        $exts = [];
        foreach ($videoFiles as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $paths[] = $file;
            $exts[] = $ext;
        }
        return [
            'paths' => $paths,
            'exts' => $exts,
            'types' => array_fill(0, count($paths), 'video'),
        ];
    }
}
