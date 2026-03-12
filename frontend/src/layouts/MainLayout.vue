<template>
  <q-layout view="lHh Lpr lff">
    <q-drawer v-model="leftDrawerOpen" show-if-above bordered :width="220"
      style="background: #ffffff; border-right: 1px solid #e2e8f0">
      <div style="
          padding: 16px 8px;
          border-bottom: 1px solid #e2e8f0;
          display: flex;
          align-items: center;
          gap: 10px;
        ">

        <div>
          <img src="~assets/angebotspilot-logo.png" alt="AngebotsPilot" style="height: 78px; width: auto;" />
          <div class="text-grey-6" style="font-size: 11px;">SHK Edition</div>
        </div>
      </div>
      <q-list padding style="padding: 8px 6px">
        <q-item v-for="item in menuItems" :key="item.to" :to="item.to" clickable :active="$route.path === item.to ||
          ($route.path.startsWith(item.to + '/') &&
            item.to !== '/quotes/create')
          " active-class="menu-active" style="border-radius: 8px; margin-bottom: 2px; min-height: 40px">
          <q-item-section avatar style="min-width: 36px">
            <q-icon :name="item.icon" :color="isActive(item.to) ? 'primary' : 'grey-6'" size="20px" />
          </q-item-section>
          <q-item-section>
            <q-item-label
              :style="`font-size: 13px; font-weight: ${isActive(item.to) ? '600' : '400'}; color: ${isActive(item.to) ? '#1d4ed8' : '#4b5563'};`">
              {{ item.label }}
            </q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
      <q-space />
      <div style="
          padding: 12px 14px;
          border-top: 1px solid #e2e8f0;
          display: flex;
          align-items: center;
          gap: 10px;
        ">
        <q-avatar size="32px" color="primary" text-color="white" style="font-weight: 700; font-size: 12px">{{
          userInitials }}</q-avatar>
        <div class="col">
          <div style="font-size: 12px; font-weight: 600; color: #0f172a">
            {{ authStore.userName }}
          </div>
          <div style="font-size: 10px; color: #94a3b8">
            {{ authStore.company?.name }}
          </div>
        </div>
        <q-btn flat round dense icon="logout" color="grey-5" size="sm"
          @click="onLogout"><q-tooltip>Abmelden</q-tooltip></q-btn>
      </div>
    </q-drawer>
    <q-page-container style="background: #f6f9fc">
      <router-view />
    </q-page-container>
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
export default {
  name: "MainLayout",
  setup() {
    const authStore = useAuthStore();
    const router = useRouter();
    const route = useRoute();
    const leftDrawerOpen = ref(true);
    const menuItems = [
      { icon: "dashboard", label: "Dashboard", to: "/dashboard" },
      { icon: "auto_awesome", label: "Neues Angebot", to: "/quotes/create" },
      { icon: "description", label: "Angebote", to: "/quotes" },
      { icon: "people", label: "Kunden", to: "/customers" },
      { icon: "inventory_2", label: "Materialkatalog", to: "/materials" },
      { icon: "content_paste", label: "Leistungsvorlagen", to: "/vorlagen" },
      { icon: "settings", label: "Einstellungen", to: "/settings" },
      {
        icon: "upload_file",
        label: "Datanorm Import",
        to: { name: "datanorm" },
      },
      { label: 'Rechnungen', icon: 'receipt_long', to: '/invoices' },
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
