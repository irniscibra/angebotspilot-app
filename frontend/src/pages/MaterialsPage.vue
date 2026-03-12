<template>
  <q-page class="q-pa-lg">
    <div class="row items-center q-mb-lg">
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
          Materialkatalog
        </h5>
        <p class="q-mb-none q-mt-xs" style="color: #64748b">
          {{ materials.length }} Materialien in
          {{ Object.keys(categories).length }} Kategorien
        </p>
      </div>
      <q-btn
        color="primary"
        icon="add"
        label="Material hinzufügen"
        no-caps
        @click="openDialog()"
        style="border-radius: 10px; font-weight: 600"
      />
    </div>
    <div class="row q-gutter-md q-mb-md">
      <q-input
        v-model="search"
        filled
        placeholder="Material suchen..."
        style="min-width: 300px"
        clearable
        ><template v-slot:prepend
          ><q-icon name="search" color="grey-5" /></template></q-input
      ><q-select
        v-model="selectedCategory"
        filled
        :options="categoryOptions"
        label="Kategorie"
        style="min-width: 200px"
        clearable
        emit-value
        map-options
      />
    </div>
    <div class="row q-gutter-xs q-mb-lg">
      <q-chip
        v-for="(count, cat) in categories"
        :key="cat"
        clickable
        :color="selectedCategory === cat ? 'primary' : 'white'"
        :text-color="selectedCategory === cat ? 'white' : 'grey-8'"
        @click="selectedCategory = selectedCategory === cat ? null : cat"
        :style="selectedCategory !== cat ? 'border: 1px solid #e2e8f0;' : ''"
        >{{ cat
        }}<q-badge
          :label="count"
          floating
          color="primary"
          v-if="selectedCategory !== cat"
      /></q-chip>
    </div>
    <q-card
      flat
      style="
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #ffffff;
      "
      ><q-card-section class="q-pa-none">
        <q-table
          :rows="filteredMaterials"
          :columns="columns"
          row-key="id"
          flat
          :loading="loading"
          no-data-label="Keine Materialien gefunden"
          :pagination="{ rowsPerPage: 25 }"
          @row-click="(evt, row) => openDialog(row)"
          class="cursor-pointer"
        >
          <template v-slot:body-cell-name="props"
            ><q-td :props="props"
              ><div style="font-weight: 500; color: #0f172a">
                {{ props.row.name }}
              </div>
              <div
                v-if="props.row.description"
                style="font-size: 11px; color: #94a3b8"
              >
                {{ props.row.description }}
              </div></q-td
            ></template
          >
          <template v-slot:body-cell-category="props"
            ><q-td :props="props"
              ><q-badge :label="props.value" color="blue-grey" /></q-td
          ></template>
          <template v-slot:body-cell-selling_price="props"
            ><q-td :props="props"
              ><span style="font-weight: 600; color: #0f172a"
                >{{ fmtP(props.value) }} €</span
              ></q-td
            ></template
          >
          <template v-slot:body-cell-purchase_price="props"
            ><q-td :props="props"
              ><span style="color: #64748b">{{
                props.value ? fmtP(props.value) + " €" : "-"
              }}</span></q-td
            ></template
          >
          <template v-slot:body-cell-margin="props"
            ><q-td :props="props"
              ><span v-if="props.row.purchase_price" :class="mrgCls(props.row)"
                >{{ cMrg(props.row) }}%</span
              ><span v-else style="color: #94a3b8">-</span></q-td
            ></template
          >
          <template v-slot:body-cell-is_active="props"
            ><q-td :props="props"
              ><q-badge
                :color="props.value ? 'positive' : 'grey'"
                :label="props.value ? 'Aktiv' : 'Inaktiv'" /></q-td
          ></template>
          <template v-slot:body-cell-actions="props"
            ><q-td :props="props"
              ><q-btn
                flat
                round
                dense
                icon="more_vert"
                color="grey-5"
                @click.stop
                ><q-menu
                  ><q-list
                    ><q-item
                      clickable
                      v-close-popup
                      @click="openDialog(props.row)"
                      ><q-item-section avatar
                        ><q-icon name="edit" /></q-item-section
                      ><q-item-section>Bearbeiten</q-item-section></q-item
                    ><q-item
                      clickable
                      v-close-popup
                      @click="toggleActive(props.row)"
                      ><q-item-section avatar
                        ><q-icon
                          :name="
                            props.row.is_active
                              ? 'visibility_off'
                              : 'visibility'
                          " /></q-item-section
                      ><q-item-section>{{
                        props.row.is_active ? "Deaktivieren" : "Aktivieren"
                      }}</q-item-section></q-item
                    ><q-separator /><q-item
                      clickable
                      v-close-popup
                      @click="onDelete(props.row)"
                      class="text-negative"
                      ><q-item-section avatar
                        ><q-icon
                          name="delete"
                          color="negative" /></q-item-section
                      ><q-item-section>Löschen</q-item-section></q-item
                    ></q-list
                  ></q-menu
                ></q-btn
              ></q-td
            ></template
          >
        </q-table>
      </q-card-section></q-card
    >
    <div v-if="!loading && materials.length === 0" class="text-center q-pa-xl">
      <q-icon name="inventory_2" size="64px" color="grey-5" />
      <h6 class="q-mt-md" style="color: #64748b">Noch keine Materialien</h6>
      <p style="color: #94a3b8">
        Legen Sie Ihre häufig verwendeten Materialien an.
      </p>
      <q-btn
        color="primary"
        icon="add"
        label="Erstes Material anlegen"
        no-caps
        @click="openDialog()"
      />
    </div>
    <q-dialog v-model="showDialog" persistent>
      <q-card style="width: 550px; max-width: 95vw; border-radius: 16px">
        <q-card-section class="row items-center q-pb-sm"
          ><h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
            {{ editingMaterial ? "Material bearbeiten" : "Neues Material" }}
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
          <q-select
            v-model="form.category"
            filled
            label="Kategorie *"
            :options="existingCategories"
            use-input
            new-value-mode="add-unique"
            @new-value="(val, done) => done(val)"
            :rules="[(val) => !!val || 'Pflichtfeld']"
            ><template v-slot:prepend
              ><q-icon name="category" color="grey-5" /></template
          ></q-select>
          <q-input
            v-model="form.name"
            filled
            label="Bezeichnung *"
            :rules="[(val) => !!val || 'Pflichtfeld']"
          />
          <q-input
            v-model="form.description"
            filled
            label="Beschreibung (optional)"
          />
          <div class="row q-gutter-sm">
            <q-input
              v-model="form.sku"
              filled
              label="Artikelnummer"
              class="col"
            /><q-select
              v-model="form.unit"
              filled
              label="Einheit *"
              :options="unitOptions"
              class="col"
            />
          </div>
          <q-separator class="q-my-xs" />
          <div
            style="
              font-size: 11px;
              font-weight: 600;
              text-transform: uppercase;
              color: #64748b;
            "
          >
            Preise (Netto)
          </div>
          <div class="row q-gutter-sm">
            <q-input
              v-model.number="form.purchase_price"
              filled
              label="Einkaufspreis"
              type="number"
              suffix="€"
              class="col"
            /><q-input
              v-model.number="form.selling_price"
              filled
              label="Verkaufspreis *"
              type="number"
              suffix="€"
              class="col"
              :rules="[(val) => val > 0 || 'Pflichtfeld']"
            /><q-input
              v-model.number="form.markup_percent"
              filled
              label="Aufschlag"
              type="number"
              suffix="%"
              class="col-3"
            />
          </div>
          <div
            v-if="form.purchase_price && form.selling_price"
            class="q-pa-sm"
            style="border-radius: 8px; background: #f8fafc"
          >
            <span style="font-size: 12px; color: #64748b">Marge: </span
            ><span
              :class="
                form.selling_price > form.purchase_price
                  ? 'text-positive'
                  : 'text-negative'
              "
              style="font-weight: 600"
              >{{
                (
                  ((form.selling_price - form.purchase_price) /
                    form.selling_price) *
                  100
                ).toFixed(1)
              }}%</span
            ><span style="font-size: 12px; color: #64748b"> · Gewinn: </span
            ><span style="font-weight: 600; color: #0f172a"
              >{{ fmtP(form.selling_price - form.purchase_price) }} €</span
            >
          </div>
          <q-separator class="q-my-xs" />
          <div
            style="
              font-size: 11px;
              font-weight: 600;
              text-transform: uppercase;
              color: #64748b;
            "
          >
            Lieferant (optional)
          </div>
          <div class="row q-gutter-sm">
            <q-input
              v-model="form.supplier"
              filled
              label="Lieferant"
              class="col"
            /><q-input
              v-model="form.supplier_sku"
              filled
              label="Lieferanten-Art.Nr."
              class="col"
            />
          </div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md"
          ><q-btn flat label="Abbrechen" color="grey" v-close-popup /><q-btn
            :label="editingMaterial ? 'Speichern' : 'Material anlegen'"
            color="primary"
            no-caps
            icon="save"
            :loading="saving"
            @click="onSave"
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
  name: "MaterialsPage",
  setup() {
    const $q = useQuasar();
    const loading = ref(true),
      saving = ref(false),
      materials = ref([]),
      categories = ref({}),
      search = ref(""),
      selectedCategory = ref(null),
      showDialog = ref(false),
      editingMaterial = ref(null);
    const unitOptions = [
      "Stück",
      "Meter",
      "m²",
      "m³",
      "Liter",
      "kg",
      "pauschal",
      "Set",
    ];
    const emptyForm = {
      category: "",
      name: "",
      description: "",
      sku: "",
      unit: "Stück",
      purchase_price: null,
      selling_price: 0,
      markup_percent: 30,
      supplier: "",
      supplier_sku: "",
    };
    const form = reactive({ ...emptyForm });
    const columns = [
      {
        name: "category",
        label: "Kategorie",
        field: "category",
        align: "left",
        sortable: true,
      },
      {
        name: "name",
        label: "Bezeichnung",
        field: "name",
        align: "left",
        sortable: true,
      },
      { name: "sku", label: "Art.Nr.", field: "sku", align: "left" },
      { name: "unit", label: "Einheit", field: "unit", align: "center" },
      {
        name: "purchase_price",
        label: "EK",
        field: "purchase_price",
        align: "right",
        sortable: true,
      },
      {
        name: "selling_price",
        label: "VK",
        field: "selling_price",
        align: "right",
        sortable: true,
      },
      { name: "margin", label: "Marge", field: "margin", align: "right" },
      {
        name: "is_active",
        label: "Status",
        field: "is_active",
        align: "center",
      },
      { name: "actions", label: "", field: "actions", align: "right" },
    ];
    const existingCategories = computed(() => Object.keys(categories.value));
    const categoryOptions = computed(() => [
      { label: "Alle Kategorien", value: null },
      ...Object.keys(categories.value).map((c) => ({
        label: `${c} (${categories.value[c]})`,
        value: c,
      })),
    ]);
    const filteredMaterials = computed(() => {
      let r = materials.value;
      if (selectedCategory.value)
        r = r.filter((m) => m.category === selectedCategory.value);
      if (search.value) {
        const s = search.value.toLowerCase();
        r = r.filter(
          (m) =>
            m.name.toLowerCase().includes(s) ||
            m.category.toLowerCase().includes(s) ||
            (m.sku || "").toLowerCase().includes(s) ||
            (m.supplier || "").toLowerCase().includes(s),
        );
      }
      return r;
    });
    const fmtP = (v) =>
      Number(v || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    const cMrg = (m) => {
      if (!m.purchase_price || !m.selling_price) return 0;
      return (
        ((m.selling_price - m.purchase_price) / m.selling_price) *
        100
      ).toFixed(1);
    };
    const mrgCls = (m) => {
      const mg = cMrg(m);
      return mg >= 20
        ? "text-positive"
        : mg >= 10
          ? "text-warning"
          : "text-negative";
    };
    const loadMaterials = async () => {
      loading.value = true;
      try {
        const r = await api.get("/materials");
        materials.value = r.data.materials || [];
        categories.value = r.data.categories || {};
      } catch (e) {
        console.error(e);
      } finally {
        loading.value = false;
      }
    };
    onMounted(loadMaterials);
    const openDialog = (mat = null) => {
      editingMaterial.value = mat;
      if (mat) {
        Object.keys(emptyForm).forEach((k) => {
          form[k] =
            mat[k] !== null && mat[k] !== undefined ? mat[k] : emptyForm[k];
        });
      } else {
        Object.assign(form, { ...emptyForm });
      }
      showDialog.value = true;
    };
    const onSave = async () => {
      if (!form.name || !form.category || !form.selling_price) {
        $q.notify({
          type: "warning",
          message: "Bitte füllen Sie alle Pflichtfelder aus",
        });
        return;
      }
      saving.value = true;
      try {
        if (editingMaterial.value) {
          await api.put(`/materials/${editingMaterial.value.id}`, form);
          $q.notify({ type: "positive", message: "Material aktualisiert" });
        } else {
          await api.post("/materials", form);
          $q.notify({ type: "positive", message: "Material angelegt" });
        }
        showDialog.value = false;
        await loadMaterials();
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Speichern",
        });
      } finally {
        saving.value = false;
      }
    };
    const toggleActive = async (mat) => {
      try {
        await api.put(`/materials/${mat.id}`, { is_active: !mat.is_active });
        await loadMaterials();
        $q.notify({
          type: "positive",
          message: mat.is_active
            ? "Material deaktiviert"
            : "Material aktiviert",
        });
      } catch (e) {
        console.error(e);
      }
    };
    const onDelete = (mat) => {
      $q.dialog({
        title: "Material löschen?",
        message: `"${mat.name}" wirklich löschen?`,
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          await api.delete(`/materials/${mat.id}`);
          await loadMaterials();
          $q.notify({ type: "positive", message: "Material gelöscht" });
        } catch (e) {
          $q.notify({ type: "negative", message: "Fehler beim Löschen" });
        }
      });
    };
    return {
      loading,
      saving,
      materials,
      categories,
      search,
      selectedCategory,
      showDialog,
      editingMaterial,
      form,
      columns,
      unitOptions,
      existingCategories,
      categoryOptions,
      filteredMaterials,
      fmtP,
      cMrg,
      mrgCls,
      openDialog,
      onSave,
      toggleActive,
      onDelete,
    };
  },
};
</script>
