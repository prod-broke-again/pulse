import { http } from '../lib/http'

export interface UploadResult {
  path: string
  original_name: string
  mime_type: string
  size: number
}

export async function uploadFile(file: File | Blob, fileName?: string): Promise<UploadResult> {
  const form = new FormData()
  const blob = file instanceof File ? file : new File([file], fileName ?? 'upload', { type: (file as Blob).type })
  form.append('file', blob)

  const res = await http.post<{ data: UploadResult }>('/uploads', form)
  return res.data.data
}
