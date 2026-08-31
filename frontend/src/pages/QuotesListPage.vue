<template>
  <q-page class="q-pa-md q-pa-lg-lg" style="background: #f6f9fc">
    <div
      class="row items-center q-mb-lg"
      :class="$q.screen.lt.sm ? 'column items-stretch q-gutter-sm' : ''"
    >
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Angebote
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          {{ quoteStore.quotes?.length || 0 }} Angebote im Überblick
        </p>
      </div>
      <q-btn
        color="primary"
        icon="add"
        label="Neues Angebot"
        no-caps
        @click="$router.push('/quotes/create')"
        :class="$q.screen.lt.sm ? 'full-width' : ''"
        style="border-radius: 10px; font-weight: 600"
      />
    </div>

    <!-- Filter/Suche -->
    <div class="row q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-6 col-md-4">
        <q-input
          v-model="search"
          filled
          dense
          placeholder="Suchen (Nr., Projekt, Kunde)..."
          bg-color="white"
          style="border-radius: 10px"
        >
          <template v-slot:prepend
            ><q-icon name="search" color="grey-5"
          /></template>
        </q-input>
      </div>
      <div class="col-12 col-sm-6 col-md-3">
        <q-select
          v-model="statusFilter"
          filled
          dense
          bg-color="white"
          :options="statusFilterOptions"
          option-value="value"
          option-label="label"
          emit-value
          map-options
          label="Status"
        />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="quoteStore.loading" class="row q-col-gutter-md">
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
      v-else-if="filteredQuotes.length === 0"
      class="text-center q-pa-xl"
      style="color: #94a3b8"
    >
      <q-icon name="description" size="48px" class="q-mb-md" />
      <div style="font-size: 15px">
        {{
          search || statusFilter
            ? "Keine Angebote gefunden"
            : "Noch keine Angebote. Erstellen Sie Ihr erstes!"
        }}
      </div>
    </div>

    <!-- Karten -->
    <div v-else class="row q-col-gutter-md">
      <div
        v-for="quote in filteredQuotes"
        :key="quote.id"
        class="col-12 col-sm-6 col-md-4"
      >
        <q-card
          flat
          clickable
          @click="$router.push(`/quotes/${quote.id}`)"
          class="quote-card"
          :style="{
            border: '1px solid #cbd5e1',
            borderRadius: '14px',
            background: statusBg(quote.status),
            height: '100%',
          }"
        >
          <q-card-section class="q-pb-none">
            <div class="row items-start no-wrap">
              <div class="col">
                <div class="row items-center q-gutter-xs q-mb-xs">
                  <span style="font-size: 12px; color: #94a3b8">{{
                    quote.quote_number
                  }}</span>
                  <q-badge
                    :color="statusColor(quote.status)"
                    :label="statusLabel(quote.status)"
                    dense
                  />
                </div>
                <div
                  style="
                    font-size: 15px;
                    font-weight: 700;
                    color: #0f172a;
                    line-height: 1.3;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                  "
                >
                  {{ quote.project_title }}
                </div>
              </div>
              <q-btn
                flat
                round
                dense
                icon="more_vert"
                color="grey-6"
                @click.stop
              >
                <q-menu auto-close style="border-radius: 10px">
                  <q-list style="min-width: 160px">
                    <q-item
                      clickable
                      @click="$router.push(`/quotes/${quote.id}`)"
                    >
                      <q-item-section avatar
                        ><q-icon name="edit" size="20px" color="grey-7"
                      /></q-item-section>
                      <q-item-section>Bearbeiten</q-item-section>
                    </q-item>
                    <q-item clickable @click="onDuplicate(quote.id)">
                      <q-item-section avatar
                        ><q-icon name="content_copy" size="20px" color="grey-7"
                      /></q-item-section>
                      <q-item-section>Duplizieren</q-item-section>
                    </q-item>
                    <q-separator />
                    <q-item
                      clickable
                      class="text-negative"
                      @click="onDelete(quote.id)"
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

          <q-card-section class="q-pt-sm">
            <div
              v-if="quote.customer"
              class="row items-center q-gutter-xs q-mb-sm"
              style="font-size: 12.5px; color: #64748b"
            >
              <q-icon
                :name="
                  quote.customer.type === 'business' ? 'business' : 'person'
                "
                size="14px"
                color="grey-5"
              />
              <span>{{
                quote.customer.type === "business"
                  ? quote.customer.company_name
                  : quote.customer.first_name + " " + quote.customer.last_name
              }}</span>
            </div>
            <div
              v-else
              class="q-mb-sm"
              style="font-size: 12.5px; color: #cbd5e1"
            >
              Kein Kunde zugewiesen
            </div>

            <q-separator class="q-mb-sm" />

            <div class="row items-center justify-between">
              <div style="font-size: 11.5px; color: #94a3b8">
                <q-icon name="event" size="13px" class="q-mr-xs" />{{
                  formatDate(quote.created_at)
                }}
                <span v-if="quote.items_count !== undefined">
                  · {{ quote.items_count }} Pos.</span
                >
              </div>
              <div style="font-weight: 800; font-size: 16px; color: #1d4ed8">
                {{ formatPrice(quote.total_gross) }} €
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Server-Pagination -->
    <div
      v-if="quoteStore.pagination.lastPage > 1"
      class="row justify-center q-mt-lg"
    >
      <q-pagination
        v-model="page"
        :max="quoteStore.pagination.lastPage"
        :max-pages="6"
        boundary-numbers
        direction-links
        color="primary"
      />
    </div>
    <div
      v-if="!quoteStore.loading && quoteStore.pagination.total > 0"
      class="text-center q-mt-sm"
      style="font-size: 12px; color: #94a3b8"
    >
      {{ quoteStore.quotes.length }} von {{ quoteStore.pagination.total }}
      Angeboten · Seite {{ quoteStore.pagination.currentPage }} von
      {{ quoteStore.pagination.lastPage }}
    </div>
  </q-page>
</template>
<script>
import { computed, onMounted, ref, watch } from "vue";
import { useQuoteStore } from "src/stores/quotes";
import { useQuasar } from "quasar";

export default {
  name: "QuotesListPage",
  setup() {
    const quoteStore = useQuoteStore();
    const $q = useQuasar();

    const search = ref("");
    const statusFilter = ref(null);
    const page = ref(1);
    let searchDebounce = null;

    const statusFilterOptions = [
      { label: "Alle", value: null },
      { label: "Entwurf", value: "draft" },
      { label: "Gesendet", value: "sent" },
      { label: "Gesehen", value: "viewed" },
      { label: "Angenommen", value: "accepted" },
      { label: "Abgelehnt", value: "rejected" },
      { label: "Abgelaufen", value: "expired" },
    ];

    const filteredQuotes = computed(() => quoteStore.quotes || []);

    const loadQuotes = () => {
      quoteStore.fetchQuotes({
        page: page.value,
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        per_page: 12,
      });
    };

    watch(page, loadQuotes);

    watch([search, statusFilter], () => {
      if (searchDebounce) clearTimeout(searchDebounce);
      searchDebounce = setTimeout(() => {
        page.value = 1;
        loadQuotes();
      }, 350);
    });

    onMounted(loadQuotes);

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

    // Sehr helle Hintergrundfarbe passend zum Status - macht den Status
    // auf einen Blick erkennbar, ohne dass man erst das Badge lesen muss.
    // Bewusst kein Rahmen/Rand, nur die Fläche selbst - wirkt ruhiger.
    const statusBg = (s) =>
      ({
        draft: "#f8fafc",
        sent: "#eff6ff",
        viewed: "#ecfeff",
        accepted: "#f0fdf4",
        rejected: "#fef2f2",
        expired: "#f1f5f9",
      })[s] || "#ffffff";

    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    const formatDate = (val) =>
      val ? new Date(val).toLocaleDateString("de-DE") : "-";

    const onDuplicate = async (id) => {
      await quoteStore.duplicateQuote(id);
      loadQuotes();
      $q.notify({ type: "positive", message: "Angebot dupliziert" });
    };
    const onDelete = async (id) => {
      $q.dialog({
        title: "Löschen?",
        message: "Angebot wirklich löschen?",
        cancel: true,
      }).onOk(async () => {
        await quoteStore.deleteQuote(id);
        $q.notify({ type: "positive", message: "Angebot gelöscht" });
      });
    };

    return {
      quoteStore,
      search,
      statusFilter,
      statusFilterOptions,
      filteredQuotes,
      page,
      statusColor,
      statusLabel,
      statusBg,
      formatPrice,
      formatDate,
      onDuplicate,
      onDelete,
    };
  },
};
</script>

<style scoped>
/* Clean, ruhige Karten: kein Rahmen, kein Zoom/Verschieben beim Hover -
   nur ein minimal stärkerer Schatten als dezenter Hinweis auf Klickbarkeit. */
.quote-card {
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
  transition: box-shadow 0.15s;
  cursor: pointer;
}
.quote-card:hover {
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
}
</style>