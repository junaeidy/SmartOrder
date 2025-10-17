import React from 'react';
import { Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';

// Import ikon dari lucide-react
import { Home, ShoppingCart, LogIn, User } from 'lucide-react';

export default function MainLayout({ children, user }) { // Tambahkan prop 'user' jika ada autentikasi
    
    // Asumsi rute Inertia yang relevan:
    const currentRoute = route().current();
    const isHome = currentRoute === 'welcome';
    // Ganti 'checkout' dengan nama rute Anda yang sebenarnya
    const isCheckout = currentRoute === 'checkout'; 

    const NavLink = ({ href, active, children, icon: Icon }) => (
        <Link
            href={href}
            className={`inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out 
                ${active
                    ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100 focus:border-indigo-700'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700 focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-700'
                }
            `}
        >
            {Icon && <Icon className="w-5 h-5 mr-1" />}
            {children}
        </Link>
    );

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
            {/* Header / Navigasi Utama */}
            <nav className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-md sticky top-0 z-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        {/* Kiri: Logo & Tautan Navigasi */}
                        <div className="flex items-center">
                            <div className="shrink-0">
                                <Link href={route('welcome')}>
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-indigo-600 dark:text-indigo-400" />
                                </Link>
                            </div>
                            
                            {/* Tautan Desktop */}
                            <div className="hidden space-x-6 sm:ml-10 sm:flex">
                                <NavLink 
                                    href={route('welcome')} 
                                    active={isHome} 
                                    icon={Home}
                                >
                                    Beranda
                                </NavLink>
                                
                                {/* Contoh Tautan Lain */}
                                {/* <NavLink href={route('products')} active={false}>Produk</NavLink> */}
                            </div>
                        </div>
                        
                        {/* Kanan: Aksi Cepat (Keranjang & Auth) */}
                        <div className="flex items-center space-x-4">
                            
                            {/* Tombol Checkout/Keranjang Menonjol */}
                            <Link
                                href={route('checkout')} // Ganti dengan rute checkout Anda
                                className={`inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-lg transition duration-150 ease-in-out
                                    ${isCheckout 
                                        ? 'bg-indigo-700 text-white hover:bg-indigo-800' // Jika sedang di halaman checkout
                                        : 'bg-indigo-600 text-white hover:bg-indigo-700'
                                    }
                                `}
                            >
                                <ShoppingCart className="w-5 h-5 mr-2" />
                                Checkout
                            </Link>

                            {/* Tautan Auth (Hanya contoh, ganti dengan komponen Auth Inertia Anda) */}
                            {user ? (
                                <Link 
                                    href={route('dashboard')} 
                                    className="p-2 text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                >
                                    <User className="w-6 h-6" />
                                </Link>
                            ) : (
                                <Link 
                                    href={route('login')} 
                                    className="p-2 text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                >
                                    <LogIn className="w-6 h-6" />
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </nav>

            {/* Konten Utama */}
            <main className="flex-grow">{children}</main>
            
            {/* Footer */}
            <footer className="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        &copy; {new Date().getFullYear()} <span className="font-semibold text-indigo-600 dark:text-indigo-400">SmartOrder</span>. All rights reserved.
                    </div>
                </div>
            </footer>
        </div>
    );
}
