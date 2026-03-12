<template>
  <q-page class="q-pa-lg" style="background: #f6f9fc">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Leistungsvorlagen
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          Wiederkehrende Leistungen als Vorlage speichern und per Klick ins
          Angebot einfügen
        </p>
      </div>
      <q-btn
        color="primary"
        icon="add"
        label="Neue Vorlage"
        no-caps
        @click="openCreateDialog"
      />
    </div>

    <!-- Suche + Filter -->
    <div class="row q-gutter-sm q-mb-md">
      <q-input
        v-model="search"
        filled
        dense
        placeholder="Vorlage suchen..."
        class="col-12 col-md-4"
        style="background: #fff; border-radius: 8px"
        @update:model-value="loadTemplates"
      >
        <template v-slot:prepend
          ><q-icon name="search" color="grey-5"
        /></template>
      </q-input>
      <q-btn-toggle
        v-model="filterCategory"
        no-caps
        rounded
        toggle-color="primary"
        :options="categoryOptions"
        class="q-ml-sm"
      />
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="40px" />
    </div>

    <!-- Leere State -->
    <q-card
      v-else-if="templates.length === 0"
      flat
      class="text-center q-pa-xl"
      style="border: 2px dashed #e2e8f0; border-radius: 14px; background: #fff"
    >
      <q-icon name="content_paste" size="48px" color="grey-4" />
      <h6 class="q-mt-md q-mb-xs" style="color: #64748b">
        Noch keine Vorlagen
      </h6>
      <p style="color: #94a3b8">
        Erstelle deine erste Vorlage oder speichere ein Angebot als Vorlage.
      </p>
      <q-btn
        color="primary"
        icon="add"
        label="Erste Vorlage erstellen"
        no-caps
        class="q-mt-sm"
        @click="openCreateDialog"
      />
    </q-card>

    <!-- Vorlagen Grid -->
    <div v-else class="row q-col-gutter-md">
      <div
        v-for="tpl in filteredTemplates"
        :key="tpl.id"
        class="col-12 col-md-6 col-lg-4"
      >
        <q-card
          flat
          class="full-height"
          style="
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            transition: box-shadow 0.2s;
          "
          @click="openDetailDialog(tpl)"
        >
          <q-card-section>
            <div class="row items-center justify-between q-mb-sm">
              <q-badge
                v-if="tpl.category"
                :color="categoryColor(tpl.category)"
                :label="tpl.category"
              />
              <span v-else></span>
              <span style="font-size: 11px; color: #94a3b8"
                >{{ tpl.usage_count }}× verwendet</span
              >
            </div>
            <div style="font-size: 16px; font-weight: 600; color: #0f172a">
              {{ tpl.name }}
            </div>
            <div
              v-if="tpl.description"
              style="font-size: 12px; color: #64748b; margin-top: 4px"
              class="ellipsis-2-lines"
            >
              {{ tpl.description }}
            </div>
          </q-card-section>
          <q-separator />
          <q-card-section class="q-py-sm">
            <div class="row items-center justify-between">
              <span style="font-size: 12px; color: #64748b"
                >{{ tpl.items_count }} Positionen</span
              >
              <div class="q-gutter-xs">
                <q-btn
                  flat
                  round
                  dense
                  icon="edit"
                  size="sm"
                  color="grey-6"
                  @click.stop="openEditDialog(tpl)"
                />
                <q-btn
                  flat
                  round
                  dense
                  icon="delete"
                  size="sm"
                  color="negative"
                  @click.stop="onDelete(tpl)"
                />
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Detail Dialog -->
    <q-dialog v-model="showDetailDialog" maximized>
      <q-card style="border-radius: 0">
        <q-card-section
          class="row items-center q-pb-sm"
          style="background: #f8fafc; border-bottom: 1px solid #e2e8f0"
        >
          <q-btn
            flat
            round
            dense
            icon="close"
            color="grey-6"
            v-close-popup
            class="q-mr-sm"
          />
          <div class="col">
            <div style="font-size: 18px; font-weight: 700; color: #0f172a">
              {{ selectedTemplate?.name }}
            </div>
            <div style="font-size: 13px; color: #64748b">
              {{ selectedTemplate?.description || "Keine Beschreibung" }}
            </div>
          </div>
          <q-badge
            v-if="selectedTemplate?.category"
            :color="categoryColor(selectedTemplate.category)"
            :label="selectedTemplate.category"
            class="q-mr-md"
          />
          <q-btn
            outline
            color="grey-7"
            icon="edit"
            label="Bearbeiten"
            no-caps
            dense
            class="q-mr-sm"
            @click="
              openEditDialog(selectedTemplate);
              showDetailDialog = false;
            "
          />
        </q-card-section>
        <q-card-section
          class="q-pa-lg"
          style="max-width: 900px; margin: 0 auto"
        >
          <div v-if="detailLoading" class="flex flex-center q-pa-xl">
            <q-spinner color="primary" size="30px" />
          </div>
          <div v-else-if="detailItems.length > 0">
            <div
              v-for="(items, groupName) in groupedDetailItems"
              :key="groupName"
              class="q-mb-md"
            >
              <div
                class="q-mb-sm q-pa-xs q-pl-sm"
                style="
                  border-left: 3px solid #3b82f6;
                  font-size: 13px;
                  font-weight: 700;
                  color: #64748b;
                  text-transform: uppercase;
                "
              >
                {{ groupName }}
              </div>
              <q-card
                v-for="item in items"
                :key="item.id"
                flat
                class="q-mb-xs"
                style="
                  background: #f8fafc;
                  border: 1px solid #f1f5f9;
                  border-radius: 10px;
                "
              >
                <q-card-section class="q-py-sm q-px-md">
                  <div class="row items-center q-gutter-sm">
                    <div class="col">
                      <div class="row items-center q-gutter-xs">
                        <span
                          style="
                            font-size: 13.5px;
                            font-weight: 500;
                            color: #0f172a;
                          "
                          >{{ item.title }}</span
                        >
                        <q-badge
                          :color="item.type === 'material' ? 'blue' : 'orange'"
                          :label="
                            item.type === 'material' ? 'Material' : 'Arbeit'
                          "
                          dense
                          style="font-size: 10px"
                        />
                        <q-badge
                          v-if="item.material_id"
                          color="green-7"
                          label="Katalog"
                          dense
                          style="font-size: 10px"
                        />
                      </div>
                      <div
                        v-if="item.description"
                        style="font-size: 11px; margin-top: 2px; color: #94a3b8"
                      >
                        {{ item.description }}
                      </div>
                    </div>
                    <div
                      style="
                        width: 60px;
                        text-align: center;
                        font-size: 13px;
                        color: #475569;
                      "
                    >
                      {{ item.quantity }}
                    </div>
                    <div
                      style="
                        width: 50px;
                        text-align: center;
                        font-size: 12px;
                        color: #64748b;
                      "
                    >
                      {{ item.unit }}
                    </div>
                    <div
                      style="
                        width: 90px;
                        text-align: right;
                        font-size: 13px;
                        color: #475569;
                      "
                    >
                      {{ formatPrice(item.unit_price) }} €
                    </div>
                    <div
                      style="
                        width: 100px;
                        text-align: right;
                        font-weight: 600;
                        font-size: 13px;
                        color: #0f172a;
                      "
                    >
                      {{ formatPrice(item.quantity * item.unit_price) }} €
                    </div>
                  </div>
                </q-card-section>
              </q-card>
            </div>
            <q-separator class="q-my-md" />
            <div
              class="text-right"
              style="font-size: 18px; font-weight: 700; color: #1d4ed8"
            >
              Gesamt: {{ formatPrice(detailTotal) }} €
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Create/Edit Dialog -->
    <q-dialog v-model="showCreateDialog" persistent maximized>
      <q-card style="border-radius: 0">
        <q-card-section
          class="row items-center q-pb-sm"
          style="background: #f8fafc; border-bottom: 1px solid #e2e8f0"
        >
          <q-btn
            flat
            round
            dense
            icon="close"
            color="grey-6"
            v-close-popup
            class="q-mr-sm"
          />
          <h6 class="q-my-none" style="font-weight: 700; color: #0f172a">
            {{
              editingTemplate ? "Vorlage bearbeiten" : "Neue Vorlage erstellen"
            }}
          </h6>
        </q-card-section>
        <q-card-section
          class="q-pa-lg"
          style="max-width: 900px; margin: 0 auto"
        >
          <!-- Vorlage Info -->
          <div class="row q-gutter-md q-mb-lg">
            <q-input
              v-model="form.name"
              filled
              dense
              label="Name der Vorlage *"
              class="col"
              placeholder="z.B. Gäste-WC komplett"
            />
            <q-input
              v-model="form.category"
              filled
              dense
              label="Kategorie"
              style="width: 200px"
              placeholder="z.B. Sanitär"
            />
          </div>
          <q-input
            v-model="form.description"
            filled
            dense
            label="Beschreibung (optional)"
            class="q-mb-lg"
            placeholder="Kurze Beschreibung wann diese Vorlage genutzt wird"
          />

          <!-- Positionen -->
          <div class="row items-center justify-between q-mb-md">
            <div style="font-size: 14px; font-weight: 600; color: #0f172a">
              Positionen
            </div>
            <q-btn
              flat
              color="primary"
              icon="add"
              label="Position hinzufügen"
              no-caps
              dense
              @click="addFormItem"
            />
          </div>

          <div
            v-for="(item, index) in form.items"
            :key="index"
            class="q-mb-sm"
            style="
              background: #f8fafc;
              border: 1px solid #e2e8f0;
              border-radius: 10px;
              padding: 12px;
            "
          >
            <div class="row q-gutter-sm items-center">
              <q-select
                v-model="item.type"
                filled
                dense
                :options="[
                  { label: 'Material', value: 'material' },
                  { label: 'Arbeit', value: 'labor' },
                ]"
                emit-value
                map-options
                style="width: 110px"
              />
              <q-input
                v-model="item.group_name"
                filled
                dense
                label="Gruppe"
                style="width: 180px"
              />
              <q-input
                v-model="item.title"
                filled
                dense
                label="Bezeichnung *"
                class="col"
              />
              <q-btn
                flat
                round
                dense
                icon="close"
                color="negative"
                size="sm"
                @click="removeFormItem(index)"
              />
            </div>
            <div class="row q-gutter-sm q-mt-xs">
              <q-input
                v-model="item.description"
                filled
                dense
                label="Beschreibung (optional)"
                class="col"
              />
              <q-input
                v-model.number="item.quantity"
                filled
                dense
                label="Menge"
                type="number"
                style="width: 80px"
              />
              <q-input
                v-model="item.unit"
                filled
                dense
                label="Einheit"
                style="width: 80px"
              />
              <q-input
                v-model.number="item.unit_price"
                filled
                dense
                label="Preis €"
                type="number"
                style="width: 100px"
              />
              <div
                style="
                  width: 90px;
                  text-align: right;
                  line-height: 40px;
                  font-weight: 600;
                  font-size: 13px;
                  color: #1d4ed8;
                "
              >
                {{
                  formatPrice((item.quantity || 0) * (item.unit_price || 0))
                }}
                €
              </div>
            </div>
          </div>

          <div
            v-if="form.items.length === 0"
            class="text-center q-pa-lg"
            style="
              border: 2px dashed #e2e8f0;
              border-radius: 10px;
              color: #94a3b8;
            "
          >
            Noch keine Positionen. Klicke "Position hinzufügen" oben.
          </div>

          <!-- Gesamtsumme -->
          <div
            v-if="form.items.length > 0"
            class="text-right q-mt-md"
            style="font-size: 16px; font-weight: 700; color: #1d4ed8"
          >
            Gesamt: {{ formatPrice(formTotal) }} €
          </div>
        </q-card-section>

        <q-card-actions
          align="right"
          class="q-pa-lg"
          style="border-top: 1px solid #e2e8f0"
        >
          <q-btn flat label="Abbrechen" color="grey" v-close-popup />
          <q-btn
            :label="editingTemplate ? 'Speichern' : 'Vorlage erstellen'"
            color="primary"
            no-caps
            icon="save"
            @click="onSave"
            :loading="saving"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { ref, computed, onMounted, watch } from "vue";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";

export default {
  name: "ServiceTemplatesPage",
  setup() {
    const $q = useQuasar();
    const loading = ref(false);
    const saving = ref(false);
    const templates = ref([]);
    const search = ref("");
    const filterCategory = ref("all");

    // Detail Dialog
    const showDetailDialog = ref(false);
    const selectedTemplate = ref(null);
    const detailItems = ref([]);
    const detailLoading = ref(false);

    // Create/Edit Dialog
    const showCreateDialog = ref(false);
    const editingTemplate = ref(null);
    const form = ref({
      name: "",
      category: "",
      description: "",
      items: [],
    });

    // Load templates
    const loadTemplates = async () => {
      loading.value = true;
      try {
        const params = {};
        if (search.value) params.search = search.value;
        if (filterCategory.value !== "all")
          params.category = filterCategory.value;
        const res = await api.get("/service-templates", { params });
        templates.value = res.data.templates || [];
      } catch (e) {
        console.error(e);
      } finally {
        loading.value = false;
      }
    };

    onMounted(loadTemplates);
    watch(filterCategory, loadTemplates);

    // Category helpers
    const categoryOptions = computed(() => {
      const cats = [
        ...new Set(templates.value.map((t) => t.category).filter(Boolean)),
      ];
      return [
        { label: "Alle", value: "all" },
        ...cats.map((c) => ({ label: c, value: c })),
      ];
    });

    const categoryColor = (cat) => {
      const map = {
        Sanitär: "blue",
        Heizung: "red",
        Klima: "cyan",
        Elektro: "amber",
        Solar: "orange",
        Wartung: "teal",
      };
      return map[cat] || "grey-7";
    };

    const filteredTemplates = computed(() => {
      if (filterCategory.value === "all") return templates.value;
      return templates.value.filter((t) => t.category === filterCategory.value);
    });

    // Detail
    const groupedDetailItems = computed(() => {
      const g = {};
      detailItems.value.forEach((i) => {
        const gr = i.group_name || "Sonstiges";
        if (!g[gr]) g[gr] = [];
        g[gr].push(i);
      });
      return g;
    });

    const detailTotal = computed(() => {
      return detailItems.value.reduce(
        (sum, i) => sum + i.quantity * i.unit_price,
        0,
      );
    });

    const openDetailDialog = async (tpl) => {
      selectedTemplate.value = tpl;
      showDetailDialog.value = true;
      detailLoading.value = true;
      try {
        const res = await api.get(`/service-templates/${tpl.id}`);
        detailItems.value = res.data.items || [];
      } catch (e) {
        console.error(e);
      } finally {
        detailLoading.value = false;
      }
    };

    // Create
    const openCreateDialog = () => {
      editingTemplate.value = null;
      form.value = {
        name: "",
        category: "",
        description: "",
        items: [
          {
            type: "material",
            group_name: "",
            title: "",
            description: "",
            quantity: 1,
            unit: "Stück",
            unit_price: 0,
            material_id: null,
          },
        ],
      };
      showCreateDialog.value = true;
    };

    // Edit
    const openEditDialog = async (tpl) => {
      editingTemplate.value = tpl;
      // Lade vollständige Daten
      try {
        const res = await api.get(`/service-templates/${tpl.id}`);
        form.value = {
          name: res.data.name,
          category: res.data.category || "",
          description: res.data.description || "",
          items: (res.data.items || []).map((i) => ({
            type: i.type,
            group_name: i.group_name,
            title: i.title,
            description: i.description || "",
            quantity: Number(i.quantity),
            unit: i.unit,
            unit_price: Number(i.unit_price),
            material_id: i.material_id,
          })),
        };
      } catch (e) {
        console.error(e);
      }
      showCreateDialog.value = true;
    };

    const addFormItem = () => {
      const lastGroup =
        form.value.items.length > 0
          ? form.value.items[form.value.items.length - 1].group_name
          : "";
      form.value.items.push({
        type: "material",
        group_name: lastGroup,
        title: "",
        description: "",
        quantity: 1,
        unit: "Stück",
        unit_price: 0,
        material_id: null,
      });
    };

    const removeFormItem = (index) => {
      form.value.items.splice(index, 1);
    };

    const formTotal = computed(() => {
      return form.value.items.reduce(
        (sum, i) => sum + (i.quantity || 0) * (i.unit_price || 0),
        0,
      );
    });

    const onSave = async () => {
      if (!form.value.name) {
        $q.notify({ type: "warning", message: "Bitte Name eingeben" });
        return;
      }
      if (form.value.items.length === 0) {
        $q.notify({
          type: "warning",
          message: "Mindestens eine Position hinzufügen",
        });
        return;
      }

      saving.value = true;
      try {
        if (editingTemplate.value) {
          // Update: Vorlage löschen und neu erstellen (einfachster Weg für Items)
          await api.delete(`/service-templates/${editingTemplate.value.id}`);
          await api.post("/service-templates", form.value);
          $q.notify({ type: "positive", message: "Vorlage aktualisiert" });
        } else {
          await api.post("/service-templates", form.value);
          $q.notify({ type: "positive", message: "Vorlage erstellt" });
        }
        showCreateDialog.value = false;
        await loadTemplates();
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Speichern",
        });
      } finally {
        saving.value = false;
      }
    };

    const onDelete = (tpl) => {
      $q.dialog({
        title: "Vorlage löschen?",
        message: `"${tpl.name}" wirklich löschen?`,
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          await api.delete(`/service-templates/${tpl.id}`);
          $q.notify({ type: "positive", message: "Vorlage gelöscht" });
          await loadTemplates();
        } catch (e) {
          $q.notify({ type: "negative", message: "Fehler beim Löschen" });
        }
      });
    };

    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

    return {
      loading,
      saving,
      templates,
      search,
      filterCategory,
      showDetailDialog,
      selectedTemplate,
      detailItems,
      detailLoading,
      groupedDetailItems,
      detailTotal,
      showCreateDialog,
      editingTemplate,
      form,
      formTotal,
      categoryOptions,
      categoryColor,
      filteredTemplates,
      openDetailDialog,
      openCreateDialog,
      openEditDialog,
      addFormItem,
      removeFormItem,
      onSave,
      onDelete,
      loadTemplates,
      formatPrice,
    };
  },
};
</script>
