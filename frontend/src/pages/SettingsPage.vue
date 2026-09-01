<template>
  <q-page class="ap-page">
    <div class="ap-header">
      <h1 class="ap-title">Einstellungen</h1>
      <p class="ap-subtitle">Firmendaten, Branding und Standardwerte</p>
    </div>

    <div v-if="loading" class="ap-loading">
      <q-spinner-orbit color="primary" size="46px" />
    </div>

    <div v-else class="ap-shell">
      <!-- Vertikale Navigation -->
      <div class="ap-sidenav">
        <button
          v-for="t in tabs"
          :key="t.name"
          class="ap-sidenav-item"
          :class="{ 'is-active': tab === t.name }"
          @click="tab = t.name"
        >
          <q-icon :name="t.icon" size="18px" />
          <span>{{ t.label }}</span>
        </button>
      </div>

      <!-- Inhalt -->
      <div class="ap-content">
        <!-- FIRMENDATEN -->
        <div v-if="tab === 'company'" class="ap-panel">
          <div class="ap-panel-head">
            <h6 class="ap-panel-title">Firmendaten</h6>
            <p class="ap-panel-desc">Erscheint auf Ihren Angeboten und PDFs.</p>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Firmenname</div>
              <div class="ap-setting-hint">Pflichtfeld</div>
            </div>
            <div class="ap-setting-control">
              <q-input
                v-model="form.name"
                filled
                dense
                :rules="[(val) => !!val || 'Pflichtfeld']"
              />
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Adresse</div>
            </div>
            <div class="ap-setting-control ap-stack">
              <q-input
                v-model="form.address_street"
                filled
                dense
                placeholder="Straße & Hausnummer"
              />
              <div class="ap-row">
                <q-input
                  v-model="form.address_zip"
                  filled
                  dense
                  placeholder="PLZ"
                  style="max-width: 110px"
                />
                <q-input
                  v-model="form.address_city"
                  filled
                  dense
                  placeholder="Ort"
                  class="ap-flex"
                />
              </div>
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Kontakt</div>
            </div>
            <div class="ap-setting-control ap-stack">
              <q-input
                v-model="form.phone"
                filled
                dense
                placeholder="Telefon"
              />
              <q-input
                v-model="form.email"
                filled
                dense
                type="email"
                placeholder="E-Mail"
              />
              <q-input
                v-model="form.website"
                filled
                dense
                placeholder="Website (optional)"
              />
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Gewerk</div>
              <div class="ap-setting-hint">Für die KI-Preisanalyse</div>
            </div>
            <div class="ap-setting-control">
              <q-select
                v-model="form.trade"
                filled
                dense
                :options="tradeOptions"
                option-value="value"
                option-label="label"
                emit-value
                map-options
                clearable
              />
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Rechtliches</div>
            </div>
            <div class="ap-setting-control ap-stack">
              <q-input
                v-model="form.tax_id"
                filled
                dense
                placeholder="USt-IdNr. · DE123456789"
              />
              <q-input
                v-model="form.trade_register"
                filled
                dense
                placeholder="Handelsregister · HRB 12345"
              />
            </div>
          </div>

          <div class="ap-panel-footer">
            <q-btn
              unelevated
              color="primary"
              label="Speichern"
              no-caps
              icon="save"
              :loading="saving"
              @click="onSave"
              class="ap-save-btn"
            />
          </div>
        </div>

        <!-- BRANDING -->
        <div v-if="tab === 'branding'" class="ap-panel">
          <div class="ap-panel-head">
            <h6 class="ap-panel-title">Branding</h6>
            <p class="ap-panel-desc">Erscheinungsbild Ihrer Angebote.</p>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Firmenlogo</div>
              <div class="ap-setting-hint">PNG, JPG oder SVG · Max. 2 MB</div>
            </div>
            <div class="ap-setting-control">
              <div class="ap-logo-row">
                <div class="ap-logo-box" :class="{ 'has-logo': logoPreview }">
                  <img
                    v-if="logoPreview"
                    :src="logoPreview"
                    class="ap-logo-img"
                  />
                  <q-icon
                    v-else
                    name="add_photo_alternate"
                    size="28px"
                    color="#c6cad9"
                  />
                </div>
                <div class="ap-logo-actions">
                  <q-btn
                    outline
                    color="primary"
                    label="Hochladen"
                    no-caps
                    dense
                    icon="upload"
                    @click="$refs.logoInput.click()"
                    class="ap-outline-btn"
                  />
                  <q-btn
                    v-if="logoPreview"
                    flat
                    color="negative"
                    label="Entfernen"
                    no-caps
                    dense
                    @click="onRemoveLogo"
                  />
                  <input
                    ref="logoInput"
                    type="file"
                    accept="image/png,image/jpeg,image/svg+xml"
                    style="display: none"
                    @change="onLogoSelected"
                  />
                </div>
              </div>
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Primärfarbe</div>
              <div class="ap-setting-hint">Für PDFs und Angebote</div>
            </div>
            <div class="ap-setting-control">
              <div class="ap-color-row">
                <q-input
                  v-model="form.primary_color"
                  filled
                  dense
                  maxlength="7"
                  style="width: 130px"
                >
                  <template v-slot:prepend>
                    <div
                      class="ap-color-chip"
                      :style="{ background: form.primary_color }"
                    />
                  </template>
                </q-input>
                <div class="ap-swatches">
                  <div
                    v-for="color in presetColors"
                    :key="color"
                    class="ap-swatch"
                    :class="{ 'is-selected': form.primary_color === color }"
                    :style="{ background: color }"
                    @click="form.primary_color = color"
                  />
                </div>
              </div>
              <div class="ap-preview-box" :style="previewBoxStyle">
                <div class="ap-preview-label">Vorschau</div>
                <div
                  class="ap-preview-name"
                  :style="{ color: form.primary_color }"
                >
                  {{ form.name || "Ihr Firmenname" }}
                </div>
                <div class="ap-preview-sub">
                  Sanitär · Heizung · Klimatechnik
                </div>
              </div>
            </div>
          </div>

          <div class="ap-panel-footer">
            <q-btn
              unelevated
              color="primary"
              label="Speichern"
              no-caps
              icon="save"
              :loading="saving"
              @click="onSave"
              class="ap-save-btn"
            />
          </div>
        </div>

        <!-- STANDARDWERTE -->
        <div v-if="tab === 'defaults'" class="ap-panel">
          <div class="ap-panel-head">
            <h6 class="ap-panel-title">Standardwerte</h6>
            <p class="ap-panel-desc">
              Basis für neue Angebote und KI-Kalkulation.
            </p>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Stundensatz Monteur</div>
              <div class="ap-setting-hint">
                Für Arbeitszeitpositionen der KI
              </div>
            </div>
            <div class="ap-setting-control">
              <q-input
                v-model.number="form.default_hourly_rate"
                filled
                dense
                type="number"
                suffix="€/Std"
                style="max-width: 180px"
              />
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">MwSt-Satz</div>
            </div>
            <div class="ap-setting-control">
              <q-input
                v-model.number="form.default_vat_rate"
                filled
                dense
                type="number"
                suffix="%"
                style="max-width: 180px"
              />
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Angebots-Präfix</div>
              <div class="ap-setting-hint">
                Beispiel: {{ form.quote_prefix || "ANG" }}-2026-1001
              </div>
            </div>
            <div class="ap-setting-control">
              <q-input
                v-model="form.quote_prefix"
                filled
                dense
                placeholder="ANG"
                style="max-width: 180px"
              />
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Angebots-Gültigkeit</div>
            </div>
            <div class="ap-setting-control">
              <q-input
                v-model.number="form.quote_validity_days"
                filled
                dense
                type="number"
                suffix="Tage"
                style="max-width: 180px"
              />
            </div>
          </div>

          <div class="ap-panel-footer">
            <q-btn
              unelevated
              color="primary"
              label="Speichern"
              no-caps
              icon="save"
              :loading="saving"
              @click="onSave"
              class="ap-save-btn"
            />
          </div>
        </div>

        <!-- MEIN KONTO -->
        <div v-if="tab === 'account'" class="ap-panel">
          <div class="ap-account-hero">
            <div class="ap-account-avatar">{{ userInitials }}</div>
            <div class="ap-flex">
              <div class="ap-account-name">{{ authStore.userName }}</div>
              <div class="ap-account-email">{{ authStore.user?.email }}</div>
            </div>
            <span class="ap-plan-badge" :class="`is-${planColor}`">{{
              planLabel
            }}</span>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Abo-Status</div>
            </div>
            <div class="ap-setting-control">
              <div
                v-if="authStore.company?.plan === 'trial'"
                class="ap-trial-block"
              >
                <div class="ap-trial-text">
                  {{ authStore.company?.trial_quotes_used || 0 }} von 5
                  kostenlosen Angeboten genutzt
                </div>
                <div class="ap-progress-track">
                  <div
                    class="ap-progress-fill"
                    :style="{
                      width:
                        ((authStore.company?.trial_quotes_used || 0) / 5) *
                          100 +
                        '%',
                    }"
                  />
                </div>
                <q-btn
                  v-if="(authStore.company?.trial_quotes_used || 0) >= 5"
                  unelevated
                  color="primary"
                  label="Jetzt upgraden"
                  no-caps
                  dense
                  to="/upgrade"
                  class="q-mt-sm"
                  style="border-radius: 8px"
                />
              </div>

              <div class="ap-plan-line">
                {{ planLabel }}
                <span
                  v-if="authStore.company?.plan === 'trial'"
                  class="ap-muted"
                >
                  · Endet am {{ formatDate(authStore.company?.trial_ends_at) }}
                </span>
              </div>

                     <div
                v-if="authStore.company?.subscription_started_at"
                class="ap-muted"
                style="font-size: 12.5px; margin-top: 4px"
              >
                Kunde seit
                {{ formatDate(authStore.company?.subscription_started_at) }}
              </div>

              <div
                v-if="
                  authStore.company?.current_period_end &&
                  !authStore.company?.cancelled_at
                "
                class="ap-muted"
                style="font-size: 12.5px; margin-top: 2px"
              >
                Nächste Abbuchung am
                {{ formatDate(authStore.company?.current_period_end) }}
              </div>

              <div
                v-if="authStore.company?.cancelled_at"
                class="ap-cancel-banner"
              >
                <div>
                  Gekündigt am
                  {{ formatDate(authStore.company?.cancelled_at) }} · Zugriff
                  besteht noch bis
                  {{ formatDate(authStore.company?.access_until) }}
                </div>
                <q-btn
                  flat
                  dense
                  no-caps
                  label="Kündigung zurücknehmen"
                  color="orange-9"
                  :loading="cancelling"
                  @click="onReactivate"
                  class="q-mt-sm"
                />
              </div>
              <q-btn
                v-else-if="
                  ['starter', 'professional', 'enterprise', 'pro'].includes(
                    authStore.company?.plan,
                  )
                "
                flat
                dense
                no-caps
                color="negative"
                label="Abo kündigen"
                class="q-mt-sm q-px-none"
                @click="confirmCancel = true"
              />
            </div>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">KI-Nutzung</div>
              <div class="ap-setting-hint">Diesen Monat</div>
            </div>
            <div class="ap-setting-control">
              <div class="ap-usage-line">{{ aiUsage }} Angebote generiert</div>
            </div>
          </div>
        </div>

        <!-- Passwort ändern -->
        <div v-if="tab === 'account'" class="ap-panel q-mt-md">
          <div class="ap-panel-head">
            <h6 class="ap-panel-title">Passwort ändern</h6>
            <p class="ap-panel-desc">
              Ändern Sie Ihr Passwort, ohne sich abzumelden.
            </p>
          </div>

          <div class="ap-setting-row">
            <div class="ap-setting-label">
              <div class="ap-setting-name">Neues Passwort</div>
              <div class="ap-setting-hint">Mind. 8 Zeichen</div>
            </div>
            <div class="ap-setting-control ap-stack" style="max-width: 320px">
              <q-input
                v-model="passwordForm.current_password"
                filled
                dense
                :type="showCurrentPw ? 'text' : 'password'"
                placeholder="Aktuelles Passwort"
              >
                <template v-slot:append>
                  <q-icon
                    :name="showCurrentPw ? 'visibility_off' : 'visibility'"
                    class="cursor-pointer"
                    size="18px"
                    color="grey-6"
                    @click="showCurrentPw = !showCurrentPw"
                  />
                </template>
              </q-input>

              <q-input
                v-model="passwordForm.password"
                filled
                dense
                :type="showNewPw ? 'text' : 'password'"
                placeholder="Neues Passwort"
                lazy-rules="ondemand"
                :rules="[
                  (val) => !val || val.length >= 8 || 'Mindestens 8 Zeichen',
                ]"
              >
                <template v-slot:append>
                  <q-icon
                    :name="showNewPw ? 'visibility_off' : 'visibility'"
                    class="cursor-pointer"
                    size="18px"
                    color="grey-6"
                    @click="showNewPw = !showNewPw"
                  />
                </template>
              </q-input>

              <q-input
                v-model="passwordForm.password_confirmation"
                filled
                dense
                :type="showNewPw ? 'text' : 'password'"
                placeholder="Neues Passwort wiederholen"
                :rules="[
                  (val) =>
                    val === passwordForm.password ||
                    'Passwörter stimmen nicht überein',
                ]"
              />

              <q-banner
                v-if="passwordError"
                dense
                rounded
                style="background: #fef2f2; color: #b91c1c; font-size: 12.5px"
              >
                {{ passwordError }}
              </q-banner>
            </div>
          </div>

          <div class="ap-panel-footer">
            <q-btn
              unelevated
              color="primary"
              label="Passwort ändern"
              no-caps
              icon="save"
              :loading="changingPassword"
              @click="onChangePassword"
              class="ap-save-btn"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Kündigungs-Dialog -->
    <q-dialog v-model="confirmCancel" persistent>
      <q-card class="ap-dialog-card">
        <q-card-section class="row items-center q-pb-sm">
          <q-icon name="warning" color="negative" size="26px" class="q-mr-sm" />
          <span style="font-weight: 700; font-size: 16px; color: #12121f"
            >Abo wirklich kündigen?</span
          >
        </q-card-section>
        <q-card-section
          style="font-size: 13.5px; color: #64748b; line-height: 1.6"
        >
          Ihr Zugriff bleibt bis zum Ende des aktuellen Abrechnungszeitraums
          bestehen. Alle Ihre Daten (Angebote, Kunden, Materialien) bleiben
          danach
          <strong style="color: #12121f">vollständig erhalten</strong> und
          werden nicht gelöscht.
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Abbrechen" color="grey" v-close-popup />
          <q-btn
            unelevated
            color="negative"
            label="Ja, kündigen"
            no-caps
            :loading="cancelling"
            @click="onCancelSubscription"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
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

    const tabs = [
      { name: "company", label: "Firmendaten", icon: "business" },
      { name: "branding", label: "Branding", icon: "palette" },
      { name: "defaults", label: "Standardwerte", icon: "tune" },
      { name: "account", label: "Mein Konto", icon: "person" },
    ];

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
      trade: "",
    });

    const presetColors = [
      "#4F46E5",
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
      "#12121F",
    ];

    const tradeOptions = [
      { label: "Sanitär, Heizung, Klima (SHK)", value: "shk" },
      { label: "Elektro", value: "elektro" },
      { label: "Maler & Lackierer", value: "maler" },
      { label: "Trockenbau & Innenausbau", value: "trockenbau" },
      { label: "Fliesen & Naturstein", value: "fliesen" },
      { label: "Schreiner & Tischler", value: "schreiner" },
      { label: "Dachdecker", value: "dachdecker" },
      { label: "Garten & Landschaftsbau", value: "gartenbau" },
      { label: "Kälte & Klimatechnik", value: "kaelte" },
      { label: "Sonstiges Baugewerk", value: "sonstiges" },
    ];

    const previewBoxStyle = computed(() => ({
      borderRadius: "10px",
      border: `1px solid ${form.primary_color}30`,
      background: `${form.primary_color}0a`,
      padding: "14px",
      marginTop: "12px",
    }));

    const loadCompany = async () => {
      loading.value = true;
      try {
        const r = await api.get("/company");
        const c = r.data;
        Object.keys(form).forEach((k) => {
          if (c[k] !== null && c[k] !== undefined) form[k] = c[k];
        });
        if (c.logo_path) logoPreview.value = `/storage/${c.logo_path}`;
        if (authStore.user) {
          authStore.user.company = c;
          localStorage.setItem("user", JSON.stringify(authStore.user));
        }
      } catch (e) {
        console.error(e);
      } finally {
        loading.value = false;
      }
    };

    const loadAiUsage = async () => {
      try {
        const r = await api.get("/dashboard");
        aiUsage.value = r.data.stats?.quotes_this_month || 0;
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
          pro: "Pro",
        })[authStore.company?.plan] || "Trial",
    );

    const planColor = computed(
      () =>
        ({
          trial: "orange",
          starter: "blue",
          professional: "indigo",
          enterprise: "purple",
          pro: "purple",
        })[authStore.company?.plan] || "grey",
    );

    const formatDate = (val) =>
      val ? new Date(val).toLocaleDateString("de-DE") : "-";

    const confirmCancel = ref(false);
    const cancelling = ref(false);

    const passwordForm = reactive({
      current_password: "",
      password: "",
      password_confirmation: "",
    });
    const showCurrentPw = ref(false);
    const showNewPw = ref(false);
    const changingPassword = ref(false);
    const passwordError = ref("");

    const onChangePassword = async () => {
      passwordError.value = "";
      if (!passwordForm.password || passwordForm.password.length < 8) {
        passwordError.value =
          "Das neue Passwort muss mindestens 8 Zeichen haben.";
        return;
      }
      if (passwordForm.password !== passwordForm.password_confirmation) {
        passwordError.value = "Die neuen Passwörter stimmen nicht überein.";
        return;
      }
      changingPassword.value = true;
      try {
        await api.post("/auth/change-password", passwordForm);
        $q.notify({
          type: "positive",
          message: "Passwort erfolgreich geändert.",
        });
        passwordForm.current_password = "";
        passwordForm.password = "";
        passwordForm.password_confirmation = "";
      } catch (e) {
        passwordError.value =
          e.response?.data?.message || "Passwort konnte nicht geändert werden.";
      } finally {
        changingPassword.value = false;
      }
    };

    const onCancelSubscription = async () => {
      cancelling.value = true;
      try {
        const res = await api.post("/company/cancel-subscription");
        if (authStore.user) {
          authStore.user.company = res.data.company;
          localStorage.setItem("user", JSON.stringify(authStore.user));
        }
        confirmCancel.value = false;
        $q.notify({
          type: "positive",
          message: res.data.message,
          timeout: 6000,
        });
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler bei der Kündigung",
        });
      } finally {
        cancelling.value = false;
      }
    };

    const onReactivate = async () => {
      cancelling.value = true;
      try {
        const res = await api.post("/company/reactivate-subscription");
        if (authStore.user) {
          authStore.user.company = res.data.company;
          localStorage.setItem("user", JSON.stringify(authStore.user));
        }
        $q.notify({ type: "positive", message: res.data.message });
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler",
        });
      } finally {
        cancelling.value = false;
      }
    };

    return {
      authStore,
      loading,
      saving,
      tab,
      tabs,
      form,
      passwordForm,
      showCurrentPw,
      showNewPw,
      changingPassword,
      passwordError,
      onChangePassword,
      logoPreview,
      aiUsage,
      presetColors,
      previewBoxStyle,
      userInitials,
      planLabel,
      planColor,
      onSave,
      onLogoSelected,
      onRemoveLogo,
      formatDate,
      tradeOptions,
      confirmCancel,
      cancelling,
      onCancelSubscription,
      onReactivate,
    };
  },
};
</script>

<style scoped>
.ap-page {
  background: #f7f8fb;
  padding: 20px 20px 40px;
  min-height: 100%;
}
@media (min-width: 1024px) {
  .ap-page {
    padding: 32px 40px 56px;
  }
}

.ap-header {
  margin-bottom: 24px;
}
.ap-title {
  font-size: clamp(20px, 3vw, 26px);
  font-weight: 700;
  color: #12121f;
  margin: 0;
  letter-spacing: -0.01em;
}
.ap-subtitle {
  font-size: 13.5px;
  color: #8b90a3;
  margin: 4px 0 0;
}

.ap-loading {
  display: flex;
  justify-content: center;
  padding: 80px 0;
}

/* Shell: Sidenav + Content */
.ap-shell {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 28px;
  max-width: 920px;
}
@media (max-width: 767px) {
  .ap-shell {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}

.ap-sidenav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
@media (max-width: 767px) {
  .ap-sidenav {
    flex-direction: row;
    overflow-x: auto;
    border-bottom: 1px solid #eceef4;
    padding-bottom: 4px;
  }
}
.ap-sidenav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border: none;
  background: none;
  border-radius: 9px;
  color: #8b90a3;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
  white-space: nowrap;
  transition:
    background 0.15s,
    color 0.15s;
}
.ap-sidenav-item:hover {
  background: #f4f5fa;
  color: #12121f;
}
.ap-sidenav-item.is-active {
  background: #eef0ff;
  color: #4f46e5;
}

.ap-content {
  min-width: 0;
}

.ap-panel {
  background: #ffffff;
  border: 1px solid #eceef4;
  border-radius: 18px;
  overflow: hidden;
}
.ap-panel-head {
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f1f2f7;
}
.ap-panel-title {
  font-size: 15.5px;
  font-weight: 700;
  color: #12121f;
  margin: 0 0 3px;
}
.ap-panel-desc {
  font-size: 12.5px;
  color: #8b90a3;
  margin: 0;
}

/* Zeilen-Layout: Label links, Control rechts */
.ap-setting-row {
  display: grid;
  grid-template-columns: 180px 1fr;
  gap: 20px;
  padding: 18px 24px;
  border-bottom: 1px solid #f4f5f9;
}
.ap-setting-row:last-of-type {
  border-bottom: none;
}
@media (max-width: 599px) {
  .ap-setting-row {
    grid-template-columns: 1fr;
    gap: 8px;
  }
}
.ap-setting-name {
  font-size: 13px;
  font-weight: 600;
  color: #12121f;
}
.ap-setting-hint {
  font-size: 11.5px;
  color: #a1a6b8;
  margin-top: 2px;
}
.ap-setting-control {
  min-width: 0;
}
.ap-stack {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ap-row {
  display: flex;
  gap: 8px;
}
.ap-flex {
  flex: 1;
}

.ap-panel-footer {
  padding: 16px 24px;
  background: #fafbfd;
  display: flex;
  justify-content: flex-end;
}
.ap-save-btn {
  border-radius: 9px;
  font-weight: 600;
  padding: 0 18px;
  height: 38px;
}

/* Branding */
.ap-logo-row {
  display: flex;
  align-items: center;
  gap: 14px;
}
.ap-logo-box {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  border: 2px dashed #d8dcea;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex-shrink: 0;
  background: #fafbfd;
}
.ap-logo-box.has-logo {
  border-style: solid;
  border-color: #c7cdfa;
}
.ap-logo-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 6px;
}
.ap-logo-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.ap-outline-btn {
  border-radius: 8px;
}

.ap-color-row {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.ap-color-chip {
  width: 18px;
  height: 18px;
  border-radius: 5px;
}
.ap-swatches {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}
.ap-swatch {
  width: 24px;
  height: 24px;
  border-radius: 7px;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform 0.1s;
}
.ap-swatch:hover {
  transform: scale(1.1);
}
.ap-swatch.is-selected {
  border-color: #12121f;
}

.ap-preview-label {
  font-size: 10px;
  font-weight: 700;
  color: #a1a6b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 4px;
}
.ap-preview-name {
  font-size: 14.5px;
  font-weight: 700;
}
.ap-preview-sub {
  font-size: 11.5px;
  color: #8b90a3;
  margin-top: 2px;
}

/* Account */
.ap-account-hero {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 22px 24px;
  border-bottom: 1px solid #f1f2f7;
}
.ap-account-avatar {
  width: 50px;
  height: 50px;
  border-radius: 13px;
  background: linear-gradient(135deg, #4f46e5, #6366f1);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  font-weight: 700;
  flex-shrink: 0;
}
.ap-account-name {
  font-size: 14.5px;
  font-weight: 700;
  color: #12121f;
}
.ap-account-email {
  font-size: 12.5px;
  color: #8b90a3;
  margin-top: 1px;
}
.ap-plan-badge {
  padding: 4px 11px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  flex-shrink: 0;
}
.ap-plan-badge.is-orange {
  background: #fef3e2;
  color: #d97706;
}
.ap-plan-badge.is-blue {
  background: #eef2ff;
  color: #2563eb;
}
.ap-plan-badge.is-indigo {
  background: #eef0ff;
  color: #4f46e5;
}
.ap-plan-badge.is-purple {
  background: #f3e8ff;
  color: #9333ea;
}
.ap-plan-badge.is-grey {
  background: #f1f5f9;
  color: #64748b;
}

.ap-trial-block {
  margin-bottom: 12px;
}
.ap-trial-text {
  font-size: 12.5px;
  color: #64748b;
  margin-bottom: 6px;
}
.ap-progress-track {
  height: 6px;
  border-radius: 999px;
  background: #eef0f5;
  max-width: 240px;
  overflow: hidden;
}
.ap-progress-fill {
  height: 100%;
  border-radius: 999px;
  background: #4f46e5;
  transition: width 0.3s;
}

.ap-plan-line {
  font-size: 13px;
  color: #12121f;
  font-weight: 600;
}
.ap-muted {
  color: #8b90a3;
  font-weight: 400;
}

.ap-cancel-banner {
  margin-top: 10px;
  padding: 12px 14px;
  border-radius: 10px;
  background: #fef3e2;
  color: #92400e;
  font-size: 12.5px;
  line-height: 1.5;
}

.ap-usage-line {
  font-size: 13.5px;
  color: #12121f;
  font-weight: 600;
}

.ap-dialog-card {
  min-width: 380px;
  max-width: 95vw;
  border-radius: 16px;
}
</style>
