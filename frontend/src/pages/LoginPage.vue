<template>
  <q-page class="flex flex-center" style="min-height: 100vh">
    <div style="width: 420px; max-width: 90vw">

      <div class="text-center q-mb-xl">
        <div style="width: 48px; height: 48px; border-radius: 14px; background: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
          <q-icon name="bolt" color="white" size="24px" />
        </div>
        <h5 class="q-my-sm" style="font-weight: 800; color: #0f172a">AngebotsPilot</h5>
        <p style="color: #64748b; font-size: 14px">Melden Sie sich an</p>
      </div>

      <!-- ✅ E-Mail erfolgreich bestätigt -->
      <q-banner v-if="justVerified" rounded class="q-mb-md" style="background: #f0fdf4; border: 1px solid #86efac; color: #166534;">
        <template v-slot:avatar><q-icon name="check_circle" color="positive" /></template>
        <strong>E-Mail bestätigt!</strong> Sie können sich jetzt anmelden.
      </q-banner>

      <!-- ⚠️ E-Mail noch nicht bestätigt -->
      <q-banner v-if="notVerified" rounded class="q-mb-md" style="background: #fffbeb; border: 1px solid #fcd34d; color: #92400e;">
        <template v-slot:avatar><q-icon name="mail" color="warning" /></template>
        <div>
          <strong>E-Mail nicht bestätigt.</strong><br>
          Bitte klicken Sie auf den Link in der Bestätigungs-E-Mail.
        </div>
        <template v-slot:action>
          <q-btn
            flat
            dense
            color="warning"
            label="Erneut senden"
            :loading="resendLoading"
            @click="resendVerification"
          />
        </template>
      </q-banner>

      <q-card flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff;">
        <q-card-section class="q-pa-lg">
          <q-form @submit="onLogin" class="q-gutter-md">
            <q-input
              v-model="email"
              filled
              label="E-Mail"
              type="email"
              :rules="[(val) => !!val || 'E-Mail ist erforderlich']"
            >
              <template v-slot:prepend><q-icon name="email" color="grey-5" /></template>
            </q-input>
            <q-input
              v-model="password"
              filled
              label="Passwort"
              :type="showPw ? 'text' : 'password'"
              :rules="[(val) => !!val || 'Passwort ist erforderlich']"
            >
              <template v-slot:prepend><q-icon name="lock" color="grey-5" /></template>
              <template v-slot:append>
                <q-icon :name="showPw ? 'visibility_off' : 'visibility'" class="cursor-pointer" color="grey-5" @click="showPw = !showPw" />
              </template>
            </q-input>
            <q-btn
              type="submit"
              color="primary"
              label="Anmelden"
              class="full-width"
              size="lg"
              no-caps
              :loading="loading"
              style="border-radius: 10px; font-weight: 600"
            />
          </q-form>
        </q-card-section>
      </q-card>

         <p class="text-center q-mt-sm" style="color: #64748b; font-size: 13px">
        <router-link to="/auth/forgot-password" style="color: #64748b; text-decoration: none">Passwort vergessen?</router-link>
      </p>
      <p class="text-center q-mt-md" style="color: #64748b; font-size: 13px">
        Noch kein Konto?
        <router-link to="/auth/register" style="color: #1d4ed8; font-weight: 600; text-decoration: none">Jetzt registrieren</router-link>
      </p>
    </div>
  </q-page>
</template>

<script>
import { ref, onMounted } from 'vue'
import { useAuthStore } from 'src/stores/auth'
import { useRouter, useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from 'src/boot/axios'

export default {
  name: 'LoginPage',
  setup() {
    const authStore = useAuthStore()
    const router = useRouter()
    const route = useRoute()
    const $q = useQuasar()

    const email = ref('')
    const password = ref('')
    const showPw = ref(false)
    const loading = ref(false)

    // E-Mail-Verifikations-Status
    const justVerified = ref(false)
    const notVerified = ref(false)
    const resendLoading = ref(false)

    onMounted(() => {
      // ?verified=1 → kam vom Verifikations-Link
      if (route.query.verified === '1') {
        justVerified.value = true
      }
    })

    const onLogin = async () => {
      loading.value = true
      notVerified.value = false
      justVerified.value = false
      try {
        await authStore.login(email.value, password.value)
        router.push('/dashboard')
      } catch (e) {
        const data = e.response?.data
        if (data?.email_not_verified) {
          notVerified.value = true
        } else {
          $q.notify({
            type: 'negative',
            message: data?.message || 'Login fehlgeschlagen',
          })
        }
      } finally {
        loading.value = false
      }
    }

    const resendVerification = async () => {
      if (!email.value) {
        $q.notify({ type: 'warning', message: 'Bitte geben Sie Ihre E-Mail-Adresse ein.' })
        return
      }
      resendLoading.value = true
      try {
        await api.post('/auth/email/resend', { email: email.value })
        $q.notify({ type: 'positive', message: 'Bestätigungs-E-Mail wurde erneut gesendet.' })
      } catch {
        $q.notify({ type: 'negative', message: 'Fehler beim Senden.' })
      } finally {
        resendLoading.value = false
      }
    }

    return {
      email, password, showPw, loading, onLogin,
      justVerified, notVerified, resendLoading, resendVerification,
    }
  },
}
</script>
