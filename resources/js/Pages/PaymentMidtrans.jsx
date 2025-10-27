import React, { useEffect, useRef, useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';

// Import ikon dari lucide-react
import { Clock, CheckCircle, XCircle, ShoppingBag, DollarSign, RefreshCw, Loader2, ArrowLeft } from 'lucide-react';

export default function PaymentMidtrans({ transaction, snapToken, clientKey }) {
    const [timeLeft, setTimeLeft] = useState(900);
    const [paymentStatus, setPaymentStatus] = useState('pending');
    const [isLoading, setIsLoading] = useState(true);

    const redirectingRef = useRef(false);
    const timerRef = useRef(null);
    const statusCheckerRef = useRef(null);

    const cleanupTimers = () => {
        if (timerRef.current) {
            clearInterval(timerRef.current);
            timerRef.current = null;
        }
        if (statusCheckerRef.current) {
            clearInterval(statusCheckerRef.current);
            statusCheckerRef.current = null;
        }
    };

    const handlePaid = useCallback(() => {
        if (redirectingRef.current) return;
        setPaymentStatus('success');
        cleanupTimers();
        redirectingRef.current = true;
        // Penundaan singkat sebelum redirect untuk memastikan status 'success' terlihat
        setTimeout(() => {
             router.visit(`/thankyou/${transaction.id}`);
        }, 1500); 
    }, [transaction?.id]);

    const checkPaymentStatus = useCallback(async () => {
        try {
            const response = await fetch(`/api/payment/check-status/${transaction?.kode_transaksi}`);

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data.success) {
                const ps = data.transaction.payment_status;
                if (ps === 'paid' || ps === 'settlement' || ps === 'capture') {
                    handlePaid();
                } else if (ps === 'expired' || ps === 'cancelled') {
                    setPaymentStatus('expired');
                    cleanupTimers();
                    setTimeLeft(0);
                } else if (ps === 'pending') {
                    setPaymentStatus('pending');
                }
            }
        } catch (error) {
            console.error('Error checking payment status:', error);
        }
    }, [transaction?.kode_transaksi, handlePaid]);

    useEffect(() => {
        // Countdown timer
        timerRef.current = setInterval(() => {
            setTimeLeft(prev => {
                if (prev <= 1) {
                    if (timerRef.current) clearInterval(timerRef.current);
                    timerRef.current = null;
                    // Redirect to checkout when timer expires
                    router.visit('/checkout', {
                        method: 'get',
                        preserveState: false,
                        preserveScroll: false,
                        replace: true,
                    });
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);

        // Load Midtrans Snap.js
        const script = document.createElement('script');
        script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
        script.setAttribute('data-client-key', clientKey);
        document.body.appendChild(script);

        script.onload = () => {
            try {
                // Open Snap popup
                window.snap.pay(snapToken, {
                    onSuccess: () => {
                        handlePaid();
                    },
                    onPending: () => {
                        setPaymentStatus('pending');
                    },
                    onError: (result) => {
                        setPaymentStatus('error');
                    },
                    onClose: () => {
                        checkPaymentStatus();
                    },
                });
            } catch (error) {
                setPaymentStatus('error');
            } finally {
                setIsLoading(false);
            }
        };

        script.onerror = (error) => {
            setIsLoading(false);
            setPaymentStatus('error');
        };

        // Status checker interval
        statusCheckerRef.current = setInterval(() => {
            checkPaymentStatus();
        }, 5000);

        return () => {
            cleanupTimers();
            try {
                document.body.removeChild(script);
            } catch (_) {}
        };
    }, [snapToken, clientKey, checkPaymentStatus, handlePaid]);

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
    };

    const handleManualCheck = () => {
        setIsLoading(true);
        checkPaymentStatus().finally(() => setIsLoading(false));
    };

    const handleTryAgain = () => {
        try {
            window.snap?.pay(snapToken);
        } catch (error) {
            setPaymentStatus('error');
        }
    };

    // Tentukan Judul Utama berdasarkan Status
    let titleText = "Lanjutkan Pembayaran";
    let titleColor = "text-blue-600";
    if (paymentStatus === 'success') {
        titleText = "Pembayaran Berhasil! 🎉";
        titleColor = "text-green-600";
    } else if (paymentStatus === 'error') {
        titleText = "Pembayaran Gagal";
        titleColor = "text-red-600";
    } else if (timeLeft <= 0) {
        titleText = "Waktu Pembayaran Habis";
        titleColor = "text-red-600";
    }

    return (
        <MainLayout>
            <Head title="Pembayaran" />
            <div className="max-w-xl mx-auto sm:px-6 lg:px-8 py-12">
                <div className="bg-white overflow-hidden shadow-xl sm:rounded-lg border border-gray-200">
                    <div className="p-8 text-gray-900">
                        
                        <h1 className={`text-3xl font-extrabold mb-6 ${titleColor}`}>
                            {titleText}
                        </h1>

                        {/* Loading/Overlay Saat Memuat Snap */}
                        {isLoading && paymentStatus === 'pending' && (
                             <div className="flex flex-col items-center justify-center p-8 bg-blue-50 rounded-lg mb-6">
                                <Loader2 className="w-8 h-8 text-blue-500 animate-spin" />
                                <p className="mt-3 text-lg font-medium text-blue-700">Memuat Gerbang Pembayaran...</p>
                                <p className="text-sm text-blue-500">Jendela Midtrans Snap akan muncul sebentar lagi.</p>
                            </div>
                        )}
                        
                        {/* Detail Pesanan */}
                        <div className="bg-gray-50 border border-gray-200 p-5 rounded-lg mb-6">
                            <div className="flex items-center mb-3">
                                <ShoppingBag className="w-5 h-5 text-gray-600 mr-2" />
                                <h2 className="font-bold text-xl text-gray-800">Detail Pesanan</h2>
                            </div>
                            <div className="space-y-2">
                                <div className="flex justify-between border-b pb-2">
                                    <div className="text-gray-600">No. Pesanan</div>
                                    <div className="font-semibold text-gray-900">{transaction?.kode_transaksi || '-'}</div>
                                </div>
                                <div className="flex justify-between pt-2">
                                    <div className="text-lg font-bold text-gray-700">Total Pembayaran</div>
                                    <div className="text-xl font-extrabold text-blue-600">
                                        <div className="flex items-center">
                                            <DollarSign className="w-5 h-5 mr-1" />
                                            Rp {Number(transaction?.total_amount || 0).toLocaleString('id-ID')}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Pemberitahuan Status Dinamis */}
                        {timeLeft > 0 && paymentStatus === 'pending' && (
                            <div className="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-md mb-6 shadow-sm">
                                <div className="flex items-center">
                                    <Clock className="w-5 h-5 text-yellow-500 flex-shrink-0" />
                                    <div className="ml-3">
                                        <p className="text-base font-medium text-yellow-800">
                                            Menunggu Pembayaran.
                                        </p>
                                        <p className="text-sm text-yellow-700 mt-1">
                                            Waktu tersisa untuk menyelesaikan pembayaran: <span className="font-extrabold text-lg">{formatTime(timeLeft)}</span>
                                        </p>
                                        <p className="text-xs text-yellow-600 mt-1">
                                            Pastikan jendela pop-up pembayaran Midtrans (Snap) tidak terblokir.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                        
                        {timeLeft <= 0 && paymentStatus !== 'success' && (
                            <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-md mb-6 shadow-sm">
                                <div className="flex items-center">
                                    <XCircle className="w-5 h-5 text-red-500 flex-shrink-0" />
                                    <div className="ml-3">
                                        <p className="text-base font-medium text-red-800">
                                            Batas Waktu Pembayaran Telah Habis.
                                        </p>
                                        <p className="text-sm text-red-700 mt-1">
                                            Silakan ulangi proses checkout untuk mendapatkan transaksi baru.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {paymentStatus === 'success' && (
                            <div className="bg-green-50 border-l-4 border-green-500 p-4 rounded-md mb-6 shadow-md">
                                <div className="flex items-center">
                                    <CheckCircle className="w-6 h-6 text-green-500 flex-shrink-0" />
                                    <div className="ml-3">
                                        <p className="text-base font-bold text-green-800">
                                            Pembayaran Berhasil Dikonfirmasi!
                                        </p>
                                        <p className="text-sm text-green-700 mt-1">
                                            Anda akan dialihkan ke halaman konfirmasi pesanan dalam beberapa saat.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {paymentStatus === 'error' && (
                            <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-md mb-6 shadow-sm">
                                <div className="flex items-center">
                                    <XCircle className="w-5 h-5 text-red-500 flex-shrink-0" />
                                    <div className="ml-3">
                                        <p className="text-base font-medium text-red-800">
                                            Pembayaran Gagal.
                                        </p>
                                        <p className="text-sm text-red-700 mt-1">
                                            Terjadi kesalahan pada pembayaran Anda. Silakan coba lagi atau periksa status.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Tombol Aksi */}
                        <div className="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4 mt-6">
                            {paymentStatus !== 'success' && timeLeft > 0 && (
                                <>
                                    <button
                                        onClick={handleTryAgain}
                                        className="w-full sm:w-auto px-6 py-3 flex items-center justify-center bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 transition duration-150 focus:outline-none focus:ring-4 focus:ring-blue-500 focus:ring-opacity-50 disabled:opacity-50 disabled:bg-gray-400"
                                        disabled={isLoading}
                                    >
                                        <DollarSign className="w-5 h-5 mr-2" />
                                        {isLoading ? 'Memuat Ulang...' : 'Buka Lagi Pembayaran'}
                                    </button>
                                    <button
                                        onClick={handleManualCheck}
                                        className="w-full sm:w-auto px-6 py-3 flex items-center justify-center bg-gray-200 text-gray-800 rounded-lg font-semibold hover:bg-gray-300 transition duration-150 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 disabled:opacity-50 disabled:bg-gray-100"
                                        disabled={isLoading}
                                    >
                                        {isLoading ? <Loader2 className="w-5 h-5 mr-2 animate-spin" /> : <RefreshCw className="w-5 h-5 mr-2" />}
                                        {isLoading ? 'Memeriksa...' : 'Periksa Status Pembayaran'}
                                    </button>
                                </>
                            )}
                            <Link
                                href="/checkout"
                                className="w-full sm:w-auto px-6 py-3 flex items-center justify-center bg-white border border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-50 transition duration-150 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                            >
                                <ArrowLeft className="w-5 h-5 mr-2" />
                                Kembali ke Checkout
                            </Link>
                        </div>
                        
                        <div className="mt-8 pt-4 border-t text-sm text-gray-500 text-center">
                            Didukung oleh <span className="font-semibold text-gray-700">Midtrans</span>
                        </div>
                        
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
