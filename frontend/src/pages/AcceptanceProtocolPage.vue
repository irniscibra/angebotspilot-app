<template>
  <q-page class="q-pa-lg" style="background: #f6f9fc">
    <!-- Liste -->
    <div v-if="!currentProtocol">
      <div class="row items-center q-mb-lg">
        <div class="col">
          <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
            Abnahmeprotokolle
          </h5>
          <p class="q-mb-none q-mt-xs" style="color: #64748b">
            Baudokumentation und Abnahme nach Projektabschluss
          </p>
        </div>
      </div>

      <div v-if="loading" class="flex flex-center q-pa-xl">
        <q-spinner-orbit color="primary" size="40px" />
      </div>

      <q-card
        v-else-if="protocols.length === 0"
        flat
        class="text-center q-pa-xl"
        style="
          border: 2px dashed #e2e8f0;
          border-radius: 14px;
          background: #fff;
        "
      >
        <q-icon name="assignment_turned_in" size="48px" color="grey-4" />
        <h6 class="q-mt-md q-mb-xs" style="color: #64748b">
          Noch keine Protokolle
        </h6>
        <p style="color: #94a3b8">
          Erstelle ein Abnahmeprotokoll aus einem bestehenden Angebot.
        </p>
      </q-card>

      <div v-else class="q-gutter-md">
        <q-card
          v-for="p in protocols"
          :key="p.id"
          flat
          clickable
          @click="loadProtocol(p.id)"
          style="
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
          "
        >
          <q-card-section>
            <div class="row items-center">
              <q-avatar
                size="42px"
                :color="statusColor(p.status)"
                text-color="white"
                :icon="statusIcon(p.status)"
                class="q-mr-md"
              />
              <div class="col">
                <div style="font-size: 15px; font-weight: 600; color: #0f172a">
                  {{ p.project_title }}
                </div>
                <div style="font-size: 12px; color: #64748b">
                  {{ p.protocol_number }} · {{ p.quote?.quote_number }}
                  <q-badge
                    :color="statusColor(p.status)"
                    :label="statusLabel(p.status)"
                    dense
                    class="q-ml-sm"
                  />
                </div>
              </div>
              <div style="font-size: 12px; color: #94a3b8">
                {{ formatDate(p.created_at) }}
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Detail/Edit -->
    <div v-else>
      <div class="row items-center q-mb-lg">
        <q-btn
          flat
          round
          icon="arrow_back"
          color="grey-7"
          @click="currentProtocol = null"
          class="q-mr-sm"
        />
        <div class="col">
          <div class="row items-center q-gutter-sm">
            <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
              {{ currentProtocol.project_title }}
            </h5>
            <q-badge
              :color="statusColor(currentProtocol.status)"
              :label="statusLabel(currentProtocol.status)"
            />
          </div>
          <p class="q-mb-none q-mt-xs" style="color: #64748b">
            {{ currentProtocol.protocol_number }}
          </p>
        </div>
        <div class="q-gutter-sm">
          <q-btn
            outline
            color="grey-7"
            icon="save"
            label="Speichern"
            no-caps
            @click="onSave"
            :loading="saving"
          />
          <q-btn
            color="primary"
            icon="picture_as_pdf"
            label="PDF"
            no-caps
            @click="onExportPdf"
          />
        </div>
      </div>

      <div class="row q-col-gutter-lg">
        <div class="col-12 col-md-8">
          <!-- Projektdaten -->
          <q-card
            flat
            class="q-mb-md"
            style="border: 1px solid #e2e8f0; border-radius: 12px"
          >
            <q-card-section>
              <div
                style="
                  font-size: 13px;
                  font-weight: 700;
                  color: #64748b;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                "
                class="q-mb-md"
              >
                Projektdaten
              </div>
              <div class="row q-gutter-md">
                <q-input
                  v-model="currentProtocol.project_title"
                  filled
                  dense
                  label="Projekttitel"
                  class="col"
                />
                <q-input
                  v-model="currentProtocol.project_address"
                  filled
                  dense
                  label="Adresse"
                  class="col"
                />
              </div>
              <div class="row q-gutter-md q-mt-sm">
                <q-input
                  v-model="currentProtocol.acceptance_date"
                  filled
                  dense
                  label="Abnahmedatum"
                  type="date"
                  class="col"
                />
                <q-input
                  v-model="currentProtocol.execution_start"
                  filled
                  dense
                  label="Ausführung von"
                  type="date"
                  class="col"
                />
                <q-input
                  v-model="currentProtocol.execution_end"
                  filled
                  dense
                  label="Ausführung bis"
                  type="date"
                  class="col"
                />
              </div>
              <div class="row q-gutter-md q-mt-sm">
                <q-input
                  v-model="currentProtocol.contractor_name"
                  filled
                  dense
                  label="Auftragnehmer"
                  class="col"
                />
                <q-input
                  v-model="currentProtocol.client_name"
                  filled
                  dense
                  label="Auftraggeber"
                  class="col"
                />
                <q-input
                  v-model="currentProtocol.client_representative"
                  filled
                  dense
                  label="Vertreter AG (optional)"
                  class="col"
                />
              </div>
            </q-card-section>
          </q-card>

          <!-- Zusammenfassung -->
          <q-card
            flat
            class="q-mb-md"
            style="border: 1px solid #e2e8f0; border-radius: 12px"
          >
            <q-card-section>
              <div class="row items-center justify-between q-mb-md">
                <div
                  style="
                    font-size: 13px;
                    font-weight: 700;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                  "
                >
                  Durchgeführte Arbeiten
                </div>
                <q-badge
                  v-if="currentProtocol.work_summary"
                  color="green-7"
                  label="KI-generiert"
                  dense
                />
              </div>
              <q-input
                v-model="currentProtocol.work_summary"
                filled
                type="textarea"
                rows="8"
                placeholder="Zusammenfassung der durchgeführten Arbeiten..."
              />
            </q-card-section>
          </q-card>

          <!-- Ergebnis -->
          <q-card
            flat
            class="q-mb-md"
            style="border: 1px solid #e2e8f0; border-radius: 12px"
          >
            <q-card-section>
              <div
                style="
                  font-size: 13px;
                  font-weight: 700;
                  color: #64748b;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                "
                class="q-mb-md"
              >
                Ergebnis der Abnahme
              </div>
              <div class="row q-gutter-md">
                <q-card
                  v-for="opt in resultOptions"
                  :key="opt.value"
                  flat
                  clickable
                  @click="currentProtocol.result = opt.value"
                  style="border-radius: 10px; cursor: pointer; flex: 1"
                  :style="
                    currentProtocol.result === opt.value
                      ? `border: 2px solid ${opt.color}; background: ${opt.bg};`
                      : 'border: 2px solid #e2e8f0;'
                  "
                >
                  <q-card-section class="text-center q-pa-md">
                    <q-icon
                      :name="opt.icon"
                      :color="
                        currentProtocol.result === opt.value
                          ? opt.qcolor
                          : 'grey-5'
                      "
                      size="28px"
                    />
                    <div
                      class="q-mt-xs"
                      style="font-size: 12px; font-weight: 600"
                      :style="
                        currentProtocol.result === opt.value
                          ? `color: ${opt.color};`
                          : 'color: #94a3b8;'
                      "
                    >
                      {{ opt.label }}
                    </div>
                  </q-card-section>
                </q-card>
              </div>
            </q-card-section>
          </q-card>

          <!-- Mängel -->
          <q-card
            flat
            class="q-mb-md"
            style="border: 1px solid #e2e8f0; border-radius: 12px"
          >
            <q-card-section>
              <div class="row items-center justify-between q-mb-md">
                <div
                  style="
                    font-size: 13px;
                    font-weight: 700;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                  "
                >
                  Mängelliste
                </div>
                <q-btn
                  flat
                  color="orange"
                  icon="add"
                  label="Mangel hinzufügen"
                  no-caps
                  dense
                  @click="addDefect"
                />
              </div>
              <div
                v-if="
                  !currentProtocol.defects ||
                  currentProtocol.defects.length === 0
                "
                class="text-center q-pa-md"
                style="
                  color: #94a3b8;
                  border: 2px dashed #e2e8f0;
                  border-radius: 8px;
                "
              >
                Keine Mängel festgestellt
              </div>
              <div v-else class="q-gutter-sm">
                <div
                  v-for="(defect, i) in currentProtocol.defects"
                  :key="i"
                  style="
                    background: #fff8f0;
                    border: 1px solid #feebc8;
                    border-radius: 8px;
                    padding: 12px;
                  "
                >
                  <div class="row q-gutter-sm items-start">
                    <q-input
                      v-model="defect.title"
                      filled
                      dense
                      label="Mangel"
                      class="col"
                    />
                    <q-select
                      v-model="defect.severity"
                      filled
                      dense
                      :options="severityOptions"
                      emit-value
                      map-options
                      label="Schwere"
                      style="width: 130px"
                    />
                    <q-input
                      v-model="defect.deadline"
                      filled
                      dense
                      label="Frist"
                      type="date"
                      style="width: 150px"
                    />
                    <q-btn
                      flat
                      round
                      dense
                      icon="close"
                      color="negative"
                      size="sm"
                      @click="removeDefect(i)"
                    />
                  </div>
                  <q-input
                    v-model="defect.description"
                    filled
                    dense
                    label="Beschreibung (optional)"
                    class="q-mt-xs"
                  />
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Bemerkungen + Vereinbarungen -->
          <q-card
            flat
            class="q-mb-md"
            style="border: 1px solid #e2e8f0; border-radius: 12px"
          >
            <q-card-section>
              <div
                style="
                  font-size: 13px;
                  font-weight: 700;
                  color: #64748b;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                "
                class="q-mb-md"
              >
                Bemerkungen & Vereinbarungen
              </div>
              <q-input
                v-model="currentProtocol.notes"
                filled
                type="textarea"
                rows="3"
                label="Bemerkungen (optional)"
                class="q-mb-md"
              />
              <q-input
                v-model="currentProtocol.agreements"
                filled
                type="textarea"
                rows="3"
                label="Vereinbarungen (optional)"
              />
            </q-card-section>
          </q-card>

          <!-- Unterschriften -->
          <q-card
            flat
            class="q-mb-md"
            style="border: 1px solid #e2e8f0; border-radius: 12px"
          >
            <q-card-section>
              <div
                style="
                  font-size: 13px;
                  font-weight: 700;
                  color: #64748b;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                "
                class="q-mb-md"
              >
                Unterschriften
              </div>
              <div class="row q-gutter-lg">
                <div class="col">
                  <div
                    style="font-size: 12px; font-weight: 600; color: #475569"
                    class="q-mb-sm"
                  >
                    Auftragnehmer
                  </div>
                  <div
                    v-if="currentProtocol.has_contractor_signature"
                    class="text-center q-pa-md"
                    style="
                      background: #f0fff4;
                      border: 1px solid #c6f6d5;
                      border-radius: 8px;
                    "
                  >
                    <q-icon name="check_circle" color="green" size="24px" />
                    <div
                      style="font-size: 11px; color: #48bb78; margin-top: 4px"
                    >
                      Unterschrieben
                      {{
                        currentProtocol.signed_contractor_at
                          ? "am " +
                            formatDate(currentProtocol.signed_contractor_at)
                          : ""
                      }}
                    </div>
                  </div>
                  <div v-else>
                    <canvas
                      ref="sigContractor"
                      width="340"
                      height="120"
                      style="
                        border: 2px dashed #e2e8f0;
                        border-radius: 8px;
                        cursor: crosshair;
                        width: 100%;
                        background: #fff;
                      "
                      @mousedown="startSign('contractor', $event)"
                      @mousemove="drawSign('contractor', $event)"
                      @mouseup="endSign"
                      @touchstart.prevent="startSignTouch('contractor', $event)"
                      @touchmove.prevent="drawSignTouch('contractor', $event)"
                      @touchend="endSign"
                    ></canvas>
                    <div class="row q-gutter-sm q-mt-xs">
                      <q-btn
                        flat
                        color="grey"
                        label="Löschen"
                        dense
                        no-caps
                        size="sm"
                        @click="clearSignature('contractor')"
                      />
                      <q-btn
                        flat
                        color="primary"
                        label="Bestätigen"
                        dense
                        no-caps
                        size="sm"
                        @click="submitSignature('contractor')"
                      />
                    </div>
                  </div>
                </div>
                <div class="col">
                  <div
                    style="font-size: 12px; font-weight: 600; color: #475569"
                    class="q-mb-sm"
                  >
                    Auftraggeber
                  </div>
                  <div
                    v-if="currentProtocol.has_client_signature"
                    class="text-center q-pa-md"
                    style="
                      background: #f0fff4;
                      border: 1px solid #c6f6d5;
                      border-radius: 8px;
                    "
                  >
                    <q-icon name="check_circle" color="green" size="24px" />
                    <div
                      style="font-size: 11px; color: #48bb78; margin-top: 4px"
                    >
                      Unterschrieben
                      {{
                        currentProtocol.signed_client_at
                          ? "am " + formatDate(currentProtocol.signed_client_at)
                          : ""
                      }}
                    </div>
                  </div>
                  <div v-else>
                    <canvas
                      ref="sigClient"
                      width="340"
                      height="120"
                      style="
                        border: 2px dashed #e2e8f0;
                        border-radius: 8px;
                        cursor: crosshair;
                        width: 100%;
                        background: #fff;
                      "
                      @mousedown="startSign('client', $event)"
                      @mousemove="drawSign('client', $event)"
                      @mouseup="endSign"
                      @touchstart.prevent="startSignTouch('client', $event)"
                      @touchmove.prevent="drawSignTouch('client', $event)"
                      @touchend="endSign"
                    ></canvas>
                    <div class="row q-gutter-sm q-mt-xs">
                      <q-btn
                        flat
                        color="grey"
                        label="Löschen"
                        dense
                        no-caps
                        size="sm"
                        @click="clearSignature('client')"
                      />
                      <q-btn
                        flat
                        color="primary"
                        label="Bestätigen"
                        dense
                        no-caps
                        size="sm"
                        @click="submitSignature('client')"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Sidebar -->
        <div class="col-12 col-md-4">
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              position: sticky;
              top: 80px;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 13px;
                  font-weight: 700;
                  color: #64748b;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                "
                class="q-mb-md"
              >
                Angebotspositionen
              </div>
              <div v-if="currentProtocol.quote?.items" class="q-gutter-xs">
                <div
                  v-for="item in currentProtocol.quote.items"
                  :key="item.id"
                  style="
                    font-size: 12px;
                    padding: 6px 0;
                    border-bottom: 1px solid #f1f5f9;
                  "
                >
                  <div class="row items-center">
                    <q-badge
                      :color="item.type === 'material' ? 'blue' : 'orange'"
                      :label="item.type === 'material' ? 'M' : 'A'"
                      dense
                      class="q-mr-sm"
                      style="font-size: 8px; width: 18px; text-align: center"
                    />
                    <span class="col" style="color: #475569">{{
                      item.title
                    }}</span>
                    <span style="color: #94a3b8; font-size: 11px"
                      >{{ item.quantity }} {{ item.unit }}</span
                    >
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script>
import { ref, onMounted, nextTick } from "vue";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";

export default {
  name: "AcceptanceProtocolPage",
  props: {
    quoteId: { type: [String, Number], default: null },
  },
  setup(props) {
    const $q = useQuasar();
    const loading = ref(false);
    const saving = ref(false);
    const protocols = ref([]);
    const currentProtocol = ref(null);

    // Signature state
    let isDrawing = false;
    let signType = null;

    const resultOptions = [
      {
        value: "accepted",
        label: "Ohne Mängel",
        icon: "check_circle",
        color: "#48bb78",
        bg: "#f0fff4",
        qcolor: "green",
      },
      {
        value: "accepted_with_defects",
        label: "Mit Mängeln",
        icon: "warning",
        color: "#ecc94b",
        bg: "#fffff0",
        qcolor: "amber",
      },
      {
        value: "rejected",
        label: "Verweigert",
        icon: "cancel",
        color: "#fc8181",
        bg: "#fff5f5",
        qcolor: "red",
      },
    ];

    const severityOptions = [
      { label: "Gering", value: "minor" },
      { label: "Wesentlich", value: "major" },
      { label: "Kritisch", value: "critical" },
    ];

    const loadProtocols = async () => {
      loading.value = true;
      try {
        const res = await api.get("/acceptance-protocols");
        protocols.value = res.data;
      } catch (e) {
        console.error(e);
      } finally {
        loading.value = false;
      }
    };

    const loadProtocol = async (id) => {
      try {
        const res = await api.get(`/acceptance-protocols/${id}`);
        currentProtocol.value = res.data;
      } catch (e) {
        console.error(e);
      }
    };

    const createFromQuote = async (quoteId) => {
      try {
        const res = await api.post("/acceptance-protocols", {
          quote_id: quoteId,
        });
        currentProtocol.value = res.data;
        $q.notify({ type: "positive", message: "Abnahmeprotokoll erstellt!" });
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler",
        });
      }
    };

    onMounted(async () => {
      await loadProtocols();
      if (props.quoteId) {
        await createFromQuote(props.quoteId);
      }
    });

    const onSave = async () => {
      saving.value = true;
      try {
        await api.put(
          `/acceptance-protocols/${currentProtocol.value.id}`,
          currentProtocol.value,
        );
        $q.notify({ type: "positive", message: "Gespeichert!" });
      } catch (e) {
        $q.notify({ type: "negative", message: "Fehler beim Speichern" });
      } finally {
        saving.value = false;
      }
    };

    const onExportPdf = () => {
      const t = localStorage.getItem("auth_token");
      window.open(
        `http://localhost:8000/api/acceptance-protocols/${currentProtocol.value.id}/pdf?token=${t}`,
        "_blank",
      );
    };

    // Defects
    const addDefect = () => {
      if (!currentProtocol.value.defects) currentProtocol.value.defects = [];
      currentProtocol.value.defects.push({
        title: "",
        description: "",
        severity: "minor",
        deadline: "",
      });
      // Auto-set result
      currentProtocol.value.result = "accepted_with_defects";
    };

    const removeDefect = (index) => {
      currentProtocol.value.defects.splice(index, 1);
      if (currentProtocol.value.defects.length === 0) {
        currentProtocol.value.result = "accepted";
      }
    };

    // Signature drawing
    const sigContractor = ref(null);
    const sigClient = ref(null);

    const getCanvas = (type) =>
      type === "contractor" ? sigContractor.value : sigClient.value;
    const getCtx = (type) => {
      const canvas = getCanvas(type);
      if (!canvas) return null;
      const ctx = canvas.getContext("2d");
      ctx.strokeStyle = "#1a202c";
      ctx.lineWidth = 2;
      ctx.lineCap = "round";
      return ctx;
    };

    const getPos = (canvas, e) => {
      const rect = canvas.getBoundingClientRect();
      const scaleX = canvas.width / rect.width;
      const scaleY = canvas.height / rect.height;
      return {
        x: (e.clientX - rect.left) * scaleX,
        y: (e.clientY - rect.top) * scaleY,
      };
    };

    const startSign = (type, e) => {
      isDrawing = true;
      signType = type;
      const ctx = getCtx(type);
      const pos = getPos(getCanvas(type), e);
      ctx.beginPath();
      ctx.moveTo(pos.x, pos.y);
    };

    const drawSign = (type, e) => {
      if (!isDrawing || signType !== type) return;
      const ctx = getCtx(type);
      const pos = getPos(getCanvas(type), e);
      ctx.lineTo(pos.x, pos.y);
      ctx.stroke();
    };

    const endSign = () => {
      isDrawing = false;
    };

    const startSignTouch = (type, e) => {
      const touch = e.touches[0];
      startSign(type, { clientX: touch.clientX, clientY: touch.clientY });
    };

    const drawSignTouch = (type, e) => {
      const touch = e.touches[0];
      drawSign(type, { clientX: touch.clientX, clientY: touch.clientY });
    };

    const clearSignature = (type) => {
      const canvas = getCanvas(type);
      if (!canvas) return;
      const ctx = canvas.getContext("2d");
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    };

    const submitSignature = async (type) => {
      const canvas = getCanvas(type);
      if (!canvas) return;
      const dataUrl = canvas.toDataURL("image/png");
      try {
        const res = await api.post(
          `/acceptance-protocols/${currentProtocol.value.id}/sign`,
          {
            type,
            signature: dataUrl,
          },
        );
        currentProtocol.value = res.data.protocol;
        $q.notify({ type: "positive", message: "Unterschrift gespeichert!" });
      } catch (e) {
        $q.notify({
          type: "negative",
          message: "Fehler beim Speichern der Unterschrift",
        });
      }
    };

    // Helpers
    const statusColor = (s) =>
      ({ draft: "grey", completed: "blue", signed: "green" })[s] || "grey";
    const statusIcon = (s) =>
      ({ draft: "edit", completed: "check", signed: "verified" })[s] || "edit";
    const statusLabel = (s) =>
      ({ draft: "Entwurf", completed: "Fertig", signed: "Unterschrieben" })[
        s
      ] || s;
    const formatDate = (d) =>
      d ? new Date(d).toLocaleDateString("de-DE") : "";

    return {
      loading,
      saving,
      protocols,
      currentProtocol,
      resultOptions,
      severityOptions,
      sigContractor,
      sigClient,
      loadProtocols,
      loadProtocol,
      createFromQuote,
      onSave,
      onExportPdf,
      addDefect,
      removeDefect,
      startSign,
      drawSign,
      endSign,
      startSignTouch,
      drawSignTouch,
      clearSignature,
      submitSignature,
      statusColor,
      statusIcon,
      statusLabel,
      formatDate,
    };
  },
};
</script>
