import React, { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import { Eye, CheckCircle, XCircle, Clock as ClockIcon } from 'lucide-react';

const currencyIDR = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n || 0);
const PaymentBadge = ({ status }) => {
  const s = (status || '').toLowerCase();
  let cls = 'bg-gray-200 text-gray-800';
  let label = status || 'N/A';
  if (s === 'paid' || s === 'settlement' || s === 'capture') { cls = 'bg-green-100 text-green-700'; label = 'Paid'; }
  else if (s === 'pending') { cls = 'bg-yellow-100 text-yellow-700'; label = 'Pending'; }
  else if (s === 'canceled' || s === 'failed' || s === 'expire' || s === 'expired' || s === 'deny') { cls = 'bg-red-100 text-red-700'; label = 'Canceled'; }
  return <span className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium ${cls}`}>{label}</span>;
};

export default function Transaksi({ auth, transactions }) {
  const rows = transactions?.data || [];
  const [currentTime, setCurrentTime] = useState(new Date());
  const [selected, setSelected] = useState(null);
  const [showDetail, setShowDetail] = useState(false);
  const [busyId, setBusyId] = useState(null);
  const audioRef = useRef(null);
  const [showActionModal, setShowActionModal] = useState(false);
  const [actionType, setActionType] = useState(null);
  const [actionTarget, setActionTarget] = useState(null);
  const debounceRef = useRef(null);
  const [audioPermissionGranted, setAudioPermissionGranted] = useState(false);
  
  // Function to request audio permission
  const requestAudioPermission = async () => {
    try {
      // Create a temporary audio and try to play it
      const tempAudio = new Audio('/sounds/notification.wav');
      tempAudio.volume = 0;  // Mute it so user doesn't hear the test
      await tempAudio.play();
      tempAudio.pause();
      tempAudio.currentTime = 0;
      setAudioPermissionGranted(true);
      console.log('Audio permission granted');
    } catch (error) {
      console.log('Audio permission not granted:', error);
      setAudioPermissionGranted(false);
    }
  };

  // Helper function to play notification sound
  const playNotificationSound = () => {
    try {
      const audio = new Audio('/sounds/notification.wav');
      audio.volume = 0.8;
      audio.play().catch((error) => {
        console.log('Failed to play with new Audio(), trying fallback...', error);
        if (audioRef.current) {
          audioRef.current.volume = 0.8;
          audioRef.current.play().catch(e => console.log('Fallback audio failed:', e));
        }
      });
    } catch (error) {
      console.log('Error creating audio:', error);
      if (audioRef.current) {
        audioRef.current.volume = 0.8;
        audioRef.current.play().catch(e => console.log('Fallback audio failed:', e));
      }
    }
  };

  const openDetail = (t) => { setSelected(t); setShowDetail(true); };
  const closeDetail = () => { setSelected(null); setShowDetail(false); };

  const confirmOrder = (t) => {
    if (!t) return;
    setActionTarget(t);
    setActionType('confirm');
    setShowActionModal(true);
  };
  const cancelOrder = (t) => {
    if (!t) return;
    setActionTarget(t);
    setActionType('cancel');
    setShowActionModal(true);
  };
  const performAction = () => {
    if (!actionTarget || !actionType) return;
    setBusyId(actionTarget.id);
    const url = actionType === 'confirm' ? route('kasir.transaksi.confirm', actionTarget.id) : route('kasir.transaksi.cancel', actionTarget.id);
    putWithRetry(url);
    setShowActionModal(false);
  };

  // Generic retry helpers
  const reloadWithRetry = (attempt = 1, max = 3) => {
    router.reload({ only: ['transactions'],
      onError: () => {
        if (attempt < max) {
          setTimeout(() => reloadWithRetry(attempt + 1, max), attempt * 800);
        }
      }
    });
  };
  const putWithRetry = (url, data = {}, attempt = 1, max = 3) => {
    router.put(url, data, {
      preserveScroll: true,
      onError: () => {
        if (attempt < max) {
          setTimeout(() => putWithRetry(url, data, attempt + 1, max), attempt * 800);
        }
      },
      onFinish: () => setBusyId(null)
    });
  };

  // Clock updater
  useEffect(() => {
    const clock = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(clock);
  }, []);

  // Realtime updates via Echo
  // Check for existing audio permission on mount
  useEffect(() => {
    const checkPermission = async () => {
      try {
        // Try to play a silent audio to check permission
        const audio = new Audio('/sounds/notification.wav');
        audio.volume = 0;
        await audio.play();
        audio.pause();
        audio.currentTime = 0;
        setAudioPermissionGranted(true);
        console.log('Audio permission already granted');
      } catch (error) {
        console.log('Audio permission needed:', error);
        setAudioPermissionGranted(false);
      }
    };
    
    checkPermission();
  }, []);

  useEffect(() => {
    if (!window.Echo) return;
    const channel = window.Echo.channel('orders');

    const triggerReload = (playSound = false) => {
      if (playSound) {
        try {
          const audio = new Audio('/sounds/notification.wav');
          audio.volume = 0.8;
          audio.play().catch((error) => {
            console.log('Failed to play with new Audio(), trying fallback...', error);
            if (audioRef.current) {
              audioRef.current.volume = 0.8;
              audioRef.current.play().catch(e => console.log('Fallback audio also failed:', e));
            }
          });
        } catch (error) {
          console.log('Error creating audio:', error);
          if (audioRef.current) {
            audioRef.current.volume = 0.8;
            audioRef.current.play().catch(e => console.log('Fallback audio failed:', e));
          }
        }
      }
      if (debounceRef.current) return;
      debounceRef.current = setTimeout(() => {
        reloadWithRetry();
        clearTimeout(debounceRef.current);
        debounceRef.current = null;
      }, 500);
    };

    channel.listen('OrderStatusChanged', (e) => {
      console.log('OrderStatusChanged event received:', e);
      const status = e?.status; // Status ada di root object, bukan di e.transaction
      // If a transaction entered awaiting_confirmation, play a sound
      const shouldSound = status === 'awaiting_confirmation';
      console.log('Should play sound:', shouldSound, 'Status:', status);
      
      // Play sound immediately if status is awaiting_confirmation
      if (shouldSound) {
        try {
          const audio = new Audio('/sounds/notification.wav');
          audio.volume = 0.8;
          audio.play().catch((error) => {
            console.log('Failed to play with new Audio(), trying fallback...', error);
            if (audioRef.current) {
              audioRef.current.volume = 0.8;
              audioRef.current.play().catch(e => console.log('Fallback audio failed:', e));
            }
          });
        } catch (error) {
          console.log('Error creating audio:', error);
          if (audioRef.current) {
            audioRef.current.volume = 0.8;
            audioRef.current.play().catch(e => console.log('Fallback audio failed:', e));
          }
        }
      }
      
      triggerReload(false); // We're handling the sound separately now
    });

    // We don't need to play sound for new orders in kasir/transaksi page
    channel.listen('NewOrderReceived', () => {
      // Just reload the data without playing sound
      triggerReload(false);
    });

    return () => {
      try { window.Echo.leave('orders'); } catch (_) {}
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
        debounceRef.current = null;
      }
    };
  }, []);

  const formatTime = (date) => {
    const h = String(date.getHours()).padStart(2, '0');
    const m = String(date.getMinutes()).padStart(2, '0');
    const s = String(date.getSeconds()).padStart(2, '0');
    return `${h}:${m}:${s}`;
  };
  const formatClockDate = (date) => {
    return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  };

  return (
    <AuthenticatedLayout
      user={auth?.user}
      header={
        <div className="flex flex-col md:flex-row justify-between items-center">
          <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Konfirmasi Transaksi</h2>
          <div className="flex items-center space-x-2 text-sm md:text-base">
            <ClockIcon className="h-5 w-5 text-orange-500 animate-pulse" />
            <div className="flex flex-col md:flex-row md:space-x-2">
              <span className="font-mono text-gray-800 dark:text-gray-200">{formatTime(currentTime)}</span>
              <span className="text-gray-600 dark:text-gray-400">{formatClockDate(currentTime)}</span>
            </div>
          </div>
        </div>
      }
    >
      <Head title="Kasir - Transaksi" />

      {!audioPermissionGranted && (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <div className="flex">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
              </div>
              <div className="ml-3">
                <p className="text-sm text-yellow-700">
                  Notifikasi suara tidak aktif. 
                  <button
                    onClick={requestAudioPermission}
                    className="ml-2 font-medium text-yellow-700 underline hover:text-yellow-600"
                  >
                    Aktifkan notifikasi
                  </button>
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      <div className="py-6 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        {rows.length === 0 ? (
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 text-center">Tidak ada transaksi menunggu konfirmasi.</div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {rows.map((t) => (
              <div key={t.id} className="bg-gray-800 rounded-lg shadow-md overflow-hidden border-l-4 border-orange-500 transition-all hover:shadow-lg">
                <div className="p-5">
                  <div className="flex justify-between items-center mb-3">
                    <div className="flex items-center">
                      <span className="bg-orange-500/20 text-orange-400 px-3 py-1.5 rounded text-base font-bold">#{t.queue_number}</span>
                      <div className="flex items-center ml-2 bg-blue-900/30 px-2 py-1 rounded">
                        <span className="text-xs text-blue-300">{t.date || '-'}</span>
                      </div>
                    </div>
                    <div className="flex items-center space-x-2">
                      <span className="text-xs text-gray-300 capitalize">{t.payment_method}</span>
                      <PaymentBadge status={t.payment_status || (t.is_paid ? 'paid' : 'pending')} />
                    </div>
                  </div>
                  <h3 className="font-bold text-lg mb-1">{t.customer_name}</h3>
                  <p className="text-gray-400 text-sm mb-3">{t.customer_phone}</p>
                  <div className="space-y-2 mb-4">
                    <div className="flex justify-between">
                      <span className="text-gray-400">Items:</span>
                      <span className="font-medium">{t.total_items}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-400">Total:</span>
                      <span className="font-bold text-orange-400">{currencyIDR(t.total_amount)}</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-end space-x-2">
                    <button onClick={() => openDetail(t)} className="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-100 text-xs font-medium">
                      <Eye className="w-4 h-4 mr-1" /> Lihat
                    </button>
                    <button disabled={busyId===t.id} onClick={() => { setBusyId(t.id); putWithRetry(route('kasir.transaksi.confirm', t.id)); }} className="inline-flex items-center px-3 py-1.5 rounded-md bg-green-600 hover:bg-green-700 text-white text-xs font-medium disabled:opacity-60">
                      <CheckCircle className="w-4 h-4 mr-1" /> Konfirmasi
                    </button>
                    {t.payment_method==='cash' && (
                      <button disabled={busyId===t.id} onClick={() => { setBusyId(t.id); putWithRetry(route('kasir.transaksi.cancel', t.id)); }} className="inline-flex items-center px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-700 text-white text-xs font-medium disabled:opacity-60">
                        <XCircle className="w-4 h-4 mr-1" /> Batalkan
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Modal detail */}
        <Modal show={showDetail} onClose={closeDetail} maxWidth="2xl">
          <div className="p-4 sm:p-6 max-h-[80vh] overflow-y-auto bg-white dark:bg-gray-900">
            <div className="flex items-start justify-between mb-4 sticky top-0 z-10 bg-white dark:bg-gray-900 pb-2">
              <div>
                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Detail Transaksi</h3>
                {selected?.kode_transaksi && (
                  <p className="text-sm text-gray-500 dark:text-gray-400">Kode: <span className="font-mono text-indigo-600 dark:text-indigo-400">{selected.kode_transaksi}</span></p>
                )}
              </div>
              <button onClick={closeDetail} className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">✕</button>
            </div>

            {selected && (
              <div className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div className="text-sm">
                    <div className="text-gray-500 dark:text-gray-400">Tanggal</div>
                    <div className="font-medium">{selected.date || '-'}</div>
                  </div>
                  <div className="text-sm">
                    <div className="text-gray-500 dark:text-gray-400">Customer</div>
                    <div className="font-medium">{selected.customer_name || '-'}</div>
                    <div className="text-gray-500 dark:text-gray-400">{selected.customer_phone || '-'}</div>
                    <div className="text-gray-500 dark:text-gray-400">{selected.customer_email || '-'}</div>
                  </div>
                  <div className="text-sm">
                    <div className="text-gray-500 dark:text-gray-400">Pembayaran</div>
                    <div className="font-medium capitalize">{selected.payment_method}</div>
                    <div className="text-gray-500 dark:text-gray-400 mt-1">Status: {selected.payment_status}</div>
                  </div>
                  <div className="text-sm">
                    <div className="text-gray-500 dark:text-gray-400">Antrian</div>
                    <div className="font-medium">{selected.queue_number ?? '-'}</div>
                  </div>
                  <div className="text-sm sm:col-span-2">
                    <div className="text-gray-500 dark:text-gray-400">Catatan</div>
                    <div className="font-medium whitespace-pre-wrap">{selected.customer_notes || '-'}</div>
                  </div>
                </div>

                <div className="mt-2">
                  <h4 className="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Items</h4>
                  <div className="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div className="max-h-64 overflow-auto">
                      <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead className="bg-gray-50 dark:bg-gray-700/50">
                          <tr>
                            <th className="px-3 py-2 text-left text-xs font-bold text-gray-600 dark:text-gray-300">Nama</th>
                            <th className="px-3 py-2 text-right text-xs font-bold text-gray-600 dark:text-gray-300">Qty</th>
                            <th className="px-3 py-2 text-right text-xs font-bold text-gray-600 dark:text-gray-300">Harga</th>
                            <th className="px-3 py-2 text-right text-xs font-bold text-gray-600 dark:text-gray-300">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                          {(selected.items || []).length > 0 ? (
                            selected.items.map((it, idx) => {
                              const name = it.nama || it.name || `Item ${idx+1}`;
                              const qty = Number(it.quantity || it.qty || 0);
                              const price = Number(it.price || it.harga || 0);
                              const subtotal = Number(it.subtotal != null ? it.subtotal : (qty * price));
                              return (
                                <tr key={idx}>
                                  <td className="px-3 py-2 text-sm">{name}</td>
                                  <td className="px-3 py-2 text-sm text-right">{qty}</td>
                                  <td className="px-3 py-2 text-sm text-right">{currencyIDR(price)}</td>
                                  <td className="px-3 py-2 text-sm text-right">{currencyIDR(subtotal)}</td>
                                </tr>
                              );
                            })
                          ) : (
                            <tr>
                              <td colSpan={4} className="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ada item.</td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                    <div className="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 py-2">
                      <span className="text-sm font-semibold text-gray-700 dark:text-gray-200">Total</span>
                      <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{currencyIDR(selected.total_amount)}</span>
                    </div>
                    <div className="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 py-2">
                      <span className="text-sm text-gray-700 dark:text-gray-200">Total Items</span>
                      <span className="text-sm text-gray-900 dark:text-gray-100">{selected.total_items}</span>
                    </div>
                  </div>
                </div>

                <div className="mt-4 flex justify-end space-x-2">
                  <button onClick={closeDetail} className="px-4 py-2 rounded-md bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600 text-sm font-medium">Tutup</button>
                  <button disabled={!selected} onClick={() => confirmOrder(selected)} className="inline-flex items-center px-4 py-2 rounded-md bg-green-600 hover:bg-green-700 text-white text-sm font-medium disabled:opacity-60">
                    <CheckCircle className="w-4 h-4 mr-1" /> Konfirmasi
                  </button>
                  {selected?.payment_method==='cash' && (
                    <button disabled={!selected} onClick={() => cancelOrder(selected)} className="inline-flex items-center px-4 py-2 rounded-md bg-red-600 hover:bg-red-700 text-white text-sm font-medium disabled:opacity-60">
                      <XCircle className="w-4 h-4 mr-1" /> Batalkan
                    </button>
                  )}
                </div>
              </div>
            )}
          </div>
        </Modal>
      </div>
      {/* Audio element kept as fallback/preload (we primarily use new Audio()) */}
      <audio ref={audioRef} preload="auto">
        <source src="/sounds/notification.wav" type="audio/wav" />
        Your browser does not support the audio element.
      </audio>

      {/* Confirm/Cancel Action Modal */}
      <Modal show={showActionModal} onClose={() => setShowActionModal(false)} maxWidth="md">
        <div className="p-5">
          <div className="flex items-start justify-between mb-3">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
              {actionType === 'cancel' ? 'Batalkan Pesanan' : 'Konfirmasi Pesanan'}
            </h3>
            <button onClick={() => setShowActionModal(false)} className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">✕</button>
          </div>
          <p className="text-gray-600 dark:text-gray-300 mb-4">
            {actionType === 'cancel' ? (
              <>Anda yakin ingin membatalkan pesanan #{actionTarget?.queue_number} milik <span className="font-medium">{actionTarget?.customer_name}</span>? Stok akan dikembalikan.</>
            ) : (
              <>Konfirmasi pesanan #{actionTarget?.queue_number} milik <span className="font-medium">{actionTarget?.customer_name}</span>? {actionTarget?.payment_method === 'cash' ? 'Pembayaran akan ditandai sebagai Lunas.' : ''}</>
            )}
          </p>
          {actionTarget && (
            <div className="bg-gray-50 dark:bg-gray-800 rounded-md p-3 border border-gray-200 dark:border-gray-700 mb-4 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-500">Items</span>
                <span className="font-medium">{actionTarget.total_items}</span>
              </div>
              <div className="flex justify-between mt-1">
                <span className="text-gray-500">Total</span>
                <span className="font-semibold text-orange-500">{currencyIDR(actionTarget.total_amount)}</span>
              </div>
            </div>
          )}
          <div className="flex justify-end space-x-2">
            <button onClick={() => setShowActionModal(false)} className="px-4 py-2 rounded-md bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600 text-sm font-medium">Batal</button>
            <button onClick={performAction} disabled={busyId===actionTarget?.id} className={`inline-flex items-center px-4 py-2 rounded-md text-white text-sm font-medium ${actionType==='cancel' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'} disabled:opacity-60`}>
              {actionType==='cancel' ? 'Ya, Batalkan' : 'Ya, Konfirmasi'}
            </button>
          </div>
        </div>
      </Modal>
    </AuthenticatedLayout>
  );
}
