<template>
  <q-page class="q-pa-lg">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Dashboard
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          Willkommen zurück, {{ authStore.userName }}
        </p>
      </div>
      <q-btn
        color="primary"
        icon="add"
        label="Neues Angebot"
        no-caps
        @click="$router.push('/quotes/create')"
        style="border-radius: 10px; font-weight: 600"
      />
    </div>
    <div class="row q-col-gutter-md q-mb-lg">
      <div
        class="col-12 col-sm-6 col-md-3"
        v-for="stat in statCards"
        :key="stat.label"
      >
        <q-card
          flat
          style="
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
          "
        >
          <q-card-section>
            <div class="row items-center no-wrap">
              <div
                :style="`width: 42px; height: 42px; border-radius: 10px; background: ${stat.bg}; display: flex; align-items: center; justify-content: center; margin-right: 12px;`"
              >
                <q-icon :name="stat.icon" :color="stat.iconColor" size="20px" />
              </div>
              <div>
                <div style="font-size: 12px; color: #64748b">
                  {{ stat.label }}
                </div>
                <div style="font-size: 24px; font-weight: 800; color: #0f172a">
                  {{ stat.value }}
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
    <q-card
      flat
      style="
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #ffffff;
      "
    >
      <q-card-section>
        <div class="row items-center justify-between q-mb-md">
          <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
            Letzte Angebote
          </h6>
          <q-btn
            flat
            color="primary"
            label="Alle anzeigen"
            no-caps
            dense
            @click="$router.push('/quotes')"
          />
        </div>
        <q-table
          :rows="quoteStore.quotes.slice(0, 10)"
          :columns="columns"
          row-key="id"
          flat
          :loading="quoteStore.loading"
          no-data-label="Noch keine Angebote erstellt"
          hide-pagination
          @row-click="(evt, row) => $router.push(`/quotes/${row.id}`)"
          class="cursor-pointer"
        >
          <template v-slot:body-cell-status="props">
            <q-td :props="props"
              ><q-badge
                :color="statusColor(props.value)"
                :label="statusLabel(props.value)"
            /></q-td>
          </template>
          <template v-slot:body-cell-total_gross="props">
            <q-td :props="props"
              ><span style="font-weight: 600; color: #0f172a"
                >{{ formatPrice(props.value) }} €</span
              ></q-td
            >
          </template>
        </q-table>
      </q-card-section>
    </q-card>
  </q-page>
</template>
<script>
import { computed, onMounted } from "vue";
import { useAuthStore } from "src/stores/auth";
import { useQuoteStore } from "src/stores/quotes";
export default {
  name: "DashboardPage",
  setup() {
    const authStore = useAuthStore();
    const quoteStore = useQuoteStore();
    onMounted(async () => {
      await Promise.all([quoteStore.fetchQuotes(), quoteStore.fetchStats()]);
    });
    const statCards = computed(() => [
      {
        label: "Angebote gesamt",
        value: quoteStore.stats?.quotes_total || 0,
        icon: "description",
        iconColor: "primary",
        bg: "#eff6ff",
      },
      {
        label: "Entwürfe",
        value: quoteStore.stats?.quotes_draft || 0,
        icon: "edit_note",
        iconColor: "orange",
        bg: "#fff7ed",
      },
      {
        label: "Angenommen",
        value: quoteStore.stats?.quotes_accepted || 0,
        icon: "check_circle",
        iconColor: "positive",
        bg: "#f0fdf4",
      },
      {
        label: "Erfolgsquote",
        value: (quoteStore.stats?.conversion_rate || 0) + "%",
        icon: "trending_up",
        iconColor: "info",
        bg: "#f0f9ff",
      },
    ]);
    const columns = [
      {
        name: "quote_number",
        label: "Nr.",
        field: "quote_number",
        align: "left",
        sortable: true,
      },
      {
        name: "project_title",
        label: "Projekt",
        field: "project_title",
        align: "left",
        sortable: true,
      },
      { name: "status", label: "Status", field: "status", align: "center" },
      {
        name: "total_gross",
        label: "Summe",
        field: "total_gross",
        align: "right",
        sortable: true,
      },
      {
        name: "created_at",
        label: "Erstellt",
        field: "created_at",
        align: "right",
        format: (val) => new Date(val).toLocaleDateString("de-DE"),
      },
    ];
    const statusColor = (s) =>
      ({
        draft: "grey",
        sent: "blue",
        viewed: "info",
        accepted: "positive",
        rejected: "negative",
        expired: "grey-7",
      })[s] || "grey";
    const statusLabel = (s) =>
      ({
        draft: "Entwurf",
        sent: "Gesendet",
        viewed: "Gesehen",
        accepted: "Angenommen",
        rejected: "Abgelehnt",
        expired: "Abgelaufen",
      })[s] || s;
    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    return {
      authStore,
      quoteStore,
      statCards,
      columns,
      statusColor,
      statusLabel,
      formatPrice,
    };
  },
};
</script>
