<template>
  <q-page class="flex flex-center" style="min-height: 100vh">
    <div style="width: 400px; max-width: 90vw">
      <div class="text-center q-mb-xl">
        <div
          style="
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #1d4ed8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
          "
        >
          <q-icon name="bolt" color="white" size="24px" />
        </div>
        <h5 class="q-my-sm" style="font-weight: 800; color: #0f172a">
          AngebotsPilot
        </h5>
        <p style="color: #64748b; font-size: 14px">Melden Sie sich an</p>
      </div>
      <q-card
        flat
        style="
          border: 1px solid #e2e8f0;
          border-radius: 14px;
          background: #ffffff;
        "
      >
        <q-card-section class="q-pa-lg">
          <q-form @submit="onLogin" class="q-gutter-md">
            <q-input
              v-model="email"
              filled
              label="E-Mail"
              type="email"
              :rules="[(val) => !!val || 'E-Mail ist erforderlich']"
              style="border-radius: 8px"
            >
              <template v-slot:prepend
                ><q-icon name="email" color="grey-5"
              /></template>
            </q-input>
            <q-input
              v-model="password"
              filled
              label="Passwort"
              :type="showPw ? 'text' : 'password'"
              :rules="[(val) => !!val || 'Passwort ist erforderlich']"
            >
              <template v-slot:prepend
                ><q-icon name="lock" color="grey-5"
              /></template>
              <template v-slot:append
                ><q-icon
                  :name="showPw ? 'visibility_off' : 'visibility'"
                  class="cursor-pointer"
                  color="grey-5"
                  @click="showPw = !showPw"
              /></template>
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
      <p class="text-center q-mt-md" style="color: #64748b; font-size: 13px">
        Noch kein Konto?
        <router-link
          to="/auth/register"
          style="color: #1d4ed8; font-weight: 600; text-decoration: none"
          >Jetzt registrieren</router-link
        >
      </p>
    </div>
  </q-page>
</template>
<script>
import { ref } from "vue";
import { useAuthStore } from "src/stores/auth";
import { useRouter } from "vue-router";
import { useQuasar } from "quasar";
export default {
  name: "LoginPage",
  setup() {
    const authStore = useAuthStore();
    const router = useRouter();
    const $q = useQuasar();
    const email = ref("");
    const password = ref("");
    const showPw = ref(false);
    const loading = ref(false);
    const onLogin = async () => {
      loading.value = true;
      try {
        await authStore.login(email.value, password.value)
        router.push("/dashboard");
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Login fehlgeschlagen",
        });
      } finally {
        loading.value = false;
      }
    };
    return { email, password, showPw, loading, onLogin };
  },
};
</script>
