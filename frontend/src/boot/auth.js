import { boot } from 'quasar/wrappers'
import { useAuthStore } from 'src/stores/auth'

/**
 * Boot file: refreshes user data from the server on every app start.
 * This prevents the router guard from using stale localStorage data
 * (e.g. outdated trial_ends_at after a DB change).
 */
export default boot(async () => {
  const authStore = useAuthStore()

  if (authStore.token) {
    try {
      await authStore.fetchUser()
    } catch {
      // fetchUser() already calls clearAuth() on failure (401 etc.)
    }
  }
})
