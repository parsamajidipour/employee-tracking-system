interface ConfirmState {
  open: boolean
  title: string
  message: string
  variant: 'default' | 'danger'
  resolve: ((value: boolean) => void) | null
}

const state = reactive<ConfirmState>({
  open: false,
  title: 'Confirm',
  message: '',
  variant: 'default',
  resolve: null,
})

export function useConfirmState() {
  return state
}

export function useConfirm() {
  function confirm(message: string, opts: { title?: string; variant?: 'default' | 'danger' } = {}): Promise<boolean> {
    return new Promise((resolve) => {
      state.message = message
      state.title = opts.title ?? 'Confirm'
      state.variant = opts.variant ?? 'default'
      state.resolve = resolve
      state.open = true
    })
  }

  return { confirm }
}
