<template>
  <q-layout view="lHh Lpr lff">
    <!-- NEU: Mobile Header mit Hamburger -->
<q-header elevated class="bg-white text-dark" style="border-bottom: 1px solid #e2e8f0;" v-if="$q.screen.lt.lg">
  <q-toolbar style="position: relative; min-height: 58px;">
    <q-btn flat round dense icon="menu" color="dark" @click="leftDrawerOpen = !leftDrawerOpen" />
    <div
      style="
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        pointer-events: none;
      "
    >
      <img
        src="~assets/angebotspilot-logo.png"
        alt="AngebotsPilot"
        style="height: 46px; width: auto;"
      />
    </div>
  </q-toolbar>
</q-header>
    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      bordered
      :width="220"
      style="background: #ffffff; border-right: 1px solid #e2e8f0"
    >
      <div
        style="
          padding: 16px 8px;
          border-bottom: 1px solid #e2e8f0;
          display: flex;
          align-items: center;
          gap: 10px;
        "
      >
        <div>
          <img
            src="~assets/angebotspilot-logo.png"
            alt="AngebotsPilot"
            style="height: 78px; width: auto"
          />
        </div>
      </div>
      <q-list padding style="padding: 8px 6px">
        <q-item
          v-for="item in menuItems"
          :key="item.to"
          :to="item.to"
          clickable
          :active="
            $route.path === item.to ||
            ($route.path.startsWith(item.to + '/') &&
              item.to !== '/quotes/create')
          "
          active-class="menu-active"
          style="border-radius: 8px; margin-bottom: 2px; min-height: 40px"
        >
          <q-item-section avatar style="min-width: 36px">
            <q-icon
              :name="item.icon"
              :color="isActive(item.to) ? 'primary' : 'grey-6'"
              size="20px"
            />
          </q-item-section>
          <q-item-section>
            <q-item-label
              :style="`font-size: 13px; font-weight: ${isActive(item.to) ? '600' : '400'}; color: ${isActive(item.to) ? '#1d4ed8' : '#4b5563'};`"
            >
              {{ item.label }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
      <q-space />
      <div
        style="
          padding: 12px 14px;
          border-top: 1px solid #e2e8f0;
          display: flex;
          align-items: center;
          gap: 10px;
        "
      >
        <q-avatar
          size="32px"
          color="primary"
          text-color="white"
          style="font-weight: 700; font-size: 12px"
          >{{ userInitials }}</q-avatar
        >
        <div class="col">
          <div style="font-size: 12px; font-weight: 600; color: #0f172a">
            {{ authStore.userName }}
          </div>
          <div style="font-size: 10px; color: #94a3b8">
            {{ authStore.company?.name }}
          </div>
        </div>
        <q-btn
          flat
          round
          dense
          icon="logout"
          color="grey-5"
          size="sm"
          @click="onLogout"
          ><q-tooltip>Abmelden</q-tooltip></q-btn
        >
      </div>
    </q-drawer>
    <!-- Trial Banner -->
    <div
      v-if="
        authStore.plan === 'trial' &&
        authStore.trialDaysLeft <= 5 &&
        authStore.trialDaysLeft > 0
      "
      :style="`background: ${authStore.trialDaysLeft <= 1 ? '#fef2f2' : authStore.trialDaysLeft <= 3 ? '#fffbeb' : '#eff6ff'}; border-bottom: 1px solid ${authStore.trialDaysLeft <= 1 ? '#fca5a5' : authStore.trialDaysLeft <= 3 ? '#fcd34d' : '#bfdbfe'}; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; font-size: 14px;`"
    >
      <span
        :style="`color: ${authStore.trialDaysLeft <= 1 ? '#dc2626' : authStore.trialDaysLeft <= 3 ? '#92400e' : '#1e40af'}; font-weight: 600;`"
      >
        ⏳ Noch {{ authStore.trialDaysLeft }}
        {{ authStore.trialDaysLeft === 1 ? "Tag" : "Tage" }} im kostenlosen Test
      </span>
      <router-link
        to="/upgrade"
        :style="`background: ${authStore.trialDaysLeft <= 3 ? '#dc2626' : '#1d4ed8'}; color: white; padding: 6px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600;`"
      >
        Jetzt upgraden →
      </router-link>
    </div>

    <q-page-container
      style="background: #f6f9fc"
      :style="$q.screen.lt.lg ? 'padding-bottom: 74px' : ''"
    >
      <router-view />
    </q-page-container>

    <MobileBottomNav v-if="$q.screen.lt.lg" />
  </q-layout>
</template>
<style>
.menu-active {
  background: #eff6ff !important;
}
</style>
<script>
import { ref, computed } from "vue";
import { useAuthStore } from "src/stores/auth";
import { useRouter, useRoute } from "vue-router";
import MobileBottomNav from "components/MobileBottomNav.vue";
export default {
  name: "MainLayout",
  components: {
    MobileBottomNav,
  },
  setup() {
    const authStore = useAuthStore();
    const router = useRouter();
    const route = useRoute();
    const leftDrawerOpen = ref(true);
    const menuItems = [
      { label: "Dashboard", icon: "dashboard", to: "/dashboard" },
      { label: "Neues Angebot", icon: "add_circle", to: "/quotes/create" },
      { label: "Angebote", icon: "description", to: "/quotes" },
      { label: "Rechnungen", icon: "receipt_long", to: "/invoices" },
      // { label: 'Mahnwesen', icon: 'warning', to: '/mahnwesen' },
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
    };
  },
};
</script>
