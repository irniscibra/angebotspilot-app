<template>
  <q-page class="q-pa-lg">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Kunden
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          {{ customers.length }} Kunden gesamt
        </p>
      </div>
      <q-btn
        color="primary"
        icon="person_add"
        label="Neuer Kunde"
        no-caps
        @click="openDialog()"
        style="border-radius: 10px; font-weight: 600"
      />
    </div>
    <q-input
      v-model="search"
      filled
      placeholder="Kunden suchen..."
      class="q-mb-md"
      style="max-width: 400px"
      clearable
      ><template v-slot:prepend
        ><q-icon name="search" color="grey-5" /></template
    ></q-input>
    <div class="row q-col-gutter-md">
      <div
        class="col-12 col-sm-6 col-md-4"
        v-for="customer in filteredCustomers"
        :key="customer.id"
      >
        <q-card
          flat
          class="full-height"
          style="
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            cursor: pointer;
          "
          @click="openDialog(customer)"
        >
          <q-card-section>
            <div class="row items-center no-wrap q-mb-sm">
              <q-avatar
                size="40px"
                :color="customer.type === 'business' ? 'blue' : 'teal'"
                text-color="white"
                :icon="customer.type === 'business' ? 'business' : 'person'"
                class="q-mr-sm"
              />
              <div class="col">
                <div style="font-size: 14px; font-weight: 600; color: #0f172a">
                  {{ displayName(customer) }}
                </div>
                <q-badge
                  :color="customer.type === 'business' ? 'blue' : 'teal'"
                  :label="
                    customer.type === 'business'
                      ? 'Geschäftskunde'
                      : 'Privatkunde'
                  "
                  dense
                  style="font-size: 10px"
                />
              </div>
              <q-btn
                flat
                round
                dense
                icon="more_vert"
                color="grey-5"
                @click.stop
              >
                <q-menu>
                  <q-list>
                    <q-item
                      clickable
                      v-close-popup
                      @click="openDialog(customer)"
                      ><q-item-section avatar
                        ><q-icon name="edit" /></q-item-section
                      ><q-item-section>Bearbeiten</q-item-section></q-item
                    >
                    <q-item
                      clickable
                      v-close-popup
                      @click="
                        $router.push('/quotes/create?customer=' + customer.id)
                      "
                      ><q-item-section avatar
                        ><q-icon name="add" /></q-item-section
                      ><q-item-section>Neues Angebot</q-item-section></q-item
                    >
                    <q-separator />
                    <q-item
                      clickable
                      v-close-popup
                      @click="onDelete(customer)"
                      class="text-negative"
                      ><q-item-section avatar
                        ><q-icon
                          name="delete"
                          color="negative" /></q-item-section
                      ><q-item-section>Löschen</q-item-section></q-item
                    >
                  </q-list>
                </q-menu>
              </q-btn>
            </div>
            <div class="q-gutter-xs" style="font-size: 12px">
              <div
                v-if="customer.email"
                class="row items-center q-gutter-xs"
                style="color: #64748b"
              >
                <q-icon name="email" size="14px" /><span>{{
                  customer.email
                }}</span>
              </div>
              <div
                v-if="customer.phone || customer.mobile"
                class="row items-center q-gutter-xs"
                style="color: #64748b"
              >
                <q-icon name="phone" size="14px" /><span>{{
                  customer.phone || customer.mobile
                }}</span>
              </div>
              <div
                v-if="customer.address_street"
                class="row items-center q-gutter-xs"
                style="color: #64748b"
              >
                <q-icon name="location_on" size="14px" /><span
                  >{{ customer.address_street }}, {{ customer.address_zip }}
                  {{ customer.address_city }}</span
                >
              </div>
            </div>
            <div v-if="customer.quotes_count > 0" class="q-mt-sm">
              <q-badge
                outline
                color="grey"
                :label="customer.quotes_count + ' Angebote'"
              />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
    <div v-if="!loading && customers.length === 0" class="text-center q-pa-xl">
      <q-icon name="people_outline" size="64px" color="grey-5" />
      <h6 class="q-mt-md" style="color: #64748b">Noch keine Kunden</h6>
      <p style="color: #94a3b8">Legen Sie Ihren ersten Kunden an.</p>
      <q-btn
        color="primary"
        icon="person_add"
        label="Ersten Kunden anlegen"
        no-caps
        @click="openDialog()"
      />
    </div>
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="50px" />
    </div>
    <q-dialog v-model="showDialog" persistent>
      <q-card style="width: 520px; max-width: 95vw; border-radius: 16px">
        <q-card-section class="row items-center q-pb-sm"
          ><h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
            {{ editingCustomer ? "Kunde bearbeiten" : "Neuer Kunde" }}
          </h6>
          <q-space /><q-btn
            flat
            round
            dense
            icon="close"
            color="grey-5"
            v-close-popup
        /></q-card-section>
        <q-card-section class="q-gutter-sm">
          <q-btn-toggle
            v-model="form.type"
            toggle-color="primary"
            no-caps
            spread
            :options="[
              { label: 'Privatkunde', value: 'private', icon: 'person' },
              { label: 'Geschäftskunde', value: 'business', icon: 'business' },
            ]"
            class="q-mb-sm"
            style="border-radius: 10px"
          />
          <q-input
            v-if="form.type === 'business'"
            v-model="form.company_name"
            filled
            label="Firmenname *"
            :rules="[(val) => !!val || 'Pflichtfeld']"
          />
          <q-input
            v-if="form.type === 'business'"
            v-model="form.contact_person"
            filled
            label="Ansprechpartner"
          />
          <div v-if="form.type === 'private'" class="row q-gutter-sm">
            <q-input
              v-model="form.first_name"
              filled
              label="Vorname *"
              class="col"
              :rules="[(val) => !!val || 'Pflichtfeld']"
            /><q-input
              v-model="form.last_name"
              filled
              label="Nachname *"
              class="col"
              :rules="[(val) => !!val || 'Pflichtfeld']"
            />
          </div>
          <q-separator class="q-my-sm" />
          <q-input v-model="form.email" filled label="E-Mail" type="email"
            ><template v-slot:prepend
              ><q-icon name="email" color="grey-5" /></template
          ></q-input>
          <div class="row q-gutter-sm">
            <q-input v-model="form.phone" filled label="Telefon" class="col"
              ><template v-slot:prepend
                ><q-icon name="phone" color="grey-5" /></template></q-input
            ><q-input v-model="form.mobile" filled label="Mobil" class="col"
              ><template v-slot:prepend
                ><q-icon name="smartphone" color="grey-5" /></template
            ></q-input>
          </div>
          <q-separator class="q-my-sm" />
          <q-input
            v-model="form.address_street"
            filled
            label="Straße & Hausnummer"
            ><template v-slot:prepend
              ><q-icon name="location_on" color="grey-5" /></template
          ></q-input>
          <div class="row q-gutter-sm">
            <q-input
              v-model="form.address_zip"
              filled
              label="PLZ"
              style="max-width: 120px"
            /><q-input
              v-model="form.address_city"
              filled
              label="Ort"
              class="col"
            />
          </div>
          <q-input
            v-model="form.notes"
            filled
            label="Interne Notizen"
            type="textarea"
            rows="2"
          />
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"
          ><q-btn flat label="Abbrechen" color="grey" v-close-popup /><q-btn
            :label="editingCustomer ? 'Speichern' : 'Kunde anlegen'"
            color="primary"
            no-caps
            :loading="saving"
            @click="onSave"
            icon="save"
        /></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>
<script>
import { ref, reactive, computed, onMounted } from "vue";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";
export default {
  name: "CustomersPage",
  setup() {
    const $q = useQuasar();
    const loading = ref(true);
    const saving = ref(false);
    const customers = ref([]);
    const search = ref("");
    const showDialog = ref(false);
    const editingCustomer = ref(null);
    const emptyForm = {
      type: "private",
      first_name: "",
      last_name: "",
      company_name: "",
      contact_person: "",
      email: "",
      phone: "",
      mobile: "",
      address_street: "",
      address_zip: "",
      address_city: "",
      notes: "",
    };
    const form = reactive({ ...emptyForm });
    const filteredCustomers = computed(() => {
      if (!search.value) return customers.value;
      const s = search.value.toLowerCase();
      return customers.value.filter((c) => {
        const name = displayName(c).toLowerCase();
        return (
          name.includes(s) ||
          (c.email || "").toLowerCase().includes(s) ||
          (c.address_city || "").toLowerCase().includes(s)
        );
      });
    });
    const displayName = (c) => {
      if (c.type === "business")
        return c.company_name || c.contact_person || "Unbekannt";
      return (
        [c.first_name, c.last_name].filter(Boolean).join(" ") || "Unbekannt"
      );
    };
    const loadCustomers = async () => {
      loading.value = true;
      try {
        const r = await api.get("/customers");
        customers.value = r.data.data || r.data;
      } catch (e) {
        console.error(e);
      } finally {
        loading.value = false;
      }
    };
    onMounted(loadCustomers);
    const openDialog = (customer = null) => {
      editingCustomer.value = customer;
      if (customer) {
        Object.keys(emptyForm).forEach((k) => {
          form[k] = customer[k] || emptyForm[k];
        });
      } else {
        Object.assign(form, { ...emptyForm });
      }
      showDialog.value = true;
    };
    const onSave = async () => {
      if (form.type === "private" && (!form.first_name || !form.last_name)) {
        $q.notify({
          type: "warning",
          message: "Vor- und Nachname sind Pflichtfelder",
        });
        return;
      }
      if (form.type === "business" && !form.company_name) {
        $q.notify({
          type: "warning",
          message: "Firmenname ist ein Pflichtfeld",
        });
        return;
      }
      saving.value = true;
      try {
        if (editingCustomer.value) {
          await api.put(`/customers/${editingCustomer.value.id}`, form);
          $q.notify({ type: "positive", message: "Kunde aktualisiert" });
        } else {
          await api.post("/customers", form);
          $q.notify({ type: "positive", message: "Kunde angelegt" });
        }
        showDialog.value = false;
        await loadCustomers();
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Speichern",
        });
      } finally {
        saving.value = false;
      }
    };
    const onDelete = (customer) => {
      $q.dialog({
        title: "Kunde löschen?",
        message: `"${displayName(customer)}" wirklich löschen?`,
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          await api.delete(`/customers/${customer.id}`);
          customers.value = customers.value.filter((c) => c.id !== customer.id);
          $q.notify({ type: "positive", message: "Kunde gelöscht" });
        } catch (e) {
          $q.notify({ type: "negative", message: "Fehler beim Löschen" });
        }
      });
    };
    return {
      loading,
      saving,
      customers,
      search,
      showDialog,
      editingCustomer,
      form,
      filteredCustomers,
      displayName,
      openDialog,
      onSave,
      onDelete,
    };
  },
};
</script>
