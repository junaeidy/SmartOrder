import { Head } from '@inertiajs/react';
import React, { useEffect, useState } from 'react';
import { CheckCircle, ShoppingBag, Home, Printer, CreditCard } from 'lucide-react';
import { router } from '@inertiajs/react';

const ThankYou = ({ transaction }) => {
    // Only clear session data when user manually navigates away
    // Not clearing automatically keeps data available for receipt
    
    const handlePrint = () => {
        window.print();
    };

    const goHome = () => {
        // Clear customer session data before starting new order
        sessionStorage.removeItem('customerData');
        sessionStorage.removeItem('cartItems');
        router.visit('/');
    };

    return (
        <>
            <Head title="Terima Kasih" />
            <div className="min-h-screen bg-gray-50 px-4 py-10 dark:bg-gray-900">
                <div className="max-w-2xl mx-auto">
                    {/* Success Card */}
                    <div className="bg-white rounded-xl shadow-2xl overflow-hidden mb-8 print:shadow-none dark:bg-gray-800">
                        {/* Header */}
                        <div className={`${
                            transaction.payment_status === 'pending' && transaction.payment_method === 'midtrans' 
                                ? 'bg-yellow-500' : 'bg-green-600'
                        } p-6 flex flex-col items-center justify-center`}>
                            {transaction.payment_status === 'pending' && transaction.payment_method === 'midtrans' ? (
                                <>
                                    <ShoppingBag className="w-16 h-16 text-white mb-4" />
                                    <h1 className="text-2xl sm:text-3xl font-bold text-white mb-2">Menunggu Pembayaran</h1>
                                    <p className="text-yellow-100 text-center">
                                        Pesanan Anda telah dibuat namun pembayaran masih tertunda. Silakan selesaikan pembayaran untuk memproses pesanan.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <CheckCircle className="w-16 h-16 text-white mb-4" />
                                    <h1 className="text-2xl sm:text-3xl font-bold text-white mb-2">Pesanan Diterima!</h1>
                                    <p className="text-green-100 text-center">
                                        Pesanan Anda telah diterima dan akan segera diproses oleh tim kami.
                                    </p>
                                </>
                            )}
                        </div>

                        {/* Order Details */}
                        <div className="p-6">
                            <div className="mb-6">
                                <h2 className="text-xl font-semibold text-gray-900 mb-4 dark:text-white">Detail Pesanan</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-gray-600 text-sm dark:text-gray-400">Nomor Pesanan</p>
                                        <p className="text-gray-900 font-medium dark:text-white">{transaction.kode_transaksi}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-600 text-sm dark:text-gray-400">Tanggal</p>
                                        <p className="text-gray-900 font-medium dark:text-white">
                                            {new Date(transaction.created_at).toLocaleString()}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-gray-600 text-sm dark:text-gray-400">Metode Pembayaran</p>
                                        <p className="text-gray-900 font-medium dark:text-white">
                                            {transaction.payment_method === 'midtrans' ? 'Pembayaran Online' : 'Tunai'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-gray-600 text-sm dark:text-gray-400">Status Pembayaran</p>
                                        <p className={`font-medium ${
                                            transaction.payment_status === 'paid' || 
                                            transaction.payment_status === 'settlement' || 
                                            transaction.payment_status === 'capture' ? 'text-green-400' : 
                                            transaction.payment_status === 'pending' ? 'text-yellow-400' : 'text-red-400'
                                        }`}>
                                            {transaction.payment_status === 'paid' || 
                                             transaction.payment_status === 'settlement' || 
                                             transaction.payment_status === 'capture' ? 'Lunas' :
                                             transaction.payment_status === 'pending' ? 'Tertunda' : 'Gagal'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-gray-600 text-sm dark:text-gray-400">Total Pembayaran</p>
                                        <p className="text-gray-900 font-medium dark:text-white">
                                            Rp {parseInt(transaction.total_amount).toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Queue Number */}
                            <div className="bg-gray-100 rounded-xl p-6 text-center mb-6 dark:bg-gray-700">
                                <h3 className="text-lg font-medium text-gray-700 mb-2 dark:text-gray-300">Nomor Antrian Anda</h3>
                                <p className="text-4xl sm:text-5xl font-bold text-orange-600 mb-2 dark:text-orange-500">
                                    {transaction.queue_number}
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    Simpan nomor ini untuk mengambil pesanan Anda
                                </p>
                            </div>

                            {/* Customer Details */}
                            <div className="mb-6">
                                <h2 className="text-xl font-semibold text-gray-900 mb-4 dark:text-white">Data Pelanggan</h2>
                                <div className="space-y-2">
                                    <div>
                                        <p className="text-gray-600 text-sm dark:text-gray-400">Nama</p>
                                        <p className="text-gray-900 dark:text-white">{transaction.customer_name}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-600 text-sm dark:text-gray-400">Email</p>
                                        <p className="text-gray-900 dark:text-white">{transaction.customer_email}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-600 text-sm dark:text-gray-400">Telepon</p>
                                        <p className="text-gray-900 dark:text-white">{transaction.customer_phone}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Order Summary */}
                            <div>
                                <h2 className="text-xl font-semibold text-gray-900 mb-4 dark:text-white">Ringkasan Pesanan</h2>
                                {/* Scrollable on screen, full height when printed */}
                                <div className="max-h-60 overflow-y-auto pr-2 print:max-h-full print:overflow-visible">
                                    <div className="space-y-3">
                                        {transaction.items.map((item, index) => (
                                            <div key={index} className="flex justify-between pb-2 border-b border-gray-200 dark:border-gray-700">
                                                <div>
                                                    <p className="text-gray-900 dark:text-white">{item.nama}</p>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                                        {item.quantity} x Rp {parseInt(item.harga).toLocaleString('id-ID')}
                                                    </p>
                                                </div>
                                                <p className="text-gray-900 dark:text-white">
                                                    Rp {parseInt(item.subtotal).toLocaleString('id-ID')}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="space-y-2 pt-2 mt-3 border-t border-gray-200 dark:border-gray-700">
                                    {transaction.discount_amount > 0 && (
                                        <div className="flex justify-between">
                                            <p className="text-gray-600 dark:text-gray-400">Subtotal</p>
                                            <p className="text-gray-900 dark:text-white">
                                                Rp {parseInt(transaction.total_amount - transaction.tax_amount + transaction.discount_amount).toLocaleString('id-ID')}
                                            </p>
                                        </div>
                                    )}
                                    
                                    {transaction.discount_amount > 0 && (
                                        <div className="flex justify-between">
                                            <p className="text-gray-600 dark:text-gray-400">Diskon</p>
                                            <p className="text-green-600 dark:text-green-400">
                                                -Rp {parseInt(transaction.discount_amount).toLocaleString('id-ID')}
                                            </p>
                                        </div>
                                    )}
                                    
                                    <div className="flex justify-between">
                                        <p className="text-gray-600 dark:text-gray-400">Pajak (PPN)</p>
                                        <p className="text-gray-900 dark:text-white">
                                            Rp {parseInt(transaction.tax_amount).toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                    
                                    <div className="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                        <p className="font-semibold text-gray-900 dark:text-white">Total</p>
                                        <p className="font-bold text-orange-600 dark:text-orange-500">
                                            Rp {parseInt(transaction.total_amount).toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                </div>
                                
                                {/* Order Notes (if any) */}
                                {transaction.customer_notes && (
                                    <div className="mt-4 p-3 bg-gray-100 rounded-lg dark:bg-gray-700">
                                        <p className="text-sm text-gray-700 mb-1 dark:text-gray-300">Catatan Pesanan:</p>
                                        <p className="text-gray-900 dark:text-white">{transaction.customer_notes}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="bg-gray-100 px-6 py-4 text-center text-gray-600 text-sm print:hidden dark:bg-gray-700 dark:text-gray-300">
                            <p>Email konfirmasi telah dikirim ke {transaction.customer_email}</p>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-4 print:hidden">
                        {/* Show payment button if payment is pending */}
                        {transaction.payment_status === 'pending' && transaction.payment_method === 'midtrans' && transaction.midtrans_payment_url && (
                            <a 
                                href={transaction.midtrans_payment_url} 
                                target="_blank"
                                className="px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-all flex items-center justify-center"
                            >
                                <CreditCard className="mr-2 w-5 h-5" />
                                <span>Selesaikan Pembayaran</span>
                            </a>
                        )}
                        
                        <button
                            onClick={handlePrint}
                            className="px-6 py-3 bg-gray-200 text-gray-900 rounded-lg font-medium hover:bg-gray-300 transition-all flex items-center justify-center dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                        >
                            <Printer className="mr-2 w-5 h-5" />
                            <span>Cetak Struk</span>
                        </button>
                        
                        <button
                            onClick={goHome}
                            className="px-6 py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-all flex items-center justify-center"
                        >
                            <Home className="mr-2 w-5 h-5" />
                            <span>Buat Pesanan Baru</span>
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};

export default ThankYou;