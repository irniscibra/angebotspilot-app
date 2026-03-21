<template>
  <q-page class="q-pa-lg" style="background: #f6f9fc">
    <!-- Header -->
    <div class="q-mb-lg">
      <div class="row items-center justify-between q-mb-sm">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Guten {{ greeting }}, {{ authStore.userName }}
        </h5>
        <q-btn
          color="primary"
          icon="add"
          :label="$q.screen.gt.xs ? 'Neues Angebot' : ''"
          no-caps
          @click="$router.push('/quotes/create')"
          style="border-radius: 10px; font-weight: 600; flex-shrink: 0"
        />
      </div>
      <p class="q-mb-none" style="color: #64748b">
        {{ todayFormatted }} · Hier ist Ihr Tagesüberblick
      </p>
    </div>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="50px" />
    </div>

    <div v-else>
      <!-- Statistik-Karten -->
      <div class="row q-col-gutter-md q-mb-lg">
        <div
          class="col-12 col-sm-6 col-lg-3"
          v-for="card in statCards"
          :key="card.label"
        >
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              background: #fff;
            "
          >
            <q-card-section class="q-py-md">
              <div class="row items-center no-wrap">
                <div
                  :style="`width: 44px; height: 44px; border-radius: 10px; background: ${card.bg}; display: flex; align-items: center; justify-content: center; margin-right: 14px;`"
                >
                  <q-icon
                    :name="card.icon"
                    :color="card.iconColor"
                    size="22px"
                  />
                </div>
                <div class="col">
                  <div style="font-size: 12px; color: #94a3b8">
                    {{ card.label }}
                  </div>
                  <div
                    style="font-size: 22px; font-weight: 800; color: #0f172a"
                  >
                    {{ card.value }}
                  </div>
                  <div
                    v-if="card.trend !== undefined"
                    style="font-size: 11px; margin-top: 2px"
                    :style="
                      card.trend >= 0 ? 'color: #16a34a;' : 'color: #ef4444;'
                    "
                  >
                    {{ card.trend >= 0 ? "↑" : "↓" }}
                    {{ Math.abs(card.trend) }}% vs. Vormonat
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Action Items (Handlungsempfehlungen) -->
      <div v-if="actions.length > 0" class="q-mb-lg">
        <div class="row items-center q-mb-md">
          <q-icon
            name="notifications_active"
            color="orange"
            size="22px"
            class="q-mr-sm"
          />
          <span style="font-size: 15px; font-weight: 700; color: #0f172a"
            >Handlungsbedarf</span
          >
          <q-badge :label="actions.length" color="orange" class="q-ml-sm" />
        </div>
        <div class="q-gutter-sm">
          <q-card
            v-for="(action, i) in actions"
            :key="i"
            flat
            clickable
            @click="$router.push(action.link)"
            style="
              border-radius: 10px;
              background: #fff;
              cursor: pointer;
              transition: box-shadow 0.2s;
            "
            :style="`border-left: 4px solid ${actionBorderColor(action)}; border: 1px solid #e2e8f0; border-left: 4px solid ${actionBorderColor(action)};`"
          >
            <q-card-section class="q-py-sm q-px-md">
              <div class="row items-center no-wrap">
                <q-avatar
                  size="36px"
                  :color="action.color + '-1'"
                  :text-color="action.color"
                  :icon="action.icon"
                  class="q-mr-md"
                  style="min-width: 36px"
                />
                <div class="col">
                  <div
                    style="font-size: 13.5px; font-weight: 600; color: #0f172a"
                  >
                    {{ action.title }}
                  </div>
                  <div style="font-size: 12px; color: #94a3b8">
                    {{ action.subtitle }}
                  </div>
                </div>
                <div
                  v-if="action.value"
                  class="text-right"
                  style="min-width: 90px"
                >
                  <div
                    style="font-size: 15px; font-weight: 700; color: #1d4ed8"
                  >
                    {{ formatPrice(action.value) }} €
                  </div>
                </div>
                <q-icon
                  name="chevron_right"
                  color="grey-4"
                  size="20px"
                  class="q-ml-sm"
                />
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Keine Handlungsempfehlungen = alles gut! -->
      <q-card
        v-if="actions.length === 0"
        flat
        class="q-mb-lg"
        style="
          border: 1px solid #bbf7d0;
          border-radius: 12px;
          background: #f0fdf4;
        "
      >
        <q-card-section class="q-py-md">
          <div class="row items-center">
            <q-icon
              name="check_circle"
              color="green"
              size="28px"
              class="q-mr-md"
            />
            <div>
              <div style="font-size: 14px; font-weight: 600; color: #166534">
                Alles erledigt!
              </div>
              <div style="font-size: 12px; color: #4ade80">
                Keine offenen Aufgaben – Zeit für ein neues Angebot?
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <div class="row q-col-gutter-lg">
        <!-- Linke Spalte: Umsatz + Aktivität -->
        <div class="col-12 col-md-8">
          <!-- Umsatz-Chart -->
          <q-card
            flat
            class="q-mb-md"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #fff;
            "
          >
            <q-card-section>
              <div
                style="font-size: 15px; font-weight: 700; color: #0f172a"
                class="q-mb-md"
              >
                Auftragsvolumen (letzte 6 Monate)
              </div>
              <div
                class="row q-gutter-sm"
                style="height: 180px; align-items: flex-end"
              >
                <div
                  v-for="month in revenueChart"
                  :key="month.month"
                  class="col text-center"
                >
                  <div
                    style="
                      position: relative;
                      height: 140px;
                      display: flex;
                      flex-direction: column;
                      justify-content: flex-end;
                      align-items: center;
                    "
                  >
                    <div
                      :style="`width: 100%; max-width: 48px; background: #dbeafe; border-radius: 6px 6px 0 0; height: ${chartHeight(month.quotes)}px; min-height: 4px; transition: height 0.5s;`"
                    >
                      <q-tooltip
                        >Aufträge: {{ formatPrice(month.quotes) }} €</q-tooltip
                      >
                    </div>
                  </div>
                  <div style="font-size: 11px; color: #94a3b8; margin-top: 6px">
                    {{ month.month_short }}
                  </div>
                  <div
                    style="font-size: 10px; color: #64748b; font-weight: 600"
                  >
                    {{ formatCompact(month.quotes) }}
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Letzte Aktivitäten -->
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #fff;
            "
          >
            <q-card-section>
              <div class="row items-center justify-between q-mb-md">
                <span style="font-size: 15px; font-weight: 700; color: #0f172a"
                  >Letzte Aktivitäten</span
                >
              </div>
              <div
                v-if="recentActivity.length === 0"
                class="text-center q-pa-md"
                style="color: #94a3b8"
              >
                Noch keine Aktivitäten
              </div>
              <q-timeline v-else color="grey-4" layout="dense">
                <q-timeline-entry
                  v-for="(act, i) in recentActivity"
                  :key="i"
                  :icon="act.icon"
                  :color="act.color"
                >
                  <template v-slot:subtitle>
                    <span style="font-size: 11px; color: #94a3b8">{{
                      formatRelativeDate(act.date)
                    }}</span>
                  </template>
                  <div
                    clickable
                    @click="$router.push(act.link)"
                    style="cursor: pointer"
                  >
                    <div
                      style="font-size: 13px; font-weight: 500; color: #0f172a"
                    >
                      {{ act.title }}
                    </div>
                    <div style="font-size: 12px; color: #94a3b8">
                      {{ act.subtitle }}
                    </div>
                  </div>
                </q-timeline-entry>
              </q-timeline>
            </q-card-section>
          </q-card>
        </div>

        <!-- Rechte Spalte: Schnellzugriff -->
        <div class="col-12 col-md-4">
          <!-- Offene Werte -->
          <q-card
            flat
            class="q-mb-md"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #fff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 13px;
                  font-weight: 700;
                  color: #94a3b8;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                "
                class="q-mb-md"
              >
                Offene Beträge
              </div>
              <div class="q-gutter-md">
                <div>
                  <div class="row justify-between items-center">
                    <span style="font-size: 13px; color: #64748b"
                      >Offene Angebote</span
                    >
                    <span
                      style="font-size: 16px; font-weight: 700; color: #1d4ed8"
                      >{{ formatPrice(data?.stats?.open_quotes_value) }} €</span
                    >
                  </div>
                  <q-linear-progress
                    :value="1"
                    color="blue-3"
                    class="q-mt-xs"
                    rounded
                    style="height: 4px"
                  />
                </div>
                <div>
                  <div class="row justify-between items-center">
                    <span style="font-size: 13px; color: #64748b"
                      >Unbezahlte Rechnungen</span
                    >
                    <span
                      style="font-size: 16px; font-weight: 700"
                      :style="
                        (data?.stats?.unpaid_invoices_value || 0) > 0
                          ? 'color: #ef4444;'
                          : 'color: #16a34a;'
                      "
                      >{{
                        formatPrice(data?.stats?.unpaid_invoices_value)
                      }}
                      €</span
                    >
                  </div>
                  <q-linear-progress
                    :value="1"
                    :color="
                      (data?.stats?.unpaid_invoices_value || 0) > 0
                        ? 'red-3'
                        : 'green-3'
                    "
                    class="q-mt-xs"
                    rounded
                    style="height: 4px"
                  />
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Schnellzugriff -->
          <q-card
            flat
            class="q-mb-md"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #fff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 13px;
                  font-weight: 700;
                  color: #94a3b8;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                "
                class="q-mb-md"
              >
                Schnellzugriff
              </div>
              <div class="q-gutter-sm">
                <q-btn
                  outline
                  color="primary"
                  icon="auto_awesome"
                  label="KI-Angebot erstellen"
                  class="full-width"
                  no-caps
                  @click="$router.push('/quotes/create')"
                  style="border-radius: 8px"
                />
                <q-btn
                  outline
                  color="teal"
                  icon="content_paste"
                  label="Aus Vorlage erstellen"
                  class="full-width"
                  no-caps
                  @click="$router.push('/quotes/create')"
                  style="border-radius: 8px"
                />
                <q-btn
                  outline
                  color="grey-7"
                  icon="receipt_long"
                  label="Neue Rechnung"
                  class="full-width"
                  no-caps
                  @click="$router.push('/invoices')"
                  style="border-radius: 8px"
                />
                <q-btn
                  outline
                  color="grey-7"
                  icon="upload_file"
                  label="Datanorm importieren"
                  class="full-width"
                  no-caps
                  @click="$router.push('/datanorm')"
                  style="border-radius: 8px"
                />
              </div>
            </q-card-section>
          </q-card>

          <!-- Erfolgsquote -->
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #fff;
            "
          >
            <q-card-section class="text-center">
              <div
                style="
                  font-size: 13px;
                  font-weight: 700;
                  color: #94a3b8;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                "
                class="q-mb-md"
              >
                Erfolgsquote
              </div>
              <div
                style="font-size: 48px; font-weight: 800"
                :style="
                  (data?.stats?.conversion_rate || 0) >= 50
                    ? 'color: #16a34a;'
                    : (data?.stats?.conversion_rate || 0) >= 25
                      ? 'color: #eab308;'
                      : 'color: #ef4444;'
                "
              >
                {{ data?.stats?.conversion_rate || 0 }}%
              </div>
              <div style="font-size: 12px; color: #94a3b8">
                Angebote → Aufträge
              </div>
              <q-linear-progress
                :value="(data?.stats?.conversion_rate || 0) / 100"
                :color="
                  (data?.stats?.conversion_rate || 0) >= 50
                    ? 'green'
                    : (data?.stats?.conversion_rate || 0) >= 25
                      ? 'amber'
                      : 'red'
                "
                class="q-mt-md"
                rounded
                style="height: 8px"
              />
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "src/stores/auth";
import { api } from "src/boot/axios";

export default {
  name: "DashboardPage",
  setup() {
    const authStore = useAuthStore();
    const loading = ref(true);
    const data = ref(null);

    const loadDashboard = async () => {
      loading.value = true;
      try {
        const res = await api.get("/dashboard");
        data.value = res.data;
      } catch (e) {
        console.error("Dashboard load error:", e);
      } finally {
        loading.value = false;
      }
    };

    onMounted(loadDashboard);

    // Greeting basierend auf Tageszeit
    const greeting = computed(() => {
      const h = new Date().getHours();
      if (h < 12) return "Morgen";
      if (h < 18) return "Nachmittag";
      return "Abend";
    });

    const todayFormatted = computed(() => {
      return new Date().toLocaleDateString("de-DE", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
      });
    });

    // Stats
    const statCards = computed(() => {
      const s = data.value?.stats || {};
      const quotesTrend =
        s.quotes_last_month > 0
          ? Math.round(
              ((s.quotes_this_month - s.quotes_last_month) /
                s.quotes_last_month) *
                100,
            )
          : undefined;

      return [
        {
          label: "Angebote diesen Monat",
          value: s.quotes_this_month || 0,
          icon: "description",
          iconColor: "primary",
          bg: "#eff6ff",
          trend: quotesTrend,
        },
        {
          label: "Umsatz diesen Monat",
          value: formatPrice(s.revenue_this_month) + " €",
          icon: "euro",
          iconColor: "positive",
          bg: "#f0fdf4",
          trend:
            s.revenue_last_month > 0
              ? Math.round(
                  ((s.revenue_this_month - s.revenue_last_month) /
                    s.revenue_last_month) *
                    100,
                )
              : undefined,
        },
        {
          label: "Angebote gesamt",
          value: s.quotes_total || 0,
          icon: "folder",
          iconColor: "orange",
          bg: "#fff7ed",
        },
        {
          label: "Angenommen",
          value: s.quotes_accepted || 0,
          icon: "check_circle",
          iconColor: "positive",
          bg: "#f0fdf4",
        },
      ];
    });

    const actions = computed(() => data.value?.actions || []);
    const recentActivity = computed(() => data.value?.recent_activity || []);
    const revenueChart = computed(() => data.value?.revenue_chart || []);

    // Chart Höhe berechnen
    const maxChartValue = computed(() => {
      const vals = (data.value?.revenue_chart || []).map((m) => m.quotes || 0);
      return Math.max(...vals, 1);
    });

    const chartHeight = (val) => {
      return Math.max((val / maxChartValue.value) * 130, 4);
    };

    // Action Border Color
    const actionBorderColor = (action) => {
      const map = {
        red: "#ef4444",
        orange: "#f59e0b",
        blue: "#3b82f6",
        grey: "#94a3b8",
        green: "#22c55e",
      };
      return map[action.color] || "#e2e8f0";
    };

    // Formatierung
    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

    const formatCompact = (val) => {
      const num = Number(val || 0);
      if (num >= 1000) return Math.round(num / 1000) + "k";
      return Math.round(num) + "";
    };

    const formatRelativeDate = (date) => {
      if (!date) return "";
      const d = new Date(date);
      const now = new Date();
      const diffMs = now - d;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);

      if (diffMins < 60) return `vor ${diffMins} Minuten`;
      if (diffHours < 24) return `vor ${diffHours} Stunden`;
      if (diffDays === 1) return "Gestern";
      if (diffDays < 7) return `vor ${diffDays} Tagen`;
      return d.toLocaleDateString("de-DE");
    };

    return {
      authStore,
      loading,
      data,
      greeting,
      todayFormatted,
      statCards,
      actions,
      recentActivity,
      revenueChart,
      chartHeight,
      actionBorderColor,
      formatPrice,
      formatCompact,
      formatRelativeDate,
    };
  },
};
</script>
