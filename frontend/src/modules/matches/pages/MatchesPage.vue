<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Scoring"
      title="Matches"
      description="The backend does not expose a dedicated matches listing endpoint yet, so this page stays read-only for now."
    />

    <EmptyState
      title="No standalone matches view yet"
      description="Run Match from the Jobs page. The result appears immediately there, but the backend still does not expose a dedicated matches listing endpoint."
      icon="pi-star"
    >
      <template #actions>
        <RouterLink to="/jobs">
          <Button label="Go to Jobs" icon="pi pi-briefcase" />
        </RouterLink>
      </template>
    </EmptyState>
  </div>
</template>

<script setup lang="ts">
import Button from 'primevue/button'
import { RouterLink } from 'vue-router'

import EmptyState from '@/shared/components/EmptyState.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
</script>
