<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class ReportsController extends Controller
{
    // Admin reports methods (duplicated from kasir methods)
    public function adminIndex(Request $request)
    {
        $baseQ = $this->buildQuery($request);

        $paidStatuses = ['paid', 'settlement', 'capture'];
        $summary = [
            'count' => (clone $baseQ)->count(),
            'total_amount' => (clone $baseQ)->whereIn('payment_status', $paidStatuses)->sum('total_amount'),
            'cash_count' => (clone $baseQ)->where('payment_method', 'cash')->count(),
            'online_count' => (clone $baseQ)->where('payment_method', 'midtrans')->count(),
        ];

        // Paginate from a fresh clone of base query
        $transactions = (clone $baseQ)->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Map collection for UI convenience
        $mapped = $transactions->through(function ($t) use ($paidStatuses) {
            return [
                'id' => $t->id,
                'date' => $t->created_at ? $t->created_at->toDateTimeString() : null,
                'kode_transaksi' => $t->kode_transaksi,
                'customer_name' => $t->customer_name,
                'customer_email' => $t->customer_email,
                'customer_phone' => $t->customer_phone,
                'customer_notes' => $t->customer_notes,
                'payment_method' => $t->payment_method === 'midtrans' ? 'online' : $t->payment_method,
                'payment_status' => $t->payment_status,
                'status' => $t->status,
                'total_amount' => $t->total_amount,
                'discount_amount' => $t->discount_amount,
                'tax_amount' => $t->tax_amount,
                'total_items' => $t->total_items,
                'queue_number' => $t->queue_number,
                'paid_at' => $t->paid_at ? $t->paid_at->toDateTimeString() : null,
                'items' => is_array($t->items) ? $t->items : [],
                'amount_received' => $t->amount_received,
                'change_amount' => $t->change_amount,
                'is_paid' => in_array(strtolower((string)$t->payment_status), $paidStatuses),
            ];
        });

        return Inertia::render('Admin/Reports', [
            'filters' => [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'payment_method' => $request->query('payment_method'),
                'status' => $request->query('status'),
                'search' => $request->query('search'),
            ],
            'summary' => $summary,
            'transactions' => $mapped,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }


    protected function buildQuery(Request $request)
    {
        $q = Transaction::query();

        // Date range
        $from = $request->query('from');
        $to = $request->query('to');
        if ($from) {
            $q->where('created_at', '>=', $from.' 00:00:00');
        }
        if ($to) {
            $q->where('created_at', '<=', $to.' 23:59:59');
        }

        // Payment method (cash|online)
        $payment = $request->query('payment_method');
        if ($payment === 'cash') {
            $q->where('payment_method', 'cash');
        } elseif ($payment === 'online') {
            $q->where('payment_method', 'midtrans');
        }

        // Status (waiting|completed|canceled)
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }

        // Search (kode_transaksi, customer_name)
        if ($search = $request->query('search')) {
            $q->where(function ($sub) use ($search) {
                $sub->where('kode_transaksi', 'like', "%$search%")
                    ->orWhere('customer_name', 'like', "%$search%");
            });
        }

        return $q;
    }

    public function index(Request $request)
    {
        $baseQ = $this->buildQuery($request);

        $paidStatuses = ['paid', 'settlement', 'capture'];
        $summary = [
            'count' => (clone $baseQ)->count(),
            'total_amount' => (clone $baseQ)->whereIn('payment_status', $paidStatuses)->sum('total_amount'),
            'cash_count' => (clone $baseQ)->where('payment_method', 'cash')->count(),
            'online_count' => (clone $baseQ)->where('payment_method', 'midtrans')->count(),
        ];

        // Paginate from a fresh clone of base query
        $transactions = (clone $baseQ)->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Map collection for UI convenience
        $mapped = $transactions->through(function ($t) use ($paidStatuses) {
            return [
                'id' => $t->id,
                'date' => $t->created_at ? $t->created_at->toDateTimeString() : null,
                'kode_transaksi' => $t->kode_transaksi,
                'customer_name' => $t->customer_name,
                'customer_email' => $t->customer_email,
                'customer_phone' => $t->customer_phone,
                'customer_notes' => $t->customer_notes,
                'payment_method' => $t->payment_method === 'midtrans' ? 'online' : $t->payment_method,
                'payment_status' => $t->payment_status,
                'status' => $t->status,
                'total_amount' => $t->total_amount,
                'discount_amount' => $t->discount_amount,
                'tax_amount' => $t->tax_amount,
                'total_items' => $t->total_items,
                'queue_number' => $t->queue_number,
                'paid_at' => $t->paid_at ? $t->paid_at->toDateTimeString() : null,
                'items' => is_array($t->items) ? $t->items : [],
                'amount_received' => $t->amount_received,
                'change_amount' => $t->change_amount,
                'is_paid' => in_array(strtolower((string)$t->payment_status), $paidStatuses),
            ];
        });

        return Inertia::render('Kasir/Reports', [
            'filters' => [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'payment_method' => $request->query('payment_method'),
                'status' => $request->query('status'),
                'search' => $request->query('search'),
            ],
            'summary' => $summary,
            'transactions' => $mapped,
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $q = $this->buildQuery($request);
        $rows = $q->orderBy('created_at', 'desc')->get();

        $paidStatuses = ['paid', 'settlement', 'capture'];
        $invalidStatuses = ['canceled', 'cancelled', 'expired', 'expire', 'deny', 'failed', 'pending', 'waiting_for_payment'];
        $formatRupiah = fn($value) => is_numeric($value) ? 'Rp ' . number_format($value, 0, ',', '.') : $value;

        // === XLSX pakai Spatie SimpleExcel ===
        if (class_exists(\Spatie\SimpleExcel\SimpleExcelWriter::class)) {
            $filename = 'laporan_'.now()->format('Ymd_His').'.xlsx';
            $path = storage_path('app/'.$filename);
            $writer = \Spatie\SimpleExcel\SimpleExcelWriter::create($path);

            $writer->addHeader(['Tanggal', 'Kode', 'Customer', 'Metode', 'Status Bayar', 'Status', 'Total', 'Items']);

            foreach ($rows as $t) {
                $isInvalid = in_array(strtolower((string)$t->status), $invalidStatuses)
                            || !in_array(strtolower((string)$t->payment_status), $paidStatuses);

                $total = $isInvalid ? '-' : $formatRupiah($t->total_amount);
                $items = $isInvalid ? '-' : $t->total_items;

                $writer->addRow([
                    optional($t->created_at)->toDateTimeString(),
                    $t->kode_transaksi,
                    $t->customer_name,
                    $t->payment_method === 'midtrans' ? 'online' : $t->payment_method,
                    $t->payment_status,
                    $t->status,
                    $total,
                    $items,
                ]);
            }

            // Hitung total hanya untuk yang paid
            $paidTotal = $rows->filter(fn($r) => in_array(strtolower((string)$r->payment_status), $paidStatuses))
                            ->sum('total_amount');

            $writer->addRow(['', '', '', 'TOTAL', '', '', $formatRupiah($paidTotal), $rows->sum('total_items')]);
            $writer->close();

            return response()->download($path)->deleteFileAfterSend(true);
        }

        // ==== CSV fallback ====
        $filename = 'laporan_'.now()->format('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($rows, $paidStatuses, $invalidStatuses, $formatRupiah) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Tanggal', 'Kode', 'Customer', 'Metode', 'Status Bayar', 'Status', 'Total', 'Items']);

            foreach ($rows as $t) {
                $isInvalid = in_array(strtolower((string)$t->status), $invalidStatuses)
                            || !in_array(strtolower((string)$t->payment_status), $paidStatuses);

                $total = $isInvalid ? '-' : $formatRupiah($t->total_amount);
                $items = $isInvalid ? '-' : $t->total_items;

                fputcsv($out, [
                    optional($t->created_at)->toDateTimeString(),
                    $t->kode_transaksi,
                    $t->customer_name,
                    $t->payment_method === 'midtrans' ? 'online' : $t->payment_method,
                    $t->payment_status,
                    $t->status,
                    $total,
                    $items,
                ]);
            }

            $paidTotal = $rows->filter(fn($r) => in_array(strtolower((string)$r->payment_status), $paidStatuses))
                            ->sum('total_amount');

            fputcsv($out, ['', '', '', 'TOTAL', '', '', $formatRupiah($paidTotal), $rows->sum('total_items')]);
            fclose($out);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $q = $this->buildQuery($request);
        $rows = $q->orderBy('created_at', 'desc')->get();

        $invalidStatuses = ['canceled', 'cancelled', 'expired', 'expire', 'deny', 'failed', 'pending', 'waiting_for_payment'];
        $paidStatuses = ['paid', 'settlement', 'capture'];

        $formatRupiah = fn($value) => is_numeric($value) ? 'Rp ' . number_format($value, 0, ',', '.') : $value;

        // Filter baris yang valid untuk kalkulasi summary
        $paidRows = $rows->filter(function ($t) use ($invalidStatuses, $paidStatuses) {
            return !in_array(strtolower((string)$t->status), $invalidStatuses)
                && in_array(strtolower((string)$t->payment_status), $paidStatuses);
        });

        $summary = [
            'total_amount' => $paidRows->sum('total_amount'),
            'total_items' => $paidRows->sum('total_items'),
        ];

        $mappedRows = $rows->map(function ($t) use ($invalidStatuses, $paidStatuses) {
            $isInvalid = in_array(strtolower((string)$t->status), $invalidStatuses)
                        || !in_array(strtolower((string)$t->payment_status), $paidStatuses);

            $mappedRow = clone $t;

            if ($isInvalid) {
                $mappedRow->total_amount = 0;
                $mappedRow->total_items = 0;
            }

            return $mappedRow;
        });

        $data = [
            'title' => 'Laporan Transaksi',
            'generatedAt' => now()->toDateTimeString(),
            'rows' => $mappedRows,
            'summary' => $summary,
            'formatRupiah' => $formatRupiah,
        ];

        if (class_exists(\Dompdf\Dompdf::class)) {
            $html = view('reports.pdf', $data)->render();
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan_'.now()->format('Ymd_His').'.pdf"',
            ]);
        }

        return view('reports.pdf', $data);
    }
}
