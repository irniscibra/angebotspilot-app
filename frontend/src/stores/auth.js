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
  },

  actions: {
    async register(data) {
      this.loading = true
      this.error = null
      try {
        const response = await api.post('/auth/register', data)
        this.setAuth(response.data)
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