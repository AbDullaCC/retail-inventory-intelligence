import { describe, it, expect } from 'vitest'
import { cleanParams } from './products'

describe('cleanParams', () => {
  it('drops undefined, empty-string and false values', () => {
    expect(cleanParams({ search: '', category_id: '', low_stock: false, page: 1 })).toEqual({ page: 1 })
  })

  it('converts boolean true to the string "true"', () => {
    expect(cleanParams({ low_stock: true, is_active: true })).toEqual({
      low_stock: 'true',
      is_active: 'true',
    })
  })

  it('keeps real string and number values', () => {
    expect(cleanParams({ search: 'cola', category_id: 3, per_page: 10 })).toEqual({
      search: 'cola',
      category_id: 3,
      per_page: 10,
    })
  })
})
