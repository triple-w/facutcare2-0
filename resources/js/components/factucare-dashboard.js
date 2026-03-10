import {
  Chart,
  LineController,
  LineElement,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js'

Chart.register(
  LineController,
  LineElement,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Filler,
  Tooltip,
  Legend,
)

const charts = {}

const destroyIfExists = (id) => {
  if (charts[id]) {
    charts[id].destroy()
    delete charts[id]
  }
}

const buildSparkline = (canvasId, labels, currentData, previousData, color) => {
  const ctx = document.getElementById(canvasId)
  if (!ctx) return

  destroyIfExists(canvasId)

  charts[canvasId] = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          data: currentData,
          borderColor: color,
          backgroundColor: `${color}22`,
          fill: true,
          tension: 0.35,
          pointRadius: 0,
          borderWidth: 2,
        },
        {
          data: previousData,
          borderColor: '#94A3B8',
          tension: 0.35,
          pointRadius: 0,
          borderWidth: 1.5,
        },
      ],
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
      },
      scales: {
        x: { display: false },
        y: { display: false, beginAtZero: true },
      },
      interaction: {
        intersect: false,
        mode: 'index',
      },
    },
  })
}

const buildMonthlyChart = (payload) => {
  const ctx = document.getElementById('dashboard-documents-monthly')
  if (!ctx || !payload) return

  destroyIfExists('dashboard-documents-monthly')

  charts['dashboard-documents-monthly'] = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: payload.labels || [],
      datasets: [
        {
          label: 'Facturas',
          data: payload.facturas || [],
          backgroundColor: '#8B5CF6',
          borderRadius: 6,
        },
        {
          label: 'Complementos',
          data: payload.complementos || [],
          backgroundColor: '#0EA5E9',
          borderRadius: 6,
        },
        {
          label: 'Notas de crédito',
          data: payload.notas_credito || [],
          backgroundColor: '#F59E0B',
          borderRadius: 6,
        },
      ],
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
      },
      scales: {
        x: {
          grid: { display: false },
        },
        y: {
          beginAtZero: true,
          ticks: {
            callback: (value) => `$${Number(value).toLocaleString('es-MX')}`,
          },
        },
      },
      interaction: {
        intersect: false,
        mode: 'index',
      },
    },
  })
}

const initFactucareDashboard = () => {
  const payload = window.factucareDashboard
  if (!payload || !payload.kpis) return

  buildSparkline(
    'dashboard-card-01',
    payload.kpis.ingresos?.series?.labels || [],
    payload.kpis.ingresos?.series?.actual || [],
    payload.kpis.ingresos?.series?.previo || [],
    '#8B5CF6',
  )

  buildSparkline(
    'dashboard-card-02',
    payload.kpis.complementos?.series?.labels || [],
    payload.kpis.complementos?.series?.actual || [],
    payload.kpis.complementos?.series?.previo || [],
    '#0EA5E9',
  )

  buildSparkline(
    'dashboard-card-03',
    payload.kpis.egresos?.series?.labels || [],
    payload.kpis.egresos?.series?.actual || [],
    payload.kpis.egresos?.series?.previo || [],
    '#F59E0B',
  )

  buildMonthlyChart(payload.monthlyChart)
}

document.addEventListener('DOMContentLoaded', initFactucareDashboard)
document.addEventListener('livewire:navigated', initFactucareDashboard)

export default initFactucareDashboard
