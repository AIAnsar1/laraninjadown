<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait TempDirectoryTrait
{
    protected string $tempDir;
    protected bool $isCleanedUp = false;

    /**
     * Создает временную директорию для скачивания файлов
     */
    protected function createTempDirectory(string $prefix = 'download'): string
    {
        $this->tempDir = sys_get_temp_dir() . '/' . $prefix . '_' . Str::random(8);

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        Log::info("Created temp directory: {$this->tempDir}");
        return $this->tempDir;
    }

    /**
     * Очищает временную директорию и все файлы в ней
     */
    protected function cleanupTempDirectory(): void
    {
        if ($this->isCleanedUp || empty($this->tempDir) || !is_dir($this->tempDir)) {
            return;
        }

        try {
            $this->removeDirectoryRecursively($this->tempDir);
            $this->isCleanedUp = true;
            Log::info("Temporary files cleaned up: {$this->tempDir}");
        } catch (\Exception $e) {
            Log::error("Error cleaning up temp directory: {$this->tempDir}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Рекурсивно удаляет директорию и все её содержимое
     */
    private function removeDirectoryRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->removeDirectoryRecursively($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Получает путь к временной директории
     */
    protected function getTempDirectory(): string
    {
        return $this->tempDir;
    }

    /**
     * Проверяет, была ли директория очищена
     */
    protected function isCleanedUp(): bool
    {
        return $this->isCleanedUp;
    }
}
