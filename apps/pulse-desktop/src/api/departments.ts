import { api } from './client'

export interface DepartmentOption {
  id: number
  name: string
  slug: string
}

export async function fetchDepartments(sourceId: number): Promise<DepartmentOption[]> {
  const response = await api.get<{ data: DepartmentOption[] }>('/departments', { source_id: sourceId })
  return response.data
}
