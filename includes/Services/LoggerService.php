<?php

namespace App\Services;

class LoggerService
{
    public static function log($level, $message, $context = [])
    {
        $config = config('logging');
        
        if (!$config['enabled']) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";
        
        // Логируем в файл
        file_put_contents($config['file'], $logMessage, FILE_APPEND | LOCK_EX);
        
        // В режиме отладки можем логировать в stdout
        if (config('app.debug')) {
            error_log($logMessage);
        }
    }
    
    public static function info($message, $context = [])
    {
        self::log('INFO', $message, $context);
    }
    
    public static function error($message, $context = [])
    {
        self::log('ERROR', $message, $context);
    }
    
    public static function debug($message, $context = [])
    {
        self::log('DEBUG', $message, $context);
    }
    
    public static function warning($message, $context = [])
    {
        self::log('WARNING', $message, $context);
    }
}