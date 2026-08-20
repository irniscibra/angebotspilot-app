<template>
  <q-page class="ap-page">
    <!-- Header -->
    <div class="ap-header">
      <div>
        <div class="ap-eyebrow">{{ todayFormatted }}</div>
        <h1 class="ap-greeting">
          Guten {{ greeting }}, {{ authStore.userName }}
        </h1>
      </div>
           <div class="row items-center q-gutter-sm">
        <q-btn
          flat
          round
          dense
          icon="feedback"
          color="grey-6"
          @click="showFeedbackDialog = true"
        >
          <q-tooltip>Feedback geben</q-tooltip>
        </q-btn>
        <q-btn
          unelevated
          color="primary"
          icon="add"
          :label="$q.screen.gt.xs ? 'Neues Angebot' : ''"
          no-caps
          class="ap-btn-primary"
          @click="$router.push('/quotes/create')"
        />
      </div>
    </div>

     <FeedbackDialog v-model="showFeedbackDialog" />

    <div v-if="loading" class="ap-loading">
      <q-spinner-orbit color="primary" size="46px" />
    </div>

    <div v-else class="ap-content">
      <!-- Stat-Kacheln -->
      <div class="ap-stats-grid">
        <!-- Hero-Kachel: Umsatz -->
        <div class="ap-hero-card">
          <div class="ap-hero-glow" />
          <div class="ap-hero-top">
            <span class="ap-hero-label">Umsatz diesen Monat</span>
            <q-icon name="trending_up" size="18px" class="ap-hero-icon" />
          </div>
          <div class="ap-hero-value">
            {{ formatPrice(data?.stats?.revenue_this_month) }}
            <span class="ap-hero-currency">€</span>
          </div>
          <div
            v-if="revenueTrend !== undefined"
            class="ap-hero-trend"
            :class="revenueTrend >= 0 ? 'is-up' : 'is-down'"
          >
            <q-icon :name="revenueTrend >= 0 ? 'arrow_upward' : 'arrow_downward'" size="13px" />
            {{ Math.abs(revenueTrend) }}% vs. Vormonat
          </div>
        </div>

        <!-- Kleinere Kacheln -->
        <div
          v-for="card in statCards"
          :key="card.label"
          class="ap-stat-card"
        >
          <div class="ap-stat-icon" :style="{ background: card.bg, color: card.iconColor }">
            <q-icon :name="card.icon" size="19px" />
          </div>
          <div class="ap-stat-body">
            <div class="ap-stat-value">{{ card.value }}</div>
            <div class="ap-stat-label">{{ card.label }}</div>
            <div
              v-if="card.trend !== undefined"
              class="ap-stat-trend"
              :class="card.trend >= 0 ? 'is-up' : 'is-down'"
            >
              {{ card.trend >= 0 ? "↑" : "↓" }} {{ Math.abs(card.trend) }}%
            </div>
          </div>
        </div>
      </div>

      <!-- Handlungsbedarf -->
      <div v-if="actions.length > 0" class="ap-section">
        <div class="ap-section-head">
          <div class="ap-section-title">
            <q-icon name="bolt" size="18px" class="ap-section-icon" />
            Handlungsbedarf
            <span class="ap-count-badge">{{ actions.length }}</span>
          </div>
        </div>
        <div class="ap-action-list">
          <div
            v-for="(action, i) in actions"
            :key="i"
            class="ap-action-item"
            @click="$router.push(action.link)"
          >
            <div
              class="ap-action-avatar"
              :style="{ background: actionSoftBg(action), color: actionBorderColor(action) }"
            >
              <q-icon :name="action.icon" size="18px" />
            </div>
            <div class="ap-action-text">
              <div class="ap-action-title">{{ action.title }}</div>
              <div class="ap-action-subtitle">{{ action.subtitle }}</div>
            </div>
            <div v-if="action.value" class="ap-action-value">
              {{ formatPrice(action.value) }} €
            </div>
            <q-icon name="chevron_right" size="18px" class="ap-action-chevron" />
          </div>
        </div>
      </div>

      <!-- Alles erledigt -->
      <div v-else class="ap-empty-good">
        <q-icon name="check_circle" size="26px" class="ap-empty-icon" />
        <div>
          <div class="ap-empty-title">Alles erledigt!</div>
          <div class="ap-empty-subtitle">Keine offenen Aufgaben – Zeit für ein neues Angebot?</div>
        </div>
      </div>

      <div class="ap-main-grid">
        <!-- Linke Spalte -->
        <div class="ap-col-main">
          <!-- Umsatz-Chart -->
          <div class="ap-card">
            <div class="ap-card-title">Auftragsvolumen (letzte 6 Monate)</div>
            <div class="ap-chart">
              <div
                v-for="month in revenueChart"
                :key="month.month"
                class="ap-chart-col"
              >
                <div class="ap-chart-bar-track">
                  <div
                    class="ap-chart-bar"
                    :style="{ height: chartHeight(month.quotes) + 'px' }"
                  >
                    <q-tooltip>{{ formatPrice(month.quotes) }} €</q-tooltip>
                  </div>
                </div>
                <div class="ap-chart-month">{{ month.month_short }}</div>
                <div class="ap-chart-amount">{{ formatCompact(month.quotes) }}</div>
              </div>
            </div>
          </div>

          <!-- Aktivitäten -->
          <div class="ap-card">
            <div class="ap-card-title">Letzte Aktivitäten</div>
            <div v-if="recentActivity.length === 0" class="ap-empty-inline">
              Noch keine Aktivitäten
            </div>
            <div v-else class="ap-timeline">
              <div
                v-for="(act, i) in recentActivity"
                :key="i"
                class="ap-timeline-item"
                @click="$router.push(act.link)"
              >
                <div class="ap-timeline-rail">
                  <div class="ap-timeline-dot" :class="`is-${act.color}`">
                    <q-icon :name="act.icon" size="12px" />
                  </div>
                  <div v-if="i < recentActivity.length - 1" class="ap-timeline-line" />
                </div>
                <div class="ap-timeline-content">
                  <div class="ap-timeline-date">{{ formatRelativeDate(act.date) }}</div>
                  <div class="ap-timeline-title">{{ act.title }}</div>
                  <div class="ap-timeline-subtitle">{{ act.subtitle }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Rechte Spalte -->
        <div class="ap-col-side">
          <!-- Offene Beträge -->
          <div class="ap-card">
            <div class="ap-card-title ap-card-title--muted">Offene Beträge</div>
            <div class="ap-amount-row">
              <div class="ap-amount-head">
                <span>Offene Angebote</span>
                <span class="ap-amount-value is-primary">
                  {{ formatPrice(data?.stats?.open_quotes_value) }} €
                </span>
              </div>
              <div class="ap-progress-track">
                <div class="ap-progress-fill is-primary" style="width: 100%" />
              </div>
            </div>
            <div class="ap-amount-row">
              <div class="ap-amount-head">
                <span>Unbezahlte Rechnungen</span>
                <span
                  class="ap-amount-value"
                  :class="(data?.stats?.unpaid_invoices_value || 0) > 0 ? 'is-danger' : 'is-success'"
                >
                  {{ formatPrice(data?.stats?.unpaid_invoices_value) }} €
                </span>
              </div>
              <div class="ap-progress-track">
                <div
                  class="ap-progress-fill"
                  :class="(data?.stats?.unpaid_invoices_value || 0) > 0 ? 'is-danger' : 'is-success'"
                  style="width: 100%"
                />
              </div>
            </div>
          </div>

          <!-- Schnellzugriff -->
          <div class="ap-card">
            <div class="ap-card-title ap-card-title--muted">Schnellzugriff</div>
            <div class="ap-quick-grid">
              <button class="ap-quick-item" @click="$router.push('/quotes/create')">
                <q-icon name="auto_awesome" size="20px" />
                <span>KI-Angebot</span>
              </button>
              <button class="ap-quick-item" @click="$router.push('/quotes/create')">
                <q-icon name="content_paste" size="20px" />
                <span>Aus Vorlage</span>
              </button>
              <button class="ap-quick-item" @click="$router.push('/invoices')">
                <q-icon name="receipt_long" size="20px" />
                <span>Neue Rechnung</span>
              </button>
              <button class="ap-quick-item" @click="$router.push('/datanorm')">
                <q-icon name="upload_file" size="20px" />
                <span>Datanorm</span>
              </button>
            </div>
          </div>

               <!-- Feedback-Einstellung -->
          <div class="ap-card">
            <div class="row items-center justify-between">
              <div>
                <div class="ap-card-title" style="margin-bottom: 2px;">Feedback-Button</div>
                <div style="font-size: 12px; color: #94a3b8;">
                  Feedback-Icon oben sichtbar
                </div>
              </div>
              <q-toggle
                v-model="feedbackWidgetEnabled"
                color="primary"
                @update:model-value="onToggleFeedbackWidget"
              />
            </div>
          </div>

          <!-- Erfolgsquote -->
          <div class="ap-card ap-card--center">
            <div class="ap-card-title ap-card-title--muted">Erfolgsquote</div>
            <div
              class="ap-ring"
              :style="ringStyle"
            >
              <div class="ap-ring-inner">
                <div class="ap-ring-value" :class="conversionColorClass">
                  {{ data?.stats?.conversion_rate || 0 }}%
                </div>
              </div>
            </div>
            <div class="ap-ring-caption">Angebote → Aufträge</div>
          </div>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "src/stores/auth";
import { api } from "src/boot/axios";
import FeedbackDialog from "src/components/FeedbackDialog.vue";

export default {
    name: "DashboardPage",
  components: { FeedbackDialog },
  setup() {
    const authStore = useAuthStore();
    const loading = ref(true);
    const data = ref(null);
    const showFeedbackDialog = ref(false);
    const feedbackWidgetEnabled = ref(true);

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

      onMounted(async () => {
      await loadDashboard();
      try {
        const res = await api.get("/company");
        feedbackWidgetEnabled.value = res.data.feedback_widget_enabled ?? true;
      } catch (e) {
        console.error("Company load error:", e);
      }
    });

    const onToggleFeedbackWidget = async (val) => {
      try {
        await api.put("/company", { feedback_widget_enabled: val });
      } catch (e) {
        console.error(e);
      }
    };

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

    const revenueTrend = computed(() => {
      const s = data.value?.stats || {};
      if (!s.revenue_last_month) return undefined;
      return Math.round(
        ((s.revenue_this_month - s.revenue_last_month) / s.revenue_last_month) * 100,
      );
    });

    // Stats (ohne Umsatz, der ist jetzt die Hero-Karte)
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
          iconColor: "#4F46E5",
          bg: "#EEF0FF",
          trend: quotesTrend,
        },
        {
          label: "Angebote gesamt",
          value: s.quotes_total || 0,
          icon: "folder",
          iconColor: "#D97706",
          bg: "#FEF3E2",
        },
        {
          label: "Angenommen",
          value: s.quotes_accepted || 0,
          icon: "check_circle",
          iconColor: "#059669",
          bg: "#E7F8F1",
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

    // Action Farben (dezente Pastell-Hintergründe statt knalliger Icon-Boxen)
    const actionColorMap = {
      red: "#DC2626",
      orange: "#D97706",
      blue: "#4F46E5",
      grey: "#64748B",
      green: "#059669",
    };
    const actionSoftBgMap = {
      red: "#FDECEC",
      orange: "#FEF3E2",
      blue: "#EEF0FF",
      grey: "#F1F5F9",
      green: "#E7F8F1",
    };
    const actionBorderColor = (action) => actionColorMap[action.color] || "#94A3B8";
    const actionSoftBg = (action) => actionSoftBgMap[action.color] || "#F1F5F9";

    // Erfolgsquote Ring (CSS conic-gradient statt SVG)
    const conversionRate = computed(() => data.value?.stats?.conversion_rate || 0);
    const conversionColorClass = computed(() => {
      const r = conversionRate.value;
      if (r >= 50) return "is-success";
      if (r >= 25) return "is-warning";
      return "is-danger";
    });
    const ringColor = computed(() => {
      const r = conversionRate.value;
      if (r >= 50) return "#059669";
      if (r >= 25) return "#D97706";
      return "#DC2626";
    });
    const ringStyle = computed(() => {
      const deg = (conversionRate.value / 100) * 360;
      return {
        background: `conic-gradient(${ringColor.value} ${deg}deg, #EEF0F5 ${deg}deg)`,
      };
    });

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
      showFeedbackDialog,
      feedbackWidgetEnabled,
      onToggleFeedbackWidget,
      greeting,
      todayFormatted,
      revenueTrend,
      statCards,
      actions,
      recentActivity,
      revenueChart,
      chartHeight,
      actionBorderColor,
      actionSoftBg,
      conversionColorClass,
      ringStyle,
      formatPrice,
      formatCompact,
      formatRelativeDate,
    };
  },
};
</script>

<style scoped>
.ap-page {
  background: #f7f8fb;
  padding: 20px 20px 40px;
  min-height: 100%;
}
@media (min-width: 1024px) {
  .ap-page {
    padding: 32px 40px 56px;
  }
}

/* Header */
.ap-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}
.ap-eyebrow {
  font-size: 12.5px;
  font-weight: 600;
  color: #8b90a3;
  text-transform: capitalize;
  margin-bottom: 4px;
}
.ap-greeting {
  font-size: clamp(20px, 3vw, 26px);
  font-weight: 700;
  color: #12121f;
  margin: 0;
  letter-spacing: -0.01em;
}
.ap-btn-primary {
  border-radius: 10px;
  font-weight: 600;
  padding: 0 18px;
  height: 42px;
}

.ap-loading {
  display: flex;
  justify-content: center;
  padding: 80px 0;
}

/* Stats grid */
.ap-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 28px;
}
@media (max-width: 1023px) {
  .ap-stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 599px) {
  .ap-stats-grid {
    grid-template-columns: 1fr;
  }
}

.ap-hero-card {
  position: relative;
  overflow: hidden;
  grid-column: span 1;
  background: linear-gradient(155deg, #14152b 0%, #23255a 55%, #33367f 100%);
  border-radius: 18px;
  padding: 20px 22px;
  color: #fff;
  min-height: 130px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.ap-hero-glow {
  position: absolute;
  top: -60px;
  right: -60px;
  width: 180px;
  height: 180px;
  background: radial-gradient(circle, rgba(120, 130, 255, 0.35), transparent 70%);
  pointer-events: none;
}
.ap-hero-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: relative;
  z-index: 1;
}
.ap-hero-label {
  font-size: 12.5px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.7);
}
.ap-hero-icon {
  color: rgba(255, 255, 255, 0.55);
}
.ap-hero-value {
  font-size: 30px;
  font-weight: 800;
  letter-spacing: -0.02em;
  position: relative;
  z-index: 1;
  margin-top: 6px;
}
.ap-hero-currency {
  font-size: 18px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.6);
  margin-left: 2px;
}
.ap-hero-trend {
  font-size: 12px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 4px;
  position: relative;
  z-index: 1;
  margin-top: 8px;
}
.ap-hero-trend.is-up { color: #6ee7b7; }
.ap-hero-trend.is-down { color: #fca5a5; }

.ap-stat-card {
  background: #ffffff;
  border: 1px solid #eceef4;
  border-radius: 16px;
  padding: 18px 18px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.ap-stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ap-stat-value {
  font-size: 21px;
  font-weight: 800;
  color: #12121f;
  letter-spacing: -0.01em;
  line-height: 1.1;
}
.ap-stat-label {
  font-size: 12px;
  color: #8b90a3;
  margin-top: 3px;
  font-weight: 500;
}
.ap-stat-trend {
  font-size: 11px;
  font-weight: 700;
  margin-top: 5px;
}
.ap-stat-trend.is-up { color: #059669; }
.ap-stat-trend.is-down { color: #dc2626; }

/* Section: Handlungsbedarf */
.ap-section {
  margin-bottom: 24px;
}
.ap-section-head {
  margin-bottom: 12px;
}
.ap-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: #12121f;
}
.ap-section-icon {
  color: #d97706;
}
.ap-count-badge {
  background: #fef3e2;
  color: #d97706;
  font-size: 11.5px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
}

.ap-action-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ap-action-item {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #ffffff;
  border: 1px solid #eceef4;
  border-radius: 14px;
  padding: 12px 16px;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.ap-action-item:hover {
  border-color: #d8dcea;
  box-shadow: 0 4px 14px rgba(20, 21, 43, 0.05);
}
.ap-action-avatar {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.ap-action-text {
  flex: 1;
  min-width: 0;
}
.ap-action-title {
  font-size: 13.5px;
  font-weight: 600;
  color: #12121f;
}
.ap-action-subtitle {
  font-size: 12px;
  color: #8b90a3;
  margin-top: 1px;
}
.ap-action-value {
  font-size: 14.5px;
  font-weight: 700;
  color: #4f46e5;
  flex-shrink: 0;
}
.ap-action-chevron {
  color: #c6cad9;
  flex-shrink: 0;
}

.ap-empty-good {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #f0fdf6;
  border: 1px solid #bbf0d4;
  border-radius: 16px;
  padding: 16px 20px;
  margin-bottom: 24px;
}
.ap-empty-icon {
  color: #059669;
}
.ap-empty-title {
  font-size: 14px;
  font-weight: 700;
  color: #056b46;
}
.ap-empty-subtitle {
  font-size: 12.5px;
  color: #38a375;
  margin-top: 1px;
}

/* Main grid */
.ap-main-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
}
@media (max-width: 1023px) {
  .ap-main-grid {
    grid-template-columns: 1fr;
  }
}
.ap-col-main, .ap-col-side {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ap-card {
  background: #ffffff;
  border: 1px solid #eceef4;
  border-radius: 18px;
  padding: 20px 22px;
}
.ap-card--center {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.ap-card-title {
  font-size: 14.5px;
  font-weight: 700;
  color: #12121f;
  margin-bottom: 16px;
}
.ap-card-title--muted {
  font-size: 11.5px;
  font-weight: 700;
  color: #a1a6b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

/* Chart */
.ap-chart {
  display: flex;
  gap: 8px;
  align-items: flex-end;
  height: 190px;
}
.ap-chart-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.ap-chart-bar-track {
  height: 140px;
  display: flex;
  align-items: flex-end;
  width: 100%;
}
.ap-chart-bar {
  width: 100%;
  max-width: 34px;
  margin: 0 auto;
  background: linear-gradient(180deg, #6366f1, #4f46e5);
  border-radius: 8px 8px 3px 3px;
  min-height: 4px;
  transition: height 0.5s ease;
}
.ap-chart-month {
  font-size: 11px;
  color: #8b90a3;
  margin-top: 8px;
  font-weight: 500;
}
.ap-chart-amount {
  font-size: 10.5px;
  color: #4f46e5;
  font-weight: 700;
  margin-top: 2px;
}

/* Timeline */
.ap-empty-inline {
  text-align: center;
  padding: 24px 0;
  color: #a1a6b8;
  font-size: 13px;
}
.ap-timeline-item {
  display: flex;
  gap: 12px;
  cursor: pointer;
}
.ap-timeline-rail {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}
.ap-timeline-dot {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: #94a3b8;
  z-index: 1;
}
.ap-timeline-dot.is-blue { background: #4f46e5; }
.ap-timeline-dot.is-green { background: #059669; }
.ap-timeline-dot.is-orange { background: #d97706; }
.ap-timeline-dot.is-red { background: #dc2626; }
.ap-timeline-dot.is-grey { background: #94a3b8; }
.ap-timeline-line {
  width: 2px;
  flex: 1;
  background: #eceef4;
  margin: 2px 0;
  min-height: 20px;
}
.ap-timeline-content {
  padding-bottom: 18px;
  min-width: 0;
}
.ap-timeline-date {
  font-size: 11px;
  color: #a1a6b8;
  font-weight: 500;
}
.ap-timeline-title {
  font-size: 13px;
  font-weight: 600;
  color: #12121f;
  margin-top: 2px;
}
.ap-timeline-subtitle {
  font-size: 12px;
  color: #8b90a3;
  margin-top: 1px;
}

/* Amount rows */
.ap-amount-row + .ap-amount-row {
  margin-top: 16px;
}
.ap-amount-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: #64748b;
  margin-bottom: 6px;
}
.ap-amount-value {
  font-size: 16px;
  font-weight: 700;
}
.ap-amount-value.is-primary { color: #4f46e5; }
.ap-amount-value.is-danger { color: #dc2626; }
.ap-amount-value.is-success { color: #059669; }
.ap-progress-track {
  height: 5px;
  border-radius: 999px;
  background: #eef0f5;
  overflow: hidden;
}
.ap-progress-fill {
  height: 100%;
  border-radius: 999px;
}
.ap-progress-fill.is-primary { background: #a5b4fc; }
.ap-progress-fill.is-danger { background: #fca5a5; }
.ap-progress-fill.is-success { background: #6ee7b7; }

/* Quick access */
.ap-quick-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.ap-quick-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 8px;
  border-radius: 12px;
  border: 1px solid #eceef4;
  background: #fafbfd;
  color: #4f46e5;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.ap-quick-item:hover {
  background: #eef0ff;
  border-color: #d8dcea;
}

/* Ring */
.ap-ring {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 4px 0 10px;
}
.ap-ring-inner {
  width: 102px;
  height: 102px;
  border-radius: 50%;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ap-ring-value {
  font-size: 24px;
  font-weight: 800;
}
.ap-ring-value.is-success { color: #059669; }
.ap-ring-value.is-warning { color: #d97706; }
.ap-ring-value.is-danger { color: #dc2626; }
.ap-ring-caption {
  font-size: 12px;
  color: #8b90a3;
  font-weight: 500;
}
</style>