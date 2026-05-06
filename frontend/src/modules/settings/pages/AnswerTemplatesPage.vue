<template>
  <div class="space-y-6">
    <PageHeader
      eyebrow="Answer Templates"
      title="Reusable application content"
      description="Manage templates that feed cover letters, common answers, and apply package fallback content."
    />

    <ErrorState v-if="pageError" title="Templates unavailable" :message="pageError">
      <template #actions>
        <Button label="Retry" icon="pi pi-refresh" @click="loadTemplates" />
      </template>
    </ErrorState>

    <SkeletonTable v-if="loading" :columns="4" />

    <div v-else class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600" v-pre>
          Templates support placeholders such as <code>{{ full_name }}</code>, <code>{{ job_title }}</code>, <code>{{ company_name }}</code>, <code>{{ headline }}</code>, and <code>{{ strength_areas }}</code>.
        </div>

        <div class="flex flex-col gap-3 md:flex-row">
          <RouterLink to="/settings">
            <Button label="Settings Home" icon="pi pi-arrow-left" severity="secondary" outlined />
          </RouterLink>
          <Button label="Bootstrap Defaults" icon="pi pi-sparkles" severity="secondary" outlined :loading="bootstrapping" @click="handleBootstrapDefaults" />
          <Button label="New Template" icon="pi pi-plus" @click="openCreateDialog" />
        </div>
      </div>

      <EmptyState
        v-if="templates.length === 0"
        title="No answer templates yet"
        description="Create reusable templates for cover letters and common application answers, or bootstrap the starter set."
        icon="pi-file-edit"
      >
        <template #actions>
          <Button label="Bootstrap Defaults" icon="pi pi-sparkles" @click="handleBootstrapDefaults" />
          <Button label="Create Template" icon="pi pi-plus" severity="secondary" outlined @click="openCreateDialog" />
        </template>
      </EmptyState>

      <DataTable
        v-else
        :value="templates"
        data-key="id"
        paginator
        :rows="10"
        class="mt-6"
        striped-rows
        responsive-layout="scroll"
      >
        <Column header="Key">
          <template #body="{ data }">
            <div>
              <p class="font-medium text-slate-900">{{ data.key }}</p>
              <p class="text-sm text-slate-500">{{ data.title }}</p>
            </div>
          </template>
        </Column>

        <Column header="Tags">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Tag v-for="tag in data.tags || []" :key="`${data.id}-${tag}`" severity="secondary" :value="tag" />
              <span v-if="!(data.tags || []).length" class="text-sm text-slate-400">No tags</span>
            </div>
          </template>
        </Column>

        <Column header="Template">
          <template #body="{ data }">
            <p class="line-clamp-3 text-sm leading-6 text-slate-700">{{ data.base_answer }}</p>
          </template>
        </Column>

        <Column header="Actions" :style="{ width: '14rem' }">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-2">
              <Button label="Edit" icon="pi pi-pencil" size="small" severity="secondary" outlined @click="openEditDialog(data)" />
              <Button label="Delete" icon="pi pi-trash" size="small" severity="danger" text @click="confirmDelete(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <Dialog v-model:visible="formDialogVisible" modal :header="editingTemplateId ? 'Edit Answer Template' : 'Create Answer Template'" :style="{ width: '44rem' }">
      <form class="space-y-4" @submit.prevent="submitForm">
        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Key</label>
          <InputText v-model.trim="form.key" fluid :disabled="editingTemplateId !== null" placeholder="cover_letter" />
          <FormError :message="fieldError('key')" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Title</label>
          <InputText v-model.trim="form.title" fluid placeholder="Default Cover Letter" />
          <FormError :message="fieldError('title')" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Tags</label>
          <InputText v-model="form.tags_csv" fluid placeholder="application, cover-letter" />
        </div>

        <div class="space-y-2">
          <label class="text-sm font-medium text-slate-700">Base Answer</label>
          <Textarea v-model="form.base_answer" fluid auto-resize rows="8" />
          <FormError :message="fieldError('base_answer')" />
        </div>

        <div v-if="formError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ formError }}
        </div>

        <div class="flex justify-end gap-3">
          <Button type="button" label="Cancel" severity="secondary" text @click="formDialogVisible = false" />
          <LoadingButton type="submit" :loading="saving" :label="editingTemplateId ? 'Save Template' : 'Create Template'" loading-label="Saving..." />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

import EmptyState from '@/shared/components/EmptyState.vue'
import ErrorState from '@/shared/components/ErrorState.vue'
import FormError from '@/shared/components/FormError.vue'
import LoadingButton from '@/shared/components/LoadingButton.vue'
import PageHeader from '@/shared/components/PageHeader.vue'
import SkeletonTable from '@/shared/components/SkeletonTable.vue'
import { getApiErrorMessage, getApiValidationErrors } from '@/shared/utils/api'
import {
  bootstrapDefaultAnswerTemplates,
  createAnswerTemplate,
  deleteAnswerTemplate,
  listAnswerTemplates,
  updateAnswerTemplate,
} from '@/modules/settings/services/answerTemplatesApi'
import type { AnswerTemplate } from '@/modules/settings/types'

interface TemplateFormState {
  key: string
  title: string
  tags_csv: string
  base_answer: string
}

const toast = useToast()
const confirm = useConfirm()
const loading = ref(false)
const saving = ref(false)
const bootstrapping = ref(false)
const pageError = ref('')
const formError = ref('')
const templates = ref<AnswerTemplate[]>([])
const formDialogVisible = ref(false)
const editingTemplateId = ref<number | null>(null)
const validationErrors = ref<Record<string, string[]>>({})

const form = reactive<TemplateFormState>({
  key: '',
  title: '',
  tags_csv: '',
  base_answer: '',
})

onMounted(async () => {
  await loadTemplates()
})

async function loadTemplates(): Promise<void> {
  loading.value = true
  pageError.value = ''

  try {
    const collection = await listAnswerTemplates()
    templates.value = collection.items
  } catch (error) {
    pageError.value = getApiErrorMessage(error, 'Failed to load answer templates.')
  } finally {
    loading.value = false
  }
}

function openCreateDialog(): void {
  editingTemplateId.value = null
  resetForm()
  formDialogVisible.value = true
}

function openEditDialog(template: AnswerTemplate): void {
  editingTemplateId.value = template.id
  form.key = template.key
  form.title = template.title
  form.tags_csv = (template.tags || []).join(', ')
  form.base_answer = template.base_answer
  validationErrors.value = {}
  formError.value = ''
  formDialogVisible.value = true
}

async function submitForm(): Promise<void> {
  saving.value = true
  validationErrors.value = {}
  formError.value = ''

  const payload = {
    key: form.key.trim(),
    title: form.title.trim(),
    base_answer: form.base_answer.trim(),
    tags: form.tags_csv
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean),
  }

  try {
    const template = editingTemplateId.value
      ? await updateAnswerTemplate(editingTemplateId.value, payload)
      : await createAnswerTemplate(payload)

    upsertTemplate(template)
    formDialogVisible.value = false
    toast.add({
      severity: 'success',
      summary: editingTemplateId.value ? 'Template updated' : 'Template created',
      detail: editingTemplateId.value ? 'Answer template updated successfully.' : 'Answer template created successfully.',
      life: 3000,
    })
  } catch (error) {
    validationErrors.value = getApiValidationErrors(error)
    formError.value = getApiErrorMessage(error, 'Failed to save answer template.')
  } finally {
    saving.value = false
  }
}

async function handleBootstrapDefaults(): Promise<void> {
  bootstrapping.value = true

  try {
    const collection = await bootstrapDefaultAnswerTemplates()
    templates.value = collection.items
    toast.add({
      severity: 'success',
      summary: 'Defaults created',
      detail: 'Starter answer templates are ready to use.',
      life: 3000,
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Bootstrap failed',
      detail: getApiErrorMessage(error, 'Failed to create default answer templates.'),
      life: 4000,
    })
  } finally {
    bootstrapping.value = false
  }
}

function confirmDelete(template: AnswerTemplate): void {
  confirm.require({
    header: 'Delete answer template',
    message: `Delete template "${template.title}"? This cannot be undone.`,
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await deleteAnswerTemplate(template.id)
        templates.value = templates.value.filter((item) => item.id !== template.id)
        toast.add({ severity: 'success', summary: 'Template deleted', detail: 'Answer template removed.', life: 3000 })
      } catch (error) {
        toast.add({ severity: 'error', summary: 'Delete failed', detail: getApiErrorMessage(error, 'Failed to delete answer template.'), life: 4000 })
      }
    },
  })
}

function upsertTemplate(template: AnswerTemplate): void {
  const index = templates.value.findIndex((item) => item.id === template.id)

  if (index === -1) {
    templates.value.unshift(template)
    return
  }

  templates.value.splice(index, 1, template)
}

function resetForm(): void {
  form.key = ''
  form.title = ''
  form.tags_csv = ''
  form.base_answer = ''
  validationErrors.value = {}
  formError.value = ''
}

function fieldError(field: string): string | null {
  return validationErrors.value[field]?.[0] ?? null
}
</script>
