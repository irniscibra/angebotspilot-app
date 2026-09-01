<template>
  <div class="flex flex-center" style="min-height: 100vh">
    <div style="width: 420px; max-width: 90vw">
      <div class="text-center q-mb-xl">
        <div
          style="width: 48px; height: 48px; border-radius: 14px; background: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px"
        >
          <q-icon name="groups" color="white" size="24px" />
        </div>
        <h5 class="q-my-none" style="font-weight: 800; color: #0f172a">AngebotsPilot</h5>
        <p style="color: #64748b; font-size: 14px">Einladung annehmen</p>
      </div>

      <div v-if="loadingInvite" class="text-center q-pa-lg">
        <q-spinner-orbit color="primary" size="40px" />
      </div>

      <q-card
        v-else-if="!invite"
        flat
        style="border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff"
      >
        <q-card-section class="q-pa-lg text-center">
          <q-icon name="error_outline" color="negative" size="40px" class="q-mb-md" />
          <p style="color: #0f172a; font-size: 14px; font-weight: 600">
            {{ loadError || "Dieser Einladungslink ist ungültig oder abgelaufen." }}
          </p>
          <q-btn
            flat
            no-caps
            label="Zum Login"
            color="primary"
            @click="$router.push('/auth/login')"
          />
        </q-card-section>
      </q-card>

      <q-card
        v-else-if="!done"
        flat
        style="border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff"
      >
        <q-card-section class="q-pa-lg">
          <p style="color: #334155; font-size: 14px; line-height: 1.6" class="q-mb-md">
            Hallo <strong>{{ invite.name }}</strong>, Sie wurden zum Team von
            <strong>{{ invite.company_name }}</strong> eingeladen. Legen Sie jetzt Ihr Passwort fest.
          </p>

          <q-form @submit="onSubmit" class="q-gutter-md">
            <q-input
              v-model="password"
              filled
              label="Passwort"
              :type="showPw ? 'text' : 'password'"
              :rules="[(val) => (!!val && val.length >= 8) || 'Mindestens 8 Zeichen']"
            >
              <template v-slot:prepend><q-icon name="lock" color="grey-5" /></template>
              <template v-slot:append>
                <q-icon
                  :name="showPw ? 'visibility_off' : 'visibility'"
                  class="cursor-pointer"
                  color="grey-5"
                  @click="showPw = !showPw"
                />
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

            <q-banner
              v-if="error"
              rounded
              style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c"
            >
              {{ error }}
            </q-banner>

            <q-btn
              type="submit"
              color="primary"
              label="Einladung annehmen & loslegen"
              class="full-width"
              size="lg"
              no-caps
              :loading="submitting"
              style="border-radius: 10px; font-weight: 600"
            />
          </q-form>
        </q-card-section>
      </q-card>

      <q-card v-else flat style="border: 1px solid #e2e8f0; border-radius: 14px; background: #ffffff">
        <q-card-section class="q-pa-lg text-center">
          <q-icon name="check_circle" color="positive" size="48px" class="q-mb-md" />
          <p style="color: #0f172a; font-size: 15px; font-weight: 600" class="q-mb-md">
            Willkommen im Team!
          </p>
          <q-btn
            color="primary"
            label="Zu meinen Projekten"
            no-caps
            class="full-width"
            style="border-radius: 10px; font-weight: 600"
            @click="$router.push('/projects')"
          />
        </q-card-section>
      </q-card>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "src/boot/axios";
import { useAuthStore } from "src/stores/auth";

export default {
  name: "AcceptInvitePage",
  setup() {
    const route = useRoute();
    const router = useRouter();
    const authStore = useAuthStore();

    const userId = ref("");
    const signParams = ref({});
    const invite = ref(null);
    const loadingInvite = ref(true);
    const loadError = ref("");

    const password = ref("");
    const passwordConfirmation = ref("");
    const showPw = ref(false);
    const submitting = ref(false);
    const error = ref("");
    const done = ref(false);

    onMounted(async () => {
      userId.value = route.query.user || "";
      signParams.value = {
        expires: route.query.expires,
        signature: route.query.signature,
      };

      if (!userId.value || !signParams.value.signature) {
        loadingInvite.value = false;
        return;
      }

      try {
        const response = await api.get(`/team/invite/${userId.value}`, {
          params: signParams.value,
        });
        invite.value = response.data;
      } catch (e) {
        loadError.value = e.response?.data?.message || "";
      } finally {
        loadingInvite.value = false;
      }
    });

    const onSubmit = async () => {
      submitting.value = true;
      error.value = "";
      try {
        const response = await api.post(
          `/team/invite/${userId.value}`,
          {
            password: password.value,
            password_confirmation: passwordConfirmation.value,
          },
          { params: signParams.value }
        );
        authStore.setAuth(response.data);
        done.value = true;
      } catch (e) {
        error.value =
          e.response?.data?.message || "Die Einladung konnte nicht angenommen werden.";
      } finally {
        submitting.value = false;
      }
    };

    return {
      invite,
      loadingInvite,
      loadError,
      password,
      passwordConfirmation,
      showPw,
      submitting,
      error,
      done,
      onSubmit,
      router,
    };
  },
};
</script>
