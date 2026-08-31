<template>
  <q-layout view="lHh Lpr lff">
    <!-- Mobile Header: nur Logo, Navigation läuft über Bottom-Nav -->
    <q-header
      elevated
      class="ap-mobile-header"
      v-if="$q.screen.lt.lg"
    >
      <q-toolbar style="min-height: 58px; justify-content: center">
        <img
          src="~assets/angebotspilot-logo.png"
          alt="AngebotsPilot"
          style="height: 42px; width: auto"
        />
      </q-toolbar>
    </q-header>

    <q-drawer
      v-if="!$q.screen.lt.lg"
      v-model="leftDrawerOpen"
      show-if-above
      :width="isDrawerExpanded ? 240 : 72"
      class="ap-drawer"
      :class="{ 'is-mini': !isDrawerExpanded }"
      @mouseenter="isDrawerExpanded = true"
      @mouseleave="isDrawerExpanded = false"
    >
      <div class="ap-drawer-inner">
        <!-- Logo -->
        <div class="ap-logo-area">
          <img
            v-if="isDrawerExpanded"
            src="~assets/angebotspilot-logo.png"
            alt="AngebotsPilot"
            style="height: 60px; width: auto"
          />
          <q-icon
            v-else
            name="verified"
            size="32px"
            color="primary"
          />
        </div>

        <!-- Navigation -->
        <q-list class="ap-nav-list">
          <router-link
            v-for="item in menuItems"
            :key="item.to"
            :to="item.to"
            class="ap-nav-item"
            :class="{ 'is-active': isActive(item.to) }"
          >
            <q-icon :name="item.icon" size="19px" class="ap-nav-icon" />
            <span v-if="isDrawerExpanded" class="ap-nav-label">{{
              item.label
            }}</span>
            <q-tooltip v-else anchor="center right" self="center left">
              {{ item.label }}
            </q-tooltip>
          </router-link>
        </q-list>

        <q-space />

        <!-- Feedback -->
        <div class="ap-footer-block">
          <button class="ap-feedback-row" @click="showFeedbackDialog = true">
            <q-icon name="chat_bubble_outline" size="17px" />
            <span v-if="isDrawerExpanded">Feedback geben</span>
            <q-tooltip v-else anchor="center right" self="center left">
              Feedback geben
            </q-tooltip>
          </button>
        </div>

        <!-- User -->
        <div class="ap-user-row">
          <div class="ap-user-avatar">{{ userInitials }}</div>
          <template v-if="isDrawerExpanded">
            <div class="ap-user-info">
              <div class="ap-user-name">{{ authStore.userName }}</div>
              <div class="ap-user-company">{{ authStore.company?.name }}</div>
            </div>
            <q-btn
              flat
              round
              dense
              icon="logout"
              size="sm"
              class="ap-logout-btn"
              @click="onLogout"
            >
              <q-tooltip>Abmelden</q-tooltip>
            </q-btn>
          </template>
        </div>
      </div>
    </q-drawer>

    <!-- Trial Banner -->
    <div
      v-if="
        authStore.plan === 'trial' &&
        authStore.trialDaysLeft <= 5 &&
        authStore.trialDaysLeft > 0
      "
      class="ap-trial-banner"
      :class="{
        'is-urgent': authStore.trialDaysLeft <= 1,
        'is-warning': authStore.trialDaysLeft > 1 && authStore.trialDaysLeft <= 3,
      }"
    >
      <span class="ap-trial-text">
        <q-icon name="schedule" size="15px" class="q-mr-xs" />
        Noch {{ authStore.trialDaysLeft }}
        {{ authStore.trialDaysLeft === 1 ? "Tag" : "Tage" }} im kostenlosen Test
      </span>
      <router-link to="/upgrade" class="ap-trial-cta">
        Jetzt upgraden →
      </router-link>
    </div>

    <q-page-container
      class="ap-page-container"
      :style="$q.screen.lt.lg ? 'padding-bottom: 74px' : ''"
    >
      <router-view />
    </q-page-container>

    <MobileBottomNav v-if="$q.screen.lt.lg" />

    <FeedbackDialog v-model="showFeedbackDialog" />
  </q-layout>
</template>

<script>
import { ref, computed } from "vue";
import { useAuthStore } from "src/stores/auth";
import { useRouter, useRoute } from "vue-router";
import MobileBottomNav from "components/MobileBottomNav.vue";
import FeedbackDialog from "components/FeedbackDialog.vue";

export default {
  name: "MainLayout",
  components: {
    MobileBottomNav,
    FeedbackDialog,
  },
  setup() {
    const authStore = useAuthStore();
    const router = useRouter();
    const route = useRoute();
    const leftDrawerOpen = ref(true);
    const showFeedbackDialog = ref(false);
    const isDrawerExpanded = ref(false);

    const menuItems = [
      { label: "Dashboard", icon: "dashboard", to: "/dashboard" },
      { label: "Neues Angebot", icon: "add_circle", to: "/quotes/create" },
      { label: "Angebote", icon: "description", to: "/quotes" },
      { label: "Rechnungen", icon: "receipt_long", to: "/invoices" },
      { label: "Protokolle", icon: "assignment_turned_in", to: "/protokolle" },
      { label: "Kunden", icon: "people", to: "/customers" },
      { label: "Materialkatalog", icon: "inventory_2", to: "/materials" },
      { label: "Leistungsvorlagen", icon: "content_paste", to: "/vorlagen" },
      { label: "Datanorm Import", icon: "upload_file", to: "/datanorm" },
      { label: "Einstellungen", icon: "settings", to: "/settings" },
    ];

    const isActive = (to) =>
      route.path === to ||
      (route.path.startsWith(to + "/") &&
        to !== "/quotes/create" &&
        to !== "/dashboard");

    const userInitials = computed(() => {
      const n = authStore.userName || "";
      return n
        .split(" ")
        .map((w) => w[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
    });

    const onLogout = async () => {
      await authStore.logout();
      router.push("/auth/login");
    };

    return {
      authStore,
      leftDrawerOpen,
      menuItems,
      userInitials,
      onLogout,
      isActive,
      showFeedbackDialog,
      isDrawerExpanded,
    };
  },
};
</script>

<style scoped>
.ap-mobile-header {
  background: #ffffff;
  color: #12121f;
  border-bottom: 1px solid #eceef4;
  box-shadow: none;
}

.ap-drawer {
  background: #ffffff;
  border-right: 1px solid #eceef4 !important;
  transition: width 0.18s ease;
  z-index: 3000;
}
.ap-drawer-inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px 14px 16px;
  overflow: hidden;
}
.ap-drawer.is-mini .ap-drawer-inner {
  padding-left: 10px;
  padding-right: 10px;
}
.ap-drawer.is-mini .ap-nav-item {
  justify-content: center;
  padding: 9px;
}
.ap-drawer.is-mini .ap-user-row {
  justify-content: center;
  padding: 12px 0 4px;
}

.ap-logo-area {
  padding: 4px 8px 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60px;
}

.ap-nav-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0;
}
.ap-nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 9px 12px;
  border-radius: 10px;
  color: #64748b;
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 500;
  transition: background 0.15s, color 0.15s;
}
.ap-nav-item:hover {
  background: #f4f5fa;
  color: #12121f;
}
.ap-nav-item.is-active {
  background: #eef0ff;
  color: #1976D2;
  font-weight: 600;
}
.ap-nav-icon {
  flex-shrink: 0;
}
.ap-nav-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ap-footer-block {
  padding-top: 8px;
  margin-top: 8px;
  border-top: 1px solid #eceef4;
}
.ap-feedback-row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: #8b90a3;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
}
.ap-feedback-row:hover {
  background: #f4f5fa;
  color: #1976D2;
}

.ap-user-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 8px 4px;
  margin-top: 4px;
}
.ap-user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: linear-gradient(135deg, #1976D2, #6366f1);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
}
.ap-user-info {
  flex: 1;
  min-width: 0;
}
.ap-user-name {
  font-size: 12.5px;
  font-weight: 600;
  color: #12121f;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ap-user-company {
  font-size: 11px;
  color: #a1a6b8;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ap-logout-btn {
  color: #c6cad9;
  flex-shrink: 0;
}

.ap-trial-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 20px;
  font-size: 13.5px;
  background: #eef0ff;
  border-bottom: 1px solid #d9ddfb;
}
.ap-trial-banner.is-warning {
  background: #fef3e2;
  border-bottom-color: #fbdca3;
}
.ap-trial-banner.is-urgent {
  background: #fdecec;
  border-bottom-color: #f5b8b8;
}
.ap-trial-text {
  color: #4338ca;
  font-weight: 600;
  display: flex;
  align-items: center;
}
.is-warning .ap-trial-text { color: #92400e; }
.is-urgent .ap-trial-text { color: #b91c1c; }

.ap-trial-cta {
  background: #1976D2;
  color: #fff;
  padding: 6px 16px;
  border-radius: 8px;
  text-decoration: none;
  font-size: 12.5px;
  font-weight: 600;
  flex-shrink: 0;
  transition: opacity 0.15s;
}
.ap-trial-cta:hover { opacity: 0.9; }
.is-urgent .ap-trial-cta { background: #dc2626; }

.ap-page-container {
  background: #f7f8fb;
}
</style>