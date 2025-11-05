<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\FirebaseService;
use App\Jobs\ProcessAnnouncementBroadcast;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Display a listing of the announcements
     */
    public function index()
    {
        $announcements = Announcement::with('sender')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'sent_by' => $announcement->sender->name,
                    'sent_at' => $announcement->sent_at?->format('d/m/Y H:i'),
                    'sent_at_iso' => $announcement->sent_at?->toISOString(),
                    'recipients_count' => $announcement->recipients_count,
                    'success_count' => $announcement->success_count,
                    'failed_count' => $announcement->failed_count,
                    'status' => $announcement->status,
                    'created_at' => $announcement->created_at->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Kasir/Announcements', [
            'announcements' => $announcements,
        ]);
    }

    /**
     * Store a new announcement (draft)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
        ]);

        $announcement = Announcement::create([
            'title' => $request->title,
            'message' => $request->message,
            'sent_by' => Auth::id(),
            'status' => 'draft',
        ]);

        return redirect()->back()->with('success', 'Pengumuman berhasil dibuat sebagai draft.');
    }

    /**
     * Send announcement to all customers
     */
    public function send(Request $request, Announcement $announcement)
    {
        // Check if already sent
        if ($announcement->status === 'sent') {
            return redirect()->back()->with('error', 'Pengumuman ini sudah dikirim sebelumnya.');
        }

        // Check if already in sending process
        if ($announcement->status === 'sending') {
            return redirect()->back()->with('error', 'Pengumuman ini sedang dalam proses pengiriman.');
        }

        try {
            // Dispatch job to process announcement broadcast
            ProcessAnnouncementBroadcast::dispatch($announcement->id);

            return redirect()->back()->with('success', 'Pengumuman sedang diproses dan akan dikirim ke semua customer. Proses ini berjalan di background.');
        } catch (\Exception $e) {
            Log::error('Failed to dispatch announcement broadcast', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Gagal memproses pengumuman: ' . $e->getMessage());
        }
    }

    /**
     * Delete an announcement
     */
    public function destroy(Announcement $announcement)
    {
        // Only allow delete if not sent yet or if user is owner
        if ($announcement->status === 'sent' && Auth::id() !== $announcement->sent_by) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus pengumuman yang sudah dikirim.');
        }

        $announcement->delete();

        return redirect()->back()->with('success', 'Pengumuman berhasil dihapus.');
    }
}
