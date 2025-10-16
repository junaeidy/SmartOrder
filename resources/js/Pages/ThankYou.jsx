import { Head } from '@inertiajs/react';
import React, { useEffect } from 'react';
import { CheckCircle, ShoppingBag, Home, Printer } from 'lucide-react';
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
            <Head title="Thank You" />
            <div className="min-h-screen bg-gray-900 px-4 py-10">
                <div className="max-w-2xl mx-auto">
                    {/* Success Card */}
                    <div className="bg-gray-800 rounded-xl shadow-2xl overflow-hidden mb-8 print:shadow-none">
                        {/* Header */}
                        <div className="bg-green-600 p-6 flex flex-col items-center justify-center">
                            <CheckCircle className="w-16 h-16 text-white mb-4" />
                            <h1 className="text-2xl sm:text-3xl font-bold text-white mb-2">Order Received!</h1>
                            <p className="text-green-100 text-center">
                                Your order has been received and will be processed by our staff shortly.
                            </p>
                        </div>

                        {/* Order Details */}
                        <div className="p-6">
                            <div className="mb-6">
                                <h2 className="text-xl font-semibold text-white mb-4">Order Details</h2>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-gray-400 text-sm">Order Number</p>
                                        <p className="text-white font-medium">{transaction.id}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-400 text-sm">Date</p>
                                        <p className="text-white font-medium">
                                            {new Date(transaction.created_at).toLocaleString()}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-gray-400 text-sm">Payment Method</p>
                                        <p className="text-white font-medium">Cash</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-400 text-sm">Total Amount</p>
                                        <p className="text-white font-medium">
                                            Rp {parseInt(transaction.total_amount).toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Queue Number */}
                            <div className="bg-gray-700 rounded-xl p-6 text-center mb-6">
                                <h3 className="text-lg font-medium text-gray-300 mb-2">Your Queue Number</h3>
                                <p className="text-4xl sm:text-5xl font-bold text-orange-500 mb-2">
                                    {transaction.queue_number}
                                </p>
                                <p className="text-sm text-gray-400">
                                    Please keep this number to pick up your order
                                </p>
                            </div>

                            {/* Customer Details */}
                            <div className="mb-6">
                                <h2 className="text-xl font-semibold text-white mb-4">Customer Details</h2>
                                <div className="space-y-2">
                                    <div>
                                        <p className="text-gray-400 text-sm">Name</p>
                                        <p className="text-white">{transaction.customer_name}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-400 text-sm">Email</p>
                                        <p className="text-white">{transaction.customer_email}</p>
                                    </div>
                                    <div>
                                        <p className="text-gray-400 text-sm">Phone</p>
                                        <p className="text-white">{transaction.customer_phone}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Order Summary */}
                            <div>
                                <h2 className="text-xl font-semibold text-white mb-4">Order Summary</h2>
                                {/* Scrollable on screen, full height when printed */}
                                <div className="max-h-60 overflow-y-auto pr-2 print:max-h-full print:overflow-visible">
                                    <div className="space-y-3">
                                        {transaction.items.map((item, index) => (
                                            <div key={index} className="flex justify-between pb-2 border-b border-gray-700">
                                                <div>
                                                    <p className="text-white">{item.nama}</p>
                                                    <p className="text-sm text-gray-400">
                                                        {item.quantity} x Rp {parseInt(item.harga).toLocaleString('id-ID')}
                                                    </p>
                                                </div>
                                                <p className="text-white">
                                                    Rp {parseInt(item.subtotal).toLocaleString('id-ID')}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                <div className="flex justify-between pt-2 mt-3 border-t border-gray-700">
                                    <p className="font-semibold text-white">Total</p>
                                    <p className="font-bold text-orange-500">
                                        Rp {parseInt(transaction.total_amount).toLocaleString('id-ID')}
                                    </p>
                                </div>
                                
                                {/* Order Notes (if any) */}
                                {transaction.customer_notes && (
                                    <div className="mt-4 p-3 bg-gray-700 rounded-lg">
                                        <p className="text-sm text-gray-300 mb-1">Order Notes:</p>
                                        <p className="text-white">{transaction.customer_notes}</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="bg-gray-700 px-6 py-4 text-center text-gray-300 text-sm print:hidden">
                            <p>A confirmation email has been sent to {transaction.customer_email}</p>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex flex-col sm:flex-row justify-center space-y-3 sm:space-y-0 sm:space-x-4 print:hidden">
                        <button
                            onClick={handlePrint}
                            className="px-6 py-3 bg-gray-700 text-white rounded-lg font-medium hover:bg-gray-600 transition-all flex items-center justify-center"
                        >
                            <Printer className="mr-2 w-5 h-5" />
                            <span>Print Receipt</span>
                        </button>
                        <button
                            onClick={goHome}
                            className="px-6 py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-all flex items-center justify-center"
                        >
                            <Home className="mr-2 w-5 h-5" />
                            <span>Start New Order</span>
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};

export default ThankYou;