<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Documents"
      title="Resumes"
      description="The backend does not expose a dedicated resumes listing endpoint yet, so generated drafts are previewed directly from the Jobs screen."
    />

    <PageCard
      eyebrow="Backend Limitation"
      title="Resume Versions Are Generated From Jobs"
      description="Use the Generate Resume action in the Jobs page. The backend currently returns the created tailored resume payload, but it does not provide a GET /jobhunter/resumes list endpoint yet."
    >
      <div class="flex flex-wrap gap-3">
        <RouterLink to="/jobs">
          <Button label="Go to Jobs" icon="pi pi-file-edit" />
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
