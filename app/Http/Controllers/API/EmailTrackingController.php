<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Response;

/**
 * Email Tracking Controller
 * 
 * Tracks email opens and clicks
 */
class EmailTrackingController extends Controller
{
    /**
     * Track email open
     * 
     * Returns a 1x1 transparent pixel
     * 
     * @param string $trackingId
     * @return Response
     */
    public function trackOpen(string $trackingId): Response
    {
        // Find email log by tracking ID
        $emailLog = EmailLog::where('tracking_id', $trackingId)->first();

        if ($emailLog) {
            $emailLog->recordOpen();
        }

        // Return 1x1 transparent GIF pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Track email link click
     * 
     * @param string $trackingId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function trackClick(string $trackingId): \Illuminate\Http\RedirectResponse
    {
        // Find email log by tracking ID
        $emailLog = EmailLog::where('tracking_id', $trackingId)->first();

        if ($emailLog) {
            $emailLog->recordClick();
        }

        // Redirect to app
        return redirect(config('app.frontend_url'));
    }
}
