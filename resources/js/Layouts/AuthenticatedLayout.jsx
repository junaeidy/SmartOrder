import Dropdown from '@/Components/Dropdown';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import Sidebar from '@/Components/Sidebar';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Bell } from 'lucide-react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [alerts, setAlerts] = useState([]);
    const bellRef = useRef(null);

    // Fetch initial alerts snapshot
    useEffect(() => {
        fetch('/kasir/stock/alerts', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                const list = [];
                data.out.forEach(p => list.push({ type: 'out_of_stock', product: p, at: Date.now() }));
                data.low.forEach(p => list.push({ type: 'low_stock', product: p, at: Date.now() }));
                data.closed.forEach(p => list.push({ type: 'closed', product: p, at: Date.now() }));
                setAlerts(list);
            })
            .catch(() => {});
    }, []);

    // Subscribe to product alerts via Echo
    useEffect(() => {
        try {
            const channel = window.Echo?.channel('products');
            channel?.listen('.ProductStockAlert', (e) => {
                setAlerts(prev => [{ type: e.type, product: e.product, at: Date.now() }, ...prev].slice(0, 50));
            });
            return () => {
                window.Echo?.leave('products');
            };
        } catch (_) {}
    }, []);

    // Close dropdown on outside click
    useEffect(() => {
        const onDocClick = (ev) => {
            if (notificationsOpen && bellRef.current && !bellRef.current.contains(ev.target)) {
                setNotificationsOpen(false);
            }
        };
        document.addEventListener('click', onDocClick);
        return () => document.removeEventListener('click', onDocClick);
    }, [notificationsOpen]);

    return (
    <div className="min-h-screen flex bg-gray-100 dark:bg-gray-900">
            {/* Sidebar kiri */}
            <Sidebar />

            {/* Bagian kanan (konten utama) */}
            <div className="flex-1 ml-64">
                <nav className="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-16 justify-between items-center">
                            {/* Brand / Logo */}
                            <div className="flex">
                                <Link href="" className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                    <marquee>Selamat datang dan selamat bekerja!</marquee>
                                </Link>
                            </div>

                            {/* Bell + Dropdown Profil */}
                            <div className="hidden sm:flex sm:items-center space-x-4">
                                {/* Bell notifications */}
                                <div className="relative" ref={bellRef}>
                                    <button
                                        onClick={() => setNotificationsOpen((v) => !v)}
                                        className="relative inline-flex items-center justify-center rounded-full p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:bg-gray-700"
                                        aria-label="Notifikasi stok"
                                    >
                                        <Bell className="h-6 w-6" />
                                        {alerts.length > 0 && (
                                            <span className="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">
                                                {alerts.length}
                                            </span>
                                        )}
                                    </button>
                                    {notificationsOpen && (
                                        <div className="absolute right-0 mt-2 w-80 max-h-96 overflow-auto rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-50">
                                            <div className="p-2 border-b dark:border-gray-700 flex justify-between items-center">
                                                <span className="font-semibold text-gray-800 dark:text-gray-100">Notifikasi</span>
                                                <button onClick={() => setAlerts([])} className="text-xs text-blue-600 dark:text-blue-400">Bersihkan</button>
                                            </div>
                                            <ul className="divide-y divide-gray-100 dark:divide-gray-700">
                                                {alerts.length === 0 ? (
                                                    <li className="p-3 text-sm text-gray-500 dark:text-gray-400">Tidak ada notifikasi.</li>
                                                ) : (
                                                    alerts.map((a, idx) => (
                                                        <li key={idx} className="p-3 text-sm">
                                                            <div className="flex items-start">
                                                                <span className={`mt-0.5 mr-2 inline-block w-2 h-2 rounded-full ${a.type === 'out_of_stock' ? 'bg-red-500' : a.type === 'low_stock' ? 'bg-yellow-500' : a.type === 'closed' ? 'bg-gray-500' : 'bg-green-500'}`}></span>
                                                                <div className="flex-1">
                                                                    <div className="text-gray-900 dark:text-gray-100 font-medium">{a.product.nama}</div>
                                                                    <div className="text-gray-600 dark:text-gray-300">
                                                                        {a.type === 'out_of_stock' && 'Stok habis.'}
                                                                        {a.type === 'low_stock' && `Stok menipis (${a.product.stok}).`}
                                                                        {a.type === 'closed' && 'Ditutup sementara.'}
                                                                        {a.type === 'opened' && 'Dibuka kembali.'}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    ))
                                                )}
                                            </ul>
                                        </div>
                                    )}
                                </div>
                                <div className="relative ms-3">
                                    <Dropdown>
                                        <Dropdown.Trigger>
                                            <span className="inline-flex rounded-md">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                                >
                                                    {user.name}
                                                    <svg
                                                        className="-me-0.5 ms-2 h-4 w-4"
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 20 20"
                                                        fill="currentColor"
                                                    >
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                </button>
                                            </span>
                                        </Dropdown.Trigger>

                                        <Dropdown.Content>
                                            <Dropdown.Link href={route('profile.edit')}>
                                                Profile
                                            </Dropdown.Link>
                                            <Dropdown.Link href={route('logout')} method="post" as="button">
                                                Log Out
                                            </Dropdown.Link>
                                        </Dropdown.Content>
                                    </Dropdown>
                                </div>
                            </div>

                            {/* Tombol responsive (mobile menu) */}
                            <div className="-me-2 flex items-center sm:hidden">
                                <button
                                    onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                                    className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                                >
                                    <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path
                                            className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M4 6h16M4 12h16M4 18h16"
                                        />
                                        <path
                                            className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Mobile dropdown */}
                    <div
                        className={`${
                            showingNavigationDropdown ? 'block' : 'hidden'
                        } sm:hidden`}
                    >
                        <div className="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600">
                            <div className="px-4">
                                <div className="text-base font-medium text-gray-800 dark:text-gray-200">
                                    {user.name}
                                </div>
                                <div className="text-sm font-medium text-gray-500">{user.email}</div>
                            </div>

                            <div className="mt-3 space-y-1">
                                <ResponsiveNavLink href={route('profile.edit')}>
                                    Profile
                                </ResponsiveNavLink>
                                <ResponsiveNavLink method="post" href={route('logout')} as="button">
                                    Log Out
                                </ResponsiveNavLink>
                            </div>
                        </div>
                    </div>
                </nav>

                {header && (
                    <header className="bg-white shadow dark:bg-gray-800">
                        <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                <main className="p-6">{children}</main>
            </div>
        </div>
    );
}
