<template>
  <q-page class="q-pa-md q-pa-lg-lg" style="background: #f6f9fc">
    <div class="row items-center justify-between q-mb-md">
      <div>
        <h5 class="q-my-none" style="font-weight: 700; color: #0f172a">Team</h5>
        <div style="font-size: 13px; color: #64748b; margin-top: 2px">
          {{ team.seats.used }} von {{ team.seats.limit }} Mitarbeiter-Sitzplätzen belegt
        </div>
      </div>
      <q-btn
        unelevated
        no-caps
        dense
        icon="person_add"
        label="Einladen"
        style="background: #4f46e5; color: #ffffff; border-radius: 10px; font-weight: 600; padding: 8px 16px"
        @click="openInviteDrawer"
      />
    </div>

    <q-banner
      v-if="team.seats.used >= team.seats.limit"
      rounded
      class="q-mb-md"
      style="background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 13px"
    >
      <q-icon name="info" size="16px" class="q-mr-xs" />
      Alle Mitarbeiter-Sitzplätze sind belegt. Um weitere Mitarbeiter einzuladen, kontaktieren Sie uns für zusätzliche Sitzplätze.
    </q-banner>

    <div v-if="loading" class="flex flex-center q-pa-xl">
      <q-spinner-orbit color="primary" size="50px" />
    </div>

    <div v-else class="ap-list-card">
      <div v-for="member in team.members" :key="member.id" class="ap-list-row" style="cursor: default">
        <div
          class="ap-list-avatar"
          :style="{ background: roleBg(member.role), color: roleAccent(member.role) }"
        >
          {{ initials(member.name) }}
        </div>
        <div class="ap-list-main">
          <div class="ap-list-title">{{ member.name }}</div>
          <div class="ap-list-sub">{{ member.email }}</div>
        </div>
        <div class="ap-list-right">
          <div
            class="ap-list-status-pill"
            :style="{ color: roleAccent(member.role), background: roleBg(member.role) }"
          >
            {{ roleLabel(member.role) }}
          </div>
          <div
            class="ap-list-status-pill"
            :style="{
              color: member.status === 'aktiv' ? '#15803d' : '#b45309',
              background: member.status === 'aktiv' ? '#dcfce7' : '#fef3c7',
            }"
          >
            {{ member.status === "aktiv" ? "Aktiv" : "Eingeladen" }}
          </div>
        </div>
        <q-btn
          v-if="member.role !== 'owner'"
          flat
          round
          dense
          size="sm"
          icon="more_vert"
          color="grey-5"
          class="q-ml-sm"
        >
          <q-menu auto-close style="border-radius: 12px">
            <q-list style="min-width: 170px">
              <q-item clickable class="text-negative" @click="onRemove(member)">
                <q-item-section avatar
                  ><q-icon name="person_remove" size="18px" color="negative"
                /></q-item-section>
                <q-item-section>Entfernen</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </div>
    </div>

    <!-- Drawer: Team-Mitglied einladen -->
    <q-dialog v-model="inviteDialog.open" position="right" full-height maximized-on-mobile persistent>
      <q-card style="width: 460px; max-width: 95vw; display: flex; flex-direction: column">
        <q-card-section class="row items-center q-pb-sm" style="border-bottom: 1px solid #f1f5f9; flex-shrink: 0">
          <h6 class="q-my-none" style="font-weight: 600; color: #0f172a">Team-Mitglied einladen</h6>
          <q-space />
          <q-btn flat round dense icon="close" color="grey-5" v-close-popup />
        </q-card-section>
        <q-card-section class="q-gutter-sm" style="flex: 1; overflow-y: auto">
          <div>
            <div class="ap-field-label">
              <q-icon name="badge" size="16px" color="grey-5" class="q-mr-xs" />Name *
            </div>
            <div class="ap-input-box">
              <q-input v-model="inviteForm.name" borderless dense autofocus placeholder="Vor- und Nachname" />
            </div>
          </div>
          <div>
            <div class="ap-field-label">
              <q-icon name="mail" size="16px" color="grey-5" class="q-mr-xs" />E-Mail *
            </div>
            <div class="ap-input-box">
              <q-input v-model="inviteForm.email" borderless dense type="email" placeholder="name@beispiel.de" />
            </div>
          </div>
          <div>
            <div class="ap-field-label">
              <q-icon name="admin_panel_settings" size="16px" color="grey-5" class="q-mr-xs" />Rolle *
            </div>
            <div class="ap-input-box">
              <q-select
                v-model="inviteForm.role"
                borderless
                dense
                :options="roleOptions"
                option-value="value"
                option-label="label"
                emit-value
                map-options
              />
            </div>
          </div>
          <div style="font-size: 12px; color: #94a3b8; line-height: 1.6; padding: 4px 2px">
            <strong>Mitarbeiter</strong> sehen nur Projekte, denen sie zugewiesen wurden - keine Angebote, Rechnungen oder Kostendaten.
            <strong>Admin</strong> hat vollen Zugriff wie der Firmeninhaber, außer Abo-Verwaltung.
          </div>
          <q-banner
            v-if="error"
            rounded
            style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; font-size: 13px"
          >
            {{ error }}
          </q-banner>
        </q-card-section>
        <q-card-actions align="right" class="q-pa-md" style="border-top: 1px solid #f1f5f9; flex-shrink: 0">
          <q-btn flat label="Abbrechen" color="grey" v-close-popup />
          <q-btn
            label="Einladung senden"
            :loading="team.saving"
            @click="onInvite"
            style="background: #4f46e5; color: #ffffff; border-radius: 10px; font-weight: 600"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { computed, onMounted, reactive, ref } from "vue";
import { useQuasar } from "quasar";
import { useTeamStore } from "src/stores/team";

export default {
  name: "TeamPage",
  setup() {
    const $q = useQuasar();
    const team = useTeamStore();
    const loading = ref(true);
    const error = ref("");

    const inviteDialog = reactive({ open: false });
    const inviteForm = reactive({ name: "", email: "", role: "employee" });

    const roleOptions = [
      { label: "Mitarbeiter", value: "employee" },
      { label: "Admin", value: "admin" },
    ];

    const roleLabel = (role) =>
      role === "owner" ? "Inhaber" : role === "admin" ? "Admin" : "Mitarbeiter";

    const roleBg = (role) =>
      role === "owner" ? "#fef3c7" : role === "admin" ? "#eef2ff" : "#f0fdf4";
    const roleAccent = (role) =>
      role === "owner" ? "#b45309" : role === "admin" ? "#4f46e5" : "#15803d";

    const initials = (name) =>
      (name || "")
        .split(" ")
        .map((w) => w[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);

    const load = async () => {
      loading.value = true;
      await team.fetchTeam();
      loading.value = false;
    };

    onMounted(load);

    const openInviteDrawer = () => {
      inviteForm.name = "";
      inviteForm.email = "";
      inviteForm.role = "employee";
      error.value = "";
      inviteDialog.open = true;
    };

    const onInvite = async () => {
      if (!inviteForm.name || !inviteForm.email) {
        error.value = "Name und E-Mail sind Pflichtfelder.";
        return;
      }
      error.value = "";
      try {
        await team.invite({ ...inviteForm });
        inviteDialog.open = false;
        $q.notify({ type: "positive", message: "Einladung wurde verschickt." });
      } catch (e) {
        error.value = e.response?.data?.message || "Einladung fehlgeschlagen.";
      }
    };

    const onRemove = async (member) => {
      $q.dialog({
        title: "Team-Mitglied entfernen",
        message: `${member.name} wirklich aus dem Team entfernen? Zugewiesene Projekte gehen dabei verloren.`,
        cancel: true,
        persistent: true,
      }).onOk(async () => {
        try {
          await team.remove(member.id);
          $q.notify({ type: "positive", message: "Entfernt." });
        } catch (e) {
          $q.notify({
            type: "negative",
            message: e.response?.data?.message || "Konnte nicht entfernt werden",
          });
        }
      });
    };

    return {
      team,
      loading,
      error,
      inviteDialog,
      inviteForm,
      roleOptions,
      roleLabel,
      roleBg,
      roleAccent,
      initials,
      openInviteDrawer,
      onInvite,
      onRemove,
    };
  },
};
</script>

<style scoped>
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
  font-size: 12px;
  font-weight: 700;
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
  gap: 4px;
  margin-right: 4px;
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
