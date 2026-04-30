import api from '@/app/services/api'
import type { CollectionResponse } from '@/shared/types'
import { extractCollection } from '@/shared/utils/api'
import { downloadBlobFile, extractDownloadFileName } from '@/shared/utils/download'
import type { TailoredResume } from '@/modules/jobs/types'

export async function listResumes(): Promise<CollectionResponse<TailoredResume>> {
  const response = await api.get('/jobhunter/resumes')
  return extractCollection<TailoredResume>(response.data)
}

export async function downloadResumePdf(resumeId: number): Promise<void> {
  const response = await api.get(`/jobhunter/resumes/${resumeId}/download-pdf`, {
    responseType: 'blob',
  })

  const fileName = extractDownloadFileName(
    response.headers['content-disposition'] as string | undefined,
    `resume-${resumeId}.pdf`,
  )

  downloadBlobFile(response.data, fileName, 'application/pdf')
}
