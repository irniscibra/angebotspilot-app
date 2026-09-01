<template>
  <q-page class="upgrade-page">
    <div class="upgrade-container">

      <!-- Header -->
      <div class="upgrade-header">
        <img src="~assets/angebotspilot-logo.png" alt="AngebotsPilot" style="height: 52px;" />
        <div class="upgrade-title">
          {{ authStore.trialExpired ? 'Ihr Testzeitraum ist abgelaufen' : 'Jetzt upgraden' }}
        </div>
        <div class="upgrade-subtitle">
          Voller Zugriff auf alle Features – fair berechnet pro Nutzer.
        </div>
        <q-chip
          v-if="authStore.trialExpired"
          color="negative"
          text-color="white"
          icon="lock"
          label="Testzeitraum abgelaufen"
          class="q-mt-md"
        />
        <q-chip
          v-else-if="authStore.plan === 'trial'"
          color="warning"
          text-color="white"
          icon="schedule"
          :label="`Noch ${authStore.trialDaysLeft} ${authStore.trialDaysLeft === 1 ? 'Tag' : 'Tage'} im Test`"
          class="q-mt-md"
        />
      </div>

           <!-- Pricing Cards – Zwei Pläne -->
      <div class="pricing-center pricing-center--two">
        <div class="pricing-card pricing-card--featured">
          <div class="plan-badge">Alle Features inklusive</div>

          <div class="plan-name">AngebotsPilot</div>

                    <div class="plan-price">
            <span class="price-amount">39</span>
            <span class="price-currency">€</span>
            <div class="price-right">
              <div class="price-period">pro Nutzer / Monat</div>
              <div class="price-note">Gemäß §19 UStG ohne MwSt. · monatlich kündbar</div>
            </div>
          </div>

          <div class="qty-selector">
            <span class="qty-label">Anzahl Nutzer</span>
            <div class="qty-stepper">
              <q-btn
                round
                dense
                flat
                icon="remove"
                text-color="white"
                @click="decrementQty"
                :disable="quantity <= 1"
              />
              <span class="qty-value">{{ quantity }}</span>
              <q-btn
                round
                dense
                flat
                icon="add"
                text-color="white"
                @click="incrementQty"
              />
            </div>
          </div>

                   <div class="qty-total">
            Monatlich gesamt: <strong>{{ totalPrice }} €</strong>
          </div>

          <q-separator dark class="q-my-lg" />

          <div class="features-grid">
            <div class="feature-item">✅ Unbegrenzte Angebote</div>
            <div class="feature-item">✅ Unbegrenzte Rechnungen</div>
            <div class="feature-item">✅ KI-Angebotserstellung in 30 Sek.</div>
            <div class="feature-item">✅ Datanorm-Import (Würth, Rexel...)</div>
            <div class="feature-item">✅ Digitale Kundenunterschrift</div>
            <div class="feature-item">✅ Online-Angebotsannahme</div>
            <div class="feature-item">✅ DATEV-Export</div>
            <div class="feature-item">✅ Professionelles Mahnwesen</div>
            <div class="feature-item">✅ GoBD-konforme Rechnungen</div>
            <div class="feature-item">✅ Abnahmeprotokolle</div>
            <div class="feature-item">✅ PDF-Generierung</div>
            <div class="feature-item">✅ E-Mail Support</div>
          </div>

              <q-btn
            color="white"
            text-color="primary"
            label="Jetzt abonnieren"
            unelevated
            class="full-width q-mt-xl"
            size="lg"
            icon-right="arrow_forward"
            :loading="checkoutLoading"
            @click="subscribe"
          />

                 <div class="guarantee-text">
            🔒 Ihre Daten bleiben sicher · Jederzeit kündbar
          </div>
        </div>

        <!-- Pro Plan -->
        <div class="pricing-card pricing-card--pro">
          <div class="plan-badge plan-badge--pro">Für Ausschreibungen</div>

          <div class="plan-name">AngebotsPilot Pro</div>

          <div class="plan-price">
            <span class="price-amount">69</span>
            <span class="price-currency">€</span>
            <div class="price-right">
              <div class="price-period">pro Firma / Monat</div>
              <div class="price-note">Gemäß §19 UStG ohne MwSt. · monatlich kündbar</div>
            </div>
          </div>

          <q-separator dark class="q-my-lg" />

          <div class="feature-item feature-item--highlight">
            🎯 Alles aus Starter, plus:
          </div>
          <div class="features-grid q-mt-sm">
            <div class="feature-item">✅ LV-Import für Ausschreibungen</div>
            <div class="feature-item">✅ PDF automatisch einlesen</div>
            <div class="feature-item">✅ KI-Marktpreisschätzung</div>
          </div>

          <q-btn
            color="white"
            text-color="purple-10"
            label="Pro abonnieren"
            unelevated
            class="full-width q-mt-xl"
            size="lg"
            icon-right="arrow_forward"
            :loading="checkoutLoadingPro"
            @click="subscribePro"
          />

          <div class="guarantee-text">
            🔒 Ihre Daten bleiben sicher · Jederzeit kündbar
          </div>
        </div>
      </div>

      <!-- Beispielrechnung -->


      <!-- Rechtliche Hinweise -->
      <div class="legal-box">
        <div class="legal-title">⚖️ Rechtliche Informationen</div>
                <div class="legal-text">
          Gemäß §19 UStG (Kleinunternehmerregelung) wird keine Umsatzsteuer berechnet oder ausgewiesen.
          Das Abonnement verlängert sich automatisch um einen weiteren Monat, sofern es nicht
          spätestens <strong>14 Tage vor Verlängerungsdatum</strong> schriftlich per E-Mail gekündigt wird.
          <br><br>
          Kündigung an: <a href="mailto:info@angebotspilot.app">info@angebotspilot.app</a>
          <br><br>
          Ihre gespeicherten Daten (Angebote, Rechnungen, Kunden) bleiben nach Ablauf des Testzeitraums
          für <strong>30 Tage</strong> erhalten und werden danach unwiderruflich gelöscht.
          Mit dem Abschluss eines Abonnements stimmen Sie unseren
          <a href="https://angebotspilot.app/agb" target="_blank">AGB</a> und der
          <a href="https://angebotspilot.app/datenschutz" target="_blank">Datenschutzerklärung</a> zu.
        </div>
      </div>

      <!-- Kontakt -->
      <div class="contact-box">
        <q-icon name="help_outline" size="20px" color="grey-6" />
        <span>Fragen? Wir helfen gerne:</span>
        <a href="mailto:info@angebotspilot.app">info@angebotspilot.app</a>
        <span class="q-mx-sm">·</span>
        <a href="tel:+491629867099">+49 162 9867099</a>
      </div>

    </div>

    <!-- Coming Soon Dialog -->
    <q-dialog v-model="comingSoonDialog">
      <q-card style="min-width: 400px">
        <q-card-section class="text-center q-pt-lg">
          <q-icon name="rocket_launch" size="52px" color="primary" />
          <div class="text-h6 q-mt-md">Online-Zahlung kommt bald!</div>
        </q-card-section>
        <q-card-section class="text-center">
          <p class="text-grey-7">
            Die automatische Stripe-Bezahlung wird gerade eingerichtet.<br>
            Kontaktieren Sie uns direkt – wir aktivieren Ihren Zugang <strong>innerhalb von 24 Stunden</strong>.
          </p>
          <q-chip color="primary" text-color="white" icon="mail" label="info@angebotspilot.app" />
        </q-card-section>
        <q-card-actions align="center" class="q-pb-lg q-gutter-sm">
          <q-btn
            color="primary"
            label="E-Mail senden"
            unelevated
            icon="mail"
            @click="openMail"
          />
          <q-btn flat label="Schließen" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>

  </q-page>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from 'src/stores/auth'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

const authStore = useAuthStore()
const $q = useQuasar()

const quantity = ref(1)
const checkoutLoading = ref(false)
const checkoutLoadingPro = ref(false)

const totalPrice = computed(() =>
  (quantity.value * 39).toLocaleString('de-DE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
)

function incrementQty() {
  quantity.value++
}
function decrementQty() {
  if (quantity.value > 1) quantity.value--
}

async function subscribe() {
  checkoutLoading.value = true
  try {
    const res = await api.post('/stripe/checkout', {
      plan: 'starter',
      quantity: quantity.value,
    })
    window.location.href = res.data.checkout_url
  } catch (e) {
    $q.notify({
      type: 'negative',
      message:
        e.response?.data?.message ||
        'Checkout konnte nicht gestartet werden. Bitte versuchen Sie es erneut.',
    })
  } finally {
    checkoutLoading.value = false
  }
}

async function subscribePro() {
  checkoutLoadingPro.value = true
  try {
    const res = await api.post('/stripe/checkout', {
      plan: 'pro',
    })
    window.location.href = res.data.checkout_url
  } catch (e) {
    $q.notify({
      type: 'negative',
      message:
        e.response?.data?.message ||
        'Checkout konnte nicht gestartet werden. Bitte versuchen Sie es erneut.',
    })
  } finally {
    checkoutLoadingPro.value = false
  }
}
</script>

<style scoped>
.upgrade-page {
  background: linear-gradient(135deg, #f0f4ff 0%, #f8fafc 100%);
  min-height: 100vh;
}
.upgrade-container {
  max-width: 680px;
  margin: 0 auto;
  padding: 48px 24px;
}
.upgrade-header {
  text-align: center;
  margin-bottom: 40px;
}
.upgrade-title {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
  margin-top: 20px;
}
.upgrade-subtitle {
  font-size: 16px;
  color: #64748b;
  margin-top: 8px;
}

/* Pricing */
.pricing-center { display: flex; justify-content: center; margin-bottom: 32px; }
.pricing-card {
  width: 100%;
  background: white;
  border-radius: 20px;
  padding: 36px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 24px rgba(0,0,0,0.06);
  position: relative;
}
.pricing-card--featured {
  background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
  border-color: #1e40af;
  color: white;
}
.plan-badge {
  display: inline-block;
  background: #f59e0b;
  color: white;
  padding: 5px 18px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 16px;
}
.plan-name {
  font-size: 22px;
  font-weight: 800;
  color: white;
  margin-bottom: 16px;
}
.plan-price {
  display: flex;
  align-items: center;
  gap: 8px;
}
.price-amount {
  font-size: 64px;
  font-weight: 800;
  color: white;
  line-height: 1;
}
.price-currency {
  font-size: 28px;
  font-weight: 700;
  color: rgba(255,255,255,0.9);
  margin-top: 8px;
}
.price-right { display: flex; flex-direction: column; justify-content: center; }
.price-period { font-size: 16px; color: rgba(255,255,255,0.9); font-weight: 600; }
.price-note { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 2px; }

.features-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.feature-item {
  font-size: 14px;
  color: rgba(255,255,255,0.9);
  padding: 4px 0;
}
.guarantee-text {
  text-align: center;
  font-size: 13px;
  color: rgba(255,255,255,0.6);
  margin-top: 16px;
}

/* Beispiel */
.example-box {
  background: white;
  border-radius: 14px;
  padding: 24px 28px;
  border: 1px solid #e2e8f0;
  margin-bottom: 20px;
}
.example-title {
  font-size: 15px;
  font-weight: 700;
  color: #334155;
  margin-bottom: 16px;
}
.example-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 16px;
  text-align: center;
}
.example-users { font-size: 14px; color: #64748b; font-weight: 600; }
.example-price { font-size: 18px; color: #1d4ed8; font-weight: 800; margin: 4px 0; }
.example-note { font-size: 11px; color: #94a3b8; }

/* Legal */
.legal-box {
  background: white;
  border-radius: 12px;
  padding: 24px 28px;
  border: 1px solid #e2e8f0;
  margin-bottom: 20px;
}
.legal-title { font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 10px; }
.legal-text { font-size: 13px; color: #64748b; line-height: 1.7; }
.legal-text a { color: #1d4ed8; }

/* Kontakt */
.contact-box {
  text-align: center;
  font-size: 14px;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  flex-wrap: wrap;
}
.contact-box a { color: #1d4ed8; font-weight: 600; text-decoration: none; }

.qty-selector {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 20px;
}
.qty-label {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.85);
  font-weight: 600;
}
.qty-stepper {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  padding: 2px 8px;
}
.qty-value {
  font-size: 16px;
  font-weight: 700;
  color: white;
  min-width: 24px;
  text-align: center;
}
.qty-total {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.9);
  margin-top: 12px;
  text-align: right;
}

.pricing-center--two {
  display: flex;
  gap: 24px;
  align-items: stretch;
  flex-wrap: wrap;
  justify-content: center;
  max-width: 1040px;
  margin: 0 auto;
  width: 100vw;
  position: relative;
  left: 50%;
  right: 50%;
  margin-left: -520px;
  margin-right: -520px;
}
@media (max-width: 1088px) {
  .pricing-center--two {
    width: auto;
    left: auto;
    right: auto;
    margin-left: auto;
    margin-right: auto;
  }
}
.pricing-center--two .pricing-card {
  flex: 1 1 320px;
  max-width: 360px;
}
.pricing-card--pro {
  background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 100%);
  border-color: #6d28d9;
  color: white;
}
.plan-badge--pro {
  background: #a78bfa;
}
.feature-item--highlight {
  font-size: 13px;
  font-weight: 700;
  color: white;
  margin-bottom: 4px;
}
</style>
