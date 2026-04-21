<template>
  <!-- Trigger Button (wird in QuoteDetailPage eingebunden) -->

  <!-- Dialog -->
  <q-dialog v-model="open" position="right" full-height>
    <q-card
      style="
        width: 480px;
        max-width: 95vw;
        border-radius: 16px 0 0 16px;
        height: 100%;
      "
    >
      <!-- Header -->
      <div
        style="
          background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
          padding: 20px 20px 16px 20px;
          border-radius: 16px 0 0 0;
        "
      >
        <div class="row items-center q-mb-xs">
          <q-icon name="analytics" color="white" size="22px" class="q-mr-sm" />
          <span style="font-size: 16px; font-weight: 700; color: #ffffff"
            >KI-Preisanalyse</span
          >
          <q-space />
          <q-btn
            flat
            round
            dense
            icon="close"
            color="white"
            size="sm"
            @click="open = false"
          />
        </div>
        <div style="font-size: 11px; color: #94a3b8">
          Vergleich mit deutschen Marktpreisen
        </div>
      </div>

      <!-- PLZ Eingabe -->
      <div
        style="
          padding: 16px 20px;
          background: #f8fafc;
          border-bottom: 1px solid #e2e8f0;
        "
      >
        <div class="row items-center q-gutter-sm">
          <q-input
            v-model="plz"
            filled
            dense
            label="PLZ für regionale Preise"
            placeholder="z.B. 80331"
            maxlength="5"
            style="flex: 1"
            :disable="loading"
          >
            <template v-slot:prepend>
              <q-icon name="location_on" color="grey-5" size="18px" />
            </template>
          </q-input>
          <q-btn
            color="primary"
            :label="loading ? 'Analysiere...' : 'Analysieren'"
            no-caps
            :loading="loading"
            @click="onAnalyze"
            style="border-radius: 10px; font-weight: 600"
            icon="auto_awesome"
          />
        </div>
        <div
          v-if="result"
          style="font-size: 11px; color: #64748b; margin-top: 6px"
        >
          📍 {{ result.region }}
        </div>
      </div>

      <q-scroll-area style="flex: 1; height: calc(100% - 160px)">
        <!-- Loading -->
        <div v-if="loading" class="flex flex-center q-pa-xl column">
          <q-spinner-orbit color="primary" size="50px" class="q-mb-md" />
          <div style="font-size: 14px; font-weight: 600; color: #0f172a">
            KI analysiert Preise...
          </div>
          <div style="font-size: 12px; color: #64748b; margin-top: 4px">
            Vergleich mit {{ itemCount }} Positionen
          </div>
        </div>

        <!-- Ergebnis -->
        <div v-else-if="result" class="q-pa-md">
          <!-- Gesamtbewertung -->
          <div
            class="q-mb-lg q-pa-md"
            style="border-radius: 12px; border: 1.5px solid"
            :style="gesamtStyle"
          >
            <div class="row items-center q-gutter-sm q-mb-xs">
              <q-icon :name="gesamtIcon" size="28px" :color="gesamtColor" />
              <div>
                <div
                  style="font-size: 15px; font-weight: 700"
                  :style="`color: ${gesamtTextColor}`"
                >
                  {{ gesamtLabel }}
                </div>
                <div style="font-size: 11px; color: #64748b">
                  Ø Abweichung: {{ result.avg_abweichung > 0 ? "+" : ""
                  }}{{ result.avg_abweichung }}% vom Marktdurchschnitt
                </div>
              </div>
            </div>
            <div
              style="font-size: 12px; line-height: 1.5"
              :style="`color: ${gesamtTextColor}`"
            >
              {{ gesamtTip }}
            </div>
          </div>

          <!-- Positions-Liste -->
          <div
            style="
              font-size: 11px;
              font-weight: 700;
              text-transform: uppercase;
              letter-spacing: 0.8px;
              color: #94a3b8;
            "
            class="q-mb-sm"
          >
            Positionen ({{ result.items.length }})
          </div>

          <div v-for="item in enrichedItems" :key="item.id" class="q-mb-xs">
            <q-card
              flat
              style="
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                overflow: hidden;
              "
            >
              <!-- Farbiger Indikator links -->
              <div class="row no-wrap">
                <div
                  style="width: 4px; flex-shrink: 0"
                  :style="`background: ${item.color}`"
                ></div>
                <div style="flex: 1; padding: 10px 12px">
                  <div class="row items-start justify-between">
                    <div class="col" style="padding-right: 8px">
                      <div
                        style="
                          font-size: 12px;
                          font-weight: 600;
                          color: #0f172a;
                          line-height: 1.3;
                        "
                      >
                        {{ item.titel }}
                      </div>
                      <div
                        style="font-size: 10px; color: #94a3b8; margin-top: 2px"
                      >
                        {{ item.menge }} {{ item.einheit }} · {{ item.typ }}
                      </div>
                    </div>
                    <div style="text-align: right; flex-shrink: 0">
                      <q-badge
                        :style="`background: ${item.bgColor}; color: ${item.color};`"
                        style="
                          font-size: 9px;
                          font-weight: 700;
                          padding: 3px 7px;
                          border-radius: 6px;
                        "
                      >
                        {{ item.badgeLabel }}
                      </q-badge>
                    </div>
                  </div>

                  <!-- Preisvergleich -->
                  <div class="row items-center q-mt-sm q-gutter-xs">
                    <div style="font-size: 11px; color: #64748b">
                      Dein Preis:
                      <strong style="color: #0f172a"
                        >{{ formatPrice(item.einzelpreis) }} €</strong
                      >
                    </div>
                    <q-icon name="arrow_forward" size="12px" color="grey-4" />
                    <div style="font-size: 11px; color: #64748b">
                      Markt:
                      <strong
                        >{{ formatPrice(item.estimated_min) }}–{{
                          formatPrice(item.estimated_max)
                        }}
                        €</strong
                      >
                    </div>
                  </div>

                  <!-- Tip -->
                  <div
                    v-if="item.tip"
                    style="
                      font-size: 10px;
                      color: #64748b;
                      margin-top: 5px;
                      font-style: italic;
                    "
                  >
                    💡 {{ item.tip }}
                  </div>
                </div>
              </div>
            </q-card>
          </div>

          <!-- Zusammenfassung -->
          <div
            class="q-mt-lg q-pa-md"
            style="
              background: #f8fafc;
              border-radius: 10px;
              border: 1px solid #e2e8f0;
            "
          >
            <div
              style="
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                color: #94a3b8;
              "
              class="q-mb-sm"
            >
              Zusammenfassung
            </div>
            <div class="row q-gutter-sm">
              <div
                v-for="stat in stats"
                :key="stat.label"
                class="col text-center"
              >
                <div
                  style="font-size: 20px; font-weight: 800"
                  :style="`color: ${stat.color}`"
                >
                  {{ stat.count }}
                </div>
                <div style="font-size: 10px; color: #64748b">
                  {{ stat.label }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Initial State -->
        <div v-else class="text-center q-pa-xl">
          <q-icon
            name="price_check"
            size="64px"
            color="grey-3"
            class="q-mb-md"
          />
          <div style="font-size: 15px; font-weight: 600; color: #0f172a">
            Preise analysieren
          </div>
          <div
            style="
              font-size: 13px;
              color: #64748b;
              margin-top: 6px;
              line-height: 1.6;
            "
          >
            Gib optional deine PLZ ein und klicke auf "Analysieren" – die KI
            vergleicht alle Positionen mit deutschen Marktpreisen.
          </div>
        </div>
      </q-scroll-area>
    </q-card>
  </q-dialog>
</template>

<script>
import { ref, computed } from "vue";
import { api } from "src/boot/axios";
import { useQuasar } from "quasar";

export default {
  name: "PriceCheckDialog",
  props: {
    modelValue: Boolean,
    quote: Object,
  },
  emits: ["update:modelValue"],
  setup(props, { emit }) {
    const $q = useQuasar();
    const open = computed({
      get: () => props.modelValue,
      set: (val) => emit("update:modelValue", val),
    });

    const plz = ref("");
    const loading = ref(false);
    const result = ref(null);

    const itemCount = computed(() => props.quote?.items?.length || 0);

    const colorMap = {
      zu_guenstig: {
        color: "#dc2626",
        bg: "#fef2f2",
        label: "🔴 Zu günstig",
        badge: "↓↓ Zu günstig",
      },
      guenstig: {
        color: "#f97316",
        bg: "#fff7ed",
        label: "🟠 Günstig",
        badge: "↓ Günstig",
      },
      marktgerecht: {
        color: "#16a34a",
        bg: "#f0fdf4",
        label: "✅ Marktgerecht",
        badge: "✓ OK",
      },
      gehoben: {
        color: "#0284c7",
        bg: "#eff6ff",
        label: "🔵 Gehoben",
        badge: "↑ Gehoben",
      },
      zu_teuer: {
        color: "#7c3aed",
        bg: "#faf5ff",
        label: "🔴 Zu teuer",
        badge: "↑↑ Zu teuer",
      },
    };

  const enrichedItems = computed(() => {
  if (!result.value?.items) return [];
  return result.value.items.map(item => {
    // Quote Item anhand ID finden
    const quoteItem = props.quote?.items?.find(i => i.id === item.id);
    const c = colorMap[item.bewertung] || colorMap.marktgerecht;
    return {
      ...item,
      // Fehlende Felder aus Quote Item nehmen
      titel: item.titel || quoteItem?.title || '',
      einzelpreis: item.einzelpreis || quoteItem?.unit_price || 0,
      menge: item.menge || quoteItem?.quantity || 0,
      einheit: item.einheit || quoteItem?.unit || '',
      typ: item.typ || (quoteItem?.type === 'labor' ? 'Arbeit' : 'Material'),
      color: c.color,
      bgColor: c.bg,
      badgeLabel: c.label,
    };
  });
});

    const gesamtBewertung = computed(
      () => result.value?.gesamt_bewertung || "marktgerecht",
    );

    const gesamtColor = computed(
      () =>
        ({
          zu_guenstig: "red",
          guenstig: "orange",
          marktgerecht: "green",
          gehoben: "blue",
          zu_teuer: "purple",
        })[gesamtBewertung.value] || "green",
    );

    const gesamtTextColor = computed(
      () => colorMap[gesamtBewertung.value]?.color || "#16a34a",
    );

    const gesamtStyle = computed(() => {
      const c = colorMap[gesamtBewertung.value] || colorMap.marktgerecht;
      return `background: ${c.bg}; border-color: ${c.color}40;`;
    });

    const gesamtIcon = computed(
      () =>
        ({
          zu_guenstig: "trending_down",
          guenstig: "south",
          marktgerecht: "check_circle",
          gehoben: "north",
          zu_teuer: "trending_up",
        })[gesamtBewertung.value] || "check_circle",
    );

    const gesamtLabel = computed(
      () =>
        ({
          zu_guenstig: "Du bist zu günstig!",
          guenstig: "Dein Angebot ist günstig",
          marktgerecht: "Preise sind marktgerecht ✓",
          gehoben: "Dein Angebot ist gehoben",
          zu_teuer: "Du bist zu teuer!",
        })[gesamtBewertung.value] || "Marktgerecht",
    );

    const gesamtTip = computed(
      () =>
        ({
          zu_guenstig:
            "Deine Preise liegen deutlich unter dem Marktdurchschnitt. Du lässt Geld auf dem Tisch – erhöhe deine Preise!",
          guenstig:
            "Deine Preise sind etwas günstiger als der Markt. Gute Wettbewerbsposition, aber Spielraum nach oben vorhanden.",
          marktgerecht:
            "Deine Preise entsprechen dem Marktdurchschnitt. Du bist wettbewerbsfähig und fair kalkuliert.",
          gehoben:
            "Deine Preise liegen über dem Durchschnitt. Das ist okay wenn du Premium-Qualität oder schnelle Verfügbarkeit bietest.",
          zu_teuer:
            "Deine Preise sind deutlich über dem Markt. Prüfe ob du Begründungen für den Aufpreis hast, sonst riskierst du den Auftrag.",
        })[gesamtBewertung.value] || "",
    );

    const stats = computed(() => {
      if (!result.value?.items) return [];
      const counts = {
        zu_guenstig: 0,
        guenstig: 0,
        marktgerecht: 0,
        gehoben: 0,
        zu_teuer: 0,
      };
      result.value.items.forEach((i) => {
        if (counts[i.bewertung] !== undefined) counts[i.bewertung]++;
      });
      return [
        { label: "Zu günstig", count: counts.zu_guenstig, color: "#dc2626" },
        { label: "Günstig", count: counts.guenstig, color: "#f97316" },
        { label: "OK", count: counts.marktgerecht, color: "#16a34a" },
        { label: "Gehoben", count: counts.gehoben, color: "#0284c7" },
        { label: "Zu teuer", count: counts.zu_teuer, color: "#7c3aed" },
      ];
    });

    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

    const onAnalyze = async () => {
      if (!props.quote?.id) return;
      loading.value = true;
      result.value = null;
      try {
        const res = await api.post(`/quotes/${props.quote.id}/price-check`, {
          plz: plz.value,
        });
        result.value = res.data;
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.error || "Fehler bei der Analyse",
        });
      } finally {
        loading.value = false;
      }
    };

    return {
      open,
      plz,
      loading,
      result,
      itemCount,
      enrichedItems,
      gesamtBewertung,
      gesamtColor,
      gesamtTextColor,
      gesamtStyle,
      gesamtIcon,
      gesamtLabel,
      gesamtTip,
      stats,
      formatPrice,
      onAnalyze,
    };
  },
};
</script>
