<template>
  <q-page class="register-page flex flex-center" style="min-height: 100vh;">

    <!-- Hintergrund -->
    <div class="bg-pattern"></div>

    <div class="register-wrapper">

      <!-- Links: Branding -->
      <div class="brand-side">
        <div class="brand-logo">
          <div class="logo-icon">
            <q-icon name="bolt" color="white" size="28px" />
          </div>
          <span class="logo-text">AngebotsPilot</span>
        </div>

        <div class="brand-content">
          <h2 class="brand-headline">Angebote in<br><span class="highlight">20 Sekunden.</span></h2>
          <p class="brand-sub">KI-gestützte Angebotserstellung für Handwerksbetriebe in Deutschland.</p>

          <div class="feature-list">
            <div class="feature-item" v-for="f in features" :key="f.text">
              <div class="feature-icon">
                <q-icon :name="f.icon" size="16px" color="white" />
              </div>
              <span>{{ f.text }}</span>
            </div>
          </div>
        </div>

        <div class="brand-footer">
          Bereits über <strong>200 Betriebe</strong> vertrauen AngebotsPilot
        </div>
      </div>

      <!-- Rechts: Formular -->
      <div class="form-side">
        <div class="form-header">
          <h5 class="form-title">Konto erstellen</h5>
          <p class="form-sub">14 Tage kostenlos · Keine Kreditkarte nötig</p>
        </div>

        <q-form @submit="onRegister" class="form-fields">

          <div class="field-row">
            <div class="field-group">
              <label class="field-label">Ihr Name</label>
              <q-input
                v-model="name"
                filled
                dense
                placeholder="Max Mustermann"
                :rules="[(val) => !!val || 'Pflichtfeld']"
                class="custom-input"
              >
                <template v-slot:prepend><q-icon name="person" color="grey-5" size="18px" /></template>
              </q-input>
            </div>
            <div class="field-group">
              <label class="field-label">Firmenname</label>
              <q-input
                v-model="companyName"
                filled
                dense
                placeholder="Mustermann GmbH"
                :rules="[(val) => !!val || 'Pflichtfeld']"
                class="custom-input"
              >
                <template v-slot:prepend><q-icon name="business" color="grey-5" size="18px" /></template>
              </q-input>
            </div>
          </div>

          <div class="field-group">
            <label class="field-label">E-Mail Adresse</label>
            <q-input
              v-model="email"
              filled
              dense
              type="email"
              placeholder="max@mustermann.de"
              :rules="[(val) => !!val || 'Pflichtfeld', (val) => /.+@.+\..+/.test(val) || 'Ungültige E-Mail']"
              class="custom-input"
            >
              <template v-slot:prepend><q-icon name="email" color="grey-5" size="18px" /></template>
            </q-input>
          </div>

          <div class="field-group">
            <label class="field-label">Einladungscode</label>
            <q-input
              v-model="inviteCode"
              filled
              dense
              placeholder="Einladungscode eingeben"
              :rules="[(val) => !!val || 'Einladungscode ist erforderlich']"
              class="custom-input"
            >
              <template v-slot:prepend><q-icon name="vpn_key" color="grey-5" size="18px" /></template>
            </q-input>
          </div>

          <div class="field-row">
            <div class="field-group">
              <label class="field-label">Passwort</label>
              <q-input
                v-model="password"
                filled
                dense
                type="password"
                placeholder="Mind. 8 Zeichen"
                :rules="[(val) => val.length >= 8 || 'Mind. 8 Zeichen']"
                class="custom-input"
              >
                <template v-slot:prepend><q-icon name="lock" color="grey-5" size="18px" /></template>
              </q-input>
            </div>
            <div class="field-group">
              <label class="field-label">Passwort bestätigen</label>
              <q-input
                v-model="passwordConfirm"
                filled
                dense
                type="password"
                placeholder="Wiederholen"
                :rules="[(val) => val === password || 'Stimmt nicht überein']"
                class="custom-input"
              >
                <template v-slot:prepend><q-icon name="lock" color="grey-5" size="18px" /></template>
              </q-input>
            </div>
          </div>

          <!-- AGB Checkbox -->
          <div class="agb-box">
            <q-checkbox
              v-model="agbAccepted"
              color="primary"
              dense
            />
            <span class="agb-text">
              Ich akzeptiere die
              <a href="https://angebotspilot.app/agb" target="_blank" class="agb-link">AGB</a>
              und die
              <a href="https://angebotspilot.app/datenschutz" target="_blank" class="agb-link">Datenschutzerklärung</a>
              von AngebotsPilot.
            </span>
          </div>

          <q-btn
            type="submit"
            color="primary"
            label="Kostenlos starten →"
            class="submit-btn full-width"
            size="md"
            no-caps
            :loading="loading"
            :disable="!agbAccepted"
          />

          <div class="login-hint">
            Bereits ein Konto?
            <router-link to="/auth/login" class="login-link">Anmelden</router-link>
          </div>

        </q-form>
      </div>

    </div>

  </q-page>
</template>

<script>
import { ref } from 'vue'
import { useAuthStore } from 'src/stores/auth'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'

export default {
  name: 'RegisterPage',
  setup() {
    const authStore = useAuthStore()
    const router = useRouter()
    const $q = useQuasar()

    const name = ref('')
    const inviteCode = ref('')
    const companyName = ref('')
    const email = ref('')
    const password = ref('')
    const passwordConfirm = ref('')
    const loading = ref(false)
    const agbAccepted = ref(false)

    const features = [
      { icon: 'bolt', text: 'KI-Angebote in unter 20 Sekunden' },
      { icon: 'picture_as_pdf', text: 'PDF-Import & Scan-Funktion' },
      { icon: 'draw', text: 'Digitale Online-Unterschrift' },
      { icon: 'analytics', text: 'KI-Preisanalyse mit Marktvergleich' },
      { icon: 'inventory_2', text: 'Datanorm & Katalog-Import' },
    ]

    const onRegister = async () => {
      if (!agbAccepted.value) {
        $q.notify({ type: 'warning', message: 'Bitte AGB und Datenschutz akzeptieren' })
        return
      }

      loading.value = true
      try {
        await authStore.register({
          name: name.value,
          invite_code: inviteCode.value,
          company_name: companyName.value,
          email: email.value,
          password: password.value,
          password_confirmation: passwordConfirm.value,
        })
        router.push('/dashboard')
      } catch (e) {
        $q.notify({
          type: 'negative',
          message: e.response?.data?.message || 'Registrierung fehlgeschlagen',
        })
      } finally {
        loading.value = false
      }
    }

    return {
      name, inviteCode, companyName, email,
      password, passwordConfirm, loading,
      agbAccepted, features, onRegister,
    }
  },
}
</script>

<style scoped>
.register-page {
  background: #f8fafc;
  position: relative;
  overflow: hidden;
}

.bg-pattern {
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse at 20% 50%, rgba(29, 78, 216, 0.08) 0%, transparent 60%),
    radial-gradient(ellipse at 80% 20%, rgba(16, 185, 129, 0.06) 0%, transparent 50%);
  pointer-events: none;
}

.register-wrapper {
  display: flex;
  width: 100%;
  max-width: 960px;
  min-height: 600px;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.12);
  margin: 24px 16px;
  position: relative;
  z-index: 1;
}

/* Brand Side */
.brand-side {
  flex: 1;
  background: linear-gradient(145deg, #1e40af 0%, #1d4ed8 50%, #2563eb 100%);
  padding: 40px 36px;
  display: flex;
  flex-direction: column;
  min-width: 300px;
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 48px;
}

.logo-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: rgba(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-text {
  font-size: 18px;
  font-weight: 800;
  color: white;
  letter-spacing: -0.3px;
}

.brand-content {
  flex: 1;
}

.brand-headline {
  font-size: 32px;
  font-weight: 800;
  color: white;
  line-height: 1.2;
  margin: 0 0 16px;
  letter-spacing: -0.5px;
}

.highlight {
  color: #93c5fd;
}

.brand-sub {
  color: rgba(255,255,255,0.75);
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 32px;
}

.feature-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 10px;
  color: rgba(255,255,255,0.9);
  font-size: 13px;
}

.feature-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: rgba(255,255,255,0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.brand-footer {
  color: rgba(255,255,255,0.6);
  font-size: 12px;
  margin-top: 32px;
}

.brand-footer strong {
  color: rgba(255,255,255,0.9);
}

/* Form Side */
.form-side {
  flex: 1.2;
  background: white;
  padding: 40px 36px;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.form-header {
  margin-bottom: 28px;
}

.form-title {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px;
  letter-spacing: -0.3px;
}

.form-sub {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

.form-fields {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.field-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.custom-input :deep(.q-field__control) {
  border-radius: 10px;
}

/* AGB */
.agb-box {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
}

.agb-text {
  font-size: 13px;
  color: #475569;
  line-height: 1.5;
}

.agb-link {
  color: #1d4ed8;
  font-weight: 600;
  text-decoration: none;
}

.agb-link:hover {
  text-decoration: underline;
}

/* Submit Button */
.submit-btn {
  border-radius: 10px !important;
  font-weight: 700 !important;
  font-size: 15px !important;
  padding: 12px !important;
  letter-spacing: -0.2px;
}

.login-hint {
  text-align: center;
  font-size: 13px;
  color: #64748b;
}

.login-link {
  color: #1d4ed8;
  font-weight: 600;
  text-decoration: none;
  margin-left: 4px;
}

/* Mobile */
@media (max-width: 700px) {
  .brand-side {
    display: none;
  }
  .field-row {
    grid-template-columns: 1fr;
  }
  .form-side {
    padding: 28px 20px;
  }
}
</style>