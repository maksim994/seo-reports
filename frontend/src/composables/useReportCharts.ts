import ApexCharts from 'apexcharts'

function formatReportNumber(value: unknown): string {
  const number = Number(value)
  if (!Number.isFinite(number)) {
    return String(value)
  }
  if (Math.abs(number - Math.round(number)) < 0.001) {
    return Math.round(number).toLocaleString('ru-RU')
  }
  return number.toLocaleString('ru-RU', { maximumFractionDigits: 1 })
}

function applyCustomMeta(options: Record<string, unknown>) {
  const tooltip = options.tooltip as Record<string, unknown> | undefined
  const meta = tooltip?.customMeta
  if (!meta) {
    return
  }

  const chart = options.chart as Record<string, unknown> | undefined
  if (chart?.type === 'donut' && typeof meta === 'object' && meta !== null && 'totalText' in meta) {
    const totalText = (meta as { totalText: string }).totalText
    const plotOptions = (options.plotOptions ?? {}) as Record<string, unknown>
    const pie = (plotOptions.pie ?? {}) as Record<string, unknown>
    const donut = (pie.donut ?? {}) as Record<string, unknown>
    const labels = (donut.labels ?? {}) as Record<string, unknown>
    const total = (labels.total ?? {}) as Record<string, unknown>
    total.formatter = () => totalText
    donut.labels = { ...labels, total }
    pie.donut = donut
    plotOptions.pie = pie
    options.plotOptions = plotOptions
  }

  if (Array.isArray(meta)) {
    const y = (tooltip?.y ?? {}) as Record<string, unknown>
    y.formatter = (value: number, opts: { dataPointIndex: number }) => {
      const index = opts.dataPointIndex
      return meta[index] !== undefined ? meta[index] : formatReportNumber(value)
    }
    if (tooltip) tooltip.y = y
  }

  if (typeof meta === 'object' && meta !== null && 'suffixes' in meta && Array.isArray((meta as { suffixes: string[] }).suffixes)) {
    const suffixes = (meta as { suffixes: string[] }).suffixes
    const y = (tooltip?.y ?? {}) as Record<string, unknown>
    y.formatter = (value: number, opts: { dataPointIndex: number }) => {
      const suffix = suffixes[opts.dataPointIndex] ?? ''
      return formatReportNumber(value) + suffix
    }
    if (tooltip) tooltip.y = y
  }

  if (typeof meta === 'object' && meta !== null && 'suffix' in meta) {
    const suffix = (meta as { suffix: string }).suffix
    const y = (tooltip?.y ?? {}) as Record<string, unknown>
    y.formatter = (value: number) => formatReportNumber(value) + suffix
    if (tooltip) tooltip.y = y
  }

  if (tooltip) {
    delete tooltip.customMeta
  }
}

const chartInstances = new WeakMap<Element, ApexCharts>()
const observedSizes = new WeakMap<HTMLElement, { w: number; h: number }>()

let resizeTimer: ReturnType<typeof setTimeout> | null = null

function mountChart(element: HTMLElement, options: Record<string, unknown>) {
  const existing = chartInstances.get(element)
  if (existing) {
    existing.destroy()
  }
  const chart = new ApexCharts(element, options)
  chartInstances.set(element, chart)
  void chart.render()
}

export function renderReportCharts(root: ParentNode | null, force = false) {
  if (!root) return

  root.querySelectorAll<HTMLElement>('.apex-chart[data-config]').forEach((element) => {
    if (element.dataset.rendered === '1' && !force) {
      return
    }

    const raw = element.getAttribute('data-config')
    if (!raw) return

    const width = element.offsetWidth
    if (!force && width < 8) {
      return
    }

    try {
      const options = JSON.parse(raw) as Record<string, unknown>
      applyCustomMeta(options)
      element.dataset.rendered = '1'
      mountChart(element, options)
    } catch (error) {
      console.error('Failed to render report chart', error)
    }
  })
}

export function destroyReportCharts(root: ParentNode | null) {
  if (!root) return

  root.querySelectorAll<HTMLElement>('.apex-chart[data-config]').forEach((element) => {
    const chart = chartInstances.get(element)
    if (chart) {
      chart.destroy()
      chartInstances.delete(element)
    }
    delete element.dataset.rendered
  })
}

export function observeReportCharts(root: HTMLElement | null): () => void {
  if (!root || typeof ResizeObserver === 'undefined') {
    return () => {}
  }

  const observer = new ResizeObserver(() => {
    if (resizeTimer) clearTimeout(resizeTimer)
    resizeTimer = setTimeout(() => {
      const w = root.offsetWidth
      if (w < 8) return

      const prev = observedSizes.get(root) ?? { w: 0, h: 0 }
      const h = root.offsetHeight
      if (Math.abs(w - prev.w) < 4 && Math.abs(h - prev.h) < 4) {
        return
      }
      observedSizes.set(root, { w, h })

      renderReportCharts(root, false)
    }, 200)
  })

  observer.observe(root)

  return () => {
    observer.disconnect()
    observedSizes.delete(root)
    if (resizeTimer) clearTimeout(resizeTimer)
  }
}
