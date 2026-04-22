import { route } from "quasar/wrappers";
import {
  createRouter,
  createMemoryHistory,
  createWebHistory,
  createWebHashHistory,
} from "vue-router";

const routes = [
      {
        path: '/angebot/:uuid',
        component: () => import('pages/PublicQuoteView.vue'),
        meta: { public: true }
    },
  {
    path: "/auth",
    component: () => import("layouts/AuthLayout.vue"),
    children: [
      {
        path: "login",
        name: "login",
        component: () => import("pages/LoginPage.vue"),
      },
      {
        path: "register",
        name: "register",
        component: () => import("pages/RegisterPage.vue"),
      },
    ],
  },
  {
    path: "/",
    component: () => import("layouts/MainLayout.vue"),
    meta: { requiresAuth: true },
    children: [
      { path: "", redirect: "/dashboard" },
      {
        path: "dashboard",
        name: "dashboard",
        component: () => import("pages/DashboardPage.vue"),
      },
      {
        path: "quotes",
        name: "quotes",
        component: () => import("pages/QuotesListPage.vue"),
      },
      {
        path: "quotes/create",
        name: "quotes-create",
        component: () => import("pages/QuoteCreatePage.vue"),
      },
      {
        path: "quotes/:id",
        name: "quotes-show",
        component: () => import("pages/QuoteDetailPage.vue"),
      },
      {
        path: "customers",
        name: "customers",
        component: () => import("pages/CustomersPage.vue"),
      },
      {
        path: "materials",
        name: "materials",
        component: () => import("pages/MaterialsPage.vue"),
      },
      {
        path: "settings",
        name: "settings",
        component: () => import("pages/SettingsPage.vue"),
      },
      {
        path: "datanorm",
        name: "datanorm",
        component: () => import("pages/DatanormImportPage.vue"),
      },
      {
        path: "vorlagen",
        name: "vorlagen",
        component: () => import("pages/ServiceTemplatesPage.vue"),
      },
      {
        path: "protokolle",
        name: "protokolle",
        component: () => import("pages/AcceptanceProtocolPage.vue"),
      },
      {
        path: "protokolle/neu/:quoteId",
        name: "protokoll-neu",
        component: () => import("pages/AcceptanceProtocolPage.vue"),
        props: true,
      },
      {
        path: "invoices",
        name: "invoices",
        component: () => import("pages/InvoicesListPage.vue"),
      },
      {
        path: "invoices/:id",
        name: "invoice-detail",
        component: () => import("pages/InvoiceDetailPage.vue"),
      },
  
    ],
  },
  {
    path: "/:catchAll(.*)*",
    component: () => import("pages/ErrorNotFound.vue"),
  },
];

export default route(function () {
  const createHistory = process.env.SERVER
    ? createMemoryHistory
    : process.env.VUE_ROUTER_MODE === "history"
      ? createWebHistory
      : createWebHashHistory;

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,
    history: createHistory(process.env.VUE_ROUTER_BASE),
  });

  Router.beforeEach((to, from, next) => {
    const token = localStorage.getItem("auth_token");
    const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);

    if (requiresAuth && !token) {
      next({ name: "login" });
    } else if (token && (to.name === "login" || to.name === "register")) {
      next({ name: "dashboard" });
    } else {
      next();
    }
  });

  return Router;
});
