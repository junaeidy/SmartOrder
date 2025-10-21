import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { Clock, DollarSign, Store, Percent, Calendar, Tag, TicketIcon } from 'lucide-react';
import toast, { Toaster } from 'react-hot-toast';

export default function Settings({ auth, storeHours, storeSettings, discounts }) {
    const [activeTab, setActiveTab] = useState('store-hours');
    const [editDiscountId, setEditDiscountId] = useState(null);

    // Store Hours Form
    const hoursForm = useForm({
        monday_open: storeHours.monday_open,
        monday_close: storeHours.monday_close,
        tuesday_open: storeHours.tuesday_open,
        tuesday_close: storeHours.tuesday_close,
        wednesday_open: storeHours.wednesday_open,
        wednesday_close: storeHours.wednesday_close,
        thursday_open: storeHours.thursday_open,
        thursday_close: storeHours.thursday_close,
        friday_open: storeHours.friday_open,
        friday_close: storeHours.friday_close,
        saturday_open: storeHours.saturday_open,
        saturday_close: storeHours.saturday_close,
        sunday_open: storeHours.sunday_open,
        sunday_close: storeHours.sunday_close,
    });

    // Store Settings Form
    const settingsForm = useForm({
        store_name: storeSettings.store_name,
        store_address: storeSettings.store_address,
        store_phone: storeSettings.store_phone,
        store_email: storeSettings.store_email,
        tax_percentage: storeSettings.tax_percentage,
        store_closed: storeSettings.store_closed,
    });

    // New Discount Form
    const newDiscountForm = useForm({
        name: '',
        code: '',
        description: '',
        percentage: '',
        min_purchase: '0',
        active: true,
        requires_code: true,
        valid_from: '',
        valid_until: '',
    });

    // Edit Discount Form
    const editDiscountForm = useForm({
        id: '',
        name: '',
        code: '',
        description: '',
        percentage: '',
        min_purchase: '',
        active: true,
        requires_code: true,
        valid_from: '',
        valid_until: '',
    });

    const submitStoreHours = (e) => {
        e.preventDefault();
        hoursForm.post(route('admin.settings.store-hours'), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Jam operasional berhasil diperbarui!');
            },
            onError: (errors) => {
                toast.error('Gagal memperbarui jam operasional.');
            }
        });
    };

    const submitStoreSettings = (e) => {
        e.preventDefault();
        settingsForm.post(route('admin.settings.store-settings'), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Pengaturan toko berhasil disimpan!');
            },
            onError: (errors) => {
                toast.error('Gagal menyimpan pengaturan toko.');
            }
        });
    };

    const submitNewDiscount = (e) => {
        e.preventDefault();
        newDiscountForm.post(route('admin.discounts.store'), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Diskon baru berhasil ditambahkan!');
                newDiscountForm.reset();
            },
            onError: (errors) => {
                toast.error('Gagal menambahkan diskon baru.');
            }
        });
    };

    const submitEditDiscount = (e) => {
        e.preventDefault();
        const id = editDiscountForm.data?.id || editDiscountId;
        if (!id) {
            toast.error('Pilih diskon yang akan diedit.');
            return;
        }
        editDiscountForm.put(route('admin.discounts.update', { discount: id }), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Diskon berhasil diperbarui!');
                setEditDiscountId(null);
            },
            onError: (errors) => {
                toast.error('Gagal memperbarui diskon.');
            }
        });
    };

    const handleDeleteDiscount = (id) => {
        if (confirm('Apakah Anda yakin ingin menghapus diskon ini?')) {
            router.delete(route('admin.discounts.destroy', { discount: id }), {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Diskon berhasil dihapus!');
                },
                onError: () => {
                    toast.error('Gagal menghapus diskon.');
                }
            });
        }
    };

    const handleToggleDiscount = (id) => {
        router.put(route('admin.discounts.toggle', { discount: id }), {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const isActive = page.props.discounts.find(d => d.id === id)?.active;
                toast.success(`Diskon berhasil ${isActive ? 'diaktifkan' : 'dinonaktifkan'}!`);
            },
            onError: () => {
                toast.error('Gagal mengubah status diskon.');
            }
        });
    };

    const startEditDiscount = (discount) => {
        setEditDiscountId(discount.id);
        editDiscountForm.setData({
            id: discount.id,
            name: discount.name,
            code: discount.code || '',
            description: discount.description || '',
            percentage: discount.percentage,
            min_purchase: discount.min_purchase,
            active: discount.active,
            requires_code: discount.requires_code ?? true,
            valid_from: discount.valid_from ? new Date(discount.valid_from).toISOString().split('T')[0] : '',
            valid_until: discount.valid_until ? new Date(discount.valid_until).toISOString().split('T')[0] : '',
        });
    };

    const cancelEdit = () => {
        setEditDiscountId(null);
    };

    // Format date for display
    const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }).format(date);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-50">Pengaturan Toko</h2>}
        >
            <Head title="Pengaturan Toko" />
            <Toaster position="top-right" toastOptions={{
                duration: 3000,
                style: {
                    background: '#333',
                    color: '#fff',
                },
                success: {
                    style: {
                        background: '#10B981',
                    },
                },
                error: {
                    style: {
                        background: '#EF4444',
                    },
                }
            }} />

            <div className="py-6 sm:py-10">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm rounded-lg overflow-hidden">
                        {/* Tabs */}
                        <div className="border-b border-gray-200 dark:border-gray-700">
                            <nav className="flex -mb-px">
                                <button
                                    className={`py-4 px-6 text-center border-b-2 font-medium text-sm ${
                                        activeTab === 'store-hours'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                                    }`}
                                    onClick={() => setActiveTab('store-hours')}
                                >
                                    <div className="flex items-center">
                                        <Clock className="h-4 w-4 mr-2" />
                                        Jam Operasional
                                    </div>
                                </button>
                                <button
                                    className={`py-4 px-6 text-center border-b-2 font-medium text-sm ${
                                        activeTab === 'store-settings'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                                    }`}
                                    onClick={() => setActiveTab('store-settings')}
                                >
                                    <div className="flex items-center">
                                        <Store className="h-4 w-4 mr-2" />
                                        Pengaturan Toko
                                    </div>
                                </button>
                                <button
                                    className={`py-4 px-6 text-center border-b-2 font-medium text-sm ${
                                        activeTab === 'discounts'
                                            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600'
                                    }`}
                                    onClick={() => setActiveTab('discounts')}
                                >
                                    <div className="flex items-center">
                                        <Percent className="h-4 w-4 mr-2" />
                                        Diskon
                                    </div>
                                </button>
                            </nav>
                        </div>

                        {/* Tab Content */}
                        <div className="p-6">
                            {/* Store Hours Tab */}
                            {activeTab === 'store-hours' && (
                                <div>
                                    <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Jam Operasional Toko</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
                                        Atur jam buka dan tutup toko untuk setiap hari dalam seminggu.
                                    </p>

                                    <form onSubmit={submitStoreHours} className="space-y-6">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            {['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'].map((day) => (
                                                <div key={day} className="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                                    <h4 className="font-medium mb-3 capitalize">
                                                        {day === 'monday' ? 'Senin' : 
                                                         day === 'tuesday' ? 'Selasa' : 
                                                         day === 'wednesday' ? 'Rabu' : 
                                                         day === 'thursday' ? 'Kamis' : 
                                                         day === 'friday' ? 'Jumat' : 
                                                         day === 'saturday' ? 'Sabtu' : 'Minggu'}
                                                    </h4>
                                                    <div className="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <InputLabel htmlFor={`${day}_open`} value="Jam Buka" />
                                                            <TextInput
                                                                id={`${day}_open`}
                                                                type="time"
                                                                className="mt-1 block w-full"
                                                                value={hoursForm.data[`${day}_open`]}
                                                                onChange={(e) => hoursForm.setData(`${day}_open`, e.target.value)}
                                                            />
                                                            <InputError message={hoursForm.errors[`${day}_open`]} className="mt-2" />
                                                        </div>
                                                        <div>
                                                            <InputLabel htmlFor={`${day}_close`} value="Jam Tutup" />
                                                            <TextInput
                                                                id={`${day}_close`}
                                                                type="time"
                                                                className="mt-1 block w-full"
                                                                value={hoursForm.data[`${day}_close`]}
                                                                onChange={(e) => hoursForm.setData(`${day}_close`, e.target.value)}
                                                            />
                                                            <InputError message={hoursForm.errors[`${day}_close`]} className="mt-2" />
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>

                                        <div className="flex items-center justify-end">
                                            <PrimaryButton className="ml-4" disabled={hoursForm.processing}>
                                                {hoursForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                            </PrimaryButton>
                                        </div>
                                    </form>
                                </div>
                            )}

                            {/* Store Settings Tab */}
                            {activeTab === 'store-settings' && (
                                <div>
                                    <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Pengaturan Toko</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
                                        Atur informasi dasar dan pengaturan operasional toko.
                                    </p>

                                    <form onSubmit={submitStoreSettings} className="space-y-6">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <InputLabel htmlFor="store_name" value="Nama Toko" />
                                                <TextInput
                                                    id="store_name"
                                                    type="text"
                                                    className="mt-1 block w-full"
                                                    value={settingsForm.data.store_name}
                                                    onChange={(e) => settingsForm.setData('store_name', e.target.value)}
                                                    required
                                                />
                                                <InputError message={settingsForm.errors.store_name} className="mt-2" />
                                            </div>

                                            <div>
                                                <InputLabel htmlFor="store_phone" value="Nomor Telepon" />
                                                <TextInput
                                                    id="store_phone"
                                                    type="text"
                                                    className="mt-1 block w-full"
                                                    value={settingsForm.data.store_phone}
                                                    onChange={(e) => settingsForm.setData('store_phone', e.target.value)}
                                                />
                                                <InputError message={settingsForm.errors.store_phone} className="mt-2" />
                                            </div>

                                            <div>
                                                <InputLabel htmlFor="store_email" value="Email" />
                                                <TextInput
                                                    id="store_email"
                                                    type="email"
                                                    className="mt-1 block w-full"
                                                    value={settingsForm.data.store_email}
                                                    onChange={(e) => settingsForm.setData('store_email', e.target.value)}
                                                />
                                                <InputError message={settingsForm.errors.store_email} className="mt-2" />
                                            </div>

                                            <div>
                                                <InputLabel htmlFor="tax_percentage" value="Persentase Pajak (%)" />
                                                <TextInput
                                                    id="tax_percentage"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    className="mt-1 block w-full"
                                                    value={settingsForm.data.tax_percentage}
                                                    onChange={(e) => settingsForm.setData('tax_percentage', e.target.value)}
                                                    required
                                                />
                                                <InputError message={settingsForm.errors.tax_percentage} className="mt-2" />
                                            </div>

                                            <div className="md:col-span-2">
                                                <InputLabel htmlFor="store_address" value="Alamat Toko" />
                                                <textarea
                                                    id="store_address"
                                                    className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                                    value={settingsForm.data.store_address}
                                                    onChange={(e) => settingsForm.setData('store_address', e.target.value)}
                                                    rows="3"
                                                ></textarea>
                                                <InputError message={settingsForm.errors.store_address} className="mt-2" />
                                            </div>

                                            <div className="md:col-span-2">
                                                <div className="flex items-center">
                                                    <input
                                                        id="store_closed"
                                                        type="checkbox"
                                                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                        checked={settingsForm.data.store_closed}
                                                        onChange={(e) => settingsForm.setData('store_closed', e.target.checked)}
                                                    />
                                                    <label htmlFor="store_closed" className="ml-2 block text-sm text-red-600 font-medium">
                                                        Tutup Toko Sementara (Override jam operasional)
                                                    </label>
                                                </div>
                                                <p className="mt-1 text-sm text-gray-500">
                                                    Jika diaktifkan, toko akan ditampilkan sebagai "Tutup" kepada pelanggan.
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-end">
                                            <PrimaryButton className="ml-4" disabled={settingsForm.processing}>
                                                {settingsForm.processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                                            </PrimaryButton>
                                        </div>
                                    </form>
                                </div>
                            )}

                            {/* Discounts Tab */}
                            {activeTab === 'discounts' && (
                                <div className="max-w-4xl mx-auto">
                                    <h3 className="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">Kelola Diskon</h3>
                                    
                                    {/* New Discount Form */}
                                    <div className="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg mb-6">
                                        <h4 className="font-medium text-base mb-3">Tambah Diskon Baru</h4>
                                        
                                        <form onSubmit={submitNewDiscount} className="space-y-3">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <InputLabel htmlFor="name" value="Nama Diskon" />
                                                    <TextInput
                                                        id="name"
                                                        type="text"
                                                        className="mt-1 block w-full text-sm"
                                                        value={newDiscountForm.data.name}
                                                        onChange={(e) => newDiscountForm.setData('name', e.target.value)}
                                                        required
                                                    />
                                                    <InputError message={newDiscountForm.errors.name} className="mt-2" />
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="code" value="Kode Diskon" />
                                                    <TextInput
                                                        id="code"
                                                        type="text"
                                                        className="mt-1 block w-full text-sm"
                                                        value={newDiscountForm.data.code}
                                                        onChange={(e) => newDiscountForm.setData('code', e.target.value.toUpperCase())}
                                                        placeholder="Contoh: WELCOME25"
                                                        required
                                                    />
                                                    <InputError message={newDiscountForm.errors.code} className="mt-2" />
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="percentage" value="Persentase Diskon (%)" />
                                                    <TextInput
                                                        id="percentage"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        className="mt-1 block w-full text-sm"
                                                        value={newDiscountForm.data.percentage}
                                                        onChange={(e) => newDiscountForm.setData('percentage', e.target.value)}
                                                        required
                                                    />
                                                    <InputError message={newDiscountForm.errors.percentage} className="mt-2" />
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="min_purchase" value="Minimum Pembelian (Rp)" />
                                                    <TextInput
                                                        id="min_purchase"
                                                        type="number"
                                                        min="0"
                                                        className="mt-1 block w-full text-sm"
                                                        value={newDiscountForm.data.min_purchase}
                                                        onChange={(e) => newDiscountForm.setData('min_purchase', e.target.value)}
                                                        required
                                                    />
                                                    <InputError message={newDiscountForm.errors.min_purchase} className="mt-2" />
                                                </div>

                                                <div className="flex items-center gap-6">
                                                    <div className="flex items-center">
                                                        <input
                                                            id="active"
                                                            type="checkbox"
                                                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                            checked={newDiscountForm.data.active}
                                                            onChange={(e) => newDiscountForm.setData('active', e.target.checked)}
                                                        />
                                                        <label htmlFor="active" className="ml-2 block text-xs font-medium">
                                                            Aktifkan Diskon
                                                        </label>
                                                    </div>
                                                    
                                                    <div className="flex items-center">
                                                        <input
                                                            id="requires_code"
                                                            type="checkbox"
                                                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                            checked={newDiscountForm.data.requires_code}
                                                            onChange={(e) => newDiscountForm.setData('requires_code', e.target.checked)}
                                                        />
                                                        <label htmlFor="requires_code" className="ml-2 block text-xs font-medium">
                                                            Wajib Input Kode
                                                        </label>
                                                    </div>
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="valid_from" value="Berlaku Dari" />
                                                    <TextInput
                                                        id="valid_from"
                                                        type="date"
                                                        className="mt-1 block w-full text-sm"
                                                        value={newDiscountForm.data.valid_from}
                                                        onChange={(e) => newDiscountForm.setData('valid_from', e.target.value)}
                                                    />
                                                    <InputError message={newDiscountForm.errors.valid_from} className="mt-2" />
                                                </div>

                                                <div>
                                                    <InputLabel htmlFor="valid_until" value="Berlaku Sampai" />
                                                    <TextInput
                                                        id="valid_until"
                                                        type="date"
                                                        className="mt-1 block w-full text-sm"
                                                        value={newDiscountForm.data.valid_until}
                                                        onChange={(e) => newDiscountForm.setData('valid_until', e.target.value)}
                                                    />
                                                    <InputError message={newDiscountForm.errors.valid_until} className="mt-2" />
                                                </div>

                                                <div className="md:col-span-2">
                                                    <InputLabel htmlFor="description" value="Deskripsi (Opsional)" />
                                                    <textarea
                                                        id="description"
                                                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm text-sm"
                                                        value={newDiscountForm.data.description}
                                                        onChange={(e) => newDiscountForm.setData('description', e.target.value)}
                                                        rows="2"
                                                    ></textarea>
                                                    <InputError message={newDiscountForm.errors.description} className="mt-2" />
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-end">
                                                <PrimaryButton className="ml-4" disabled={newDiscountForm.processing}>
                                                    {newDiscountForm.processing ? 'Menambahkan...' : 'Tambah Diskon Baru'}
                                                </PrimaryButton>
                                            </div>
                                        </form>
                                    </div>

                                    {/* Discount List */}
                                    <div>
                                        <h4 className="font-medium text-lg mb-4">Daftar Diskon</h4>
                                        
                                        {discounts && discounts.length > 0 ? (
                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm text-gray-900 dark:text-gray-100">
                                                    <thead className="bg-gray-50 dark:bg-gray-800">
                                                        <tr>
                                                            <th scope="col" className="px-3 py-2 text-left text-[11px] font-semibold text-gray-700 dark:text-gray-100 uppercase tracking-wider">
                                                                Nama
                                                            </th>
                                                            <th scope="col" className="px-3 py-2 text-left text-[11px] font-semibold text-gray-700 dark:text-gray-100 uppercase tracking-wider">
                                                                Kode
                                                            </th>
                                                            <th scope="col" className="px-3 py-2 text-left text-[11px] font-semibold text-gray-700 dark:text-gray-100 uppercase tracking-wider">
                                                                Persentase
                                                            </th>
                                                            <th scope="col" className="px-3 py-2 text-left text-[11px] font-semibold text-gray-700 dark:text-gray-100 uppercase tracking-wider">
                                                                Min. Pembelian
                                                            </th>
                                                            <th scope="col" className="px-3 py-2 text-left text-[11px] font-semibold text-gray-700 dark:text-gray-100 uppercase tracking-wider">
                                                                Periode
                                                            </th>
                                                            <th scope="col" className="px-3 py-2 text-left text-[11px] font-semibold text-gray-700 dark:text-gray-100 uppercase tracking-wider">
                                                                Status
                                                            </th>
                                                            <th scope="col" className="px-3 py-2 text-right text-[11px] font-semibold text-gray-700 dark:text-gray-100 uppercase tracking-wider">
                                                                Aksi
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700 text-gray-900 dark:text-gray-100">
                                                        {discounts.map((discount) => (
                                                            <tr key={discount.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                                {editDiscountId === discount.id ? (
                                                                    <td colSpan="6" className="px-6 py-4">
                                                                        <form onSubmit={submitEditDiscount} className="space-y-4">
                                                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                                                <div>
                                                                                    <InputLabel htmlFor="edit_name" value="Nama Diskon" />
                                                                                    <TextInput
                                                                                        id="edit_name"
                                                                                        type="text"
                                                                                        className="mt-1 block w-full"
                                                                                        value={editDiscountForm.data.name}
                                                                                        onChange={(e) => editDiscountForm.setData('name', e.target.value)}
                                                                                        required
                                                                                    />
                                                                                </div>
                                                                                
                                                                                <div>
                                                                                    <InputLabel htmlFor="edit_code" value="Kode Diskon" />
                                                                                    <TextInput
                                                                                        id="edit_code"
                                                                                        type="text"
                                                                                        className="mt-1 block w-full"
                                                                                        value={editDiscountForm.data.code}
                                                                                        onChange={(e) => editDiscountForm.setData('code', e.target.value.toUpperCase())}
                                                                                        placeholder="Contoh: WELCOME25"
                                                                                        required
                                                                                    />
                                                                                </div>

                                                                                <div>
                                                                                    <InputLabel htmlFor="edit_percentage" value="Persentase Diskon (%)" />
                                                                                    <TextInput
                                                                                        id="edit_percentage"
                                                                                        type="number"
                                                                                        step="0.01"
                                                                                        min="0"
                                                                                        max="100"
                                                                                        className="mt-1 block w-full"
                                                                                        value={editDiscountForm.data.percentage}
                                                                                        onChange={(e) => editDiscountForm.setData('percentage', e.target.value)}
                                                                                        required
                                                                                    />
                                                                                </div>

                                                                                <div>
                                                                                    <InputLabel htmlFor="edit_min_purchase" value="Minimum Pembelian (Rp)" />
                                                                                    <TextInput
                                                                                        id="edit_min_purchase"
                                                                                        type="number"
                                                                                        min="0"
                                                                                        className="mt-1 block w-full"
                                                                                        value={editDiscountForm.data.min_purchase}
                                                                                        onChange={(e) => editDiscountForm.setData('min_purchase', e.target.value)}
                                                                                        required
                                                                                    />
                                                                                </div>

                                                                                <div className="flex items-center gap-4">
                                                                                    <div className="flex items-center">
                                                                                        <input
                                                                                            id="edit_active"
                                                                                            type="checkbox"
                                                                                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                                                            checked={editDiscountForm.data.active}
                                                                                            onChange={(e) => editDiscountForm.setData('active', e.target.checked)}
                                                                                        />
                                                                                        <label htmlFor="edit_active" className="ml-2 block text-sm font-medium">
                                                                                            Aktifkan Diskon
                                                                                        </label>
                                                                                    </div>
                                                                                    
                                                                                    <div className="flex items-center">
                                                                                        <input
                                                                                            id="edit_requires_code"
                                                                                            type="checkbox"
                                                                                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                                                            checked={editDiscountForm.data.requires_code}
                                                                                            onChange={(e) => editDiscountForm.setData('requires_code', e.target.checked)}
                                                                                        />
                                                                                        <label htmlFor="edit_requires_code" className="ml-2 block text-sm font-medium">
                                                                                            Wajib Input Kode
                                                                                        </label>
                                                                                    </div>
                                                                                </div>

                                                                                <div>
                                                                                    <InputLabel htmlFor="edit_valid_from" value="Berlaku Dari" />
                                                                                    <TextInput
                                                                                        id="edit_valid_from"
                                                                                        type="date"
                                                                                        className="mt-1 block w-full"
                                                                                        value={editDiscountForm.data.valid_from}
                                                                                        onChange={(e) => editDiscountForm.setData('valid_from', e.target.value)}
                                                                                    />
                                                                                </div>

                                                                                <div>
                                                                                    <InputLabel htmlFor="edit_valid_until" value="Berlaku Sampai" />
                                                                                    <TextInput
                                                                                        id="edit_valid_until"
                                                                                        type="date"
                                                                                        className="mt-1 block w-full"
                                                                                        value={editDiscountForm.data.valid_until}
                                                                                        onChange={(e) => editDiscountForm.setData('valid_until', e.target.value)}
                                                                                    />
                                                                                </div>

                                                                                <div className="md:col-span-2">
                                                                                    <InputLabel htmlFor="edit_description" value="Deskripsi (Opsional)" />
                                                                                    <textarea
                                                                                        id="edit_description"
                                                                                        className="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm"
                                                                                        value={editDiscountForm.data.description}
                                                                                        onChange={(e) => editDiscountForm.setData('description', e.target.value)}
                                                                                        rows="2"
                                                                                    ></textarea>
                                                                                </div>
                                                                            </div>

                                                                            <div className="flex items-center justify-end">
                                                                                <button 
                                                                                    type="button"
                                                                                    className="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md text-gray-700 dark:text-gray-300 text-sm font-medium"
                                                                                    onClick={cancelEdit}
                                                                                >
                                                                                    Batal
                                                                                </button>
                                                                                <PrimaryButton className="ml-4" disabled={editDiscountForm.processing}>
                                                                                    {editDiscountForm.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                                                                </PrimaryButton>
                                                                            </div>
                                                                        </form>
                                                                    </td>
                                                                ) : (
                                                                    <>
                                                                        <td className="px-3 py-2 whitespace-nowrap">
                                                                            <div className="text-sm font-medium text-gray-900 dark:text-gray-200">{discount.name}</div>
                                                                            {discount.description && (
                                                                                <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">{discount.description}</div>
                                                                            )}
                                                                        </td>
                                                                        <td className="px-3 py-2 whitespace-nowrap">
                                                                            {discount.code ? (
                                                                                <div className="flex items-center">
                                                                                    <span className="px-2 py-1 inline-flex text-xs leading-5 font-mono font-semibold rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                                                        {discount.code}
                                                                                    </span>
                                                                                    {discount.requires_code && (
                                                                                        <span className="ml-2 inline-flex text-xxs px-1 py-0.5 bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200 rounded-sm">
                                                                                            wajib
                                                                                        </span>
                                                                                    )}
                                                                                </div>
                                                                            ) : (
                                                                                <span className="text-gray-500 dark:text-gray-200 text-xs italic">Tidak ada kode</span>
                                                                            )}
                                                                        </td>
                                                                        <td className="px-3 py-2 whitespace-nowrap">
                                                                            <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                                                {discount.percentage}%
                                                                            </span>
                                                                        </td>
                                                                        <td className="px-3 py-2 whitespace-nowrap">
                                                                            <div className="text-sm text-gray-900 dark:text-gray-200">
                                                                                {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(discount.min_purchase)}
                                                                            </div>
                                                                        </td>
                                                                        <td className="px-3 py-2 whitespace-nowrap">
                                                                            <div className="text-sm text-gray-500 dark:text-gray-400">
                                                                                {discount.valid_from ? formatDate(discount.valid_from) : '-'} s/d {discount.valid_until ? formatDate(discount.valid_until) : '-'}
                                                                            </div>
                                                                        </td>
                                                                        <td className="px-3 py-2 whitespace-nowrap">
                                                                            <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                                                discount.active 
                                                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' 
                                                                                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                                            }`}>
                                                                                {discount.active ? 'Aktif' : 'Tidak Aktif'}
                                                                            </span>
                                                                        </td>
                                                                        <td className="px-3 py-2 whitespace-nowrap text-right text-sm font-medium">
                                                                            <button
                                                                                onClick={() => handleToggleDiscount(discount.id)}
                                                                                className={`inline-flex items-center px-2 py-1 mr-2 text-xs rounded-md ${
                                                                                    discount.active 
                                                                                        ? 'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800' 
                                                                                        : 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800'
                                                                                }`}
                                                                            >
                                                                                {discount.active ? 'Nonaktifkan' : 'Aktifkan'}
                                                                            </button>
                                                                            <button
                                                                                onClick={() => startEditDiscount(discount)}
                                                                                className="inline-flex items-center px-2 py-1 mr-2 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800 rounded-md text-xs"
                                                                            >
                                                                                Edit
                                                                            </button>
                                                                            <button
                                                                                onClick={() => handleDeleteDiscount(discount.id)}
                                                                                className="inline-flex items-center px-2 py-1 bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800 rounded-md text-xs"
                                                                            >
                                                                                Hapus
                                                                            </button>
                                                                        </td>
                                                                    </>
                                                                )}
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        ) : (
                                            <div className="bg-gray-100 dark:bg-gray-700 rounded-lg p-6 text-center">
                                                <Tag className="h-12 w-12 mx-auto text-gray-400 dark:text-gray-500" />
                                                <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Belum ada diskon</h3>
                                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    Tambahkan diskon pertama untuk meningkatkan penjualan.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}