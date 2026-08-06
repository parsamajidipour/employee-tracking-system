interface ConfirmState {
  open: boolean
  title: string
  message: string
  variant: 'default' | 'danger'
  resolve: ((value: boolean) => void) | null
}

// Module-level, not inside the composable function — every call site needs
// the same one dialog instance (rendered once by ConfirmDialog.vue in
// app.vue), the same way useToast()'s toast list is shared.
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

/**
 * Replaces window.confirm() across the app — resolves to true/false the
 * same way, but renders through components/Modal.vue (via
 * components/ConfirmDialog.vue) instead of a native browser dialog.
 */
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
