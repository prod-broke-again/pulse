import { api } from './client'

export interface DepartmentOption {
  id: number
  name: string
  slug: string
  icon?: string
}

export interface DepartmentWithSource extends DepartmentOption {
  source_id: number
  /** Название источника (интеграции); одна рубрика может повторяться на разных источниках. */
  source_name?: string | null
}

export async function fetchDepartments(sourceId: number): Promise<DepartmentOption[]> {
  const response = await api.get<{ data: DepartmentOption[] }>('/departments', { source_id: sourceId })
  return response.data
}

/** Все отделы модератора (агрегат по источникам). */
export async function fetchUserDepartments(): Promise<DepartmentWithSource[]> {
  const response = await api.get<{ data: DepartmentWithSource[] }>('/user/departments')
  return response.data
}
