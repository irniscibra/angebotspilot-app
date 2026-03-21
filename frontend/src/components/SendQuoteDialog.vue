<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <q-card style="width: 95vw; max-width: 500px; border-radius: 16px">
      <!-- Header -->
      <q-card-section
        class="row items-center q-pb-sm"
        style="background: #f8fafc; border-bottom: 1px solid #e2e8f0"
      >
        <q-icon name="send" color="green" size="24px" class="q-mr-sm" />
        <div>
          <h6 class="q-my-none" style="font-weight: 700; color: #0f172a">
            Angebot versenden
          </h6>
          <span style="font-size: 12px; color: #94a3b8"
            >{{ quote?.quote_number }} · PDF wird automatisch angehängt</span
          >
        </div>
        <q-space />
        <q-btn flat round dense icon="close" color="grey-5" v-close-popup />
      </q-card-section>

      <!-- Form -->
      <q-card-section class="q-gutter-md q-pt-lg">
        <!-- Empfänger E-Mail -->
        <q-input
          v-model="form.recipient_email"
          filled
          label="Empfänger E-Mail *"
          type="email"
          :rules="[
            (val) => !!val || 'E-Mail ist erforderlich',
            (val) => /.+@.+\..+/.test(val) || 'Ungültige E-Mail',
          ]"
        >
          <template v-slot:prepend
            ><q-icon name="email" color="grey-5"
          /></template>
        </q-input>

        <!-- Empfänger Name -->
        <q-input
          v-model="form.recipient_name"
          filled
          label="Empfänger Name *"
          :rules="[(val) => !!val || 'Name ist erforderlich']"
        >
          <template v-slot:prepend
            ><q-icon name="person" color="grey-5"
          /></template>
        </q-input>

        <!-- Betreff (vorausgefüllt) -->
        <q-input v-model="form.subject" filled label="Betreff">
          <template v-slot:prepend
            ><q-icon name="subject" color="grey-5"
          /></template>
        </q-input>

        <!-- Persönliche Nachricht -->
        <q-input
          v-model="form.message"
          filled
          type="textarea"
          rows="4"
          label="Persönliche Nachricht (optional)"
          placeholder="z.B. Vielen Dank für das nette Gespräch. Anbei unser Angebot..."
        >
          <template v-slot:prepend
            ><q-icon name="chat" color="grey-5" style="margin-top: 4px"
          /></template>
        </q-input>

        <!-- Vorschau Box -->
        <div
          style="
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
          "
        >
          <div
            style="
              font-size: 11px;
              font-weight: 600;
              text-transform: uppercase;
              color: #94a3b8;
              letter-spacing: 0.05em;
              margin-bottom: 8px;
            "
          >
            Wird gesendet
          </div>
          <div class="row items-center q-gutter-sm">
            <q-icon name="picture_as_pdf" color="red" size="20px" />
            <span style="font-size: 13px; color: #0f172a; font-weight: 500"
              >{{ quote?.quote_number }}.pdf</span
            >
            <q-badge
              color="green-7"
              label="PDF automatisch angehängt"
              dense
              style="font-size: 10px"
            />
          </div>
          <div style="margin-top: 8px; font-size: 12px; color: #64748b">
            {{ quote?.project_title }} · {{ formatPrice(quote?.total_gross) }} €
            brutto
          </div>
          <div style="margin-top: 4px; font-size: 11px; color: #94a3b8">
            Antworten des Kunden gehen an Ihre Firmen-E-Mail
          </div>
        </div>
      </q-card-section>

      <!-- Actions -->
      <q-card-actions
        align="right"
        class="q-pa-md"
        style="border-top: 1px solid #e2e8f0"
      >
        <q-btn flat label="Abbrechen" color="grey" v-close-popup no-caps />
        <q-btn
          color="green"
          icon="send"
          label="Jetzt senden"
          no-caps
          :loading="sending"
          :disable="!form.recipient_email || !form.recipient_name"
          @click="onSend"
          style="border-radius: 10px; font-weight: 600; padding: 8px 24px"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script>
import { ref, watch } from "vue";
import { api } from "src/boot/axios";
import { useQuasar } from "quasar";

export default {
  name: "SendQuoteDialog",
  props: {
    modelValue: Boolean,
    quote: Object,
  },
  emits: ["update:modelValue", "sent"],
  setup(props, { emit }) {
    const $q = useQuasar();
    const sending = ref(false);

    const form = ref({
      recipient_email: "",
      recipient_name: "",
      subject: "",
      message: "",
    });

    // Formular vorausfüllen wenn Dialog geöffnet wird
    watch(
      () => props.modelValue,
      (val) => {
        if (val && props.quote) {
          const customer = props.quote.customer;
          if (customer) {
            form.value.recipient_email = customer.email || "";
            form.value.recipient_name =
              customer.type === "business"
                ? customer.company_name || ""
                : (
                    (customer.first_name || "") +
                    " " +
                    (customer.last_name || "")
                  ).trim();
          } else {
            form.value.recipient_email = "";
            form.value.recipient_name = "";
          }
          form.value.subject =
            props.quote.quote_number +
            " – Angebot: " +
            props.quote.project_title;
          form.value.message = "";
        }
      },
    );

    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

    const onSend = async () => {
      if (!form.value.recipient_email || !form.value.recipient_name) {
        $q.notify({
          type: "warning",
          message: "Bitte E-Mail und Name eingeben",
        });
        return;
      }

      sending.value = true;
      try {
        const res = await api.post(`/quotes/${props.quote.id}/send`, {
          recipient_email: form.value.recipient_email,
          recipient_name: form.value.recipient_name,
          subject: form.value.subject,
          message: form.value.message,
        });

        $q.notify({
          type: "positive",
          message: "Angebot erfolgreich versendet!",
          caption: `An ${form.value.recipient_email}`,
          icon: "check_circle",
          timeout: 4000,
        });

        emit("sent", res.data.quote);
        emit("update:modelValue", false);
      } catch (e) {
        const errorMsg =
          e.response?.data?.message || "E-Mail konnte nicht gesendet werden";
        $q.notify({
          type: "negative",
          message: "Versand fehlgeschlagen",
          caption: errorMsg,
          icon: "error",
          timeout: 6000,
        });
      } finally {
        sending.value = false;
      }
    };

    return { form, sending, formatPrice, onSend };
  },
};
</script>
