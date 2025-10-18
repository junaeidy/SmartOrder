import React, { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import Modal from '@/Components/Modal';

// Import ikon dari lucide-react
import { Search, Filter, FileText, FileBarChart, DollarSign, ShoppingBag, CreditCard, ArrowLeft, ArrowRight, Eye } from 'lucide-react';

const currencyIDR = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

// Helper untuk Badge Status
const getStatusBadge = (status) => {
    switch (status?.toLowerCase()) {
        case 'completed':
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">Completed</span>;
        case 'waiting':
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">Waiting</span>;
        case 'canceled':
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">Canceled</span>;
        default:
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 capitalize">{status || 'N/A'}</span>;
    }
};

const getPaymentMethodIcon = (method) => {
    switch (method?.toLowerCase()) {
        case 'cash':
            return <span className="flex items-center text-green-600 dark:text-green-400 font-semibold">Cash</span>;
        case 'online':
            return <span className="flex items-center text-blue-600 dark:text-blue-400 font-semibold"> Online</span>;
        default:
            return <span className="capitalize">{method}</span>;
    }
};

// Komponen Kartu Ringkasan
const SummaryCard = ({ title, value, icon: Icon, colorClass, subText }) => (
    <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg flex justify-between items-center transition duration-200 hover:shadow-xl">
        <div>
            <p className="text-sm font-medium text-gray-500 dark:text-gray-400">{title}</p>
            <p className={`mt-1 text-3xl font-extrabold ${colorClass}`}>{value}</p>
        </div>
        <div className={`p-3 rounded-full ${colorClass.replace('text-', 'bg-').replace('dark:text-', 'dark:bg-').replace('-600', '-100')}`}>
            <Icon className={`w-7 h-7 ${colorClass}`} />
        </div>
    </div>
);


export default function Reports({ filters, summary, transactions, pagination, auth }) {
    const [local, setLocal] = useState({
        from: filters.from || '',
        to: filters.to || '',
        payment_method: filters.payment_method || '',
        status: filters.status || '',
        search: filters.search || '',
    });

    const [showDetail, setShowDetail] = useState(false);
    const [selected, setSelected] = useState(null);

    // Hitung subtotal berdasarkan jumlah subtotal item (qty * price atau field subtotal)
    const itemsSubtotal = useMemo(() => {
        if (!selected || !Array.isArray(selected.items)) return 0;
        try {
            return selected.items.reduce((sum, it) => {
                const qty = Number(it?.quantity ?? it?.qty ?? 0);
                const price = Number(it?.harga ?? it?.price ?? 0);
                const sub = Number(it?.subtotal != null ? it.subtotal : qty * price);
                return sum + (isNaN(sub) ? 0 : sub);
            }, 0);
        } catch (e) {
            return 0;
        }
    }, [selected]);

    const apply = () => {
        router.get(route('admin.reports'), local, { preserveState: true, replace: true });
    };

    const reset = () => {
        const cleared = { from: '', to: '', payment_method: '', status: '', search: '' };
        setLocal(cleared);
        router.get(route('admin.reports'), {}, { preserveState: false, replace: true });
    };

    const exportUrl = (type) => {
        const base = type === 'excel' ? route('admin.reports.export.excel') : route('admin.reports.export.pdf');
        const params = new URLSearchParams(local).toString();
        return `${base}?${params}`;
    };

    const rows = Array.isArray(transactions) ? transactions : (transactions?.data || []);

    const openDetail = (t) => {
        setSelected(t);
        setShowDetail(true);
    };
    const closeDetail = () => {
        setShowDetail(false);
        setSelected(null);
    };

    return (
        <AuthenticatedLayout 
            user={auth?.user} 
            header={<h2 className="text-2xl font-bold text-gray-900 dark:text-gray-50">Laporan Transaksi</h2>}
        >
            <Head title="Laporan" />

            <div className="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                {/* RINGKASAN DATA (SUMMARY) */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <SummaryCard
                        title="Total Transaksi Ditemukan"
                        value={summary.count || 0}
                        icon={ShoppingBag}
                        colorClass="text-indigo-600 dark:text-indigo-400"
                    />
                    <SummaryCard
                        title="Total Pendapatan"
                        value={currencyIDR(summary.total_amount)}
                        icon={DollarSign}
                        colorClass="text-green-600 dark:text-green-400"
                    />
                    <SummaryCard
                        title="Distribusi Pembayaran"
                        value={`${summary.cash_count || 0} / ${summary.online_count || 0}`}
                        icon={CreditCard}
                        colorClass="text-orange-600 dark:text-orange-400"
                        subText="Cash / Online"
                    />
                </div>

                {/* AREA FILTER DAN AKSI */}
                <div className="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-xl border border-gray-100 dark:border-gray-700">
                    <h3 className="text-xl font-semibold mb-5 flex items-center text-gray-800 dark:text-gray-100">
                        <Filter className="w-6 h-6 mr-2 text-indigo-500" />
                        Pencarian & Filter
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div className="md:col-span-2">
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cari (Kode / Customer)</label>
                            <div className="relative">
                                <input 
                                    type="text" 
                                    value={local.search} 
                                    onChange={(e) => setLocal({ ...local, search: e.target.value })} 
                                    placeholder="Masukkan kode atau nama customer" 
                                    className="w-full pl-10 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 placeholder-gray-400" 
                                />
                                <Search className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" />
                            </div>
                        </div>
                        
                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Dari Tanggal</label>
                            <input type="date" value={local.from} onChange={(e) => setLocal({ ...local, from: e.target.value })} className="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" />
                        </div>
                        
                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sampai Tanggal</label>
                            <input type="date" value={local.to} onChange={(e) => setLocal({ ...local, to: e.target.value })} className="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" />
                        </div>
                        
                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Metode Bayar</label>
                            <select value={local.payment_method} onChange={(e) => setLocal({ ...local, payment_method: e.target.value })} className="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                <option value="">Semua Metode</option>
                                <option value="cash">Cash</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                        
                        <div>
                            <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status Order</label>
                            <select value={local.status} onChange={(e) => setLocal({ ...local, status: e.target.value })} className="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                <option value="">Semua Status</option>
                                <option value="waiting">Waiting</option>
                                <option value="completed">Completed</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </div>
                    </div>
                    
                    {/* Tombol Aksi */}
                    <div className="mt-6 flex flex-wrap gap-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <button 
                            onClick={apply} 
                            className="px-5 py-2 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 flex items-center transition duration-150 shadow-md"
                        >
                            <Filter className="w-4 h-4 mr-2" />
                            Terapkan Filter
                        </button>
                        <button 
                            onClick={reset}
                            className="px-5 py-2 rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600 font-semibold transition duration-150 shadow-md"
                        >
                            Reset Filter
                        </button>
                        <a 
                            href={exportUrl('excel')} 
                            className="px-5 py-2 rounded-lg bg-green-600 text-white font-semibold hover:bg-green-700 flex items-center transition duration-150 shadow-md"
                        >
                            <FileBarChart className="w-4 h-4 mr-2" />
                            Export Excel/CSV
                        </a>
                        <a 
                            href={exportUrl('pdf')} 
                            className="px-5 py-2 rounded-lg bg-red-600 text-white font-semibold hover:bg-red-700 flex items-center transition duration-150 shadow-md"
                        >
                            <FileText className="w-4 h-4 mr-2" />
                            Export PDF
                        </a>
                    </div>
                </div>

                {/* TABEL DATA TRANSAKSI */}
                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Tanggal</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Kode Transaksi</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Metode</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status Bayar</th>
                                    <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status Order</th>
                                    <th className="px-4 py-3 text-right text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Items</th>
                                    <th className="px-4 py-3 text-right text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                    <th className="px-4 py-3 text-center text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                                {rows && rows.length > 0 ? (
                                    rows.map((t) => (
                                        <tr key={t.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-100">
                                            <td className="px-4 py-3 text-sm">{t.date}</td>
                                            <td className="px-4 py-3 text-sm font-mono text-indigo-600 dark:text-indigo-400">{t.kode_transaksi}</td>
                                            <td className="px-4 py-3 text-sm">{t.customer_name}</td>
                                            <td className="px-4 py-3 text-sm">{getPaymentMethodIcon(t.payment_method)}</td>
                                            <td className="px-4 py-3 text-sm">
                                                {/* Asumsi: payment_status sama dengan status order */}
                                                {getStatusBadge(t.payment_status)} 
                                            </td>
                                            <td className="px-4 py-3 text-sm">
                                                {getStatusBadge(t.status)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-right font-medium">{t.total_items}</td>
                                            <td className="px-4 py-3 text-sm text-right font-semibold">{t.is_paid ? currencyIDR(t.total_amount) : '-'}</td>
                                            <td className="px-4 py-3 text-sm text-center">
                                                <button
                                                    onClick={() => openDetail(t)}
                                                    className="inline-flex items-center px-2.5 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium shadow"
                                                    title="Lihat Detail"
                                                >
                                                    <Eye className="w-4 h-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-8 text-center text-base text-gray-500 dark:text-gray-400">
                                            <FileText className="w-8 h-8 mx-auto mb-2" />
                                            Tidak ada data transaksi yang ditemukan sesuai filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {pagination && pagination.total > pagination.per_page && (
                        <div className="p-4 bg-gray-50 dark:bg-gray-800/50 flex justify-between items-center border-t border-gray-200 dark:border-gray-700">
                            <span className="text-sm text-gray-600 dark:text-gray-400">
                                Menampilkan <strong>{(pagination.current_page - 1) * pagination.per_page + 1}</strong> sampai <strong>{Math.min(pagination.current_page * pagination.per_page, pagination.total)}</strong> dari <strong>{pagination.total}</strong> hasil.
                            </span>
                            <div className="space-x-2 flex">
                                <button 
                                    disabled={pagination.current_page <= 1} 
                                    onClick={() => router.get(route('admin.reports'), { ...filters, page: pagination.current_page - 1 }, { preserveState: true, replace: true })} 
                                    className="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center transition"
                                >
                                    <ArrowLeft className="w-4 h-4 mr-1" />
                                    Sebelumnya
                                </button>
                                <button 
                                    disabled={pagination.current_page >= pagination.last_page} 
                                    onClick={() => router.get(route('admin.reports'), { ...filters, page: pagination.current_page + 1 }, { preserveState: true, replace: true })} 
                                    className="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center transition"
                                >
                                    Berikutnya
                                    <ArrowRight className="w-4 h-4 ml-1" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>

                {/* DETAIL MODAL */}
                <Modal show={showDetail} onClose={closeDetail} maxWidth="2xl">
                    <div className="p-4 sm:p-6 max-h-[80vh] overflow-y-auto bg-white dark:bg-gray-900">
                        <div className="flex items-start justify-between mb-4 sticky top-0 z-10 bg-white dark:bg-gray-900 pb-2">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Detail Transaksi</h3>
                                {selected?.kode_transaksi && (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Kode: <span className="font-mono text-indigo-600 dark:text-indigo-400">{selected.kode_transaksi}</span></p>
                                )}
                            </div>
                            <button onClick={closeDetail} className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">✕</button>
                        </div>

                        {selected && (
                            <div className="space-y-4">
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div className="text-sm">
                                        <div className="text-gray-500 dark:text-gray-400">Tanggal</div>
                                        <div className="font-medium">{selected.date || '-'}</div>
                                    </div>
                                    <div className="text-sm">
                                        <div className="text-gray-500 dark:text-gray-400">Status Order</div>
                                        <div>{getStatusBadge(selected.status)}</div>
                                    </div>
                                    <div className="text-sm">
                                        <div className="text-gray-500 dark:text-gray-400">Customer</div>
                                        <div className="font-medium">{selected.customer_name || '-'}</div>
                                        <div className="text-gray-500 dark:text-gray-400">{selected.customer_phone || '-'}</div>
                                        <div className="text-gray-500 dark:text-gray-400">{selected.customer_email || '-'}</div>
                                    </div>
                                    <div className="text-sm">
                                        <div className="text-gray-500 dark:text-gray-400">Pembayaran</div>
                                        <div className="font-medium capitalize">{selected.payment_method}</div>
                                        <div className="mt-1">{getStatusBadge(selected.payment_status)}</div>
                                        <div className="text-gray-500 dark:text-gray-400 mt-1">Paid At: {selected.paid_at || '-'}</div>
                                    </div>
                                    <div className="text-sm">
                                        <div className="text-gray-500 dark:text-gray-400">Antrian</div>
                                        <div className="font-medium">{selected.queue_number ?? '-'}</div>
                                    </div>
                                    <div className="text-sm">
                                        <div className="text-gray-500 dark:text-gray-400">Catatan Customer</div>
                                        <div className="font-medium whitespace-pre-wrap">{selected.customer_notes || '-'}</div>
                                    </div>
                                </div>

                                {/* Items */}
                                <div className="mt-2">
                                    <h4 className="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Items</h4>
                                    <div className="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                        <div className="max-h-64 overflow-auto">
                                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead className="bg-gray-50 dark:bg-gray-700/50">
                                                    <tr>
                                                        <th className="px-3 py-2 text-left text-xs font-bold text-gray-600 dark:text-gray-300">Nama</th>
                                                        <th className="px-3 py-2 text-right text-xs font-bold text-gray-600 dark:text-gray-300">Qty</th>
                                                        <th className="px-3 py-2 text-right text-xs font-bold text-gray-600 dark:text-gray-300">Harga</th>
                                                        <th className="px-3 py-2 text-right text-xs font-bold text-gray-600 dark:text-gray-300">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                    {(selected.items || []).length > 0 ? (
                                                        selected.items.map((it, idx) => {
                                                            const name = it.nama || it.name || `Item ${idx+1}`;
                                                            const qty = Number(it.quantity || it.qty || 0);
                                                            const price = Number(it.harga || 0);
                                                            const subtotal = Number(it.subtotal != null ? it.subtotal : (qty * price));
                                                            return (
                                                                <tr key={idx}>
                                                                    <td className="px-3 py-2 text-sm">{name}</td>
                                                                    <td className="px-3 py-2 text-sm text-right">{qty}</td>
                                                                    <td className="px-3 py-2 text-sm text-right">{currencyIDR(price)}</td>
                                                                    <td className="px-3 py-2 text-sm text-right">{currencyIDR(subtotal)}</td>
                                                                </tr>
                                                            );
                                                        })
                                                    ) : (
                                                        <tr>
                                                            <td colSpan={4} className="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada item.</td>
                                                        </tr>
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                        {/* Totals area kept visible */}
                                        <div className="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 px-3 py-2 space-y-1">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700 dark:text-gray-200">Subtotal</span>
                                                <span className="text-sm text-gray-900 dark:text-gray-100">{currencyIDR(itemsSubtotal)}</span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700 dark:text-gray-200">Diskon</span>
                                                <span className="text-sm text-gray-900 dark:text-gray-100">- {currencyIDR(selected.discount_amount || 0)}</span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700 dark:text-gray-200">Pajak</span>
                                                <span className="text-sm text-gray-900 dark:text-gray-100">{currencyIDR(selected.tax_amount || 0)}</span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-semibold text-gray-700 dark:text-gray-200">Total</span>
                                                <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{selected.is_paid ? currencyIDR(selected.total_amount) : '-'}</span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-700 dark:text-gray-200">Total Items</span>
                                                <span className="text-sm text-gray-900 dark:text-gray-100">{selected.total_items}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Payment Details (Cash) */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                                    <div className="text-sm">
                                        <div className="text-gray-500 dark:text-gray-400">Metode Pembayaran</div>
                                        <div className="font-medium capitalize">{selected.payment_method}</div>
                                    </div>
                                    {selected.payment_method === 'cash' && (
                                        <>
                                            <div className="text-sm">
                                                <div className="text-gray-500 dark:text-gray-400">Uang Diterima</div>
                                                <div className="font-medium">{selected.amount_received ? currencyIDR(selected.amount_received) : '-'}</div>
                                            </div>
                                            <div className="text-sm">
                                                <div className="text-gray-500 dark:text-gray-400">Kembalian</div>
                                                <div className="font-medium">{selected.change_amount ? currencyIDR(selected.change_amount) : '-'}</div>
                                            </div>
                                        </>
                                    )}
                                </div>

                                <div className="mt-4 flex justify-end">
                                    <button onClick={closeDetail} className="px-4 py-2 rounded-md bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600 text-sm font-medium">Tutup</button>
                                </div>
                            </div>
                        )}
                    </div>
                </Modal>
            </div>
        </AuthenticatedLayout>
    );
}