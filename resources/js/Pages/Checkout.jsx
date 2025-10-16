import { Head } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { ShoppingCart, ArrowLeft, User, CreditCard } from 'lucide-react';
import { router } from '@inertiajs/react';

const Checkout = ({ products }) => {
    const [customerData, setCustomerData] = useState(null);
    const [cartItems, setCartItems] = useState({});
    const [orderNotes, setOrderNotes] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        // Load customer data and cart items from session storage
        const savedData = sessionStorage.getItem('customerData');
        const savedCart = sessionStorage.getItem('cartItems');
        
        if (savedData) {
            setCustomerData(JSON.parse(savedData));
        }
        
        if (savedCart) {
            setCartItems(JSON.parse(savedCart));
        }
    }, []);

    // Calculate cart totals
    const calculateTotals = () => {
        let totalItems = 0;
        let totalAmount = 0;
        
        Object.entries(cartItems).forEach(([productId, quantity]) => {
            const product = products.find(p => p.id == productId);
            if (product) {
                totalItems += quantity;
                totalAmount += product.harga * quantity;
            }
        });
        
        return { totalItems, totalAmount };
    };

    const { totalItems, totalAmount } = calculateTotals();

    // Handle form submission for checkout
    const handleCheckout = () => {
        setLoading(true);
        
        router.post('/checkout/process', {
            customerData,
            cartItems,
            orderNotes
        }, {
            onSuccess: () => {
                // Clear cart after successful checkout
                sessionStorage.removeItem('cartItems');
                setLoading(false);
            },
            onError: () => {
                setLoading(false);
            }
        });
    };

    // Go back to product page
    const goBack = () => {
        window.location.href = '/';
    };

    // If no items in cart or no customer data, redirect to home
    if (!customerData || Object.keys(cartItems).length === 0) {
        return (
            <>
                <Head title="Checkout" />
                <div className="min-h-screen flex flex-col items-center justify-center bg-gray-900 px-4">
                    <div className="text-center mb-6">
                        <ShoppingCart className="w-16 h-16 text-orange-500 mx-auto mb-4" />
                        <h1 className="text-2xl font-bold text-white mb-2">Your cart is empty</h1>
                        <p className="text-gray-400 mb-6">Add some items to your cart before checkout</p>
                        <button
                            onClick={goBack}
                            className="px-6 py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-all flex items-center mx-auto"
                        >
                            <ArrowLeft className="mr-2 w-5 h-5" />
                            <span>Return to Menu</span>
                        </button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Checkout" />
            <div className="min-h-screen bg-gray-900">
                {/* Header */}
                <div className="bg-gray-800 text-white p-4 sm:p-5 fixed top-0 w-full z-10 shadow-lg">
                    <div className="max-w-7xl mx-auto flex justify-between items-center">
                        <button
                            onClick={goBack}
                            className="flex items-center text-gray-300 hover:text-white"
                        >
                            <ArrowLeft className="w-5 h-5 mr-2" />
                            <span>Back to Menu</span>
                        </button>
                        <h1 className="text-xl font-bold">Checkout</h1>
                        <div className="w-24"></div> {/* Spacer for balance */}
                    </div>
                </div>

                <div className="max-w-4xl mx-auto px-4 pt-24 pb-12">
                    <div className="grid grid-cols-1 lg:grid-cols-5 gap-8">
                        {/* Customer details */}
                        <div className="lg:col-span-2">
                            <div className="bg-gray-800 rounded-lg shadow-xl p-6">
                                <div className="flex items-center mb-6">
                                    <User className="w-6 h-6 text-orange-500 mr-3" />
                                    <h2 className="text-xl font-bold text-white">Customer Details</h2>
                                </div>
                                
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-gray-400 text-sm mb-1">Full Name</label>
                                        <div className="text-white font-medium">{customerData.nama}</div>
                                    </div>
                                    
                                    <div>
                                        <label className="block text-gray-400 text-sm mb-1">WhatsApp Number</label>
                                        <div className="text-white font-medium">{customerData.whatsapp}</div>
                                    </div>
                                    
                                    <div>
                                        <label className="block text-gray-400 text-sm mb-1">Email Address</label>
                                        <div className="text-white font-medium">{customerData.email}</div>
                                    </div>
                                </div>
                            </div>

                            {/* Payment Method */}
                            <div className="bg-gray-800 rounded-lg shadow-xl p-6 mt-6">
                                <div className="flex items-center mb-6">
                                    <CreditCard className="w-6 h-6 text-orange-500 mr-3" />
                                    <h2 className="text-xl font-bold text-white">Payment Method</h2>
                                </div>
                                
                                <div className="bg-gray-700 rounded-lg p-4 flex items-center">
                                    <div className="h-5 w-5 rounded-full bg-orange-500 mr-3 flex items-center justify-center">
                                        <div className="h-2 w-2 bg-white rounded-full"></div>
                                    </div>
                                    <span className="text-white font-medium">Cash Payment</span>
                                </div>
                                
                                <p className="text-gray-400 text-sm mt-4">
                                    Pay after your order is confirmed and prepared.
                                </p>
                            </div>
                        </div>

                        {/* Order Summary */}
                        <div className="lg:col-span-3">
                            <div className="bg-gray-800 rounded-lg shadow-xl p-6">
                                <div className="flex items-center mb-6">
                                    <ShoppingCart className="w-6 h-6 text-orange-500 mr-3" />
                                    <h2 className="text-xl font-bold text-white">Order Summary</h2>
                                </div>
                                
                                <div className="space-y-4 mb-6">
                                    {Object.entries(cartItems).map(([productId, quantity]) => {
                                        const product = products.find(p => p.id == productId);
                                        if (!product) return null;
                                        
                                        return (
                                            <div key={productId} className="flex items-center justify-between pb-4 border-b border-gray-700">
                                                <div className="flex items-center">
                                                    {product.gambar && (
                                                        <img 
                                                            src={product.gambar} 
                                                            alt={product.nama}
                                                            className="w-12 h-12 object-cover rounded-md mr-3"
                                                        />
                                                    )}
                                                    <div>
                                                        <div className="text-white font-medium">{product.nama}</div>
                                                        <div className="text-gray-400 text-sm">{quantity} x Rp {parseInt(product.harga).toLocaleString('id-ID')}</div>
                                                    </div>
                                                </div>
                                                <div className="text-white font-semibold">
                                                    Rp {parseInt(product.harga * quantity).toLocaleString('id-ID')}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                                
                                {/* Order totals */}
                                <div className="space-y-3 border-t border-gray-700 pt-4">
                                    <div className="flex justify-between text-gray-400">
                                        <span>Subtotal</span>
                                        <span>Rp {parseInt(totalAmount).toLocaleString('id-ID')}</span>
                                    </div>
                                    <div className="flex justify-between text-gray-400">
                                        <span>Total Items</span>
                                        <span>{totalItems} items</span>
                                    </div>
                                    <div className="flex justify-between text-white text-lg font-bold pt-2 border-t border-gray-700">
                                        <span>Total</span>
                                        <span className="text-orange-500">Rp {parseInt(totalAmount).toLocaleString('id-ID')}</span>
                                    </div>
                                </div>
                                
                                {/* Order Notes */}
                                <div className="mt-6">
                                    <label htmlFor="orderNotes" className="block text-gray-400 text-sm mb-2">Order Notes (Optional)</label>
                                    <textarea
                                        id="orderNotes"
                                        rows="3"
                                        value={orderNotes}
                                        onChange={(e) => setOrderNotes(e.target.value)}
                                        placeholder="Add any special instructions or notes for your order..."
                                        className="w-full bg-gray-700 text-white rounded-lg p-3 focus:ring-2 focus:ring-orange-500 focus:outline-none"
                                    ></textarea>
                                </div>
                                
                                {/* Checkout button */}
                                <button
                                    onClick={handleCheckout}
                                    disabled={loading}
                                    className={`w-full mt-6 py-4 rounded-lg font-semibold text-white transition-all ${
                                        loading ? 'bg-gray-600' : 'bg-orange-500 hover:bg-orange-600'
                                    }`}
                                >
                                    {loading ? 'Processing...' : 'Complete Order'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

export default Checkout;