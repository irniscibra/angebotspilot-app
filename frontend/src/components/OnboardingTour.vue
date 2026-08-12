<template>
  <transition name="tour-fade">
    <div v-if="active" class="onboarding-overlay" @click.self="skip">
      <div class="spotlight" :style="spotlightStyle" />

      <transition name="tour-card-fade" mode="out-in">
        <div class="tour-card" :style="cardStyle" :key="currentIndex">
          <div class="tour-card__progress">
            <div
              v-for="(s, i) in steps"
              :key="i"
              class="tour-card__dot"
              :class="{
                'tour-card__dot--active': i === currentIndex,
                'tour-card__dot--done': i < currentIndex,
              }"
            />
          </div>

          <div class="tour-card__title">{{ currentStep.title }}</div>
          <div class="tour-card__text">{{ currentStep.text }}</div>

          <div class="tour-card__actions">
            <button class="tour-card__skip" @click="skip">Überspringen</button>
            <button class="tour-card__next" @click="next">
              {{ isLastStep ? "Fertig" : "Weiter" }}
              <span class="tour-card__next-icon">→</span>
            </button>
          </div>
        </div>
      </transition>
    </div>
  </transition>
</template>

<script>
import {
  ref,
  computed,
  onMounted,
  onBeforeUnmount,
  nextTick,
  watch,
} from "vue";
import { useAuthStore } from "src/stores/auth";
import { api } from "src/boot/axios";

export default {
  name: "OnboardingTour",
  setup() {
    const authStore = useAuthStore();
    const active = ref(false);
    const currentIndex = ref(0);
    const targetRect = ref(null);
    let resizeObserver = null;

    const steps = [
      {
        selector: '[data-tour="new-quote"]',
        title: "Neues Angebot erstellen",
        text: "Hier startest du ein neues Angebot. Die KI übernimmt die komplette Kalkulation für dich.",
      },
      {
        selector: '[data-tour="ai-quote-button"]',
        title: "KI-Angebot per Beschreibung",
        text: "Beschreib das Projekt in eigenen Worten — die KI erstellt daraus ein vollständiges Angebot in Sekunden.",
      },
      {
        selector: '[data-tour="datanorm-menu"]',
        title: "Materialkatalog importieren",
        text: "Lade deinen Datanorm-Katalog hoch, damit die KI mit deinen echten Einkaufspreisen kalkuliert.",
      },
      {
        selector: '[data-tour="settings-menu"]',
        title: "Firmendaten & Stundensatz",
        text: "Hier hinterlegst du deinen Stundensatz und deine Firmendaten — die Grundlage für jede Kalkulation.",
      },
    ];

    const currentStep = computed(() => steps[currentIndex.value]);
    const isLastStep = computed(() => currentIndex.value === steps.length - 1);

    const updateTargetRect = async () => {
      await nextTick();
      const el = document.querySelector(currentStep.value.selector);
      targetRect.value = el ? el.getBoundingClientRect() : null;
    };

    const spotlightStyle = computed(() => {
      if (!targetRect.value) return { opacity: 0 };
      const r = targetRect.value;
      const pad = 8;
      return {
        top: `${r.top - pad}px`,
        left: `${r.left - pad}px`,
        width: `${r.width + pad * 2}px`,
        height: `${r.height + pad * 2}px`,
      };
    });

    const cardStyle = computed(() => {
      const isMobile = window.innerWidth < 600;
      const cardWidth = isMobile ? Math.min(window.innerWidth - 32, 340) : 340;
      const cardHeight = isMobile ? 210 : 190;
      const gap = 18;

      // Auf Mobile immer zentriert unten anzeigen - zuverlässiger als
      // neben kleinen Elementen zu positionieren, die oft breiter als der Screen sind
      if (isMobile) {
        return {
          width: `${cardWidth}px`,
          left: "50%",
          bottom: "24px",
          top: "auto",
          transform: "translateX(-50%)",
        };
      }

      if (!targetRect.value) {
        return {
          width: `${cardWidth}px`,
          top: "50%",
          left: "50%",
          transform: "translate(-50%, -50%)",
        };
      }

      const r = targetRect.value;
      let top = r.bottom + gap;
      let left = r.left;

      if (top + cardHeight > window.innerHeight - 16) {
        top = r.top - cardHeight - gap;
      }
      if (top < 16) top = 16;
      if (left + cardWidth > window.innerWidth - 16) {
        left = window.innerWidth - cardWidth - 16;
      }
      if (left < 16) left = 16;

      return { width: `${cardWidth}px`, top: `${top}px`, left: `${left}px` };
    });
    const next = async () => {
      if (isLastStep.value) {
        await finish();
        return;
      }
      currentIndex.value++;
      await updateTargetRect();
    };

    const skip = async () => {
      await finish();
    };

    const finish = async () => {
      active.value = false;
      try {
        await api.post("/auth/complete-onboarding");
        if (authStore.user) {
          authStore.user.onboarding_completed_at = new Date().toISOString();
          localStorage.setItem("user", JSON.stringify(authStore.user));
        }
      } catch (e) {
        console.error("Onboarding-Status konnte nicht gespeichert werden", e);
      }
    };

    const handleResize = () => {
  updateTargetRect();
};

    onMounted(async () => {
      if (authStore.user && !authStore.user.onboarding_completed_at) {
        // Kurze Verzögerung, damit Dashboard-Daten geladen sind und Layout steht
        setTimeout(async () => {
          active.value = true;
          await updateTargetRect();
        }, 400);
        window.addEventListener("resize", handleResize);
      }
    });

    onBeforeUnmount(() => {
      window.removeEventListener("resize", handleResize);
      if (resizeObserver) resizeObserver.disconnect();
    });

    return {
      active,
      currentIndex,
      steps,
      currentStep,
      isLastStep,
      spotlightStyle,
      cardStyle,
      next,
      skip,
    };
  },
};
</script>

<style scoped>
.onboarding-overlay {
  position: fixed;
  inset: 0;
  z-index: 9998;
}

.spotlight {
  position: fixed;
  border-radius: 12px;
  box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.6);
  background: transparent;
  pointer-events: none;
  transition:
    top 0.35s cubic-bezier(0.4, 0, 0.2, 1),
    left 0.35s cubic-bezier(0.4, 0, 0.2, 1),
    width 0.35s cubic-bezier(0.4, 0, 0.2, 1),
    height 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 9998;
}

.tour-card {
  position: fixed;
  width: 340px;
  background: #ffffff;
  border-radius: 14px;
  padding: 20px 22px;
  box-shadow:
    0 20px 50px rgba(15, 23, 42, 0.35),
    0 2px 8px rgba(15, 23, 42, 0.1);
  z-index: 9999;
  transition:
    top 0.35s cubic-bezier(0.4, 0, 0.2, 1),
    left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.tour-card__progress {
  display: flex;
  gap: 6px;
  margin-bottom: 14px;
}

.tour-card__dot {
  height: 4px;
  flex: 1;
  border-radius: 2px;
  background: #e2e8f0;
  transition: background 0.25s ease;
}

.tour-card__dot--active {
  background: #1d4ed8;
}

.tour-card__dot--done {
  background: #93c5fd;
}

.tour-card__title {
  font-size: 15.5px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 6px;
  letter-spacing: -0.1px;
}

.tour-card__text {
  font-size: 13.5px;
  line-height: 1.55;
  color: #64748b;
  margin-bottom: 18px;
}

.tour-card__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.tour-card__skip {
  background: none;
  border: none;
  font-size: 12.5px;
  font-weight: 600;
  color: #94a3b8;
  cursor: pointer;
  padding: 6px 4px;
  transition: color 0.2s ease;
}

.tour-card__skip:hover {
  color: #64748b;
}

.tour-card__next {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #1d4ed8;
  color: white;
  border: none;
  border-radius: 8px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.2s ease,
    transform 0.15s ease;
}

.tour-card__next:hover {
  background: #1e40af;
}

.tour-card__next:active {
  transform: scale(0.97);
}

.tour-card__next-icon {
  transition: transform 0.2s ease;
}

.tour-card__next:hover .tour-card__next-icon {
  transform: translateX(2px);
}

/* Übergänge */
.tour-fade-enter-active,
.tour-fade-leave-active {
  transition: opacity 0.3s ease;
}
.tour-fade-enter-from,
.tour-fade-leave-to {
  opacity: 0;
}

.tour-card-fade-enter-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}
.tour-card-fade-leave-active {
  transition: opacity 0.15s ease;
}
.tour-card-fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.tour-card-fade-leave-to {
  opacity: 0;
}

@media (max-width: 599px) {
  .tour-card {
    padding: 18px 20px;
  }

  .tour-card__actions {
    flex-direction: column-reverse;
    align-items: stretch;
    gap: 10px;
  }

  .tour-card__next {
    justify-content: center;
    width: 100%;
    padding: 11px 16px;
  }

  .tour-card__skip {
    text-align: center;
    padding: 8px;
  }
}
</style>
