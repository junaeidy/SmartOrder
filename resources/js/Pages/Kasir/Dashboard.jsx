import React, { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, Link } from '@inertiajs/react';
import { ShoppingBag, Clock, Check, ClipboardList, Search, ShoppingCart, AlertCircle, BarChart4, ChefHat, CheckCircle2 } from 'lucide-react';

const currencyIDR = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

const StatusBadge = ({ status, className = '' }) => {
    const baseClasses = "px-2 py-1 rounded-full text-xs font-medium";
    
    switch (status?.toLowerCase()) {
        case 'completed':
            return <span className={`${baseClasses} bg-green-100 text-green-800 dark:bg-green-800/30 dark:text-green-200 ${className}`}>Selesai</span>;
        case 'waiting':
            return <span className={`${baseClasses} bg-amber-100 text-amber-800 dark:bg-amber-800/30 dark:text-amber-200 ${className}`}>Menunggu</span>;
        case 'canceled':
            return <span className={`${baseClasses} bg-red-100 text-red-800 dark:bg-red-800/30 dark:text-red-200 ${className}`}>Dibatalkan</span>;
        case 'paid':
        case 'settlement':
        case 'capture':
            return <span className={`${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-800/30 dark:text-blue-200 ${className}`}>Dibayar</span>;
        default:
            return <span className={`${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 ${className}`}>{status || 'N/A'}</span>;
    }
};

const StatCard = ({ title, value, icon: Icon, color }) => (
    <div className={`bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-${color}-100 dark:border-${color}-900/20`}>
        <div className="flex items-center gap-4">
            <div className={`p-3 rounded-full bg-${color}-100 dark:bg-${color}-900/30 text-${color}-600 dark:text-${color}-400`}>
                <Icon className="w-6 h-6" />
            </div>
            <div>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">{value}</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">{title}</p>
            </div>
        </div>
    </div>
);

const ActionCard = ({ title, description, icon: Icon, color, to, buttonText }) => (
    <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700">
        <div className="flex items-start gap-4">
            <div className={`p-3 rounded-full bg-${color}-100 dark:bg-${color}-900/30 text-${color}-600 dark:text-${color}-400 flex-shrink-0`}>
                <Icon className="w-7 h-7" />
            </div>
            <div className="flex-grow">
                <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-1">{title}</h3>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">{description}</p>
                <Link href={to} className={`inline-flex items-center px-4 py-2 bg-${color}-600 hover:bg-${color}-700 text-white font-medium rounded-lg text-sm transition-colors`}>
                    {buttonText}
                </Link>
            </div>
        </div>
    </div>
);

export default function Dashboard({ stats, topProducts, recentTransactions, auth }) {
    const [searchQuery, setSearchQuery] = useState('');
    const [filteredTransactions, setFilteredTransactions] = useState(recentTransactions || []);

    // Filter transactions when search query changes
    useEffect(() => {
        if (!recentTransactions) return;
        
        if (!searchQuery) {
            setFilteredTransactions(recentTransactions);
            return;
        }
        
        const query = searchQuery.toLowerCase();
        const filtered = recentTransactions.filter(tx => 
            tx.kode_transaksi.toLowerCase().includes(query) || 
            (tx.customer_name && tx.customer_name.toLowerCase().includes(query))
        );
        setFilteredTransactions(filtered);
    }, [searchQuery, recentTransactions]);

    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={<h2 className="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-50">Dashboard Kasir</h2>}
        >
            <Head title="Kasir Dashboard" />

            {/* Realtime updates via Echo: reload stats/charts without full page refresh */}
            <RealtimeUpdater />

            <div className="py-6 sm:py-8">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    
                    {/* Quick Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <StatCard 
                            title="Total Pesanan Hari Ini" 
                            value={stats?.todayOrders || 0} 
                            icon={ShoppingCart} 
                            color="blue" 
                        />
                        <StatCard 
                            title="Pesanan Menunggu" 
                            value={stats?.pendingCount || 0} 
                            icon={Clock} 
                            color="amber" 
                        />
                        <StatCard 
                            title="Pesanan Selesai Hari Ini" 
                            value={stats?.completedToday || 0} 
                            icon={Check} 
                            color="green" 
                        />
                    </div>

                    {/* Quick Actions Row */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <ActionCard
                            title="Kelola Transaksi"
                            description="Konfirmasi pesanan masuk dan kelola status pembayaran untuk memastikan proses yang lancar."
                            icon={ShoppingBag}
                            color="indigo"
                            to={route("kasir.transaksi")}
                            buttonText="Lihat Transaksi"
                        />
                        <ActionCard
                            title="Laporan & Statistik"
                            description="Akses laporan transaksi, filter berdasarkan tanggal, dan unduh laporan dalam format Excel atau PDF."
                            icon={BarChart4}
                            color="emerald"
                            to={route("kasir.reports")}
                            buttonText="Lihat Laporan"
                        />
                    </div>

                    {/* Content Area - Split View */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Produk Terlaris */}
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                            <div className="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center">
                                <ChefHat className="w-5 h-5 text-amber-500 mr-2" />
                                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">Produk Terlaris Hari Ini</h3>
                            </div>
                            <div className="p-0">
                                {topProducts && topProducts.length > 0 ? (
                                    <div className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {topProducts.slice(0, 5).map((product, idx) => (
                                            <div key={idx} className="flex items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                <div className="w-8 h-8 flex items-center justify-center bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-full font-bold text-sm mr-3">
                                                    {idx + 1}
                                                </div>
                                                <div className="flex-grow">
                                                    <h4 className="font-medium text-gray-900 dark:text-gray-100">{product.name}</h4>
                                                </div>
                                                <div className="text-right">
                                                    <span className="inline-block px-3 py-1 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded-full font-medium text-sm">
                                                        {product.quantity} terjual
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                                        <AlertCircle className="w-12 h-12 mx-auto mb-3 opacity-40" />
                                        <p>Belum ada transaksi hari ini</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Recent Transactions */}
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden lg:col-span-2">
                            <div className="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                <div className="flex items-center">
                                    <ClipboardList className="w-5 h-5 text-blue-500 mr-2" />
                                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">Transaksi Terbaru</h3>
                                </div>
                                <div className="relative w-64">
                                    <input
                                        type="text"
                                        placeholder="Cari kode/nama..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500"
                                    />
                                    <Search className="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                                </div>
                            </div>
                            <div className="overflow-x-auto">
                                {filteredTransactions && filteredTransactions.length > 0 ? (
                                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead className="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Kode</th>
                                                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Pelanggan</th>
                                                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Waktu</th>
                                                <th scope="col" className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                                <th scope="col" className="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            {filteredTransactions.map((tx) => (
                                                <tr key={tx.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                    <td className="px-4 py-3 text-sm font-medium text-blue-600 dark:text-blue-400 font-mono">{tx.kode_transaksi}</td>
                                                    <td className="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{tx.customer_name || '-'}</td>
                                                    <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{tx.created_at}</td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge status={tx.is_paid ? tx.payment_status : tx.status} />
                                                    </td>
                                                    <td className="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-gray-100">
                                                        {tx.is_paid ? currencyIDR(tx.total_amount) : '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                ) : (
                                    <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                                        {searchQuery ? (
                                            <>
                                                <Search className="w-12 h-12 mx-auto mb-3 opacity-40" />
                                                <p>Tidak ada transaksi yang sesuai dengan pencarian</p>
                                            </>
                                        ) : (
                                            <>
                                                <AlertCircle className="w-12 h-12 mx-auto mb-3 opacity-40" />
                                                <p>Belum ada transaksi terbaru</p>
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>
                            
                            {/* View All Link */}
                            <div className="p-4 bg-gray-50 dark:bg-gray-700/30 text-center">
                                <Link 
                                    href={route("kasir.transaksi")} 
                                    className="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium text-sm inline-flex items-center"
                                >
                                    Lihat Semua Transaksi
                                    <svg className="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function RealtimeUpdater() {
    const debounceRef = useRef(null);

    useEffect(() => {
        if (!window.Echo) return;
        const channel = window.Echo.channel('orders');

        const trigger = () => {
            if (debounceRef.current) return;
            debounceRef.current = setTimeout(() => {
                router.reload({ only: ['stats', 'topProducts', 'recentTransactions'] });
                clearTimeout(debounceRef.current);
                debounceRef.current = null;
            }, 500);
        };

        channel.listen('NewOrderReceived', trigger);
        channel.listen('OrderStatusChanged', trigger);

        return () => {
            try { window.Echo.leave('orders'); } catch (_) {}
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
                debounceRef.current = null;
            }
        };
    }, []);

    return null;
}
