<template>
  <q-page class="q-pa-lg" style="background: #f6f9fc;">
    <!-- Header -->
    <div class="row items-center q-mb-lg">
      <div class="col">
        <div class="row items-center q-gutter-sm">
          <q-btn flat dense icon="arrow_back" color="grey-7" :to="{ name: 'materials' }" />
          <div>
            <h5 class="q-my-none" style="font-weight: 700; color: #1a1a2e;">Datanorm Import</h5>
            <p class="q-mb-none q-mt-xs" style="color: #64748b;">Materialien aus Großhändler-Dateien importieren</p>
          </div>
        </div>
      </div>
    </div>

    <div class="row q-gutter-lg">
      <!-- Upload Section -->
      <div class="col-12 col-md-7">
        <q-card flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #fff;">
          <q-card-section>
            <div class="text-subtitle1" style="font-weight: 600; color: #1a1a2e;">Datanorm-Datei hochladen</div>
            <p style="color: #64748b; font-size: 13px;">
              Laden Sie die .dat Datei Ihres Großhändlers hoch (z.B. Richter+Frenzel, Cordes & Graefe, GC Gruppe).
            </p>

            <!-- Dropzone -->
            <div
              class="datanorm-dropzone q-pa-xl text-center cursor-pointer"
              :class="{ 'dragover': isDragover, 'has-file': selectedFile }"
              @click="triggerFileInput"
              @drop.prevent="onDrop"
              @dragover.prevent="isDragover = true"
              @dragleave="isDragover = false"
            >
              <input ref="fileInput" type="file" accept=".dat,.csv,.txt,.001,.002,.003,.004,.005" @change="onFileSelect" class="hidden" />

              <div v-if="!selectedFile">
                <q-icon name="upload_file" size="48px" style="color: #94a3b8;" />
                <div class="q-mt-sm" style="font-weight: 600; color: #475569;">Datanorm-Datei hierher ziehen</div>
                <div style="color: #94a3b8; font-size: 12px;" class="q-mt-xs">oder klicken zum Auswählen – .dat, .csv, .txt (max. 50MB)</div>
              </div>

              <div v-else>
                <q-icon name="description" size="40px" color="primary" />
                <div class="q-mt-sm" style="font-weight: 600; color: #1a1a2e;">{{ selectedFile.name }}</div>
                <div style="color: #64748b; font-size: 12px;">{{ formatFileSize(selectedFile.size) }}</div>
                <q-btn flat dense size="sm" icon="close" color="grey" @click.stop="clearFile" class="q-mt-xs" label="Andere Datei" />
              </div>
            </div>
          </q-card-section>

          <!-- Preview Results -->
          <q-card-section v-if="preview" class="q-pt-none">
            <q-separator class="q-mb-md" />

            <div class="text-subtitle2" style="font-weight: 600; color: #1a1a2e;">Vorschau</div>

            <div class="row q-gutter-sm q-mt-sm">
              <q-card flat v-for="stat in previewStats" :key="stat.label" class="col" style="border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc;">
                <q-card-section class="q-pa-sm text-center">
                  <div style="font-size: 22px; font-weight: 700; color: #1d4ed8;">{{ stat.value }}</div>
                  <div style="font-size: 11px; color: #64748b;">{{ stat.label }}</div>
                </q-card-section>
              </q-card>
            </div>

            <!-- Lieferant -->
            <div v-if="preview.supplier_name" class="q-mt-md q-pa-sm" style="border-radius: 8px; background: #f0f9ff; border: 1px solid #bae6fd;">
              <q-icon name="local_shipping" color="primary" class="q-mr-xs" />
              <span style="font-weight: 600; color: #1a1a2e;">Lieferant: </span>
              <span style="color: #475569;">{{ preview.supplier_name }}</span>
            </div>

            <!-- Beispiel-Artikel -->
            <div v-if="preview.sample_articles?.length" class="q-mt-md">
              <div style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Beispiel-Artikel</div>
              <q-list dense separator class="q-mt-xs" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                <q-item v-for="(article, idx) in preview.sample_articles" :key="idx" dense>
                  <q-item-section>
                    <q-item-label style="font-weight: 500; color: #1a1a2e; font-size: 13px;">{{ article.name }}</q-item-label>
                    <q-item-label caption>Art.Nr: {{ article.article_number }} · {{ article.unit }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <span style="font-weight: 600; color: #1d4ed8;">{{ article.list_price ? formatPrice(article.list_price) + ' €' : '-' }}</span>
                  </q-item-section>
                </q-item>
              </q-list>
            </div>
          </q-card-section>

          <!-- Import Settings & Button -->
          <q-card-section v-if="preview" class="q-pt-none">
            <q-separator class="q-mb-md" />

            <div class="text-subtitle2 q-mb-sm" style="font-weight: 600; color: #1a1a2e;">Import-Einstellungen</div>

            <div class="row q-gutter-sm">
              <q-input
                v-model="importSettings.supplier_name"
                outlined
                dense
                label="Lieferant"
                class="col"
                style="font-size: 13px;"
              />
              <q-input
                v-model.number="importSettings.default_markup_percent"
                outlined
                dense
                label="Aufschlag %"
                type="number"
                suffix="%"
                class="col-3"
                style="font-size: 13px;"
              />
            </div>

            <div class="row q-gutter-md q-mt-xs">
              <q-toggle v-model="importSettings.update_existing" label="Bestehende aktualisieren" dense color="primary" />
              <q-toggle v-model="importSettings.overwrite_prices" label="Preise überschreiben" dense color="primary" />
            </div>

            <q-btn
              color="primary"
              icon="cloud_upload"
              :label="`${preview.estimated_articles} Artikel importieren`"
              no-caps
              class="full-width q-mt-md"
              style="border-radius: 10px; font-weight: 600;"
              :loading="importing"
              @click="startImport"
            />
          </q-card-section>

          <!-- Import Result -->
          <q-card-section v-if="importResult" class="q-pt-none">
            <q-separator class="q-mb-md" />

            <q-banner :class="importResult.status === 'completed' ? 'bg-green-1' : 'bg-red-1'" rounded style="border-radius: 10px;">
              <template v-slot:avatar>
                <q-icon :name="importResult.status === 'completed' ? 'check_circle' : 'error'" :color="importResult.status === 'completed' ? 'positive' : 'negative'" />
              </template>
              <div style="font-weight: 600;">
                {{ importResult.status === 'completed' ? 'Import erfolgreich!' : 'Import fehlgeschlagen' }}
              </div>
              <div v-if="importResult.status === 'completed'" style="font-size: 13px; color: #475569;" class="q-mt-xs">
                {{ importResult.imported_count }} neu importiert ·
                {{ importResult.updated_count }} aktualisiert ·
                {{ importResult.skipped_count }} übersprungen
                <span v-if="importResult.error_count"> · {{ importResult.error_count }} Fehler</span>
              </div>
              <div v-else style="font-size: 13px; color: #dc2626;">
                {{ importResult.errors?.[0]?.message || 'Unbekannter Fehler' }}
              </div>
            </q-banner>

            <q-btn
              flat
              color="primary"
              icon="arrow_forward"
              label="Zum Materialkatalog"
              no-caps
              :to="{ name: 'materials' }"
              class="q-mt-sm"
            />
          </q-card-section>
        </q-card>
      </div>

      <!-- Sidebar: Import History + Info -->
      <div class="col-12 col-md-5">
        <!-- Info Card -->
        <q-card flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #fff;" class="q-mb-md">
          <q-card-section>
            <div class="row items-center q-gutter-xs q-mb-sm">
              <q-icon name="info" color="primary" />
              <span style="font-weight: 600; color: #1a1a2e;">Was ist Datanorm?</span>
            </div>
            <p style="color: #64748b; font-size: 13px; line-height: 1.5; margin: 0;">
              Datanorm ist der Standard-Datenaustausch im deutschen Handwerk. Ihr Großhändler stellt
              Artikelkataloge als .dat Dateien bereit – mit Artikelnummern, Beschreibungen, Einheiten
              und Preisen. Durch den Import sparen Sie sich die manuelle Eingabe tausender Materialien.
            </p>
          </q-card-section>
        </q-card>

        <!-- Supported Suppliers -->
        <q-card flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #fff;" class="q-mb-md">
          <q-card-section>
            <div style="font-weight: 600; color: #1a1a2e; font-size: 14px;" class="q-mb-sm">Unterstützte Formate</div>
            <q-list dense>
              <q-item v-for="fmt in supportedFormats" :key="fmt.name" dense class="q-px-none">
                <q-item-section avatar style="min-width: 32px;">
                  <q-icon name="check_circle" color="positive" size="18px" />
                </q-item-section>
                <q-item-section>
                  <q-item-label style="font-size: 13px; color: #1a1a2e;">{{ fmt.name }}</q-item-label>
                  <q-item-label caption style="font-size: 11px;">{{ fmt.desc }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>

        <!-- Import History -->
        <q-card flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #fff;">
          <q-card-section>
            <div style="font-weight: 600; color: #1a1a2e; font-size: 14px;" class="q-mb-sm">Import-Verlauf</div>

            <div v-if="imports.length === 0" class="text-center q-py-md">
              <q-icon name="history" size="32px" style="color: #cbd5e1;" />
              <div style="color: #94a3b8; font-size: 13px;" class="q-mt-xs">Noch keine Imports</div>
            </div>

            <q-list v-else dense separator>
              <q-item v-for="imp in imports" :key="imp.id" dense class="q-px-none">
                <q-item-section avatar style="min-width: 32px;">
                  <q-icon
                    :name="imp.status === 'completed' ? 'check_circle' : imp.status === 'failed' ? 'error' : 'hourglass_empty'"
                    :color="imp.status === 'completed' ? 'positive' : imp.status === 'failed' ? 'negative' : 'warning'"
                    size="18px"
                  />
                </q-item-section>
                <q-item-section>
                  <q-item-label style="font-size: 13px; font-weight: 500; color: #1a1a2e;">
                    {{ imp.original_filename }}
                  </q-item-label>
                  <q-item-label caption style="font-size: 11px;">
                    {{ imp.supplier_name || 'Unbekannt' }} · {{ formatDate(imp.created_at) }}
                  </q-item-label>
                  <q-item-label v-if="imp.status === 'completed'" caption style="font-size: 11px;">
                    {{ imp.imported_count }} neu, {{ imp.updated_count }} aktualisiert
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-btn flat dense round icon="delete" size="sm" color="grey" @click="deleteImport(imp)" />
                </q-item-section>
              </q-item>
            </q-list>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

export default {
  name: 'DatanormImportPage',
  setup() {
    const $q = useQuasar()
    const fileInput = ref(null)
    const selectedFile = ref(null)
    const isDragover = ref(false)
    const preview = ref(null)
    const previewing = ref(false)
    const importing = ref(false)
    const importResult = ref(null)
    const imports = ref([])

    const importSettings = reactive({
      supplier_name: '',
      default_markup_percent: 30,
      update_existing: true,
      overwrite_prices: true,
    })

    const supportedFormats = [
      { name: 'Datanorm 4', desc: 'Feste Feldlängen (.dat)' },
      { name: 'Datanorm 5', desc: 'Semikolon-getrennt (.dat, .csv)' },
      { name: 'Preisdateien', desc: 'P-Sätze für Preisupdates' },
      { name: 'Großhändler', desc: 'R+F, C&G, GC Gruppe, u.a.' },
    ]

    const previewStats = computed(() => {
      if (!preview.value) return []
      return [
        { value: preview.value.estimated_articles, label: 'Artikel' },
        { value: preview.value.a_records, label: 'A-Sätze' },
        { value: preview.value.b_records, label: 'Langtexte' },
        { value: preview.value.p_records, label: 'Preisdaten' },
      ]
    })

    const triggerFileInput = () => fileInput.value?.click()

    const onFileSelect = (event) => {
      const file = event.target.files[0]
      if (file) handleFile(file)
    }

    const onDrop = (event) => {
      isDragover.value = false
      const file = event.dataTransfer.files[0]
      if (file) handleFile(file)
    }

    const handleFile = async (file) => {
      selectedFile.value = file
      preview.value = null
      importResult.value = null
      previewing.value = true

      const formData = new FormData()
      formData.append('file', file)

      try {
        const res = await api.post('/datanorm/preview', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        preview.value = res.data

        // Lieferant vorausfüllen
        if (res.data.supplier_name) {
          importSettings.supplier_name = res.data.supplier_name
        }
      } catch (e) {
        $q.notify({
          type: 'negative',
          message: e.response?.data?.message || 'Fehler beim Lesen der Datei',
        })
        selectedFile.value = null
      } finally {
        previewing.value = false
      }
    }

    const clearFile = () => {
      selectedFile.value = null
      preview.value = null
      importResult.value = null
      if (fileInput.value) fileInput.value.value = ''
    }

    const startImport = async () => {
      if (!selectedFile.value) return

      importing.value = true
      importResult.value = null

      const formData = new FormData()
      formData.append('file', selectedFile.value)
      formData.append('supplier_name', importSettings.supplier_name)
      formData.append('default_markup_percent', importSettings.default_markup_percent)
      formData.append('update_existing', importSettings.update_existing ? '1' : '0')
      formData.append('overwrite_prices', importSettings.overwrite_prices ? '1' : '0')

      try {
        const res = await api.post('/datanorm/import', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })
        importResult.value = res.data.import

        $q.notify({
          type: 'positive',
          message: res.data.message,
          timeout: 5000,
        })

        // History neu laden
        loadImports()

      } catch (e) {
        const errorData = e.response?.data
        if (errorData?.import) {
          importResult.value = errorData.import
        }
        $q.notify({
          type: 'negative',
          message: errorData?.message || 'Import fehlgeschlagen',
        })
      } finally {
        importing.value = false
      }
    }

    const loadImports = async () => {
      try {
        const res = await api.get('/datanorm')
        imports.value = res.data.imports || []
      } catch (e) {
        console.error('Failed to load imports:', e)
      }
    }

    const deleteImport = (imp) => {
      $q.dialog({
        title: 'Import löschen?',
        message: `Import "${imp.original_filename}" wirklich löschen? Die importierten Materialien bleiben erhalten.`,
        cancel: true,
        color: 'negative',
      }).onOk(async () => {
        try {
          await api.delete(`/datanorm/${imp.id}`)
          await loadImports()
          $q.notify({ type: 'positive', message: 'Import gelöscht' })
        } catch (e) {
          $q.notify({ type: 'negative', message: 'Fehler beim Löschen' })
        }
      })
    }

    const formatPrice = (val) => Number(val || 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

    const formatFileSize = (bytes) => {
      if (bytes < 1024) return bytes + ' B'
      if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
      return (bytes / 1048576).toFixed(1) + ' MB'
    }

    const formatDate = (dateStr) => {
      if (!dateStr) return ''
      const d = new Date(dateStr)
      return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    }

    onMounted(loadImports)

    return {
      fileInput, selectedFile, isDragover, preview, previewing, importing, importResult,
      imports, importSettings, supportedFormats, previewStats,
      triggerFileInput, onFileSelect, onDrop, clearFile, startImport, deleteImport,
      formatPrice, formatFileSize, formatDate,
    }
  },
}
</script>

<style scoped>
.datanorm-dropzone {
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  background: #f8fafc;
  transition: all 0.2s ease;
}
.datanorm-dropzone:hover {
  border-color: #1d4ed8;
  background: #eff6ff;
}
.datanorm-dropzone.dragover {
  border-color: #1d4ed8;
  background: #dbeafe;
}
.datanorm-dropzone.has-file {
  border-style: solid;
  border-color: #1d4ed8;
  background: #eff6ff;
}
.hidden {
  display: none;
}
</style>
