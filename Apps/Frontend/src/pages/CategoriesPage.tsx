import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import toast from 'react-hot-toast'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { categoriesApi } from '../api/categories'
import { apiErrorMessage } from '../lib/api'
import {
  Badge,
  Button,
  Card,
  ConfirmDialog,
  EmptyState,
  Field,
  Input,
  Modal,
  PageHeader,
  Table,
  TableSkeleton,
  TBody,
  TD,
  TH,
  THead,
  Textarea,
  Tooltip,
} from '../components/ui'
import { formatDateTime } from '../lib/format'
import { usePageTitle } from '../lib/usePageTitle'
import type { Category } from '../types'

export function CategoriesPage() {
  usePageTitle('Categories')

  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Category | null>(null)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [saving, setSaving] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null)
  const [deleting, setDeleting] = useState(false)

  const load = () => {
    setLoading(true)
    categoriesApi
      .list()
      .then(setCategories)
      .catch((error) => toast.error(apiErrorMessage(error)))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  const openCreate = () => {
    setEditing(null)
    setName('')
    setDescription('')
    setModalOpen(true)
  }

  const openEdit = (category: Category) => {
    setEditing(category)
    setName(category.name)
    setDescription(category.description ?? '')
    setModalOpen(true)
  }

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setSaving(true)
    try {
      const payload = { name, description: description || null }
      if (editing) {
        await categoriesApi.update(editing.id, payload)
        toast.success('Category updated.')
      } else {
        await categoriesApi.create(payload)
        toast.success('Category created.')
      }
      setModalOpen(false)
      load()
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
      await categoriesApi.remove(deleteTarget.id)
      toast.success('Category deleted.')
      setCategories((prev) => prev.filter((c) => c.id !== deleteTarget.id))
      setDeleteTarget(null)
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Categories"
        description={
          loading ? undefined : `${categories.length} ${categories.length === 1 ? 'category' : 'categories'}`
        }
        actions={
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            New category
          </Button>
        }
      />

      <Card>
        {loading ? (
          <TableSkeleton rows={6} cols={5} />
        ) : categories.length === 0 ? (
          <EmptyState
            title="No categories yet"
            message="Create your first category to start adding products."
            action={
              <Button onClick={openCreate}>
                <Plus className="h-4 w-4" />
                New category
              </Button>
            }
          />
        ) : (
          <Table>
            <THead>
              <TH>Name</TH>
              <TH>Description</TH>
              <TH align="right">Products</TH>
              <TH>Created</TH>
              <TH align="right">
                <span className="sr-only">Actions</span>
              </TH>
            </THead>
            <TBody>
              {categories.map((category) => (
                <tr key={category.id} className="transition-colors hover:bg-slate-50/80">
                  <TD className="font-medium text-slate-900">{category.name}</TD>
                  <TD className="max-w-md truncate text-slate-500">{category.description ?? '—'}</TD>
                  <TD numeric>
                    <Badge tone="gray">{category.products_count ?? 0}</Badge>
                  </TD>
                  <TD className="text-slate-500">{formatDateTime(category.created_at)}</TD>
                  <TD>
                    <div className="flex items-center justify-end gap-1">
                      <Tooltip content="Edit">
                        <Button
                          variant="ghost"
                          size="xs"
                          aria-label={`Edit ${category.name}`}
                          onClick={() => openEdit(category)}
                        >
                          <Pencil className="h-4 w-4" />
                        </Button>
                      </Tooltip>
                      <Tooltip content="Delete">
                        <Button
                          variant="ghost"
                          size="xs"
                          aria-label={`Delete ${category.name}`}
                          onClick={() => setDeleteTarget(category)}
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
        )}
      </Card>

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? 'Edit category' : 'New category'}
        footer={
          <>
            <Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" form="category-form" loading={saving}>
              {editing ? 'Save changes' : 'Create category'}
            </Button>
          </>
        }
      >
        <form id="category-form" onSubmit={onSubmit} className="space-y-4">
          <Field label="Name" htmlFor="cat-name" required>
            <Input
              id="cat-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Beverages"
              required
            />
          </Field>
          <Field label="Description" htmlFor="cat-desc">
            <Textarea
              id="cat-desc"
              rows={3}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Optional description"
            />
          </Field>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        onConfirm={() => void onDelete()}
        title="Delete category"
        message={deleteTarget ? `Delete category "${deleteTarget.name}"?` : ''}
        loading={deleting}
      />
    </div>
  )
}
