<template>
  <q-dialog v-model="isOpen" persistent>
    <q-card style="width: 95vw; max-width: 480px; border-radius: 16px">
      <q-card-section class="row items-center q-pb-sm">
        <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
          Feedback geben
        </h6>
        <q-space />
        <q-btn flat round dense icon="close" color="grey-5" v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-none q-gutter-sm">
        <p style="font-size: 13px; color: #64748b; margin: 0 0 4px;">
          Fehler, falsche Preise, Ideen oder sonstiges – wir lesen alles.
        </p>

        <q-select
          v-model="form.type"
          filled
          dense
          label="Kategorie"
          :options="typeOptions"
          emit-value
          map-options
        />

        <q-input
          v-model="form.message"
          filled
          type="textarea"
          rows="5"
          label="Deine Nachricht"
          placeholder="Was ist aufgefallen?"
        />

        <div v-if="quoteId" style="font-size: 12px; color: #94a3b8;">
          <q-icon name="link" size="14px" class="q-mr-xs" />
          Wird mit diesem Angebot verknüpft (ID {{ quoteId }})
        </div>
      </q-card-section>

      <q-card-actions align="right" class="q-pa-md">
        <q-btn flat label="Abbrechen" color="grey" v-close-popup />
        <q-btn
          label="Absenden"
          color="primary"
          no-caps
          icon="send"
          :loading="sending"
          :disable="!form.message"
          @click="onSubmit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script>
import { ref, computed, watch } from "vue";
import { useQuasar } from "quasar";
import { useRoute } from "vue-router";
import { api } from "src/boot/axios";

export default {
  name: "FeedbackDialog",
  props: {
    modelValue: { type: Boolean, default: false },
    quoteId: { type: [Number, String], default: null },
  },
  emits: ["update:modelValue"],
  setup(props, { emit }) {
    const $q = useQuasar();
    const route = useRoute();
    const sending = ref(false);

    const typeOptions = [
      { label: "Falsche Kalkulation / Preis", value: "kalkulation" },
      { label: "Fehler / Bug", value: "bug" },
      { label: "Idee / Verbesserungsvorschlag", value: "idea" },
      { label: "Sonstiges", value: "sonstiges" },
    ];

    const form = ref({
      type: "sonstiges",
      message: "",
    });

    const isOpen = computed({
      get: () => props.modelValue,
      set: (val) => emit("update:modelValue", val),
    });

    watch(isOpen, (val) => {
      if (val) {
        form.value = { type: props.quoteId ? "kalkulation" : "sonstiges", message: "" };
      }
    });

    const onSubmit = async () => {
      if (!form.value.message) return;
      sending.value = true;
      try {
        await api.post("/feedback", {
          type: form.value.type,
          message: form.value.message,
          quote_id: props.quoteId || null,
          page_context: route.fullPath,
        });
        $q.notify({ type: "positive", message: "Danke für dein Feedback!" });
        isOpen.value = false;
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Senden",
        });
      } finally {
        sending.value = false;
      }
    };

    return { isOpen, form, typeOptions, sending, onSubmit };
  },
};
</script>