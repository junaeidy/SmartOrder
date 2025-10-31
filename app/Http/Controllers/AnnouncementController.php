<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

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

        // Update status to sending
        $announcement->update(['status' => 'sending']);

        try {
            // Send to all customers with FCM tokens
            $result = $this->firebaseService->sendAnnouncementToAllCustomers(
                $announcement->title,
                $announcement->message,
                $announcement->id
            );

            // Update announcement with results
            $announcement->update([
                'status' => 'sent',
                'sent_at' => now(),
                'recipients_count' => $result['total'],
                'success_count' => $result['success'],
                'failed_count' => $result['failed'],
            ]);

            $message = "Pengumuman berhasil dikirim ke {$result['success']} dari {$result['total']} penerima.";
            
            if ($result['failed'] > 0) {
                $message .= " {$result['failed']} gagal dikirim.";
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            // Update status to failed
            $announcement->update(['status' => 'failed']);

            return redirect()->back()->with('error', 'Gagal mengirim pengumuman: ' . $e->getMessage());
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
