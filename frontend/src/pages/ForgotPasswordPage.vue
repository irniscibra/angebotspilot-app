<template>
  <q-page class="flex flex-center" style="min-height: 100vh">
    <div style="width: 420px; max-width: 90vw">

      <div class="text-center q-mb-xl">
        <div style="width: 48px; height: 48px; border-radius: 14px; background: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
          <q-icon name="lock_reset" color="white" size="24px" />
        </div>
        <h5 class="q-my-sm" style="font-weight: 800; color: #0f172a">AngebotsPilot</h5>
        <p style="color: #64748b; font-size: 14px">Passwort zurücksetzen</p>
      </div>

      <q-card v-if="!sent" flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff;">
        <q-card-section class="q-pa-lg">
          <p style="color: #64748b; font-size: 13.5px; line-height: 1.6" class="q-mb-md">
            Geben Sie Ihre E-Mail-Adresse ein. Wir senden Ihnen einen Link,
            mit dem Sie ein neues Passwort festlegen können.
          </p>
          <q-form @submit="onSubmit" class="q-gutter-md">
            <q-input
              v-model="email"
              filled
              label="E-Mail"
              type="email"
              :rules="[(val) => !!val || 'E-Mail ist erforderlich']"
            >
              <template v-slot:prepend><q-icon name="email" color="grey-5" /></template>
            </q-input>

            <q-banner v-if="error" rounded style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;">
              {{ error }}
            </q-banner>

            <q-btn
              type="submit"
              color="primary"
              label="Link anfordern"
              class="full-width"
              size="lg"
              no-caps
              :loading="loading"
              style="border-radius: 10px; font-weight: 600"
            />
          </q-form>
        </q-card-section>
      </q-card>

      <q-card v-else flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff;">
        <q-card-section class="q-pa-lg text-center">
          <q-icon name="mark_email_read" color="positive" size="48px" class="q-mb-md" />
          <p style="color: #0f172a; font-size: 15px; font-weight: 600">
            E-Mail unterwegs!
          </p>
          <p style="color: #64748b; font-size: 13.5px; line-height: 1.6">
            Falls ein Konto mit dieser E-Mail existiert, finden Sie in
            Kürze einen Link zum Zurücksetzen Ihres Passworts in Ihrem
            Postfach (ggf. auch im Spam-Ordner).
          </p>
        </q-card-section>
      </q-card>

      <p class="text-center q-mt-md" style="color: #64748b; font-size: 13px">
        <router-link to="/auth/login" style="color: #1d4ed8; font-weight: 600; text-decoration: none">← Zurück zur Anmeldung</router-link>
      </p>
    </div>
  </q-page>
</template>

<script>
import { ref } from "vue";
import { api } from "src/boot/axios";

export default {
  name: "ForgotPasswordPage",
  setup() {
    const email = ref("");
    const loading = ref(false);
    const error = ref("");
    const sent = ref(false);

    const onSubmit = async () => {
      loading.value = true;
      error.value = "";
      try {
        await api.post("/auth/forgot-password", { email: email.value });
        sent.value = true;
      } catch (e) {
        error.value =
          e.response?.data?.message || "Es ist ein Fehler aufgetreten.";
      } finally {
        loading.value = false;
      }
    };

    return { email, loading, error, sent, onSubmit };
  },
};
</script>