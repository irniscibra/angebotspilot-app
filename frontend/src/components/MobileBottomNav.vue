<template>
  <div class="mobile-bottom-nav">
    <div class="mobile-bottom-nav__inner">
      <router-link
        v-for="item in mainItems"
        :key="item.to"
        :to="item.to"
        class="nav-item"
        :class="{ 'nav-item--active': isActive(item.to) }"
      >
        <q-icon :name="item.icon" size="22px" />
        <span class="nav-item__label">{{ item.label }}</span>
      </router-link>

      <!-- Zentraler "Neu"-Button, hervorgehoben -->
      <router-link to="/quotes/create" class="nav-item nav-item--fab">
        <div class="nav-item__fab-circle">
          <q-icon name="add" size="24px" color="white" />
        </div>
      </router-link>

      <router-link
        v-for="item in secondaryItems"
        :key="item.to"
        :to="item.to"
        class="nav-item"
        :class="{ 'nav-item--active': isActive(item.to) }"
      >
        <q-icon :name="item.icon" size="22px" />
        <span class="nav-item__label">{{ item.label }}</span>
      </router-link>

      <div
        class="nav-item"
        :class="{ 'nav-item--active': moreSheetOpen }"
        @click="moreSheetOpen = true"
      >
        <q-icon name="more_horiz" size="22px" />
        <span class="nav-item__label">Mehr</span>
      </div>
    </div>

    <!-- "Mehr"-Sheet -->
    <q-dialog v-model="moreSheetOpen" position="bottom">
      <q-card class="more-sheet">
        <div class="more-sheet__handle" />
        <div class="more-sheet__title">Mehr</div>
        <div class="more-sheet__grid">
          <router-link
            v-for="item in moreItems"
            :key="item.to"
            :to="item.to"
            class="more-sheet__item"
            @click="moreSheetOpen = false"
          >
            <div class="more-sheet__item-icon">
              <q-icon :name="item.icon" size="20px" color="#1d4ed8" />
            </div>
            <span class="more-sheet__item-label">{{ item.label }}</span>
          </router-link>
        </div>

        <div class="more-sheet__divider" />

        <div class="more-sheet__logout" @click="onLogout">
          <q-icon name="logout" size="18px" color="#ef4444" />
          <span>Abmelden</span>
        </div>
      </q-card>
    </q-dialog>
  </div>
</template>

<script>
import { ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "src/stores/auth";

export default {
  name: "MobileBottomNav",
  setup() {
    const route = useRoute();
    const router = useRouter();
    const authStore = useAuthStore();
    const moreSheetOpen = ref(false);

    const onLogout = async () => {
      moreSheetOpen.value = false;
      await authStore.logout();
      router.push("/auth/login");
    };

    const mainItems = [
      { label: "Home", icon: "dashboard", to: "/dashboard" },
      { label: "Angebote", icon: "description", to: "/quotes" },
    ];

    const secondaryItems = [
      { label: "Kunden", icon: "people", to: "/customers" },
    ];

    const moreItems = [
      { label: "Projekte", icon: "folder", to: "/projects" },
      { label: "Materialkatalog", icon: "inventory_2", to: "/materials" },
      { label: "Datanorm Import", icon: "upload_file", to: "/datanorm" },
      { label: "Rechnungen", icon: "receipt_long", to: "/invoices" },
      { label: "Protokolle", icon: "assignment_turned_in", to: "/protokolle" },
      { label: "Leistungsvorlagen", icon: "content_paste", to: "/vorlagen" },
      { label: "Einstellungen", icon: "settings", to: "/settings" },
    ];

    const isActive = (to) =>
      route.path === to || route.path.startsWith(to + "/");

    return {
      mainItems,
      secondaryItems,
      moreItems,
      moreSheetOpen,
      isActive,
        onLogout,
    };
  },
};
</script>

<style scoped>
.mobile-bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 2000;
  background: #ffffff;
  border-top: 1px solid #e2e8f0;
  box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.06);
  padding-bottom: env(safe-area-inset-bottom, 0px);
}

.mobile-bottom-nav__inner {
  display: flex;
  align-items: center;
  justify-content: space-around;
  height: 58px;
  max-width: 480px;
  margin: 0 auto;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  flex: 1;
  height: 100%;
  color: #94a3b8;
  text-decoration: none;
  cursor: pointer;
  transition: color 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}

.nav-item--active {
  color: #1d4ed8;
}

.nav-item__label {
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.01em;
}

/* Zentraler FAB-Button */
.nav-item--fab {
  flex: 0.9;
  position: relative;
  top: -14px;
}

.nav-item__fab-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 16px rgba(29, 78, 216, 0.4);
  transition: transform 0.15s ease;
}

.nav-item--fab:active .nav-item__fab-circle {
  transform: scale(0.92);
}

/* "Mehr"-Sheet */
.more-sheet {
  border-radius: 20px 20px 0 0;
  padding: 8px 20px 24px;
  width: 100%;
}

.more-sheet__handle {
  width: 36px;
  height: 4px;
  background: #e2e8f0;
  border-radius: 2px;
  margin: 6px auto 16px;
}

.more-sheet__title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 16px;
}

.more-sheet__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.more-sheet__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  -webkit-tap-highlight-color: transparent;
}

.more-sheet__item-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: #eff6ff;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.15s ease;
}

.more-sheet__item:active .more-sheet__item-icon {
  transform: scale(0.94);
}

.more-sheet__item-label {
  font-size: 11px;
  font-weight: 600;
  color: #475569;
  text-align: center;
  line-height: 1.3;
}

.more-sheet__divider {
  height: 1px;
  background: #e2e8f0;
  margin: 20px 0 8px;
}

.more-sheet__logout {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 4px;
  color: #ef4444;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
</style>