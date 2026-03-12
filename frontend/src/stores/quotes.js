import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export const useQuoteStore = defineStore('quotes', {
  state: () => ({
    quotes: [],
    currentQuote: null,
    loading: false,
    generating: false,
    error: null,
    stats: null,
  }),

  actions: {
    async fetchQuotes() {
      this.loading = true
      try {
        const response = await api.get('/quotes')
        this.quotes = response.data.data || response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Fehler beim Laden'
      } finally {
        this.loading = false
      }
    },

    async fetchQuote(id) {
      this.loading = true
      try {
        const response = await api.get(`/quotes/${id}`)
        this.currentQuote = response.data
        return response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Angebot nicht gefunden'
        throw err
      } finally {
        this.loading = false
      }
    },

    async createQuote(data) {
      this.generating = true
      this.error = null
      try {
        const response = await api.post('/quotes', {
          project_description: data.description,
          customer_id: data.customer_id || null,
          project_address: data.address || null,
          use_ai: true,
        })
        this.currentQuote = response.data.quote
        return response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Fehler bei der Erstellung'
        throw err
      } finally {
        this.generating = false
      }
    },

    async updateQuote(id, data) {
      try {
        const response = await api.put(`/quotes/${id}`, data)
        this.currentQuote = response.data
        return response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Fehler beim Speichern'
        throw err
      }
    },

    async updateItem(quoteId, itemId, data) {
      try {
        const response = await api.put(`/quotes/${quoteId}/items/${itemId}`, data)
        // Item im currentQuote aktualisieren
        if (this.currentQuote) {
          const idx = this.currentQuote.items.findIndex(i => i.id === itemId)
          if (idx !== -1) this.currentQuote.items[idx] = response.data
          // Angebot neu laden für aktuelle Summen
          await this.fetchQuote(quoteId)
        }
        return response.data
      } catch (err) {
        throw err
      }
    },

    async addItem(quoteId, data) {
      try {
        const response = await api.post(`/quotes/${quoteId}/items`, data)
        await this.fetchQuote(quoteId)
        return response.data
      } catch (err) {
        throw err
      }
    },

    async deleteItem(quoteId, itemId) {
      try {
        await api.delete(`/quotes/${quoteId}/items/${itemId}`)
        await this.fetchQuote(quoteId)
      } catch (err) {
        throw err
      }
    },

    async deleteQuote(id) {
      try {
        await api.delete(`/quotes/${id}`)
        this.quotes = this.quotes.filter(q => q.id !== id)
      } catch (err) {
        throw err
      }
    },

    async duplicateQuote(id) {
      try {
        const response = await api.post(`/quotes/${id}/duplicate`)
        return response.data
      } catch (err) {
        throw err
      }
    },

    async sendQuote(id) {
      try {
        const response = await api.post(`/quotes/${id}/send`)
        if (this.currentQuote?.id === id) {
          this.currentQuote = response.data.quote
        }
        return response.data
      } catch (err) {
        throw err
      }
    },

    async fetchStats() {
      try {
        const response = await api.get('/dashboard/stats')
        this.stats = response.data
        return response.data
      } catch (err) {
        throw err
      }
    },
  },
})