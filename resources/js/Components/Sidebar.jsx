import { Link, usePage } from "@inertiajs/react";
import { Home, Users, ClipboardList, ShoppingCart, Settings, Donut, Grid2X2PlusIcon } from "lucide-react";

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
    admin: [
      { label: "Dashboard", icon: <Home size={18} />, href: route('admin.dashboard') },
      { label: "Laporan", icon: <ClipboardList size={18} />, href: route('admin.reports') },
      { label: "Pengaturan", icon: <Settings size={18} />, href: "/admin/settings" },
    ],
    kasir: [
      { label: "Dashboard", icon: <Home size={18} />, href: route("kasir.dashboard") },
      { label: "Transaksi", icon: <ShoppingCart size={18} />, href: route("kasir.transaksi") },
      { label: "Laporan", icon: <Grid2X2PlusIcon size={18} />, href: route("kasir.reports") },
      { label: "Produk", icon: <Donut size={18} />, href: route("products.index") },
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
    <div className="w-64 bg-white h-screen shadow-lg flex flex-col justify-between fixed left-0 top-0 border-r">
      <div>
        <div className="p-4 text-2xl font-bold text-amber-700 border-b">
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
                    : "text-gray-700 hover:bg-amber-100 hover:text-amber-700"
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

      <div className="p-4 border-t text-sm text-gray-500">
        <p className="font-semibold text-gray-700">{auth.user.name}</p>
        <p className="capitalize">{auth.user.role}</p>
      </div>
    </div>
  );
}
