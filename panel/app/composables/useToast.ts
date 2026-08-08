interface ToastItem {
  id: number
  message: string
  variant: 'success' | 'error'
}

const toasts = reactive<ToastItem[]>([])
let nextId = 0

export function useToastState() {
  return toasts
}

export function useToast() {
  function push(message: string, variant: 'success' | 'error') {
    const id = nextId++
    toasts.push({ id, message, variant })
    setTimeout(() => {
      const index = toasts.findIndex((t) => t.id === id)
      if (index !== -1) toasts.splice(index, 1)
    }, 4000)
  }

  return {
    success: (message: string) => push(message, 'success'),
    error: (message: string) => push(message, 'error'),
  }
}
