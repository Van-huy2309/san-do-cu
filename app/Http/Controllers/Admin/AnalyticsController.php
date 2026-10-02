<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, AdminAnalyticsService $analytics)
    {
        $days = (int) $request->integer('days', 30);
        $days = max(7, min(90, $days));
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        $summary = $analytics->summary($from, $to);
        $series = $analytics->dailySeries($days);
        $ordersByStatus = $analytics->ordersByStatus();
        $listingsByStatus = $analytics->listingsByStatus();
        $usersByKyc = $analytics->usersByKyc();
        $topProducts = $analytics->topProducts(8, $from, $to);
        $forecastBasis = $days >= 60 ? 'quarter' : 'month';
        $forecast = $analytics->forecast($forecastBasis);

        return view('admin.analytics.index', compact(
            'summary', 'series', 'ordersByStatus', 'listingsByStatus', 'usersByKyc', 'topProducts', 'days',
            'forecast'
        ));
    }

    public function export(Request $request, AdminAnalyticsService $analytics): StreamedResponse
    {
        $data = $request->validate([
            'kind' => 'nullable|in:revenue,products,orders',
            'days' => 'nullable|integer|min:7|max:90',
        ]);
        $days = (int) ($data['days'] ?? 30);
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();
        $file = $analytics->exportCsv($data['kind'] ?? 'revenue', $from, $to);
        $content = $file['content'];

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $file['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
