
export default defineNuxtRouteMiddleware(async (to) => {
  if (import.meta.server) return
  if (to.path === '/login') return

  const { user, refresh } = useAuthUser()
  if (user.value) return

  if (!(await refresh())) {
    return navigateTo('/login')
  }
})
