<template>
  <q-page padding>
    <!-- Header -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="text-h5 text-weight-bold">Mahnwesen</div>
        <div class="text-caption text-grey-6">Überfällige Rechnungen verwalten und Mahnungen versenden</div>
      </div>
      <q-btn
        color="negative"
        icon="warning"
        label="Mahnlauf starten"
        unelevated
        @click="openMahnlauf"
      />
    </div>

    <!-- Stats -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-6 col-md-3">
        <q-card flat bordered class="stat-card">
          <q-card-section>
            <div class="text-caption text-grey-6 q-mb-xs">Gesamt Mahnungen</div>
            <div class="text-h5 text-weight-bold">{{ stats.total ?? 0 }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="stat-card">
          <q-card-section>
            <div class="text-caption text-grey-6 q-mb-xs">Offen / Versendet</div>
            <div class="text-h5 text-weight-bold text-orange">{{ (stats.draft ?? 0) + (stats.sent ?? 0) }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="stat-card">
          <q-card-section>
            <div class="text-caption text-grey-6 q-mb-xs">Bezahlt</div>
            <div class="text-h5 text-weight-bold text-positive">{{ stats.paid ?? 0 }}</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-6 col-md-3">
        <q-card flat bordered class="stat-card">
          <q-card-section>
            <div class="text-caption text-grey-6 q-mb-xs">Offener Betrag</div>
            <div class="text-h5 text-weight-bold text-negative">{{ formatCurrency(stats.open_amount ?? 0) }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Filter -->
    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row q-col-gutter-sm items-center">
        <div class="col-12 col-md-4">
          <q-input
            v-model="search"
            placeholder="Suche nach Mahnung oder Rechnung..."
            outlined dense clearable
            @update:model-value="loadMahnungen"
          >
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </div>
        <div class="col-6 col-md-3">
          <q-select
            v-model="filterStatus"
            :options="statusOptions"
            label="Status"
            outlined dense emit-value map-options
            @update:model-value="loadMahnungen"
          />
        </div>
        <div class="col-6 col-md-3">
          <q-select
            v-model="filterLevel"
            :options="levelOptions"
            label="Mahnstufe"
            outlined dense emit-value map-options
            @update:model-value="loadMahnungen"
          />
        </div>
      </q-card-section>
    </q-card>

    <!-- Tabelle -->
    <q-card flat bordered>
      <q-table
        :rows="mahnungen"
        :columns="columns"
        row-key="id"
        flat
        :loading="loading"
        :pagination="{ rowsPerPage: 20 }"
        no-data-label="Keine Mahnungen gefunden"
      >
        <template #body-cell-mahnung_number="props">
          <q-td :props="props">
            <div class="text-weight-bold">{{ props.row.mahnung_number }}</div>
            <div class="text-caption text-grey-6">{{ props.row.level_label }}</div>
          </q-td>
        </template>

        <template #body-cell-customer="props">
          <q-td :props="props">
            <div v-if="props.row.customer">
              <div class="text-weight-medium">
                {{ props.row.customer.company_name || (props.row.customer.first_name + ' ' + props.row.customer.last_name) }}
              </div>
              <div class="text-caption text-grey-6">{{ props.row.customer.email }}</div>
            </div>
            <span v-else class="text-grey-5">–</span>
          </q-td>
        </template>

        <template #body-cell-invoice="props">
          <q-td :props="props">
            <div v-if="props.row.invoice" class="text-weight-medium">
              {{ props.row.invoice.invoice_number }}
            </div>
          </q-td>
        </template>

        <template #body-cell-level="props">
          <q-td :props="props">
            <q-badge
              :color="levelColor(props.row.level)"
              :label="props.row.level_label"
            />
          </q-td>
        </template>

        <template #body-cell-total_amount="props">
          <q-td :props="props">
            <div class="text-weight-bold text-negative">
              {{ formatCurrency(props.row.total_amount) }}
            </div>
            <div v-if="props.row.mahnung_fee > 0 || props.row.interest_amount > 0" class="text-caption text-grey-6">
              inkl. Gebühr + Zinsen
            </div>
          </q-td>
        </template>

        <template #body-cell-status="props">
          <q-td :props="props">
            <q-badge
              :color="props.row.status_color"
              :label="props.row.status_label"
            />
          </q-td>
        </template>

        <template #body-cell-new_due_date="props">
          <q-td :props="props">
            <div :class="isOverdue(props.row.new_due_date) && props.row.status === 'sent' ? 'text-negative text-weight-bold' : ''">
              {{ formatDate(props.row.new_due_date) }}
            </div>
          </q-td>
        </template>

        <template #body-cell-actions="props">
          <q-td :props="props" auto-width>
            <q-btn flat round dense icon="more_vert">
              <q-menu>
                <q-list style="min-width: 180px">
                  <q-item clickable v-close-popup @click="downloadPdf(props.row)">
                    <q-item-section avatar><q-icon name="picture_as_pdf" /></q-item-section>
                    <q-item-section>PDF herunterladen</q-item-section>
                  </q-item>

                  <q-item
                    v-if="props.row.status === 'draft'"
                    clickable v-close-popup
                    @click="sendMahnung(props.row)"
                  >
                    <q-item-section avatar><q-icon name="send" color="primary" /></q-item-section>
                    <q-item-section>Per E-Mail senden</q-item-section>
                  </q-item>

                  <q-item
                    v-if="['draft', 'sent'].includes(props.row.status)"
                    clickable v-close-popup
                    @click="markPaid(props.row)"
                  >
                    <q-item-section avatar><q-icon name="check_circle" color="positive" /></q-item-section>
                    <q-item-section>Als bezahlt markieren</q-item-section>
                  </q-item>

                  <q-separator />

                  <q-item
                    v-if="['draft', 'sent'].includes(props.row.status)"
                    clickable v-close-popup
                    @click="cancelMahnung(props.row)"
                    class="text-negative"
                  >
                    <q-item-section avatar><q-icon name="cancel" color="negative" /></q-item-section>
                    <q-item-section>Stornieren</q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </q-td>
        </template>
      </q-table>
    </q-card>

    <!-- Mahnlauf Dialog -->
    <q-dialog v-model="mahnlaufDialog" maximized>
      <q-card>
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Mahnlauf – Überfällige Rechnungen</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section>
          <q-banner class="bg-orange-1 text-orange-9 q-mb-md" rounded>
            <template #avatar><q-icon name="info" /></template>
            Alle unten aufgeführten Rechnungen sind überfällig und haben noch keine offene Mahnung auf der nächsten Stufe.
          </q-banner>

          <q-table
            :rows="overdueInvoices"
            :columns="overdueColumns"
            row-key="invoice.id"
            flat bordered
            :loading="overdueLoading"
            no-data-label="Keine überfälligen Rechnungen gefunden 🎉"
          >
            <template #body-cell-customer="props">
              <q-td :props="props">
                <div v-if="props.row.invoice.customer">
                  {{ props.row.invoice.customer.company_name || (props.row.invoice.customer.first_name + ' ' + props.row.invoice.customer.last_name) }}
                </div>
              </q-td>
            </template>

            <template #body-cell-days_overdue="props">
              <q-td :props="props">
                <q-badge color="negative" :label="props.row.days_overdue + ' Tage'" />
              </q-td>
            </template>

            <template #body-cell-next_level="props">
              <q-td :props="props">
                <q-badge :color="levelColor(props.row.next_level)" :label="props.row.next_level + '. Mahnung'" />
              </q-td>
            </template>

            <template #body-cell-amount="props">
              <q-td :props="props">
                <strong>{{ formatCurrency(props.row.invoice.total_gross) }}</strong>
              </q-td>
            </template>

            <template #body-cell-action="props">
              <q-td :props="props" auto-width>
                <q-btn
                  color="negative"
                  label="Mahnung erstellen"
                  size="sm"
                  unelevated
                  :loading="creatingFor === props.row.invoice.id"
                  @click="createMahnung(props.row.invoice.id)"
                />
              </q-td>
            </template>
          </q-table>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Senden Bestätigung -->
    <q-dialog v-model="sendDialog">
      <q-card style="min-width: 400px">
        <q-card-section>
          <div class="text-h6">Mahnung versenden</div>
        </q-card-section>
        <q-card-section>
          <p>
            Soll die <strong>{{ selectedMahnung?.level_label }}</strong> ({{ selectedMahnung?.mahnung_number }})
            per E-Mail an <strong>{{ selectedMahnung?.customer?.email }}</strong> versendet werden?
          </p>
          <q-banner v-if="selectedMahnung?.level === 3" class="bg-red-1 text-red-9 q-mt-md" rounded>
            <template #avatar><q-icon name="warning" /></template>
            Dies ist die letzte Mahnung. Der Kunde wird über mögliche rechtliche Schritte informiert.
          </q-banner>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Abbrechen" v-close-popup />
          <q-btn
            color="negative"
            label="Jetzt senden"
            icon="send"
            unelevated
            :loading="sending"
            @click="confirmSend"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Bezahlt Dialog -->
    <q-dialog v-model="paidDialog">
      <q-card style="min-width: 380px">
        <q-card-section>
          <div class="text-h6">Zahlung erfassen</div>
        </q-card-section>
        <q-card-section>
          <p>Zahlung für <strong>{{ selectedMahnung?.mahnung_number }}</strong> erfassen?</p>
          <p class="text-caption text-grey-6">Die Rechnung wird ebenfalls als bezahlt markiert.</p>
          <q-input v-model="paidAt" label="Zahlungsdatum" type="date" outlined class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Abbrechen" v-close-popup />
          <q-btn color="positive" label="Zahlung speichern" unelevated :loading="paying" @click="confirmPaid" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from 'src/boot/axios'
import { useQuasar } from 'quasar'

const $q = useQuasar()

// State
const mahnungen      = ref([])
const stats          = ref({})
const loading        = ref(false)
const search         = ref('')
const filterStatus   = ref('all')
const filterLevel    = ref('all')

const mahnlaufDialog  = ref(false)
const overdueInvoices = ref([])
const overdueLoading  = ref(false)
const creatingFor     = ref(null)

const sendDialog      = ref(false)
const selectedMahnung = ref(null)
const sending         = ref(false)

const paidDialog = ref(false)
const paidAt     = ref(new Date().toISOString().split('T')[0])
const paying     = ref(false)

// Columns
const columns = [
  { name: 'mahnung_number', label: 'Mahnung', field: 'mahnung_number', align: 'left', sortable: true },
  { name: 'customer',       label: 'Kunde',   field: 'customer',       align: 'left' },
  { name: 'invoice',        label: 'Rechnung',field: 'invoice',        align: 'left' },
  { name: 'level',          label: 'Stufe',   field: 'level',          align: 'center', sortable: true },
  { name: 'total_amount',   label: 'Betrag',  field: 'total_amount',   align: 'right', sortable: true },
  { name: 'status',         label: 'Status',  field: 'status',         align: 'center' },
  { name: 'new_due_date',   label: 'Neue Fälligkeit', field: 'new_due_date', align: 'center', sortable: true },
  { name: 'actions',        label: '',        field: 'actions',        align: 'right' },
]

const overdueColumns = [
  { name: 'customer',     label: 'Kunde',       field: 'customer',    align: 'left' },
  { name: 'invoice',      label: 'Rechnung',    field: row => row.invoice.invoice_number, align: 'left' },
  { name: 'amount',       label: 'Betrag',      field: 'amount',      align: 'right' },
  { name: 'days_overdue', label: 'Überfällig',  field: 'days_overdue',align: 'center' },
  { name: 'next_level',   label: 'Nächste Stufe', field: 'next_level',align: 'center' },
  { name: 'action',       label: '',            field: 'action',      align: 'right' },
]

const statusOptions = [
  { label: 'Alle', value: 'all' },
  { label: 'Entwurf', value: 'draft' },
  { label: 'Versendet', value: 'sent' },
  { label: 'Bezahlt', value: 'paid' },
  { label: 'Storniert', value: 'cancelled' },
]

const levelOptions = [
  { label: 'Alle Stufen', value: 'all' },
  { label: '1. Mahnung', value: 1 },
  { label: '2. Mahnung', value: 2 },
  { label: '3. Mahnung', value: 3 },
]

// Load
async function loadMahnungen() {
  loading.value = true
  try {
    const params = {}
    if (search.value) params.search = search.value
    if (filterStatus.value !== 'all') params.status = filterStatus.value
    if (filterLevel.value !== 'all') params.level = filterLevel.value

    const res = await api.get('/mahnungen', { params })
    mahnungen.value = res.data.mahnungen.data
    stats.value     = res.data.stats
  } catch (e) {
    $q.notify({ type: 'negative', message: 'Fehler beim Laden der Mahnungen' })
  } finally {
    loading.value = false
  }
}

async function openMahnlauf() {
  mahnlaufDialog.value = true
  overdueLoading.value = true
  try {
    const res = await api.get('/mahnungen/overdue-invoices')
    overdueInvoices.value = res.data
  } catch (e) {
    $q.notify({ type: 'negative', message: 'Fehler beim Laden der überfälligen Rechnungen' })
  } finally {
    overdueLoading.value = false
  }
}

async function createMahnung(invoiceId) {
  creatingFor.value = invoiceId
  try {
    const res = await api.post('/mahnungen', { invoice_id: invoiceId })
    $q.notify({ type: 'positive', message: res.data.message })
    // Aus Liste entfernen
    overdueInvoices.value = overdueInvoices.value.filter(i => i.invoice.id !== invoiceId)
    await loadMahnungen()
  } catch (e) {
    $q.notify({ type: 'negative', message: e.response?.data?.message || 'Fehler beim Erstellen' })
  } finally {
    creatingFor.value = null
  }
}

function sendMahnung(mahnung) {
  selectedMahnung.value = mahnung
  sendDialog.value = true
}

async function confirmSend() {
  sending.value = true
  try {
    const res = await api.post(`/mahnungen/${selectedMahnung.value.id}/send`)
    $q.notify({ type: 'positive', message: res.data.message })
    sendDialog.value = false
    await loadMahnungen()
  } catch (e) {
    $q.notify({ type: 'negative', message: e.response?.data?.message || 'Fehler beim Versenden' })
  } finally {
    sending.value = false
  }
}

function markPaid(mahnung) {
  selectedMahnung.value = mahnung
  paidAt.value = new Date().toISOString().split('T')[0]
  paidDialog.value = true
}

async function confirmPaid() {
  paying.value = true
  try {
    await api.post(`/mahnungen/${selectedMahnung.value.id}/paid`, { paid_at: paidAt.value })
    $q.notify({ type: 'positive', message: 'Zahlung erfasst. Rechnung als bezahlt markiert.' })
    paidDialog.value = false
    await loadMahnungen()
  } catch (e) {
    $q.notify({ type: 'negative', message: e.response?.data?.message || 'Fehler' })
  } finally {
    paying.value = false
  }
}

async function cancelMahnung(mahnung) {
  $q.dialog({
    title: 'Mahnung stornieren',
    message: `Möchten Sie ${mahnung.mahnung_number} wirklich stornieren?`,
    cancel: true,
    ok: { color: 'negative', label: 'Stornieren' },
  }).onOk(async () => {
    try {
      await api.post(`/mahnungen/${mahnung.id}/cancel`)
      $q.notify({ type: 'positive', message: 'Mahnung storniert.' })
      await loadMahnungen()
    } catch (e) {
      $q.notify({ type: 'negative', message: e.response?.data?.message || 'Fehler' })
    }
  })
}

async function downloadPdf(mahnung) {
  try {
    const res = await api.get(`/mahnungen/${mahnung.id}/pdf`, { responseType: 'blob' })
    const blob = new Blob([res.data], { type: 'application/pdf' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = mahnung.mahnung_number + '.pdf'
    link.click()
  } catch (e) {
    $q.notify({ type: 'negative', message: 'PDF konnte nicht geladen werden' })
  }
}

// Helpers
function formatCurrency(val) {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(val || 0)
}

function formatDate(val) {
  if (!val) return '–'
  return new Date(val).toLocaleDateString('de-DE')
}

function isOverdue(date) {
  return date && new Date(date) < new Date()
}

function levelColor(level) {
  return level === 1 ? 'warning' : level === 2 ? 'negative' : 'red-10'
}

onMounted(loadMahnungen)
</script>

<style scoped>
.stat-card { transition: box-shadow 0.2s; }
.stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
</style>
