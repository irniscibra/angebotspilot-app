<template>
  <q-page class="q-pa-md q-pa-lg-lg">
    <!-- KI Loading Dialog -->
    <q-dialog v-model="generating" persistent>
      <q-card
        class="text-center q-pa-xl"
        style="
          min-width: 320px;
          max-width: 420px;
          border-radius: 20px;
          background: #ffffff;
        "
      >
        <q-spinner-orbit color="primary" size="60px" class="q-mb-lg" />
        <h6 class="q-my-sm" style="font-weight: 700; color: #0f172a">
          KI erstellt Ihr Angebot
        </h6>
        <p style="color: #64748b">{{ generatingSteps[currentStep] }}</p>
        <q-linear-progress
          :value="progress"
          color="primary"
          class="q-mt-md"
          rounded
          style="height: 6px"
        />
      </q-card>
    </q-dialog>

    <!-- Schritt 1: Erstellungsmethode wählen -->
    <div v-if="!quoteCreated">
      <div class="text-center q-mb-xl">
        <h5 class="q-my-none" style="font-weight: 800; color: #0f172a">
          Neues Angebot erstellen
        </h5>
        <p class="q-mt-sm" style="color: #64748b">
          Wählen Sie wie Sie das Angebot erstellen möchten
        </p>
      </div>

      <div style="max-width: 1000px; margin: 0 auto">
        <!-- Methode wählen -->
        <div class="row q-gutter-md q-mb-lg justify-center">
          <q-card
            flat
            clickable
            @click="createMode = 'ai'"
            style="
              width: 220px;
              border-radius: 14px;
              cursor: pointer;
              transition: all 0.2s;
            "
            :style="
              createMode === 'ai'
                ? 'border: 2px solid #1d4ed8; background: #eff6ff;'
                : 'border: 2px solid #e2e8f0; background: #fff;'
            "
          >
            <q-card-section class="text-center q-pa-lg">
              <q-icon
                name="auto_awesome"
                size="40px"
                :color="createMode === 'ai' ? 'primary' : 'grey-5'"
              />
              <div
                class="q-mt-sm"
                style="font-size: 15px; font-weight: 700"
                :style="
                  createMode === 'ai' ? 'color: #1d4ed8;' : 'color: #0f172a;'
                "
              >
                KI-Angebot
              </div>
              <div style="font-size: 12px; color: #64748b; margin-top: 4px">
                Projekt beschreiben, KI kalkuliert automatisch
              </div>
            </q-card-section>
          </q-card>

          <q-card
            flat
            clickable
            @click="createMode = 'template'"
            style="
              width: 220px;
              border-radius: 14px;
              cursor: pointer;
              transition: all 0.2s;
            "
            :style="
              createMode === 'template'
                ? 'border: 2px solid #0d9488; background: #f0fdfa;'
                : 'border: 2px solid #e2e8f0; background: #fff;'
            "
          >
            <q-card-section class="text-center q-pa-lg">
              <q-icon
                name="content_paste"
                size="40px"
                :color="createMode === 'template' ? 'teal' : 'grey-5'"
              />
              <div
                class="q-mt-sm"
                style="font-size: 15px; font-weight: 700"
                :style="
                  createMode === 'template'
                    ? 'color: #0d9488;'
                    : 'color: #0f172a;'
                "
              >
                Aus Vorlage
              </div>
              <div style="font-size: 12px; color: #64748b; margin-top: 4px">
                Gespeicherte Leistungsvorlage verwenden
              </div>
            </q-card-section>
          </q-card>

          <q-card
            flat
            clickable
            @click="createMode = 'empty'"
            style="
              width: 220px;
              border-radius: 14px;
              cursor: pointer;
              transition: all 0.2s;
            "
            :style="
              createMode === 'empty'
                ? 'border: 2px solid #6366f1; background: #eef2ff;'
                : 'border: 2px solid #e2e8f0; background: #fff;'
            "
          >
            <q-card-section class="text-center q-pa-lg">
              <q-icon
                name="edit_note"
                size="40px"
                :color="createMode === 'empty' ? 'indigo' : 'grey-5'"
              />
              <div
                class="q-mt-sm"
                style="font-size: 15px; font-weight: 700"
                :style="
                  createMode === 'empty' ? 'color: #6366f1;' : 'color: #0f172a;'
                "
              >
                Leeres Angebot
              </div>
              <div style="font-size: 12px; color: #64748b; margin-top: 4px">
                Manuell Positionen hinzufügen
              </div>
            </q-card-section>
          </q-card>

          <q-card
            flat
            clickable
            @click="createMode = 'pdf'"
            style="
              width: 220px;
              border-radius: 14px;
              cursor: pointer;
              transition: all 0.2s;
            "
            :style="
              createMode === 'pdf'
                ? 'border: 2px solid #dc2626; background: #fef2f2;'
                : 'border: 2px solid #e2e8f0; background: #fff;'
            "
          >
            <q-card-section class="text-center q-pa-lg">
              <q-icon
                name="picture_as_pdf"
                size="40px"
                :color="createMode === 'pdf' ? 'red' : 'grey-5'"
              />
              <div
                class="q-mt-sm"
                style="font-size: 15px; font-weight: 700"
                :style="
                  createMode === 'pdf' ? 'color: #dc2626;' : 'color: #0f172a;'
                "
              >
                PDF importieren
              </div>
              <div style="font-size: 12px; color: #64748b; margin-top: 4px">
                Fremdes Angebot hochladen, KI übernimmt Positionen
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- KI Modus -->
        <q-card
          v-if="createMode === 'ai'"
          flat
          style="
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
          "
        >
          <q-card-section class="q-pa-lg">
            <q-form @submit="onGenerate">
              <q-input
                v-model="description"
                filled
                type="textarea"
                label="Projektbeschreibung"
                hint="z.B. Kundenanfrage von Google reinkopieren"
                rows="5"
                :rules="[(val) => val.length >= 10 || 'Mindestens 10 Zeichen']"
                class="q-mb-md"
              />
              <q-input
                v-model="address"
                filled
                label="Projektadresse (optional)"
                class="q-mb-md"
              >
                <template v-slot:prepend
                  ><q-icon name="location_on" color="grey-5"
                /></template>
              </q-input>
              <q-select
                v-model="selectedCustomer"
                filled
                label="Kunde (optional)"
                :options="customerOptions"
                option-value="value"
                option-label="label"
                emit-value
                map-options
                clearable
                class="q-mb-md"
                use-input
                @filter="filterCustomers"
              >
                <template v-slot:prepend
                  ><q-icon name="person" color="grey-5"
                /></template>
                <template v-slot:no-option
                  ><q-item
                    ><q-item-section style="color: #94a3b8"
                      >Keine Kunden gefunden</q-item-section
                    ></q-item
                  ></template
                >
              </q-select>
              <q-btn
                type="submit"
                color="primary"
                icon="auto_awesome"
                label="Angebot generieren"
                class="full-width"
                size="lg"
                no-caps
                :loading="generating"
                style="border-radius: 10px; font-weight: 600"
              />
            </q-form>
          </q-card-section>
        </q-card>

        <!-- Vorlage Modus -->
        <q-card
          v-if="createMode === 'template'"
          flat
          style="
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
          "
        >
          <q-card-section class="q-pa-lg">
            <q-input
              v-model="templateSearch"
              filled
              dense
              placeholder="Vorlage suchen..."
              class="q-mb-md"
            >
              <template v-slot:prepend
                ><q-icon name="search" color="grey-5"
              /></template>
            </q-input>
            <div class="row q-gutter-md q-mb-md">
              <q-select
                v-model="selectedCustomer"
                filled
                dense
                label="Kunde (optional)"
                :options="customerOptions"
                option-value="value"
                option-label="label"
                emit-value
                map-options
                clearable
                class="col"
                use-input
                @filter="filterCustomers"
              >
                <template v-slot:prepend
                  ><q-icon name="person" color="grey-5"
                /></template>
              </q-select>
              <q-input
                v-model="address"
                filled
                dense
                label="Projektadresse (optional)"
                class="col"
              >
                <template v-slot:prepend
                  ><q-icon name="location_on" color="grey-5"
                /></template>
              </q-input>
            </div>
            <div v-if="templatesLoading" class="flex flex-center q-pa-lg">
              <q-spinner color="teal" size="30px" />
            </div>
            <div
              v-else-if="filteredTemplates.length === 0"
              class="text-center q-pa-lg"
              style="color: #94a3b8"
            >
              {{
                templateSearch
                  ? "Keine Vorlagen gefunden"
                  : "Noch keine Vorlagen erstellt"
              }}<br />
              <q-btn
                flat
                color="teal"
                label="Vorlage erstellen"
                icon="add"
                no-caps
                class="q-mt-sm"
                @click="$router.push('/vorlagen')"
              />
            </div>
            <div v-else class="q-gutter-sm">
              <q-card
                v-for="tpl in filteredTemplates"
                :key="tpl.id"
                flat
                clickable
                @click="onCreateFromTemplate(tpl)"
                style="
                  border: 1px solid #e2e8f0;
                  border-radius: 12px;
                  cursor: pointer;
                  transition: all 0.15s;
                "
                class="hover-card"
              >
                <q-card-section class="q-py-md">
                  <div class="row items-center">
                    <q-avatar
                      size="40px"
                      color="teal-1"
                      text-color="teal"
                      icon="content_paste"
                      class="q-mr-md"
                    />
                    <div class="col">
                      <div
                        style="
                          font-size: 15px;
                          font-weight: 600;
                          color: #0f172a;
                        "
                      >
                        {{ tpl.name }}
                      </div>
                      <div style="font-size: 12px; color: #64748b">
                        <q-badge
                          v-if="tpl.category"
                          :label="tpl.category"
                          dense
                          color="grey-3"
                          text-color="grey-8"
                          class="q-mr-xs"
                        />
                        {{ tpl.items_count }} Positionen ·
                        {{ tpl.usage_count }}× verwendet
                      </div>
                    </div>
                    <q-icon name="arrow_forward" color="teal" size="20px" />
                  </div>
                </q-card-section>
              </q-card>
            </div>
          </q-card-section>
        </q-card>

        <!-- Leeres Angebot Modus -->
        <q-card
          v-if="createMode === 'empty'"
          flat
          style="
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
          "
        >
          <q-card-section class="q-pa-lg">
            <q-input
              v-model="emptyTitle"
              filled
              label="Projekttitel *"
              class="q-mb-md"
              placeholder="z.B. Badsanierung Müller"
            />
            <div class="row q-gutter-md q-mb-md">
              <q-select
                v-model="selectedCustomer"
                filled
                label="Kunde (optional)"
                :options="customerOptions"
                option-value="value"
                option-label="label"
                emit-value
                map-options
                clearable
                class="col"
                use-input
                @filter="filterCustomers"
              >
                <template v-slot:prepend
                  ><q-icon name="person" color="grey-5"
                /></template>
              </q-select>
              <q-input
                v-model="address"
                filled
                label="Projektadresse (optional)"
                class="col"
              >
                <template v-slot:prepend
                  ><q-icon name="location_on" color="grey-5"
                /></template>
              </q-input>
            </div>
            <q-btn
              color="indigo"
              icon="add"
              label="Leeres Angebot erstellen"
              class="full-width"
              size="lg"
              no-caps
              @click="onCreateEmpty"
              style="border-radius: 10px; font-weight: 600"
            />
          </q-card-section>
        </q-card>

        <!-- PDF Import Modus -->
        <q-card
          v-if="createMode === 'pdf'"
          flat
          style="
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
          "
        >
          <q-card-section class="q-pa-lg">
            <!-- ── SCAN LÄUFT ── -->
            <div v-if="scanProcessing" class="text-center q-py-xl">
              <!-- Animierter Kreis mit PDF Icon -->
              <div
                style="
                  position: relative;
                  display: inline-block;
                  margin-bottom: 24px;
                "
              >
                <q-circular-progress
                  indeterminate
                  size="90px"
                  :thickness="0.12"
                  color="red-7"
                  track-color="red-1"
                />
                <q-icon
                  name="picture_as_pdf"
                  color="red-7"
                  size="36px"
                  style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                  "
                />
              </div>

              <h6
                class="q-my-none"
                style="font-weight: 700; color: #0f172a; font-size: 18px"
              >
                Scan wird verarbeitet...
              </h6>
              <p style="color: #64748b; margin-top: 8px; font-size: 14px">
                {{ scanStatusMessage }}
              </p>

              <!-- Schritt-Anzeige -->
              <div
                class="q-mt-lg q-mx-auto"
                style="max-width: 380px; text-align: left"
              >
                <div
                  v-for="(step, i) in scanSteps"
                  :key="i"
                  class="row items-center q-gutter-sm q-mb-sm"
                  :style="i <= scanCurrentStep ? 'opacity: 1' : 'opacity: 0.3'"
                >
                  <q-icon
                    :name="
                      i < scanCurrentStep
                        ? 'check_circle'
                        : i === scanCurrentStep
                          ? 'radio_button_checked'
                          : 'radio_button_unchecked'
                    "
                    :color="
                      i < scanCurrentStep
                        ? 'positive'
                        : i === scanCurrentStep
                          ? 'red-7'
                          : 'grey-4'
                    "
                    size="20px"
                  />
                  <span style="font-size: 13px; color: #475569">{{
                    step
                  }}</span>
                  <q-spinner
                    v-if="i === scanCurrentStep"
                    size="14px"
                    color="red-7"
                    class="q-ml-xs"
                  />
                </div>
              </div>

              <q-linear-progress
                :value="scanProgress"
                color="red-7"
                track-color="red-1"
                rounded
                class="q-mt-lg q-mx-auto"
                style="height: 8px; max-width: 380px"
                animation-speed="1000"
              />

              <p class="q-mt-md" style="font-size: 12px; color: #94a3b8">
                ⏱ Große Dokumente (60+ Seiten) dauern 3–5 Minuten
              </p>
            </div>

            <!-- ── SCAN FERTIG ── -->
            <div v-else-if="scanDone" class="text-center q-py-lg">
              <div style="margin-bottom: 16px">
                <q-icon name="check_circle" color="positive" size="72px" />
              </div>
              <h6
                class="q-my-none"
                style="font-weight: 700; color: #0f172a; font-size: 20px"
              >
                Angebot fertig! 🎉
              </h6>
              <p style="color: #64748b; font-size: 14px; margin-top: 8px">
                <strong>{{ scanPositionsCount }} Positionen</strong> wurden
                importiert und mit KI-Preisen versehen.
              </p>

              <q-banner
                rounded
                class="q-mt-md text-left"
                style="
                  background: #fef3c7;
                  border: 1px solid #fcd34d;
                  border-radius: 12px;
                "
              >
                <template v-slot:avatar
                  ><q-icon name="warning" color="amber-7"
                /></template>
                <div style="font-size: 13px; color: #92400e; font-weight: 600">
                  Bitte Preise prüfen und anpassen
                </div>
                <div
                  style="
                    font-size: 12px;
                    color: #b45309;
                    margin-top: 4px;
                    line-height: 1.5;
                  "
                >
                  Positionen mit ⚠ haben KI-Schätzpreise – das sind Richtwerte.
                  Bitte vor dem Versenden prüfen und ggf. anpassen. Positionen
                  aus deinem Katalog ✓ sind bereits mit deinen Preisen
                  hinterlegt.
                </div>
              </q-banner>

              <q-btn
                color="primary"
                icon="edit"
                label="Angebot jetzt bearbeiten"
                no-caps
                class="q-mt-lg full-width"
                size="lg"
                style="border-radius: 10px; font-weight: 600"
                @click="$router.push(`/quotes/${scanQuoteId}`)"
              />
              <q-btn
                flat
                color="grey-7"
                label="Weiteres PDF importieren"
                no-caps
                class="q-mt-sm full-width"
                @click="resetScan"
              />
            </div>

            <!-- ── SCAN FEHLGESCHLAGEN ── -->
            <div v-else-if="scanFailed" class="text-center q-py-lg">
              <q-icon name="error_outline" color="negative" size="72px" />
              <h6 class="q-my-sm" style="font-weight: 700; color: #0f172a">
                Import fehlgeschlagen
              </h6>
              <p style="color: #64748b; font-size: 14px">
                {{ scanErrorMessage }}
              </p>
              <q-btn
                color="primary"
                label="Nochmal versuchen"
                no-caps
                class="q-mt-md"
                icon="refresh"
                @click="resetScan"
                style="border-radius: 10px"
              />
            </div>

            <!-- ── UPLOAD FORMULAR ── -->
            <div v-else>
              <!-- Kunde + Adresse -->
              <div class="row q-gutter-md q-mb-md">
                <q-select
                  v-model="selectedCustomer"
                  filled
                  dense
                  label="Kunde (optional)"
                  :options="customerOptions"
                  option-value="value"
                  option-label="label"
                  emit-value
                  map-options
                  clearable
                  class="col"
                  use-input
                  @filter="filterCustomers"
                >
                  <template v-slot:prepend
                    ><q-icon name="person" color="grey-5"
                  /></template>
                </q-select>
                <q-input
                  v-model="address"
                  filled
                  dense
                  label="Projektadresse (optional)"
                  class="col"
                >
                  <template v-slot:prepend
                    ><q-icon name="location_on" color="grey-5"
                  /></template>
                </q-input>
              </div>

              <!-- Upload Zone -->
              <div
                class="text-center q-pa-xl"
                style="
                  border: 2px dashed #e2e8f0;
                  border-radius: 12px;
                  cursor: pointer;
                  transition: all 0.2s;
                "
                :style="
                  pdfFile
                    ? 'border-color: #16a34a; background: #f0fdf4;'
                    : 'background: #f8fafc;'
                "
                @click="$refs.pdfInput.click()"
                @dragover.prevent
                @drop.prevent="onPdfDrop"
              >
                <input
                  ref="pdfInput"
                  type="file"
                  accept=".pdf"
                  style="display: none"
                  @change="onPdfSelect"
                />
                <q-icon
                  :name="pdfFile ? 'check_circle' : 'upload_file'"
                  :color="pdfFile ? 'positive' : 'grey-4'"
                  size="48px"
                />
                <div
                  class="q-mt-sm"
                  style="font-weight: 600; color: #0f172a; font-size: 15px"
                >
                  {{ pdfFile ? pdfFile.name : "PDF hier ablegen oder klicken" }}
                </div>
                <div style="font-size: 12px; color: #94a3b8; margin-top: 4px">
                  {{
                    pdfFile
                      ? `${(pdfFile.size / 1024 / 1024).toFixed(1)} MB · Klicken um anderes PDF zu wählen`
                      : "Fremdes Angebot als PDF · Max. 50 MB · Text-PDF & Scan-PDF werden unterstützt"
                  }}
                </div>
              </div>

              <!-- Info-Banner wenn Datei ausgewählt -->
              <div v-if="pdfFile" class="q-mt-md q-gutter-sm">
                <q-banner
                  rounded
                  style="
                    background: #eff6ff;
                    border: 1px solid #bfdbfe;
                    border-radius: 10px;
                  "
                >
                  <template v-slot:avatar
                    ><q-icon name="auto_awesome" color="primary"
                  /></template>
                  <div
                    style="font-size: 13px; color: #1e40af; font-weight: 600"
                  >
                    KI analysiert das PDF automatisch
                  </div>
                  <div style="font-size: 12px; color: #3b82f6; margin-top: 2px">
                    Positionen werden mit deinen Katalogpreisen abgeglichen.
                    Fehlende Preise schätzt die KI anhand deutscher Marktpreise.
                  </div>
                </q-banner>

                <q-banner
                  rounded
                  style="
                    background: #fef3c7;
                    border: 1px solid #fcd34d;
                    border-radius: 10px;
                  "
                >
                  <template v-slot:avatar
                    ><q-icon name="schedule" color="amber-7"
                  /></template>
                  <div
                    style="font-size: 13px; color: #92400e; font-weight: 600"
                  >
                    Scan-PDFs werden im Hintergrund verarbeitet
                  </div>
                  <div style="font-size: 12px; color: #b45309; margin-top: 2px">
                    Bei gescannten Dokumenten (60+ Seiten) dauert die
                    Verarbeitung 3–5 Minuten. Du siehst den Fortschritt live auf
                    dieser Seite.
                  </div>
                </q-banner>
              </div>

              <q-btn
                color="red-7"
                icon="picture_as_pdf"
                label="Angebot importieren"
                class="full-width q-mt-md"
                size="lg"
                no-caps
                :loading="importingPdf"
                :disable="!pdfFile"
                @click="onImportPdf"
                style="border-radius: 10px; font-weight: 600"
              />
            </div>
          </q-card-section>
        </q-card>

        <!-- Beispiele nur bei KI -->
        <div v-if="createMode === 'ai'" class="q-mt-lg">
          <p
            style="
              color: #64748b;
              font-size: 11px;
              font-weight: 600;
              text-transform: uppercase;
              letter-spacing: 0.05em;
            "
            class="q-mb-sm"
          >
            Beispiele zum Ausprobieren
          </p>
          <q-card
            v-for="(example, i) in examples"
            :key="i"
            flat
            class="q-mb-sm cursor-pointer"
            style="
              border: 1px solid #e2e8f0;
              border-radius: 10px;
              background: #ffffff;
            "
            @click="description = example"
          >
            <q-card-section class="q-py-sm q-px-md">
              <p class="q-my-none" style="font-size: 13px; color: #475569">
                {{ example }}
              </p>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>

    <!-- Schritt 2: Erstelltes Angebot anzeigen -->
    <div v-else>
      <div class="row items-start q-mb-lg q-gutter-md">
        <div class="col">
          <div class="row items-center q-gutter-sm q-mb-xs">
            <q-badge
              color="positive"
              :label="
                createdFromTemplate
                  ? '✓ Aus Vorlage erstellt'
                  : '✓ KI-Angebot erstellt'
              "
            />
          </div>
          <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">
            {{ quoteStore.currentQuote?.project_title }}
          </h5>
          <p class="q-mb-none q-mt-xs" style="color: #64748b">
            {{ quoteStore.currentQuote?.quote_number }} ·
            {{ itemCount }} Positionen
          </p>
        </div>
        <div class="row q-gutter-sm">
          <q-btn
            outline
            color="grey-7"
            icon="refresh"
            label="Neu erstellen"
            no-caps
            @click="resetForm"
          />
          <q-btn
            color="primary"
            icon="edit"
            label="Angebot bearbeiten"
            no-caps
            @click="$router.push(`/quotes/${quoteStore.currentQuote.id}`)"
          />
        </div>
      </div>

      <div class="row q-col-gutter-lg">
        <div class="col-12 col-md-8">
          <div
            v-for="(items, groupName) in groupedItems"
            :key="groupName"
            class="q-mb-md"
          >
            <p
              class="q-mb-sm"
              style="
                font-weight: 700;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
              "
            >
              {{ groupName }}
            </p>
            <q-card
              v-for="item in items"
              :key="item.id"
              flat
              class="q-mb-xs"
              style="
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                background: #ffffff;
              "
            >
              <q-card-section class="q-py-sm q-px-md">
                <div class="row items-center q-gutter-sm">
                  <div class="col">
                    <div
                      style="
                        font-size: 13.5px;
                        font-weight: 500;
                        color: #0f172a;
                      "
                    >
                      {{ item.title }}
                    </div>
                    <q-badge
                      :color="item.type === 'material' ? 'blue' : 'orange'"
                      :label="item.type === 'material' ? 'Material' : 'Arbeit'"
                      dense
                      class="q-mt-xs"
                      style="font-size: 10px"
                    />
                    <q-badge
                      v-if="item.material_id"
                      color="green-7"
                      label="Katalog"
                      dense
                      class="q-mt-xs q-ml-xs"
                      style="font-size: 10px"
                    />
                  </div>
                  <div
                    class="text-center"
                    style="min-width: 60px; font-size: 13px; color: #475569"
                  >
                    {{ item.quantity }} {{ item.unit }}
                  </div>
                  <div
                    class="text-right"
                    style="min-width: 80px; font-size: 13px; color: #475569"
                  >
                    {{ formatPrice(item.unit_price) }} €
                  </div>
                  <div
                    class="text-right"
                    style="
                      min-width: 90px;
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
        </div>

        <div class="col-12 col-md-4">
          <q-card
            flat
            style="
              border: 1px solid #dbeafe;
              border-radius: 16px;
              background: #ffffff;
              position: sticky;
              top: 80px;
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
                  <span style="color: #64748b">Material</span>
                  <span style="font-weight: 600; color: #0f172a"
                    >{{
                      formatPrice(quoteStore.currentQuote?.subtotal_materials)
                    }}
                    €</span
                  >
                </div>
                <div class="row justify-between">
                  <span style="color: #64748b">Arbeitsleistung</span>
                  <span style="font-weight: 600; color: #0f172a"
                    >{{
                      formatPrice(quoteStore.currentQuote?.subtotal_labor)
                    }}
                    €</span
                  >
                </div>
                <q-separator class="q-my-sm" />
                <div class="row justify-between">
                  <span style="color: #64748b">Netto</span>
                  <span style="font-weight: 600; color: #0f172a"
                    >{{
                      formatPrice(quoteStore.currentQuote?.subtotal_net)
                    }}
                    €</span
                  >
                </div>
                <div class="row justify-between">
                  <span style="color: #64748b"
                    >MwSt ({{ quoteStore.currentQuote?.vat_rate }}%)</span
                  >
                  <span style="color: #94a3b8"
                    >{{
                      formatPrice(quoteStore.currentQuote?.vat_amount)
                    }}
                    €</span
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
                    >{{
                      formatPrice(quoteStore.currentQuote?.total_gross)
                    }}
                    €</span
                  >
                </div>
              </div>
            </q-card-section>
          </q-card>

          <q-card
            v-if="aiNotes && !createdFromTemplate"
            flat
            class="q-mt-md"
            style="
              border: 1px solid #fef3c7;
              border-radius: 12px;
              background: #fffbeb;
            "
          >
            <q-card-section class="q-pa-md">
              <p
                class="q-mb-xs"
                style="font-weight: 700; font-size: 12px; color: #b45309"
              >
                KI-Hinweis
              </p>
              <p
                class="q-mb-none"
                style="font-size: 12px; line-height: 1.5; color: #78716c"
              >
                {{ aiNotes }}
              </p>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useQuoteStore } from "src/stores/quotes";
import { useQuasar } from "quasar";
import { api } from "src/boot/axios";

export default {
  name: "QuoteCreatePage",
  setup() {
    const router = useRouter();
    const quoteStore = useQuoteStore();
    const $q = useQuasar();

    // Mode
    const createMode = ref("ai");
    const createdFromTemplate = ref(false);

    // Shared fields
    const description = ref("");
    const address = ref("");
    const selectedCustomer = ref(null);
    const allCustomers = ref([]);
    const customerOptions = ref([]);
    const quoteCreated = ref(false);

    // AI state
    const generating = ref(false);
    const progress = ref(0);
    const currentStep = ref(0);
    const aiNotes = ref("");
    const generatingSteps = [
      "Projektbeschreibung wird analysiert...",
      "Gewerke werden identifiziert...",
      "Materialien aus Katalog werden gesucht...",
      "Arbeitszeiten werden geschätzt...",
      "Angebot wird zusammengestellt...",
    ];
    const examples = [
      "Badezimmer komplett sanieren, ca. 8m², alte Badewanne raus, neue bodengleiche Dusche rein, neues WC und Waschtisch. Alles moderne Sanitärobjekte.",
      "Heizungsanlage erneuern, Altbau 140m², alte Ölheizung raus, neue Gasbrennwerttherme, 8 Heizkörper austauschen",
      "Gäste-WC neu einrichten, 3m², Handwaschbecken, Wand-WC, neue Leitungen verlegen",
    ];

    // Template state
    const templateSearch = ref("");
    const allTemplates = ref([]);
    const templatesLoading = ref(false);

    // Empty state
    const emptyTitle = ref("");

    // PDF Import State
    const pdfFile = ref(null);
    const importingPdf = ref(false);
    const pdfInput = ref(null);

    // Scan Polling State
    const scanProcessing = ref(false);
    const scanDone = ref(false);
    const scanFailed = ref(false);
    const scanQuoteId = ref(null);
    const scanStatusMessage = ref("PDF wird hochgeladen...");
    const scanErrorMessage = ref("");
    const scanPositionsCount = ref(0);
    const scanProgress = ref(0);
    const scanCurrentStep = ref(0);
    const scanSteps = [
      "PDF wird erkannt und analysiert",
      "Seiten werden in Bilder konvertiert",
      "KI liest alle Positionen (Scan-Vision)",
      "Katalog-Matching & Preisschätzung",
      "Angebot wird fertiggestellt",
    ];
    let scanPollInterval = null;
    let scanStepInterval = null;

    // Load data
    const loadCustomers = async () => {
      try {
        const r = await api.get("/customers");
        allCustomers.value = r.data.data || r.data;
        customerOptions.value = allCustomers.value.map((c) => ({
          label:
            c.type === "business"
              ? c.company_name
              : c.first_name + " " + c.last_name,
          value: c.id,
        }));
      } catch (e) {
        console.error(e);
      }
    };

    const loadTemplates = async () => {
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

    onMounted(() => {
      loadCustomers();
      loadTemplates();
    });

    onUnmounted(() => {
      if (scanPollInterval) clearInterval(scanPollInterval);
      if (scanStepInterval) clearInterval(scanStepInterval);
    });

    const filterCustomers = (val, update) => {
      update(() => {
        const list = allCustomers.value.map((c) => ({
          label:
            c.type === "business"
              ? c.company_name
              : c.first_name + " " + c.last_name,
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

    const filteredTemplates = computed(() => {
      if (!templateSearch.value) return allTemplates.value;
      const s = templateSearch.value.toLowerCase();
      return allTemplates.value.filter(
        (t) =>
          t.name.toLowerCase().includes(s) ||
          (t.category || "").toLowerCase().includes(s),
      );
    });

    const itemCount = computed(
      () => quoteStore.currentQuote?.items?.length || 0,
    );

    const groupedItems = computed(() => {
      if (!quoteStore.currentQuote?.items) return {};
      const g = {};
      quoteStore.currentQuote.items.forEach((i) => {
        const gr = i.group_name || "Sonstiges";
        if (!g[gr]) g[gr] = [];
        g[gr].push(i);
      });
      return g;
    });

    const formatPrice = (val) =>
      Number(val || 0).toLocaleString("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });

    // === KI generieren ===
    const onGenerate = async () => {
      generating.value = true;
      progress.value = 0;
      currentStep.value = 0;
      createdFromTemplate.value = false;

      const si = setInterval(() => {
        if (currentStep.value < generatingSteps.length - 1) currentStep.value++;
      }, 2000);
      const pi = setInterval(() => {
        if (progress.value < 0.9) progress.value += 0.02;
      }, 200);

      try {
        const r = await quoteStore.createQuote({
          description: description.value,
          address: address.value,
          customer_id: selectedCustomer.value,
        });
        aiNotes.value = r.ai_notes || "";
        progress.value = 1;
        setTimeout(() => {
          generating.value = false;
          quoteCreated.value = true;
        }, 500);
      } catch (e) {
        generating.value = false;
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler bei der KI-Erstellung",
        });
      } finally {
        clearInterval(si);
        clearInterval(pi);
      }
    };

    // === Aus Vorlage erstellen ===
    const onCreateFromTemplate = async (tpl) => {
      try {
        const res = await api.post("/quotes", {
          project_description: tpl.name,
          customer_id: selectedCustomer.value || null,
          project_address: address.value || null,
          use_ai: false,
        });
        const quote = res.data.quote || res.data;
        quoteStore.currentQuote = quote;
        await api.post(`/service-templates/${tpl.id}/apply/${quote.id}`);
        await api.put(`/quotes/${quote.id}`, { project_title: tpl.name });
        await quoteStore.fetchQuote(quote.id);
        createdFromTemplate.value = true;
        quoteCreated.value = true;
        $q.notify({
          type: "positive",
          message: `Angebot aus Vorlage "${tpl.name}" erstellt!`,
        });
      } catch (e) {
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim Erstellen",
        });
      }
    };

    // === Leeres Angebot ===
    const onCreateEmpty = async () => {
      if (!emptyTitle.value) {
        $q.notify({ type: "warning", message: "Bitte Projekttitel eingeben" });
        return;
      }
      try {
        const res = await api.post("/quotes", {
          project_description: emptyTitle.value,
          customer_id: selectedCustomer.value || null,
          project_address: address.value || null,
          use_ai: false,
        });
        const quote = res.data.quote || res.data;
        await api.put(`/quotes/${quote.id}`, {
          project_title: emptyTitle.value,
        });
        $q.notify({ type: "positive", message: "Leeres Angebot erstellt" });
        router.push(`/quotes/${quote.id}`);
      } catch (e) {
        $q.notify({ type: "negative", message: "Fehler beim Erstellen" });
      }
    };

    const resetForm = () => {
      quoteCreated.value = false;
      description.value = "";
      address.value = "";
      emptyTitle.value = "";
      selectedCustomer.value = null;
      quoteStore.currentQuote = null;
      createdFromTemplate.value = false;
    };

    // === PDF Import ===
    const resetScan = () => {
      scanProcessing.value = false;
      scanDone.value = false;
      scanFailed.value = false;
      scanQuoteId.value = null;
      scanStatusMessage.value = "PDF wird hochgeladen...";
      scanErrorMessage.value = "";
      scanPositionsCount.value = 0;
      scanProgress.value = 0;
      scanCurrentStep.value = 0;
      pdfFile.value = null;
      if (scanPollInterval) {
        clearInterval(scanPollInterval);
        scanPollInterval = null;
      }
      if (scanStepInterval) {
        clearInterval(scanStepInterval);
        scanStepInterval = null;
      }
    };

    const startScanPolling = (quoteId) => {
      scanCurrentStep.value = 1;
      scanProgress.value = 0.2;

      const statusMessages = [
        "Scan-PDF erkannt – KI liest alle Seiten...",
        "Seiten werden als Bilder konvertiert...",
        "KI analysiert die Inhalte Seite für Seite...",
        "Katalogpreise werden abgeglichen...",
        "Angebot wird fertiggestellt...",
      ];

      // Schritte alle 30 Sek weiterschaltenähe
      scanStepInterval = setInterval(() => {
        if (scanCurrentStep.value < scanSteps.length - 1) {
          scanCurrentStep.value++;
          scanProgress.value = Math.min(
            (scanCurrentStep.value + 1) / scanSteps.length,
            0.9,
          );
          scanStatusMessage.value =
            statusMessages[scanCurrentStep.value] ||
            statusMessages[statusMessages.length - 1];
        }
      }, 30000);

      // Alle 5 Sek Status abfragen
      scanPollInterval = setInterval(async () => {
        try {
          const res = await api.get(`/quotes/${quoteId}/scan-status`);
          const status = res.data.status;

          if (status === "done") {
            clearInterval(scanPollInterval);
            clearInterval(scanStepInterval);
            scanPollInterval = null;
            scanStepInterval = null;
            scanProgress.value = 1;
            scanCurrentStep.value = scanSteps.length - 1;

            const quote = res.data.quote;
            scanPositionsCount.value = quote?.items?.length || 0;
            quoteStore.currentQuote = quote;

            setTimeout(() => {
              scanProcessing.value = false;
              scanDone.value = true;
            }, 600);
          } else if (status === "failed") {
            clearInterval(scanPollInterval);
            clearInterval(scanStepInterval);
            scanPollInterval = null;
            scanStepInterval = null;
            scanProcessing.value = false;
            scanFailed.value = true;
            scanErrorMessage.value =
              res.data.message || "Unbekannter Fehler beim Verarbeiten.";
          }
        } catch (e) {
          console.error("Polling Fehler:", e);
        }
      }, 5000);
    };

    const onPdfSelect = (event) => {
      const file = event.target.files[0];
      if (file && file.type === "application/pdf") {
        pdfFile.value = file;
      } else {
        $q.notify({
          type: "warning",
          message: "Bitte eine PDF-Datei auswählen",
        });
      }
    };

    const onPdfDrop = (event) => {
      const file = event.dataTransfer.files[0];
      if (file && file.type === "application/pdf") {
        pdfFile.value = file;
      } else {
        $q.notify({
          type: "warning",
          message: "Bitte eine PDF-Datei auswählen",
        });
      }
    };

    const onImportPdf = async () => {
      if (!pdfFile.value) return;
      importingPdf.value = true;

      try {
        const formData = new FormData();
        formData.append("pdf", pdfFile.value);
        if (selectedCustomer.value)
          formData.append("customer_id", selectedCustomer.value);
        if (address.value) formData.append("project_address", address.value);

        const res = await api.post("/quotes/import-pdf", formData, {
          headers: { "Content-Type": "multipart/form-data" },
        });

        const quote = res.data.quote;
        const isScan = res.data.is_scan === true || res.status === 202;

        if (isScan) {
          // Scan-PDF → Hintergrundverarbeitung mit Polling
          scanQuoteId.value = quote.id;
          importingPdf.value = false;
          scanProcessing.value = true;
          scanStatusMessage.value =
            "Scan-PDF erkannt – KI liest alle Seiten...";
          startScanPolling(quote.id);
        } else {
          // Normales Text-PDF → sofort fertig
          quoteStore.currentQuote = quote;
          importingPdf.value = false;
          $q.notify({
            type: "positive",
            message: `${res.data.positions_count} Positionen importiert!`,
          });
          quoteCreated.value = true;
        }
      } catch (e) {
        importingPdf.value = false;
        $q.notify({
          type: "negative",
          message: e.response?.data?.message || "Fehler beim PDF-Import",
        });
      }
    };

    return {
      quoteStore,
      createMode,
      createdFromTemplate,
      description,
      address,
      selectedCustomer,
      customerOptions,
      quoteCreated,
      generating,
      progress,
      currentStep,
      generatingSteps,
      examples,
      aiNotes,
      templateSearch,
      allTemplates,
      templatesLoading,
      filteredTemplates,
      emptyTitle,
      itemCount,
      groupedItems,
      formatPrice,
      filterCustomers,
      onGenerate,
      onCreateFromTemplate,
      onCreateEmpty,
      resetForm,
      pdfFile,
      importingPdf,
      pdfInput,
      scanProcessing,
      scanDone,
      scanFailed,
      scanQuoteId,
      scanStatusMessage,
      scanErrorMessage,
      scanPositionsCount,
      scanProgress,
      scanCurrentStep,
      scanSteps,
      resetScan,
      onPdfSelect,
      onPdfDrop,
      onImportPdf,
    };
  },
};
</script>
