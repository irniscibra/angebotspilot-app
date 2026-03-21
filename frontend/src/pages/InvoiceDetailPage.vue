<template>
  <q-page class="q-pa-sm q-pa-md-lg" style="background: #f6f9fc">
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="50px" />
    </div>
    <div v-else-if="invoice">
      <!-- HEADER – Mobile optimiert -->
      <div class="q-mb-md">
        <div class="row items-center no-wrap q-mb-xs">
          <q-btn
            flat
            round
            icon="arrow_back"
            color="grey-6"
            class="q-mr-xs"
            @click="$router.push('/invoices')"
          />
          <div class="row items-center q-gutter-xs">
            <q-badge
              :color="statusColor(invoice.status)"
              :label="statusLabel(invoice.status)"
            />
            <q-badge
              v-if="invoice.type !== 'standard'"
              outline
              :color="invoice.type === 'partial' ? 'orange' : 'purple'"
              :label="typeLabel(invoice.type)"
            />
            <span style="font-size: 12px; color: #94a3b8">{{
              invoice.invoice_number
            }}</span>
          </div>
          <q-space />
          <div class="row items-center q-gutter-xs">
            <q-btn
              v-if="invoice.status === 'draft'"
              color="green"
              icon="send"
              :label="$q.screen.gt.sm ? 'Finalisieren' : ''"
              no-caps
              dense
              @click="onSend"
            />
            <q-btn
              v-if="invoice.status === 'sent'"
              color="green"
              icon="check"
              :label="$q.screen.gt.sm ? 'Bezahlt' : ''"
              no-caps
              dense
              @click="onMarkPaid"
            />
            <q-btn
              color="primary"
              icon="picture_as_pdf"
              :label="$q.screen.gt.sm ? 'PDF' : ''"
              no-caps
              dense
              @click="onExportPdf"
            />
            <q-btn round flat icon="more_vert" color="grey-7">
              <q-menu auto-close style="min-width: 200px; border-radius: 12px">
                <q-list>
                  <q-item
                    v-if="invoice.status !== 'cancelled'"
                    clickable
                    @click="onCancel"
                  >
                    <q-item-section avatar
                      ><q-icon name="block" color="negative"
                    /></q-item-section>
                    <q-item-section class="text-negative"
                      >Stornieren</q-item-section
                    >
                  </q-item>
                  <q-item
                    v-if="invoice.status === 'draft'"
                    clickable
                    @click="onDelete"
                  >
                    <q-item-section avatar
                      ><q-icon name="delete" color="negative"
                    /></q-item-section>
                    <q-item-section class="text-negative"
                      >Entwurf löschen</q-item-section
                    >
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </div>
        </div>
        <!-- Titel eigene Zeile -->
        <div class="q-pl-xs">
          <h5
            class="q-my-none"
            style="
              font-weight: 700;
              color: #0f172a;
              font-size: clamp(16px, 4vw, 22px);
            "
          >
            {{ invoice.project_title }}
          </h5>
        </div>
      </div>

      <!-- Storno-Banner -->
      <q-banner
        v-if="invoice.status === 'cancelled'"
        class="q-mb-md"
        rounded
        style="background: #fef2f2; border: 1px solid #fecaca"
      >
        <template v-slot:avatar
          ><q-icon name="block" color="negative"
        /></template>
        <span style="font-weight: 600; color: #991b1b"
          >Diese Rechnung wurde storniert.</span
        >
        <span v-if="invoice.cancellation_reason" style="color: #7f1d1d">
          Grund: {{ invoice.cancellation_reason }}</span
        >
      </q-banner>

      <!-- Nicht editierbar Banner -->
      <q-banner
        v-if="invoice.status !== 'draft' && invoice.status !== 'cancelled'"
        class="q-mb-md"
        rounded
        style="background: #eff6ff; border: 1px solid #bfdbfe"
      >
        <template v-slot:avatar><q-icon name="lock" color="blue" /></template>
        <span style="color: #1e40af"
          >Diese Rechnung wurde finalisiert und kann nicht mehr bearbeitet
          werden.</span
        >
      </q-banner>

      <!-- Info Cards -->
      <div class="row q-col-gutter-md q-mb-lg">
        <div class="col-12 col-md-4">
          <q-card
            flat
            class="full-height"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              background: #fff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 11px;
                  font-weight: 600;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                  color: #94a3b8;
                "
                class="q-mb-sm"
              >
                Rechnungsdetails
              </div>
              <div class="q-gutter-sm">
                <div>
                  <span style="font-size: 12px; color: #94a3b8"
                    >Rechnungs-Nr.</span
                  >
                  <div
                    style="font-size: 14px; font-weight: 600; color: #0f172a"
                  >
                    {{ invoice.invoice_number }}
                  </div>
                </div>
                <div>
                  <span style="font-size: 12px; color: #94a3b8"
                    >Erstellt am</span
                  >
                  <div style="font-size: 13px; color: #0f172a">
                    {{ formatDate(invoice.created_at) }}
                  </div>
                </div>
                <div>
                  <span style="font-size: 12px; color: #94a3b8"
                    >Fällig bis</span
                  >
                  <div
                    style="font-size: 13px"
                    :style="
                      isOverdue
                        ? 'color: #ef4444; font-weight: 600;'
                        : 'color: #0f172a;'
                    "
                  >
                    {{ formatDate(invoice.due_date) }}
                    {{ isOverdue ? "(überfällig!)" : "" }}
                  </div>
                </div>
                <div v-if="invoice.quote_reference">
                  <span style="font-size: 12px; color: #94a3b8">Referenz</span>
                  <div style="font-size: 12px; color: #64748b">
                    {{ invoice.quote_reference }}
                  </div>
                </div>
                <div v-if="invoice.paid_at">
                  <span style="font-size: 12px; color: #94a3b8"
                    >Bezahlt am</span
                  >
                  <div
                    style="font-size: 13px; color: #16a34a; font-weight: 600"
                  >
                    {{ formatDate(invoice.paid_at) }}
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card
            flat
            class="full-height"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              background: #fff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 11px;
                  font-weight: 600;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                  color: #94a3b8;
                "
                class="q-mb-sm"
              >
                Kunde
              </div>
              <div v-if="invoice.customer">
                <div style="font-size: 14px; font-weight: 600; color: #0f172a">
                  {{
                    invoice.customer.type === "business"
                      ? invoice.customer.company_name
                      : invoice.customer.first_name +
                        " " +
                        invoice.customer.last_name
                  }}
                </div>
                <div style="font-size: 12px; margin-top: 4px; color: #64748b">
                  <div v-if="invoice.customer.address_street">
                    {{ invoice.customer.address_street }}
                  </div>
                  <div v-if="invoice.customer.address_zip">
                    {{ invoice.customer.address_zip }}
                    {{ invoice.customer.address_city }}
                  </div>
                </div>
              </div>
              <div v-else style="font-size: 13px; color: #94a3b8">
                Kein Kunde zugewiesen
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card
            flat
            class="full-height"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              background: #fff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 11px;
                  font-weight: 600;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                  color: #94a3b8;
                "
                class="q-mb-sm"
              >
                Leistungszeitraum
              </div>
              <div v-if="invoice.status === 'draft'" class="row q-gutter-sm">
                <q-input
                  v-model="invoice.service_date_from"
                  filled
                  dense
                  label="Von"
                  type="date"
                  class="col"
                  @change="onSave"
                />
                <q-input
                  v-model="invoice.service_date_to"
                  filled
                  dense
                  label="Bis"
                  type="date"
                  class="col"
                  @change="onSave"
                />
              </div>
              <div v-else style="font-size: 13px; color: #0f172a">
                {{
                  invoice.service_date_from
                    ? formatDate(invoice.service_date_from)
                    : "—"
                }}
                bis
                {{
                  invoice.service_date_to
                    ? formatDate(invoice.service_date_to)
                    : "—"
                }}
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Positionen + Kalkulation -->
      <div class="row q-col-gutter-lg">
        <div class="col-12 col-md-8">
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
                <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
                  Positionen
                </h6>
                <q-btn
                  v-if="invoice.status === 'draft'"
                  flat
                  color="primary"
                  icon="add"
                  :label="$q.screen.gt.sm ? 'Position hinzufügen' : ''"
                  no-caps
                  dense
                  @click="openAddDialog"
                />
              </div>

              <div
                v-for="(items, groupName) in groupedItems"
                :key="groupName"
                class="q-mb-md"
              >
                <div
                  class="q-mb-sm q-pa-xs q-pl-sm"
                  style="
                    border-left: 3px solid #3b82f6;
                    font-size: 13px;
                    font-weight: 700;
                    color: #64748b;
                    text-transform: uppercase;
                  "
                >
                  {{ groupName }}
                </div>
                <q-card
                  v-for="item in items"
                  :key="item.id"
                  flat
                  class="q-mb-xs"
                  style="
                    background: #f8fafc;
                    border: 1px solid #f1f5f9;
                    border-radius: 10px;
                  "
                >
                  <q-card-section class="q-py-sm q-px-sm">
                    <!-- Mobile Layout -->
                    <div v-if="$q.screen.lt.md">
                      <div class="row items-start justify-between q-mb-xs">
                        <div class="col">
                          <span
                            style="
                              font-size: 13px;
                              font-weight: 600;
                              color: #0f172a;
                            "
                            >{{ item.title }}</span
                          >
                          <q-badge
                            :color="
                              item.type === 'material' ? 'blue' : 'orange'
                            "
                            :label="
                              item.type === 'material' ? 'Material' : 'Arbeit'
                            "
                            dense
                            class="q-ml-xs"
                            style="font-size: 10px"
                          />
                          <div
                            v-if="item.description"
                            style="
                              font-size: 11px;
                              margin-top: 2px;
                              color: #94a3b8;
                            "
                          >
                            {{ item.description }}
                          </div>
                        </div>
                        <q-btn
                          v-if="invoice.status === 'draft'"
                          flat
                          round
                          dense
                          icon="close"
                          color="negative"
                          size="sm"
                          @click="onDeleteItem(item)"
                        />
                      </div>
                      <div class="row items-center q-gutter-sm">
                        <div
                          v-if="invoice.status === 'draft'"
                          style="width: 70px"
                        >
                          <q-input
                            :model-value="item.quantity"
                            @change="
                              (val) =>
                                onUpdateItem(item, 'quantity', val.target.value)
                            "
                            dense
                            filled
                            type="number"
                            step="0.5"
                            style="font-size: 12px"
                          />
                        </div>
                        <div
                          v-else
                          style="
                            font-size: 13px;
                            color: #475569;
                            min-width: 30px;
                          "
                        >
                          {{ item.quantity }}
                        </div>
                        <div
                          style="
                            font-size: 12px;
                            color: #64748b;
                            min-width: 30px;
                          "
                        >
                          {{ item.unit }}
                        </div>
                        <div
                          v-if="invoice.status === 'draft'"
                          style="width: 85px"
                        >
                          <q-input
                            :model-value="item.unit_price"
                            @change="
                              (val) =>
                                onUpdateItem(
                                  item,
                                  'unit_price',
                                  val.target.value,
                                )
                            "
                            dense
                            filled
                            type="number"
                            step="0.50"
                            suffix="€"
                            style="font-size: 12px"
                          />
                        </div>
                        <div v-else style="font-size: 13px; color: #475569">
                          {{ formatPrice(item.unit_price) }} €
                        </div>
                        <q-space />
                        <div
                          style="
                            font-weight: 700;
                            font-size: 14px;
                            color: #1d4ed8;
                          "
                        >
                          {{ formatPrice(item.quantity * item.unit_price) }} €
                        </div>
                      </div>
                    </div>

                    <!-- Desktop Layout -->
                    <div v-else class="row items-center q-gutter-sm">
                      <div class="col">
                        <span
                          style="
                            font-size: 13.5px;
                            font-weight: 500;
                            color: #0f172a;
                          "
                          >{{ item.title }}</span
                        >
                        <q-badge
                          :color="item.type === 'material' ? 'blue' : 'orange'"
                          :label="
                            item.type === 'material' ? 'Material' : 'Arbeit'
                          "
                          dense
                          class="q-ml-xs"
                          style="font-size: 10px"
                        />
                        <div
                          v-if="item.description"
                          style="
                            font-size: 11px;
                            margin-top: 2px;
                            color: #94a3b8;
                          "
                        >
                          {{ item.description }}
                        </div>
                      </div>
                      <div
                        v-if="invoice.status === 'draft'"
                        style="width: 75px"
                      >
                        <q-input
                          :model-value="item.quantity"
                          @change="
                            (val) =>
                              onUpdateItem(item, 'quantity', val.target.value)
                          "
                          dense
                          filled
                          type="number"
                          step="0.5"
                          style="font-size: 13px"
                        />
                      </div>
                      <div
                        v-else
                        style="
                          width: 60px;
                          text-align: center;
                          font-size: 13px;
                          color: #475569;
                        "
                      >
                        {{ item.quantity }}
                      </div>
                      <div
                        class="text-center"
                        style="width: 55px; font-size: 12px; color: #64748b"
                      >
                        {{ item.unit }}
                      </div>
                      <div
                        v-if="invoice.status === 'draft'"
                        style="width: 95px"
                      >
                        <q-input
                          :model-value="item.unit_price"
                          @change="
                            (val) =>
                              onUpdateItem(item, 'unit_price', val.target.value)
                          "
                          dense
                          filled
                          type="number"
                          step="0.50"
                          suffix="€"
                          style="font-size: 13px"
                        />
                      </div>
                      <div
                        v-else
                        class="text-right"
                        style="width: 90px; font-size: 13px; color: #475569"
                      >
                        {{ formatPrice(item.unit_price) }} €
                      </div>
                      <div
                        class="text-right"
                        style="
                          width: 95px;
                          font-weight: 600;
                          font-size: 13px;
                          color: #0f172a;
                        "
                      >
                        {{ formatPrice(item.quantity * item.unit_price) }} €
                      </div>
                      <q-btn
                        v-if="invoice.status === 'draft'"
                        flat
                        round
                        dense
                        icon="close"
                        color="negative"
                        size="sm"
                        @click="onDeleteItem(item)"
                      />
                    </div>
                  </q-card-section>
                </q-card>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Kalkulation Sidebar -->
        <div class="col-12 col-md-4">
          <q-card
            flat
            style="
              border: 1px solid #dbeafe;
              border-radius: 16px;
              background: #fff;
              position: sticky;
              top: 20px;
            "
          >
            <q-card-section class="q-pa-lg">
              <h6
                class="q-my-none q-mb-md"
                style="
                  font-weight: 700;
                  text-transform: uppercase;
                  font-size: 12px;
                  letter-spacing: 0.05em;
                  color: #64748b;
                "
              >
                Kalkulation
              </h6>
              <div class="q-gutter-sm">
                <div class="row justify-between">
                  <span style="color: #64748b">Material</span
                  ><span style="font-weight: 600; color: #0f172a"
                    >{{ formatPrice(invoice.subtotal_materials) }} €</span
                  >
                </div>
                <div class="row justify-between">
                  <span style="color: #64748b">Arbeitsleistung</span
                  ><span style="font-weight: 600; color: #0f172a"
                    >{{ formatPrice(invoice.subtotal_labor) }} €</span
                  >
                </div>
                <q-separator class="q-my-sm" />
                <div class="row justify-between">
                  <span style="color: #64748b">Netto</span
                  ><span style="font-weight: 600; color: #0f172a"
                    >{{ formatPrice(invoice.subtotal_net) }} €</span
                  >
                </div>
                <div class="row justify-between">
                  <span style="color: #64748b"
                    >MwSt ({{ invoice.vat_rate }}%)</span
                  ><span style="color: #94a3b8"
                    >{{ formatPrice(invoice.vat_amount) }} €</span
                  >
                </div>
                <q-separator class="q-my-sm" />
                <div class="row justify-between items-center">
                  <span
                    style="font-weight: 700; font-size: 16px; color: #0f172a"
                    >Gesamt</span
                  >
                  <span
                    style="font-weight: 800; font-size: 20px; color: #1d4ed8"
                    >{{ formatPrice(invoice.total_gross) }} €</span
                  >
                </div>
                <div
                  v-if="
                    invoice.type === 'final' &&
                    invoice.partial_payments_total > 0
                  "
                >
                  <q-separator class="q-my-sm" />
                  <div class="row justify-between">
                    <span style="color: #ef4444">Abzgl. Abschläge</span
                    ><span style="font-weight: 600; color: #ef4444"
                      >-{{
                        formatPrice(invoice.partial_payments_total)
                      }}
                      €</span
                    >
                  </div>
                  <div class="row justify-between items-center q-mt-sm">
                    <span
                      style="font-weight: 700; font-size: 16px; color: #0f172a"
                      >Zahlbetrag</span
                    >
                    <span
                      style="font-weight: 800; font-size: 20px; color: #16a34a"
                      >{{ formatPrice(invoice.remaining_amount) }} €</span
                    >
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>

    <!-- Dialog Position hinzufügen -->
    <q-dialog v-model="showAddDialog">
      <q-card style="width: 95vw; max-width: 450px; border-radius: 14px">
        <q-card-section
          ><h6 class="q-my-none" style="font-weight: 600">
            Position hinzufügen
          </h6></q-card-section
        >
        <q-card-section class="q-pt-none q-gutter-sm">
          <q-select
            v-model="newItem.type"
            filled
            dense
            :options="[
              { label: 'Material', value: 'material' },
              { label: 'Arbeit', value: 'labor' },
            ]"
            emit-value
            map-options
            label="Typ"
          />
          <q-input v-model="newItem.group_name" filled dense label="Gruppe" />
          <q-input v-model="newItem.title" filled dense label="Bezeichnung *" />
          <q-input
            v-model="newItem.description"
            filled
            dense
            label="Beschreibung (optional)"
          />
          <div class="row q-gutter-sm">
            <q-input
              v-model.number="newItem.quantity"
              filled
              dense
              label="Menge"
              type="number"
              class="col"
            />
            <q-input
              v-model="newItem.unit"
              filled
              dense
              label="Einheit"
              class="col"
            />
            <q-input
              v-model.number="newItem.unit_price"
              filled
              dense
              label="Preis €"
              type="number"
              class="col"
            />
          </div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Abbrechen" color="grey" v-close-popup />
          <q-btn
            label="Hinzufügen"
            color="primary"
            no-caps
            @click="onAddItem"
            v-close-popup
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";

export default {
  name: "InvoiceDetailPage",
  setup() {
    const route = useRoute();
    const router = useRouter();
    const $q = useQuasar();

    const loading = ref(true);
    const invoice = ref(null);
    const showAddDialog = ref(false);
    const newItem = ref({
      type: "material",
      group_name: "Sonstiges",
      title: "",
      description: "",
      quantity: 1,
      unit: "Stück",
      unit_price: 0,
    });

    const groupedItems = computed(() => {
      if (!invoice.value?.items) return {};
      const g = {};
      invoice.value.items.forEach((i) => {
        const gr = i.group_name || "Sonstiges";
        if (!g[gr]) g[gr] = [];
        g[gr].push(i);
      });
      return g;
    });

    const isOverdue = computed(() => {
      if (!invoice.value?.due_date) return false;
      return (
        new Date(invoice.value.due_date) < new Date() &&
        !["paid", "cancelled"].includes(invoice.value.status)
      );
    });

    const loadInvoice = async () => {
      loading.value = true;
      try {
        const res = await api.get(`/invoices/${route.params.id}`);
        invoice.value = res.data;
      } catch (e) {
        $q.notify({ type: "negative", message: "Rechnung nicht gefunden" });
        router.push("/invoices");
      } finally {
        loading.value = false;
      }
    };

    onMounted(loadInvoice);

    const onSave = async () => {
      try {
        const res = await api.put(
          `/invoices/${invoice.value.id}`,
          invoice.value,
        );
        invoice.value = res.data;
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler",
        });
      }
    };

    const onUpdateItem = async (item, field, value) => {
      try {
        await api.put(`/invoices/${invoice.value.id}/items/${item.id}`, {
          [field]: Number(value),
        });
        await loadInvoice();
      } catch (e) {
        console.error(e);
      }
    };

    const onDeleteItem = async (item) => {
      $q.dialog({
        title: "Position löschen?",
        message: `"${item.title}" entfernen?`,
        cancel: true,
      }).onOk(async () => {
        await api.delete(`/invoices/${invoice.value.id}/items/${item.id}`);
        await loadInvoice();
      });
    };

    const onAddItem = async () => {
      if (!newItem.value.title) return;
      await api.post(`/invoices/${invoice.value.id}/items`, newItem.value);
      await loadInvoice();
      $q.notify({ type: "positive", message: "Position hinzugefügt" });
    };

    const openAddDialog = () => {
      const groups = Object.keys(groupedItems.value);
      newItem.value = {
        type: "material",
        group_name: groups.length > 0 ? groups[groups.length - 1] : "Sonstiges",
        title: "",
        description: "",
        quantity: 1,
        unit: "Stück",
        unit_price: 0,
      };
      showAddDialog.value = true;
    };

    const onSend = () => {
      $q.dialog({
        title: "Rechnung finalisieren?",
        message:
          "Nach dem Finalisieren kann die Rechnung nicht mehr bearbeitet werden. Die Rechnungsnummer wird dauerhaft vergeben.",
        cancel: true,
      }).onOk(async () => {
        try {
          const res = await api.post(`/invoices/${invoice.value.id}/send`);
          invoice.value = res.data.invoice;
          $q.notify({ type: "positive", message: "Rechnung finalisiert" });
        } catch (e) {
          $q.notify({
            type: "negative",
            message: e.response?.data?.message || "Fehler",
          });
        }
      });
    };

    const onMarkPaid = () => {
      $q.dialog({
        title: "Als bezahlt markieren?",
        message: `Rechnungsbetrag: ${formatPrice(invoice.value.total_gross)} €`,
        cancel: true,
      }).onOk(async () => {
        try {
          const res = await api.post(`/invoices/${invoice.value.id}/paid`);
          invoice.value = res.data.invoice;
          $q.notify({ type: "positive", message: "Als bezahlt markiert" });
        } catch (e) {
          $q.notify({ type: "negative", message: "Fehler" });
        }
      });
    };

    const onCancel = () => {
      $q.dialog({
        title: "Rechnung stornieren?",
        message: "Stornierte Rechnungen können nicht wiederhergestellt werden.",
        prompt: { model: "", label: "Storno-Grund *", type: "text" },
        cancel: true,
        color: "negative",
      }).onOk(async (reason) => {
        if (!reason) {
          $q.notify({ type: "warning", message: "Bitte Storno-Grund angeben" });
          return;
        }
        try {
          const res = await api.post(`/invoices/${invoice.value.id}/cancel`, {
            reason,
          });
          invoice.value = res.data.invoice;
          $q.notify({ type: "positive", message: "Rechnung storniert" });
        } catch (e) {
          $q.notify({
            type: "negative",
            message: e.response?.data?.message || "Fehler",
          });
        }
      });
    };

    const onDelete = () => {
      $q.dialog({
        title: "Entwurf löschen?",
        message: "Dieser Entwurf wird unwiderruflich gelöscht.",
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          await api.delete(`/invoices/${invoice.value.id}`);
          $q.notify({ type: "positive", message: "Entwurf gelöscht" });
          router.push("/invoices");
        } catch (e) {
          $q.notify({
            type: "negative",
            message: e.response?.data?.message || "Fehler",
          });
        }
      });
    };

    const onExportPdf = () => {
      const t = localStorage.getItem("auth_token");
      window.open(`/api/invoices/${invoice.value.id}/pdf?token=${t}`, "_blank");
    };

    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    const formatDate = (d) =>
      d ? new Date(d).toLocaleDateString("de-DE") : "-";
    const statusColor = (s) =>
      ({
        draft: "grey",
        sent: "blue",
        paid: "green",
        partial_paid: "orange",
        overdue: "red",
        cancelled: "grey-7",
      })[s] || "grey";
    const statusLabel = (s) =>
      ({
        draft: "Entwurf",
        sent: "Versendet",
        paid: "Bezahlt",
        partial_paid: "Teilbezahlt",
        overdue: "Überfällig",
        cancelled: "Storniert",
      })[s] || s;
    const typeLabel = (t) =>
      ({ standard: "Rechnung", partial: "Abschlag", final: "Schlussrechnung" })[
        t
      ] || t;

    return {
      loading,
      invoice,
      showAddDialog,
      newItem,
      groupedItems,
      isOverdue,
      loadInvoice,
      onSave,
      onUpdateItem,
      onDeleteItem,
      onAddItem,
      openAddDialog,
      onSend,
      onMarkPaid,
      onCancel,
      onDelete,
      onExportPdf,
      formatPrice,
      formatDate,
      statusColor,
      statusLabel,
      typeLabel,
    };
  },
};
</script>
