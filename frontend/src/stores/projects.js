import { defineStore } from 'pinia'
import { api } from 'src/boot/axios'

export const useProjectStore = defineStore('projects', {
  state: () => ({
    projects: [],
    currentProject: null,
    loading: false,
    saving: false,
    error: null,
    pagination: {
      currentPage: 1,
      lastPage: 1,
      total: 0,
      perPage: 12,
    },
  }),

  actions: {
    async fetchProjects(params = {}) {
      this.loading = true
      try {
        const response = await api.get('/projects', { params })
        // Backend liefert Laravel-Paginator-Struktur { data, current_page, ... }
        this.projects = response.data.data || response.data
        if (response.data.current_page !== undefined) {
          this.pagination = {
            currentPage: response.data.current_page,
            lastPage: response.data.last_page,
            total: response.data.total,
            perPage: response.data.per_page,
          }
        }
      } catch (err) {
        this.error = err.response?.data?.message || 'Fehler beim Laden'
      } finally {
        this.loading = false
      }
    },

    async fetchProject(id) {
      this.loading = true
      try {
        const response = await api.get(`/projects/${id}`)
        this.currentProject = response.data
        return response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Projekt nicht gefunden'
        throw err
      } finally {
        this.loading = false
      }
    },

    async createProject(data) {
      this.saving = true
      this.error = null
      try {
        const response = await api.post('/projects', data)
        return response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Fehler bei der Erstellung'
        throw err
      } finally {
        this.saving = false
      }
    },

    async updateProject(id, data) {
      this.saving = true
      try {
        const response = await api.put(`/projects/${id}`, data)
        this.currentProject = response.data
        return response.data
      } catch (err) {
        this.error = err.response?.data?.message || 'Fehler beim Speichern'
        throw err
      } finally {
        this.saving = false
      }
    },

    async deleteProject(id) {
      try {
        await api.delete(`/projects/${id}`)
        this.projects = this.projects.filter((p) => p.id !== id)
      } catch (err) {
        throw err
      }
    },
  },
})
