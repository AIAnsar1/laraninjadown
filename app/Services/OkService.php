<?php

namespace App\Services;


use App\Services\BaseService;
use YoutubeDl\{YoutubeDl, Options};
use YoutubeDl\Exception\YoutubeDlException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class OkService extends BaseService
{
    protected string $ytBin;

    public function __construct()
    {
        $this->ytBin = '/usr/local/bin/yt-dlp';
    }

    public function download(string $url): array|false
    {
        $outputPath = sys_get_temp_dir();
        $filenameTemplate = '%(title)s.%(ext)s';
        $cookiesPath = storage_path('cookies.txt');
        $type = 'video';

        // Сначала получим имя итогового файла
        $printCmd = [
            $this->ytBin,
            '--no-playlist',
            '--restrict-filenames',
            '--merge-output-format', 'mp4',
            '--print', 'filename',
            '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
            '-o', $filenameTemplate,
            $url,
        ];

        if (file_exists($cookiesPath)) {
            $printCmd[] = '--cookies=' . $cookiesPath;
        }

        $printProcess = new Process($printCmd, $outputPath);
        $printProcess->run();

        if (!$printProcess->isSuccessful()) {
            logger()->error('yt-dlp --print filename failed', ['stderr' => $printProcess->getErrorOutput()]);
            return false;
        }

        $filename = trim($printProcess->getOutput());
        $fullPath = $outputPath . '/' . $filename;

        // Теперь качаем файл
        $command = [
            $this->ytBin,
            '--no-warnings',
            '--quiet',
            '--restrict-filenames',
            '--no-playlist',
            '--merge-output-format', 'mp4',
            '--external-downloader=aria2c',
            '--external-downloader-args=aria2c:-x 16 -k 1M',
            '-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
            '-o', $filename,
            $url,
        ];

        if (file_exists($cookiesPath)) {
            $command[] = '--cookies=' . $cookiesPath;
        }

        $process = new Process($command, $outputPath);
        $process->run();

        if (!$process->isSuccessful()) {
            logger()->error("yt-dlp failed", ['stderr' => $process->getErrorOutput()]);
            return false;
        }

        if (!file_exists($fullPath)) {
            logger()->error("Файл после загрузки не найден: $fullPath");
            return false;
        }

        return [
            'path' => $fullPath,
            'title' => basename($fullPath),
            'ext' => pathinfo($fullPath, PATHINFO_EXTENSION),
            'url' => $url,
            'ok_type' => $type,
            'delete_after_send' => true,
        ];
    }
}
