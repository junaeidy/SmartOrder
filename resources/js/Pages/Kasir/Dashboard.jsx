import React, { useEffect, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, LineChart, Line, PieChart, Pie, Cell } from 'recharts';
import { DollarSign, ShoppingBag, TrendingUp, Clock, Clock1, FastForward } from 'lucide-react';

const currencyIDR = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);

const COLORS = ['#f97316', '#22c55e', '#3b82f6', '#a855f7', '#ef4444', '#14b8a6'];

const StatCard = ({ title, value, icon: Icon, colorClass, description }) => (
    <div className={`bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg overflow-hidden border-l-4 ${colorClass}`}>
        <div className="flex items-start justify-between">
            <div>
                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">{title}</p>
                <p className={`mt-1 text-3xl font-extrabold ${colorClass.replace('-l-4', '').replace('border-', 'text-')}`}>
                    {value}
                </p>
            </div>
            {Icon && (
                <div className={`p-3 rounded-full ${colorClass.replace('border-', 'bg-').replace('-l-4', '-100/50 dark:bg-gray-700')}`}>
                    <Icon className={`w-6 h-6 ${colorClass.replace('-l-4', '').replace('border-', 'text-')}`} />
                </div>
            )}
        </div>
        {description && <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">{description}</p>}
    </div>
);

export default function Dashboard({ stats, charts, auth }) {
    const paymentData = (charts?.paymentBreakdown || []).map(p => ({ name: (p.method || 'UNKNOWN').toUpperCase(), value: p.orders }));
    const hourlyData = charts?.ordersByHour || [];
    const last7DaysData = charts?.last7Days || [];
    const topProducts = charts?.topProducts || [];
    const avgCompletionByDay = charts?.avgCompletionByDay || [];

    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={<h2 className="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-50">Dashboard Admin</h2>}
        >
            <Head title="Admin Dashboard" />

            {/* Realtime updates via Echo: reload stats/charts without full page refresh */}
            <RealtimeUpdater />

            <div className="py-6 sm:py-10">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    {/* KPI Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <StatCard
                            title="Pendapatan Hari Ini"
                            value={currencyIDR(stats?.todayRevenue)}
                            icon={DollarSign}
                            colorClass="border-orange-500"
                            description={`Total ${stats?.todayOrders || 0} pesanan berhasil.`}
                        />
                        <StatCard
                            title="Jumlah Pesanan Hari Ini"
                            value={stats?.todayOrders || 0}
                            icon={ShoppingBag}
                            colorClass="border-blue-500"
                            description={`Rata-rata order: ${currencyIDR(stats?.avgOrderValue)}`}
                        />
                        <StatCard
                            title="Rata-Rata Nilai Order"
                            value={currencyIDR(stats?.avgOrderValue)}
                            icon={TrendingUp}
                            colorClass="border-green-500"
                            description="Nilai rata-rata transaksi per pelanggan."
                        />
                        <StatCard
                            title="Antrian Menunggu"
                            value={stats?.pendingCount || 0}
                            icon={Clock}
                            colorClass="border-red-500"
                            description="Pesanan yang belum selesai/menunggu pembayaran."
                        />
                    </div>
                    
                    {/* --- */}
                    
                    {/* Charts Grid */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* CHART 1: Orders per Hour (Bar Chart) */}
                        <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg h-[400px]">
                            <h3 className="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">
                                Pesanan per Jam (Aktivitas Harian)
                            </h3>
                            <ResponsiveContainer width="100%" height="90%">
                                <BarChart data={hourlyData} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="hour" tick={{ fontSize: 12 }} />
                                    <YAxis allowDecimals={false} label={{ value: 'Jumlah Pesanan', angle: -90, position: 'insideLeft', fontSize: 12, fill: '#6b7280' }} />
                                    <Tooltip />
                                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                                    <Bar dataKey="orders" fill="#f97316" name="Pesanan" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                        
                        {/* CHART 2: Payment Method Breakdown (Pie Chart) */}
                        <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg h-[400px]">
                            <h3 className="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">
                                Distribusi Pembayaran Hari Ini
                            </h3>
                            <ResponsiveContainer width="100%" height="90%">
                                <PieChart>
                                    <Pie 
                                        data={paymentData} 
                                        dataKey="value" 
                                        nameKey="name" 
                                        cx="50%" 
                                        cy="50%" 
                                        outerRadius={100} 
                                        fill="#8884d8" 
                                        label={({ name, percent }) => `${name} (${(percent * 100).toFixed(0)}%)`}
                                    >
                                        {paymentData.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                    <Legend layout="vertical" verticalAlign="bottom" align="center" wrapperStyle={{ fontSize: '12px' }} />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                        
                        {/* --- */}
                        
                        {/* BOTTOM ROW: 3 kolom */}
                        <div className="lg:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-6">
                            {/* CHART 3: Last 7 Days Revenue (Line Chart) */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg h-80 md:col-span-1">
                                <h3 className="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-200">
                                    Pendapatan 7 Hari Terakhir
                                </h3>
                                <ResponsiveContainer width="100%" height="80%">
                                    <LineChart data={last7DaysData} margin={{ top: 5, right: 20, left: 10, bottom: 5 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="date" tick={{ fontSize: 10 }} />
                                        <YAxis tickFormatter={currencyIDR} domain={['dataMin', 'dataMax']} />
                                        <Tooltip formatter={(v) => [currencyIDR(v), 'Pendapatan']} />
                                        <Line type="monotone" dataKey="revenue" stroke="#3b82f6" name="Pendapatan" strokeWidth={3} dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
                            </div>
                            
                            {/* TABLE: Top 5 Produk Terlaris */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg h-80 md:col-span-1">
                                <h3 className="text-lg font-semibold mb-3 text-gray-800 dark:text-gray-200">
                                    Top 5 Produk Terlaris
                                </h3>
                                <div className="h-[calc(100%-2rem)] overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead className="bg-gray-50 dark:bg-gray-700/50">
                                            <tr>
                                                <th scope="col" className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">No</th>
                                                <th scope="col" className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Produk</th>
                                                <th scope="col" className="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qty Terjual</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            {topProducts && topProducts.length > 0 ? (
                                                topProducts.slice(0, 5).map((p, idx) => (
                                                    <tr key={p.name + idx}>
                                                        <td className="px-4 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{idx + 1}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{p.name}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{p.quantity}</td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={3} className="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* CHART 5: Average Completion Time per Day (Line Chart) */}
                            <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg h-80 md:col-span-1">
                                <h3 className="text-lg font-semibold mb-1 text-gray-800 dark:text-gray-200">
                                    Waktu Penyelesaian Rata-rata
                                </h3>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mb-3 flex items-center">
                                    <FastForward className="w-3 h-3 mr-1 text-red-500" />
                                    Rata-rata Hari Ini: <span className="font-semibold ml-1">{stats?.overallAvgCompletionHuman ?? '0 menit'}</span>
                                </p>
                                <ResponsiveContainer width="100%" height="80%">
                                    <LineChart data={avgCompletionByDay} margin={{ top: 5, right: 20, left: 10, bottom: 5 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="date" tick={{ fontSize: 10 }} />
                                        <YAxis allowDecimals={false} label={{ value: 'Menit', angle: -90, position: 'insideLeft', fontSize: 12, fill: '#6b7280' }} />
                                        <Tooltip formatter={(v) => [`${v} menit`, 'Rata-rata']} />
                                        <Line type="monotone" dataKey="avgMinutes" stroke="#ef4444" name="Rata-rata (menit)" strokeWidth={3} dot={false} />
                                    </LineChart>
                                </ResponsiveContainer>
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
                router.reload({ only: ['stats', 'charts'] });
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