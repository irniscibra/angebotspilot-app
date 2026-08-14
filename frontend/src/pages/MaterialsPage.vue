<template>
  <q-page class="q-pa-lg">
    <!-- Header -->
    <div class="row items-center q-mb-lg" :class="$q.screen.lt.sm ? 'column items-stretch q-gutter-sm' : ''">
      <div class="col">
        <h5 class="q-my-none" style="font-weight: 700;">Materialkatalog</h5>
        <p class="text-grey-6 q-mb-none q-mt-xs">{{ materials.length }} Materialien in {{ Object.keys(categories).length }} Kategorien</p>
      </div>
      <div class="row q-gutter-sm" :class="$q.screen.lt.sm ? 'full-width column' : ''">
        <q-btn
          v-if="materials.length > 0"
          flat
          color="negative"
          icon="delete_sweep"
          label="Katalog leeren"
          no-caps
          @click="openEmptyCatalogDialog"
          :class="$q.screen.lt.sm ? 'full-width' : ''"
        />
        <q-btn
          color="primary"
          icon="add"
          label="Material hinzufügen"
          no-caps
          @click="openDialog()"
          :class="$q.screen.lt.sm ? 'full-width' : ''"
          style="border-radius: 10px; font-weight: 600;"
        />
      </div>
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

    <q-banner
      v-if="selected.length > 0"
      rounded
      class="q-mb-md"
      style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;"
    >
      <div class="row items-center">
        <span style="font-weight: 600; color: #1e40af;">{{ selected.length }} ausgewählt</span>
        <q-space />
        <q-btn flat dense color="grey-7" label="Auswahl aufheben" no-caps @click="selected = []" class="q-mr-sm" />
        <q-btn
          color="negative"
          icon="delete"
          :label="`${selected.length} löschen`"
          no-caps
          @click="onBulkDelete"
          style="border-radius: 8px; font-weight: 600;"
        />
      </div>
    </q-banner>

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
          selection="multiple"
          v-model:selected="selected"
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
            hint="Vorhandene wählen oder neue eingeben"
            :options="filteredCategoryOptions"
            use-input
            input-debounce="0"
            emit-value
            map-options
            @filter="filterCategoryFn"
            :rules="[(val) => !!val || 'Pflichtfeld']"
            ><template v-slot:prepend
              ><q-icon name="category" color="grey-5" /></template
            ><template v-slot:no-option
              ><q-item
                ><q-item-section class="text-grey">
                  Tippen zum Anlegen
                </q-item-section></q-item
              ></template
            ></q-select
          >
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

    <q-dialog v-model="showEmptyCatalogDialog" persistent>
      <q-card style="width: 460px; max-width: 95vw; border-radius: 16px">
        <q-card-section class="row items-center q-pb-sm">
          <q-icon name="warning" color="negative" size="28px" class="q-mr-sm" />
          <h6 class="q-my-none" style="font-weight: 700; color: #0f172a;">Katalog leeren</h6>
        </q-card-section>
        <q-card-section>
          <p style="color: #475569; font-size: 14px; line-height: 1.6;">
            Du bist dabei, <strong>{{ emptyDialogCount }} Material(ien)</strong>
            <span v-if="selectedCategory"> aus der Kategorie "{{ selectedCategory }}"</span>
            unwiderruflich zu löschen. Diese Aktion kann nicht rückgängig gemacht werden.
          </p>
          <p style="color: #475569; font-size: 13px;">
            Zum Bestätigen tippe <strong>LÖSCHEN</strong> in das Feld unten:
          </p>
          <q-input v-model="emptyDialogConfirmText" filled dense placeholder="LÖSCHEN" autofocus />
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Abbrechen" color="grey" v-close-popup @click="emptyDialogConfirmText = ''" />
          <q-btn
            label="Endgültig löschen"
            color="negative"
            no-caps
            icon="delete_forever"
            :disable="emptyDialogConfirmText !== 'LÖSCHEN'"
            :loading="emptyDialogDeleting"
            @click="onEmptyCatalog"
          />
        </q-card-actions>
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
      editingMaterial = ref(null),
      selected = ref([]),
      showEmptyCatalogDialog = ref(false),
      emptyDialogConfirmText = ref(""),
      emptyDialogDeleting = ref(false);

    const filteredCategoryOptions = ref([]);

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
    const emptyDialogCount = computed(() =>
      selectedCategory.value
        ? categories.value[selectedCategory.value] || 0
        : materials.value.length,
    );

    const resetCategoryOptions = () => {
      filteredCategoryOptions.value = existingCategories.value.map((c) => ({
        label: c,
        value: c,
      }));
    };

    const filterCategoryFn = (val, update) => {
      update(() => {
        const needle = val.toLowerCase();
        let opts = existingCategories.value
          .filter((c) => c.toLowerCase().includes(needle))
          .map((c) => ({ label: c, value: c }));

        const exactMatch = existingCategories.value.some(
          (c) => c.toLowerCase() === needle,
        );
        if (val && !exactMatch) {
          opts.push({ label: `"${val}" neu anlegen`, value: val });
        }
        filteredCategoryOptions.value = opts;
      });
    };

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
      resetCategoryOptions();
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

    const onBulkDelete = () => {
      const ids = selected.value.map((m) => m.id);
      if (ids.length === 0) return;

      $q.dialog({
        title: "Materialien löschen?",
        message: `${ids.length} Material(ien) wirklich löschen? Das kann nicht rückgängig gemacht werden.`,
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          const res = await api.post("/materials/bulk-delete", { ids });
          selected.value = [];
          await loadMaterials();
          $q.notify({ type: "positive", message: res.data.message || "Materialien gelöscht" });
        } catch (e) {
          $q.notify({ type: "negative", message: e.response?.data?.message || "Fehler beim Löschen" });
        }
      });
    };

    const openEmptyCatalogDialog = () => {
      emptyDialogConfirmText.value = "";
      showEmptyCatalogDialog.value = true;
    };

    const onEmptyCatalog = async () => {
      if (emptyDialogConfirmText.value !== "LÖSCHEN") return;
      emptyDialogDeleting.value = true;
      try {
        const payload = { all: true };
        if (selectedCategory.value) payload.category = selectedCategory.value;
        if (search.value) payload.search = search.value;

        const res = await api.post("/materials/bulk-delete", payload);
        selected.value = [];
        showEmptyCatalogDialog.value = false;
        emptyDialogConfirmText.value = "";
        await loadMaterials();
        $q.notify({ type: "positive", message: res.data.message || "Katalog geleert" });
      } catch (e) {
        $q.notify({ type: "negative", message: e.response?.data?.message || "Fehler beim Löschen" });
      } finally {
        emptyDialogDeleting.value = false;
      }
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
      filteredCategoryOptions,
      filterCategoryFn,
      resetCategoryOptions,
      filteredMaterials,
      fmtP,
      cMrg,
      mrgCls,
      openDialog,
      onSave,
      toggleActive,
      onDelete,
      selected,
      showEmptyCatalogDialog,
      emptyDialogConfirmText,
      emptyDialogDeleting,
      emptyDialogCount,
      onBulkDelete,
      openEmptyCatalogDialog,
      onEmptyCatalog,
    };
  },
};
</script>