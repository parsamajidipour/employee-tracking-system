/**
 * Without this, nothing in the panel ever sends a signed-out visitor to
 * /login: index.vue redirects "/" straight to /map unconditionally, and
 * every page's own 401 handling is just a local error message, not a
 * redirect — a visitor with no session lands on a page that tries and
 * fails to load data, with no way to discover /login exists.
 *
 * The import.meta.server bail-out is defensive, not load-bearing: the app
 * is ssr:false (see nuxt.config.ts) specifically so this middleware and
 * every apiFetch() call run where the browser's session cookie actually
 * exists. If that ever changes, this keeps the check from running
 * cookie-less server-side and bouncing every authenticated visitor to
 * /login regardless of their real session.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  if (import.meta.server) return
  if (to.path === '/login') return

  const { user, refresh } = useAuthUser()
  if (user.value) return

  if (!(await refresh())) {
    return navigateTo('/login')
  }
})
