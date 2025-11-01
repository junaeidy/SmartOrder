import { Link, usePage } from "@inertiajs/react";
import { Home, Users, ClipboardList, ShoppingCart, Settings, Donut, Grid2X2PlusIcon, Megaphone } from "lucide-react";

export default function Sidebar() {
  const { url } = usePage();
  const { auth } = usePage().props;
  const role = auth?.user?.role;

  const getLinkPath = (href) => {
    try {
      return new URL(href).pathname;
    } catch (e) {
      return href;
    }
  };

  const menus = {
    kasir: [
      { label: "Dashboard", icon: <Home size={18} />, href: route("kasir.dashboard") },
      { label: "Transaksi", icon: <ShoppingCart size={18} />, href: route("kasir.transaksi") },
      { label: "Laporan", icon: <Grid2X2PlusIcon size={18} />, href: route("kasir.reports") },
      { label: "Produk", icon: <Donut size={18} />, href: route("products.index") },
      { label: "Pengumuman", icon: <Megaphone size={18} />, href: route("kasir.announcements") },
      { label: "Pengaturan", icon: <Settings size={18} />, href: route('admin.settings') },
    ],
    karyawan: [
      { label: "Dashboard", icon: <Home size={18} />, href: route("karyawan.dashboard") },
      { label: "Pesanan", icon: <ShoppingCart size={18} />, href: route("karyawan.orders") },
    ],
  };

  const activeRoleMenus = menus[role] || [];
  
  const currentUrl = url || ''; 
  const currentPath = currentUrl.endsWith('/') && currentUrl.length > 1 ? currentUrl.slice(0, -1) : currentUrl;

  return (
    <div className="w-64 bg-white dark:bg-gray-900 h-screen shadow-lg flex flex-col justify-between fixed left-0 top-0 border-r border-gray-200 dark:border-gray-800">
      <div>
        <div className="p-4 text-2xl font-bold text-amber-700 dark:text-amber-400 border-b border-gray-200 dark:border-gray-800">
          🍽️ SmartOrder
        </div>
        <nav className="mt-4 flex flex-col space-y-1">
          {activeRoleMenus.map((item, index) => {
            const itemPath = getLinkPath(item.href);
            const isActive = currentPath === itemPath;

            return (
              <Link
                key={index}
                href={item.href}
                className={`
                  flex items-center gap-2 px-4 py-2 transition rounded-md
                  ${isActive 
                    ? "bg-amber-500 text-white shadow-md hover:bg-amber-600"
                    : "text-gray-700 dark:text-gray-300 hover:bg-amber-100 dark:hover:bg-gray-800 hover:text-amber-700 dark:hover:text-amber-300"
                  }
                `}
              >
                {item.icon}
                <span>{item.label}</span>
              </Link>
            );
          })}
        </nav>
      </div>

      <div className="p-4 border-t border-gray-200 dark:border-gray-800 text-sm text-gray-500 dark:text-gray-400">
        <p className="font-semibold text-gray-700 dark:text-gray-200">{auth.user.name}</p>
        <p className="capitalize">{auth.user.role}</p>
      </div>
    </div>
  );
}
