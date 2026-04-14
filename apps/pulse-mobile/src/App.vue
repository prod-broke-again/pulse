<script setup lang="ts">
import { onMounted, watch } from 'vue'
import MobileShell from './components/layout/MobileShell.vue'
import {
  isModeratorStaffUser,
  startModeratorPresenceForMobile,
  stopModeratorPresenceForMobile,
} from './lib/moderatorPresenceMobile'
import { useAuthStore } from './stores/authStore'

const auth = useAuthStore()

watch(
  () => [auth.isAuthenticated, auth.user] as const,
  ([ok, user]) => {
    if (ok && isModeratorStaffUser(user)) {
      startModeratorPresenceForMobile()
    } else {
      stopModeratorPresenceForMobile()
    }
  },
  { immediate: true },
)

onMounted(() => {
  if (auth.token && !auth.user) {
    void auth.fetchMe().catch(() => {})
  }
})
</script>

<template>
  <MobileShell />
</template>
