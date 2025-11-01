<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    /**
     * Get all sent announcements (public or for authenticated customers)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $customer = $request->user('sanctum');
        
        $query = Announcement::where('status', 'sent');
        
        // Filter announcements for authenticated customers
        // Only show announcements sent after customer registration
        if ($customer) {
            $query->where(function ($q) use ($customer) {
                $q->where('sent_at', '>=', $customer->created_at)
                  ->orWhereNull('sent_at');
            });
        }
        
        $announcements = $query->orderBy('sent_at', 'desc')->paginate(20);

        $data = $announcements->map(function ($announcement) use ($customer) {
            $item = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'sent_at' => $announcement->sent_at?->toIso8601String(),
                'created_at' => $announcement->created_at->toIso8601String(),
            ];

            // Add read status if customer is authenticated
            if ($customer) {
                $item['is_read'] = $customer->hasReadAnnouncement($announcement->id);
            }

            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
            ],
        ]);
    }

    /**
     * Get single announcement detail
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $customer = $request->user('sanctum');
        
        $query = Announcement::where('status', 'sent')
            ->with('sender:id,name');
        
        // Filter for authenticated customers
        if ($customer) {
            $query->where(function ($q) use ($customer) {
                $q->where('sent_at', '>=', $customer->created_at)
                  ->orWhereNull('sent_at');
            });
        }
        
        $announcement = $query->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan',
            ], 404);
        }

        $data = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'sent_at' => $announcement->sent_at?->toIso8601String(),
            'sent_by' => $announcement->sender?->name,
            'created_at' => $announcement->created_at->toIso8601String(),
        ];

        // Add read status if customer is authenticated
        if ($customer) {
            $data['is_read'] = $customer->hasReadAnnouncement($announcement->id);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get latest announcements (for home/notification badge)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function latest(Request $request)
    {
        $customer = $request->user('sanctum');
        $limit = $request->input('limit', 5);

        $query = Announcement::where('status', 'sent');
        
        // Filter for authenticated customers
        if ($customer) {
            $query->where(function ($q) use ($customer) {
                $q->where('sent_at', '>=', $customer->created_at)
                  ->orWhereNull('sent_at');
            });
        }

        $announcements = $query->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'sent_at' => $announcement->sent_at?->toIso8601String(),
                    'created_at' => $announcement->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    /**
     * Get announcements count (for badge)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function count(Request $request)
    {
        $customer = $request->user('sanctum');
        
        $query = Announcement::where('status', 'sent');
        
        // Filter for authenticated customers
        if ($customer) {
            $query->where(function ($q) use ($customer) {
                $q->where('sent_at', '>=', $customer->created_at)
                  ->orWhereNull('sent_at');
            });
        }
        
        $totalCount = $query->count();

        $response = [
            'success' => true,
            'count' => $totalCount,
        ];

        // Add unread count if customer is authenticated
        if ($customer) {
            $readCount = $customer->readAnnouncements()->count();
            $response['unread_count'] = max(0, $totalCount - $readCount);
        }

        return response()->json($response);
    }

    /**
     * Mark an announcement as read for the authenticated customer
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $id)
    {
        $customer = $request->user('sanctum');

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $announcement = Announcement::where('status', 'sent')
            ->where(function ($q) use ($customer) {
                $q->where('sent_at', '>=', $customer->created_at)
                  ->orWhereNull('sent_at');
            })
            ->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan',
            ], 404);
        }

        $customer->markAnnouncementAsRead($id);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil ditandai sebagai sudah dibaca',
        ]);
    }

    /**
     * Mark an announcement as unread for the authenticated customer
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsUnread(Request $request, $id)
    {
        $customer = $request->user('sanctum');

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $announcement = Announcement::where('status', 'sent')
            ->where(function ($q) use ($customer) {
                $q->where('sent_at', '>=', $customer->created_at)
                  ->orWhereNull('sent_at');
            })
            ->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumuman tidak ditemukan',
            ], 404);
        }

        $customer->markAnnouncementAsUnread($id);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil ditandai sebagai belum dibaca',
        ]);
    }

    /**
     * Mark all announcements as read for the authenticated customer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        $customer = $request->user('sanctum');

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $unreadAnnouncements = Announcement::where('status', 'sent')
            ->where(function ($q) use ($customer) {
                $q->where('sent_at', '>=', $customer->created_at)
                  ->orWhereNull('sent_at');
            })
            ->whereNotIn('id', function ($query) use ($customer) {
                $query->select('announcement_id')
                    ->from('customer_announcement_read')
                    ->where('customer_id', $customer->id);
            })
            ->pluck('id');

        foreach ($unreadAnnouncements as $announcementId) {
            $customer->markAnnouncementAsRead($announcementId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Semua pengumuman berhasil ditandai sebagai sudah dibaca',
            'marked_count' => $unreadAnnouncements->count(),
        ]);
    }

    /**
     * Get unread announcements for the authenticated customer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unread(Request $request)
    {
        $customer = $request->user('sanctum');

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $unreadAnnouncements = Announcement::where('status', 'sent')
            ->where(function ($q) use ($customer) {
                $q->where('sent_at', '>=', $customer->created_at)
                  ->orWhereNull('sent_at');
            })
            ->whereNotIn('id', function ($query) use ($customer) {
                $query->select('announcement_id')
                    ->from('customer_announcement_read')
                    ->where('customer_id', $customer->id);
            })
            ->orderBy('sent_at', 'desc')
            ->paginate(20);

        $data = $unreadAnnouncements->map(function ($announcement) {
            return [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'sent_at' => $announcement->sent_at?->toIso8601String(),
                'created_at' => $announcement->created_at->toIso8601String(),
                'is_read' => false,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $unreadAnnouncements->currentPage(),
                'last_page' => $unreadAnnouncements->lastPage(),
                'per_page' => $unreadAnnouncements->perPage(),
                'total' => $unreadAnnouncements->total(),
            ],
        ]);
    }
}
