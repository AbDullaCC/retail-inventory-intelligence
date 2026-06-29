import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import toast from 'react-hot-toast'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { categoriesApi } from '../api/categories'
import { apiErrorMessage } from '../lib/api'
import { Button, Card, EmptyState, Field, Input, Modal, PageSpinner, Textarea } from '../components/ui'
import { formatDateTime } from '../lib/format'
import type { Category } from '../types'

export function CategoriesPage() {
  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Category | null>(null)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [saving, setSaving] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)

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

  const onDelete = async (category: Category) => {
    if (!window.confirm(`Delete category "${category.name}"?`)) return
    setDeletingId(category.id)
    try {
      await categoriesApi.remove(category.id)
      toast.success('Category deleted.')
      setCategories((prev) => prev.filter((c) => c.id !== category.id))
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-slate-900">Categories</h1>
          <p className="text-sm text-slate-500">Organise your products into groups.</p>
        </div>
        <Button onClick={openCreate}>
          <Plus className="h-4 w-4" />
          New category
        </Button>
      </div>

      <Card>
        {loading ? (
          <PageSpinner />
        ) : categories.length === 0 ? (
          <EmptyState
            title="No categories yet"
            message="Create your first category to start adding products."
            action={<Button onClick={openCreate}>New category</Button>}
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th className="px-5 py-3 font-medium">Name</th>
                  <th className="px-5 py-3 font-medium">Description</th>
                  <th className="px-5 py-3 font-medium">Products</th>
                  <th className="px-5 py-3 font-medium">Created</th>
                  <th className="px-5 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {categories.map((category) => (
                  <tr key={category.id} className="hover:bg-slate-50">
                    <td className="px-5 py-3 font-medium text-slate-800">{category.name}</td>
                    <td className="max-w-xs truncate px-5 py-3 text-slate-500">
                      {category.description ?? '—'}
                    </td>
                    <td className="px-5 py-3 text-slate-600">{category.products_count ?? 0}</td>
                    <td className="px-5 py-3 text-slate-500">{formatDateTime(category.created_at)}</td>
                    <td className="px-5 py-3">
                      <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="sm" onClick={() => openEdit(category)}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          loading={deletingId === category.id}
                          onClick={() => void onDelete(category)}
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
        )}
      </Card>

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? 'Edit category' : 'New category'}
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
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="secondary" onClick={() => setModalOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" loading={saving}>
              {editing ? 'Save changes' : 'Create category'}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
