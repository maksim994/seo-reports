export interface User {
  id: number
  name: string
  email: string
  is_admin: boolean
  is_blocked: boolean
  projects_count?: number
  created_at?: string
  updated_at?: string
}

export interface Project {
  id: number
  user_id: number
  name: string
  domain: string | null
  promotion_start_date: string | null
  has_analytics: boolean
  settings: Record<string, unknown> | null
  created_at: string
  updated_at: string
  user?: Pick<User, 'id' | 'name' | 'email'>
}

export interface AppSettings {
  app_name: string
  support_email: string
  registration_enabled: boolean
  email_verification_required: boolean
  report_retention_months: number
  maintenance_mode: boolean
  maintenance_message: string
}

export interface PublicSettings {
  app_name: string
  registration_enabled: boolean
  maintenance_mode: boolean
  maintenance_message: string
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiError {
  message?: string
  errors?: Record<string, string[]>
}

export interface IntegrationProviderMeta {
  provider: string
  label: string
  description: string
  icon: string
  logo_url?: string | null
  configured: boolean
  auth_type?: 'oauth' | 'api_key'
  api_key_fields?: Array<'user_id' | 'api_key'>
}

export interface WorkItem {
  id: number
  project_id: number
  work_date: string
  category: 'seo' | 'content' | 'technical'
  category_label: string
  description: string
  created_at: string
  updated_at: string
}

export interface Integration {
  id: number
  provider: string
  label: string
  logo_url?: string | null
  status: 'active' | 'token_expired' | 'error'
  account_label: string | null
  expires_at: string | null
  project_integrations_count: number
  created_at: string
}

export interface IntegrationResource {
  id: string
  label: string
  meta?: Record<string, unknown>
}

export interface ProjectIntegrationBinding {
  id: number
  integration_id: number
  provider: string
  external_resource_id: string
  external_resource_label: string | null
  config: Record<string, unknown> | null
}

export interface ReportBlockCatalogItem {
  block_type: string
  category: string
  label: string
  description: string
  required_integration: string | null
  settings_schema?: BlockSettingsField[]
}

export interface BlockSettingsField {
  key: string
  label: string
  type: 'text' | 'textarea' | 'number' | 'select' | 'multiselect'
  default?: string | number | string[]
  min?: number
  max?: number
  options?: Array<{ value: string; label: string }>
  options_key?: string
}

export interface TemplateBlockItem {
  id?: number
  block_type: string
  label?: string
  category?: string
  required_integration?: string | null
  sort_order?: number
  settings?: Record<string, unknown> | null
}

export interface ReportTemplate {
  id: number
  name: string
  description: string | null
  logo_url: string | null
  is_default: boolean
  blocks_count?: number
  blocks?: TemplateBlockItem[]
  created_at: string
  updated_at: string
}

export interface ReportJobFile {
  format: 'html' | 'pdf'
  size: number
}

export interface ReportJob {
  id: number
  status: 'queued' | 'fetching' | 'rendering' | 'done' | 'failed'
  period_start: string
  period_end: string
  compare_period_start: string | null
  compare_period_end: string | null
  error_message: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string
  share_enabled: boolean
  share_token: string | null
  share_expires_at: string | null
  project: { id: number; name: string; domain: string | null } | null
  template: { id: number; name: string } | null
  files: ReportJobFile[]
}

export interface PublicReportMeta {
  project_name: string | null
  template_name: string | null
  period_start: string
  period_end: string
  finished_at: string | null
  formats: string[]
}

export interface DashboardMetrikaMetrics {
  visits: number
  users: number
  visits_change_pct: number | null
}

export interface DashboardSearchMetrics {
  source: 'google_search_console' | 'yandex_webmaster'
  clicks: number
  impressions: number
  ctr: number
  position?: number
  clicks_change_pct: number | null
}

export interface DashboardPositionMetrics {
  provider: string
  visibility: number | null
  visibility_dynamic: number | null
  top10: number | null
  avg_position: number | null
}

export interface DashboardProjectRow {
  id: number
  name: string
  domain: string | null
  has_analytics: boolean
  integrations: string[]
  metrics: {
    metrika: DashboardMetrikaMetrics | null
    search: DashboardSearchMetrics | null
    positions: DashboardPositionMetrics | null
  }
  last_report: {
    id: number
    period_start: string
    period_end: string
    finished_at: string | null
  } | null
  errors: Array<{ provider: string; message: string }>
}

export interface DashboardData {
  period: { start: string; end: string }
  compare_period: { start: string; end: string }
  projects: DashboardProjectRow[]
}

export interface TechnicalAuditFile {
  format: 'json' | 'md' | 'docx'
  size: number
}

export interface TechnicalAuditActivityEntry {
  at: string
  level: 'info' | 'success' | 'warning' | 'error'
  message: string
  context?: Record<string, unknown> | null
}

export interface TechnicalAuditJob {
  id: number
  status: 'queued' | 'launching' | 'running' | 'processing' | 'done' | 'failed'
  site_url: string
  site_name: string | null
  sample_urls: string[]
  crawl_depth: 'light' | 'sitemap'
  lang: 'ru' | 'en'
  cursor_agent_id: string | null
  cursor_agent_url: string | null
  webhook_reachable: boolean
  result_summary: {
    totals?: { critical?: number; warning?: number; ok?: number }
    top_priorities?: string[]
  } | null
  activity_log: TechnicalAuditActivityEntry[]
  error_message: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string
  project: { id: number; name: string; domain: string | null } | null
  files: TechnicalAuditFile[]
}
