<template>
  <q-page class="flex flex-center" style="min-height: 100vh">
    <div style="width: 420px; max-width: 90vw">

      <div class="text-center q-mb-xl">
        <div style="width: 48px; height: 48px; border-radius: 14px; background: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
          <q-icon name="lock_open" color="white" size="24px" />
        </div>
        <h5 class="q-my-sm" style="font-weight: 800; color: #0f172a">AngebotsPilot</h5>
        <p style="color: #64748b; font-size: 14px">Neues Passwort festlegen</p>
      </div>

      <q-card v-if="!done" flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff;">
        <q-card-section class="q-pa-lg">
          <q-banner v-if="!token || !email" rounded style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;">
            Dieser Link ist unvollständig oder ungültig. Bitte fordern Sie
            einen neuen an.
          </q-banner>

          <q-form v-else @submit="onSubmit" class="q-gutter-md">
            <q-input
              v-model="password"
              filled
              label="Neues Passwort"
              :type="showPw ? 'text' : 'password'"
              :rules="[(val) => (!!val && val.length >= 8) || 'Mindestens 8 Zeichen']"
            >
              <template v-slot:prepend><q-icon name="lock" color="grey-5" /></template>
              <template v-slot:append>
                <q-icon :name="showPw ? 'visibility_off' : 'visibility'" class="cursor-pointer" color="grey-5" @click="showPw = !showPw" />
              </template>
            </q-input>

            <q-input
              v-model="passwordConfirmation"
              filled
              label="Passwort wiederholen"
              :type="showPw ? 'text' : 'password'"
              :rules="[(val) => val === password || 'Passwörter stimmen nicht überein']"
            >
              <template v-slot:prepend><q-icon name="lock" color="grey-5" /></template>
            </q-input>

            <q-banner v-if="error" rounded style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;">
              {{ error }}
            </q-banner>

            <q-btn
              type="submit"
              color="primary"
              label="Passwort speichern"
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
          <q-icon name="check_circle" color="positive" size="48px" class="q-mb-md" />
          <p style="color: #0f172a; font-size: 15px; font-weight: 600" class="q-mb-md">
            Passwort erfolgreich geändert!
          </p>
          <q-btn
            color="primary"
            label="Jetzt anmelden"
            no-caps
            class="full-width"
            style="border-radius: 10px; font-weight: 600"
            @click="$router.push('/auth/login')"
          />
        </q-card-section>
      </q-card>
    </div>
  </q-page>
</template>

<script>
import { ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { api } from "src/boot/axios";

export default {
  name: "ResetPasswordPage",
  setup() {
    const route = useRoute();
    const token = ref("");
    const email = ref("");
    const password = ref("");
    const passwordConfirmation = ref("");
    const showPw = ref(false);
    const loading = ref(false);
    const error = ref("");
    const done = ref(false);

    onMounted(() => {
      token.value = route.query.token || "";
      email.value = route.query.email || "";
    });

    const onSubmit = async () => {
      loading.value = true;
      error.value = "";
      try {
        await api.post("/auth/reset-password", {
          token: token.value,
          email: email.value,
          password: password.value,
          password_confirmation: passwordConfirmation.value,
        });
        done.value = true;
      } catch (e) {
        error.value =
          e.response?.data?.message ||
          "Das Passwort konnte nicht zurückgesetzt werden.";
      } finally {
        loading.value = false;
      }
    };

    return {
      token,
      email,
      password,
      passwordConfirmation,
      showPw,
      loading,
      error,
      done,
      onSubmit,
    };
  },
};
</script>