import type { IntegrationResource } from '@/types'

type TopvisorRegion = {
  index: number
  searcher: string
  region: string
  label: string
}

export function topvisorRegions(resource: IntegrationResource): TopvisorRegion[] {
  const regions = resource.meta?.regions
  return Array.isArray(regions) ? (regions as TopvisorRegion[]) : []
}

type KeysSoSearchSetting = {
  region_id: number
  region_name: string
  engine: number
  engine_name: string
  label: string
}

export function keysSoSearchSettings(resource: IntegrationResource): KeysSoSearchSetting[] {
  const settings = resource.meta?.search_settings
  return Array.isArray(settings) ? (settings as KeysSoSearchSetting[]) : []
}

function pluralRegions(count: number): string {
  const mod10 = count % 10
  const mod100 = count % 100
  if (mod10 === 1 && mod100 !== 11) {
    return `${count} регион`
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
    return `${count} региона`
  }
  return `${count} регионов`
}

export function integrationResourceTitle(resource: IntegrationResource): string {
  const meta = resource.meta ?? {}
  const name = typeof meta.name === 'string' ? meta.name.trim() : ''
  if (name) {
    return name
  }
  if (typeof meta.project_name === 'string' && meta.project_name.trim()) {
    const title = meta.project_name.trim()
    if (!title.startsWith('Проект #')) {
      return title
    }
  }
  if (resource.label?.trim()) {
    return resource.label.replace(/\s*\(#\d+\)$/, '')
  }
  return resource.id
}

export function integrationResourceSubtitle(resource: IntegrationResource): string {
  const meta = resource.meta ?? {}
  const projectId = meta.project_id
  const url = typeof meta.url === 'string' ? meta.url : typeof meta.site === 'string' ? meta.site : null

  if (url && projectId !== undefined) {
    return `${url} · #${projectId}`
  }

  if (projectId !== undefined) {
    return `#${projectId}`
  }

  return ''
}

export function integrationResourceHint(resource: IntegrationResource): string | null {
  if (resource.meta?.resource_kind === 'dashboard') {
    return 'Проект дашборда — для отчётов по позициям нужен проект мониторинга'
  }

  const regions = topvisorRegions(resource)
  if (regions.length > 0) {
    const preview = regions
      .slice(0, 3)
      .map((region) => region.label)
      .join('; ')

    const suffix = regions.length > 3 ? '…' : ''

    return `${pluralRegions(regions.length)}: ${preview}${suffix}`
  }

  const searchSettings = keysSoSearchSettings(resource)
  if (searchSettings.length > 0) {
    const preview = searchSettings
      .slice(0, 3)
      .map((setting) => setting.label)
      .join('; ')

    const suffix = searchSettings.length > 3 ? '…' : ''

    return `${pluralRegions(searchSettings.length)}: ${preview}${suffix}`
  }

  return 'Регионы для проверки позиций не настроены'
}

export function formatIntegrationResourceOption(resource: IntegrationResource): string {
  return resource.label || integrationResourceTitle(resource)
}

export function formatTopvisorRegionOption(region: TopvisorRegion): string {
  return region.label
}

export function formatKeysSoSearchSettingOption(setting: KeysSoSearchSetting): string {
  return setting.label
}
