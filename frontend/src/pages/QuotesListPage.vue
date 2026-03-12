<template>
  <q-page class="q-pa-lg">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Angebote
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          Alle Ihre Angebote im Überblick
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
    <q-card
      flat
      style="
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #ffffff;
      "
    >
      <q-card-section>
        <q-table
          :rows="quoteStore.quotes"
          :columns="columns"
          row-key="id"
          flat
          :loading="quoteStore.loading"
          no-data-label="Noch keine Angebote. Erstellen Sie Ihr erstes!"
          @row-click="(evt, row) => $router.push(`/quotes/${row.id}`)"
          class="cursor-pointer"
        >
          <template v-slot:body-cell-status="props"
            ><q-td :props="props"
              ><q-badge
                :color="statusColor(props.value)"
                :label="statusLabel(props.value)" /></q-td
          ></template>
          <template v-slot:body-cell-total_gross="props"
            ><q-td :props="props"
              ><span style="font-weight: 600; color: #0f172a"
                >{{ formatPrice(props.value) }} €</span
              ></q-td
            ></template
          >
          <template v-slot:body-cell-actions="props">
            <q-td :props="props">
              <q-btn flat round dense icon="more_vert" color="grey-6">
                <q-menu>
                  <q-list>
                    <q-item
                      clickable
                      v-close-popup
                      @click="$router.push(`/quotes/${props.row.id}`)"
                      ><q-item-section avatar
                        ><q-icon name="edit" /></q-item-section
                      ><q-item-section>Bearbeiten</q-item-section></q-item
                    >
                    <q-item
                      clickable
                      v-close-popup
                      @click="onDuplicate(props.row.id)"
                      ><q-item-section avatar
                        ><q-icon name="content_copy" /></q-item-section
                      ><q-item-section>Duplizieren</q-item-section></q-item
                    >
                    <q-separator />
                    <q-item
                      clickable
                      v-close-popup
                      @click="onDelete(props.row.id)"
                      class="text-negative"
                      ><q-item-section avatar
                        ><q-icon
                          name="delete"
                          color="negative" /></q-item-section
                      ><q-item-section>Löschen</q-item-section></q-item
                    >
                  </q-list>
                </q-menu>
              </q-btn>
            </q-td>
          </template>
        </q-table>
      </q-card-section>
    </q-card>
  </q-page>
</template>
<script>
import { onMounted } from "vue";
import { useQuoteStore } from "src/stores/quotes";
import { useQuasar } from "quasar";
export default {
  name: "QuotesListPage",
  setup() {
    const quoteStore = useQuoteStore();
    const $q = useQuasar();
    onMounted(() => quoteStore.fetchQuotes());
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
      { name: "actions", label: "", field: "actions", align: "right" },
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
    const onDuplicate = async (id) => {
      await quoteStore.duplicateQuote(id);
      await quoteStore.fetchQuotes();
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
      columns,
      statusColor,
      statusLabel,
      formatPrice,
      onDuplicate,
      onDelete,
    };
  },
};
</script>
