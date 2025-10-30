import React, { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import { Eye, CheckCircle, XCircle, Clock as ClockIcon } from 'lucide-react';

  // Robust date parser for Laravel-style timestamps ("YYYY-MM-DD HH:mm:ss")
  const parseLaravelDate = (value) => {
    if (!value) return null;
    if (value instanceof Date && !isNaN(value)) return value;
    try {
      if (typeof value === 'string') {
        const s = value.trim();
        // If it's a space-separated format: YYYY-MM-DD HH:mm:ss
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(s)) {
          const [datePart, timePart] = s.split(' ');
          const [y, mo, d] = datePart.split('-').map(Number);
          const [h, mi, se] = timePart.split(':').map(Number);
          const dt = new Date(y, (mo || 1) - 1, d || 1, h || 0, mi || 0, se || 0);
          return isNaN(dt) ? null : dt;
        }
        // Otherwise, let Date try (handles ISO like 2025-10-18T13:21:50Z)
        const dt = new Date(s);
        return isNaN(dt) ? null : dt;
      }
    } catch (_) { /* ignore */ }
    return null;
  };

  // Choose the best timestamp to display: paid_at > updated_at > created_at > date
  const resolveTransactionDate = (t) => {
    return (
      parseLaravelDate(t?.paid_at) ||
      parseLaravelDate(t?.updated_at) ||
      parseLaravelDate(t?.created_at) ||
      parseLaravelDate(t?.date)
    );
  };

  const currencyIDR = (n) => {
    // Pastikan n adalah number, jika string ubah ke number
    const amount = typeof n === 'string' ? parseFloat(n) : n;
    return new Intl.NumberFormat('id-ID', { 
      style: 'currency', 
      currency: 'IDR', 
      minimumFractionDigits: 0 
    }).format(amount || 0);
  };

const PaymentBadge = ({ status }) => {
  const s = (status || '').toLowerCase();
  let cls = 'bg-gray-200 text-gray-800';
  let label = status || 'N/A';
  if (s === 'paid' || s === 'settlement' || s === 'capture') { cls = 'bg-green-100 text-green-700'; label = 'Lunas'; }
  else if (s === 'pending') { cls = 'bg-yellow-100 text-yellow-700'; label = 'Menunggu'; }
  else if (s === 'canceled' || s === 'failed' || s === 'expire' || s === 'expired' || s === 'deny') { cls = 'bg-red-100 text-red-700'; label = 'Dibatalkan'; }
  return <span className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium ${cls}`}>{label}</span>;
};

// Visual badge for payment method (cash/online variants)
const MethodBadge = ({ method }) => {
  const m = (method || '').toLowerCase();
  const map = {
    cash: { cls: 'bg-green-100 text-green-700', label: 'Cash' },
  };
  const conf = map[m] || { cls: 'bg-blue-100 text-blue-700 capitalize', label: (method || 'Online').toString() };
  return <span className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium ${conf.cls}`}>{conf.label}</span>;
};

// Visual badge for order status (completed/awaiting_confirmation/canceled/etc)
const OrderStatusBadge = ({ status }) => {
  const s = (status || '').toLowerCase();
  let cls = 'bg-gray-200 text-gray-800';
  let label = status || 'N/A';
  if (['completed', 'done', 'success', 'finished'].includes(s)) { cls = 'bg-green-100 text-green-700'; label = 'Selesai'; }
  else if (['awaiting_confirmation', 'waiting', 'waiting_confirmation', 'awaiting', 'menunggu_konfirmasi'].includes(s)) { cls = 'bg-yellow-100 text-yellow-700'; label = 'Menunggu'; }
  else if (['canceled', 'cancelled', 'failed', 'expired', 'expire', 'deny', 'rejected'].includes(s)) { cls = 'bg-red-100 text-red-700'; label = 'Dibatalkan'; }
  else if (['processing', 'in_progress', 'preparing', 'on_progress'].includes(s)) { cls = 'bg-indigo-100 text-indigo-700'; label = 'Diproses'; }
  return <span className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium ${cls}`}>{label}</span>;
};

export default function Transaksi({ auth, transactions, history, storeSettings }) {
  const rows = transactions?.data || [];
  const historyRowsRaw = Array.isArray(history) ? history : (history?.data || []);
  const [historySearch, setHistorySearch] = useState('');
  const [historyPage, setHistoryPage] = useState(1);
  const historyRows = historyRowsRaw.filter((t) => {
    if (!historySearch.trim()) return true;
    const q = historySearch.toLowerCase();
    const hay = [
      t.kode_transaksi,
      t.queue_number?.toString(),
      t.customer_name,
      t.customer_phone,
      t.payment_method,
      t.payment_status,
      t.status,
    ].map(v => (v ?? '').toString().toLowerCase());
    return hay.some(s => s.includes(q));
  });
  const historyPerPage = 20;
  const historyTotal = historyRows.length;
  const historyTotalPages = Math.max(1, Math.ceil(historyTotal / historyPerPage));
  const historyStartIndex = (historyPage - 1) * historyPerPage;
  const historyEndIndex = Math.min(historyStartIndex + historyPerPage, historyTotal);
  const historyVisibleRows = historyRows.slice(historyStartIndex, historyEndIndex);
  const [activeTab, setActiveTab] = useState('pending'); // 'pending' | 'history'
  

  // Debug first transaction if exists
  if (rows.length > 0) {
    
  }

  const [currentTime, setCurrentTime] = useState(new Date());
  const [selected, setSelected] = useState(null);
  const [showDetail, setShowDetail] = useState(false);
  const [busyId, setBusyId] = useState(null);
  const audioRef = useRef(null);
  const [showActionModal, setShowActionModal] = useState(false);
  const [actionType, setActionType] = useState(null);
  const [actionTarget, setActionTarget] = useState(null);
  const [showSuccessModal, setShowSuccessModal] = useState(false);
  const [processedOrder, setProcessedOrder] = useState(null);
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [cashReceived, setCashReceived] = useState('');
  const [cashError, setCashError] = useState('');
  const [printerConnected, setPrinterConnected] = useState(false);
  const [printerDevice, setPrinterDevice] = useState(null);
  const debounceRef = useRef(null);

  // Fungsi untuk print struk
  const connectPrinter = async () => {
    try {
      const device = await navigator.bluetooth.requestDevice({
        acceptAllDevices: true,
        optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb', 'e7810a71-73ae-499d-8c15-faa9aef0c3f2']
      });
      
      
      await device.gatt.connect();
      setPrinterDevice(device);
      setPrinterConnected(true);
      return true;
    } catch (error) {
      
      setPrinterConnected(false);
      setPrinterDevice(null);
      alert('Gagal terhubung ke printer. Pastikan printer Bluetooth sudah dinyalakan dan dalam jangkauan.');
      return false;
    }
  };

      const writeToCharacteristic = async (characteristic, data) => {
        const maxChunkSize = 512;
        for (let i = 0; i < data.length; i += maxChunkSize) {
          const chunk = data.slice(i, Math.min(i + maxChunkSize, data.length));
          await characteristic.writeValue(chunk);
          // Tambah delay kecil antara chunks untuk memastikan printer bisa mengikuti
          await new Promise(resolve => setTimeout(resolve, 50));
        }
      };

      const printReceipt = async (order) => {
    try {
      
      // Gunakan printer yang sudah terhubung atau hubungkan yang baru
      const device = printerDevice || await navigator.bluetooth.requestDevice({
        filters: [
          { services: ['000018f0-0000-1000-8000-00805f9b34fb'] },
          { namePrefix: 'Printer' }
        ]
      });

      
      const server = await device.gatt.connect();
      const service = await server.getPrimaryService('000018f0-0000-1000-8000-00805f9b34fb');
      const characteristic = await service.getCharacteristic('00002af1-0000-1000-8000-00805f9b34fb');

      
      // Format struk
      const formatDateTime = (dateObj) => {
        if (!dateObj || isNaN(dateObj)) return { date: '-', time: '-' };
        try {
          const dateFormatter = new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
          });
          const timeFormatter = new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
          });
          return {
            date: dateFormatter.format(dateObj),
            time: timeFormatter.format(dateObj)
          };
        } catch (error) {
          
          return { date: '-', time: '-' };
        }
      };

      // Use paid_at > updated_at > created_at
      const resolvedDate = resolveTransactionDate(order);
      const datetime = formatDateTime(resolvedDate);
      
      const ESC = '\x1B';
      const GS = '\x1D';
      const INIT = ESC + '@'; // Initialize printer
      const CENTER = ESC + 'a' + '\x01'; // Center alignment
      const LEFT = ESC + 'a' + '\x00';  // Left alignment
      const BOLD_ON = ESC + 'E' + '\x01'; // Bold on
      const BOLD_OFF = ESC + 'E' + '\x00'; // Bold off
      const DOUBLE_ON = GS + '!' + '\x11'; // Double height & width
      const DOUBLE_OFF = GS + '!' + '\x00'; // Normal size
      const LARGE_ON = GS + '!' + '\x01'; // Large text
      const LARGE_OFF = GS + '!' + '\x00'; // Normal text

      const name = (storeSettings && typeof storeSettings.store_name !== 'undefined'
        ? String(storeSettings.store_name)
        : 'SMART ORDER').trim() || 'SMART ORDER';
      const address = storeSettings && typeof storeSettings.store_address !== 'undefined'
        ? String(storeSettings.store_address).trim()
        : '';
      const phone = storeSettings && typeof storeSettings.store_phone !== 'undefined'
        ? String(storeSettings.store_phone).trim()
        : '';
      const email = storeSettings && typeof storeSettings.store_email !== 'undefined'
        ? String(storeSettings.store_email).trim()
        : '';

      

      const receipt = [
        INIT,
        CENTER,
        DOUBLE_ON + `${name}\n` + DOUBLE_OFF,
        address ? `${address}\n` : '',
        phone ? `Telp: ${phone}\n` : '',
        email ? `Email: ${email}\n` : '',
        '\n',
        BOLD_ON + '========================\n' + BOLD_OFF,
        LEFT,
        `No. Antrian : #${order.queue_number}\n`,
        `Tanggal     : ${datetime.date}\n`,
        `Jam         : ${datetime.time}\n`,
        `Kasir       : ${auth.user.name}\n`,
        `Pelanggan   : ${order.customer_name}\n`,
        BOLD_ON + '------------------------\n' + BOLD_OFF,
      ];

      // Fungsi untuk format harga
      const formatPrice = (price) => {
        const num = typeof price === 'string' ? parseFloat(price) : Number(price);
        if (!isFinite(num)) return '0';
        return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
      };

      // Tambahkan items dan akumulasi subtotal keseluruhan
      let itemsTotal = 0;
      order.items.forEach(item => {
        const rawPrice = (item.harga ?? item.price);
        const priceNum = typeof rawPrice === 'string' ? parseFloat(rawPrice) : Number(rawPrice);
        const qtyNum = Number(item.quantity ?? item.qty ?? 0);
        const subtotal = (item.subtotal != null) ? Number(item.subtotal) : (qtyNum * (isFinite(priceNum) ? priceNum : 0));
        itemsTotal += (isFinite(subtotal) ? subtotal : 0);
        // Format item dengan padding untuk alignment
        const itemName = item.nama.length > 20 ? item.nama.substring(0, 17) + '...' : item.nama;
        const qtyStr = `${qtyNum}x`;
        const priceStr = formatPrice(priceNum);
        const subtotalStr = formatPrice(subtotal);
        
        receipt.push(
          `${itemName}\n`,
          `${qtyStr.padEnd(4)}${priceStr.padStart(10)} = ${subtotalStr.padStart(10)}\n`
        );
      });

      // Ringkasan biaya: Subtotal, Diskon (jika ada), Pajak (jika ada), Total
      const discountAmount = Number(order.discount_amount || 0);
      const taxAmount = Number(order.tax_amount || 0);

      receipt.push(BOLD_ON + '-------------------------\n' + BOLD_OFF);
      receipt.push(`Subtotal   : Rp ${formatPrice(itemsTotal)}\n`);
      if (discountAmount > 0) {
        receipt.push(`Diskon     : -Rp ${formatPrice(discountAmount)}\n`);
      }
      if (taxAmount > 0) {
        receipt.push(`Pajak (PPN): Rp ${formatPrice(taxAmount)}\n`);
      }
      receipt.push(`Total      : Rp ${formatPrice(order.total_amount)}\n`);
      receipt.push(`Pembayaran : ${order.payment_method.toUpperCase()}\n`);

      if (order.payment_method === 'cash') {
        const receivedRaw = (order.amount_received != null ? order.amount_received : cashReceived);
        const receivedNum = Number(receivedRaw || 0);
        const changeNum = Math.max(0, receivedNum - Number(order.total_amount || 0));
        receipt.push(
          `Tunai      : Rp ${formatPrice(receivedNum)}\n`,
          `Kembalian  : Rp ${formatPrice(changeNum)}\n`
        );
      }

      receipt.push(
        '\n',
        CENTER,
        'Terima Kasih\n',
        'Atas Kunjungan Anda\n\n',
        '\n\n\n' // Extra lines for paper cutting
      );

      // Kirim ke printer dalam chunks
      
      const encoder = new TextEncoder();
      const fullData = encoder.encode(receipt.join(''));
      
      
      // Kirim data dalam chunks
      
      await writeToCharacteristic(characteristic, fullData);
      
      
    } catch (error) {
      
      alert('Gagal mencetak struk. Pastikan printer Bluetooth sudah dinyalakan dan terhubung.');
    }
  };

  // Test print with dummy order (header uses storeSettings from database)
  const handleTestPrint = async () => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const createdAt = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    const testOrder = {
      queue_number: 'TEST',
      customer_name: 'Tes Cetak',
      items: [
        { id: 0, nama: 'Item Contoh', harga: 10000, quantity: 1, subtotal: 10000 },
      ],
      total_amount: 10000,
      discount_amount: 0,
      tax_amount: 0,
      payment_method: 'cash',
      amount_received: 10000,
      created_at: createdAt,
    };
    await printReceipt(testOrder);
  };
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
      
    } catch (error) {
      
      setAudioPermissionGranted(false);
    }
  };

  // Helper function to play notification sound
  const playNotificationSound = () => {
    try {
      const audio = new Audio('/sounds/notification.wav');
      audio.volume = 0.8;
      audio.play().catch((error) => {
        
        if (audioRef.current) {
          audioRef.current.volume = 0.8;
          audioRef.current.play().catch(e => {});
        }
      });
    } catch (error) {
      
      if (audioRef.current) {
        audioRef.current.volume = 0.8;
  audioRef.current.play().catch(e => {});
      }
    }
  };

  const openDetail = (t) => { 
    
    setSelected(t); 
    setShowDetail(true); 
  };
  const closeDetail = () => { setSelected(null); setShowDetail(false); };

  const confirmOrder = (t) => {
    if (!t) return;
    setActionTarget(t);
    setActionType('confirm');
    setShowConfirmModal(true);
    setCashReceived('');
    setCashError('');
  };
  const cancelOrder = (t) => {
    if (!t) return;
    setActionTarget(t);
    setActionType('cancel');
    setShowConfirmModal(true);
  };
  const performAction = () => {
    if (!actionTarget || !actionType) return;
    setBusyId(actionTarget.id);
    const url = actionType === 'confirm' ? route('kasir.transaksi.confirm', actionTarget.id) : route('kasir.transaksi.cancel', actionTarget.id);
    const payload = {};
    if (actionType === 'confirm' && actionTarget?.payment_method === 'cash') {
      const receivedNum = Number(String(cashReceived).replace(/[^\d.-]/g, ''));
      const totalNum = Number(actionTarget.total_amount);
      if (!isFinite(receivedNum) || receivedNum <= 0) {
        setBusyId(null);
        setCashError('Masukkan nominal yang valid.');
        return;
      }
      if (receivedNum < totalNum) {
        setBusyId(null);
        setCashError('Uang yang diterima kurang dari total.');
        return;
      }
      payload.amount_received = receivedNum;
    }

    putWithRetry(url, payload, 1, 3, () => {
      setShowConfirmModal(false);
      if (actionType === 'confirm') {
        const receivedNum = payload.amount_received ?? null;
        const totalNum = Number(actionTarget.total_amount);
        const enriched = {
          ...actionTarget,
          ...(receivedNum != null ? {
            amount_received: receivedNum,
            change_amount: Math.max(0, Number(receivedNum) - totalNum)
          } : {})
        };
        setProcessedOrder(enriched);
        setShowSuccessModal(true);
      }
    });
  };

  // Generic retry helpers
  const reloadWithRetry = (attempt = 1, max = 3) => {
    router.reload({ only: ['transactions','history','storeSettings'],
      onError: () => {
        if (attempt < max) {
          setTimeout(() => reloadWithRetry(attempt + 1, max), attempt * 800);
        }
      }
    });
  };

  // Reset history page on search or when switching to history tab
  useEffect(() => {
    setHistoryPage(1);
  }, [historySearch]);
  useEffect(() => {
    if (activeTab === 'history') setHistoryPage(1);
  }, [activeTab]);
  const putWithRetry = (url, data = {}, attempt = 1, max = 3, onSuccess = null) => {
    router.put(url, data, {
      preserveScroll: true,
      onSuccess: () => {
        if (onSuccess) onSuccess();
      },
      onError: () => {
        if (attempt < max) {
          setTimeout(() => putWithRetry(url, data, attempt + 1, max, onSuccess), attempt * 800);
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
        
      } catch (error) {
        
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
            
            if (audioRef.current) {
              audioRef.current.volume = 0.8;
              audioRef.current.play().catch(e => {});
            }
          });
        } catch (error) {
          
          if (audioRef.current) {
            audioRef.current.volume = 0.8;
            audioRef.current.play().catch(e => {});
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

    channel.listen('.OrderStatusChanged', (e) => {
      console.log('Order status changed:', e);
      const status = e?.status; // Status ada di root object, bukan di e.transaction
      // If a transaction entered awaiting_confirmation, play a sound
      const shouldSound = status === 'awaiting_confirmation';
      
      
      // Play sound immediately if status is awaiting_confirmation
      if (shouldSound) {
        try {
          const audio = new Audio('/sounds/notification.wav');
          audio.volume = 0.8;
          audio.play().catch((error) => {
            
            if (audioRef.current) {
              audioRef.current.volume = 0.8;
              audioRef.current.play().catch(e => {});
            }
          });
        } catch (error) {
          
          if (audioRef.current) {
            audioRef.current.volume = 0.8;
            audioRef.current.play().catch(e => {});
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
          <div className="flex flex-col space-y-2 md:space-y-0 md:flex-row md:items-center md:space-x-4">
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Konfirmasi Transaksi</h2>
            <button
              onClick={connectPrinter}
              className={`inline-flex items-center px-3 py-1.5 rounded text-sm font-medium transition-colors ${
                printerConnected 
                  ? 'bg-green-100 text-green-800 dark:bg-green-800/30 dark:text-green-400' 
                  : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
              }`}
            >
              <svg xmlns="http://www.w3.org/2000/svg" className={`h-4 w-4 mr-1.5 ${printerConnected ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              {printerConnected ? 'Printer Terhubung' : 'Hubungkan Printer'}
            </button>
            <button
              onClick={handleTestPrint}
              className="inline-flex items-center px-3 py-1.5 rounded text-sm font-medium transition-colors bg-blue-600 text-white hover:bg-blue-700"
              title={`Header: ${(storeSettings?.store_name ?? 'SMART ORDER')} | ${(storeSettings?.store_address ?? '-')} | ${(storeSettings?.store_phone ?? '-')} | ${(storeSettings?.store_email ?? '-')}`}
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1.5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 8H5a3 3 0 00-3 3v1a3 3 0 003 3h1v3a1 1 0 001 1h10a1 1 0 001-1v-3h1a3 3 0 003-3v-1a3 3 0 00-3-3zM16 18H8v-4h8v4zM17 4H7a1 1 0 00-1 1v2h12V5a1 1 0 00-1-1z" />
              </svg>
              Test Print
            </button>
          </div>
          <div className="flex items-center space-x-2 text-sm md:text-base">
            <ClockIcon className="h-5 w-5 text-orange-500 animate-pulse" />
            <div className="flex flex-col md:flex-row md:space-x-2">
              <span className="font-mono text-gray-900 font-bold dark:text-gray-200">{formatTime(currentTime)}</span>
              <span className="text-gray-700 dark:text-gray-400">{formatClockDate(currentTime)}</span>
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
        {/* Tabs (mimic Orders.jsx) */}
        <div className="mb-2 border-b border-gray-700">
          <nav className="flex space-x-8">
            <button
              onClick={() => setActiveTab('pending')}
              className={`py-3 px-1 border-b-2 font-medium text-sm transition-colors duration-200 ${
                activeTab === 'pending'
                  ? 'border-orange-500 text-orange-500'
                  : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-400'
              }`}
            >
              Menunggu Konfirmasi
              <span className="ml-2 bg-orange-500 px-2 py-0.5 rounded-full text-xs text-white">
                {typeof transactions?.total === 'number' ? transactions.total : (rows?.length || 0)}
              </span>
            </button>
            <button
              onClick={() => setActiveTab('history')}
              className={`py-3 px-1 border-b-2 font-medium text-sm transition-colors duration-200 ${
                activeTab === 'history'
                  ? 'border-indigo-500 text-indigo-500'
                  : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-400'
              }`}
            >
              Riwayat
              <span className="ml-2 bg-indigo-600 px-2 py-0.5 rounded-full text-xs text-white">
                {historyRowsRaw?.length || 0}
              </span>
            </button>
          </nav>
        </div>
        {activeTab === 'pending' && (
          rows.length === 0 ? (
            <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 text-center">Tidak ada transaksi menunggu konfirmasi.</div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {rows.map((t) => (
              <div key={t.id} className="bg-gray-800 rounded-lg shadow-md overflow-hidden border-l-4 border-orange-500 transition-all hover:shadow-lg cursor-pointer" onClick={() => openDetail(t)}>
                <div className="p-5">
                  <div className="flex justify-between items-center mb-3">
                    <div className="flex items-center">
                      <span className="bg-orange-500/20 text-orange-400 px-3 py-1.5 rounded text-base font-bold">#{t.queue_number}</span>
                      <div className="flex items-center ml-2 bg-blue-900/30 px-2 py-1 rounded">
                        <ClockIcon className="w-3 h-3 text-blue-300 mr-1" />
                        <span className="text-xs text-blue-300">
                          {(() => {
                            const d = resolveTransactionDate(t);
                            return d ? d.toLocaleTimeString([], { hour: '2-digit', minute:'2-digit' }) : '-';
                          })()}
                        </span>
                      </div>
                    </div>
                    <span className="text-xs text-gray-400">
                      {(() => {
                        const d = resolveTransactionDate(t);
                        return d ? d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
                      })()}
                    </span>
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
                    <div className="flex items-center justify-between pt-1">
                      <div className="flex items-center space-x-2 text-xs">
                        <MethodBadge method={t.payment_method} />
                        <PaymentBadge status={t.payment_status || (t.is_paid ? 'paid' : 'pending')} />
                      </div>
                      <div className="flex items-center space-x-2">
                        <button
                          className="px-3 py-1 rounded bg-green-600 hover:bg-green-700 text-white text-xs"
                          onClick={(e) => { e.stopPropagation(); confirmOrder(t); }}
                        >
                          Konfirmasi
                        </button>
                        {t?.payment_method === 'cash' && (
                          <button
                            className="px-3 py-1 rounded bg-red-600 hover:bg-red-700 text-white text-xs"
                            onClick={(e) => { e.stopPropagation(); cancelOrder(t); }}
                          >
                            Batalkan
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center justify-end space-x-2"></div>
                </div>
              </div>
              ))}
            </div>
          )
        )}

        {activeTab === 'history' && (
          <>
            <div className="bg-white dark:bg-gray-800 rounded-xl p-4 shadow border border-gray-100 dark:border-gray-700">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="md:col-span-3">
                  <label className="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cari (Kode/Antrian/Nama/HP/Metode/Status)</label>
                  <input type="text" value={historySearch} onChange={(e)=>setHistorySearch(e.target.value)} placeholder="Ketik kata kunci..." className="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100" />
                </div>
              </div>
            </div>

            {/* Table for history */}
            <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl overflow-hidden">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                  <thead className="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Tanggal</th>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Kode/Antrian</th>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Metode</th>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status Bayar</th>
                      <th className="px-4 py-3 text-left text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status Order</th>
                      <th className="px-4 py-3 text-right text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Items</th>
                      <th className="px-4 py-3 text-right text-xs font-bold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Total</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                    {historyVisibleRows.length === 0 ? (
                      <tr>
                        <td colSpan={8} className="px-4 py-8 text-center text-base text-gray-500 dark:text-gray-400">Belum ada riwayat transaksi untuk hari ini.</td>
                      </tr>
                    ) : (
                      historyVisibleRows.map((t) => (
                        <tr key={t.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-100 cursor-pointer" onClick={() => openDetail(t)}>
                          <td className="px-4 py-3 text-sm">{(() => { const d = resolveTransactionDate(t); return d ? d.toLocaleString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'; })()}</td>
                          <td className="px-4 py-3 text-sm font-mono text-indigo-600 dark:text-indigo-400">{t.kode_transaksi || `#${t.queue_number}`}</td>
                          <td className="px-4 py-3 text-sm">{t.customer_name}</td>
                          <td className="px-4 py-3 text-sm"><MethodBadge method={t.payment_method} /></td>
                          <td className="px-4 py-3 text-sm"><PaymentBadge status={t.payment_status} /></td>
                          <td className="px-4 py-3 text-sm"><OrderStatusBadge status={t.status} /></td>
                          <td className="px-4 py-3 text-sm text-right font-medium">{t.total_items}</td>
                          <td className="px-4 py-3 text-sm text-right font-semibold">{currencyIDR(t.total_amount)}</td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
              {/* Pagination footer */}
              <div className="p-4 bg-gray-50 dark:bg-gray-800/50 flex justify-between items-center border-t border-gray-200 dark:border-gray-700">
                <span className="text-sm text-gray-600 dark:text-gray-400">
                  Menampilkan <strong>{historyTotal === 0 ? 0 : historyStartIndex + 1}</strong> sampai <strong>{historyEndIndex}</strong> dari <strong>{historyTotal}</strong> hasil.
                </span>
                <div className="space-x-2 flex">
                  <button
                    disabled={historyPage <= 1}
                    onClick={() => setHistoryPage(p => Math.max(1, p - 1))}
                    className="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center transition"
                  >
                    Sebelumnya
                  </button>
                  <button
                    disabled={historyPage >= historyTotalPages}
                    onClick={() => setHistoryPage(p => Math.min(historyTotalPages, p + 1))}
                    className="px-4 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-300 dark:hover:bg-gray-600 flex items-center transition"
                  >
                    Berikutnya
                  </button>
                </div>
              </div>
            </div>
          </>
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
                    <div className="font-medium">
                      {(() => {
                        const d = resolveTransactionDate(selected);
                        return d ? d.toLocaleString('id-ID', {
                          day: '2-digit',
                          month: '2-digit',
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit'
                        }) : '-';
                      })()}
                    </div>
                  </div>
                  <div className="text-sm">
                    <div className="text-gray-500 dark:text-gray-400">Customer</div>
                    <div className="font-medium">{selected.customer_name || '-'}</div>
                    <div className="text-gray-500 dark:text-gray-400">{selected.customer_phone || '-'}</div>
                    <div className="text-gray-500 dark:text-gray-400">{selected.customer_email || '-'}</div>
                  </div>
                  <div className="text-sm">
                    <div className="text-gray-500 dark:text-gray-400">Pembayaran</div>
                    <div className="flex items-center gap-2 mt-0.5">
                      <MethodBadge method={selected.payment_method} />
                      <PaymentBadge status={selected.payment_status} />
                    </div>
                  </div>
                  <div className="text-sm">
                    <div className="text-gray-500 dark:text-gray-400">Antrian</div>
                    <div className="font-medium">{selected.queue_number ?? '-'}</div>
                  </div>
                  <div className="text-sm">
                    <div className="text-gray-500 dark:text-gray-400">Status Order</div>
                    <div className="mt-0.5"><OrderStatusBadge status={selected.status} /></div>
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
                              const qty = parseInt(it.quantity || it.qty || 0, 10);
                              // Convert string price to number
                              const price = parseFloat(String(it.harga || it.price).replace(/[^\d.-]/g, ''));
                              // Use provided subtotal or calculate
                              const subtotal = it.subtotal ? parseFloat(String(it.subtotal)) : (qty * price);
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
                    {/* Breakdown: Subtotal, Diskon, Pajak, Total Items */}
                    {(() => {
                      const items = Array.isArray(selected.items) ? selected.items : [];
                      const itemsTotal = items.reduce((sum, it) => {
                        const qty = Number(it.quantity ?? it.qty ?? 0);
                        const price = Number(typeof (it.harga ?? it.price) === 'string' ? parseFloat((it.harga ?? it.price)) : (it.harga ?? it.price) || 0);
                        const sub = it.subtotal != null ? Number(it.subtotal) : (isFinite(qty) && isFinite(price) ? qty * price : 0);
                        return sum + (isFinite(sub) ? sub : 0);
                      }, 0);
                      const discount = Number(selected.discount_amount || 0);
                      const tax = Number(selected.tax_amount || 0);
                      return (
                        <>
                          <div className="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 py-2">
                            <span className="text-sm text-gray-700 dark:text-gray-200">Subtotal</span>
                            <span className="text-sm text-gray-900 dark:text-gray-100">{currencyIDR(itemsTotal)}</span>
                          </div>
                          {discount > 0 && (
                            <div className="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 py-2">
                              <span className="text-sm text-gray-700 dark:text-gray-200">Diskon</span>
                              <span className="text-sm text-green-600 dark:text-green-400">- {currencyIDR(discount)}</span>
                            </div>
                          )}
                          {tax > 0 && (
                            <div className="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 py-2">
                              <span className="text-sm text-gray-700 dark:text-gray-200">Pajak (PPN)</span>
                              <span className="text-sm text-gray-900 dark:text-gray-100">{currencyIDR(tax)}</span>
                            </div>
                          )}
                          <div className="bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 py-2">
                            <span className="text-sm text-gray-700 dark:text-gray-200">Total Items</span>
                            <span className="text-sm text-gray-900 dark:text-gray-100">{selected.total_items}</span>
                          </div>
                        </>
                      );
                    })()}
                  </div>
                </div>

                <div className="mt-4 flex justify-end">
                  <button onClick={closeDetail} className="px-4 py-2 rounded-md bg-gray-200 text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600 text-sm font-medium">Tutup</button>
                </div>
              </div>
            )}
          </div>
        </Modal>
      </div>
      {/* Confirmation Modal */}
      <Modal show={showConfirmModal} onClose={() => setShowConfirmModal(false)} maxWidth="md">
        <div className="p-6">
          <div className="flex items-center mb-4">
            <div className={`mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full ${
              actionType === 'confirm' ? 'bg-blue-100' : 'bg-red-100'
            }`}>
              {actionType === 'confirm' ? (
                <CheckCircle className="h-8 w-8 text-blue-600" />
              ) : (
                <XCircle className="h-8 w-8 text-red-600" />
              )}
            </div>
          </div>
          
          <div className="mt-3 text-center">
            <h3 className="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
              {actionType === 'confirm' ? 'Konfirmasi Pesanan' : 'Batalkan Pesanan'}
            </h3>
            <div className="mt-2">
              <p className="text-sm text-gray-500 dark:text-gray-400">
                {actionType === 'confirm'
                  ? `Apakah Anda yakin ingin mengkonfirmasi pesanan #${actionTarget?.queue_number}?`
                  : `Apakah Anda yakin ingin membatalkan pesanan #${actionTarget?.queue_number}?`
                }
              </p>
            </div>
            
            {actionTarget && (
              <div className="mt-4 bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-left">
                <div className="space-y-2">
                  <div className="flex justify-between">
                    <span className="text-gray-600 dark:text-gray-400">Pelanggan:</span>
                    <span className="font-medium">{actionTarget.customer_name}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600 dark:text-gray-400">Total Items:</span>
                    <span className="font-medium">{actionTarget.total_items}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600 dark:text-gray-400">Total:</span>
                    <span className="font-medium">{currencyIDR(actionTarget.total_amount)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600 dark:text-gray-400">Pembayaran:</span>
                    <span className="font-medium capitalize">{actionTarget.payment_method}</span>
                  </div>
                  {actionTarget?.payment_method === 'cash' && actionType === 'confirm' && (
                    <>
                      <div className="pt-2">
                        <label className="block text-sm text-gray-600 dark:text-gray-300 mb-1">Nominal Diterima</label>
                        <input
                          type="number"
                          min="0"
                          inputMode="decimal"
                          value={cashReceived}
                          onChange={(e) => {
                            setCashReceived(e.target.value);
                            setCashError('');
                          }}
                          className="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2"
                          placeholder="Masukkan nominal uang..."
                        />
                        {cashError && <p className="mt-1 text-xs text-red-500">{cashError}</p>}
                      </div>
                      {(() => {
                        const receivedNum = Number(String(cashReceived).replace(/[^\d.-]/g, ''));
                        const totalNum = Number(actionTarget.total_amount);
                        const valid = isFinite(receivedNum) && receivedNum > 0;
                        const change = valid ? Math.max(0, receivedNum - totalNum) : 0;
                        return (
                          <div className="flex justify-between mt-2">
                            <span className="text-gray-600 dark:text-gray-400">Kembalian:</span>
                            <span className={`font-semibold ${valid && receivedNum >= totalNum ? 'text-green-600' : 'text-gray-400'}`}>
                              {currencyIDR(change)}
                            </span>
                          </div>
                        );
                      })()}
                    </>
                  )}
                </div>
              </div>
            )}
          </div>
          
          <div className="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            <button
              onClick={performAction}
              disabled={busyId === actionTarget?.id}
              className={`inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white ${
                actionType === 'confirm'
                  ? 'bg-blue-600 hover:bg-blue-700'
                  : 'bg-red-600 hover:bg-red-700'
              } focus:outline-none focus:ring-2 focus:ring-offset-2 ${
                actionType === 'confirm' ? 'focus:ring-blue-500' : 'focus:ring-red-500'
              } ${busyId === actionTarget?.id ? 'opacity-50 cursor-not-allowed' : ''}`}
            >
              {actionType === 'confirm' ? 'Ya, Konfirmasi' : 'Ya, Batalkan'}
            </button>
            <button
              onClick={() => setShowConfirmModal(false)}
              className="inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Batal
            </button>
          </div>
        </div>
      </Modal>

      {/* Success Modal */}
      <Modal show={showSuccessModal} onClose={() => setShowSuccessModal(false)} maxWidth="md">
        <div className="p-6">
          <div className="flex items-center justify-center mb-4">
            <div className="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-green-100">
              <CheckCircle className="h-8 w-8 text-green-600" />
            </div>
          </div>
          <div className="mt-3 text-center sm:mt-5">
            <h3 className="text-lg font-semibold leading-6 text-gray-900 dark:text-gray-100">
              Pesanan Berhasil Diselesaikan
            </h3>
            <div className="mt-2">
              <p className="text-sm text-gray-500 dark:text-gray-400">
                Pesanan #{processedOrder?.queue_number} telah berhasil dikonfirmasi.
              </p>
            </div>
            
            {/* Order Summary */}
            <div className="mt-4 bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-left">
              <div className="space-y-2">
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Pelanggan:</span>
                  <span className="font-medium">{processedOrder?.customer_name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Total Items:</span>
                  <span className="font-medium">{processedOrder?.total_items}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Total:</span>
                  <span className="font-medium text-green-600">{currencyIDR(processedOrder?.total_amount)}</span>
                </div>
                {processedOrder?.payment_method === 'cash' && (
                  <>
                    <div className="flex justify-between">
                      <span className="text-gray-600 dark:text-gray-400">Tunai:</span>
                      <span className="font-medium">{currencyIDR(processedOrder?.amount_received ?? cashReceived)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-600 dark:text-gray-400">Kembalian:</span>
                      <span className="font-medium">{currencyIDR((processedOrder?.change_amount) ?? (Number(cashReceived || 0) - Number(processedOrder?.total_amount || 0)))}</span>
                    </div>
                  </>
                )}
                <div className="flex justify-between">
                  <span className="text-gray-600 dark:text-gray-400">Pembayaran:</span>
                  <span className="font-medium capitalize">{processedOrder?.payment_method}</span>
                </div>
              </div>
            </div>
          </div>
          
          <div className="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            <button
              onClick={() => printReceipt(processedOrder)}
              className="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
              </svg>
              Cetak Struk
            </button>
            <button
              onClick={() => setShowSuccessModal(false)}
              className="inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              OK
            </button>
          </div>
        </div>
      </Modal>

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
