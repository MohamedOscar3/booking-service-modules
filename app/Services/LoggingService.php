<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * @description Logging service for logging messages
 */
class LoggingService
{
    /**
     * @param  string  $fileName
     * @param  string  $module
     */
    public function __construct(
        protected $fileName = null,
        protected $module = null,
    ) {}

    /**
     * @description Log a message
     *
     * @param  string  $message  Message to log
     * @param  array  $data  Data to log
     */
    public function log(string $message, array $data): void
    {
        if ($this->fileName) {
            $today = now()->format('Y-m-d');
            $file_path = ($this->module ? $this->module.'_' : '').$this->fileName;

            // Create a custom log channel configuration on the fly
            $customChannel = 'custom_'.$file_path;

            // Configure a custom channel
            config([
                'logging.channels.'.$customChannel => [
                    'driver' => 'single',
                    'path' => storage_path('logs/logging/'.$today.'_'.$file_path.'.log'),
                    'level' => 'debug',
                ],
            ]);

            // Log to the custom channel
            Log::channel($customChannel)->info('LoggingService: '.$message, $data);
        } else {
            Log::info('LoggingService: '.$message, $data);
        }
    }
}
