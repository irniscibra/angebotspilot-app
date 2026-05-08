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

      <!-- Pricing Card – Ein Plan -->
      <div class="pricing-center">
        <div class="pricing-card pricing-card--featured">
          <div class="plan-badge">Alle Features inklusive</div>

          <div class="plan-name">AngebotsPilot</div>

          <div class="plan-price">
            <span class="price-amount">39</span>
            <span class="price-currency">€</span>
            <div class="price-right">
              <div class="price-period">pro Nutzer / Monat</div>
              <div class="price-note">zzgl. 19% MwSt. · monatlich kündbar</div>
            </div>
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
            @click="subscribe"
          />

          <div class="guarantee-text">
            🔒 Ihre Daten bleiben sicher · Jederzeit kündbar
          </div>
        </div>
      </div>

      <!-- Beispielrechnung -->
      <div class="example-box">
        <div class="example-title">💡 Beispielrechnung</div>
        <div class="example-grid">
          <div class="example-item">
            <div class="example-users">1 Nutzer</div>
            <div class="example-price">39,00 € / Monat</div>
            <div class="example-note">zzgl. MwSt.</div>
          </div>
          <div class="example-item">
            <div class="example-users">3 Nutzer</div>
            <div class="example-price">117,00 € / Monat</div>
            <div class="example-note">zzgl. MwSt.</div>
          </div>
          <div class="example-item">
            <div class="example-users">5 Nutzer</div>
            <div class="example-price">195,00 € / Monat</div>
            <div class="example-note">zzgl. MwSt.</div>
          </div>
        </div>
      </div>

      <!-- Rechtliche Hinweise -->
      <div class="legal-box">
        <div class="legal-title">⚖️ Rechtliche Informationen</div>
        <div class="legal-text">
          Alle Preise verstehen sich netto zzgl. der gesetzlichen Umsatzsteuer von derzeit 19%.
          Das Abonnement verlängert sich automatisch um einen weiteren Monat, sofern es nicht
          spätestens <strong>14 Tage vor Verlängerungsdatum</strong> schriftlich per E-Mail gekündigt wird.
          <br><br>
          Kündigung an: <a href="mailto:info@nettwebsolutions.de">info@nettwebsolutions.de</a>
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
        <a href="mailto:info@nettwebsolutions.de">info@nettwebsolutions.de</a>
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
          <q-chip color="primary" text-color="white" icon="mail" label="info@nettwebsolutions.de" />
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
import { ref } from 'vue'
import { useAuthStore } from 'src/stores/auth'

const authStore = useAuthStore()
const comingSoonDialog = ref(false)

function subscribe() {
  // TODO: Stripe Checkout
  comingSoonDialog.value = true
}

function openMail() {
  const company = authStore.company?.name || ''
  window.location.href = `mailto:info@nettwebsolutions.de?subject=AngebotsPilot Abonnement – 39€/Nutzer&body=Hallo,%0A%0Aich möchte AngebotsPilot abonnieren (39€ pro Nutzer/Monat).%0A%0AUnternehmen: ${company}%0A%0AMit freundlichen Grüßen`
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
</style>
