<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function owner()
    {
        return Inertia::render('Owner/Dashboard');
    }

    public function kasir()
    {
        $startOfToday = now()->startOfDay();
        $endOfToday = now()->endOfDay();

        // Fetch today's relevant transactions (waiting or completed)
        $todayTx = \App\Models\Transaction::whereBetween('created_at', [$startOfToday, $endOfToday])
            ->whereIn('status', ['waiting', 'completed'])
            ->get();

        $todayRevenue = $todayTx->sum('total_amount');
        $todayOrders = $todayTx->count();
        $pendingCount = \App\Models\Transaction::where('status', 'waiting')->count();
        $completedToday = \App\Models\Transaction::where('status', 'completed')
            ->whereBetween('created_at', [$startOfToday, $endOfToday])
            ->count();

        $avgOrderValueToday = $todayOrders > 0 ? round($todayRevenue / $todayOrders) : 0;

        // Payment method breakdown (today) - rename 'midtrans' to 'online'
        $paymentBreakdown = $todayTx->groupBy('payment_method')->map(function($group, $method) {
            $label = $method === 'midtrans' ? 'online' : ($method ?: 'unknown');
            return [
                'method' => $label,
                'orders' => $group->count(),
                'revenue' => $group->sum('total_amount'),
            ];
        })->values();

        // Hourly orders for today
        $hours = collect(range(0, 23));
        $ordersByHour = $hours->map(function($h) use ($todayTx) {
            $count = $todayTx->filter(function($tx) use ($h) {
                return $tx->created_at ? ((int)$tx->created_at->format('G') === (int)$h) : false;
            })->count();
            $revenue = $todayTx->filter(function($tx) use ($h) {
                return $tx->created_at ? ((int)$tx->created_at->format('G') === (int)$h) : false;
            })->sum('total_amount');
            return [
                'hour' => sprintf('%02d:00', $h),
                'orders' => $count,
                'revenue' => $revenue,
            ];
        });

        // Last 7 days revenue
        $start7 = now()->copy()->subDays(6)->startOfDay();
        $tx7 = \App\Models\Transaction::whereBetween('created_at', [$start7, $endOfToday])
            ->whereIn('status', ['waiting', 'completed'])
            ->get();
        $days = collect(range(0, 6))->map(function($i) use ($start7) { return $start7->copy()->addDays($i); });
        $last7Days = $days->map(function($day) use ($tx7) {
            $revenue = $tx7->filter(function($tx) use ($day) {
                return $tx->created_at ? $tx->created_at->isSameDay($day) : false;
            })->sum('total_amount');
            $orders = $tx7->filter(function($tx) use ($day) {
                return $tx->created_at ? $tx->created_at->isSameDay($day) : false;
            })->count();
            return [
                'date' => $day->format('d M'),
                'revenue' => $revenue,
                'orders' => $orders,
            ];
        });

        // Average completion time per day for last 7 days
        $completed7 = \App\Models\Transaction::where('status', 'completed')
            ->whereBetween('created_at', [$start7, $endOfToday])
            ->get();
        $avgCompletionByDay = $days->map(function($day) use ($completed7) {
            $bucket = $completed7->filter(function($tx) use ($day) {
                return $tx->created_at ? $tx->created_at->isSameDay($day) : false;
            });
            $count = $bucket->count();
            $avg = 0;
            if ($count > 0) {
                $sumSeconds = $bucket->reduce(function($carry, $tx) {
                    if ($tx->created_at && $tx->updated_at) {
                        $carry += $tx->updated_at->diffInSeconds($tx->created_at);
                    }
                    return $carry;
                }, 0);
                $avg = round(($sumSeconds / $count) / 60, 1); // minutes
            }
            return [
                'date' => $day->format('d M'),
                'avgMinutes' => $avg,
                'count' => $count,
            ];
        });

        // Top products today (from items array)
        $productAgg = [];
        foreach ($todayTx as $tx) {
            $items = is_array($tx->items) ? $tx->items : [];
            foreach ($items as $item) {
                $name = $item['nama'] ?? 'Item';
                $qty = (int) ($item['quantity'] ?? 0);
                $subtotal = (int) ($item['subtotal'] ?? 0);
                if (!isset($productAgg[$name])) {
                    $productAgg[$name] = ['name' => $name, 'quantity' => 0, 'amount' => 0];
                }
                $productAgg[$name]['quantity'] += $qty;
                $productAgg[$name]['amount'] += $subtotal;
            }
        }
        // Take top 5 by quantity
        $topProducts = collect($productAgg)->sortByDesc('quantity')->take(5)->values()->all();

        // Average completion time (in minutes), per hour today and overall today
        $completedTodayTx = \App\Models\Transaction::where('status', 'completed')
            ->whereBetween('created_at', [$startOfToday, $endOfToday])
            ->get();

        $avgCompletionByHour = $hours->map(function($h) use ($completedTodayTx) {
            $bucket = $completedTodayTx->filter(function($tx) use ($h) {
                return $tx->created_at ? ((int)$tx->created_at->format('G') === (int)$h) : false;
            });
            $avg = 0;
            $count = $bucket->count();
            if ($bucket->count() > 0) {
                $sumSeconds = $bucket->reduce(function($carry, $tx) {
                    if ($tx->created_at && $tx->updated_at) {
                        $carry += $tx->updated_at->diffInSeconds($tx->created_at);
                    }
                    return $carry;
                }, 0);
                $avg = round(($sumSeconds / $bucket->count()) / 60, 1); // minutes with 1 decimal
            }
            return [
                'hour' => sprintf('%02d:00', $h),
                'avgMinutes' => $avg,
                'count' => $count,
            ];
        });

        $overallAvgCompletion = 0;
        $overallCompletionCount = 0;
        if ($completedTodayTx->count() > 0) {
            $overallSumSeconds = $completedTodayTx->reduce(function($carry, $tx) {
                if ($tx->created_at && $tx->updated_at) {
                    $carry += $tx->updated_at->diffInSeconds($tx->created_at);
                }
                return $carry;
            }, 0);
            $overallAvgCompletion = round(($overallSumSeconds / $completedTodayTx->count()) / 60, 1); // minutes with 1 decimal
            $overallCompletionCount = $completedTodayTx->count();
            // Build human-readable
            $avgSec = (int) round($overallSumSeconds / max(1, $completedTodayTx->count()));
            $hours = intdiv($avgSec, 3600);
            $minutes = intdiv($avgSec % 3600, 60);
            $seconds = $avgSec % 60;
            $parts = [];
            if ($hours > 0) { $parts[] = $hours.' jam'; }
            if ($minutes > 0) { $parts[] = $minutes.' menit'; }
            if ($hours === 0 && $minutes === 0) { $parts[] = $seconds.' detik'; }
            $overallAvgCompletionHuman = implode(' ', $parts);
        } else {
            $overallAvgCompletionHuman = '0 menit';
        }

        $stats = [
            'todayRevenue' => $todayRevenue,
            'todayOrders' => $todayOrders,
            'avgOrderValue' => $avgOrderValueToday,
            'pendingCount' => $pendingCount,
            'completedToday' => $completedToday,
            'overallAvgCompletion' => $overallAvgCompletion,
            'overallCompletionCount' => $overallCompletionCount,
            'overallAvgCompletionHuman' => $overallAvgCompletionHuman,
        ];

        $charts = [
            'paymentBreakdown' => $paymentBreakdown,
            'ordersByHour' => $ordersByHour,
            'last7Days' => $last7Days,
            'topProducts' => $topProducts,
            'avgCompletionByHour' => $avgCompletionByHour,
            'avgCompletionByDay' => $avgCompletionByDay,
        ];

        return Inertia::render('Kasir/Dashboard', [
            'stats' => $stats,
            'charts' => $charts,
        ]);
    }

    public function karyawan()
    {
        return Inertia::render('Karyawan/Dashboard');
    }
}
