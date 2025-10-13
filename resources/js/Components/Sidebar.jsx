import { Link, usePage } from "@inertiajs/react";
import { Home, Users, ClipboardList, ShoppingCart, Settings, Donut } from "lucide-react";

export default function Sidebar() {
  const { auth } = usePage().props;
  const role = auth?.user?.role;

  const menus = {
    owner: [
      { label: "Dashboard", icon: <Home size={18} />, href: "/owner/dashboard" },
      { label: "Data Karyawan", icon: <Users size={18} />, href: "/owner/karyawan" },
      { label: "Laporan", icon: <ClipboardList size={18} />, href: "/owner/laporan" },
      { label: "Pengaturan", icon: <Settings size={18} />, href: "/owner/settings" },
    ],
    kasir: [
      { label: "Dashboard", icon: <Home size={18} />, href: "/kasir/dashboard" },
      { label: "Transaksi", icon: <ShoppingCart size={18} />, href: "/kasir/transaksi" },
      { label: "Riwayat", icon: <ClipboardList size={18} />, href: "/kasir/riwayat" },
      { label: "Produk", icon: <Donut size={18} />, href: route("products.index") },
    ],
    karyawan: [
      { label: "Dashboard", icon: <Home size={18} />, href: "/karyawan/dashboard" },
      { label: "Pesanan", icon: <ShoppingCart size={18} />, href: "/karyawan/pesanan" },
    ],
  };

  const activeRoleMenus = menus[role] || [];

  return (
    <div className="w-64 bg-white h-screen shadow-lg flex flex-col justify-between fixed left-0 top-0 border-r">
      <div>
        <div className="p-4 text-2xl font-bold text-amber-700 border-b">
          🍽️ SmartOrder
        </div>
        <nav className="mt-4 flex flex-col space-y-1">
          {activeRoleMenus.map((item, index) => (
            <Link
              key={index}
              href={item.href}
              className="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-amber-100 hover:text-amber-700 transition rounded-md"
            >
              {item.icon}
              <span>{item.label}</span>
            </Link>
          ))}
        </nav>
      </div>

      <div className="p-4 border-t text-sm text-gray-500">
        <p className="font-semibold text-gray-700">{auth.user.name}</p>
        <p className="capitalize">{auth.user.role}</p>
      </div>
    </div>
  );
}
