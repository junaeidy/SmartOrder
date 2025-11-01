import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import { Bell, Plus, Send, Trash2, CheckCircle, XCircle, Clock, Users, AlertCircle } from 'lucide-react';
import toast from 'react-hot-toast';

const StatusBadge = ({ status }) => {
    const badges = {
        draft: { label: 'Draft', class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' },
        sending: { label: 'Mengirim...', class: 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100' },
        sent: { label: 'Terkirim', class: 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' },
        failed: { label: 'Gagal', class: 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' },
    };
    const badge = badges[status] || badges.draft;
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${badge.class}`}>
            {badge.label}
        </span>
    );
};

const StatCard = ({ title, value, icon: Icon, colorClass }) => (
    <div className={`bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border-l-4 ${colorClass}`}>
        <div className="flex items-start justify-between">
            <div>
                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">{title}</p>
                <p className={`mt-1 text-3xl font-extrabold ${colorClass.replace('-l-4', '').replace('border-', 'text-')}`}>
                    {value}
                </p>
            </div>
            {Icon && (
                <div className={`p-3 rounded-full ${colorClass.replace('border-', 'bg-').replace('-l-4', '-100/50 dark:bg-gray-700')}`}>
                    <Icon className={`w-6 h-6 ${colorClass.replace('-l-4', '').replace('border-', 'text-')}`} />
                </div>
            )}
        </div>
    </div>
);

export default function Announcements({ announcements, auth }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showConfirmSend, setShowConfirmSend] = useState(false);
    const [selectedAnnouncement, setSelectedAnnouncement] = useState(null);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        message: '',
    });

    const stats = {
        total: announcements?.data?.length || 0,
        sent: announcements?.data?.filter(a => a.status === 'sent').length || 0,
        draft: announcements?.data?.filter(a => a.status === 'draft').length || 0,
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('kasir.announcements.store'), {
            onSuccess: () => {
                reset();
                setShowCreateModal(false);
                toast.success('Pengumuman berhasil dibuat sebagai draft.');
            },
        });
    };

    const handleSend = (announcement) => {
        setSelectedAnnouncement(announcement);
        setShowConfirmSend(true);
    };

    const confirmSend = () => {
        if (selectedAnnouncement) {
            router.post(route('kasir.announcements.send', selectedAnnouncement.id), {}, {
                onSuccess: () => {
                    setShowConfirmSend(false);
                    setSelectedAnnouncement(null);
                    toast.success('Pengumuman sedang dikirim.');
                },
            });
        }
    };

    const handleDelete = (announcement) => {
        setSelectedAnnouncement(announcement);
        setShowDeleteConfirm(true);
    };

    const confirmDelete = () => {
        if (selectedAnnouncement) {
            router.delete(route('kasir.announcements.destroy', selectedAnnouncement.id), {
                onSuccess: () => {
                    setShowDeleteConfirm(false);
                    setSelectedAnnouncement(null);
                    toast.success('Pengumuman berhasil dihapus.');
                },
            });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth?.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-2xl font-bold leading-tight text-gray-900 dark:text-gray-50">
                        Pengumuman
                    </h2>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg shadow-md transition duration-150"
                    >
                        <Plus className="w-5 h-5 mr-2" />
                        Buat Pengumuman
                    </button>
                </div>
            }
        >
            <Head title="Pengumuman" />

            <div className="py-6 sm:py-10">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    {/* Statistics Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <StatCard
                            title="Total Pengumuman"
                            value={stats.total}
                            icon={Bell}
                            colorClass="border-blue-500"
                        />
                        <StatCard
                            title="Terkirim"
                            value={stats.sent}
                            icon={CheckCircle}
                            colorClass="border-green-500"
                        />
                        <StatCard
                            title="Draft"
                            value={stats.draft}
                            icon={Clock}
                            colorClass="border-gray-500"
                        />
                    </div>

                    {/* Announcements List */}
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl overflow-hidden">
                        <div className="p-6 border-b border-gray-200 dark:border-gray-700">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Daftar Pengumuman
                            </h3>
                        </div>
                        
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Judul & Pesan
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Penerima
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Dikirim Oleh
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Tanggal
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    {announcements?.data?.length === 0 ? (
                                        <tr>
                                            <td colSpan="6" className="px-6 py-12 text-center">
                                                <Bell className="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                                <p className="text-gray-500 dark:text-gray-400">
                                                    Belum ada pengumuman. Buat pengumuman pertama Anda!
                                                </p>
                                            </td>
                                        </tr>
                                    ) : (
                                        announcements?.data?.map((announcement) => (
                                            <tr key={announcement.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-col">
                                                        <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {announcement.title}
                                                        </span>
                                                        <span className="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                                            {announcement.message}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <StatusBadge status={announcement.status} />
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    {announcement.status === 'sent' ? (
                                                        <div className="flex flex-col text-sm">
                                                            <span className="text-green-600 dark:text-green-400 font-medium flex items-center">
                                                                <CheckCircle className="w-4 h-4 mr-1" />
                                                                {announcement.success_count}
                                                            </span>
                                                            {announcement.failed_count > 0 && (
                                                                <span className="text-red-600 dark:text-red-400 text-xs flex items-center">
                                                                    <XCircle className="w-3 h-3 mr-1" />
                                                                    {announcement.failed_count} gagal
                                                                </span>
                                                            )}
                                                            <span className="text-gray-500 dark:text-gray-400 text-xs">
                                                                dari {announcement.recipients_count}
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-sm text-gray-500 dark:text-gray-400">-</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {announcement.sent_by}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    {announcement.sent_at || announcement.created_at}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex justify-end gap-2">
                                                        {announcement.status === 'draft' && (
                                                            <button
                                                                onClick={() => handleSend(announcement)}
                                                                className="inline-flex items-center px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-md transition"
                                                            >
                                                                <Send className="w-3.5 h-3.5 mr-1" />
                                                                Kirim
                                                            </button>
                                                        )}
                                                        {(announcement.status === 'draft' || announcement.status === 'failed') && (
                                                            <button
                                                                onClick={() => handleDelete(announcement)}
                                                                className="inline-flex items-center px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-md transition"
                                                            >
                                                                <Trash2 className="w-3.5 h-3.5" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {announcements?.links && (
                            <div className="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                                <div className="flex justify-between items-center">
                                    <div className="text-sm text-gray-500 dark:text-gray-400">
                                        Menampilkan {announcements.from || 0} - {announcements.to || 0} dari {announcements.total || 0} pengumuman
                                    </div>
                                    <div className="flex gap-2">
                                        {announcements.links?.map((link, index) => (
                                            <button
                                                key={index}
                                                onClick={() => link.url && router.visit(link.url)}
                                                disabled={!link.url}
                                                className={`px-3 py-1 rounded ${
                                                    link.active
                                                        ? 'bg-orange-600 text-white'
                                                        : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600'
                                                } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Create Announcement Modal */}
            <Modal show={showCreateModal} onClose={() => setShowCreateModal(false)} maxWidth="2xl">
                <div className="p-6">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                        <Bell className="w-5 h-5 mr-2 text-orange-600" />
                        Buat Pengumuman Baru
                    </h3>
                    
                    <form onSubmit={handleSubmit}>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Judul Pengumuman <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    className="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-orange-500 focus:ring-orange-500"
                                    placeholder="Contoh: Promo Spesial Hari Ini!"
                                    maxLength="255"
                                />
                                {errors.title && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.title}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Pesan <span className="text-red-500">*</span>
                                </label>
                                <textarea
                                    value={data.message}
                                    onChange={(e) => setData('message', e.target.value)}
                                    rows="5"
                                    className="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-orange-500 focus:ring-orange-500"
                                    placeholder="Tulis pesan pengumuman di sini..."
                                    maxLength="1000"
                                />
                                <div className="mt-1 flex justify-between">
                                    {errors.message && <p className="text-sm text-red-600 dark:text-red-400">{errors.message}</p>}
                                    <p className="text-xs text-gray-500 dark:text-gray-400 ml-auto">
                                        {data.message.length}/1000 karakter
                                    </p>
                                </div>
                            </div>

                            <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                <div className="flex items-start">
                                    <AlertCircle className="w-5 h-5 text-blue-600 dark:text-blue-400 mr-2 mt-0.5" />
                                    <div className="text-sm text-blue-800 dark:text-blue-200">
                                        <p className="font-medium mb-1">Informasi:</p>
                                        <ul className="list-disc list-inside space-y-1 text-xs">
                                            <li>Pengumuman akan disimpan sebagai draft terlebih dahulu</li>
                                            <li>Anda bisa mengirim pengumuman ke semua pelanggan dari daftar</li>
                                            <li>Notifikasi akan dikirim ke semua customer yang memiliki aplikasi mobile</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => {
                                    setShowCreateModal(false);
                                    reset();
                                }}
                                className="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition disabled:opacity-50"
                            >
                                {processing ? 'Menyimpan...' : 'Simpan Draft'}
                            </button>
                        </div>
                    </form>
                </div>
            </Modal>

            {/* Confirm Send Modal */}
            <Modal show={showConfirmSend} onClose={() => setShowConfirmSend(false)}>
                <div className="p-6">
                    <div className="flex items-center mb-4">
                        <div className="p-3 bg-green-100 dark:bg-green-900/30 rounded-full mr-3">
                            <Send className="w-6 h-6 text-green-600 dark:text-green-400" />
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Kirim Pengumuman?
                        </h3>
                    </div>
                    
                    <div className="mb-4">
                        <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            Pengumuman akan dikirim ke semua customer yang memiliki aplikasi mobile.
                        </p>
                        {selectedAnnouncement && (
                            <div className="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 mt-3">
                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {selectedAnnouncement.title}
                                </p>
                                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {selectedAnnouncement.message}
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-3">
                        <button
                            onClick={() => setShowConfirmSend(false)}
                            className="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                        >
                            Batal
                        </button>
                        <button
                            onClick={confirmSend}
                            className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition"
                        >
                            Ya, Kirim Sekarang
                        </button>
                    </div>
                </div>
            </Modal>

            {/* Delete Confirm Modal */}
            <Modal show={showDeleteConfirm} onClose={() => setShowDeleteConfirm(false)}>
                <div className="p-6">
                    <div className="flex items-center mb-4">
                        <div className="p-3 bg-red-100 dark:bg-red-900/30 rounded-full mr-3">
                            <Trash2 className="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Hapus Pengumuman?
                        </h3>
                    </div>
                    
                    <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Pengumuman ini akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
                    </p>

                    <div className="flex justify-end gap-3">
                        <button
                            onClick={() => setShowDeleteConfirm(false)}
                            className="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                        >
                            Batal
                        </button>
                        <button
                            onClick={confirmDelete}
                            className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition"
                        >
                            Ya, Hapus
                        </button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
