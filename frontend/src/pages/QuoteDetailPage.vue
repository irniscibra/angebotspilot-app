<template>
  <q-page class="q-pa-sm q-pa-md-lg" style="background: #f6f9fc">
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="50px" />
    </div>
    <div v-else-if="quote">
      <!-- HEADER – Mobile optimiert -->
      <div class="q-mb-md">
        <div class="row items-center no-wrap q-mb-xs">
          <q-btn
            flat
            round
            icon="arrow_back"
            color="grey-6"
            class="q-mr-xs"
            @click="$router.push('/quotes')"
          />
          <div class="row items-center q-gutter-xs">
            <q-badge
              :color="statusColor(quote.status)"
              :label="statusLabel(quote.status)"
            />
            <span style="font-size: 12px; color: #94a3b8">{{
              quote.quote_number
            }}</span>
          </div>
          <q-space />
          <!-- Buttons nur Icons auf Mobile, mit Label auf Desktop -->
          <div class="row items-center q-gutter-xs">
            <q-btn
              color="green"
              icon="send"
              :label="$q.screen.gt.sm ? 'Versenden' : ''"
              no-caps
              dense
              @click="showSendDialog = true"
            />
            <q-btn
              color="primary"
              icon="picture_as_pdf"
              :label="$q.screen.gt.sm ? 'PDF' : ''"
              no-caps
              dense
              @click="onExportPdf"
            />
            <q-btn round flat icon="more_vert" color="grey-7">
              <q-menu auto-close style="min-width: 220px; border-radius: 12px">
                <q-list>
                  <q-item clickable @click="onDuplicate">
                    <q-item-section avatar
                      ><q-icon name="content_copy" color="grey-7"
                    /></q-item-section>
                    <q-item-section>Duplizieren</q-item-section>
                  </q-item>
                  <q-item clickable @click="showSaveAsTemplateDialog = true">
                    <q-item-section avatar
                      ><q-icon name="content_paste" color="teal"
                    /></q-item-section>
                    <q-item-section>Als Vorlage speichern</q-item-section>
                  </q-item>
                  <q-item
                    clickable
                    @click="$router.push(`/protokolle/neu/${quote.id}`)"
                  >
                    <q-item-section avatar
                      ><q-icon name="assignment_turned_in" color="teal"
                    /></q-item-section>
                    <q-item-section>Abnahmeprotokoll</q-item-section>
                  </q-item>
                  <q-separator />
                  <q-item clickable @click="onDelete">
                    <q-item-section avatar
                      ><q-icon name="delete" color="negative"
                    /></q-item-section>
                    <q-item-section class="text-negative"
                      >Angebot löschen</q-item-section
                    >
                  </q-item>
                  <q-item clickable @click="$router.push('/invoices')">
                    <q-item-section avatar
                      ><q-icon name="receipt_long" color="blue"
                    /></q-item-section>
                    <q-item-section>Rechnung erstellen</q-item-section>
                  </q-item>
                  <q-item clickable @click="showPriceCheck = true">
  <q-item-section avatar>
    <q-icon name="analytics" color="primary" />
  </q-item-section>
  <q-item-section>KI-Preisanalyse</q-item-section>
</q-item>
                </q-list>
              </q-menu>
            </q-btn>
          </div>
        </div>
        <!-- Titel in eigener Zeile -->
        <div class="q-pl-xs">
          <h5
            class="q-my-none"
            style="
              font-weight: 700;
              color: #0f172a;
              font-size: clamp(16px, 4vw, 22px);
            "
          >
            <span
              v-if="!editingTitle"
              @dblclick="startEditTitle"
              class="cursor-pointer"
            >
              {{ quote.project_title }}
              <q-icon name="edit" size="14px" color="grey-5" class="q-ml-xs" />
            </span>
            <q-input
              v-else
              v-model="editTitle"
              dense
              filled
              @keyup.enter="saveTitle"
              @blur="saveTitle"
              autofocus
              style="max-width: 500px"
            />
          </h5>
        </div>
      </div>

      <!-- Info Cards -->
      <div class="row q-col-gutter-md q-mb-lg">
        <div class="col-12 col-md-4">
          <q-card
            flat
            class="full-height"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              background: #ffffff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 11px;
                  font-weight: 600;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                  color: #94a3b8;
                "
                class="q-mb-sm"
              >
                Projekt-Details
              </div>
              <div class="q-gutter-sm">
                <div>
                  <span style="font-size: 12px; color: #94a3b8"
                    >Erstellt am</span
                  >
                  <div style="font-size: 13px; color: #0f172a">
                    {{ formatDate(quote.created_at) }}
                  </div>
                </div>
                <div v-if="quote.valid_until">
                  <span style="font-size: 12px; color: #94a3b8"
                    >Gültig bis</span
                  >
                  <div style="font-size: 13px; color: #0f172a">
                    {{ formatDate(quote.valid_until) }}
                  </div>
                </div>
                <div v-if="quote.project_address">
                  <span style="font-size: 12px; color: #94a3b8">Adresse</span>
                  <div style="font-size: 13px; color: #0f172a">
                    {{ quote.project_address }}
                  </div>
                </div>
                <div v-if="quote.sent_at">
                  <span style="font-size: 12px; color: #94a3b8"
                    >Gesendet am</span
                  >
                  <div style="font-size: 13px; color: #0f172a">
                    {{ formatDate(quote.sent_at) }}
                  </div>
                </div>
                <div v-if="quote.accepted_at">
                  <span style="font-size: 12px; color: #94a3b8"
                    >Angenommen am</span
                  >
                  <div style="font-size: 13px; color: #16a34a">
                    {{ formatDate(quote.accepted_at) }}
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card
            flat
            class="full-height"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              background: #ffffff;
            "
          >
            <q-card-section>
              <div class="row items-center justify-between q-mb-sm">
                <div
                  style="
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: #94a3b8;
                  "
                >
                  Kunde
                </div>
                <q-btn
                  flat
                  dense
                  size="sm"
                  icon="edit"
                  color="grey-5"
                  @click="showCustomerDialog = true"
                />
              </div>
              <div v-if="quote.customer">
                <div style="font-size: 14px; font-weight: 600; color: #0f172a">
                  {{
                    quote.customer.type === "business"
                      ? quote.customer.company_name
                      : quote.customer.first_name +
                        " " +
                        quote.customer.last_name
                  }}
                </div>
                <div style="font-size: 12px; margin-top: 4px; color: #64748b">
                  <div v-if="quote.customer.address_street">
                    {{ quote.customer.address_street }}
                  </div>
                  <div v-if="quote.customer.address_zip">
                    {{ quote.customer.address_zip }}
                    {{ quote.customer.address_city }}
                  </div>
                  <div v-if="quote.customer.email" class="q-mt-xs">
                    {{ quote.customer.email }}
                  </div>
                  <div v-if="quote.customer.phone">
                    {{ quote.customer.phone }}
                  </div>
                </div>
              </div>
              <div v-else style="font-size: 13px; color: #94a3b8">
                Kein Kunde zugewiesen<br /><q-btn
                  flat
                  dense
                  color="primary"
                  label="Kunde zuweisen"
                  no-caps
                  class="q-mt-xs"
                  @click="showCustomerDialog = true"
                />
              </div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card
            flat
            class="full-height"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              background: #ffffff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 11px;
                  font-weight: 600;
                  text-transform: uppercase;
                  letter-spacing: 0.05em;
                  color: #94a3b8;
                "
                class="q-mb-sm"
              >
                Projektbeschreibung
              </div>
              <div style="font-size: 12px; line-height: 1.6; color: #475569">
                {{ quote.project_description || "Keine Beschreibung" }}
              </div>
              <div v-if="quote.ai_model" class="q-mt-sm">
                <q-badge
                  outline
                  color="blue"
                  :label="'KI: ' + quote.ai_model"
                /><q-badge
                  outline
                  color="grey"
                  :label="quote.ai_tokens_used + ' Tokens'"
                  class="q-ml-xs"
                />
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Positionen + Kalkulation -->
      <div class="row q-col-gutter-lg">
        <div class="col-12 col-md-8">
          <q-card
            flat
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #ffffff;
            "
          >
            <q-card-section>
              <div class="row items-center justify-between q-mb-md">
                <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
                  Positionen
                </h6>
                <div class="row q-gutter-xs">
                  <q-btn
                    flat
                    color="teal"
                    icon="content_paste"
                    :label="$q.screen.gt.sm ? 'Vorlage einfügen' : ''"
                    no-caps
                    dense
                    @click="openTemplateDialog"
                  />
                  <q-btn
                    flat
                    color="primary"
                    icon="add"
                    :label="$q.screen.gt.sm ? 'Position hinzufügen' : ''"
                    no-caps
                    dense
                    @click="openAddDialog"
                  />
                </div>
              </div>

              <div
                v-for="(items, groupName) in groupedItems"
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
                    letter-spacing: 0.04em;
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
                  <q-card-section class="q-py-sm q-px-sm">
                    <!-- Mobile Layout: gestapelt -->
                    <div v-if="$q.screen.lt.md">
                      <div class="row items-start justify-between q-mb-xs">
                        <div class="col">
                          <div class="row items-center q-gutter-xs q-mb-xs">
                            <span
                              style="
                                font-size: 13px;
                                font-weight: 600;
                                color: #0f172a;
                              "
                              >{{ item.title }}</span
                            >
                            <q-badge
                              :color="
                                item.type === 'material' ? 'blue' : 'orange'
                              "
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
                            style="font-size: 11px; color: #94a3b8"
                          >
                            {{ item.description }}
                          </div>
                        </div>
                        <q-btn
                          flat
                          round
                          dense
                          icon="close"
                          color="negative"
                          size="sm"
                          @click="onDeleteItem(item)"
                        />
                      </div>
                      <div class="row items-center q-gutter-sm">
                        <div style="width: 70px">
                          <q-input
                            :model-value="item.quantity"
                            @change="
                              (val) => onUpdateItem(item, 'quantity', val)
                            "
                            dense
                            filled
                            type="number"
                            step="0.5"
                            style="font-size: 12px"
                          />
                        </div>
                        <div
                          style="
                            font-size: 12px;
                            color: #64748b;
                            min-width: 30px;
                          "
                        >
                          {{ item.unit }}
                        </div>
                        <div style="width: 85px">
                          <q-input
                            :model-value="item.unit_price"
                            @change="
                              (val) => onUpdateItem(item, 'unit_price', val)
                            "
                            dense
                            filled
                            type="number"
                            step="0.50"
                            suffix="€"
                            style="font-size: 12px"
                          />
                        </div>
                        <q-space />
                        <div
                          style="
                            font-weight: 700;
                            font-size: 14px;
                            color: #1d4ed8;
                          "
                        >
                          {{ formatPrice(item.quantity * item.unit_price) }} €
                        </div>
                      </div>
                    </div>

                    <!-- Desktop Layout: eine Zeile -->
                    <div v-else class="row items-center q-gutter-sm">
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
                            :color="
                              item.type === 'material' ? 'blue' : 'orange'
                            "
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
                          style="
                            font-size: 11px;
                            margin-top: 2px;
                            color: #94a3b8;
                          "
                        >
                          {{ item.description }}
                        </div>
                      </div>
                      <div style="width: 75px">
                        <q-input
                          :model-value="item.quantity"
                          @change="(val) => onUpdateItem(item, 'quantity', val)"
                          dense
                          filled
                          type="number"
                          step="0.5"
                          style="font-size: 13px"
                        />
                      </div>
                      <div
                        class="text-center"
                        style="width: 55px; font-size: 12px; color: #64748b"
                      >
                        {{ item.unit }}
                      </div>
                      <div style="width: 95px">
                        <q-input
                          :model-value="item.unit_price"
                          @change="
                            (val) => onUpdateItem(item, 'unit_price', val)
                          "
                          dense
                          filled
                          type="number"
                          step="0.50"
                          suffix="€"
                          style="font-size: 13px"
                        />
                      </div>
                      <div
                        class="text-right"
                        style="
                          width: 95px;
                          font-weight: 600;
                          font-size: 13px;
                          color: #0f172a;
                        "
                      >
                        {{ formatPrice(item.quantity * item.unit_price) }} €
                      </div>
                      <q-btn
                        flat
                        round
                        dense
                        icon="close"
                        color="negative"
                        size="sm"
                        @click="onDeleteItem(item)"
                        ><q-tooltip>Position löschen</q-tooltip></q-btn
                      >
                    </div>
                  </q-card-section>
                </q-card>
              </div>

              <div
                v-if="!quote.items || quote.items.length === 0"
                class="text-center q-pa-lg"
                style="color: #94a3b8"
              >
                Keine Positionen vorhanden
              </div>
            </q-card-section>
          </q-card>

          <q-card
            flat
            class="q-mt-md"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 14px;
              background: #ffffff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 11px;
                  font-weight: 600;
                  text-transform: uppercase;
                  color: #94a3b8;
                "
                class="q-mb-sm"
              >
                Interne Notizen
              </div>
              <q-input
                v-model="internalNotes"
                filled
                type="textarea"
                rows="3"
                placeholder="Notizen die nur Sie sehen (nicht im Angebot)"
                @blur="saveNotes"
              />
            </q-card-section>
          </q-card>
        </div>

        <!-- Kalkulation Sidebar -->
        <div class="col-12 col-md-4">
          <q-card
            flat
            style="
              border: 1px solid #dbeafe;
              border-radius: 16px;
              background: #ffffff;
              position: sticky;
              top: 20px;
            "
          >
            <q-card-section class="q-pa-lg">
              <h6
                class="q-my-none q-mb-md"
                style="
                  font-weight: 700;
                  text-transform: uppercase;
                  font-size: 12px;
                  letter-spacing: 0.05em;
                  color: #64748b;
                "
              >
                Kalkulation
              </h6>
              <div class="q-gutter-sm">
                <div class="row justify-between">
                  <span style="color: #64748b">Material</span
                  ><span style="font-weight: 600; color: #0f172a"
                    >{{ formatPrice(quote.subtotal_materials) }} €</span
                  >
                </div>
                <div class="row justify-between">
                  <span style="color: #64748b">Arbeitsleistung</span
                  ><span style="font-weight: 600; color: #0f172a"
                    >{{ formatPrice(quote.subtotal_labor) }} €</span
                  >
                </div>
                <q-separator class="q-my-sm" />
                <div class="row items-center justify-between">
                  <span style="color: #64748b">Rabatt</span>
                  <div style="width: 80px">
                    <q-input
                      v-model.number="discountPercent"
                      dense
                      filled
                      type="number"
                      min="0"
                      max="100"
                      suffix="%"
                      @change="saveDiscount"
                      style="font-size: 12px"
                    />
                  </div>
                </div>
                <div
                  v-if="quote.discount_amount > 0"
                  class="row justify-between"
                >
                  <span></span
                  ><span style="font-weight: 600; color: #ef4444"
                    >-{{ formatPrice(quote.discount_amount) }} €</span
                  >
                </div>
                <q-separator class="q-my-sm" />
                <div class="row justify-between">
                  <span style="color: #64748b">Netto</span
                  ><span style="font-weight: 600; color: #0f172a"
                    >{{ formatPrice(quote.subtotal_net) }} €</span
                  >
                </div>
                <div class="row justify-between">
                  <span style="color: #64748b"
                    >MwSt ({{ quote.vat_rate }}%)</span
                  ><span style="color: #94a3b8"
                    >{{ formatPrice(quote.vat_amount) }} €</span
                  >
                </div>
                <q-separator class="q-my-sm" />
                <div class="row justify-between items-center">
                  <span
                    style="font-weight: 700; font-size: 16px; color: #0f172a"
                    >Gesamt</span
                  >
                  <span
                    style="font-weight: 800; font-size: 20px; color: #1d4ed8"
                    >{{ formatPrice(quote.total_gross) }} €</span
                  >
                </div>
              </div>
            </q-card-section>
          </q-card>
          <q-card
            flat
            class="q-mt-md"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 12px;
              background: #ffffff;
            "
          >
            <q-card-section>
              <div
                style="
                  font-size: 11px;
                  font-weight: 600;
                  text-transform: uppercase;
                  color: #94a3b8;
                "
                class="q-mb-sm"
              >
                Status
              </div>
              <q-select
                v-model="quote.status"
                :options="statusOptions"
                emit-value
                map-options
                filled
                dense
                @update:model-value="onStatusChange"
              />
            </q-card-section>
          </q-card>
          <q-card
            flat
            class="q-mt-md"
            style="
              border: 1px solid #fecaca;
              border-radius: 12px;
              background: #fff;
            "
          >
            <q-card-section
              ><q-btn
                flat
                color="negative"
                icon="delete"
                label="Angebot löschen"
                no-caps
                class="full-width"
                @click="onDelete"
            /></q-card-section>
          </q-card>
        </div>
      </div>
    </div>

    <!-- Dialoge – UNVERÄNDERT -->
    <q-dialog v-model="showAddDialog">
      <q-card style="width: 95vw; max-width: 560px; border-radius: 16px">
        <q-card-section
          ><h6 class="q-my-none" style="color: #0f172a; font-weight: 600">
            Position hinzufügen
          </h6></q-card-section
        >
        <q-card-section class="q-pt-none q-gutter-sm">
          <div style="position: relative">
            <q-input
              v-model="materialSearch"
              filled
              dense
              label="Material aus Katalog suchen..."
              @update:model-value="onMaterialSearch"
              clearable
              @clear="clearMaterialSearch"
            >
              <template v-slot:prepend
                ><q-icon name="search" color="grey-5"
              /></template>
              <template v-slot:after
                ><q-badge
                  v-if="catalogResults.length"
                  color="primary"
                  :label="catalogResults.length"
              /></template>
            </q-input>
            <q-card
              v-if="catalogResults.length > 0 && materialSearch"
              flat
              bordered
              style="
                position: absolute;
                z-index: 100;
                width: 100%;
                max-height: 260px;
                overflow-y: auto;
                border-radius: 10px;
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
              "
            >
              <q-list dense>
                <q-item
                  v-for="mat in catalogResults"
                  :key="mat.id"
                  clickable
                  @click="selectCatalogMaterial(mat)"
                  class="q-py-sm"
                >
                  <q-item-section>
                    <q-item-label
                      style="font-weight: 500; font-size: 13px; color: #0f172a"
                      >{{ mat.name }}</q-item-label
                    >
                    <q-item-label caption>
                      <span
                        v-if="mat.datanorm_article_number"
                        style="color: #1d4ed8"
                        >Art.{{ mat.datanorm_article_number }}</span
                      >
                      <span v-else-if="mat.sku" style="color: #64748b"
                        >Art.{{ mat.sku }}</span
                      >
                      <span style="color: #94a3b8">
                        · {{ mat.category }} · {{ mat.unit }}</span
                      >
                      <span v-if="mat.supplier" style="color: #94a3b8">
                        · {{ mat.supplier }}</span
                      >
                    </q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <div
                      style="font-weight: 700; font-size: 14px; color: #1d4ed8"
                    >
                      {{ formatPrice(mat.selling_price) }} €
                    </div>
                    <q-badge
                      v-if="mat.source === 'datanorm'"
                      color="green-7"
                      label="Datanorm"
                      dense
                      style="font-size: 9px"
                    />
                  </q-item-section>
                </q-item>
              </q-list>
            </q-card>
          </div>
          <q-banner
            v-if="selectedMaterial"
            rounded
            class="q-mt-xs"
            style="
              background: #f0fdf4;
              border: 1px solid #bbf7d0;
              border-radius: 8px;
            "
          >
            <template v-slot:avatar
              ><q-icon name="check_circle" color="positive"
            /></template>
            <div style="font-weight: 600; font-size: 13px; color: #166534">
              {{ selectedMaterial.name }}
            </div>
            <div style="font-size: 12px; color: #4ade80">
              {{ selectedMaterial.category }} ·
              {{ formatPrice(selectedMaterial.selling_price) }} €/{{
                selectedMaterial.unit
              }}<span v-if="selectedMaterial.supplier">
                · {{ selectedMaterial.supplier }}</span
              >
            </div>
            <template v-slot:action
              ><q-btn
                flat
                dense
                icon="close"
                color="grey"
                size="sm"
                @click="clearMaterialSearch"
            /></template>
          </q-banner>
          <q-separator class="q-my-xs" />
          <q-select
            v-model="newItem.type"
            filled
            dense
            :options="[
              { label: 'Material', value: 'material' },
              { label: 'Arbeit', value: 'labor' },
            ]"
            emit-value
            map-options
            label="Typ"
          />
          <q-input v-model="newItem.group_name" filled dense label="Gruppe" />
          <q-input v-model="newItem.title" filled dense label="Bezeichnung" />
          <q-input
            v-model="newItem.description"
            filled
            dense
            label="Beschreibung (optional)"
          />
          <div class="row q-gutter-sm">
            <q-input
              v-model.number="newItem.quantity"
              filled
              dense
              label="Menge"
              type="number"
              class="col"
            />
            <q-input
              v-model="newItem.unit"
              filled
              dense
              label="Einheit"
              class="col"
            />
            <q-input
              v-model.number="newItem.unit_price"
              filled
              dense
              label="Preis €"
              type="number"
              class="col"
            />
          </div>
          <div
            class="text-right"
            style="font-size: 14px; font-weight: 700; color: #1d4ed8"
          >
            Gesamt:
            {{
              formatPrice((newItem.quantity || 0) * (newItem.unit_price || 0))
            }}
            €
          </div>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Abbrechen" color="grey" v-close-popup />
          <q-btn
            label="Hinzufügen"
            color="primary"
            no-caps
            icon="add"
            @click="onAddItem"
            v-close-popup
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showTemplateDialog">
      <q-card style="width: 95vw; max-width: 420px; border-radius: 14px">
        <q-card-section class="row items-center q-pb-sm">
          <q-icon
            name="content_paste"
            color="teal"
            size="24px"
            class="q-mr-sm"
          />
          <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
            Vorlage einfügen
          </h6>
          <q-space /><q-btn
            flat
            round
            dense
            icon="close"
            color="grey-5"
            v-close-popup
          />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-input
            v-model="templateSearch"
            filled
            dense
            placeholder="Vorlage suchen..."
            class="q-mb-sm"
            ><template v-slot:prepend
              ><q-icon name="search" color="grey-5" /></template
          ></q-input>
          <div v-if="templatesLoading" class="flex flex-center q-pa-md">
            <q-spinner color="primary" size="24px" />
          </div>
          <div v-else style="max-height: 400px; overflow-y: auto">
            <q-item
              v-for="tpl in filteredTemplates"
              :key="tpl.id"
              clickable
              @click="onApplyTemplate(tpl)"
              style="
                border-radius: 8px;
                margin-bottom: 4px;
                border: 1px solid #f1f5f9;
              "
            >
              <q-item-section avatar
                ><q-avatar
                  size="36px"
                  color="teal-1"
                  text-color="teal"
                  icon="content_paste"
              /></q-item-section>
              <q-item-section>
                <q-item-label style="font-weight: 600; color: #0f172a">{{
                  tpl.name
                }}</q-item-label>
                <q-item-label caption
                  ><q-badge
                    v-if="tpl.category"
                    :label="tpl.category"
                    dense
                    class="q-mr-xs"
                    color="grey-4"
                    text-color="grey-8"
                  />{{ tpl.items_count }} Positionen · {{ tpl.usage_count }}×
                  verwendet</q-item-label
                >
              </q-item-section>
              <q-item-section side
                ><q-icon name="add_circle" color="teal" size="20px"
              /></q-item-section>
            </q-item>
            <div
              v-if="filteredTemplates.length === 0"
              class="text-center q-pa-md"
              style="color: #94a3b8"
            >
              {{
                templateSearch
                  ? "Keine Vorlagen gefunden"
                  : "Noch keine Vorlagen erstellt"
              }}
            </div>
          </div>
        </q-card-section>
        <q-card-actions
          align="right"
          class="q-pa-md"
          style="border-top: 1px solid #f1f5f9"
        >
          <q-btn
            flat
            label="Vorlagen verwalten"
            color="teal"
            no-caps
            icon="settings"
            @click="
              showTemplateDialog = false;
              $router.push('/vorlagen');
            "
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showSaveAsTemplateDialog">
      <q-card style="width: 95vw; max-width: 420px; border-radius: 14px">
        <q-card-section>
          <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
            Als Vorlage speichern
          </h6>
          <p style="font-size: 12px; color: #64748b">
            Alle {{ quote?.items?.length || 0 }} Positionen werden als Vorlage
            gespeichert.
          </p>
        </q-card-section>
        <q-card-section class="q-pt-none q-gutter-sm">
          <q-input
            v-model="saveTemplateForm.name"
            filled
            dense
            label="Name der Vorlage *"
            placeholder="z.B. Gäste-WC komplett"
          />
          <q-input
            v-model="saveTemplateForm.category"
            filled
            dense
            label="Kategorie"
            placeholder="z.B. Sanitär"
          />
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Abbrechen" color="grey" v-close-popup />
          <q-btn
            label="Speichern"
            color="teal"
            no-caps
            icon="save"
            @click="onSaveAsTemplate"
            v-close-popup
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="showCustomerDialog">
      <q-card style="width: 95vw; max-width: 500px; border-radius: 16px">
        <q-card-section class="row items-center q-pb-sm">
          <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
            Kunde zuweisen
          </h6>
          <q-space /><q-btn
            flat
            round
            dense
            icon="close"
            color="grey-5"
            v-close-popup
          />
        </q-card-section>
        <q-card-section class="q-pt-none">
          <q-input
            v-model="customerSearch"
            filled
            dense
            placeholder="Kunde suchen..."
            class="q-mb-sm"
            ><template v-slot:prepend
              ><q-icon name="search" color="grey-5" /></template
          ></q-input>
          <div style="max-height: 350px; overflow-y: auto">
            <q-item
              v-if="quote.customer_id"
              clickable
              @click="onAssignCustomer(null)"
              style="
                border-radius: 8px;
                border: 1px solid #fecaca;
                margin-bottom: 4px;
              "
            >
              <q-item-section avatar
                ><q-icon name="person_remove" color="negative"
              /></q-item-section>
              <q-item-section
                ><q-item-label style="color: #ef4444; font-weight: 500"
                  >Kundenzuweisung entfernen</q-item-label
                ></q-item-section
              >
            </q-item>
            <q-item
              v-for="c in filteredDialogCustomers"
              :key="c.id"
              clickable
              @click="onAssignCustomer(c.id)"
              style="border-radius: 8px; margin-bottom: 2px"
              :class="quote.customer_id === c.id ? 'bg-blue-1' : ''"
            >
              <q-item-section avatar
                ><q-avatar
                  size="36px"
                  :color="c.type === 'business' ? 'blue' : 'teal'"
                  text-color="white"
                  :icon="c.type === 'business' ? 'business' : 'person'"
              /></q-item-section>
              <q-item-section>
                <q-item-label style="font-weight: 500; color: #0f172a">{{
                  c.type === "business"
                    ? c.company_name
                    : c.first_name + " " + c.last_name
                }}</q-item-label>
                <q-item-label caption style="color: #64748b">{{
                  c.email || c.phone || c.address_city || ""
                }}</q-item-label>
              </q-item-section>
              <q-item-section side v-if="quote.customer_id === c.id"
                ><q-icon name="check_circle" color="primary"
              /></q-item-section>
            </q-item>
            <div
              v-if="filteredDialogCustomers.length === 0 && !customersLoading"
              class="text-center q-pa-md"
              style="color: #94a3b8"
            >
              {{
                customerSearch
                  ? "Keine Kunden gefunden"
                  : "Noch keine Kunden angelegt"
              }}
            </div>
            <div v-if="customersLoading" class="flex flex-center q-pa-md">
              <q-spinner color="primary" size="24px" />
            </div>
          </div>
        </q-card-section>
        <q-card-actions
          align="right"
          class="q-pa-md"
          style="border-top: 1px solid #f1f5f9"
        >
          <q-btn
            flat
            label="Neuen Kunden anlegen"
            color="primary"
            no-caps
            icon="person_add"
            @click="
              showCustomerDialog = false;
              $router.push('/customers');
            "
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <SendQuoteDialog
      v-model="showSendDialog"
      :quote="quote"
      @sent="
        (q) => {
          quote = q;
        }
      "
    />
    <PriceCheckDialog v-model="showPriceCheck" :quote="quote" />
  </q-page>
</template>

<script>
import { ref, computed, onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useQuoteStore } from "src/stores/quotes";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";
import SendQuoteDialog from "src/components/SendQuoteDialog.vue";
import PriceCheckDialog from "src/components/PriceCheckDialog.vue";
export default {
  name: "QuoteDetailPage",
  components: { SendQuoteDialog, PriceCheckDialog },
  setup() {
    const route = useRoute();
    const router = useRouter();
    const quoteStore = useQuoteStore();
    const $q = useQuasar();
    const showSendDialog = ref(false);
    // State
    const loading = ref(true);
    const quote = ref(null);
    const editingTitle = ref(false);
    const editTitle = ref("");
    const internalNotes = ref("");
    const discountPercent = ref(0);
    const showAddDialog = ref(false);
    const showCustomerDialog = ref(false);
    const customerSearch = ref("");
    const allCustomers = ref([]);
    const customersLoading = ref(false);

    // Materialsuche State
    const materialSearch = ref("");
    const catalogResults = ref([]);
    const selectedMaterial = ref(null);
    let searchTimeout = null;

    // Template State
    const showTemplateDialog = ref(false);
    const templateSearch = ref("");
    const allTemplates = ref([]);
    const templatesLoading = ref(false);
    const showSaveAsTemplateDialog = ref(false);
    const saveTemplateForm = ref({ name: "", category: "" });

    //KI Price Analyse Dialog ref
    const showPriceCheck = ref(false);

    const newItem = ref({
      type: "material",
      group_name: "",
      title: "",
      description: "",
      quantity: 1,
      unit: "Stück",
      unit_price: 0,
      material_id: null,
    });

    const statusOptions = [
      { label: "Entwurf", value: "draft" },
      { label: "Gesendet", value: "sent" },
      { label: "Angenommen", value: "accepted" },
      { label: "Abgelehnt", value: "rejected" },
    ];

    // Computed
    const groupedItems = computed(() => {
      if (!quote.value?.items) return {};
      const g = {};
      quote.value.items.forEach((i) => {
        const gr = i.group_name || "Sonstiges";
        if (!g[gr]) g[gr] = [];
        g[gr].push(i);
      });
      return g;
    });

    const filteredDialogCustomers = computed(() => {
      if (!customerSearch.value) return allCustomers.value;
      const s = customerSearch.value.toLowerCase();
      return allCustomers.value.filter((c) => {
        const name =
          c.type === "business"
            ? c.company_name || ""
            : (c.first_name || "") + " " + (c.last_name || "");
        return (
          name.toLowerCase().includes(s) ||
          (c.email || "").toLowerCase().includes(s) ||
          (c.address_city || "").toLowerCase().includes(s)
        );
      });
    });

    // Materialsuche mit Debounce
    const onMaterialSearch = (val) => {
      if (searchTimeout) clearTimeout(searchTimeout);
      if (!val || val.length < 2) {
        catalogResults.value = [];
        return;
      }
      searchTimeout = setTimeout(async () => {
        try {
          const res = await api.get("/materials/search", {
            params: { q: val },
          });
          catalogResults.value = res.data || [];
        } catch (e) {
          console.error("Material search failed:", e);
          catalogResults.value = [];
        }
      }, 250);
    };

    const selectCatalogMaterial = (mat) => {
      selectedMaterial.value = mat;
      catalogResults.value = [];
      materialSearch.value = "";

      // Formular mit Katalogdaten füllen
      newItem.value.type = "material";
      newItem.value.title = mat.name;
      newItem.value.description = mat.description || "";
      newItem.value.unit = mat.unit;
      newItem.value.unit_price = Number(mat.selling_price);
      newItem.value.material_id = mat.id;

      // Gruppe basierend auf Kategorie vorschlagen
      if (mat.category && !newItem.value.group_name) {
        newItem.value.group_name = mat.category;
      }
    };

    const clearMaterialSearch = () => {
      materialSearch.value = "";
      catalogResults.value = [];
      selectedMaterial.value = null;
      newItem.value.material_id = null;
    };

    const openAddDialog = () => {
      // Erste Gruppenname aus bestehenden Positionen vorschlagen
      const existingGroups = Object.keys(groupedItems.value);
      newItem.value = {
        type: "material",
        group_name:
          existingGroups.length > 0
            ? existingGroups[existingGroups.length - 1]
            : "Sonstiges",
        title: "",
        description: "",
        quantity: 1,
        unit: "Stück",
        unit_price: 0,
        material_id: null,
      };
      materialSearch.value = "";
      catalogResults.value = [];
      selectedMaterial.value = null;
      showAddDialog.value = true;
    };

    // Data loading
    const loadCustomers = async () => {
      customersLoading.value = true;
      try {
        const r = await api.get("/customers");
        allCustomers.value = r.data.data || r.data;
      } catch (e) {
        console.error(e);
      } finally {
        customersLoading.value = false;
      }
    };

    const onAssignCustomer = async (customerId) => {
      try {
        await quoteStore.updateQuote(quote.value.id, {
          customer_id: customerId,
        });
        await loadQuote();
        showCustomerDialog.value = false;
        $q.notify({
          type: "positive",
          message: customerId ? "Kunde zugewiesen" : "Kundenzuweisung entfernt",
        });
      } catch (e) {
        $q.notify({ type: "negative", message: "Fehler beim Zuweisen" });
      }
    };

    const loadQuote = async () => {
      loading.value = true;
      try {
        await quoteStore.fetchQuote(route.params.id);
        quote.value = quoteStore.currentQuote;
        internalNotes.value = quote.value.internal_notes || "";
        discountPercent.value = Number(quote.value.discount_percent) || 0;
      } catch (e) {
        $q.notify({ type: "negative", message: "Angebot nicht gefunden" });
        router.push("/quotes");
      } finally {
        loading.value = false;
      }
    };

    onMounted(loadQuote);
    watch(
      () => quoteStore.currentQuote,
      (val) => {
        if (val) quote.value = val;
      },
      { deep: true },
    );
    watch(showCustomerDialog, (val) => {
      if (val) {
        customerSearch.value = "";
        loadCustomers();
      }
    });

    // Helpers
    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    const formatDate = (val) =>
      val
        ? new Date(val).toLocaleDateString("de-DE", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
          })
        : "-";
    const statusColor = (s) =>
      ({
        draft: "grey",
        sent: "blue",
        viewed: "info",
        accepted: "positive",
        rejected: "negative",
        expired: "grey-7",
      })[s] || "grey";
    const statusLabel = (s) =>
      ({
        draft: "Entwurf",
        sent: "Gesendet",
        viewed: "Gesehen",
        accepted: "Angenommen",
        rejected: "Abgelehnt",
        expired: "Abgelaufen",
      })[s] || s;

    // Actions
    const startEditTitle = () => {
      editTitle.value = quote.value.project_title;
      editingTitle.value = true;
    };
    const saveTitle = async () => {
      editingTitle.value = false;
      if (editTitle.value && editTitle.value !== quote.value.project_title) {
        await quoteStore.updateQuote(quote.value.id, {
          project_title: editTitle.value,
        });
        quote.value.project_title = editTitle.value;
      }
    };
    const saveNotes = async () => {
      await quoteStore.updateQuote(quote.value.id, {
        internal_notes: internalNotes.value,
      });
    };
    const saveDiscount = async () => {
      await quoteStore.updateQuote(quote.value.id, {
        discount_percent: discountPercent.value,
      });
      await loadQuote();
    };

    const onUpdateItem = async (item, field, value) => {
      try {
        await quoteStore.updateItem(quote.value.id, item.id, {
          [field]: Number(value),
        });
        quote.value = quoteStore.currentQuote;
      } catch (e) {
        console.error(e);
      }
    };

    const onDeleteItem = async (item) => {
      $q.dialog({
        title: "Position löschen?",
        message: `"${item.title}" wirklich entfernen?`,
        cancel: true,
      }).onOk(async () => {
        await quoteStore.deleteItem(quote.value.id, item.id);
        quote.value = quoteStore.currentQuote;
      });
    };

    const onAddItem = async () => {
      if (!newItem.value.title) {
        $q.notify({ type: "warning", message: "Bitte Bezeichnung eingeben" });
        return;
      }
      await quoteStore.addItem(quote.value.id, newItem.value);
      quote.value = quoteStore.currentQuote;
      $q.notify({ type: "positive", message: "Position hinzugefügt" });
    };

    // Template functions
    const filteredTemplates = computed(() => {
      if (!templateSearch.value) return allTemplates.value;
      const s = templateSearch.value.toLowerCase();
      return allTemplates.value.filter(
        (t) =>
          t.name.toLowerCase().includes(s) ||
          (t.category || "").toLowerCase().includes(s),
      );
    });

    const openTemplateDialog = async () => {
      showTemplateDialog.value = true;
      templateSearch.value = "";
      templatesLoading.value = true;
      try {
        const res = await api.get("/service-templates");
        allTemplates.value = res.data.templates || [];
      } catch (e) {
        console.error(e);
      } finally {
        templatesLoading.value = false;
      }
    };

    const onApplyTemplate = async (tpl) => {
      try {
        await api.post(`/service-templates/${tpl.id}/apply/${quote.value.id}`);
        await loadQuote();
        showTemplateDialog.value = false;
        $q.notify({
          type: "positive",
          message: `Vorlage "${tpl.name}" eingefügt`,
        });
      } catch (e) {
        $q.notify({ type: "negative", message: "Fehler beim Einfügen" });
      }
    };

    const onSaveAsTemplate = async () => {
      if (!saveTemplateForm.value.name) {
        $q.notify({ type: "warning", message: "Bitte Name eingeben" });
        return;
      }
      try {
        await api.post(
          `/service-templates/from-quote/${quote.value.id}`,
          saveTemplateForm.value,
        );
        $q.notify({ type: "positive", message: "Als Vorlage gespeichert!" });
        saveTemplateForm.value = { name: "", category: "" };
      } catch (e) {
        $q.notify({ type: "negative", message: "Fehler beim Speichern" });
      }
    };

    const onStatusChange = async (s) => {
      await quoteStore.updateQuote(quote.value.id, { status: s });
      $q.notify({ type: "positive", message: "Status aktualisiert" });
    };
    const onSend = async () => {
      $q.dialog({
        title: "Angebot versenden?",
        message: 'Das Angebot wird als "Gesendet" markiert.',
        cancel: true,
      }).onOk(async () => {
        await quoteStore.sendQuote(quote.value.id);
        quote.value = quoteStore.currentQuote;
        $q.notify({ type: "positive", message: "Angebot versendet" });
      });
    };
    const onDuplicate = async () => {
      const n = await quoteStore.duplicateQuote(quote.value.id);
      $q.notify({ type: "positive", message: "Angebot dupliziert" });
      router.push(`/quotes/${n.id}`);
    };
    const onDelete = () => {
      $q.dialog({
        title: "Angebot löschen?",
        message: "Dieses Angebot wird unwiderruflich gelöscht.",
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        await quoteStore.deleteQuote(quote.value.id);
        $q.notify({ type: "positive", message: "Angebot gelöscht" });
        router.push("/quotes");
      });
    };
    const onExportPdf = () => {
      const t = localStorage.getItem("auth_token");
      window.open(`/api/quotes/${quote.value.id}/pdf?token=${t}`, "_blank");
    };

    return {
      loading,
      quote,
      editingTitle,
      editTitle,
      internalNotes,
      discountPercent,
      showAddDialog,
      showCustomerDialog,
      customerSearch,
      allCustomers,
      customersLoading,
      filteredDialogCustomers,
      newItem,
      statusOptions,
      groupedItems,
      materialSearch,
      catalogResults,
      selectedMaterial,
      showTemplateDialog,
      templateSearch,
      allTemplates,
      templatesLoading,
      filteredTemplates,
      showSaveAsTemplateDialog,
      saveTemplateForm,
      formatPrice,
      formatDate,
      statusColor,
      statusLabel,
      startEditTitle,
      saveTitle,
      saveNotes,
      saveDiscount,
      onUpdateItem,
      onDeleteItem,
      onAddItem,
      onStatusChange,
      onSend,
      onDuplicate,
      onDelete,
      onExportPdf,
      onAssignCustomer,
      onMaterialSearch,
      selectCatalogMaterial,
      clearMaterialSearch,
      openAddDialog,
      openTemplateDialog,
      onApplyTemplate,
      onSaveAsTemplate,
      showSendDialog,
      showPriceCheck,
    };
  },
};
</script>
