import { Head, Link } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import { ShoppingCart, Plus, Minus, Home, User, X, ChevronRight, ArrowLeft } from 'lucide-react';

const Welcome = ({ products }) => {
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

    // Step 1: Welcome
    const WelcomeScreen = () => (
        <div className="min-h-screen flex items-center justify-center bg-gray-900 px-6">
            <div className="text-center w-full max-w-2xl">
                <div className="mb-8">
                    <User className="w-20 h-20 sm:w-24 sm:h-24 text-orange-500 mx-auto" />
                </div>
                <h1 className="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-6">
                    Welcome to SmartOrder
                </h1>
                <p className="text-base sm:text-lg text-gray-400 mb-10">
                    Your Smart Solution for Food Ordering
                </p>
                <button
                    onClick={() => setStep(2)}
                    className="px-6 sm:px-8 py-3 sm:py-4 bg-orange-500 text-white rounded-lg text-lg sm:text-xl font-semibold hover:bg-orange-600 transition-all transform hover:scale-105 shadow-lg w-full sm:w-auto"
                >
                    Start Your Order
                </button>
            </div>
        </div>
    );

    // Step 2: Form
    const CustomerForm = () => (
        <div className="min-h-screen flex items-center justify-center bg-gray-900 px-4">
            <div className="w-full max-w-md p-6 sm:p-8 bg-gray-800 rounded-2xl shadow-2xl">
                <div className="flex justify-center mb-6">
                    <User className="w-14 h-14 sm:w-16 sm:h-16 text-orange-500" />
                </div>
                <h2 className="text-2xl sm:text-3xl font-bold text-white mb-6 text-center">
                    Enter Your Details
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
                                className="block text-sm font-medium text-gray-300"
                            >
                                {field === 'nama'
                                    ? 'Full Name'
                                    : field === 'whatsapp'
                                    ? 'WhatsApp Number'
                                    : 'Email Address'}
                            </label>
                            <input
                                type={field === 'email' ? 'email' : field === 'whatsapp' ? 'tel' : 'text'}
                                id={field}
                                name={field}
                                required
                                className="mt-1 block w-full rounded-lg bg-gray-700 border-gray-600 text-white shadow-sm focus:border-orange-500 focus:ring-orange-500 p-3 sm:p-3.5"
                                placeholder={
                                    field === 'nama'
                                        ? 'Enter your full name'
                                        : field === 'whatsapp'
                                        ? 'Enter your WhatsApp number'
                                        : 'Enter your email address'
                                }
                            />
                        </div>
                    ))}
                    <button
                        type="submit"
                        className="w-full py-3 sm:py-3.5 px-4 bg-orange-500 text-white rounded-lg font-semibold hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-gray-900 transform hover:scale-105 transition-all"
                    >
                        Continue to Menu
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
            <div className="min-h-screen bg-gray-900">
                {customerData && (
                    <div className="bg-gray-800 text-white p-4 sm:p-5 fixed top-0 w-full z-10 shadow-lg">
                        <div className="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-3">
                            <div className="flex items-center space-x-3">
                                <User className="w-6 h-6 text-orange-500" />
                                <div>
                                    <span className="font-semibold text-white block">{customerData.nama}</span>
                                    <span className="text-sm text-gray-400">{customerData.whatsapp}</span>
                                </div>
                            </div>
                            <div className="flex items-center space-x-4">
                                <button
                                    onClick={() => setIsCartOpen(true)} 
                                    className="relative"
                                    aria-label="Open shopping cart"
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
                                    className="text-xs sm:text-sm px-3 sm:px-4 py-2 rounded-lg bg-orange-500 hover:bg-orange-600 transition-all flex items-center space-x-1 sm:space-x-2"
                                >
                                    <Home className="w-4 h-4" />
                                    <span>New Order</span>
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Floating Cart Button (Mobile) */}
                {Object.keys(cartItems).length > 0 && (
                    <div className="fixed bottom-6 right-6 z-10 md:hidden">
                        <button
                            onClick={() => setIsCartOpen(true)}
                            className="flex items-center justify-center p-3 bg-orange-500 text-white rounded-full shadow-lg hover:bg-orange-600 transition-all"
                        >
                            <ShoppingCart className="w-6 h-6" />
                            <span className="absolute -top-2 -right-2 bg-white text-orange-500 text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center border-2 border-orange-500">
                                {Object.values(cartItems).reduce((a, b) => a + b, 0)}
                            </span>
                        </button>
                    </div>
                )}

                {/* Produk Grid */}
                <div className="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 pt-28 sm:pt-24 pb-12">
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4 sm:gap-6">
                        {products.map((product) => (
                            <div
                                key={product.id}
                                className="bg-gray-800 rounded-lg shadow-xl overflow-hidden hover:shadow-2xl transition-all transform hover:scale-[1.02] flex flex-col"
                            >
                                <div className="relative">
                                    {product.gambar && (
                                        <img
                                            src={product.gambar}
                                            alt={product.nama}
                                            className="w-full aspect-video object-cover"
                                        />
                                    )}
                                    {product.stok <= 0 && (
                                        <div className="absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center">
                                            <span className="text-white font-semibold text-sm sm:text-base">Out of Stock</span>
                                        </div>
                                    )}
                                </div>
                                <div className="p-3 sm:p-4 flex flex-col flex-grow">
                                    <h3 className="text-sm sm:text-lg font-semibold text-white line-clamp-2">
                                        {product.nama}
                                    </h3>
                                    <p className="text-gray-400 text-xs sm:text-sm">Stock: {product.stok}</p>
                                    <div className="mt-auto flex items-center justify-between pt-3">
                                        <span className="text-base sm:text-xl font-bold text-orange-500">
                                            Rp {parseInt(product.harga).toLocaleString('id-ID')}
                                        </span>
                                        {product.stok > 0 ? (
                                            cartItems[product.id] ? (
                                                <div className="flex items-center space-x-1 sm:space-x-2">
                                                    <button
                                                        onClick={() => removeFromCart(product.id)}
                                                        className="p-1 sm:p-1.5 rounded-lg bg-gray-700 text-white hover:bg-gray-600"
                                                    >
                                                        <Minus className="w-4 h-4" />
                                                    </button>
                                                    <span className="text-white px-1 sm:px-2 text-sm sm:text-base">{cartItems[product.id]}</span>
                                                    <button
                                                        onClick={() => addToCart(product)}
                                                        className="p-1 sm:p-1.5 rounded-lg bg-gray-700 text-white hover:bg-gray-600"
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
                                                    <span>Add</span>
                                                </button>
                                            )
                                        ) : (
                                            <button
                                                className="px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-700 text-gray-400 rounded-lg text-sm sm:text-base cursor-not-allowed"
                                                disabled
                                            >
                                                Out
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
                        <div className="absolute inset-0 bg-black bg-opacity-50" onClick={() => setIsCartOpen(false)}></div>
                        <div className="w-full max-w-md bg-gray-800 h-full overflow-y-auto shadow-xl transform transition-all ease-in-out duration-300 z-10">
                            <div className="p-4 sm:p-6 flex flex-col h-full">
                                <div className="flex items-center justify-between mb-6">
                                    <h2 className="text-xl sm:text-2xl font-bold text-white flex items-center">
                                        <ShoppingCart className="mr-2 text-orange-500" /> Your Cart
                                    </h2>
                                    <button
                                        onClick={() => setIsCartOpen(false)}
                                        className="p-2 rounded-full hover:bg-gray-700 transition-colors"
                                    >
                                        <X className="w-5 h-5 text-gray-400" />
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
                                                    <div
                                                        key={productId}
                                                        className="mb-4 p-4 bg-gray-700 rounded-lg flex items-start"
                                                    >
                                                        {product.gambar && (
                                                            <img
                                                                src={product.gambar}
                                                                alt={product.nama}
                                                                className="w-20 h-20 object-cover rounded-md mr-3"
                                                            />
                                                        )}
                                                        <div className="flex-grow">
                                                            <h3 className="text-white font-medium mb-1">{product.nama}</h3>
                                                            <p className="text-orange-500 font-semibold mb-2">
                                                                Rp {parseInt(product.harga).toLocaleString('id-ID')}
                                                            </p>
                                                            <div className="flex items-center justify-between">
                                                                <div className="flex items-center border border-gray-600 rounded-lg overflow-hidden">
                                                                    <button
                                                                        onClick={() => updateCartQuantity(productId, quantity - 1)}
                                                                        className="px-3 py-1 bg-gray-800 text-white hover:bg-gray-900"
                                                                    >
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
                                                                        className="w-12 text-center bg-gray-800 text-white border-0 focus:ring-0"
                                                                    />
                                                                    <button
                                                                        onClick={() => updateCartQuantity(productId, quantity + 1)}
                                                                        className="px-3 py-1 bg-gray-800 text-white hover:bg-gray-900"
                                                                    >
                                                                        <Plus className="w-4 h-4" />
                                                                    </button>
                                                                </div>
                                                                <p className="text-white font-medium">
                                                                    Rp {parseInt(subtotal).toLocaleString('id-ID')}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        <div className="border-t border-gray-700 mt-4 pt-4">
                                            <div className="flex justify-between mb-2 text-gray-400">
                                                <span>Total Items:</span>
                                                <span>{Object.values(cartItems).reduce((a, b) => a + b, 0)}</span>
                                            </div>
                                            <div className="flex justify-between mb-6 text-white font-bold text-xl">
                                                <span>Total:</span>
                                                <span className="text-orange-500">
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
                                                <span>Proceed to Checkout</span>
                                                <ChevronRight className="ml-2 w-5 h-5" />
                                            </Link>
                                        </div>
                                    </>
                                ) : (
                                    <div className="flex-grow flex flex-col items-center justify-center text-center">
                                        <ShoppingCart className="w-16 h-16 text-gray-600 mb-4" />
                                        <h3 className="text-white text-xl font-medium mb-2">Your cart is empty</h3>
                                        <p className="text-gray-400 mb-6">Add some delicious items to your cart!</p>
                                        <button
                                            onClick={() => setIsCartOpen(false)}
                                            className="px-6 py-2 bg-orange-500 text-white rounded-lg font-medium hover:bg-orange-600 transition-all flex items-center"
                                        >
                                            <ArrowLeft className="mr-2 w-5 h-5" />
                                            <span>Continue Shopping</span>
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
            <Head title="Welcome" />
            {step === 1 && <WelcomeScreen />}
            {step === 2 && <CustomerForm />}
            {step === 3 && <ProductList />}
        </>
    );
};

export default Welcome;
