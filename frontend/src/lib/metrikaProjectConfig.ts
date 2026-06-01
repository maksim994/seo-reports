export type MetrikaProjectConfig = {
  metrika?: {
    goal_ids?: number[]
    traffic_source?: string
  }
}

export type MetrikaFormState = {
  goalIds: string[]
  trafficSource: string
}

export function emptyMetrikaFormState(): MetrikaFormState {
  return {
    goalIds: [],
    trafficSource: '',
  }
}

export function metrikaFormFromConfig(
  config: Record<string, unknown> | null | undefined,
): MetrikaFormState {
  const state = emptyMetrikaFormState()
  if (!config?.metrika || typeof config.metrika !== 'object') {
    return state
  }

  const metrika = config.metrika as MetrikaProjectConfig['metrika']
  if (Array.isArray(metrika?.goal_ids)) {
    state.goalIds = metrika.goal_ids.map(String)
  }
  if (typeof metrika?.traffic_source === 'string') {
    state.trafficSource = metrika.traffic_source
  }

  return state
}

export function metrikaConfigFromForm(form: MetrikaFormState): MetrikaProjectConfig {
  const goalIds = form.goalIds.map((id) => Number(id)).filter((id) => id > 0)

  return {
    metrika: {
      goal_ids: goalIds.length > 0 ? goalIds : undefined,
      traffic_source: form.trafficSource || undefined,
    },
  }
}
