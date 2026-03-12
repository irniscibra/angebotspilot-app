<template>
  <q-page class="q-pa-lg">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Einstellungen
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          Firmendaten, Branding und Standardwerte
        </p>
      </div>
    </div>
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="50px" />
    </div>
    <div v-else style="max-width: 900px">
      <q-tabs
        v-model="tab"
        dense
        active-color="primary"
        indicator-color="primary"
        class="q-mb-lg"
        align="left"
        no-caps
      >
        <q-tab name="company" label="Firmendaten" icon="business" />
        <q-tab name="branding" label="Branding" icon="palette" />
        <q-tab name="defaults" label="Standardwerte" icon="tune" />
        <q-tab name="account" label="Mein Konto" icon="person" />
      </q-tabs>
      <q-tab-panels v-model="tab" animated class="bg-transparent">
        <q-tab-panel name="company" class="q-pa-none">
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #ffffff;
            "
          >
            <q-card-section class="q-pa-lg">
              <h6
                class="q-my-none q-mb-md"
                style="font-weight: 600; color: #0f172a"
              >
                Firmendaten
              </h6>
              <p style="font-size: 13px; color: #64748b" class="q-mb-lg">
                Diese Daten erscheinen auf Ihren Angeboten und PDFs.
              </p>
              <div class="q-gutter-md">
                <q-input
                  v-model="form.name"
                  filled
                  label="Firmenname *"
                  :rules="[(val) => !!val || 'Pflichtfeld']"
                  ><template v-slot:prepend
                    ><q-icon name="business" color="grey-5" /></template
                ></q-input>
                <q-input
                  v-model="form.address_street"
                  filled
                  label="Straße & Hausnummer"
                  ><template v-slot:prepend
                    ><q-icon name="location_on" color="grey-5" /></template
                ></q-input>
                <div class="row q-gutter-md">
                  <q-input
                    v-model="form.address_zip"
                    filled
                    label="PLZ"
                    class="col-3"
                  /><q-input
                    v-model="form.address_city"
                    filled
                    label="Ort"
                    class="col"
                  />
                </div>
                <div class="row q-gutter-md">
                  <q-input
                    v-model="form.phone"
                    filled
                    label="Telefon"
                    class="col"
                    ><template v-slot:prepend
                      ><q-icon
                        name="phone"
                        color="grey-5" /></template></q-input
                  ><q-input
                    v-model="form.email"
                    filled
                    label="E-Mail"
                    type="email"
                    class="col"
                    ><template v-slot:prepend
                      ><q-icon name="email" color="grey-5" /></template
                  ></q-input>
                </div>
                <q-input
                  v-model="form.website"
                  filled
                  label="Website (optional)"
                  ><template v-slot:prepend
                    ><q-icon name="language" color="grey-5" /></template
                ></q-input>
                <div class="row q-gutter-md">
                  <q-input
                    v-model="form.tax_id"
                    filled
                    label="USt-IdNr."
                    class="col"
                    placeholder="DE123456789"
                  /><q-input
                    v-model="form.trade_register"
                    filled
                    label="Handelsregister (optional)"
                    class="col"
                    placeholder="HRB 12345"
                  />
                </div>
              </div>
              <q-btn
                color="primary"
                label="Speichern"
                no-caps
                class="q-mt-lg"
                icon="save"
                :loading="saving"
                @click="onSave"
                style="border-radius: 10px; font-weight: 600"
              />
            </q-card-section>
          </q-card>
        </q-tab-panel>
        <q-tab-panel name="branding" class="q-pa-none">
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #ffffff;
            "
          >
            <q-card-section class="q-pa-lg">
              <h6
                class="q-my-none q-mb-md"
                style="font-weight: 600; color: #0f172a"
              >
                Branding
              </h6>
              <p style="font-size: 13px; color: #64748b" class="q-mb-lg">
                Passen Sie das Erscheinungsbild Ihrer Angebote an.
              </p>
              <div class="q-mb-xl">
                <div
                  style="
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    color: #64748b;
                  "
                  class="q-mb-sm"
                >
                  Firmenlogo
                </div>
                <div class="row items-center q-gutter-md">
                  <div
                    style="
                      width: 120px;
                      height: 120px;
                      border-radius: 14px;
                      border: 2px dashed #d1d5db;
                      display: flex;
                      align-items: center;
                      justify-content: center;
                      overflow: hidden;
                    "
                    :style="
                      logoPreview
                        ? 'border-style: solid; border-color: #93c5fd;'
                        : ''
                    "
                  >
                    <img
                      v-if="logoPreview"
                      :src="logoPreview"
                      style="
                        width: 100%;
                        height: 100%;
                        object-fit: contain;
                        padding: 8px;
                      "
                    />
                    <q-icon
                      v-else
                      name="add_photo_alternate"
                      size="36px"
                      color="grey-5"
                    />
                  </div>
                  <div>
                    <q-btn
                      outline
                      color="primary"
                      label="Logo hochladen"
                      no-caps
                      @click="$refs.logoInput.click()"
                      icon="upload"
                    />
                    <q-btn
                      v-if="logoPreview"
                      flat
                      color="negative"
                      label="Entfernen"
                      no-caps
                      class="q-ml-sm"
                      @click="onRemoveLogo"
                    />
                    <input
                      ref="logoInput"
                      type="file"
                      accept="image/png,image/jpeg,image/svg+xml"
                      style="display: none"
                      @change="onLogoSelected"
                    />
                    <div
                      class="q-mt-xs"
                      style="font-size: 11px; color: #94a3b8"
                    >
                      PNG, JPG oder SVG · Max. 2 MB
                    </div>
                  </div>
                </div>
              </div>
              <div>
                <div
                  style="
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                    color: #64748b;
                  "
                  class="q-mb-sm"
                >
                  Primärfarbe
                </div>
                <div class="row items-center q-gutter-md">
                  <q-input
                    v-model="form.primary_color"
                    filled
                    style="width: 160px"
                    maxlength="7"
                    ><template v-slot:prepend
                      ><div
                        :style="`width: 24px; height: 24px; border-radius: 6px; background: ${form.primary_color};`"
                      ></div></template
                  ></q-input>
                  <div class="row q-gutter-xs">
                    <div
                      v-for="color in presetColors"
                      :key="color"
                      :style="`width: 32px; height: 32px; border-radius: 8px; background: ${color}; cursor: pointer; border: 2px solid ${form.primary_color === color ? '#0f172a' : 'transparent'};`"
                      @click="form.primary_color = color"
                    ></div>
                  </div>
                </div>
                <div
                  class="q-mt-lg q-pa-md"
                  :style="`border-radius: 10px; border: 1px solid ${form.primary_color}30; background: ${form.primary_color}08;`"
                >
                  <div style="font-size: 11px; color: #94a3b8" class="q-mb-xs">
                    VORSCHAU
                  </div>
                  <div
                    :style="`font-size: 16px; font-weight: 700; color: ${form.primary_color};`"
                  >
                    {{ form.name || "Ihr Firmenname" }}
                  </div>
                  <div style="font-size: 12px; color: #64748b">
                    Sanitär · Heizung · Klimatechnik
                  </div>
                </div>
              </div>
              <q-btn
                color="primary"
                label="Speichern"
                no-caps
                class="q-mt-lg"
                icon="save"
                :loading="saving"
                @click="onSave"
                style="border-radius: 10px; font-weight: 600"
              />
            </q-card-section>
          </q-card>
        </q-tab-panel>
        <q-tab-panel name="defaults" class="q-pa-none">
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #ffffff;
            "
          >
            <q-card-section class="q-pa-lg">
              <h6
                class="q-my-none q-mb-md"
                style="font-weight: 600; color: #0f172a"
              >
                Standardwerte
              </h6>
              <p style="font-size: 13px; color: #64748b" class="q-mb-lg">
                Diese Werte werden für neue Angebote und die KI-Kalkulation
                verwendet.
              </p>
              <div class="q-gutter-lg">
                <div>
                  <div
                    style="
                      font-size: 12px;
                      font-weight: 600;
                      text-transform: uppercase;
                      color: #64748b;
                    "
                    class="q-mb-xs"
                  >
                    Stundensatz Monteur
                  </div>
                  <q-input
                    v-model.number="form.default_hourly_rate"
                    filled
                    type="number"
                    suffix="€ / Std"
                    style="max-width: 250px"
                    ><template v-slot:prepend
                      ><q-icon name="euro" color="grey-5" /></template
                  ></q-input>
                  <div class="q-mt-xs" style="font-size: 11px; color: #94a3b8">
                    Wird von der KI für Arbeitszeitpositionen verwendet
                  </div>
                </div>
                <div>
                  <div
                    style="
                      font-size: 12px;
                      font-weight: 600;
                      text-transform: uppercase;
                      color: #64748b;
                    "
                    class="q-mb-xs"
                  >
                    MwSt-Satz
                  </div>
                  <q-input
                    v-model.number="form.default_vat_rate"
                    filled
                    type="number"
                    suffix="%"
                    style="max-width: 250px"
                    ><template v-slot:prepend
                      ><q-icon name="receipt" color="grey-5" /></template
                  ></q-input>
                </div>
                <q-separator />
                <div>
                  <div
                    style="
                      font-size: 12px;
                      font-weight: 600;
                      text-transform: uppercase;
                      color: #64748b;
                    "
                    class="q-mb-xs"
                  >
                    Angebots-Präfix
                  </div>
                  <q-input
                    v-model="form.quote_prefix"
                    filled
                    style="max-width: 250px"
                    placeholder="ANG"
                    ><template v-slot:prepend
                      ><q-icon name="tag" color="grey-5" /></template
                  ></q-input>
                  <div class="q-mt-xs" style="font-size: 11px; color: #94a3b8">
                    Beispiel: {{ form.quote_prefix || "ANG" }}-2026-1001
                  </div>
                </div>
                <div>
                  <div
                    style="
                      font-size: 12px;
                      font-weight: 600;
                      text-transform: uppercase;
                      color: #64748b;
                    "
                    class="q-mb-xs"
                  >
                    Angebots-Gültigkeit
                  </div>
                  <q-input
                    v-model.number="form.quote_validity_days"
                    filled
                    type="number"
                    suffix="Tage"
                    style="max-width: 250px"
                    ><template v-slot:prepend
                      ><q-icon name="event" color="grey-5" /></template
                  ></q-input>
                </div>
              </div>
              <q-btn
                color="primary"
                label="Speichern"
                no-caps
                class="q-mt-lg"
                icon="save"
                :loading="saving"
                @click="onSave"
                style="border-radius: 10px; font-weight: 600"
              />
            </q-card-section>
          </q-card>
        </q-tab-panel>
        <q-tab-panel name="account" class="q-pa-none">
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #ffffff;
            "
          >
            <q-card-section class="q-pa-lg">
              <h6
                class="q-my-none q-mb-md"
                style="font-weight: 600; color: #0f172a"
              >
                Mein Konto
              </h6>
              <div class="q-gutter-md">
                <div class="row items-center q-gutter-md q-mb-md">
                  <q-avatar
                    size="64px"
                    color="primary"
                    text-color="white"
                    style="font-weight: 700; font-size: 22px"
                    >{{ userInitials }}</q-avatar
                  >
                  <div>
                    <div
                      style="font-size: 16px; font-weight: 600; color: #0f172a"
                    >
                      {{ authStore.userName }}
                    </div>
                    <div style="color: #64748b">
                      {{ authStore.user?.email }}
                    </div>
                    <q-badge
                      :color="planColor"
                      :label="planLabel"
                      class="q-mt-xs"
                    />
                  </div>
                </div>
                <q-separator />
                <div>
                  <div
                    style="
                      font-size: 12px;
                      font-weight: 600;
                      text-transform: uppercase;
                      color: #64748b;
                    "
                    class="q-mb-xs"
                  >
                    Abo-Status
                  </div>
                  <div style="font-size: 14px; color: #0f172a">
                    {{ planLabel }}
                    <span
                      v-if="authStore.company?.plan === 'trial'"
                      style="color: #64748b"
                    >
                      · Endet am
                      {{ formatDate(authStore.company?.trial_ends_at) }}</span
                    >
                  </div>
                </div>
                <div>
                  <div
                    style="
                      font-size: 12px;
                      font-weight: 600;
                      text-transform: uppercase;
                      color: #64748b;
                    "
                    class="q-mb-xs"
                  >
                    KI-Nutzung diesen Monat
                  </div>
                  <div style="font-size: 14px; color: #0f172a">
                    {{ aiUsage }} Angebote generiert
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </q-tab-panel>
      </q-tab-panels>
    </div>
  </q-page>
</template>
<script>
import { ref, reactive, computed, onMounted } from "vue";
import { useAuthStore } from "src/stores/auth";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";
export default {
  name: "SettingsPage",
  setup() {
    const authStore = useAuthStore();
    const $q = useQuasar();
    const loading = ref(true);
    const saving = ref(false);
    const tab = ref("company");
    const logoPreview = ref(null);
    const aiUsage = ref(0);
    const form = reactive({
      name: "",
      address_street: "",
      address_zip: "",
      address_city: "",
      phone: "",
      email: "",
      website: "",
      tax_id: "",
      trade_register: "",
      primary_color: "#1E40AF",
      default_vat_rate: 19,
      default_hourly_rate: 65,
      quote_validity_days: 30,
      quote_prefix: "ANG",
    });
    const presetColors = [
      "#1E40AF",
      "#1D4ED8",
      "#2563EB",
      "#0D9488",
      "#059669",
      "#16A34A",
      "#DC2626",
      "#EA580C",
      "#D97706",
      "#7C3AED",
      "#9333EA",
      "#1a1a2e",
    ];
    const loadCompany = async () => {
      loading.value = true;
      try {
        const r = await api.get("/company");
        const c = r.data;
        Object.keys(form).forEach((k) => {
          if (c[k] !== null && c[k] !== undefined) form[k] = c[k];
        });
        if (c.logo_path)
          logoPreview.value = `http://localhost:8000/storage/${c.logo_path}`;
      } catch (e) {
        console.error(e);
      } finally {
        loading.value = false;
      }
    };
    const loadAiUsage = async () => {
      try {
        const r = await api.get("/dashboard/stats");
        aiUsage.value = r.data.quotes_this_month || 0;
      } catch (e) {}
    };
    onMounted(() => {
      loadCompany();
      loadAiUsage();
    });
    const onSave = async () => {
      saving.value = true;
      try {
        const r = await api.put("/company", form);
        if (authStore.user) {
          authStore.user.company = r.data.company;
          localStorage.setItem("user", JSON.stringify(authStore.user));
        }
        $q.notify({ type: "positive", message: "Einstellungen gespeichert" });
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Speichern",
        });
      } finally {
        saving.value = false;
      }
    };
    const onLogoSelected = async (event) => {
      const f = event.target.files[0];
      if (!f) return;
      const r = new FileReader();
      r.onload = (e) => {
        logoPreview.value = e.target.result;
      };
      r.readAsDataURL(f);
      const fd = new FormData();
      fd.append("logo", f);
      try {
        const res = await api.post("/company/logo", fd, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        logoPreview.value = res.data.logo_url;
        $q.notify({ type: "positive", message: "Logo hochgeladen" });
      } catch (e) {
        $q.notify({ type: "negative", message: "Logo-Upload fehlgeschlagen" });
        logoPreview.value = null;
      }
    };
    const onRemoveLogo = async () => {
      try {
        await api.delete("/company/logo");
        logoPreview.value = null;
        $q.notify({ type: "positive", message: "Logo entfernt" });
      } catch (e) {
        $q.notify({ type: "negative", message: "Fehler beim Entfernen" });
      }
    };
    const userInitials = computed(() => {
      const n = authStore.userName || "";
      return n
        .split(" ")
        .map((w) => w[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
    });
    const planLabel = computed(
      () =>
        ({
          trial: "14-Tage Testversion",
          starter: "Starter",
          professional: "Professional",
          enterprise: "Enterprise",
        })[authStore.company?.plan] || "Trial",
    );
    const planColor = computed(
      () =>
        ({
          trial: "orange",
          starter: "blue",
          professional: "primary",
          enterprise: "purple",
        })[authStore.company?.plan] || "grey",
    );
    const formatDate = (val) =>
      val ? new Date(val).toLocaleDateString("de-DE") : "-";
    return {
      authStore,
      loading,
      saving,
      tab,
      form,
      logoPreview,
      aiUsage,
      presetColors,
      userInitials,
      planLabel,
      planColor,
      onSave,
      onLogoSelected,
      onRemoveLogo,
      formatDate,
    };
  },
};
</script>
