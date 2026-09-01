<template>
  <q-page class="q-pa-md q-pa-lg-lg" style="background: #f6f9fc">
    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="50px" />
    </div>

    <template v-else-if="project">
      <!-- Zurück + gemeinsames Aktionsmenü -->
      <div class="row items-center justify-between q-mb-md">
        <q-btn
          flat
          no-caps
          dense
          icon="arrow_back"
          label="Zurück zu Projekten"
          color="grey-7"
          @click="$router.push('/projects')"
          style="font-weight: 600"
        />
        <q-btn-dropdown
          unelevated
          no-caps
          dense
          icon="add"
          label="Neu"
          content-style="border-radius: 12px"
          style="background: #4f46e5; color: #ffffff; border-radius: 10px; font-weight: 600; padding: 6px 14px"
        >
          <q-list style="min-width: 220px">
            <q-item
              clickable
              v-close-popup
              @click="$router.push(`/quotes/create?project_id=${project.id}`)"
            >
              <q-item-section avatar
                ><q-icon name="description" size="20px" color="#4f46e5"
              /></q-item-section>
              <q-item-section>Neues Angebot</q-item-section>
            </q-item>
            <q-separator />
            <q-item clickable v-close-popup @click="openExpenseDialog()">
              <q-item-section avatar
                ><q-icon name="payments" size="20px" color="#4f46e5"
              /></q-item-section>
              <q-item-section>Ausgabe erfassen</q-item-section>
            </q-item>
            <q-separator />
            <q-item clickable v-close-popup @click="$refs.photoInput.click()">
              <q-item-section avatar
                ><q-icon name="add_a_photo" size="20px" color="#4f46e5"
              /></q-item-section>
              <q-item-section>Foto hochladen</q-item-section>
            </q-item>
            <q-separator />
            <q-item clickable v-close-popup @click="openReportDrawer()">
              <q-item-section avatar
                ><q-icon name="edit_note" size="20px" color="#4f46e5"
              /></q-item-section>
              <q-item-section>Bautagesbericht erfassen</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
      </div>

      <input
        ref="photoInput"
        type="file"
        accept="image/*"
        multiple
        style="display: none"
        @change="onPhotosSelected"
      />

      <!-- Kopfbereich -->
      <q-card flat style="border: 1px solid #e2e8f0; border-radius: 18px; background: #ffffff">
        <q-card-section class="q-pa-lg">
          <div class="row items-start justify-between q-col-gutter-md">
            <div class="col-12 col-md-8">
              <div class="ap-field-label">
                <q-icon name="title" size="16px" color="grey-5" class="q-mr-xs" />Titel
              </div>
              <div class="ap-input-box q-mb-sm">
                <q-input
                  v-model="form.title"
                  borderless
                  dense
                  placeholder="Projekttitel"
                  style="font-weight: 700; font-size: 16px; color: #0f172a"
                />
              </div>
              <div class="row items-center q-gutter-sm q-mt-xs">
                <q-icon name="event" size="15px" color="grey-5" />
                <span style="font-size: 12.5px; color: #94a3b8"
                  >Angelegt am {{ formatDate(project.created_at) }}</span
                >
              </div>
            </div>

            <!-- Status: prominent, eigene Farbe je Zustand -->
            <div class="col-12 col-md-4">
              <div
                class="ap-status-box"
                :style="{ background: statusBg(form.status), border: '1px solid ' + statusBorder(form.status) }"
              >
                <div style="font-size: 11px; color: #64748b; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase">
                  Status
                </div>
                <q-select
                  v-model="form.status"
                  :options="statusOptions"
                  option-value="value"
                  option-label="label"
                  emit-value
                  map-options
                  borderless
                  dense
                  @update:model-value="onStatusChange"
                  :style="{ color: statusColor(form.status) }"
                  class="ap-status-select"
                >
                  <template v-slot:selected>
                    <span :style="{ fontWeight: 700, fontSize: '16px', color: statusColor(form.status) }">
                      {{ statusLabel(form.status) }}
                    </span>
                  </template>
                </q-select>
              </div>
            </div>
          </div>

          <q-separator class="q-my-md" />

          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-6">
              <div class="ap-field-label">
                <q-icon name="person" size="16px" color="grey-5" class="q-mr-xs" />Kunde
              </div>
              <div class="ap-input-box">
                <q-select
                  v-model="form.customer_id"
                  borderless
                  dense
                  :options="customerOptions"
                  option-value="value"
                  option-label="label"
                  emit-value
                  map-options
                  clearable
                  use-input
                  placeholder="Kein Kunde zugewiesen"
                  @filter="filterCustomers"
                />
              </div>
            </div>
            <div class="col-12 col-md-6">
              <div class="ap-field-label">
                <q-icon name="location_on" size="16px" color="grey-5" class="q-mr-xs" />Projektadresse
              </div>
              <div class="ap-input-box">
                <q-input
                  v-model="form.project_address"
                  borderless
                  dense
                  placeholder="Optional"
                />
              </div>
            </div>
            <div class="col-12">
              <div class="ap-field-label">
                <q-icon name="notes" size="16px" color="grey-5" class="q-mr-xs" />Beschreibung
              </div>
              <div class="ap-input-box">
                <q-input
                  v-model="form.description"
                  borderless
                  dense
                  type="textarea"
                  rows="3"
                  placeholder="Optional"
                />
              </div>
            </div>
          </div>

          <div class="row justify-end q-mt-md" v-if="isDirty">
            <q-btn
              flat
              no-caps
              label="Verwerfen"
              color="grey-7"
              class="q-mr-sm"
              @click="resetForm"
            />
            <q-btn
              unelevated
              no-caps
              icon="save"
              label="Speichern"
              :loading="saving"
              @click="onSave"
              style="background: #4f46e5; color: #ffffff; border-radius: 10px; font-weight: 600"
            />
          </div>
        </q-card-section>
      </q-card>

      <!-- Kosten-Übersicht: Plan (Angebot) vs. Ist (Ausgaben), plus Marge -->
      <q-card
        v-if="project.quotes?.length || project.invoices?.length || project.expenses?.length"
        flat
        class="q-mt-lg"
        style="border: 1px solid #e2e8f0; border-radius: 18px; background: #ffffff"
      >
        <q-card-section class="q-pa-lg">
          <div class="ap-section-title q-mb-md">
            <q-icon name="query_stats" size="18px" color="#64748b" class="q-mr-xs" />
            Kosten-Übersicht
          </div>

          <div class="row q-col-gutter-lg">
            <div class="col-6 col-sm-3">
              <div class="cost-stat-label">Angebot</div>
              <div class="cost-stat-value" style="color: #0f172a">
                {{ formatPrice(costOverview.quotesTotal) }} €
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="cost-stat-label">Rechnungen</div>
              <div class="cost-stat-value" style="color: #1d4ed8">
                {{ formatPrice(costOverview.invoicesTotal) }} €
              </div>
              <div v-if="costOverview.invoicesTotal < costOverview.quotesTotal" class="cost-stat-caption">
                von {{ formatPrice(costOverview.quotesTotal) }} € Angebot abgerechnet
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="cost-stat-label">Ausgaben</div>
              <div class="cost-stat-value" style="color: #c2410c">
                {{ formatPrice(costOverview.expensesTotal) }} €
              </div>
            </div>
            <div class="col-6 col-sm-3">
              <div class="cost-stat-label">Marge</div>
              <div
                class="cost-stat-value"
                :style="{ color: costOverview.margin >= 0 ? '#15803d' : '#dc2626' }"
              >
                {{ costOverview.margin >= 0 ? "+" : "" }}{{ formatPrice(costOverview.margin) }} €
              </div>
              <div class="cost-stat-caption">Angebot − Ausgaben</div>
            </div>
          </div>

          <div v-if="costOverview.budgetBasis > 0" class="q-mt-md">
            <div class="row items-center justify-between q-mb-xs">
              <span style="font-size: 11.5px; color: #94a3b8; font-weight: 600"
                >Ausgaben im Verhältnis zur Angebotssumme</span
              >
              <span style="font-size: 11.5px; color: #94a3b8; font-weight: 600"
                >{{ costOverview.budgetPercent }} %</span
              >
            </div>
            <div class="cost-progress-track">
              <div
                class="cost-progress-fill"
                :style="{
                  width: Math.min(costOverview.budgetPercent, 100) + '%',
                  background: costOverview.budgetColor,
                }"
              />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <!-- Angebote -->
      <div class="row items-center q-mt-lg q-mb-sm">
        <div class="ap-section-title">
          <q-icon name="description" size="18px" color="#64748b" class="q-mr-xs" />
          Angebote
          <span class="ap-section-count">{{ project.quotes?.length || 0 }}</span>
        </div>
      </div>

      <div v-if="!project.quotes || project.quotes.length === 0" class="ap-empty-card">
        <q-icon name="description" size="28px" color="#c6cad9" />
        <div class="ap-empty-text">Noch keine Angebote für dieses Projekt</div>
      </div>
      <div v-else class="ap-list-card">
        <div
          v-for="quote in project.quotes"
          :key="'q' + quote.id"
          class="ap-list-row"
          @click="$router.push(`/quotes/${quote.id}`)"
        >
          <div
            class="ap-list-avatar"
            :style="{ background: quoteStatusBg(quote.status), color: quoteStatusAccent(quote.status) }"
          >
            <q-icon name="description" size="16px" />
          </div>
          <div class="ap-list-main">
            <div class="ap-list-title">{{ quote.project_title }}</div>
            <div class="ap-list-sub">{{ quote.quote_number }} · {{ formatDate(quote.created_at) }}</div>
          </div>
          <div class="ap-list-right">
            <div class="ap-list-amount">{{ formatPrice(quote.total_gross) }} €</div>
            <div
              class="ap-list-status-pill"
              :style="{ color: quoteStatusAccent(quote.status), background: quoteStatusBg(quote.status) }"
            >
              {{ quoteStatusLabel(quote.status) }}
            </div>
          </div>
          <q-icon name="chevron_right" size="18px" color="#cbd5e1" class="q-ml-sm" />
        </div>
      </div>

      <!-- Rechnungen -->
      <div class="row items-center q-mt-lg q-mb-sm">
        <div class="ap-section-title">
          <q-icon name="receipt_long" size="18px" color="#64748b" class="q-mr-xs" />
          Rechnungen
          <span class="ap-section-count">{{ project.invoices?.length || 0 }}</span>
        </div>
      </div>

      <div v-if="!project.invoices || project.invoices.length === 0" class="ap-empty-card">
        <q-icon name="receipt_long" size="28px" color="#c6cad9" />
        <div class="ap-empty-text">Noch keine Rechnungen für dieses Projekt</div>
      </div>
      <div v-else class="ap-list-card">
        <div
          v-for="invoice in project.invoices"
          :key="'i' + invoice.id"
          class="ap-list-row"
          @click="$router.push(`/invoices/${invoice.id}`)"
        >
          <div
            class="ap-list-avatar"
            :style="{ background: invoiceStatusBg(invoice.status), color: invoiceStatusAccent(invoice.status) }"
          >
            <q-icon name="receipt_long" size="16px" />
          </div>
          <div class="ap-list-main">
            <div class="ap-list-title">{{ invoice.project_title }}</div>
            <div class="ap-list-sub">{{ invoice.invoice_number }} · {{ formatDate(invoice.created_at) }}</div>
          </div>
          <div class="ap-list-right">
            <div class="ap-list-amount">{{ formatPrice(invoice.total_gross) }} €</div>
            <div
              class="ap-list-status-pill"
              :style="{ color: invoiceStatusAccent(invoice.status), background: invoiceStatusBg(invoice.status) }"
            >
              {{ invoiceStatusLabel(invoice.status) }}
            </div>
          </div>
          <q-icon name="chevron_right" size="18px" color="#cbd5e1" class="q-ml-sm" />
        </div>
      </div>

      <!-- Ausgaben -->
      <div class="row items-center q-mt-lg q-mb-sm">
        <div class="ap-section-title">
          <q-icon name="payments" size="18px" color="#64748b" class="q-mr-xs" />
          Ausgaben
          <span class="ap-section-count">{{ project.expenses?.length || 0 }}</span>
          <span v-if="project.expenses?.length" class="ap-section-total"
            >· {{ formatPrice(expensesTotal) }} €</span
          >
        </div>
      </div>

      <div v-if="!project.expenses || project.expenses.length === 0" class="ap-empty-card">
        <q-icon name="payments" size="28px" color="#c6cad9" />
        <div class="ap-empty-text">Noch keine Ausgaben für dieses Projekt erfasst</div>
      </div>
      <div v-else class="ap-list-card">
        <div
          v-for="expense in project.expenses"
          :key="'e' + expense.id"
          class="ap-list-row"
          @click="openExpenseDialog(expense)"
        >
          <div
            class="ap-list-avatar"
            :style="{ background: expenseCategoryBg(expense.category), color: expenseCategoryAccent(expense.category) }"
          >
            <q-icon :name="expenseCategoryIcon(expense.category)" size="16px" />
          </div>
          <div class="ap-list-main">
            <div class="ap-list-title">{{ expense.description }}</div>
            <div class="ap-list-sub">
              {{ expenseCategoryLabel(expense.category) }} · {{ formatDate(expense.expense_date) }}
            </div>
          </div>
          <div class="ap-list-right">
            <div class="ap-list-amount">{{ formatPrice(expense.amount) }} €</div>
          </div>
          <q-btn
            flat
            round
            dense
            size="sm"
            icon="more_vert"
            color="grey-5"
            class="q-ml-sm"
            @click.stop
          >
            <q-menu auto-close style="border-radius: 12px">
              <q-list style="min-width: 150px">
                <q-item clickable @click="openExpenseDialog(expense)">
                  <q-item-section avatar
                    ><q-icon name="edit" size="18px" color="grey-7"
                  /></q-item-section>
                  <q-item-section>Bearbeiten</q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable class="text-negative" @click="onDeleteExpense(expense)">
                  <q-item-section avatar
                    ><q-icon name="delete" size="18px" color="negative"
                  /></q-item-section>
                  <q-item-section>Löschen</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>
      </div>

      <!-- Fotos -->
      <div class="row items-center q-mt-lg q-mb-sm">
        <div class="ap-section-title">
          <q-icon name="photo_library" size="18px" color="#64748b" class="q-mr-xs" />
          Fotos
          <span class="ap-section-count">{{ project.photos?.length || 0 }}</span>
        </div>
      </div>

      <div v-if="!project.photos || project.photos.length === 0" class="ap-empty-card">
        <q-icon name="photo_library" size="28px" color="#c6cad9" />
        <div class="ap-empty-text">Noch keine Fotos für dieses Projekt</div>
      </div>
      <div v-else class="photo-grid">
        <div
          v-for="photo in project.photos"
          :key="'ph' + photo.id"
          class="photo-tile"
          @click="openPhotoViewer(photo)"
        >
          <img :src="photo.url" :alt="photo.caption || 'Projektfoto'" />
        </div>
      </div>

      <!-- Bautagesberichte -->
      <div class="row items-center q-mt-lg q-mb-sm">
        <div class="ap-section-title">
          <q-icon name="edit_note" size="18px" color="#64748b" class="q-mr-xs" />
          Bautagesberichte
          <span class="ap-section-count">{{ project.reports?.length || 0 }}</span>
        </div>
      </div>

      <div v-if="!project.reports || project.reports.length === 0" class="ap-empty-card">
        <q-icon name="edit_note" size="28px" color="#c6cad9" />
        <div class="ap-empty-text">Noch keine Bautagesberichte für dieses Projekt</div>
      </div>
      <div v-else class="ap-list-card">
        <div
          v-for="report in project.reports"
          :key="'r' + report.id"
          class="ap-list-row"
          @click="openReportDrawer(report)"
        >
          <div class="ap-list-avatar" style="background: #eef2ff; color: #4f46e5">
            <q-icon name="edit_note" size="16px" />
          </div>
          <div class="ap-list-main">
            <div class="ap-list-title">{{ formatDate(report.report_date) }}</div>
            <div class="ap-list-sub">{{ truncateText(report.content, 90) }}</div>
          </div>
          <q-btn
            flat
            round
            dense
            size="sm"
            icon="more_vert"
            color="grey-5"
            class="q-ml-sm"
            @click.stop
          >
            <q-menu auto-close style="border-radius: 12px">
              <q-list style="min-width: 150px">
                <q-item clickable @click="openReportDrawer(report)">
                  <q-item-section avatar
                    ><q-icon name="edit" size="18px" color="grey-7"
                  /></q-item-section>
                  <q-item-section>Bearbeiten</q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable class="text-negative" @click="onDeleteReport(report)">
                  <q-item-section avatar
                    ><q-icon name="delete" size="18px" color="negative"
                  /></q-item-section>
                  <q-item-section>Löschen</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>
      </div>

      <!-- Drawer: Ausgabe erfassen/bearbeiten -->
      <q-dialog
        v-model="expenseDialog.show"
        position="right"
        full-height
        maximized-on-mobile
        persistent
      >
        <q-card
          style="
            width: 440px;
            max-width: 95vw;
            display: flex;
            flex-direction: column;
          "
        >
          <q-card-section
            class="row items-center q-pb-sm"
            style="border-bottom: 1px solid #f1f5f9; flex-shrink: 0"
          >
            <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
              {{ expenseDialog.id ? "Ausgabe bearbeiten" : "Ausgabe erfassen" }}
            </h6>
            <q-space />
            <q-btn flat round dense icon="close" color="grey-5" v-close-popup />
          </q-card-section>
          <q-card-section
            class="q-gutter-md"
            style="flex: 1; overflow-y: auto"
          >
            <div>
              <div class="ap-field-label">
                <q-icon name="notes" size="16px" color="grey-5" class="q-mr-xs" />Beschreibung *
              </div>
              <div class="ap-input-box">
                <q-input
                  v-model="expenseDialog.description"
                  borderless
                  dense
                  autofocus
                  placeholder="z. B. Fliesen, Dichtmasse..."
                />
              </div>
            </div>
            <div class="row q-col-gutter-sm">
              <div class="col-6">
                <div class="ap-field-label">
                  <q-icon name="euro" size="16px" color="grey-5" class="q-mr-xs" />Betrag *
                </div>
                <div class="ap-input-box">
                  <q-input
                    v-model.number="expenseDialog.amount"
                    borderless
                    dense
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="0,00"
                  />
                </div>
              </div>
              <div class="col-6">
                <div class="ap-field-label">
                  <q-icon name="event" size="16px" color="grey-5" class="q-mr-xs" />Datum
                </div>
                <div class="ap-input-box">
                  <q-input v-model="expenseDialog.expense_date" borderless dense type="date" />
                </div>
              </div>
            </div>
            <div>
              <div class="ap-field-label">
                <q-icon name="category" size="16px" color="grey-5" class="q-mr-xs" />Kategorie
              </div>
              <div class="ap-input-box">
                <q-select
                  v-model="expenseDialog.category"
                  borderless
                  dense
                  :options="expenseCategoryOptions"
                  option-value="value"
                  option-label="label"
                  emit-value
                  map-options
                />
              </div>
            </div>
            <div>
              <div class="ap-field-label">
                <q-icon name="notes" size="16px" color="grey-5" class="q-mr-xs" />Notizen (optional)
              </div>
              <div class="ap-input-box">
                <q-input
                  v-model="expenseDialog.notes"
                  borderless
                  dense
                  type="textarea"
                  rows="2"
                  placeholder="Optional"
                />
              </div>
            </div>
          </q-card-section>
          <q-card-actions
            align="right"
            class="q-pa-md"
            style="border-top: 1px solid #f1f5f9; flex-shrink: 0"
          >
            <q-btn
              v-if="expenseDialog.id"
              flat
              no-caps
              label="Löschen"
              color="negative"
              class="q-mr-auto"
              @click="onDeleteExpense({ id: expenseDialog.id })"
            />
            <q-btn flat no-caps label="Abbrechen" color="grey" v-close-popup />
            <q-btn
              label="Speichern"
              no-caps
              unelevated
              icon="save"
              :loading="expenseDialog.saving"
              @click="onSaveExpense"
              style="background: #4f46e5; color: #ffffff"
            />
          </q-card-actions>
        </q-card>
      </q-dialog>

      <!-- Fotoansicht (Lightbox) -->
      <q-dialog v-model="photoViewer.show">
        <q-card style="width: 640px; max-width: 95vw; border-radius: 16px; overflow: hidden">
          <q-img
            v-if="photoViewer.photo"
            :src="photoViewer.photo.url"
            style="max-height: 70vh"
            fit="contain"
          />
          <q-card-section class="row items-center justify-between q-pa-md">
            <div style="font-size: 12.5px; color: #64748b">
              {{ photoViewer.photo?.caption || formatDate(photoViewer.photo?.created_at) }}
            </div>
            <div>
              <q-btn flat round dense icon="delete" color="negative" @click="onDeletePhoto(photoViewer.photo)" />
              <q-btn flat round dense icon="close" color="grey-6" v-close-popup />
            </div>
          </q-card-section>
        </q-card>
      </q-dialog>

      <!-- Drawer: Bautagesbericht erfassen/bearbeiten -->
      <q-dialog
        v-model="reportDialog.show"
        position="right"
        full-height
        maximized-on-mobile
        persistent
      >
        <q-card
          style="
            width: 440px;
            max-width: 95vw;
            display: flex;
            flex-direction: column;
          "
        >
          <q-card-section
            class="row items-center q-pb-sm"
            style="border-bottom: 1px solid #f1f5f9; flex-shrink: 0"
          >
            <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">
              {{ reportDialog.id ? "Bautagesbericht bearbeiten" : "Bautagesbericht erfassen" }}
            </h6>
            <q-space />
            <q-btn flat round dense icon="close" color="grey-5" v-close-popup />
          </q-card-section>
          <q-card-section class="q-gutter-md" style="flex: 1; overflow-y: auto">
            <div>
              <div class="ap-field-label">
                <q-icon name="event" size="16px" color="grey-5" class="q-mr-xs" />Datum
              </div>
              <div class="ap-input-box">
                <q-input v-model="reportDialog.report_date" borderless dense type="date" />
              </div>
            </div>
            <div>
              <div class="ap-field-label">
                <q-icon name="auto_awesome" size="16px" color="grey-5" class="q-mr-xs" />Stichpunkte (optional)
              </div>
              <div class="ap-input-box">
                <q-input
                  v-model="reportDialog.notes"
                  borderless
                  dense
                  type="textarea"
                  rows="2"
                  placeholder="z. B. Fliesen Bad verlegt, Wasserschaden unter Wanne entdeckt, Kunde informiert"
                />
              </div>
              <q-btn
                flat
                no-caps
                dense
                icon="auto_awesome"
                label="Mit KI ausformulieren"
                :loading="reportDialog.generating"
                @click="onGenerateReportDraft"
                class="q-mt-xs"
                style="color: #7c3aed; font-weight: 600"
              />
            </div>
            <div>
              <div class="ap-field-label">
                <q-icon name="edit_note" size="16px" color="grey-5" class="q-mr-xs" />Bericht *
              </div>
              <div class="ap-input-box">
                <q-input
                  v-model="reportDialog.content"
                  borderless
                  dense
                  type="textarea"
                  rows="8"
                  autofocus
                  placeholder="Was wurde heute gemacht? Besonderheiten, Materialverbrauch, Absprachen..."
                />
              </div>
            </div>
          </q-card-section>
          <q-card-actions
            align="right"
            class="q-pa-md"
            style="border-top: 1px solid #f1f5f9; flex-shrink: 0"
          >
            <q-btn
              v-if="reportDialog.id"
              flat
              no-caps
              label="Löschen"
              color="negative"
              class="q-mr-auto"
              @click="onDeleteReport({ id: reportDialog.id })"
            />
            <q-btn flat no-caps label="Abbrechen" color="grey" v-close-popup />
            <q-btn
              label="Speichern"
              no-caps
              unelevated
              icon="save"
              :loading="reportDialog.saving"
              @click="onSaveReport"
              style="background: #4f46e5; color: #ffffff"
            />
          </q-card-actions>
        </q-card>
      </q-dialog>
    </template>

    <div v-else class="text-center q-pa-xl" style="color: #94a3b8">
      Projekt nicht gefunden.
    </div>
  </q-page>
</template>
<script>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useProjectStore } from "src/stores/projects";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";

export default {
  name: "ProjectDetailPage",
  setup() {
    const route = useRoute();
    const projectStore = useProjectStore();
    const $q = useQuasar();

    const loading = ref(true);
    const saving = ref(false);
    const project = computed(() => projectStore.currentProject);

    const emptyForm = {
      title: "",
      customer_id: null,
      project_address: "",
      description: "",
      status: "angefragt",
    };
    const form = reactive({ ...emptyForm });

    const applyProjectToForm = () => {
      if (!project.value) return;
      form.title = project.value.title || "";
      form.customer_id = project.value.customer_id || null;
      form.project_address = project.value.project_address || "";
      form.description = project.value.description || "";
      form.status = project.value.status || "angefragt";
    };

    const isDirty = computed(() => {
      if (!project.value) return false;
      return (
        form.title !== (project.value.title || "") ||
        form.customer_id !== (project.value.customer_id || null) ||
        form.project_address !== (project.value.project_address || "") ||
        form.description !== (project.value.description || "")
      );
    });

    const loadProject = async () => {
      loading.value = true;
      try {
        await projectStore.fetchProject(route.params.id);
        applyProjectToForm();
      } catch (e) {
        console.error(e);
      } finally {
        loading.value = false;
      }
    };

    onMounted(loadProject);

    const resetForm = () => applyProjectToForm();

    const onSave = async () => {
      if (!form.title) {
        $q.notify({ type: "warning", message: "Titel ist ein Pflichtfeld" });
        return;
      }
      saving.value = true;
      try {
        await projectStore.updateProject(route.params.id, {
          title: form.title,
          customer_id: form.customer_id,
          project_address: form.project_address,
          description: form.description,
        });
        $q.notify({ type: "positive", message: "Projekt gespeichert" });
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Speichern",
        });
      } finally {
        saving.value = false;
      }
    };

    // Status wird per Dropdown geändert und speichert sofort, ohne dass
    // erst der "Speichern"-Button für die übrigen Felder betätigt werden muss.
    const onStatusChange = async (value) => {
      try {
        await projectStore.updateProject(route.params.id, { status: value });
        $q.notify({ type: "positive", message: "Status aktualisiert" });
      } catch (e) {
        form.status = project.value.status;
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Status konnte nicht geändert werden",
        });
      }
    };

    const statusOptions = [
      { label: "Angefragt", value: "angefragt" },
      { label: "Kalkuliert", value: "kalkuliert" },
      { label: "Beauftragt", value: "beauftragt" },
      { label: "In Ausführung", value: "in_ausfuehrung" },
      { label: "Abgeschlossen", value: "abgeschlossen" },
      { label: "Storniert", value: "storniert" },
    ];

    const statusColor = (s) =>
      ({
        angefragt: "#475569",
        kalkuliert: "#1d4ed8",
        beauftragt: "#4f46e5",
        in_ausfuehrung: "#c2410c",
        abgeschlossen: "#15803d",
        storniert: "#64748b",
      })[s] || "#475569";
    const statusBg = (s) =>
      ({
        angefragt: "#f8fafc",
        kalkuliert: "#eff6ff",
        beauftragt: "#eef2ff",
        in_ausfuehrung: "#fff7ed",
        abgeschlossen: "#f0fdf4",
        storniert: "#f1f5f9",
      })[s] || "#f8fafc";
    const statusBorder = (s) =>
      ({
        angefragt: "#e2e8f0",
        kalkuliert: "#bfdbfe",
        beauftragt: "#c7d2fe",
        in_ausfuehrung: "#fed7aa",
        abgeschlossen: "#bbf7d0",
        storniert: "#e2e8f0",
      })[s] || "#e2e8f0";
    const statusLabel = (s) =>
      (statusOptions.find((o) => o.value === s) || {}).label || s;

    const formatDate = (val) =>
      val ? new Date(val).toLocaleDateString("de-DE") : "-";
    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

    // ---- Status-Badges für Angebote/Rechnungen (gleiche Farblogik wie
    // QuotesListPage.vue / InvoicesListPage.vue) ----
    const quoteStatusColor = (s) =>
      ({
        draft: "grey",
        sent: "blue",
        viewed: "info",
        accepted: "positive",
        rejected: "negative",
        expired: "grey-7",
      })[s] || "grey";
    const quoteStatusLabel = (s) =>
      ({
        draft: "Entwurf",
        sent: "Gesendet",
        viewed: "Gesehen",
        accepted: "Angenommen",
        rejected: "Abgelehnt",
        expired: "Abgelaufen",
      })[s] || s;
    const invoiceStatusColor = (s) =>
      ({
        draft: "grey",
        sent: "blue",
        paid: "green",
        partial_paid: "orange",
        overdue: "red",
        cancelled: "grey-7",
      })[s] || "grey";
    const invoiceStatusLabel = (s) =>
      ({
        draft: "Entwurf",
        sent: "Versendet",
        paid: "Bezahlt",
        partial_paid: "Teilweise bezahlt",
        overdue: "Überfällig",
        cancelled: "Storniert",
      })[s] || s;

    // ---- Hex-Farben für Avatar-Icon und Status-Pill in den
    // Angebote-/Rechnungen-Listenzeilen (moderner als Quasar-Badges) ----
    const quoteStatusAccent = (s) =>
      ({
        draft: "#64748b",
        sent: "#2563eb",
        viewed: "#0891b2",
        accepted: "#16a34a",
        rejected: "#dc2626",
        expired: "#94a3b8",
      })[s] || "#64748b";
    const quoteStatusBg = (s) =>
      ({
        draft: "#f8fafc",
        sent: "#eff6ff",
        viewed: "#ecfeff",
        accepted: "#f0fdf4",
        rejected: "#fef2f2",
        expired: "#f1f5f9",
      })[s] || "#f8fafc";
    const invoiceStatusAccent = (s) =>
      ({
        draft: "#64748b",
        sent: "#2563eb",
        paid: "#16a34a",
        partial_paid: "#ea580c",
        overdue: "#dc2626",
        cancelled: "#94a3b8",
      })[s] || "#64748b";
    const invoiceStatusBg = (s) =>
      ({
        draft: "#f8fafc",
        sent: "#eff6ff",
        paid: "#f0fdf4",
        partial_paid: "#fff7ed",
        overdue: "#fef2f2",
        cancelled: "#f1f5f9",
      })[s] || "#f8fafc";

    // ---- Kunden-Auswahl ----
    const allCustomers = ref([]);
    const customerOptions = ref([]);
    const customerName = (c) =>
      c.type === "business"
        ? c.company_name || c.contact_person || "Unbekannt"
        : [c.first_name, c.last_name].filter(Boolean).join(" ") || "Unbekannt";

    const loadCustomers = async () => {
      try {
        const r = await api.get("/customers");
        allCustomers.value = r.data.data || r.data;
        customerOptions.value = allCustomers.value.map((c) => ({
          label: customerName(c),
          value: c.id,
        }));
      } catch (e) {
        console.error(e);
      }
    };
    const filterCustomers = (val, update) => {
      update(() => {
        const list = allCustomers.value.map((c) => ({
          label: customerName(c),
          value: c.id,
        }));
        if (!val) {
          customerOptions.value = list;
        } else {
          const s = val.toLowerCase();
          customerOptions.value = list.filter((c) =>
            c.label.toLowerCase().includes(s),
          );
        }
      });
    };
    onMounted(loadCustomers);

    watch(
      () => route.params.id,
      () => loadProject(),
    );

    // ---- Ausgaben (Phase 3) ----
    const expenseCategoryOptions = [
      { label: "Material", value: "material" },
      { label: "Lohn", value: "lohn" },
      { label: "Fremdleistung", value: "fremdleistung" },
      { label: "Sonstiges", value: "sonstiges" },
    ];

    const expenseCategoryLabel = (c) =>
      (expenseCategoryOptions.find((o) => o.value === c) || {}).label || c;

    const expenseCategoryIcon = (c) =>
      ({
        material: "inventory_2",
        lohn: "engineering",
        fremdleistung: "handshake",
        sonstiges: "receipt",
      })[c] || "receipt";

    const expenseCategoryAccent = (c) =>
      ({
        material: "#7c3aed",
        lohn: "#2563eb",
        fremdleistung: "#0891b2",
        sonstiges: "#64748b",
      })[c] || "#64748b";

    const expenseCategoryBg = (c) =>
      ({
        material: "#f5f3ff",
        lohn: "#eff6ff",
        fremdleistung: "#ecfeff",
        sonstiges: "#f8fafc",
      })[c] || "#f8fafc";

    const expensesTotal = computed(() =>
      (project.value?.expenses || []).reduce(
        (sum, e) => sum + Number(e.amount || 0),
        0,
      ),
    );

    const emptyExpenseDialog = () => ({
      show: false,
      id: null,
      description: "",
      amount: null,
      category: "sonstiges",
      expense_date: "",
      notes: "",
      saving: false,
    });
    const expenseDialog = reactive(emptyExpenseDialog());

    const openExpenseDialog = (expense = null) => {
      Object.assign(expenseDialog, emptyExpenseDialog());
      if (expense) {
        expenseDialog.id = expense.id;
        expenseDialog.description = expense.description;
        expenseDialog.amount = Number(expense.amount);
        expenseDialog.category = expense.category || "sonstiges";
        expenseDialog.expense_date = expense.expense_date
          ? String(expense.expense_date).substring(0, 10)
          : "";
        expenseDialog.notes = expense.notes || "";
      }
      expenseDialog.show = true;
    };

    const onSaveExpense = async () => {
      if (!expenseDialog.description || !expenseDialog.amount) {
        $q.notify({
          type: "warning",
          message: "Beschreibung und Betrag sind Pflichtfelder",
        });
        return;
      }
      expenseDialog.saving = true;
      const payload = {
        description: expenseDialog.description,
        amount: expenseDialog.amount,
        category: expenseDialog.category,
        expense_date: expenseDialog.expense_date || null,
        notes: expenseDialog.notes || null,
      };
      try {
        if (expenseDialog.id) {
          await api.put(
            `/projects/${route.params.id}/expenses/${expenseDialog.id}`,
            payload,
          );
        } else {
          await api.post(`/projects/${route.params.id}/expenses`, payload);
        }
        expenseDialog.show = false;
        $q.notify({ type: "positive", message: "Ausgabe gespeichert" });
        await loadProject();
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Speichern",
        });
      } finally {
        expenseDialog.saving = false;
      }
    };

    const onDeleteExpense = (expense) => {
      $q.dialog({
        title: "Löschen?",
        message: "Diese Ausgabe wirklich löschen?",
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          await api.delete(`/projects/${route.params.id}/expenses/${expense.id}`);
          expenseDialog.show = false;
          $q.notify({ type: "positive", message: "Ausgabe gelöscht" });
          await loadProject();
        } catch (e) {
          $q.notify({
            type: "negative",
            message: e.response?.data?.message || "Fehler beim Löschen",
          });
        }
      });
    };

    // ---- Kosten-Übersicht: Plan (Angebot) vs. Ist (Ausgaben) ----
    const costOverview = computed(() => {
      const quotesTotal = (project.value?.quotes || [])
        .filter((q) => q.status !== "rejected")
        .reduce((sum, q) => sum + Number(q.total_gross || 0), 0);
      const invoicesTotal = (project.value?.invoices || [])
        .filter((i) => i.status !== "cancelled")
        .reduce((sum, i) => sum + Number(i.total_gross || 0), 0);
      const expensesTotalVal = expensesTotal.value;

      // Marge = Angebotssumme minus Ausgaben - immer auf Basis des gesamten
      // Projekts, unabhängig davon wie viel davon schon abgerechnet ist.
      // (Nicht auf Basis der Rechnung: sonst würde ein noch nicht
      // fakturiertes zweites Angebot fälschlich aus der Marge verschwinden.)
      const margin = quotesTotal - expensesTotalVal;

      const budgetBasis = quotesTotal;
      const budgetPercent =
        budgetBasis > 0 ? Math.round((expensesTotalVal / budgetBasis) * 100) : 0;
      const budgetColor =
        budgetPercent >= 100 ? "#dc2626" : budgetPercent >= 80 ? "#f97316" : "#22c55e";

      return {
        quotesTotal,
        invoicesTotal,
        expensesTotal: expensesTotalVal,
        margin,
        budgetBasis,
        budgetPercent,
        budgetColor,
      };
    });

    // ---- Hilfsfunktion: Text kürzen (für Bautagesbericht-Vorschau) ----
    const truncateText = (text, max) => {
      if (!text) return "";
      return text.length > max ? text.slice(0, max).trim() + "..." : text;
    };

    // ---- Fotos ----
    const photoViewer = reactive({ show: false, photo: null });

    const openPhotoViewer = (photo) => {
      photoViewer.photo = photo;
      photoViewer.show = true;
    };

    const onPhotosSelected = async (event) => {
      const files = Array.from(event.target.files || []);
      if (!files.length) return;
      const fd = new FormData();
      files.forEach((f) => fd.append("photos[]", f));
      try {
        await api.post(`/projects/${route.params.id}/photos`, fd, {
          headers: { "Content-Type": "multipart/form-data" },
        });
        $q.notify({
          type: "positive",
          message: files.length > 1 ? "Fotos hochgeladen" : "Foto hochgeladen",
        });
        await loadProject();
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Hochladen",
        });
      } finally {
        event.target.value = "";
      }
    };

    const onDeletePhoto = (photo) => {
      if (!photo) return;
      $q.dialog({
        title: "Löschen?",
        message: "Dieses Foto wirklich löschen?",
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          await api.delete(`/projects/${route.params.id}/photos/${photo.id}`);
          photoViewer.show = false;
          $q.notify({ type: "positive", message: "Foto gelöscht" });
          await loadProject();
        } catch (e) {
          $q.notify({
            type: "negative",
            message: e.response?.data?.message || "Fehler beim Löschen",
          });
        }
      });
    };

    // ---- Bautagesberichte ----
    const emptyReportDialog = () => ({
      show: false,
      id: null,
      report_date: new Date().toISOString().substring(0, 10),
      notes: "",
      content: "",
      saving: false,
      generating: false,
    });
    const reportDialog = reactive(emptyReportDialog());

    const openReportDrawer = (report = null) => {
      Object.assign(reportDialog, emptyReportDialog());
      if (report) {
        reportDialog.id = report.id;
        reportDialog.report_date = report.report_date
          ? String(report.report_date).substring(0, 10)
          : reportDialog.report_date;
        reportDialog.content = report.content || "";
      }
      reportDialog.show = true;
    };

    const onGenerateReportDraft = async () => {
      if (!reportDialog.notes) {
        $q.notify({ type: "warning", message: "Bitte kurze Stichpunkte eingeben" });
        return;
      }
      reportDialog.generating = true;
      try {
        const r = await api.post(`/projects/${route.params.id}/reports/ai-draft`, {
          notes: reportDialog.notes,
        });
        reportDialog.content = r.data.content;
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "KI-Generierung fehlgeschlagen",
        });
      } finally {
        reportDialog.generating = false;
      }
    };

    const onSaveReport = async () => {
      if (!reportDialog.content) {
        $q.notify({ type: "warning", message: "Bericht darf nicht leer sein" });
        return;
      }
      reportDialog.saving = true;
      const payload = {
        report_date: reportDialog.report_date || null,
        content: reportDialog.content,
      };
      try {
        if (reportDialog.id) {
          await api.put(
            `/projects/${route.params.id}/reports/${reportDialog.id}`,
            payload,
          );
        } else {
          await api.post(`/projects/${route.params.id}/reports`, payload);
        }
        reportDialog.show = false;
        $q.notify({ type: "positive", message: "Bautagesbericht gespeichert" });
        await loadProject();
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Speichern",
        });
      } finally {
        reportDialog.saving = false;
      }
    };

    const onDeleteReport = (report) => {
      $q.dialog({
        title: "Löschen?",
        message: "Diesen Bautagesbericht wirklich löschen?",
        cancel: true,
        color: "negative",
      }).onOk(async () => {
        try {
          await api.delete(`/projects/${route.params.id}/reports/${report.id}`);
          reportDialog.show = false;
          $q.notify({ type: "positive", message: "Bautagesbericht gelöscht" });
          await loadProject();
        } catch (e) {
          $q.notify({
            type: "negative",
            message: e.response?.data?.message || "Fehler beim Löschen",
          });
        }
      });
    };

    return {
      loading,
      saving,
      project,
      form,
      isDirty,
      resetForm,
      onSave,
      onStatusChange,
      statusOptions,
      statusColor,
      statusBg,
      statusBorder,
      statusLabel,
      formatDate,
      formatPrice,
      quoteStatusColor,
      quoteStatusLabel,
      quoteStatusAccent,
      quoteStatusBg,
      invoiceStatusColor,
      invoiceStatusLabel,
      invoiceStatusAccent,
      invoiceStatusBg,
      customerOptions,
      filterCustomers,
      expenseCategoryOptions,
      expenseCategoryLabel,
      expenseCategoryIcon,
      expenseCategoryAccent,
      expenseCategoryBg,
      expensesTotal,
      expenseDialog,
      openExpenseDialog,
      onSaveExpense,
      onDeleteExpense,
      costOverview,
      truncateText,
      photoViewer,
      openPhotoViewer,
      onPhotosSelected,
      onDeletePhoto,
      reportDialog,
      openReportDrawer,
      onGenerateReportDraft,
      onSaveReport,
      onDeleteReport,
    };
  },
};
</script>

<style scoped>
.ap-status-box {
  border-radius: 12px;
  padding: 10px 14px 4px;
}
.ap-status-select :deep(.q-field__control) {
  padding-left: 0;
  height: 32px;
}
.ap-status-select :deep(.q-field__marginal) {
  height: 32px;
  color: inherit;
}
.ap-field-label {
  font-size: 12.5px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
}
.ap-input-box {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #ffffff;
  padding: 0 12px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.ap-input-box:focus-within {
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}
.ap-input-box :deep(.q-field__control) {
  min-height: 40px;
}
.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 10px;
}
.photo-tile {
  position: relative;
  aspect-ratio: 1;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  border: 1px solid #e2e8f0;
  background: #f1f5f9;
  transition: box-shadow 0.15s;
}
.photo-tile:hover {
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
}
.photo-tile img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.ap-section-title {
  display: flex;
  align-items: center;
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}
.ap-section-count {
  margin-left: 8px;
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
}
.ap-section-total {
  margin-left: 4px;
  font-size: 12px;
  font-weight: 600;
  color: #1d4ed8;
}
.cost-stat-label {
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  margin-bottom: 4px;
}
.cost-stat-value {
  font-size: 19px;
  font-weight: 700;
  line-height: 1.2;
}
.cost-stat-caption {
  font-size: 10.5px;
  color: #94a3b8;
  margin-top: 3px;
}
.cost-progress-track {
  height: 6px;
  border-radius: 999px;
  background: #f1f5f9;
  overflow: hidden;
}
.cost-progress-fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.3s;
}
.ap-empty-card {
  padding: 32px 20px;
  text-align: center;
  background: #ffffff;
  border: 1px dashed #d8dbe8;
  border-radius: 14px;
}
.ap-empty-text {
  margin-top: 8px;
  font-size: 13px;
  color: #94a3b8;
}
.ap-list-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.ap-list-row {
  display: flex;
  align-items: center;
  padding: 11px 16px;
  cursor: pointer;
  transition: background 0.15s;
  border-bottom: 1px solid #f1f5f9;
}
.ap-list-row:last-child {
  border-bottom: none;
}
.ap-list-row:hover {
  background: #f8fafc;
}
.ap-list-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  margin-right: 12px;
  flex-shrink: 0;
}
.ap-list-main {
  flex: 1;
  min-width: 0;
}
.ap-list-title {
  font-size: 13.5px;
  font-weight: 600;
  color: #0f172a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ap-list-sub {
  font-size: 11.5px;
  color: #94a3b8;
  margin-top: 1px;
}
.ap-list-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
  margin-right: 4px;
}
.ap-list-amount {
  font-size: 13.5px;
  font-weight: 700;
  color: #1d4ed8;
  white-space: nowrap;
}
.ap-list-status-pill {
  font-size: 10.5px;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
  white-space: nowrap;
  letter-spacing: 0.01em;
}
</style>
