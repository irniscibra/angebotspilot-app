<template>
  <q-page class="flex flex-center" style="min-height: 100vh">
    <div style="width: 440px; max-width: 90vw">
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
          Konto erstellen
        </h5>
        <p style="color: #64748b; font-size: 14px">
          14 Tage kostenlos testen – keine Kreditkarte nötig
        </p>
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
          <q-form @submit="onRegister" class="q-gutter-md">
            <q-input
              v-model="name"
              filled
              label="Ihr Name"
              :rules="[(val) => !!val || 'Pflichtfeld']"
            >
              <template v-slot:prepend
                ><q-icon name="person" color="grey-5"
              /></template>
            </q-input>
             <q-input
              v-model="inviteCode"
              filled
              label="Einladungscode"
              :rules="[(val) => !!val || 'Einladungscode ist erforderlich']"
            >
              <template v-slot:prepend><q-icon name="vpn_key" color="grey-5" /></template>
            </q-input>
            <q-input
              v-model="companyName"
              filled
              label="Firmenname"
              :rules="[(val) => !!val || 'Pflichtfeld']"
            >
              <template v-slot:prepend
                ><q-icon name="business" color="grey-5"
              /></template>
            </q-input>
            <q-input
              v-model="email"
              filled
              label="E-Mail"
              type="email"
              :rules="[(val) => !!val || 'Pflichtfeld']"
            >
              <template v-slot:prepend
                ><q-icon name="email" color="grey-5"
              /></template>
            </q-input>
            <q-input
              v-model="password"
              filled
              label="Passwort"
              type="password"
              :rules="[(val) => val.length >= 8 || 'Mind. 8 Zeichen']"
            >
              <template v-slot:prepend
                ><q-icon name="lock" color="grey-5"
              /></template>
            </q-input>
            <q-input
              v-model="passwordConfirm"
              filled
              label="Passwort bestätigen"
              type="password"
              :rules="[
                (val) => val === password || 'Passwörter stimmen nicht überein',
              ]"
            >
              <template v-slot:prepend
                ><q-icon name="lock" color="grey-5"
              /></template>
            </q-input>
            <q-btn
              type="submit"
              color="primary"
              label="Kostenlos registrieren"
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
        Bereits ein Konto?
        <router-link
          to="/auth/login"
          style="color: #1d4ed8; font-weight: 600; text-decoration: none"
          >Anmelden</router-link
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
  name: "RegisterPage",
  setup() {
    const authStore = useAuthStore();
    const router = useRouter();
    const $q = useQuasar();
    const name = ref("");
    const inviteCode = ref("");
    const companyName = ref("");
    const email = ref("");
    const password = ref("");
    const passwordConfirm = ref("");
    const loading = ref(false);
    const onRegister = async () => {
      loading.value = true;
      try {
        await authStore.register({
          name: name.value,
          invite_code: inviteCode.value,
          company_name: companyName.value,
          email: email.value,
          password: password.value,
          password_confirmation: passwordConfirm.value,
        });
        router.push("/dashboard");
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Registrierung fehlgeschlagen",
        });
      } finally {
        loading.value = false;
      }
    };
    return {
      name,
      inviteCode,
      companyName,
      email,
      password,
      passwordConfirm,
      loading,
      onRegister,
    };
  },
};
</script>
