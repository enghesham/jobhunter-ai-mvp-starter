<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Scoring"
      title="Matches"
      description="The backend does not expose a dedicated matches listing endpoint yet, so this page stays read-only for now."
    />

    <PageCard
      eyebrow="Backend Limitation"
      title="Matches Will Appear After Running Match From Jobs"
      description="Use the Jobs screen to run Match on a specific job. The result is available immediately in the job action dialog, but there is no GET /jobhunter/matches endpoint yet for a standalone list page."
    >
      <div class="flex flex-wrap gap-3">
        <RouterLink to="/jobs">
          <Button label="Go to Jobs" icon="pi pi-briefcase" />
        </RouterLink>
      </div>
    </PageCard>
  </div>
</template>

<script setup lang="ts">
import Button from 'primevue/button'
import { RouterLink } from 'vue-router'

import PageCard from '@/shared/components/PageCard.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
</script>
