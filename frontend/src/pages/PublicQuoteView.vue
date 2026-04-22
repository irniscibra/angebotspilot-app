<template>
  <div style="min-height: 100vh; background: #f6f9fc; font-family: 'Inter', sans-serif;">

    <!-- Loading -->
    <div v-if="loading" class="flex flex-center" style="height: 100vh;">
      <div class="text-center">
        <q-spinner-orbit color="primary" size="60px" />
        <div style="margin-top: 16px; color: #64748b; font-size: 14px;">Angebot wird geladen...</div>
      </div>
    </div>

    <!-- Fehler -->
    <div v-else-if="error" class="flex flex-center" style="height: 100vh;">
      <div class="text-center q-pa-xl">
        <q-icon name="error_outline" size="80px" color="negative" />
        <div style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 16px;">{{ error }}</div>
        <div style="color: #64748b; margin-top: 8px;">Bitte kontaktieren Sie den Handwerker.</div>
      </div>
    </div>

    <!-- Angebot angenommen -->
    <div v-else-if="accepted" class="flex flex-center" style="height: 100vh;">
      <div class="text-center q-pa-xl" style="max-width: 500px;">
        <div style="width: 100px; height: 100px; border-radius: 50%; background: #dcfce7; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
          <q-icon name="check_circle" size="60px" color="positive" />
        </div>
        <div style="font-size: 26px; font-weight: 800; color: #0f172a;">Angebot angenommen! 🎉</div>
        <div style="color: #64748b; margin-top: 12px; font-size: 15px; line-height: 1.6;">
          Vielen Dank, <strong>{{ signerName }}</strong>!<br>
          Ihr Auftrag für <strong>{{ quote.project_title }}</strong> wurde verbindlich bestätigt.
        </div>
        <div style="margin-top: 20px; padding: 16px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
          <div style="font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Angenommen am</div>
          <div style="font-size: 16px; font-weight: 600; color: #16a34a; margin-top: 4px;">{{ acceptedAt }}</div>
        </div>
        <div style="margin-top: 16px; font-size: 13px; color: #94a3b8;">
          {{ company.name }} wird sich in Kürze bei Ihnen melden.
        </div>
      </div>
    </div>

    <!-- Angebot anzeigen -->
    <div v-else-if="quote" style="max-width: 900px; margin: 0 auto; padding: 24px 16px 80px;">

      <!-- Header Firma -->
      <div style="background: #ffffff; border-radius: 16px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
          <div>
            <img v-if="company.logo_url" :src="company.logo_url" style="max-height: 60px; max-width: 200px; object-fit: contain; margin-bottom: 8px;" />
            <div v-else style="font-size: 22px; font-weight: 800;" :style="`color: ${company.primary_color}`">{{ company.name }}</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.7; margin-top: 4px;">
              {{ company.address_street }}<br>
              {{ company.address_zip }} {{ company.address_city }}<br>
              <span v-if="company.phone">Tel: {{ company.phone }}<br></span>
              <span v-if="company.email">{{ company.email }}</span>
            </div>
          </div>
          <div style="text-align: right;">
            <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Angebots-Nr.</div>
            <div style="font-size: 18px; font-weight: 700; color: #0f172a;">{{ quote.quote_number }}</div>
            <div style="font-size: 12px; color: #94a3b8; margin-top: 8px;">Datum: {{ quote.created_at }}</div>
            <div style="font-size: 12px; color: #94a3b8;">Gültig bis: {{ quote.valid_until || '-' }}</div>
            <q-badge
              :color="statusColor"
              :label="statusLabel"
              style="margin-top: 8px; font-size: 11px; padding: 4px 10px;"
            />
          </div>
        </div>
      </div>

      <!-- Kunde + Projekt -->
      <div style="background: #ffffff; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
        <div style="display: flex; gap: 24px; flex-wrap: wrap;">
          <div v-if="customer" style="flex: 1;">
            <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Auftraggeber</div>
            <div style="font-size: 15px; font-weight: 600; color: #0f172a;">{{ customer.name }}</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.7;">
              {{ customer.address }}<br>{{ customer.zip }} {{ customer.city }}
            </div>
          </div>
          <div style="flex: 1;">
            <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Projekt</div>
            <div style="font-size: 15px; font-weight: 600; color: #0f172a;">{{ quote.project_title }}</div>
            <div v-if="quote.project_address" style="font-size: 13px; color: #64748b;">📍 {{ quote.project_address }}</div>
          </div>
        </div>
      </div>

      <!-- Intro Text -->
      <div v-if="quote.header_text" style="background: #ffffff; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); font-size: 14px; color: #475569; line-height: 1.7;">
        {{ quote.header_text }}
      </div>
      <div v-else style="background: #ffffff; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); font-size: 14px; color: #475569; line-height: 1.7;">
        Sehr geehrte Damen und Herren,<br><br>
        vielen Dank für Ihre Anfrage. Anbei erhalten Sie unser Angebot für die genannten Leistungen.
        Alle Preise verstehen sich in Euro netto zzgl. {{ quote.vat_rate }}% MwSt.
      </div>

      <!-- Positionen -->
      <div v-for="(items, groupName) in groupedItems" :key="groupName" style="margin-bottom: 16px;">
        <div :style="`background: ${company.primary_color}10; border-left: 4px solid ${company.primary_color}; padding: 10px 16px; border-radius: 0 8px 0 0; font-size: 13px; font-weight: 700; color: ${company.primary_color};`">
          {{ groupName || 'Positionen' }}
        </div>
        <div style="background: #ffffff; border-radius: 0 0 12px 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); overflow: hidden;">
          <!-- Tabellen Header -->
          <div style="display: grid; grid-template-columns: 1fr 80px 70px 100px 110px; gap: 8px; padding: 8px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">
            <div>Bezeichnung</div>
            <div style="text-align: right;">Menge</div>
            <div style="text-align: center;">Einheit</div>
            <div style="text-align: right;">Einzelpr.</div>
            <div style="text-align: right;">Gesamt</div>
          </div>
          <!-- Positionen -->
          <div
            v-for="(item, i) in items"
            :key="item.id"
            :style="`display: grid; grid-template-columns: 1fr 80px 70px 100px 110px; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; ${i % 2 === 1 ? 'background: #fafafa;' : ''}`"
          >
            <div>
              <div style="font-size: 13px; font-weight: 600; color: #0f172a;">{{ item.title }}</div>
              <div v-if="item.description" style="font-size: 11px; color: #94a3b8; margin-top: 2px; line-height: 1.4;">{{ item.description }}</div>
            </div>
            <div style="text-align: right; font-size: 13px; color: #475569; padding-top: 2px;">{{ formatNum(item.quantity) }}</div>
            <div style="text-align: center; font-size: 13px; color: #64748b; padding-top: 2px;">{{ item.unit }}</div>
            <div style="text-align: right; font-size: 13px; color: #475569; padding-top: 2px;">{{ formatPrice(item.unit_price) }} €</div>
            <div style="text-align: right; font-size: 13px; font-weight: 600; color: #0f172a; padding-top: 2px;">{{ formatPrice(item.total_price) }} €</div>
          </div>
        </div>
      </div>

      <!-- Summen -->
      <div style="background: #ffffff; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
        <div style="max-width: 320px; margin-left: auto;">
          <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9;">
            <span>Materialkosten</span><span style="font-weight: 500;">{{ formatPrice(quote.subtotal_materials) }} €</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #64748b; border-bottom: 1px solid #f1f5f9;">
            <span>Arbeitsleistung</span><span style="font-weight: 500;">{{ formatPrice(quote.subtotal_labor) }} €</span>
          </div>
          <div v-if="quote.discount_percent > 0" style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #16a34a; border-bottom: 1px solid #f1f5f9;">
            <span>Rabatt ({{ quote.discount_percent }}%)</span><span style="font-weight: 500;">-{{ formatPrice(quote.discount_amount) }} €</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #e2e8f0;">
            <span>Nettobetrag</span><span>{{ formatPrice(quote.subtotal_net) }} €</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px; color: #94a3b8;">
            <span>zzgl. MwSt. {{ quote.vat_rate }}%</span><span>{{ formatPrice(quote.vat_amount) }} €</span>
          </div>
          <div :style="`display: flex; justify-content: space-between; padding: 12px 16px; margin-top: 8px; border-radius: 10px; background: ${company.primary_color}; color: white;`">
            <span style="font-size: 16px; font-weight: 700;">Gesamtbetrag</span>
            <span style="font-size: 20px; font-weight: 800;">{{ formatPrice(quote.total_gross) }} €</span>
          </div>
        </div>
      </div>

      <!-- Zahlungsbedingungen -->
      <div style="background: #ffffff; border-radius: 16px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
        <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Zahlungsbedingungen</div>
        <div style="font-size: 13px; color: #64748b; line-height: 1.7;">
          {{ quote.terms_text || 'Zahlbar innerhalb von 14 Tagen nach Rechnungsstellung ohne Abzug.' }}
        </div>
      </div>

      <!-- Aktions-Buttons (nur wenn noch nicht angenommen/abgelehnt) -->
      <div v-if="quote.status !== 'accepted' && quote.status !== 'rejected'" style="background: #ffffff; border-radius: 16px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
        <div style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">Angebot annehmen</div>
        <div style="font-size: 13px; color: #64748b; margin-bottom: 20px;">Mit Ihrem Namen bestätigen Sie dieses Angebot verbindlich.</div>

        <q-input
          v-model="signerName"
          filled
          label="Ihr vollständiger Name *"
          placeholder="Max Mustermann"
          style="margin-bottom: 16px;"
        >
          <template v-slot:prepend><q-icon name="person" color="grey-5" /></template>
        </q-input>

        <!-- Unterschrifts-Pad -->
        <div style="margin-bottom: 16px;">
          <div style="font-size: 12px; color: #64748b; margin-bottom: 8px; font-weight: 600;">Unterschrift (mit Finger oder Maus):</div>
          <canvas
            ref="signaturePad"
            width="600"
            height="150"
            style="width: 100%; height: 150px; border: 2px dashed #e2e8f0; border-radius: 10px; cursor: crosshair; background: #fafafa; touch-action: none;"
            @mousedown="startDrawing"
            @mousemove="draw"
            @mouseup="stopDrawing"
            @mouseleave="stopDrawing"
            @touchstart.prevent="startDrawingTouch"
            @touchmove.prevent="drawTouch"
            @touchend="stopDrawing"
          ></canvas>
          <div style="display: flex; justify-content: flex-end; margin-top: 6px;">
            <q-btn flat dense size="sm" color="grey-6" icon="refresh" label="Löschen" no-caps @click="clearSignature" />
          </div>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <q-btn
            color="positive"
            icon="check_circle"
            label="Angebot verbindlich annehmen"
            no-caps
            :loading="accepting"
            :disable="!signerName || !hasSignature"
            @click="onAccept"
            style="flex: 1; border-radius: 10px; font-weight: 700; font-size: 15px; padding: 12px;"
          />
          <q-btn
            flat
            color="negative"
            icon="cancel"
            label="Ablehnen"
            no-caps
            :loading="rejecting"
            @click="onReject"
            style="border-radius: 10px;"
          />
        </div>

        <div style="font-size: 11px; color: #94a3b8; margin-top: 12px; text-align: center;">
          🔒 Ihre Daten sind sicher. Mit dem Annehmen stimmen Sie dem Angebot zu (§ 145 BGB).
          Datum & IP werden als Nachweis gespeichert.
        </div>
      </div>

      <!-- Bereits angenommen Banner -->
      <div v-if="quote.status === 'accepted'" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; padding: 20px; text-align: center;">
        <q-icon name="check_circle" color="positive" size="40px" />
        <div style="font-size: 16px; font-weight: 700; color: #16a34a; margin-top: 8px;">Dieses Angebot wurde bereits angenommen</div>
      </div>

      <!-- Footer -->
      <div style="text-align: center; margin-top: 32px; font-size: 11px; color: #cbd5e1;">
        {{ company.name }} ·
        <span v-if="company.tax_id">USt-IdNr.: {{ company.tax_id }} · </span>
        Erstellt mit AngebotsPilot
      </div>

    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import axios from 'axios'

export default {
  name: 'PublicQuoteView',
  setup() {
    const route = useRoute()
    const $q = useQuasar()

    const loading = ref(true)
    const error = ref(null)
    const quote = ref(null)
    const company = ref({})
    const customer = ref(null)
    const groupedItems = ref({})
    const accepted = ref(false)
    const acceptedAt = ref('')
    const signerName = ref('')
    const accepting = ref(false)
    const rejecting = ref(false)
    const signaturePad = ref(null)
    const isDrawing = ref(false)
    const hasSignature = ref(false)
    let ctx = null

    const uuid = route.params.uuid

    // Angebot laden
    const loadQuote = async () => {
      try {
        const res = await axios.get(`/api/public/quotes/${uuid}`)
        quote.value = res.data.quote
        company.value = res.data.company
        customer.value = res.data.customer
        groupedItems.value = res.data.grouped_items
      } catch (e) {
        if (e.response?.status === 410) {
          error.value = e.response?.data?.error || 'Dieses Angebot ist abgelaufen oder nicht mehr verfügbar.'
        } else {
          error.value = 'Angebot nicht gefunden.'
        }
      } finally {
        loading.value = false
      }
    }

    onMounted(() => {
      loadQuote()
    })

    // Canvas initialisieren
    const initCanvas = () => {
      if (!signaturePad.value) return
      const canvas = signaturePad.value
      canvas.width = canvas.offsetWidth * window.devicePixelRatio
      canvas.height = canvas.offsetHeight * window.devicePixelRatio
      ctx = canvas.getContext('2d')
      ctx.scale(window.devicePixelRatio, window.devicePixelRatio)
      ctx.strokeStyle = '#0f172a'
      ctx.lineWidth = 2
      ctx.lineCap = 'round'
      ctx.lineJoin = 'round'
    }

    const getPos = (e, canvas) => {
      const rect = canvas.getBoundingClientRect()
      return {
        x: (e.clientX - rect.left),
        y: (e.clientY - rect.top),
      }
    }

    const startDrawing = (e) => {
      if (!ctx) initCanvas()
      isDrawing.value = true
      const pos = getPos(e, signaturePad.value)
      ctx.beginPath()
      ctx.moveTo(pos.x, pos.y)
    }

    const draw = (e) => {
      if (!isDrawing.value || !ctx) return
      const pos = getPos(e, signaturePad.value)
      ctx.lineTo(pos.x, pos.y)
      ctx.stroke()
      hasSignature.value = true
    }

    const stopDrawing = () => { isDrawing.value = false }

    const startDrawingTouch = (e) => {
      if (!ctx) initCanvas()
      isDrawing.value = true
      const touch = e.touches[0]
      const rect = signaturePad.value.getBoundingClientRect()
      ctx.beginPath()
      ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top)
    }

    const drawTouch = (e) => {
      if (!isDrawing.value || !ctx) return
      const touch = e.touches[0]
      const rect = signaturePad.value.getBoundingClientRect()
      ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top)
      ctx.stroke()
      hasSignature.value = true
    }

    const clearSignature = () => {
      if (!ctx) return
      ctx.clearRect(0, 0, signaturePad.value.width, signaturePad.value.height)
      hasSignature.value = false
    }

    // Annehmen
    const onAccept = async () => {
      if (!signerName.value || !hasSignature.value) return
      accepting.value = true
      try {
        const signatureData = signaturePad.value.toDataURL('image/png')
        const res = await axios.post(`/api/public/quotes/${uuid}/accept`, {
          signer_name: signerName.value,
          signature: signatureData,
        })
        acceptedAt.value = res.data.accepted_at
        accepted.value = true
        window.scrollTo(0, 0)
      } catch (e) {
        $q.notify({ type: 'negative', message: e.response?.data?.error || 'Fehler beim Annehmen' })
      } finally {
        accepting.value = false
      }
    }

    // Ablehnen
    const onReject = async () => {
      $q.dialog({
        title: 'Angebot ablehnen?',
        message: 'Möchten Sie dieses Angebot wirklich ablehnen?',
        cancel: { label: 'Zurück', flat: true },
        ok: { label: 'Ja, ablehnen', color: 'negative' },
      }).onOk(async () => {
        rejecting.value = true
        try {
          await axios.post(`/api/public/quotes/${uuid}/reject`)
          quote.value.status = 'rejected'
          $q.notify({ type: 'warning', message: 'Angebot wurde abgelehnt.' })
        } catch (e) {
          $q.notify({ type: 'negative', message: 'Fehler beim Ablehnen' })
        } finally {
          rejecting.value = false
        }
      })
    }

    // Helpers
    const formatPrice = (val) => Number(val || 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    const formatNum = (val) => Number(val || 0).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

    const statusColor = computed(() => ({
      draft: 'grey', sent: 'blue', viewed: 'orange',
      accepted: 'positive', rejected: 'negative',
    }[quote.value?.status] || 'grey'))

    const statusLabel = computed(() => ({
      draft: 'Entwurf', sent: 'Gesendet', viewed: 'Gesehen',
      accepted: 'Angenommen', rejected: 'Abgelehnt',
    }[quote.value?.status] || ''))

    return {
      loading, error, quote, company, customer, groupedItems,
      accepted, acceptedAt, signerName, accepting, rejecting,
      signaturePad, hasSignature,
      onAccept, onReject, clearSignature,
      startDrawing, draw, stopDrawing,
      startDrawingTouch, drawTouch,
      formatPrice, formatNum, statusColor, statusLabel,
    }
  }
}
</script>