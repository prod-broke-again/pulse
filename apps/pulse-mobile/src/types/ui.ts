export type ToastType = 'success' | 'error' | 'info'

export interface ToastItem {
  id: string
  message: string
  type: ToastType
}
