import { api } from './client'
import type { UploadResult } from '../types/dto/upload.types'

export async function uploadFile(file: File): Promise<UploadResult['data']> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await api.postForm<UploadResult>('/uploads', formData)
  return response.data
}
