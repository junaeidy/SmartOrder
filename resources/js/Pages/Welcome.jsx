import { Head, Link } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { ShoppingCart, Plus, Minus, Home, User, X, ChevronRight, ArrowLeft, Clock, AlertTriangle, AlertOctagon } from 'lucide-react';

const Welcome = ({ products, isStoreOpen, storeHours }) => {
    const [step, setStep] = useState(1);
    const [customerData, setCustomerData] = useState(null);
    const [cartItems, setCartItems] = useState({});
    const [isCartOpen, setIsCartOpen] = useState(false);

    useEffect(() => {
        const savedData = sessionStorage.getItem('customerData');
        const savedCart = sessionStorage.getItem('cartItems');
        
        if (savedData) {
            setCustomerData(JSON.parse(savedData));
            setStep(3);
        }
        
        if (savedCart) {
            setCartItems(JSON.parse(savedCart));
        }
    }, []);

    const handleFormSubmit = (data) => {
        setCustomerData(data);
        setStep(3);
    };

    // Helper to convert day number to name
    const getDayName = (dayNum) => {
        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        return days[dayNum];
    };
    
    // Helper to format hours for display
    const formatHours = (timeString) => {
        if (!timeString) return '';
        const [hours, minutes] = timeString.split(':');
        return `${hours}:${minutes}`;
    };
    
    // Get today's hours
    const getCurrentDayHours = () => {
        if (!storeHours) return { open: '08:00', close: '20:00' };
        const today = getDayName(new Date().getDay());
        return storeHours[today] || { open: '08:00', close: '20:00' };
    };
    
    // Step 1: Welcome
    const WelcomeScreen = () => (
        <div className="min-h-screen flex items-center justify-center bg-white px-6 dark:bg-gray-900">
            <div className="text-center w-full max-w-2xl">
                {!isStoreOpen && (
                    <div className="mb-8 p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 dark:bg-red-900/60 dark:border-red-500 dark:text-red-200">
                        <div className="flex items-center justify-center mb-2">
                            <AlertTriangle className="w-6 h-6 text-red-600 mr-2 dark:text-red-400" />
                            <h2 className="text-xl font-bold">Toko Tutup</h2>
                        </div>
                        <p className="text-gray-700 dark:text-gray-300">
                            Saat ini toko sedang tutup. Jam operasional hari ini: {formatHours(getCurrentDayHours().open)} - {formatHours(getCurrentDayHours().close)}
                        </p>
                    </div>
                )}
                <div className="mb-8">
                    <User className="w-20 h-20 sm:w-24 sm:h-24 text-orange-500 mx-auto" />
                </div>
                <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-6 dark:text-white">
                    Selamat Datang di SmartOrder
                </h1>
                <p className="text-base sm:text-lg text-gray-600 mb-10 dark:text-gray-400">
                    Solusi pintar untuk pemesanan makanan
                </p>
                <button
                    onClick={() => setStep(2)}
                    className={`px-6 sm:px-8 py-3 sm:py-4 bg-orange-500 text-white rounded-lg text-lg sm:text-xl font-semibold hover:bg-orange-600 transition-all transform hover:scale-105 shadow-lg w-full sm:w-auto ${!isStoreOpen ? 'opacity-50 cursor-not-allowed' : ''}`}
                    disabled={!isStoreOpen}
                >
                    {isStoreOpen ? 'Mulai Pesanan' : 'Toko Sedang Tutup'}
                </button>
                
                {!isStoreOpen && (
                    <div className="mt-6 text-gray-600 text-sm dark:text-gray-400">
                        <p>Silakan kembali pada jam operasional.</p>
                    </div>
                )}
            </div>
        </div>
    );

    // Step 2: Form
    const CustomerForm = () => (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4 dark:bg-gray-900">
            <div className="w-full max-w-md p-6 sm:p-8 bg-white rounded-2xl shadow-2xl dark:bg-gray-800">
                <div className="flex justify-center mb-6">
                    <User className="w-14 h-14 sm:w-16 sm:h-16 text-orange-500" />
                </div>
                <h2 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-6 text-center dark:text-white">
                    Masukkan Data Anda
                </h2>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        const formData = {
                            nama: e.target.nama.value,
                            whatsapp: e.target.whatsapp.value,
                            email: e.target.email.value
                        };
                        sessionStorage.setItem('customerData', JSON.stringify(formData));
                        handleFormSubmit(formData);
                    }}
                    className="space-y-5 sm:space-y-6"
                >
                    {['nama', 'whatsapp', 'email'].map((field, i) => (
                        <div key={i}>
                            <label
                                htmlFor={field}
                                className="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {field === 'nama'
                                    ? 'Nama Lengkap'
                                    : field === 'whatsapp'
                                    ? 'Nomor WhatsApp'
                                    : 'Alamat Email'}
                            </label>
                            <input
                                type={field === 'email' ? 'email' : field === 'whatsapp' ? 'tel' : 'text'}
                                id={field}
                                name={field}
                                required
                                className="mt-1 block w-full rounded-lg bg-white border-gray-300 text-gray-900 shadow-sm focus:border-orange-500 focus:ring-orange-500 p-3 sm:p-3.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                placeholder={
                                    field === 'nama'
                                        ? 'Masukkan nama lengkap'
                                        : field === 'whatsapp'
                                        ? 'Masukkan nomor WhatsApp'
                                        : 'Masukkan alamat email'
                                }
                            />
                        </div>
                    ))}
                    <button
                        type="submit"
                        className="w-full py-3 sm:py-3.5 px-4 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transform hover:scale-105 transition-all focus:ring-offset-white dark:focus:ring-offset-gray-900"
                    >
                        Lanjut ke Menu
                    </button>
                </form>
            </div>
        </div>
    );

    // Step 3: Product List
    const ProductList = () => {
        const addToCart = (product) => {
            const updatedCart = {
                ...cartItems,
                [product.id]: (cartItems[product.id] || 0) + 1
            };
            setCartItems(updatedCart);
            sessionStorage.setItem('cartItems', JSON.stringify(updatedCart));
        };

        const removeFromCart = (productId) => {
            const updatedCart = { ...cartItems };
            if (updatedCart[productId] > 1) {
                updatedCart[productId]--;
            } else {
                delete updatedCart[productId];
            }
            setCartItems(updatedCart);
            sessionStorage.setItem('cartItems', JSON.stringify(updatedCart));
        };
        
        const updateCartQuantity = (productId, quantity) => {
            if (quantity <= 0) {
                const updatedCart = { ...cartItems };
                delete updatedCart[productId];
                setCartItems(updatedCart);
                sessionStorage.setItem('cartItems', JSON.stringify(updatedCart));
            } else {
                const updatedCart = { ...cartItems, [productId]: quantity };
                setCartItems(updatedCart);
                sessionStorage.setItem('cartItems', JSON.stringify(updatedCart));
            }
        };

        return (
            <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
                {customerData && (
                    <div className="bg-white text-gray-900 p-4 sm:p-5 fixed top-0 w-full z-10 shadow-lg dark:bg-gray-800 dark:text-white">
                        <div className="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
                            <div className="flex items-center space-x-3">
                                <User className="w-6 h-6 text-orange-500" />
                                <div>
                                    <span className="font-semibold block">{customerData.nama}</span>
                                    <span className="text-sm text-gray-500 dark:text-gray-400">{customerData.whatsapp}</span>
                                </div>
                            </div>
                            <div className="flex items-center space-x-4">
                                <button
                                    onClick={() => setIsCartOpen(true)} 
                                    className="relative"
                                    aria-label="Buka keranjang belanja"
                                >
                                    <ShoppingCart className="w-6 h-6 text-orange-500" />
                                    {Object.keys(cartItems).length > 0 && (
                                        <span className="absolute -top-2 -right-2 bg-orange-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                            {Object.values(cartItems).reduce((a, b) => a + b, 0)}
                                        </span>
                                    )}
                                </button>
                                <button
                                    onClick={() => {
                                        sessionStorage.removeItem('customerData');
                                        sessionStorage.removeItem('cartItems');
                                        setStep(1);
                                        setCustomerData(null);
                                        setCartItems({});
                                    }}
                                    className="text-xs sm:text-sm px-3 sm:px-4 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 transition-all flex items-center space-x-1 sm:space-x-2 text-white"
                                >
                                    <Home className="w-4 h-4" />
                                    <span>Pesanan Baru</span>
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Floating Cart Button removed to avoid overlapping with Theme toggle */}

                {/* Produk Grid */}
                <div className="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 pt-28 sm:pt-24 pb-12">
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4 sm:gap-6">
                        {products.map((product) => (
                            <div
                                key={product.id}
                                className="bg-white rounded-lg shadow-xl overflow-hidden hover:shadow-2xl transition-all transform hover:scale-[1.02] flex flex-col dark:bg-gray-800"
                            >
                                <div className="relative">
                                    {product.gambar && (
                                        <img
                                            src={product.gambar}
                                            alt={product.nama}
                                            className="w-full aspect-video object-cover"
                                        />
                                    )}
                                    {(product.stok <= 0 || product.closed) && (
                                        <div className="absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center">
                                            <span className="text-white font-semibold text-sm sm:text-base">{product.stok <= 0 ? 'Stok Habis' : 'Ditutup Sementara'}</span>
                                        </div>
                                    )}
                                    {product.stok > 0 && product.stok <= 20 && !product.closed && (
                                        <div className="absolute top-2 left-2 bg-yellow-500 text-black text-xs px-2 py-1 rounded flex items-center">
                                            <AlertOctagon className="w-3 h-3 mr-1" />
                                            Stok menipis
                                        </div>
                                    )}
                                </div>
                                <div className="p-3 sm:p-4 flex flex-col flex-grow">
                                    <h3 className="text-sm sm:text-lg font-semibold text-gray-900 line-clamp-2 dark:text-white">
                                        {product.nama}
                                    </h3>
                                    <p className="text-gray-500 text-xs sm:text-sm dark:text-gray-400">Stok: {product.stok} {product.closed && <span className="ml-1 text-red-500 dark:text-red-400">(Ditutup)</span>}</p>
                                    <div className="mt-auto flex items-center justify-between pt-3">
                                        <span className="text-base sm:text-xl font-bold text-orange-500">
                                            Rp {parseInt(product.harga).toLocaleString('id-ID')}
                                        </span>
                                        {product.stok > 0 && !product.closed ? (
                                            cartItems[product.id] ? (
                                                <div className="flex items-center space-x-1 sm:space-x-2">
                                                    <button
                                                        onClick={() => removeFromCart(product.id)}
                                                        className="p-1 sm:p-1.5 rounded-lg bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                                                    >
                                                        <Minus className="w-4 h-4" />
                                                    </button>
                                                    <span className="text-gray-900 px-1 sm:px-2 text-sm sm:text-base dark:text-white">{cartItems[product.id]}</span>
                                                    <button
                                                        onClick={() => addToCart(product)}
                                                        className="p-1 sm:p-1.5 rounded-lg bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600"
                                                    >
                                                        <Plus className="w-4 h-4" />
                                                    </button>
                                                </div>
                                            ) : (
                                                <button
                                                    className="flex items-center space-x-1 sm:space-x-2 px-3 sm:px-4 py-1.5 sm:py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-all text-sm sm:text-base"
                                                    onClick={() => addToCart(product)}
                                                >
                                                    <Plus className="w-4 h-4" />
                                                    <span>Tambah</span>
                                                </button>
                                            )
                                        ) : (
                                            <button
                                                className="px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-200 text-gray-500 rounded-lg text-sm sm:text-base cursor-not-allowed dark:bg-gray-700 dark:text-gray-400"
                                                disabled
                                            >
                                                {product.stok <= 0 ? 'Habis' : 'Ditutup'}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Shopping Cart Drawer */}
                {isCartOpen && (
                    <div className="fixed inset-0 z-50 flex justify-end">
                        <div className="absolute inset-0 bg-black/50" onClick={() => setIsCartOpen(false)}></div>
                        <div className="w-full max-w-md bg-white h-full overflow-y-auto shadow-xl transform transition-all ease-in-out duration-300 z-10 dark:bg-gray-800">
                            <div className="p-4 sm:p-6 flex flex-col h-full">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-xl sm:text-2xl font-bold text-gray-900 flex items-center dark:text-white">
                                        <ShoppingCart className="mr-2 text-orange-500" /> Keranjang Belanja
                                    </h2>
                                    <button
                                        onClick={() => setIsCartOpen(false)}
                                        className="p-2 rounded-full hover:bg-gray-100 transition-colors dark:hover:bg-gray-700"
                                    >
                                        <X className="w-5 h-5 text-gray-500 dark:text-gray-400" />
                                    </button>
                                </div>

                                {Object.keys(cartItems).length > 0 ? (
                                    <>
                                        <div className="flex-grow overflow-y-auto">
                                            {Object.keys(cartItems).map((productId) => {
                                                const product = products.find(p => p.id == productId);
                                                if (!product) return null;
                                                const quantity = cartItems[productId];
                                                const subtotal = product.harga * quantity;
                                                
                                                return (
                                                    <div key={productId} className="mb-4 p-4 bg-gray-50 rounded-lg flex items-start dark:bg-gray-700">
                                                        {product.gambar && (
                                                            <img
                                                                src={product.gambar}
                                                                alt={product.nama}
                                                                className="w-20 h-20 object-cover rounded-md mr-3"
                                                            />
                                                        )}
                                                        <div className="flex-grow">
                                                            <h3 className="text-gray-900 font-medium mb-1 dark:text-white">{product.nama}</h3>
                                                            <p className="text-orange-600 font-semibold mb-2 dark:text-orange-500">
                                                                Rp {parseInt(product.harga).toLocaleString('id-ID')}
                                                            </p>
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex items-center border border-gray-300 rounded-lg overflow-hidden dark:border-gray-600">
                                                                    <button onClick={() => updateCartQuantity(productId, quantity - 1)} className="px-3 py-1 bg-white text-gray-900 hover:bg-gray-50 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-900">
                                                                        <Minus className="w-4 h-4" />
                                                                    </button>
                                                                    <input
                                                                        type="number"
                                                                        min="1"
                                                                        value={quantity}
                                                                        onChange={(e) => {
                                                                            const newVal = parseInt(e.target.value);
                                                                            if (!isNaN(newVal)) {
                                                                                updateCartQuantity(productId, newVal);
                                                                            }
                                                                        }}
                                                                        className="w-12 text-center bg-white text-gray-900 border-0 focus:ring-0 dark:bg-gray-800 dark:text-white"
                                                                    />
                                                                    <button onClick={() => updateCartQuantity(productId, quantity + 1)} className="px-3 py-1 bg-white text-gray-900 hover:bg-gray-50 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-900">
                                                                        <Plus className="w-4 h-4" />
                                                                    </button>
                                                                </div>
                                                                <p className="text-gray-900 font-medium dark:text-white">
                                                                    Rp {parseInt(subtotal).toLocaleString('id-ID')}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        <div className="border-t border-gray-200 mt-4 pt-4 dark:border-gray-700">
                                            <div className="flex justify-between mb-2 text-gray-600 dark:text-gray-400">
                                                <span>Total Items:</span>
                                                <span>{Object.values(cartItems).reduce((a, b) => a + b, 0)}</span>
                                            </div>
                                            <div className="flex justify-between mb-6 text-gray-900 font-bold text-xl dark:text-white">
                                                <span>Total:</span>
                                                <span className="text-orange-600 dark:text-orange-500">
                                                    Rp {parseInt(
                                                        Object.keys(cartItems).reduce((total, productId) => {
                                                            const product = products.find(p => p.id == productId);
                                                            return total + (product ? product.harga * cartItems[productId] : 0);
                                                        }, 0)
                                                    ).toLocaleString('id-ID')}
                                                </span>
                                            </div>
                                            <Link 
                                                href= {route('checkout')}
                                                className="w-full py-3 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600 transition-all flex items-center justify-center"
                                            >
                                                <span>Lanjut ke Checkout</span>
                                                <ChevronRight className="ml-2 w-5 h-5" />
                                            </Link>
                                        </div>
                                    </>
                                ) : (
                                    <div className="flex-grow flex flex-col items-center justify-center text-center">
                                        <ShoppingCart className="w-16 h-16 text-gray-400 mb-4 dark:text-gray-600" />
                                        <h3 className="text-gray-900 text-xl font-medium mb-2 dark:text-white">Keranjang Anda kosong</h3>
                                        <p className="text-gray-500 mb-6 dark:text-gray-400">Tambahkan beberapa menu lezat ke keranjang Anda!</p>
                                        <button
                                            onClick={() => setIsCartOpen(false)}
                                            className="px-6 py-2 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-all flex items-center"
                                        >
                                            <ArrowLeft className="mr-2 w-5 h-5" />
                                            <span>Lanjut Belanja</span>
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        );
    };

    return (
        <>
            <Head title="Beranda" />
            {step === 1 && <WelcomeScreen />}
            {step === 2 && <CustomerForm />}
            {step === 3 && <ProductList />}
        </>
    );
};

export default Welcome;
