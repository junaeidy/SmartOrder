import { Head } from '@inertiajs/react';
import React, { useState, useEffect, useMemo } from 'react';
import { ShoppingCart, ArrowLeft, User, CreditCard } from 'lucide-react';
import { router } from '@inertiajs/react';

const Checkout = ({ products, taxPercentage = 11 }) => {
    const [customerData, setCustomerData] = useState(null);
    const [cartItems, setCartItems] = useState({});
    const [orderNotes, setOrderNotes] = useState('');
    const [loading, setLoading] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState('cash'); // Default to cash
    const [discountCode, setDiscountCode] = useState('');
    const [discountError, setDiscountError] = useState('');
    const [discountSuccess, setDiscountSuccess] = useState('');
    const [discountInfo, setDiscountInfo] = useState(null);
    const [checkingDiscount, setCheckingDiscount] = useState(false);
    const [availabilityIssues, setAvailabilityIssues] = useState([]);
    const [validatingCart, setValidatingCart] = useState(false);

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

    // Build payload of current cart
    const cartPayload = useMemo(() => ({
        cartItems
    }), [cartItems]);

    // Validate cart availability when cart changes
    useEffect(() => {
        const validate = async () => {
            if (!cartItems || Object.keys(cartItems).length === 0) {
                setAvailabilityIssues([]);
                return;
            }
            setValidatingCart(true);
            try {
                const { data } = await window.axios.post('/cart/validate', cartPayload);
                setAvailabilityIssues(data.issues || []);
            } catch (_) {
                // Ignore network errors here, keep prior state
            } finally {
                setValidatingCart(false);
            }
        };
        validate();
    }, [cartPayload]);

    // Subscribe to stock alerts to revalidate cart in realtime
    useEffect(() => {
        try {
            const ch = window.Echo?.channel('products');
            const handler = () => {
                // Revalidate silently
                window.axios.post('/cart/validate', cartPayload)
                    .then(({ data }) => setAvailabilityIssues(data.issues || []))
                    .catch(() => {});
            };
            ch?.listen('.ProductStockAlert', handler);
            return () => {
                window.Echo?.leave('products');
            };
        } catch (_) {}
    }, [cartPayload]);

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
    
    // Verify discount code
    const verifyDiscountCode = () => {
        if (!discountCode.trim()) {
            setDiscountError('Silakan masukkan kode diskon');
            return;
        }
        
        setCheckingDiscount(true);
        setDiscountError('');
        setDiscountSuccess('');
        setDiscountInfo(null);
        
        window.axios.post('/discount/verify', {
            code: discountCode,
            amount: totalAmount
        })
        .then(({ data }) => {
            setCheckingDiscount(false);
            if (data.success) {
                setDiscountSuccess(data.message);
                setDiscountInfo(data.discount);
            } else {
                setDiscountError(data.message || 'Kode diskon tidak valid');
            }
        })
        .catch((error) => {
            setCheckingDiscount(false);
            const msg = error?.response?.data?.message || 'Gagal memeriksa kode diskon. Silakan coba lagi.';
            setDiscountError(msg);
            
        });
    };

    // Handle form submission for checkout
    const handleCheckout = async () => {
        setLoading(true);
        // Final validation before submit
        try {
            const { data } = await window.axios.post('/cart/validate', cartPayload);
            if (!data.success) {
                setAvailabilityIssues(data.issues || []);
                setLoading(false);
                return;
            }
        } catch (_) {
            setLoading(false);
            return;
        }

        router.post('/checkout/process', {
            customerData,
            cartItems,
            orderNotes,
            paymentMethod,
            discountCode
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
                <div className="min-h-screen flex flex-col items-center justify-center bg-gray-50 px-4 dark:bg-gray-900">
                    <div className="text-center mb-6">
                        <ShoppingCart className="w-16 h-16 text-orange-500 mx-auto mb-4" />
                        <h1 className="text-2xl font-bold text-gray-900 mb-2 dark:text-white">Keranjang Anda kosong</h1>
                        <p className="text-gray-600 mb-6 dark:text-gray-400">Tambahkan item ke keranjang sebelum checkout</p>
                        <button
                            onClick={goBack}
                            className="px-6 py-3 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-all flex items-center mx-auto"
                        >
                            <ArrowLeft className="mr-2 w-5 h-5" />
                            <span>Kembali ke Menu</span>
                        </button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Checkout" />
            <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
                {/* Header */}
                <div className="bg-white text-gray-900 p-4 sm:p-5 fixed top-0 w-full z-10 shadow-lg dark:bg-gray-800 dark:text-white">
                    <div className="max-w-7xl mx-auto flex justify-between items-center">
                        <button
                            onClick={goBack}
                            className="flex items-center text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                        >
                            <ArrowLeft className="w-5 h-5 mr-2" />
                            <span>Kembali ke Menu</span>
                        </button>
                        <h1 className="text-xl font-bold">Checkout</h1>
                        <div className="w-24"></div> {/* Spacer for balance */}
                    </div>
                </div>

                <div className="max-w-4xl mx-auto px-4 pt-24 pb-12">
                    <div className="grid grid-cols-1 lg:grid-cols-5 gap-8">
                        {/* Customer details */}
                        <div className="lg:col-span-2">
                            <div className="bg-white rounded-lg shadow-xl p-6 dark:bg-gray-800">
                                <div className="flex items-center mb-6">
                                    <User className="w-6 h-6 text-orange-500 mr-3" />
                                    <h2 className="text-xl font-bold text-gray-900 dark:text-white">Data Pelanggan</h2>
                                </div>
                                
                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-gray-500 text-sm mb-1 dark:text-gray-400">Nama Lengkap</label>
                                        <div className="text-gray-900 font-medium dark:text-white">{customerData.nama}</div>
                                    </div>
                                    
                                    <div>
                                        <label className="block text-gray-500 text-sm mb-1 dark:text-gray-400">Nomor WhatsApp</label>
                                        <div className="text-gray-900 font-medium dark:text-white">{customerData.whatsapp}</div>
                                    </div>
                                    
                                    <div>
                                        <label className="block text-gray-500 text-sm mb-1 dark:text-gray-400">Alamat Email</label>
                                        <div className="text-gray-900 font-medium dark:text-white">{customerData.email}</div>
                                    </div>
                                </div>
                            </div>

                            {/* Payment Method */}
                            <div className="bg-white rounded-lg shadow-xl p-6 mt-6 dark:bg-gray-800">
                                <div className="flex items-center mb-6">
                                    <CreditCard className="w-6 h-6 text-orange-500 mr-3" />
                                    <h2 className="text-xl font-bold text-gray-900 dark:text-white">Metode Pembayaran</h2>
                                </div>
                                
                                <div 
                                    className={`bg-gray-50 dark:bg-gray-700 rounded-lg p-4 flex items-center mb-3 cursor-pointer ${paymentMethod === 'cash' ? 'ring-2 ring-orange-500' : ''}`}
                                    onClick={() => setPaymentMethod('cash')}
                                >
                                    <div className="h-5 w-5 rounded-full bg-gray-300 dark:bg-gray-600 mr-3 flex items-center justify-center">
                                        {paymentMethod === 'cash' && (
                                            <div className="h-2 w-2 bg-orange-500 rounded-full"></div>
                                        )}
                                    </div>
                                    <span className="text-gray-900 font-medium dark:text-white">Pembayaran Tunai</span>
                                </div>
                                
                                <div 
                                    className={`bg-gray-50 dark:bg-gray-700 rounded-lg p-4 flex items-center cursor-pointer ${paymentMethod === 'midtrans' ? 'ring-2 ring-orange-500' : ''}`}
                                    onClick={() => setPaymentMethod('midtrans')}
                                >
                                    <div className="h-5 w-5 rounded-full bg-gray-300 dark:bg-gray-600 mr-3 flex items-center justify-center">
                                        {paymentMethod === 'midtrans' && (
                                            <div className="h-2 w-2 bg-orange-500 rounded-full"></div>
                                        )}
                                    </div>
                                    <span className="text-gray-900 font-medium dark:text-white">Pembayaran Online</span>
                                </div>
                                
                                <p className="text-gray-600 text-sm mt-4 dark:text-gray-400">
                                    {paymentMethod === 'cash' 
                                        ? 'Bayar setelah pesanan Anda dikonfirmasi dan disiapkan.'
                                        : 'Bayar secara online dengan aman menggunakan berbagai metode pembayaran (kartu, e-wallet, transfer bank, dll.).'}
                                </p>
                            </div>
                        </div>

                        {/* Order Summary */}
                        <div className="lg:col-span-3">
                            <div className="bg-white rounded-lg shadow-xl p-6 dark:bg-gray-800">
                                <div className="flex items-center mb-6">
                                    <ShoppingCart className="w-6 h-6 text-orange-500 mr-3" />
                                    <h2 className="text-xl font-bold text-gray-900 dark:text-white">Ringkasan Pesanan</h2>
                                </div>
                                
                                {/* Scrollable items list to handle many cart items without stretching the page */}
                                <div className="space-y-4 mb-6 max-h-64 sm:max-h-80 lg:max-h-[50vh] overflow-y-auto pr-2">
                                    {Object.entries(cartItems).map(([productId, quantity]) => {
                                        const product = products.find(p => p.id == productId);
                                        if (!product) return null;
                                        const issue = availabilityIssues.find(i => i.product_id == productId);
                                        
                                        return (
                                            <div key={productId} className="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                                                <div className="flex items-center">
                                                    {product.gambar && (
                                                        <img 
                                                            src={product.gambar} 
                                                            alt={product.nama}
                                                            className="w-12 h-12 object-cover rounded-md mr-3"
                                                        />
                                                    )}
                                                    <div>
                                                        <div className="text-gray-900 font-medium dark:text-white">{product.nama}</div>
                                                        <div className="text-gray-600 text-sm dark:text-gray-400">{quantity} x Rp {parseInt(product.harga).toLocaleString('id-ID')}</div>
                                                        {issue && (
                                                            <div className="text-xs mt-1 ${issue.reason === 'out_of_stock' || issue.reason === 'closed' ? 'text-red-400' : 'text-yellow-400'}">
                                                                {issue.message} {issue.available ? `(tersedia: ${issue.available})` : ''}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="text-gray-900 font-semibold dark:text-white">
                                                    Rp {parseInt(product.harga * quantity).toLocaleString('id-ID')}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                                
                                {/* Order totals */}
                                {/* Discount Code */}
                                <div className="mb-4">
                                    <label htmlFor="discountCode" className="block text-gray-400 text-sm mb-2">Kode Diskon (Opsional)</label>
                                    <div className="flex space-x-2">
                                        <input
                                            id="discountCode"
                                            type="text"
                                            value={discountCode}
                                            onChange={(e) => {
                                                setDiscountCode(e.target.value);
                                                setDiscountError('');
                                                setDiscountSuccess('');
                                                setDiscountInfo(null);
                                            }}
                                            placeholder="Masukkan kode diskon"
                                            className={`flex-1 bg-gray-700 text-white rounded-lg p-3 focus:outline-none ${
                                                discountError ? 'border border-red-500 focus:ring-2 focus:ring-red-500' :
                                                discountSuccess ? 'border border-green-500 focus:ring-2 focus:ring-green-500' :
                                                'focus:ring-2 focus:ring-orange-500'
                                            }`}
                                        />
                                        <button
                                            type="button"
                                            onClick={verifyDiscountCode}
                                            disabled={checkingDiscount}
                                            className={`px-4 py-3 rounded-lg font-medium transition-all ${
                                                checkingDiscount ? 'bg-gray-600 text-gray-300' : 'bg-orange-500 hover:bg-orange-600 text-white'
                                            }`}
                                        >
                                            {checkingDiscount ? 'Memeriksa...' : 'Verifikasi'}
                                        </button>
                                    </div>
                                    
                                    {/* Discount feedback */}
                                    {discountError && (
                                        <p className="mt-2 text-sm text-red-400">{discountError}</p>
                                    )}
                                    
                                    {discountSuccess && discountInfo && (
                                        <div className="mt-2 p-2 bg-green-900/40 border border-green-700 rounded-lg">
                                            <p className="text-sm text-green-400">{discountSuccess}</p>
                                            <p className="text-xs text-green-300 mt-1">
                                                {discountInfo.name} - {discountInfo.percentage}% diskon
                                                {discountInfo.amount > 0 && ` (Rp ${parseInt(discountInfo.amount).toLocaleString('id-ID')})`}
                                            </p>
                                        </div>
                                    )}
                                </div>
                                
                                <div className="space-y-3 border-t border-gray-700 pt-4">
                                    <div className="flex justify-between text-gray-600 dark:text-gray-400">
                                        <span>Subtotal</span>
                                        <span>Rp {parseInt(totalAmount).toLocaleString('id-ID')}</span>
                                    </div>
                                    <div className="flex justify-between text-gray-600 dark:text-gray-400">
                                        <span>Total Item</span>
                                        <span>{totalItems} item</span>
                                    </div>
                                    
                                    {discountInfo && (
                                        <div className="flex justify-between text-green-600 dark:text-green-400">
                                            <span>Diskon ({discountInfo.percentage}%)</span>
                                            <span>-Rp {parseInt(discountInfo.amount).toLocaleString('id-ID')}</span>
                                        </div>
                                    )}
                                    
                                    <div className="flex justify-between text-gray-600 dark:text-gray-400">
                                        <span>Pajak (PPN)</span>
                                        <span>{taxPercentage}%</span>
                                    </div>
                                    
                                    <div className="flex justify-between text-gray-900 text-lg font-bold pt-2 border-t border-gray-200 dark:text-white dark:border-gray-700">
                                        <span>Total</span>
                                        <span className="text-orange-600 dark:text-orange-500">
                                            Rp {parseInt(
                                                (totalAmount - (discountInfo?.amount || 0)) * (1 + taxPercentage/100)
                                            ).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                </div>
                                
                                {/* Order Notes */}
                                <div className="mt-6">
                                    <label htmlFor="orderNotes" className="block text-gray-600 text-sm mb-2 dark:text-gray-400">Catatan Pesanan (Opsional)</label>
                                    <textarea
                                        id="orderNotes"
                                        rows="3"
                                        value={orderNotes}
                                        onChange={(e) => setOrderNotes(e.target.value)}
                                        placeholder="Tambahkan instruksi khusus atau catatan untuk pesanan Anda..."
                                        className="w-full bg-white text-gray-900 rounded-lg p-3 focus:ring-2 focus:ring-orange-500 focus:outline-none dark:bg-gray-700 dark:text-white"
                                    ></textarea>
                                </div>
                                
                                {/* Checkout button */}
                                <button
                                    onClick={handleCheckout}
                                    disabled={loading || validatingCart || availabilityIssues.length > 0}
                                    className={`w-full mt-6 py-4 rounded-lg font-semibold text-white transition-all ${
                                        (loading || validatingCart || availabilityIssues.length > 0) ? 'bg-gray-400 dark:bg-gray-600' : 'bg-orange-500 hover:bg-orange-600'
                                    }`}
                                >
                                    {loading ? 'Memproses...' : validatingCart ? 'Memvalidasi...' : availabilityIssues.length > 0 ? 'Perbaiki Ketersediaan' : 'Selesaikan Pesanan'}
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