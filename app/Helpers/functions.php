<?php

if (!function_exists('activity')) {
    function activity($description = null)
    {
        $logger = app(\Spatie\Activitylog\ActivityLogger::class);
        
        if ($description !== null) {
            $logger->log($description);
            return;
        }
        
        return $logger;
    }
}

if (!function_exists('yesterday')) {
    function yesterday()
    {
        return now()->subDay();
    }
}
