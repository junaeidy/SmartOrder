import { Head, router } from '@inertiajs/react';
import React, { useState, useEffect, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CheckCircle, XCircle, AlertCircle, Clock } from 'lucide-react';

const Orders = ({ pendingOrders, completedOrders, auth }) => {
    const [orders, setOrders] = useState(pendingOrders);
    const [processing, setProcessing] = useState(false);
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [activeTab, setActiveTab] = useState('pending');
    const [currentTime, setCurrentTime] = useState(new Date());
    const [searchQuery, setSearchQuery] = useState('');
    const [filteredOrders, setFilteredOrders] = useState(completedOrders);
    const [currentPage, setCurrentPage] = useState(1);
    const [ordersPerPage] = useState(10);
    const audioRef = useRef(null);
    const [audioPermissionGranted, setAudioPermissionGranted] = useState(false);
    
    useEffect(() => {
        const clockInterval = setInterval(() => {
            setCurrentTime(new Date());
        }, 1000);
        
    const channel = window.Echo.channel('orders');
        
        // Listen for new orders
        channel.listen('.NewOrderReceived', (e) => {
            setOrders(prev => {
                const exists = prev.some(o => o.id === e.transaction.id);
                return exists ? prev : [...prev, e.transaction];
            });
            
            // Play sound for new orders
                const audio = new Audio('/sounds/notification.wav');
                audio.volume = 0.8;
                audio.play().catch(error => {
            });
        });

        // Listen for status changes but don't play sound
        channel.listen('.OrderStatusChanged', (e) => {
            if (e?.transaction) {
                setOrders(prev => {
                    // Remove order if it's moved to awaiting_confirmation
                    if (e.transaction.status === 'awaiting_confirmation') {
                        return prev.filter(o => o.id !== e.transaction.id);
                    }
                    // Update the order if status changed
                    return prev.map(o => o.id === e.transaction.id ? e.transaction : o);
                });
            }
        });
            
        return () => {
            clearInterval(clockInterval);
            window.Echo.leave('orders');
        };
    }, []);

    // Check and request audio permission helpers
    const requestAudioPermission = async () => {
        try {
            const tempAudio = new Audio('/sounds/notification.wav');
            tempAudio.volume = 0;
            await tempAudio.play();
            tempAudio.pause();
            tempAudio.currentTime = 0;
            setAudioPermissionGranted(true);
            
        } catch (error) {
            setAudioPermissionGranted(false);
        }
    };

    useEffect(() => {
        // Attempt silent play to detect permission on mount
        (async () => {
            try {
                const a = new Audio('/sounds/notification.wav');
                a.volume = 0;
                await a.play();
                a.pause();
                a.currentTime = 0;
                setAudioPermissionGranted(true);
            } catch (e) {
                setAudioPermissionGranted(false);
            }
        })();
    }, []);
    
    useEffect(() => {
        if (searchQuery.trim() === '') {
            setFilteredOrders(completedOrders);
        } else {
            const lowercaseQuery = searchQuery.toLowerCase();
            const filtered = completedOrders.filter(order => 
                order.kode_transaksi.toLowerCase().includes(lowercaseQuery) ||
                order.queue_number.toString().includes(lowercaseQuery) ||
                order.customer_name.toLowerCase().includes(lowercaseQuery)
            );
            setFilteredOrders(filtered);
        }
        setCurrentPage(1);
    }, [searchQuery, completedOrders]);
    
    const handleProcessOrder = (order, status) => {
        if (processing) return;
        
        setProcessing(true);
        
        const completedTime = new Date();
        
        router.put(`/karyawan/orders/${order.id}`, {
            status: status
        }, {
            onSuccess: (page) => {
                setOrders(prev => prev.filter(o => o.id !== order.id));
                
                if (status === 'completed' && page.props.completedOrders) {
                    const completedOrder = {
                        ...order,
                        status: 'completed',
                        updated_at: completedTime.toISOString()
                    };
                    
                    router.reload({ only: ['completedOrders'] });
                }
                
                setSelectedOrder(null);
                setProcessing(false);
            },
            onError: () => {
                setProcessing(false);
            }
        });
    };
    
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(amount);
    };
    
    const formatDate = (dateString) => {
        const options = { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return new Date(dateString).toLocaleDateString('id-ID', options);
    };
    
    // Calculate elapsed time since order was created
    const calculateElapsedTime = (createdAt) => {
        const created = new Date(createdAt);
        const now = currentTime;
        
        const diffMs = now - created;
        const diffSecs = Math.floor(diffMs / 1000);
        const diffMins = Math.floor(diffSecs / 60);
        const diffHours = Math.floor(diffMins / 60);
        
        if (diffMins < 1) {
            return `${diffSecs} detik`;
        } else if (diffMins < 60) {
            const secs = diffSecs % 60;
            return `${diffMins}:${secs.toString().padStart(2, '0')}`;
        } else {
            const mins = diffMins % 60;
            return `${diffHours}:${mins.toString().padStart(2, '0')}:${(diffSecs % 60).toString().padStart(2, '0')}`;
        }
    };
    
    // Format time for real-time clock
    const formatTime = (date) => {
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        const seconds = date.getSeconds().toString().padStart(2, '0');
        
        return `${hours}:${minutes}:${seconds}`;
    };
    
    // Format date for clock header
    const formatClockDate = (date) => {
        const options = { 
            weekday: 'long', 
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        };
        return date.toLocaleDateString('id-ID', options);
    };
    
    // Pagination logic
    const indexOfLastOrder = currentPage * ordersPerPage;
    const indexOfFirstOrder = indexOfLastOrder - ordersPerPage;
    const currentOrders = filteredOrders.slice(indexOfFirstOrder, indexOfLastOrder);
    const totalPages = Math.ceil(filteredOrders.length / ordersPerPage);
    
    const paginate = (pageNumber) => {
        if (pageNumber > 0 && pageNumber <= totalPages) {
            setCurrentPage(pageNumber);
        }
    };
    
    const calculateProcessTime = (createdAt, updatedAt) => {
        const created = new Date(createdAt);
        const updated = new Date(updatedAt);
        
        const diffMs = updated - created;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) {
            const diffSecs = Math.floor(diffMs / 1000);
            return `${diffSecs} detik`;
        } else if (diffMins < 60) {
            return `${diffMins} menit`;
        } else {
            const hours = Math.floor(diffMins / 60);
            const mins = diffMins % 60;
            return `${hours} jam ${mins} menit`;
        }
    };
    
    const ConfirmationModal = ({ isOpen, onClose, onConfirm, order }) => {
        const [isAnimating, setIsAnimating] = useState(false);
        
        useEffect(() => {
            if (isOpen) {
                setIsAnimating(true);
            }
        }, [isOpen]);
        
        const handleClose = () => {
            setIsAnimating(false);
            setTimeout(() => {
                onClose();
            }, 300); 
        };
        
        if (!isOpen && !isAnimating) return null;
        
        return (
            <div className={`fixed inset-0 z-50 flex items-center justify-center transition-opacity duration-300 ${isOpen ? 'opacity-100' : 'opacity-0'}`}>
                {/* Backdrop with fade animation */}
                <div 
                    className={`absolute inset-0 bg-black transition-opacity duration-300 ${isOpen ? 'bg-opacity-70' : 'bg-opacity-0'}`} 
                    onClick={handleClose}
                ></div>
                
                {/* Modal with fade-in and scale animation */}
                <div className={`relative z-10 bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full mx-4 overflow-hidden transform transition-all duration-300 ${
                    isOpen ? 'opacity-100 scale-100' : 'opacity-0 scale-95'
                }`}>
                    {/* Header */}
                    <div className="bg-gray-100 dark:bg-gray-700 px-6 py-4 flex items-center">
                        <AlertCircle className="text-orange-500 dark:text-orange-400 w-6 h-6 mr-3" />
                        <h3 className="text-xl font-semibold text-gray-800 dark:text-white">Kirim ke Kasir</h3>
                    </div>
                    
                    {/* Body */}
                    <div className="px-6 py-5">
                        <p className={`text-gray-700 dark:text-gray-300 mb-6 transition-all duration-500 ${
                            isOpen ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-4'
                        }`}>
                            Apakah Anda ingin mengirim pesanan #{order?.queue_number} milik <span className="text-gray-900 dark:text-white font-medium">{order?.customer_name}</span> ke kasir untuk konfirmasi?
                        </p>
                        
                        {/* Order summary */}
                        <div className={`bg-gray-100 dark:bg-gray-700/50 rounded-lg p-4 mb-6 transition-all duration-500 delay-100 ${
                            isOpen ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-4'
                        }`}>
                            <div className="flex justify-between mb-2">
                                <span className="text-gray-600 dark:text-gray-400">Item:</span>
                                <span className="text-gray-900 dark:text-white">{order?.total_items}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600 dark:text-gray-400">Total:</span>
                                <span className="text-orange-500 dark:text-orange-400 font-medium">{formatCurrency(order?.total_amount)}</span>
                            </div>
                        </div>
                        
                        {/* Buttons */}
                        <div className={`flex space-x-3 transition-all duration-500 delay-200 ${
                            isOpen ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-4'
                        }`}>
                            <button 
                                onClick={() => {
                                    setIsAnimating(false);
                                    setTimeout(() => {
                                        onConfirm();
                                    }, 300);
                                }}
                                disabled={processing}
                                className={`flex-1 py-3 rounded-lg font-medium flex items-center justify-center text-white transition duration-200 ${
                                    processing 
                                        ? 'bg-gray-500 dark:bg-gray-600 cursor-not-allowed' 
                                        : 'bg-green-600 hover:bg-green-700'
                                }`}
                            >
                                <CheckCircle className="w-5 h-5 mr-2" />
                                <span>{processing ? 'Memproses...' : 'Kirim ke Kasir'}</span>
                            </button>
                            <button 
                                onClick={handleClose}
                                className="py-3 px-4 rounded-lg text-gray-900 dark:text-white bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 flex items-center justify-center transition duration-200"
                                disabled={processing}
                            >
                                <XCircle className="w-5 h-5 mr-2" />
                                <span>Batal</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        );
    };
    
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col md:flex-row justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Manajemen Pesanan
                    </h2>
                    <div className="flex items-center space-x-2 text-sm md:text-base">
                        <Clock className="h-5 w-5 text-orange-500 animate-pulse" />
                        <div className="flex flex-col md:flex-row md:space-x-2">
                            <span className="font-mono text-gray-800 dark:text-gray-200">{formatTime(currentTime)}</span>
                            <span className="text-gray-600 dark:text-gray-400">{formatClockDate(currentTime)}</span>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title="Pesanan Karyawan" />
            
            {/* Audio permission banner */}
            {!audioPermissionGranted && (
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div className="flex">
                            <div className="flex-shrink-0">
                                <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                </svg>
                            </div>
                            <div className="ml-3">
                                <p className="text-sm text-yellow-700">
                                    Notifikasi suara tidak aktif.
                                    <button onClick={requestAudioPermission} className="ml-2 font-medium text-yellow-700 underline hover:text-yellow-600">
                                        Aktifkan notifikasi
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Confirmation Modal */}
            <ConfirmationModal 
                isOpen={showConfirmModal} 
                onClose={() => setShowConfirmModal(false)}
                onConfirm={() => {
                    handleProcessOrder(selectedOrder, 'awaiting_confirmation');
                    setShowConfirmModal(false);
                }}
                order={selectedOrder}
            />
            
            {/* Audio for notification */}
            <audio ref={audioRef} preload="auto">
                <source src="/sounds/notification.wav" type="audio/mpeg" />
                Your browser does not support the audio element.
            </audio>
                
                {/* Main Content */}
                <div className="py-12">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    
                    {/* Tab Navigation */}
                    <div className="mb-6 border-b border-gray-700">
                        <nav className="flex space-x-8">
                            <button
                                onClick={() => setActiveTab('pending')}
                                className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 ${
                                    activeTab === 'pending' 
                                        ? 'border-orange-500 text-orange-500' 
                                        : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-400'
                                }`}
                            >
                                Pesanan Tertunda
                                <span className="ml-2 bg-orange-500 px-2 py-0.5 rounded-full text-xs text-white">
                                    {orders.length}
                                </span>
                            </button>
                            
                            <button
                                onClick={() => setActiveTab('completed')}
                                className={`py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 ${
                                    activeTab === 'completed' 
                                        ? 'border-green-500 text-green-500' 
                                        : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-400'
                                }`}
                            >
                                Pesanan Selesai
                                <span className="ml-2 bg-green-600 px-2 py-0.5 rounded-full text-xs text-white">
                                    {completedOrders.length}
                                </span>
                            </button>
                        </nav>
                    </div>
                    
                    {activeTab === 'pending' && (
                        <>
                            <div className="mb-2 flex justify-between items-center">
                                <h2 className="text-xl font-semibold">Pesanan Tertunda</h2>
                                <span className="bg-orange-500 px-3 py-1 rounded-full text-sm font-medium">
                                    {orders.length} Antrian
                                </span>
                            </div>
                            
                            <div className="mb-6 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-blue-800 dark:text-blue-200">
                                <p>Klik kartu pesanan untuk mengirim ke kasir (menunggu konfirmasi). Pesanan baru akan muncul otomatis disertai suara notifikasi.</p>
                            </div>
                        </>
                    )}
                    
                    {activeTab === 'pending' && (
                        orders.length === 0 ? (
                            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 text-center">
                                <p className="text-gray-600 dark:text-gray-400">Tidak ada pesanan tertunda saat ini.</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                {orders.map(order => (
                                    <div key={order.id}>
                                        <div 
                                            className="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden border-l-4 border-orange-500 transition-all hover:shadow-lg cursor-pointer"
                                            onClick={() => {
                                                setSelectedOrder(order);
                                                setShowConfirmModal(true);
                                            }}
                                    >
                                        <div className="p-5">
                                            <div className="flex justify-between items-center mb-4">
                                                <div className="flex items-center">
                                                    <span className="bg-orange-500/20 text-orange-400 px-3 py-1.5 rounded text-base font-bold">
                                                        #{order.queue_number}
                                                    </span>
                                                    <div className="flex items-center ml-2 bg-blue-100 dark:bg-blue-900/30 px-2 py-1 rounded">
                                                        <Clock className="w-3 h-3 text-blue-600 dark:text-blue-300 mr-1" />
                                                        <span className="text-xs text-blue-700 dark:text-blue-300">
                                                            {new Date(order.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                                        </span>
                                                    </div>
                                                </div>
                                                <span className="text-xs text-gray-400">
                                                    {formatDate(order.created_at).split(' ').slice(0, -1).join(' ')}
                                                </span>
                                            </div>
                                            
                                            <h3 className="font-bold text-lg mb-1 text-gray-900 dark:text-white">{order.customer_name}</h3>
                                            <p className="text-gray-600 dark:text-gray-400 text-sm mb-3">{order.customer_phone}</p>
                                            
                                            <div className="space-y-2 mb-4">
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600 dark:text-gray-400">Item:</span>
                                                    <span className="font-medium text-gray-900 dark:text-white">{order.total_items}</span>
                                                </div>
                                                <div className="flex justify-between items-center">
                                                    <span className="text-gray-600 dark:text-gray-400">Waktu:</span>
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="w-4 h-4 text-orange-500 animate-pulse" />
                                                        <span className="font-bold text-orange-600 dark:text-orange-400 font-mono">
                                                            {calculateElapsedTime(order.created_at)}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div className="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                                <div className="max-h-24 overflow-y-auto pr-1">
                                                    {order.items.map((item, idx) => (
                                                        <div key={idx} className="truncate">
                                                            • {item.quantity}x {item.nama}
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                            
                                            {order.customer_notes && (
                                                <div className="bg-gray-100 dark:bg-gray-700 p-2 rounded text-sm mt-2 border-l-2 border-orange-500">
                                                    <p className="text-gray-700 dark:text-gray-300 text-xs mb-1">Catatan:</p>
                                                    <p className="text-gray-900 dark:text-gray-100">{order.customer_notes}</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ))}
                    
                    {/* Completed Orders Table */}
                    {activeTab === 'completed' && (
                        <>
                            <div className="mb-4 flex flex-col md:flex-row md:justify-between md:items-center space-y-4 md:space-y-0">
                                <div className="flex items-center">
                                    <h2 className="text-xl font-semibold">Pesanan Selesai Hari Ini</h2>
                                    <span className="ml-3 bg-green-600 px-3 py-1 rounded-full text-sm font-medium">
                                        {completedOrders.length} Selesai
                                    </span>
                                </div>
                                
                                {/* Search Bar */}
                                <div className="relative w-full md:w-64">
                                    <input
                                        type="text"
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        placeholder="Cari pesanan..."
                                        className="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm pl-10 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    <div className="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                                {filteredOrders.length === 0 ? (
                                    <div className="p-8 text-center">
                                        <CheckCircle className="mx-auto h-12 w-12 text-gray-400" />
                                        <h3 className="mt-2 text-lg font-medium text-gray-900 dark:text-white">Tidak ada pesanan selesai</h3>
                                        <p className="mt-1 text-gray-600 dark:text-gray-400">
                                            {searchQuery ? "Coba kata kunci lain" : "Pesanan yang selesai akan muncul di sini"}
                                        </p>
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead className="bg-gray-50 dark:bg-gray-700">
                                                <tr>
                                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                                        Kode Transaksi
                                                    </th>
                                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                                        No. Antrian
                                                    </th>
                                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                                        Customer
                                                    </th>
                                                    <th scope="col" className="px-6 py-3 text-center text-xs font-medium text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                                        Item
                                                    </th>
                                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                                        Diterima
                                                    </th>
                                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                                                        Selesai
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                {currentOrders.map((order) => (
                                                    <tr key={order.id} className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="text-sm font-medium text-gray-900 dark:text-white">{order.kode_transaksi}</div>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                #{order.queue_number}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="text-sm text-gray-900 dark:text-white">{order.customer_name}</div>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap text-center">
                                                            <div className="text-sm text-gray-900 dark:text-white">{order.total_items}</div>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="text-sm text-gray-600 dark:text-gray-300">
                                                                {new Date(order.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                                            </div>
                                                        </td>
                                                        <td className="px-6 py-4 whitespace-nowrap">
                                                            <div className="text-sm text-gray-600 dark:text-gray-300">
                                                                {new Date(order.updated_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                                                <span className="block text-xs text-green-400 mt-1">
                                                                    ({calculateProcessTime(order.created_at, order.updated_at)})
                                                                </span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                        
                                        {/* Pagination */}
                                        {totalPages > 1 && (
                                            <div className="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                                                <div className="flex items-center justify-between">
                                                    <div className="hidden sm:block">
                                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                                            Menampilkan <span className="font-medium text-gray-900 dark:text-white">{indexOfFirstOrder + 1}</span> hingga{" "}
                                                            <span className="font-medium text-gray-900 dark:text-white">
                                                                {Math.min(indexOfLastOrder, filteredOrders.length)}
                                                            </span>{" "}
                                                            dari <span className="font-medium text-gray-900 dark:text-white">{filteredOrders.length}</span> pesanan
                                                        </p>
                                                    </div>
                                                    
                                                    <div className="flex justify-between sm:justify-end">
                                                        <button
                                                            onClick={() => paginate(currentPage - 1)}
                                                            disabled={currentPage === 1}
                                                            className={`relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md ${
                                                                currentPage === 1
                                                                ? 'bg-gray-200 dark:bg-gray-800 text-gray-500 cursor-not-allowed'
                                                                : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-600'
                                                            } mr-2`}
                                                        >
                                                            Sebelumnya
                                                        </button>
                                                        
                                                        <div className="hidden md:flex">
                                                            {[...Array(totalPages)].map((_, i) => (
                                                                <button
                                                                    key={i}
                                                                    onClick={() => paginate(i + 1)}
                                                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-medium ${
                                                                        currentPage === i + 1
                                                                        ? 'bg-orange-500 text-white'
                                                                        : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-600'
                                                                    } ${i !== totalPages - 1 ? 'mr-2' : ''}`}
                                                                >
                                                                    {i + 1}
                                                                </button>
                                                            ))}
                                                        </div>
                                                        
                                                        <div className="flex md:hidden items-center px-4">
                                                            <span className="text-gray-600 dark:text-gray-400">
                                                                Halaman {currentPage} dari {totalPages}
                                                            </span>
                                                        </div>
                                                        
                                                        <button
                                                            onClick={() => paginate(currentPage + 1)}
                                                            disabled={currentPage === totalPages}
                                                            className={`relative inline-flex items-center px-4 py-2 text-sm font-medium rounded-md ${
                                                                currentPage === totalPages
                                                                ? 'bg-gray-200 dark:bg-gray-800 text-gray-500 cursor-not-allowed'
                                                                : 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-gray-600'
                                                            }`}
                                                        >
                                                            Berikutnya
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                    
                </div>
            </div>
        </AuthenticatedLayout>
    );
};

export default Orders;