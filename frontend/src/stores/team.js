import { defineStore } from "pinia";
import { api } from "src/boot/axios";

export const useTeamStore = defineStore("team", {
  state: () => ({
    members: [],
    seats: { used: 0, limit: 0 },
    loading: false,
    saving: false,
    error: null,
    companyTimeEntries: { entries: [], byEmployee: [] },
    timeEntriesLoading: false,
  }),

  actions: {
    // Firmenweite Zeituebersicht ueber alle Projekte (fuer Lohnabrechnung).
    // month im Format "YYYY-MM"; ohne Angabe liefert das Backend alle Zeiten.
    async fetchCompanyTimeEntries(month) {
      this.timeEntriesLoading = true;
      try {
        const response = await api.get("/time-entries", {
          params: month ? { month } : {},
        });
        this.companyTimeEntries = {
          entries: response.data.entries,
          byEmployee: response.data.by_employee,
        };
      } catch (err) {
        this.error = err.response?.data?.message || "Fehler beim Laden der Zeiten";
      } finally {
        this.timeEntriesLoading = false;
      }
    },

    async fetchTeam() {
      this.loading = true;
      this.error = null;
      try {
        const response = await api.get("/team");
        this.members = response.data.members;
        this.seats = response.data.seats;
      } catch (err) {
        this.error = err.response?.data?.message || "Fehler beim Laden";
      } finally {
        this.loading = false;
      }
    },

    async invite(data) {
      this.saving = true;
      this.error = null;
      try {
        const response = await api.post("/team/invite", data);
        await this.fetchTeam();
        return response.data;
      } catch (err) {
        this.error = err.response?.data?.message || "Einladung fehlgeschlagen";
        throw err;
      } finally {
        this.saving = false;
      }
    },

    async remove(userId) {
      try {
        await api.delete(`/team/${userId}`);
        this.members = this.members.filter((m) => m.id !== userId);
      } catch (err) {
        throw err;
      }
    },
  },
});
