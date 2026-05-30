import keyssoLogo from '@/assets/integrations/keysso.png'
import topvisorLogo from '@/assets/integrations/topvisor.svg'
import yandexMetrikaLogo from '@/assets/integrations/yandex-metrika.svg'
import yandexWebmasterLogo from '@/assets/integrations/yandex-webmaster.svg'
import yandexWordstatLogo from '@/assets/integrations/yandex-wordstat.svg'

export interface IntegrationBranding {
  logoUrl?: string
  icon: string
  accentClass?: string
}

const BRANDING: Record<string, IntegrationBranding> = {
  yandex_metrika: {
    icon: '📊',
    logoUrl: yandexMetrikaLogo,
    accentClass: 'bg-sky-50 border-sky-100',
  },
  google_analytics: { icon: '📈', accentClass: 'bg-orange-50 border-orange-100' },
  yandex_webmaster: {
    icon: '🔍',
    logoUrl: yandexWebmasterLogo,
    accentClass: 'bg-amber-50 border-amber-100',
  },
  yandex_wordstat: {
    icon: '📉',
    logoUrl: yandexWordstatLogo,
    accentClass: 'bg-violet-50 border-violet-100',
  },
  google_search_console: { icon: '🌐', accentClass: 'bg-blue-50 border-blue-100' },
  topvisor: {
    icon: '📍',
    logoUrl: topvisorLogo,
    accentClass: 'bg-slate-50 border-slate-200',
  },
  keys_so: {
    icon: '🔑',
    logoUrl: keyssoLogo,
    accentClass: 'bg-lime-50 border-lime-200',
  },
}

export function integrationBranding(provider: string, fallbackIcon = '🔗'): IntegrationBranding {
  return BRANDING[provider] ?? { icon: fallbackIcon, accentClass: 'bg-gray-50 border-gray-100' }
}

export function integrationLogoUrl(provider: string, _apiLogoUrl?: string | null): string | undefined {
  return integrationBranding(provider).logoUrl
}
