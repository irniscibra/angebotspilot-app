<template>
  <q-page class="q-pa-md q-pa-lg-lg" style="background: #f6f9fc">
    <div
      class="row items-center q-mb-lg"
      :class="$q.screen.lt.sm ? 'column items-stretch q-gutter-sm' : ''"
    >
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Projekte
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          {{ projectStore.pagination.total || 0 }} Projekte im Überblick
        </p>
      </div>
      <q-btn
        unelevated
        icon="add"
        label="Neues Projekt"
        no-caps
        @click="openCreateDialog"
        :class="$q.screen.lt.sm ? 'full-width' : ''"
        style="
          border-radius: 10px;
          font-weight: 600;
          background: #4f46e5;
          color: #ffffff;
        "
      />
    </div>

    <!-- Filter/Suche -->
    <div class="row q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-6 col-md-4">
        <div class="ap-input-box">
          <q-input
            v-model="search"
            borderless
            dense
            placeholder="Suchen (Titel, Adresse, Kunde)..."
          >
            <template v-slot:prepend
              ><q-icon name="search" color="grey-5"
            /></template>
          </q-input>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <div class="ap-input-box">
          <q-select
            v-model="statusFilter"
            borderless
            dense
            :options="statusFilterOptions"
            option-value="value"
            option-label="label"
            emit-value
            map-options
            placeholder="Status"
          >
            <template v-slot:prepend
              ><q-icon name="tune" color="grey-5"
            /></template>
          </q-select>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="projectStore.loading" class="row q-col-gutter-md">
      <div v-for="n in 6" :key="n" class="col-12 col-sm-6 col-md-4">
        <q-card
          flat
          style="border-radius: 14px; height: 168px; background: #ffffff"
        >
          <q-card-section>
            <q-skeleton type="text" width="40%" class="q-mb-sm" />
            <q-skeleton type="text" width="80%" class="q-mb-md" />
            <q-skeleton type="text" width="60%" />
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Leer -->
    <div
      v-else-if="projects.length === 0"
      class="text-center q-pa-xl"
      style="color: #94a3b8"
    >
      <q-icon name="folder_open" size="48px" class="q-mb-md" />
      <div style="font-size: 15px" class="q-mb-md">
        {{
          search || statusFilter
            ? "Keine Projekte gefunden"
            : "Noch keine Projekte. Legen Sie Ihr erstes Projekt an."
        }}
      </div>
      <q-btn
        v-if="!search && !statusFilter"
        unelevated
        icon="add"
        label="Erstes Projekt anlegen"
        no-caps
        @click="openCreateDialog"
        style="border-radius: 10px; font-weight: 600; background: #4f46e5; color: #ffffff"
      />
    </div>

    <!-- Karten -->
    <div v-else class="row q-col-gutter-md">
      <div
        v-for="project in projects"
        :key="project.id"
        class="col-12 col-sm-6 col-md-4"
      >
        <q-card
          flat
          clickable
          @click="$router.push(`/projects/${project.id}`)"
          class="project-card"
          :style="{
            border: '1px solid #e2e8f0',
            borderLeft: `3px solid ${statusAccent(project.status)}`,
            borderRadius: '16px',
            background: '#ffffff',
            height: '100%',
          }"
        >
          <q-card-section class="q-pb-none">
            <div class="row items-start no-wrap">
              <div class="col">
                <div
                  class="status-pill q-mb-sm"
                  :style="{
                    color: statusAccent(project.status),
                    background: statusBg(project.status),
                  }"
                >
                  <span
                    class="status-dot"
                    :style="{ background: statusAccent(project.status) }"
                  />
                  {{ statusLabel(project.status) }}
                </div>
                <div class="project-title">
                  {{ project.title }}
                </div>
              </div>
              <q-btn
                flat
                round
                dense
                size="sm"
                icon="more_vert"
                color="grey-5"
                class="q-ml-xs"
                @click.stop
              >
                <q-menu auto-close style="border-radius: 12px">
                  <q-list style="min-width: 160px">
                    <q-item
                      clickable
                      @click="$router.push(`/projects/${project.id}`)"
                    >
                      <q-item-section avatar
                        ><q-icon name="open_in_new" size="20px" color="grey-7"
                      /></q-item-section>
                      <q-item-section>Öffnen</q-item-section>
                    </q-item>
                    <q-separator />
                    <q-item
                      clickable
                      class="text-negative"
                      @click="onDelete(project)"
                    >
                      <q-item-section avatar
                        ><q-icon name="delete" size="20px" color="negative"
                      /></q-item-section>
                      <q-item-section>Löschen</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>
          </q-card-section>

          <q-card-section class="q-pt-sm q-pb-sm">
            <div v-if="project.customer" class="row items-center q-gutter-xs">
              <q-icon
                :name="
                  project.customer.type === 'business' ? 'business' : 'person'
                "
                size="14px"
                color="#94a3b8"
              />
              <span class="project-meta-text">{{
                customerName(project.customer)
              }}</span>
            </div>
            <div v-else class="project-meta-text" style="color: #cbd5e1">
              Kein Kunde zugewiesen
            </div>
            <div
              v-if="project.project_address"
              class="row items-center q-gutter-xs q-mt-xs no-wrap"
            >
              <q-icon name="location_on" size="14px" color="#94a3b8" />
              <span class="project-meta-text ellipsis">{{
                project.project_address
              }}</span>
            </div>
          </q-card-section>

          <q-separator />

          <q-card-section class="q-py-sm row items-center justify-between">
            <div class="row items-center q-gutter-md">
              <div class="stat-chip" title="Angebote">
                <q-icon name="description" size="14px" color="#94a3b8" />
                {{ project.quotes_count ?? 0 }}
              </div>
              <div class="stat-chip" title="Rechnungen">
                <q-icon name="receipt_long" size="14px" color="#94a3b8" />
                {{ project.invoices_count ?? 0 }}
              </div>
            </div>
            <div class="project-date">
              {{ formatDate(project.created_at) }}
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Server-Pagination -->
    <div
      v-if="projectStore.pagination.lastPage > 1"
      class="row justify-center q-mt-lg"
    >
      <q-pagination
        v-model="page"
        :max="projectStore.pagination.lastPage"
        :max-pages="6"
        boundary-numbers
        direction-links
        color="primary"
      />
    </div>
    <div
      v-if="!projectStore.loading && projectStore.pagination.total > 0"
      class="text-center q-mt-sm"
      style="font-size: 12px; color: #94a3b8"
    >
      {{ projects.length }} von {{ projectStore.pagination.total }} Projekten
      · Seite {{ projectStore.pagination.currentPage }} von
      {{ projectStore.pagination.lastPage }}
    </div>

    <!-- Drawer: Neues Projekt -->
    <q-dialog
      v-model="showDialog"
      position="right"
      full-height
      maximized-on-mobile
      persistent
    >
      <q-card
        style="
          width: 460px;
          max-width: 95vw;
          display: flex;
          flex-direction: column;
        "
      >
        <q-card-section
          class="row items-center q-pb-sm"
          style="border-bottom: 1px solid #f1f5f9; flex-shrink: 0"
        >
          <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
            Neues Projekt
          </h6>
          <q-space />
          <q-btn flat round dense icon="close" color="grey-5" v-close-popup />
        </q-card-section>
        <q-card-section class="q-gutter-sm" style="flex: 1; overflow-y: auto">
          <div>
            <div class="ap-field-label">
              <q-icon name="title" size="16px" color="grey-5" class="q-mr-xs" />Titel *
            </div>
            <div class="ap-input-box">
              <q-input
                v-model="form.title"
                borderless
                dense
                autofocus
                placeholder="Projekttitel"
                :rules="[(val) => !!val || 'Pflichtfeld']"
              />
            </div>
          </div>
          <div>
            <div class="ap-field-label">
              <q-icon name="person" size="16px" color="grey-5" class="q-mr-xs" />Kunde (optional)
            </div>
            <div class="ap-input-box">
              <q-select
                v-model="form.customer_id"
                borderless
                dense
                :options="customerOptions"
                option-value="value"
                option-label="label"
                emit-value
                map-options
                clearable
                use-input
                @filter="filterCustomers"
              >
                <template v-slot:no-option
                  ><q-item
                    ><q-item-section style="color: #94a3b8"
                      >Keine Kunden gefunden</q-item-section
                    ></q-item
                  ></template
                >
              </q-select>
            </div>
          </div>
          <div>
            <div class="ap-field-label">
              <q-icon name="location_on" size="16px" color="grey-5" class="q-mr-xs" />Projektadresse (optional)
            </div>
            <div class="ap-input-box">
              <q-input v-model="form.project_address" borderless dense placeholder="Optional" />
            </div>
          </div>
          <div>
            <div class="ap-field-label">
              <q-icon name="notes" size="16px" color="grey-5" class="q-mr-xs" />Beschreibung (optional)
            </div>
            <div class="ap-input-box">
              <q-input
                v-model="form.description"
                borderless
                dense
                type="textarea"
                rows="3"
                placeholder="Optional"
              />
            </div>
          </div>
        </q-card-section>
        <q-card-actions
          align="right"
          class="q-pa-md"
          style="border-top: 1px solid #f1f5f9; flex-shrink: 0"
        >
          <q-btn flat label="Abbrechen" color="grey" v-close-popup />
          <q-btn
            label="Projekt anlegen"
            no-caps
            unelevated
            :loading="projectStore.saving"
            @click="onCreate"
            icon="save"
            style="background: #4f46e5; color: #ffffff"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
<script>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useProjectStore } from "src/stores/projects";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";

export default {
  name: "ProjectsListPage",
  setup() {
    const projectStore = useProjectStore();
    const $q = useQuasar();
    const router = useRouter();

    const search = ref("");
    const statusFilter = ref(null);
    const page = ref(1);
    let searchDebounce = null;

    const statusFilterOptions = [
      { label: "Alle", value: null },
      { label: "Angefragt", value: "angefragt" },
      { label: "Kalkuliert", value: "kalkuliert" },
      { label: "Beauftragt", value: "beauftragt" },
      { label: "In Ausführung", value: "in_ausfuehrung" },
      { label: "Abgeschlossen", value: "abgeschlossen" },
      { label: "Storniert", value: "storniert" },
    ];

    const projects = computed(() => projectStore.projects || []);

    const loadProjects = () => {
      projectStore.fetchProjects({
        page: page.value,
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        per_page: 12,
      });
    };

    watch(page, loadProjects);

    watch([search, statusFilter], () => {
      if (searchDebounce) clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        page.value = 1;
        loadProjects();
      }, 350);
    });

    onMounted(loadProjects);

    const statusColor = (s) =>
      ({
        angefragt: "grey",
        kalkuliert: "blue",
        beauftragt: "indigo",
        in_ausfuehrung: "orange",
        abgeschlossen: "positive",
        storniert: "grey-7",
      })[s] || "grey";
    const statusLabel = (s) =>
      ({
        angefragt: "Angefragt",
        kalkuliert: "Kalkuliert",
        beauftragt: "Beauftragt",
        in_ausfuehrung: "In Ausführung",
        abgeschlossen: "Abgeschlossen",
        storniert: "Storniert",
      })[s] || s;

    // Sehr helle Hintergrundfarbe passend zum Status - für den Status-Pill
    // auf der Karte, dezent statt aufdringlich.
    const statusBg = (s) =>
      ({
        angefragt: "#f8fafc",
        kalkuliert: "#eff6ff",
        beauftragt: "#eef2ff",
        in_ausfuehrung: "#fff7ed",
        abgeschlossen: "#f0fdf4",
        storniert: "#f1f5f9",
      })[s] || "#f8fafc";

    // Kräftige Akzentfarbe passend zum Status - für den linken Kartenrand,
    // den Status-Punkt und den Pill-Text.
    const statusAccent = (s) =>
      ({
        angefragt: "#94a3b8",
        kalkuliert: "#3b82f6",
        beauftragt: "#6366f1",
        in_ausfuehrung: "#f97316",
        abgeschlossen: "#22c55e",
        storniert: "#64748b",
      })[s] || "#94a3b8";

    const formatDate = (val) =>
      val ? new Date(val).toLocaleDateString("de-DE") : "-";

    const customerName = (c) =>
      c.type === "business"
        ? c.company_name || c.contact_person || "Unbekannt"
        : [c.first_name, c.last_name].filter(Boolean).join(" ") || "Unbekannt";

    // ---- Neues Projekt: Kunden-Auswahl ----
    const allCustomers = ref([]);
    const customerOptions = ref([]);

    const loadCustomers = async () => {
      try {
        const r = await api.get("/customers");
        allCustomers.value = r.data.data || r.data;
        customerOptions.value = allCustomers.value.map((c) => ({
          label: customerName(c),
          value: c.id,
        }));
      } catch (e) {
        console.error(e);
      }
    };

    const filterCustomers = (val, update) => {
      update(() => {
        const list = allCustomers.value.map((c) => ({
          label: customerName(c),
          value: c.id,
        }));
        if (!val) {
          customerOptions.value = list;
        } else {
          const s = val.toLowerCase();
          customerOptions.value = list.filter((c) =>
            c.label.toLowerCase().includes(s),
          );
        }
      });
    };

    onMounted(loadCustomers);

    // ---- Dialog: Neues Projekt ----
    const showDialog = ref(false);
    const emptyForm = {
      title: "",
      customer_id: null,
      project_address: "",
      description: "",
    };
    const form = reactive({ ...emptyForm });

    const openCreateDialog = () => {
      Object.assign(form, { ...emptyForm });
      showDialog.value = true;
    };

    const onCreate = async () => {
      if (!form.title) {
        $q.notify({ type: "warning", message: "Titel ist ein Pflichtfeld" });
        return;
      }
      try {
        const project = await projectStore.createProject(form);
        showDialog.value = false;
        $q.notify({ type: "positive", message: "Projekt angelegt" });
        router.push(`/projects/${project.id}`);
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Anlegen",
        });
      }
    };

    const onDelete = (project) => {
      $q.dialog({
        title: "Löschen?",
        message: `Projekt "${project.title}" wirklich löschen?`,
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          await projectStore.deleteProject(project.id);
          $q.notify({ type: "positive", message: "Projekt gelöscht" });
        } catch (e) {
          $q.notify({
            type: "negative",
            message: e.response?.data?.message || "Fehler beim Löschen",
          });
        }
      });
    };

    return {
      projectStore,
      search,
      statusFilter,
      statusFilterOptions,
      projects,
      page,
      statusColor,
      statusLabel,
      statusBg,
      statusAccent,
      formatDate,
      customerName,
      customerOptions,
      filterCustomers,
      showDialog,
      form,
      openCreateDialog,
      onCreate,
      onDelete,
    };
  },
};
</script>

<style scoped>
/* Clean, ruhige Karten: kein Rahmen-Zoom, kein Verschieben beim Hover -
   nur ein minimal stärkerer Schatten und eine dezent dunklere Randfarbe
   als Hinweis auf Klickbarkeit. */
.ap-field-label {
  font-size: 12.5px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
}
.ap-input-box {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #ffffff;
  padding: 0 12px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.ap-input-box:focus-within {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}
.ap-input-box :deep(.q-field__control) {
  min-height: 40px;
}

.project-card {
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  transition: box-shadow 0.15s, border-color 0.15s;
  cursor: pointer;
}
.project-card:hover {
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
  border-color: #cbd5e1 !important;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.01em;
}
.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

.project-title {
  font-size: 15.5px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.project-meta-text {
  font-size: 12.5px;
  color: #64748b;
}

.stat-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12.5px;
  font-weight: 600;
  color: #475569;
}

.project-date {
  font-size: 11.5px;
  color: #94a3b8;
}
</style>
