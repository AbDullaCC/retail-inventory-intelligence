import { useEffect, useMemo, useState } from 'react'
import type { ChangeEvent, FormEvent } from 'react'
import { Link } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { categoriesApi } from '../api/categories'
import { productsApi } from '../api/products'
import type { ProductFilters, ProductPayload } from '../api/products'
import { apiErrorMessage } from '../lib/api'
import {
  Button,
  Card,
  EmptyState,
  Field,
  Input,
  Modal,
  Pagination,
  PageSpinner,
  Select,
  Textarea,
} from '../components/ui'
import { StockStatusBadge } from '../components/StockStatusBadge'
import { formatCurrency, formatNumber } from '../lib/format'
import type { Category, PaginationMeta, Product } from '../types'

interface FormState {
  category_id: string
  sku: string
  name: string
  description: string
  price: string
  cost: string
  reorder_level: string
  is_active: boolean
  quantity: string
}

const emptyForm: FormState = {
  category_id: '',
  sku: '',
  name: '',
  description: '',
  price: '',
  cost: '',
  reorder_level: '0',
  is_active: true,
  quantity: '0',
}

const sortOptions: Array<{ value: string; label: string; sort_by: string; sort_dir: 'asc' | 'desc' }> = [
  { value: 'name-asc', label: 'Name (A–Z)', sort_by: 'name', sort_dir: 'asc' },
  { value: 'name-desc', label: 'Name (Z–A)', sort_by: 'name', sort_dir: 'desc' },
  { value: 'price-asc', label: 'Price (low → high)', sort_by: 'price', sort_dir: 'asc' },
  { value: 'price-desc', label: 'Price (high → low)', sort_by: 'price', sort_dir: 'desc' },
  { value: 'quantity-asc', label: 'Stock (low → high)', sort_by: 'quantity', sort_dir: 'asc' },
  { value: 'quantity-desc', label: 'Stock (high → low)', sort_by: 'quantity', sort_dir: 'desc' },
]

export function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([])
  const [meta, setMeta] = useState<PaginationMeta | null>(null)
  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [searchInput, setSearchInput] = useState('')
  const [filters, setFilters] = useState<ProductFilters>({
    page: 1,
    per_page: 10,
    sort_by: 'name',
    sort_dir: 'asc',
  })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Product | null>(null)
  const [form, setForm] = useState<FormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  // Load categories once for the filter + form selects.
  useEffect(() => {
    categoriesApi.list().then(setCategories).catch((error) => toast.error(apiErrorMessage(error)))
  }, [])

  // Debounce the search box into the filters.
  useEffect(() => {
    const timer = setTimeout(() => {
      setFilters((f) => ({ ...f, search: searchInput || undefined, page: 1 }))
    }, 300)
    return () => clearTimeout(timer)
  }, [searchInput])

  // Fetch products whenever filters change. The `ignore` flag prevents an older,
  // slower response from overwriting a newer one (search/filter race) and silences
  // state updates after unmount.
  useEffect(() => {
    let ignore = false
    setLoading(true)
    productsApi
      .list(filters)
      .then((res) => {
        if (ignore) return
        setProducts(res.data)
        setMeta(res.meta)
      })
      .catch((error) => {
        if (!ignore) toast.error(apiErrorMessage(error))
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })
    return () => {
      ignore = true
    }
  }, [filters])

  const currentSort = useMemo(
    () => `${filters.sort_by ?? 'name'}-${filters.sort_dir ?? 'asc'}`,
    [filters.sort_by, filters.sort_dir],
  )

  const onSortChange = (e: ChangeEvent<HTMLSelectElement>) => {
    const option = sortOptions.find((o) => o.value === e.target.value)
    if (option) {
      setFilters((f) => ({ ...f, sort_by: option.sort_by, sort_dir: option.sort_dir, page: 1 }))
    }
  }

  const onCategoryFilter = (e: ChangeEvent<HTMLSelectElement>) => {
    const value = e.target.value
    setFilters((f) => ({ ...f, category_id: value ? Number(value) : '', page: 1 }))
  }

  const openCreate = () => {
    setEditing(null)
    setForm(emptyForm)
    setModalOpen(true)
  }

  const openEdit = (product: Product) => {
    setEditing(product)
    setForm({
      category_id: String(product.category_id),
      sku: product.sku,
      name: product.name,
      description: product.description ?? '',
      price: String(product.price),
      cost: product.cost === null ? '' : String(product.cost),
      reorder_level: String(product.reorder_level),
      is_active: product.is_active,
      quantity: String(product.quantity),
    })
    setModalOpen(true)
  }

  const refresh = () => setFilters((f) => ({ ...f }))

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    if (!form.category_id) {
      toast.error('Please choose a category.')
      return
    }
    setSaving(true)
    try {
      const payload: ProductPayload = {
        category_id: Number(form.category_id),
        sku: form.sku.trim(),
        name: form.name.trim(),
        description: form.description || null,
        price: Number(form.price),
        cost: form.cost === '' ? null : Number(form.cost),
        reorder_level: Number(form.reorder_level || '0'),
        is_active: form.is_active,
      }
      if (editing) {
        await productsApi.update(editing.id, payload)
        toast.success('Product updated.')
      } else {
        payload.quantity = Number(form.quantity || '0')
        await productsApi.create(payload)
        toast.success('Product created.')
      }
      setModalOpen(false)
      refresh()
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  const onDelete = async (product: Product) => {
    if (!window.confirm(`Delete product "${product.name}"? This also removes its stock history.`)) return
    setDeletingId(product.id)
    try {
      await productsApi.remove(product.id)
      toast.success('Product deleted.')
      refresh()
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setDeletingId(null)
    }
  }

  const setField = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }))

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Products</h1>
          <p className="text-sm text-slate-500">Manage your catalogue and stock levels.</p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4" />
          New product
        </Button>
      </div>

      {/* Filters */}
      <Card className="p-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <Input
              className="pl-9"
              placeholder="Search name or SKU…"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
            />
          </div>
          <Select value={filters.category_id ? String(filters.category_id) : ''} onChange={onCategoryFilter}>
            <option value="">All categories</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </Select>
          <Select value={currentSort} onChange={onSortChange}>
            {sortOptions.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </Select>
          <label className="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600">
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-slate-300 text-indigo-600"
              checked={filters.low_stock === true}
              onChange={(e) => setFilters((f) => ({ ...f, low_stock: e.target.checked, page: 1 }))}
            />
            Low stock only
          </label>
        </div>
      </Card>

      {/* Table */}
      <Card>
        {loading ? (
          <PageSpinner />
        ) : products.length === 0 ? (
          <EmptyState
            title="No products found"
            message="Try adjusting your filters, or add a new product."
            action={<Button onClick={openCreate}>New product</Button>}
          />
        ) : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                  <tr>
                    <th className="px-5 py-3 font-medium">Product</th>
                    <th className="px-5 py-3 font-medium">Category</th>
                    <th className="px-5 py-3 text-right font-medium">Price</th>
                    <th className="px-5 py-3 text-right font-medium">Stock</th>
                    <th className="px-5 py-3 font-medium">Status</th>
                    <th className="px-5 py-3" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {products.map((product) => (
                    <tr key={product.id} className="hover:bg-slate-50">
                      <td className="px-5 py-3">
                        <Link
                          to={`/products/${product.id}`}
                          className="font-medium text-slate-800 hover:text-indigo-600"
                        >
                          {product.name}
                        </Link>
                        <p className="text-xs text-slate-400">{product.sku}</p>
                      </td>
                      <td className="px-5 py-3 text-slate-600">{product.category?.name ?? '—'}</td>
                      <td className="px-5 py-3 text-right text-slate-700">{formatCurrency(product.price)}</td>
                      <td className="px-5 py-3 text-right text-slate-700">{formatNumber(product.quantity)}</td>
                      <td className="px-5 py-3">
                        <StockStatusBadge product={product} />
                      </td>
                      <td className="px-5 py-3">
                        <div className="flex justify-end gap-1">
                          <Link
                            to={`/products/${product.id}`}
                            className="rounded-md p-1.5 text-slate-500 hover:bg-slate-100"
                            aria-label="View"
                          >
                            <Eye className="h-4 w-4" />
                          </Link>
                          <Button variant="ghost" size="sm" onClick={() => openEdit(product)}>
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            loading={deletingId === product.id}
                            onClick={() => void onDelete(product)}
                          >
                            <Trash2 className="h-4 w-4 text-red-500" />
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {meta && <Pagination meta={meta} onPage={(page) => setFilters((f) => ({ ...f, page }))} />}
          </>
        )}
      </Card>

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? 'Edit product' : 'New product'}
      >
        <form onSubmit={onSubmit} className="space-y-4">
          <Field label="Category" htmlFor="p-category" required>
            <Select
              id="p-category"
              value={form.category_id}
              onChange={(e) => setField('category_id', e.target.value)}
              required
            >
              <option value="">Select a category…</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </Select>
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="SKU" htmlFor="p-sku" required>
              <Input
                id="p-sku"
                value={form.sku}
                onChange={(e) => setField('sku', e.target.value)}
                placeholder="SKU-001"
                required
              />
            </Field>
            <Field label="Name" htmlFor="p-name" required>
              <Input
                id="p-name"
                value={form.name}
                onChange={(e) => setField('name', e.target.value)}
                required
              />
            </Field>
          </div>
          <Field label="Description" htmlFor="p-desc">
            <Textarea
              id="p-desc"
              rows={2}
              value={form.description}
              onChange={(e) => setField('description', e.target.value)}
            />
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Price" htmlFor="p-price" required>
              <Input
                id="p-price"
                type="number"
                step="0.01"
                min="0"
                value={form.price}
                onChange={(e) => setField('price', e.target.value)}
                required
              />
            </Field>
            <Field label="Cost" htmlFor="p-cost">
              <Input
                id="p-cost"
                type="number"
                step="0.01"
                min="0"
                value={form.cost}
                onChange={(e) => setField('cost', e.target.value)}
              />
            </Field>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Reorder level" htmlFor="p-reorder" hint="Low-stock threshold.">
              <Input
                id="p-reorder"
                type="number"
                min="0"
                value={form.reorder_level}
                onChange={(e) => setField('reorder_level', e.target.value)}
              />
            </Field>
            {!editing && (
              <Field label="Opening stock" htmlFor="p-qty" hint="Initial quantity on hand.">
                <Input
                  id="p-qty"
                  type="number"
                  min="0"
                  value={form.quantity}
                  onChange={(e) => setField('quantity', e.target.value)}
                />
              </Field>
            )}
          </div>
          <label className="flex items-center gap-2 text-sm text-slate-600">
            <input
              type="checkbox"
              className="h-4 w-4 rounded border-slate-300 text-indigo-600"
              checked={form.is_active}
              onChange={(e) => setField('is_active', e.target.checked)}
            />
            Active (visible &amp; sellable)
          </label>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" loading={saving}>
              {editing ? 'Save changes' : 'Create product'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
