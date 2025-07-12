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

class YouTubeService
{
    protected string $ytBin;
    protected string $downloadPath;

    public function __construct()
    {
        $this->ytBin = config('services.ytdlp.bin', '/usr/local/bin/yt-dlp');
        $this->downloadPath = storage_path('app/youtube');
    }

    public function fetch(string $url, string $type = 'video', ?string $format = null): array|false
    {
        if (!is_dir($this->downloadPath)) {
            mkdir($this->downloadPath, 0777, true);
        }

        $filenameTemplate = '%(title).200s.%(ext)s';
        $outputPath = $this->downloadPath;

        $command = [
            $this->ytBin,
            '--no-warnings',
            '--quiet',
            '--no-playlist',
            '--restrict-filenames',
            '--merge-output-format', 'mp4',
            '--ffmpeg-location=/usr/local/bin/ffmpeg',
            '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
            '--no-check-certificate',
            '--geo-bypass',
            '--prefer-insecure',
            '--socket-timeout', '30',
            '--retries', '10',
            '--fragment-retries', '10',
            '--http-chunk-size', '524288000',
            '--concurrent-fragments', '16',
            '--external-downloader', 'aria2c',
            '--external-downloader-args', 'aria2c:-x 16 -s 16 -k 1M --min-split-size=1M --max-connection-per-server=16 --max-concurrent-downloads=16 --max-tries=10 --retry-wait=1 --timeout=10 --summary-interval=0 --download-result=hide --quiet=true --enable-http-keep-alive=true --enable-http-pipelining=true --file-allocation=none --no-conf=true --remote-time=false --conditional-get=false',
            '--no-write-thumbnail',
            '--no-write-info-json',
            '--no-write-subs',
            '--no-write-auto-subs',
            '-o', "$outputPath/$filenameTemplate",
        ];

        if ($type === 'audio') {
            $command[] = '--extract-audio';
            $command[] = '--audio-format=' . ($format ?? 'mp3');
            $command[] = '--audio-quality=0';
                } else {
            if ($format) {
                // Используем конкретный формат
                Log::info("Downloading format: {$format}");
                $command[] = '-f';
                $command[] = $format;
            } else {
                // Если формат не указан, используем лучший доступный
                $command[] = '-f';
                $command[] = 'bestvideo+bestaudio/best';
            }
        }

        $command[] = $url;



        $process = new Process($command);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            Log::error("yt-dlp failed: " . $errorOutput);

            // Если запрошенный формат недоступен, попробуем скачать лучший доступный
            if (strpos($errorOutput, 'Requested format is not available') !== false) {
                Log::info("Requested format not available, trying best format...");

                $fallbackCommand = [
                    $this->ytBin,
                    '--no-warnings',
                    '--quiet',
                    '--no-playlist',
                    '--restrict-filenames',
                    '--merge-output-format', 'mp4',
                    '--ffmpeg-location=/usr/local/bin/ffmpeg',
                    '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
                    '--no-check-certificate',
                    '--geo-bypass',
                    '--prefer-insecure',
                    '--socket-timeout', '30',
                    '--retries', '10',
                    '--fragment-retries', '10',
                    '--http-chunk-size', '524288000',
                    '--concurrent-fragments', '16',
                    '--external-downloader', 'aria2c',
                    '--external-downloader-args', 'aria2c:-x 16 -s 16 -k 1M --min-split-size=1M --max-connection-per-server=16 --max-concurrent-downloads=16 --max-tries=10 --retry-wait=1 --timeout=10 --summary-interval=0 --download-result=hide --quiet=true --enable-http-keep-alive=true --enable-http-pipelining=true --file-allocation=none --no-conf=true --remote-time=false --conditional-get=false',
                    '--no-write-thumbnail',
                    '--no-write-info-json',
                    '--no-write-subs',
                    '--no-write-auto-subs',
                    '-o', "$outputPath/$filenameTemplate",
                    $url
                ];

                Log::info('YouTube fallback command: ' . json_encode($fallbackCommand));

                $fallbackProcess = new Process($fallbackCommand);
                $fallbackProcess->setTimeout(300);
                $fallbackProcess->run();

                if (!$fallbackProcess->isSuccessful()) {
                    Log::error("yt-dlp fallback failed: " . $fallbackProcess->getErrorOutput());
                    return false;
                }

                // Продолжаем с результатом fallback
            } else {
                return false;
            }
        }

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

    public function getAvailableFormats(string $url): array
    {
        $command = [
            $this->ytBin,
            '--no-warnings',
            '--quiet',
            '--skip-download',
            '--print-json',
            '--no-playlist',
            '--restrict-filenames',
            '--user-agent=Mozilla/5.0',
            '--geo-bypass',
            $url
        ];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error("yt-dlp format list failed: " . $process->getErrorOutput());
            return [];
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        if (!is_array($data) || !isset($data['formats'])) {
            return [];
        }

        $formats = [];

        $valid_heights = [360, 480, 720, 1080, 1440, 2160];

        $audioFormats = collect($data['formats'])
            ->filter(fn($f) => ($f['vcodec'] ?? 'none') === 'none' && ($f['acodec'] ?? 'none') !== 'none');

        $bestAudio = $audioFormats->sortByDesc(fn($f) => $f['abr'] ?? 0)->first();
        $audioSize = $bestAudio['filesize'] ?? $bestAudio['filesize_approx'] ?? 0;
        $audioId = $bestAudio['format_id'] ?? null;

        $seen = [];

                // Сначала найдем лучшие форматы для каждого разрешения
        $bestFormats = [];

        foreach ($data['formats'] as $format) {
            if (($format['vcodec'] ?? 'none') === 'none') continue;
            $height = $format['height'] ?? null;
            if (!$height || !in_array($height, $valid_heights)) continue;

            // Если уже есть формат с этим разрешением, выбираем лучший (с большим размером)
            if (isset($bestFormats[$height])) {
                $currentSize = $format['filesize'] ?? $format['filesize_approx'] ?? 0;
                $existingSize = $bestFormats[$height]['filesize'] ?? $bestFormats[$height]['filesize_approx'] ?? 0;

                if ($currentSize > $existingSize) {
                    $bestFormats[$height] = $format;
                }
            } else {
                $bestFormats[$height] = $format;
            }
        }

        // Теперь обрабатываем лучшие форматы
        foreach ($bestFormats as $height => $format) {
            $videoSize = $format['filesize'] ?? $format['filesize_approx'] ?? 0;

            // Если размер видео не указан, оцениваем по битрейту
            if ($videoSize === 0) {
                $bitrate = $format['vbr'] ?? $format['abr'] ?? 0;
                $duration = $data['duration'] ?? 0;
                if ($bitrate > 0 && $duration > 0) {
                    $videoSize = ($bitrate * 1000 * $duration) / 8; // битрейт в битах/сек, делим на 8 для байтов
                }
            }

            // Проверяем, содержит ли видео аудио
            $hasAudio = ($format['acodec'] ?? 'none') !== 'none';

            // Если видео содержит аудио, используем только размер видео
            // Если нет аудио, добавляем размер лучшего аудио
            if ($hasAudio) {
                $totalSize = $videoSize;
            } else {
                // Добавляем размер аудио только если видео не содержит аудио
                $totalSize = $videoSize + $audioSize;
            }

            // Если все еще нет размера, используем примерную оценку по разрешению
            if ($totalSize === 0) {
                $duration = $data['duration'] ?? 0;

                // Примерные битрейты для разных разрешений (в кбит/с)
                $bitrateMap = [
                    144 => 100,
                    240 => 200,
                    360 => 500,
                    480 => 800,
                    720 => 1500,
                    1080 => 2500,
                    1440 => 4000,
                    2160 => 8000,
                ];

                $estimatedBitrate = $bitrateMap[$height] ?? 1000;
                $totalSize = ($estimatedBitrate * 1000 * $duration) / 8;
            }

            // Форматируем размер для отображения
            if ($totalSize < 1024 * 1024) {
                $sizeStr = round($totalSize / 1024, 2) . ' KB';
            } else {
                $sizeStr = round($totalSize / (1024 * 1024), 2) . ' MB';
            }

            $formats[] = [
                'itag' => $format['format_id'],
                'height' => $height,
                'filesize' => $totalSize,
                'size_str' => $sizeStr,
                'format_string' => $hasAudio ? $format['format_id'] : $format['format_id'] . '+' . $audioId,
                'total_size' => $totalSize,
                'has_audio' => $hasAudio,
            ];

            // Логируем информацию о размере для отладки
            Log::info("Format {$format['format_id']} ({$height}p): videoSize={$videoSize}, audioSize={$audioSize}, hasAudio=" . ($hasAudio ? 'yes' : 'no') . ", totalSize={$totalSize}, sizeStr={$sizeStr}");
        }

        return $formats;
    }

    public function isYoutubeUrl(string $url): bool
    {
        return preg_match('~^https?://(www\.)?(youtube\.com|youtu\.be)/~i', $url);
    }
}
