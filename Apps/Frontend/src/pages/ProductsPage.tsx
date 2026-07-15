import { useEffect, useMemo, useState } from 'react'
import type { ChangeEvent, FormEvent, ReactNode } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import { Eye, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { categoriesApi } from '../api/categories'
import { productsApi } from '../api/products'
import type { ProductFilters, ProductPayload } from '../api/products'
import { apiErrorMessage } from '../lib/api'
import {
  Badge,
  Button,
  CapacityBar,
  Card,
  Checkbox,
  ConfirmDialog,
  Drawer,
  EmptyState,
  Field,
  Input,
  PageHeader,
  Pagination,
  Select,
  Table,
  TableSkeleton,
  TBody,
  TD,
  TH,
  THead,
  Textarea,
  Tooltip,
} from '../components/ui'
import { StockStatusBadge } from '../components/StockStatusBadge'
import { formatCurrency, formatNumber } from '../lib/format'
import { usePageTitle } from '../lib/usePageTitle'
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

/** Tiny uppercase group label used to section the drawer form. */
function SectionLabel({ children }: { children: ReactNode }) {
  return (
    <p className="pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{children}</p>
  )
}

export function ProductsPage() {
  usePageTitle('Products')
  const navigate = useNavigate()

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

  const [drawerOpen, setDrawerOpen] = useState(false)
  const [editing, setEditing] = useState<Product | null>(null)
  const [form, setForm] = useState<FormState>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<Product | null>(null)
  const [deleting, setDeleting] = useState(false)

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

  /** Column-header sorting: first click sorts asc, clicking again flips the direction. */
  const toggleSort = (column: string) => {
    setFilters((f) => ({
      ...f,
      sort_by: column,
      sort_dir: f.sort_by === column && f.sort_dir === 'asc' ? 'desc' : 'asc',
      page: 1,
    }))
  }

  const sortDirFor = (column: string): 'asc' | 'desc' | null =>
    filters.sort_by === column ? filters.sort_dir ?? 'asc' : null

  const onCategoryFilter = (e: ChangeEvent<HTMLSelectElement>) => {
    const value = e.target.value
    setFilters((f) => ({ ...f, category_id: value ? Number(value) : '', page: 1 }))
  }

  const openCreate = () => {
    setEditing(null)
    setForm(emptyForm)
    setDrawerOpen(true)
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
    setDrawerOpen(true)
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
      setDrawerOpen(false)
      refresh()
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setSaving(false)
    }
  }

  const onDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await productsApi.remove(deleteTarget.id)
      toast.success('Product deleted.')
      setDeleteTarget(null)
      refresh()
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setDeleting(false)
    }
  }

  const setField = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }))

  return (
    <div>
      <PageHeader
        title="Products"
        description={meta ? `${meta.total} products in the catalog` : undefined}
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            New product
          </Button>
        }
      />

      {/* Toolbar */}
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <div className="relative w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <Input
            className="pl-9"
            placeholder="Search name or SKU…"
            aria-label="Search products"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
          />
        </div>
        <Select
          className="w-44"
          aria-label="Filter by category"
          value={filters.category_id ? String(filters.category_id) : ''}
          onChange={onCategoryFilter}
        >
          <option value="">All categories</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </Select>
        <Select className="w-48" aria-label="Sort products" value={currentSort} onChange={onSortChange}>
          {sortOptions.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </Select>
        <Checkbox
          label="Below min level"
          checked={filters.low_stock === true}
          onChange={(e) => setFilters((f) => ({ ...f, low_stock: e.target.checked, page: 1 }))}
        />
      </div>

      <Card>
        {loading ? (
          <TableSkeleton rows={10} cols={6} />
        ) : products.length === 0 ? (
          <EmptyState
            title="No products found"
            message="Try adjusting your filters, or add a new product."
            action={
              <Button onClick={openCreate}>
                <Plus className="h-4 w-4" />
                New product
              </Button>
            }
          />
        ) : (
          <>
            <Table>
              <THead>
                <TH sortable sortDir={sortDirFor('name')} onSort={() => toggleSort('name')}>
                  Product
                </TH>
                <TH align="right" sortable sortDir={sortDirFor('price')} onSort={() => toggleSort('price')}>
                  Price
                </TH>
                <TH
                  align="right"
                  sortable
                  sortDir={sortDirFor('quantity')}
                  onSort={() => toggleSort('quantity')}
                >
                  Stock
                </TH>
                <TH align="right">Value</TH>
                <TH>Status</TH>
                <TH align="right">
                  <span className="sr-only">Actions</span>
                </TH>
              </THead>
              <TBody>
                {products.map((product) => (
                  <tr
                    key={product.id}
                    className="cursor-pointer transition-colors hover:bg-slate-50/80"
                    onClick={() => navigate(`/products/${product.id}`)}
                  >
                    <TD>
                      <p className="font-medium text-slate-900">{product.name}</p>
                      <div className="mt-0.5 flex items-center gap-2">
                        <span className="font-mono text-xs text-slate-400">{product.sku}</span>
                        {product.category && <Badge tone="gray">{product.category.name}</Badge>}
                      </div>
                    </TD>
                    <TD numeric className="text-slate-700">
                      {formatCurrency(product.price)}
                    </TD>
                    <TD numeric className="text-slate-700">
                      {formatNumber(product.quantity)}
                      <div className="ml-auto mt-1 w-24">
                        <CapacityBar value={product.quantity} max={product.reorder_level} />
                      </div>
                    </TD>
                    <TD numeric className="text-slate-700">
                      {formatCurrency(product.stock_value)}
                    </TD>
                    <TD>
                      <StockStatusBadge product={product} />
                    </TD>
                    <TD>
                      <div className="flex items-center justify-end gap-1">
                        <Tooltip content="View">
                          <Link
                            to={`/products/${product.id}`}
                            aria-label={`View ${product.name}`}
                            onClick={(e) => e.stopPropagation()}
                            className="inline-flex items-center justify-center rounded-lg p-1.5 text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                          >
                            <Eye className="h-4 w-4" />
                          </Link>
                        </Tooltip>
                        <Tooltip content="Edit">
                          <Button
                            variant="ghost"
                            size="xs"
                            aria-label={`Edit ${product.name}`}
                            onClick={(e) => {
                              e.stopPropagation()
                              openEdit(product)
                            }}
                          >
                            <Pencil className="h-4 w-4" />
                          </Button>
                        </Tooltip>
                        <Tooltip content="Delete">
                          <Button
                            variant="ghost"
                            size="xs"
                            aria-label={`Delete ${product.name}`}
                            onClick={(e) => {
                              e.stopPropagation()
                              setDeleteTarget(product)
                            }}
                          >
                            <Trash2 className="h-4 w-4 text-danger-600" />
                          </Button>
                        </Tooltip>
                      </div>
                    </TD>
                  </tr>
                ))}
              </TBody>
            </Table>
            {meta && <Pagination meta={meta} onPage={(page) => setFilters((f) => ({ ...f, page }))} />}
          </>
        )}
      </Card>

      <Drawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        title={editing ? 'Edit product' : 'New product'}
        subtitle={editing ? `SKU ${editing.sku}` : undefined}
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setDrawerOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" form="product-form" loading={saving}>
              {editing ? 'Save changes' : 'Create product'}
            </Button>
          </>
        }
      >
        <form id="product-form" onSubmit={onSubmit} className="space-y-4">
          <SectionLabel>Basics</SectionLabel>
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

          <SectionLabel>Pricing</SectionLabel>
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

          <SectionLabel>Inventory</SectionLabel>
          <div className="grid grid-cols-2 gap-3">
            <Field
              label="Min stock level"
              htmlFor="p-reorder"
              hint="Optional manual floor — reorder alerts come from the demand model."
            >
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
          <Checkbox
            label="Active (visible & sellable)"
            checked={form.is_active}
            onChange={(e) => setField('is_active', e.target.checked)}
          />
        </form>
      </Drawer>

      <ConfirmDialog
        open={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => void onDelete()}
        title="Delete product"
        message={
          deleteTarget
            ? `Delete "${deleteTarget.name}"? This also removes its stock history.`
            : ''
        }
        loading={deleting}
      />
    </div>
  )
}
