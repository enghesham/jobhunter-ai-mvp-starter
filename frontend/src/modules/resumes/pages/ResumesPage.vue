<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Documents"
      title="Resumes"
      description="The backend does not expose a dedicated resumes listing endpoint yet, so generated drafts are previewed directly from the Jobs screen."
    />

    <EmptyState
      title="No standalone resumes view yet"
      description="Generate resumes from the Jobs page. The backend returns the generated resume payload there, but it still does not expose a dedicated resumes listing endpoint."
      icon="pi-file-edit"
    >
      <template #actions>
        <RouterLink to="/jobs">
          <Button label="Go to Jobs" icon="pi pi-file-edit" />
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
