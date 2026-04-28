import { api } from './client'

export interface UsageToday {
  used: number
  cap: number | null
  remaining: number | null
  percentage: number
  cost_usd: number
}

export interface UsageSeriesRow {
  day: string
  action: string
  tokens: number
  cost: number
}

export interface UsageBreakdownRow {
  provider: string
  model: string
  prompt_tokens: number
  completion_tokens: number
  total_tokens: number
  cost_usd: number
  calls: number
}

export const usageApi = {
  today: () => api.get<UsageToday>('/api/usage/today').then((r) => r.data),
  timeseries: (days = 14) =>
    api
      .get<{ series: UsageSeriesRow[] }>('/api/usage/timeseries', { params: { days } })
      .then((r) => r.data.series),
  breakdown: () =>
    api.get<{ breakdown: UsageBreakdownRow[] }>('/api/usage/breakdown').then((r) => r.data.breakdown),
}
