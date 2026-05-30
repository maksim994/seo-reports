export type WordstatDynamicsConfig = {
  phrases?: string
  period?: 'monthly' | 'weekly' | 'daily'
  lookback_months?: number
}

export type WordstatTopRequestsConfig = {
  phrase?: string
  limit?: number
}

export type WordstatRegionsConfig = {
  phrase?: string
  region_type?: 'all' | 'cities' | 'regions'
  limit?: number
}

export type WordstatProjectConfig = {
  region_id?: number
  wordstat?: {
    dynamics?: WordstatDynamicsConfig
    top_requests?: WordstatTopRequestsConfig
    regions?: WordstatRegionsConfig
  }
}

export type WordstatFormState = {
  regionId: string
  dynamicsPhrases: string
  dynamicsPeriod: 'monthly' | 'weekly' | 'daily'
  dynamicsLookback: string
  topPhrase: string
  topLimit: string
  regionsPhrase: string
  regionsType: 'all' | 'cities' | 'regions'
  regionsLimit: string
}

export function emptyWordstatFormState(): WordstatFormState {
  return {
    regionId: '',
    dynamicsPhrases: '',
    dynamicsPeriod: 'monthly',
    dynamicsLookback: '24',
    topPhrase: '',
    topLimit: '10',
    regionsPhrase: '',
    regionsType: 'all',
    regionsLimit: '10',
  }
}

export function wordstatFormFromConfig(config: Record<string, unknown> | null | undefined): WordstatFormState {
  const state = emptyWordstatFormState()
  if (!config) {
    return state
  }

  if (typeof config.region_id === 'number') {
    state.regionId = String(config.region_id)
  }

  const wordstat = config.wordstat
  if (!wordstat || typeof wordstat !== 'object') {
    return state
  }

  const dynamics = (wordstat as WordstatProjectConfig['wordstat'])?.dynamics
  if (dynamics && typeof dynamics === 'object') {
    if (typeof dynamics.phrases === 'string') state.dynamicsPhrases = dynamics.phrases
    if (dynamics.period === 'weekly' || dynamics.period === 'daily' || dynamics.period === 'monthly') {
      state.dynamicsPeriod = dynamics.period
    }
    if (typeof dynamics.lookback_months === 'number') {
      state.dynamicsLookback = String(dynamics.lookback_months)
    }
  }

  const topRequests = (wordstat as WordstatProjectConfig['wordstat'])?.top_requests
  if (topRequests && typeof topRequests === 'object') {
    if (typeof topRequests.phrase === 'string') state.topPhrase = topRequests.phrase
    if (typeof topRequests.limit === 'number') state.topLimit = String(topRequests.limit)
  }

  const regions = (wordstat as WordstatProjectConfig['wordstat'])?.regions
  if (regions && typeof regions === 'object') {
    if (typeof regions.phrase === 'string') state.regionsPhrase = regions.phrase
    if (regions.region_type === 'all' || regions.region_type === 'cities' || regions.region_type === 'regions') {
      state.regionsType = regions.region_type
    }
    if (typeof regions.limit === 'number') state.regionsLimit = String(regions.limit)
  }

  return state
}

export function wordstatConfigFromForm(form: WordstatFormState): WordstatProjectConfig {
  const config: WordstatProjectConfig = {
    wordstat: {
      dynamics: {
        phrases: form.dynamicsPhrases.trim(),
        period: form.dynamicsPeriod,
        lookback_months: Number(form.dynamicsLookback) || 24,
      },
      top_requests: {
        phrase: form.topPhrase.trim(),
        limit: Number(form.topLimit) || 10,
      },
      regions: {
        phrase: form.regionsPhrase.trim(),
        region_type: form.regionsType,
        limit: Number(form.regionsLimit) || 10,
      },
    },
  }

  if (form.regionId.trim()) {
    config.region_id = Number(form.regionId)
  }

  return config
}
