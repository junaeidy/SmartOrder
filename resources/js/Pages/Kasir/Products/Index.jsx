import { useForm, usePage, Head, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { useState, useEffect } from "react";
import toast from "react-hot-toast";
import {
  Plus,
  Edit,
  Trash2,
  Search,
  Loader2,
  Package,
  XCircle,
  Save,
} from "lucide-react";

export default function Index({ products, filters }) {
  const { flash } = usePage().props;
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [imagePreviewUrl, setImagePreviewUrl] = useState(null);
  const [searchTerm, setSearchTerm] = useState(filters?.search || "");

  const { data, setData, post, reset, processing } = useForm({
    nama: "",
    harga: "",
    stok: "",
    gambar: null,
  });

  useEffect(() => {
    return () => {
      if (imagePreviewUrl) URL.revokeObjectURL(imagePreviewUrl);
    };
  }, [imagePreviewUrl]);

  const handleImageChange = (e) => {
    const file = e.target.files[0];
    setData("gambar", file);

    if (file) {
      const url = URL.createObjectURL(file);
      setImagePreviewUrl(url);
    } else {
      setImagePreviewUrl(null);
    }
  };

  const clearImagePreview = () => {
    setData("gambar", null);
    if (imagePreviewUrl) URL.revokeObjectURL(imagePreviewUrl);
    setImagePreviewUrl(null);
    const fileInput = document.getElementById("gambar");
    if (fileInput) fileInput.value = "";
  };

  const handleSubmit = (e) => {
    e.preventDefault();

    const routeName = isEditing
      ? route("products.update", editingId)
      : route("products.store");

    router.post(
      routeName,
      {
        _method: isEditing ? "put" : "post",
        ...data,
      },
      {
        forceFormData: true,
        onSuccess: () => {
          toast.success(
            isEditing
              ? "Produk berhasil diperbarui 🎉"
              : "Produk berhasil ditambahkan 🎉"
          );
          resetForm();
        },
        onError: (errors) => {
          const message = Object.values(errors).flat().join("; ");
          toast.error(message || "Gagal menyimpan produk ❌");
        },
      }
    );
  };

  const resetForm = () => {
    reset();
    clearImagePreview();
    setIsFormOpen(false);
    setIsEditing(false);
    setEditingId(null);
  };

  const handleDelete = (id) => {
    if (confirm("Yakin ingin menghapus produk ini?")) {
      router.delete(route("products.destroy", id), {
        onSuccess: () => toast.success("Produk dihapus ✅"),
        onError: () => toast.error("Gagal menghapus produk ❌"),
      });
    }
  };

  const handleEdit = (product) => {
    setIsEditing(true);
    setEditingId(product.id);
    setData({
      nama: product.nama,
      harga: product.harga,
      stok: product.stok,
      gambar: null,
    });
    if (product.gambar) {
      setImagePreviewUrl(`/storage/${product.gambar}`);
    } else {
      setImagePreviewUrl(null);
    }
    setIsFormOpen(true);
  };

  // 🔍 Handle pencarian ke server (Inertia GET)
  const handleSearch = (e) => {
    e.preventDefault();
    router.get(
      route("products.index"),
      { search: searchTerm },
      { preserveState: true, replace: true }
    );
  };

  return (
    <AuthenticatedLayout>
      <Head title="Manajemen Produk" />
      <div className="p-4 sm:p-6 lg:p-8">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white flex items-center">
            <Package className="w-7 h-7 mr-3 text-blue-600" />
            Daftar Produk
          </h1>
          <button
            onClick={() => {
              if (isFormOpen && isEditing) {
                resetForm();
              } else {
                setIsFormOpen(!isFormOpen);
                if (isFormOpen) resetForm();
              }
            }}
            className="flex items-center bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition"
          >
            <Plus className="w-5 h-5 mr-2" />
            {isEditing ? "Batal Edit" : "Tambah Produk"}
          </button>
        </div>

        {isFormOpen && (
          <div className="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg mb-8 border border-gray-200 dark:border-gray-700">
            <h3 className="text-xl font-semibold mb-4 text-gray-800 dark:text-white border-b pb-2">
              {isEditing ? "Edit Produk" : "Input Produk Baru"}
            </h3>
            <form onSubmit={handleSubmit}>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Nama Produk
                  </label>
                  <input
                    type="text"
                    value={data.nama}
                    onChange={(e) => setData("nama", e.target.value)}
                    required
                    className="mt-1 block w-full border rounded-md p-2 dark:bg-gray-700 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Harga (Rp)
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={data.harga}
                    onChange={(e) => setData("harga", e.target.value)}
                    required
                    className="mt-1 block w-full border rounded-md p-2 dark:bg-gray-700 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Stok
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={data.stok}
                    onChange={(e) => setData("stok", e.target.value)}
                    required
                    className="mt-1 block w-full border rounded-md p-2 dark:bg-gray-700 dark:text-white"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Gambar Produk
                  </label>
                  <div className="flex items-center space-x-2">
                    <input
                      id="gambar"
                      type="file"
                      accept="image/*"
                      onChange={handleImageChange}
                      className="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />
                    {imagePreviewUrl && (
                      <button
                        type="button"
                        onClick={clearImagePreview}
                        className="text-red-500 hover:text-red-700"
                      >
                        <XCircle className="w-5 h-5" />
                      </button>
                    )}
                  </div>
                  {imagePreviewUrl && (
                    <div className="mt-2 w-24 h-24 rounded-md overflow-hidden border">
                      <img
                        src={imagePreviewUrl}
                        alt="Preview Produk"
                        className="w-full h-full object-cover"
                      />
                    </div>
                  )}
                </div>
              </div>

              <div className="flex justify-end space-x-3 mt-6">
                <button
                  type="button"
                  onClick={resetForm}
                  className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="flex items-center bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 px-4 rounded-lg shadow-md disabled:opacity-50 transition"
                >
                  {processing ? (
                    <>
                      <Loader2 className="animate-spin w-5 h-5 mr-3" />
                      Menyimpan...
                    </>
                  ) : isEditing ? (
                    <>
                      <Save className="w-5 h-5 mr-2" />
                      Update Produk
                    </>
                  ) : (
                    <>
                      <Plus className="w-5 h-5 mr-2" />
                      Simpan Produk
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        )}

        {/* 🔍 Pencarian */}
        <form onSubmit={handleSearch} className="flex justify-end mb-4">
          <div className="relative w-full max-w-sm">
            <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
              <Search className="w-5 h-5 text-gray-400" />
            </div>
            <input
              type="text"
              placeholder="Cari produk..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="block w-full pl-10 pr-4 py-2 border rounded-lg shadow-sm dark:bg-gray-700 dark:text-white"
            />
          </div>
        </form>

        {/* 🧾 Tabel */}
        <div className="overflow-x-auto bg-white dark:bg-gray-800 rounded-xl shadow-lg">
          <table className="min-w-full text-left">
            <thead className="bg-gray-100 dark:bg-gray-700">
              <tr>
                <th className="p-4">No</th>
                <th className="p-4">Nama</th>
                <th className="p-4">Harga</th>
                <th className="p-4">Stok</th>
                <th className="p-4">Gambar</th>
                <th className="p-4 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              {products.data.length > 0 ? (
                products.data.map((product, i) => (
                  <tr
                    key={product.id}
                    className="border-b hover:bg-gray-50 dark:hover:bg-gray-700"
                  >
                    <td className="p-4">
                      {(products.current_page - 1) * products.per_page + i + 1}
                    </td>
                    <td className="p-4 font-medium">{product.nama}</td>
                    <td className="p-4">
                      {new Intl.NumberFormat("id-ID", {
                        style: "currency",
                        currency: "IDR",
                        minimumFractionDigits: 0,
                      }).format(product.harga)}
                    </td>
                    <td className="p-4">{product.stok}</td>
                    <td className="p-4">
                      {product.gambar ? (
                        <img
                          src={`/storage/${product.gambar}`}
                          alt={product.nama}
                          className="w-12 h-12 object-cover rounded-md"
                        />
                      ) : (
                        <span className="text-gray-400 text-sm italic">
                          Tidak ada gambar
                        </span>
                      )}
                    </td>
                    <td className="p-4 text-center space-x-2">
                      <button
                        onClick={() => handleEdit(product)}
                        className="bg-yellow-500 hover:bg-yellow-600 text-white p-2 rounded-full"
                      >
                        <Edit className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleDelete(product.id)}
                        className="bg-red-600 hover:bg-red-700 text-white p-2 rounded-full"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </td>
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan="6" className="p-4 text-center text-gray-500">
                    Tidak ada produk ditemukan.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div className="flex justify-end mt-4 space-x-2">
          {products.links.map((link, i) => (
            <button
              key={i}
              onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
              className={`px-3 py-1 border rounded-md ${
                link.active
                  ? "bg-amber-600 text-white"
                  : "text-gray-600 hover:bg-amber-100"
              }`}
              dangerouslySetInnerHTML={{ __html: link.label }}
            />
          ))}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
