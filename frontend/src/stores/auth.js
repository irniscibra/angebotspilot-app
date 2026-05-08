import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('user')) || null,
    token: localStorage.getItem('auth_token') || null,
    loading: false,
    error: null,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
    company: (state) => state.user?.company || null,
    userName: (state) => state.user?.name || '',

    // Trial-Status
    trialEndsAt: (state) => state.user?.company?.trial_ends_at || null,
    plan: (state) => state.user?.company?.plan || 'trial',

    trialDaysLeft: (state) => {
      const endsAt = state.user?.company?.trial_ends_at
      if (!endsAt) return 0
      const diff = new Date(endsAt) - new Date()
      return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
    },

    trialExpired: (state) => {
      const company = state.user?.company
      if (!company) return false
      if (['starter', 'professional', 'enterprise'].includes(company.plan)) return false
      if (!company.trial_ends_at) return false
      return new Date(company.trial_ends_at) < new Date()
    },

    hasActiveSubscription: (state) => {
      const company = state.user?.company
      if (!company) return false
      if (['starter', 'professional', 'enterprise'].includes(company.plan)) return true
      if (company.plan === 'trial' && new Date(company.trial_ends_at) > new Date()) return true
      return false
    },
  },

  actions: {
    async register(data) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/auth/register', data)
        // Kein Token mehr – User muss zuerst E-Mail bestätigen
        return response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Registrierung fehlgeschlagen'
        throw err
      } finally {
        this.loading = false
      }
    },

    async login(email, password) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/auth/login', { email, password })
        this.setAuth(response.data)
        return response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Login fehlgeschlagen'
        throw err
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        await api.post('/auth/logout')
      } catch (e) {
        // Ignorieren – Token wird trotzdem gelöscht
      }
      this.clearAuth()
    },

    async fetchUser() {
      try {
        const response = await api.get('/auth/me')
        this.user = response.data.user
        localStorage.setItem('user', JSON.stringify(this.user))
      } catch (err) {
        this.clearAuth()
      }
    },

    setAuth(data) {
      this.user = data.user
      this.token = data.token
      localStorage.setItem('auth_token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
    },

    clearAuth() {
      this.user = null
      this.token = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
    },
  },
})