<template>
  <q-page class="q-pa-lg" style="background: #f6f9fc">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Rechnungen
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          Rechnungen erstellen, verwalten und versenden
        </p>
      </div>
      <q-btn
        color="primary"
        icon="add"
        label="Neue Rechnung"
        no-caps
        @click="showCreateDialog = true"
      />
    </div>

    <!-- Filter -->
    <div class="row q-gutter-sm q-mb-md">
      <q-input
        v-model="search"
        filled
        dense
        placeholder="Suchen..."
        style="width: 250px; background: #fff"
        @update:model-value="loadInvoices"
      >
        <template v-slot:prepend
          ><q-icon name="search" color="grey-5"
        /></template>
      </q-input>
      <q-btn-toggle
        v-model="statusFilter"
        no-caps
        rounded
        toggle-color="primary"
        :options="statusFilterOptions"
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="40px" />
    </div>

    <!-- Leerer State -->
    <q-card
      v-else-if="invoices.length === 0"
      flat
      class="text-center q-pa-xl"
      style="border: 2px dashed #e2e8f0; border-radius: 14px; background: #fff"
    >
      <q-icon name="receipt_long" size="48px" color="grey-4" />
      <h6 class="q-mt-md q-mb-xs" style="color: #64748b">
        Noch keine Rechnungen
      </h6>
      <p style="color: #94a3b8">
        Erstelle deine erste Rechnung aus einem bestehenden Angebot.
      </p>
    </q-card>

    <!-- Rechnungsliste -->
    <div v-else class="q-gutter-sm">
      <q-card
        v-for="inv in invoices"
        :key="inv.id"
        flat
        clickable
        @click="$router.push(`/invoices/${inv.id}`)"
        style="border: 1px solid #e2e8f0; border-radius: 12px; background: #fff"
      >
        <q-card-section class="q-py-md">
          <div class="row items-center">
            <q-avatar
              size="42px"
              :color="invoiceStatusColor(inv.status)"
              text-color="white"
              icon="receipt_long"
              class="q-mr-md"
            />
            <div class="col">
              <div class="row items-center q-gutter-sm">
                <span
                  style="font-size: 15px; font-weight: 600; color: #0f172a"
                  >{{ inv.project_title }}</span
                >
                <q-badge
                  :color="invoiceStatusColor(inv.status)"
                  :label="invoiceStatusLabel(inv.status)"
                  dense
                />
                <q-badge
                  v-if="inv.type !== 'standard'"
                  outline
                  :color="inv.type === 'partial' ? 'orange' : 'purple'"
                  :label="invoiceTypeLabel(inv.type)"
                  dense
                />
              </div>
              <div style="font-size: 12px; color: #64748b; margin-top: 2px">
                {{ inv.invoice_number }}
                <span v-if="inv.customer">
                  ·
                  {{
                    inv.customer.type === "business"
                      ? inv.customer.company_name
                      : inv.customer.first_name + " " + inv.customer.last_name
                  }}</span
                >
                · {{ inv.items_count }} Positionen
              </div>
            </div>
            <div class="text-right">
              <div style="font-size: 18px; font-weight: 700; color: #1d4ed8">
                {{ formatPrice(inv.total_gross) }} €
              </div>
              <div style="font-size: 11px; color: #94a3b8">
                {{ formatDate(inv.created_at) }}
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </div>

    <!-- Neue Rechnung Dialog -->
    <q-dialog v-model="showCreateDialog">
      <q-card style="min-width: 500px; border-radius: 16px">
        <q-card-section>
          <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
            Neue Rechnung erstellen
          </h6>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <!-- Tab: aus Angebot oder leer -->
          <q-tabs
            v-model="createTab"
            dense
            no-caps
            class="q-mb-md"
            active-color="primary"
            indicator-color="primary"
          >
            <q-tab name="from_quote" label="Aus Angebot" icon="description" />
            <q-tab name="empty" label="Leere Rechnung" icon="add" />
          </q-tabs>

          <!-- Aus Angebot -->
          <div v-if="createTab === 'from_quote'">
            <q-input
              v-model="quoteSearch"
              filled
              dense
              placeholder="Angebot suchen..."
              class="q-mb-sm"
            >
              <template v-slot:prepend
                ><q-icon name="search" color="grey-5"
              /></template>
            </q-input>
            <div v-if="quotesLoading" class="flex flex-center q-pa-md">
              <q-spinner size="24px" color="primary" />
            </div>
            <div
              v-else
              style="max-height: 300px; overflow-y: auto"
              class="q-gutter-xs"
            >
              <q-item
                v-for="q in filteredQuotes"
                :key="q.id"
                clickable
                @click="selectedQuote = q"
                :class="selectedQuote?.id === q.id ? 'bg-blue-1' : ''"
                style="border: 1px solid #f1f5f9; border-radius: 8px"
              >
                <q-item-section>
                  <q-item-label style="font-weight: 600">{{
                    q.project_title
                  }}</q-item-label>
                  <q-item-label caption
                    >{{ q.quote_number }} · {{ formatPrice(q.total_gross) }} € ·
                    {{ q.items_count }} Pos.</q-item-label
                  >
                </q-item-section>
                <q-item-section side v-if="selectedQuote?.id === q.id">
                  <q-icon name="check_circle" color="primary" />
                </q-item-section>
              </q-item>
              <div
                v-if="filteredQuotes.length === 0"
                class="text-center q-pa-md"
                style="color: #94a3b8"
              >
                Keine Angebote gefunden
              </div>
            </div>

            <div v-if="selectedQuote" class="q-mt-md">
              <q-select
                v-model="invoiceType"
                filled
                dense
                label="Rechnungstyp"
                :options="typeOptions"
                emit-value
                map-options
                class="q-mb-sm"
              />
              <q-btn
                color="primary"
                icon="receipt_long"
                label="Rechnung erstellen"
                class="full-width"
                size="lg"
                no-caps
                @click="onCreateFromQuote"
                :loading="creating"
                :disable="!selectedQuote"
              />
            </div>
          </div>

          <!-- Leere Rechnung -->
          <div v-if="createTab === 'empty'">
            <q-input
              v-model="emptyTitle"
              filled
              dense
              label="Projekttitel *"
              class="q-mb-md"
              placeholder="z.B. Wartungsarbeiten März 2026"
            />
            <q-btn
              color="primary"
              icon="add"
              label="Leere Rechnung erstellen"
              class="full-width"
              size="lg"
              no-caps
              @click="onCreateEmpty"
              :loading="creating"
            />
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";

export default {
  name: "InvoicesListPage",
  setup() {
    const router = useRouter();
    const $q = useQuasar();

    const loading = ref(false);
    const invoices = ref([]);
    const search = ref("");
    const statusFilter = ref("all");
    const showCreateDialog = ref(false);
    const createTab = ref("from_quote");
    const creating = ref(false);

    // Aus Angebot
    const allQuotes = ref([]);
    const quotesLoading = ref(false);
    const quoteSearch = ref("");
    const selectedQuote = ref(null);
    const invoiceType = ref("standard");

    // Leere Rechnung
    const emptyTitle = ref("");

    const statusFilterOptions = [
      { label: "Alle", value: "all" },
      { label: "Entwurf", value: "draft" },
      { label: "Versendet", value: "sent" },
      { label: "Bezahlt", value: "paid" },
      { label: "Storniert", value: "cancelled" },
    ];

    const typeOptions = [
      { label: "Standard-Rechnung", value: "standard" },
      { label: "Abschlagsrechnung", value: "partial" },
      { label: "Schlussrechnung", value: "final" },
    ];

    const loadInvoices = async () => {
      loading.value = true;
      try {
        const params = {};
        if (search.value) params.search = search.value;
        if (statusFilter.value !== "all") params.status = statusFilter.value;
        const res = await api.get("/invoices", { params });
        invoices.value = res.data.data || res.data;
      } catch (e) {
        console.error(e);
      } finally {
        loading.value = false;
      }
    };

    const loadQuotes = async () => {
      quotesLoading.value = true;
      try {
        const res = await api.get("/quotes");
        allQuotes.value = (res.data.data || res.data).filter(
          (q) =>
            q.status === "accepted" ||
            q.status === "sent" ||
            q.status === "draft",
        );
      } catch (e) {
        console.error(e);
      } finally {
        quotesLoading.value = false;
      }
    };

    onMounted(loadInvoices);
    watch(statusFilter, loadInvoices);
    watch(showCreateDialog, (val) => {
      if (val) {
        loadQuotes();
        selectedQuote.value = null;
        invoiceType.value = "standard";
        emptyTitle.value = "";
      }
    });

    const filteredQuotes = computed(() => {
      if (!quoteSearch.value) return allQuotes.value;
      const s = quoteSearch.value.toLowerCase();
      return allQuotes.value.filter(
        (q) =>
          q.project_title.toLowerCase().includes(s) ||
          q.quote_number.toLowerCase().includes(s),
      );
    });

    const onCreateFromQuote = async () => {
      if (!selectedQuote.value) return;
      creating.value = true;
      try {
        const res = await api.post("/invoices/from-quote", {
          quote_id: selectedQuote.value.id,
          type: invoiceType.value,
        });
        showCreateDialog.value = false;
        $q.notify({ type: "positive", message: "Rechnung erstellt!" });
        router.push(`/invoices/${res.data.invoice.id}`);
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler",
        });
      } finally {
        creating.value = false;
      }
    };

    const onCreateEmpty = async () => {
      if (!emptyTitle.value) {
        $q.notify({ type: "warning", message: "Bitte Titel eingeben" });
        return;
      }
      creating.value = true;
      try {
        const res = await api.post("/invoices", {
          project_title: emptyTitle.value,
        });
        showCreateDialog.value = false;
        $q.notify({ type: "positive", message: "Rechnung erstellt!" });
        router.push(`/invoices/${res.data.invoice.id}`);
      } catch (e) {
        $q.notify({ type: "negative", message: "Fehler" });
      } finally {
        creating.value = false;
      }
    };

    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    const formatDate = (d) =>
      d ? new Date(d).toLocaleDateString("de-DE") : "";
    const invoiceStatusColor = (s) =>
      ({
        draft: "grey",
        sent: "blue",
        paid: "green",
        partial_paid: "orange",
        overdue: "red",
        cancelled: "grey-7",
      })[s] || "grey";
    const invoiceStatusLabel = (s) =>
      ({
        draft: "Entwurf",
        sent: "Versendet",
        paid: "Bezahlt",
        partial_paid: "Teilbezahlt",
        overdue: "Überfällig",
        cancelled: "Storniert",
      })[s] || s;
    const invoiceTypeLabel = (t) =>
      ({ partial: "Abschlag", final: "Schlussrechnung" })[t] || "";

    return {
      loading,
      invoices,
      search,
      statusFilter,
      statusFilterOptions,
      showCreateDialog,
      createTab,
      creating,
      allQuotes,
      quotesLoading,
      quoteSearch,
      filteredQuotes,
      selectedQuote,
      invoiceType,
      typeOptions,
      emptyTitle,
      onCreateFromQuote,
      onCreateEmpty,
      loadInvoices,
      formatPrice,
      formatDate,
      invoiceStatusColor,
      invoiceStatusLabel,
      invoiceTypeLabel,
    };
  },
};
</script>
