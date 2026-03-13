<script setup>
import { computed, reactive, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import axios from "axios";
import dayjs from "dayjs";
import { create_month_options, formatNumber } from "@/helpers/utils";
import ECharts from "vue-echarts";
import * as echarts from "echarts";

const page = usePage();
const title = "Analitik";

const now = new Date();
const defaultFiscalYear = now.getMonth() + 1 >= 4 ? now.getFullYear() : now.getFullYear() - 1;

const serverFilters = computed(() => page.props.filters || {});
const currentStats = computed(() => page.props.currentStats || {});
const prevStats = computed(() => page.props.prevStats || null);

const form = reactive({
  fiscal_year: serverFilters.value.fiscal_year || defaultFiscalYear,
  month: serverFilters.value.month || null,
  compare_year: page.props.compareYear || null,
});

const monthOptions = computed(() => [{ value: null, label: "Semua Bulan" }, ...create_month_options()]);

const fiscalYearOptions = computed(() => {
  const selectedFy = Number(form.fiscal_year || 0);
  const serverFy = Number(serverFilters.value.fiscal_year || 0);
  const base = Math.max(defaultFiscalYear, selectedFy, serverFy);
  const out = [];
  for (let i = 0; i < 10; i++) {
    const fy = base - i;
    out.push({ value: fy, label: `FY ${fy}/${fy + 1}` });
  }
  return out;
});

const compareYearOptions = computed(() => [{ value: null, label: "Tanpa Compare" }, ...fiscalYearOptions.value]);

const salesByBS = computed(() => page.props.salesByBS || []);
const salesByDistributor = computed(() => page.props.salesByDistributor || []);
const salesByProduct = computed(() => page.props.salesByProduct || []);
const topDistributors = computed(() => page.props.topDistributors || []);
const topRetailers = computed(() => page.props.topRetailers || []);
const targetVsActual = computed(() => page.props.targetVsActual || []);

const monthlySales = computed(() => page.props.monthlySales || []);
const prevMonthlySales = computed(() => page.props.prevMonthlySales || []);

const activityVsSales = ref([]);
const loadingActivityVsSales = ref(false);

const pctChange = (currentValue, previousValue) => {
  const current = Number(currentValue || 0);
  const previous = Number(previousValue || 0);
  if (!previous) return null;
  return ((current - previous) / previous) * 100;
};

// Posisi bulan dalam tahun fiskal: Apr=1, May=2, ..., Mar=12
const fiscalMonthPos = (yyyyMm) => {
  const m = parseInt(yyyyMm.split('-')[1]);
  return m >= 4 ? m - 3 : m + 9;
};

const monthlyRows = computed(() => {
  // Map prevMonthlySales by fiscal-month position (1=Apr..12=Mar)
  // Ini fix bug: FY2025 Apr='2025-04', FY2024 Apr='2024-04' - kunci beda
  const prevMap = {};
  prevMonthlySales.value.forEach((item) => {
    prevMap[fiscalMonthPos(item.month)] = Number(item.total_sales || 0);
  });

  return monthlySales.value.map((item) => {
    const current = Number(item.total_sales || 0);
    const prev = prevMap[fiscalMonthPos(item.month)] || 0;
    return {
      month: item.month,
      current,
      previous: prev,
      growth: pctChange(current, prev),
    };
  });
});

const applyFilters = () => {
  const query = {};
  if (form.fiscal_year) query.fiscal_year = form.fiscal_year;
  if (form.month) query.month = form.month;
  if (form.compare_year) query.compare_year = form.compare_year;

  router.get(route("admin.analytics.index"), query, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  form.fiscal_year = defaultFiscalYear;
  form.month = null;
  form.compare_year = null;
  applyFilters();
};

const fetchActivityVsSales = async () => {
  loadingActivityVsSales.value = true;
  try {
    const params = {};
    if (serverFilters.value.fiscal_year) params.fiscal_year = serverFilters.value.fiscal_year;
    if (serverFilters.value.month) params.month = serverFilters.value.month;

    const response = await axios.get(route("admin.analytics.activity-vs-sales"), { params });
    activityVsSales.value = response.data || [];
  } finally {
    loadingActivityVsSales.value = false;
  }
};

watch(
  () => [serverFilters.value.fiscal_year, serverFilters.value.month],
  () => {
    form.fiscal_year = serverFilters.value.fiscal_year || defaultFiscalYear;
    form.month = serverFilters.value.month || null;
    form.compare_year = page.props.compareYear || null;
    fetchActivityVsSales();
  },
  { immediate: true }
);

// ── Line chart: Tren Penjualan Bulanan ────────────────────────
const monthlyChartOption = computed(() => {
  const hasCompare = form.compare_year && prevMonthlySales.value.length > 0;
  const labels = monthlyRows.value.map((r) => dayjs(`${r.month}-01`).format("MMM YYYY"));
  const currentData = monthlyRows.value.map((r) => r.current);
  const prevData = monthlyRows.value.map((r) => r.previous);

  const series = [
    {
      name: `FY ${form.fiscal_year}/${(form.fiscal_year || 0) + 1}`,
      type: "line",
      smooth: true,
      data: currentData,
      lineStyle: { color: "#1976d2", width: 2 },
      itemStyle: { color: "#1976d2" },
      areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: "rgba(25,118,210,0.18)" }, { offset: 1, color: "rgba(25,118,210,0.01)" }]) },
    },
  ];

  if (hasCompare) {
    series.push({
      name: `FY ${form.compare_year}/${(form.compare_year || 0) + 1}`,
      type: "line",
      smooth: true,
      data: prevData,
      lineStyle: { color: "#f57c00", width: 2, type: "dashed" },
      itemStyle: { color: "#f57c00" },
    });
  }

  return {
    tooltip: {
      trigger: "axis",
      formatter: (params) =>
        params[0].name + "<br>" +
        params.map((p) => `${p.marker}${p.seriesName}: <b>Rp ${formatNumber(p.value || 0)}</b>`).join("<br>"),
    },
    legend: { top: 4, data: series.map((s) => s.name) },
    grid: { containLabel: true, left: 8, right: 8, bottom: 8, top: hasCompare ? 36 : 28 },
    xAxis: {
      type: "category",
      data: labels,
      axisLabel: { rotate: 30, fontSize: 11 },
      axisTick: { alignWithLabel: true },
    },
    yAxis: {
      type: "value",
      axisLabel: {
        formatter: (v) => (v >= 1_000_000 ? `${(v / 1_000_000).toFixed(0)}jt` : v >= 1_000 ? `${(v / 1_000).toFixed(0)}rb` : v),
      },
      splitLine: { lineStyle: { type: "dashed", color: "#ddd" } },
    },
    series,
  };
});
</script>

<template>
  <i-head :title="title" />
  <authenticated-layout>
    <template #title>{{ title }}</template>

    <q-page class="analytics-page">
      <div class="analytics-content">
        <!-- ── Filter ── -->
        <q-card flat bordered square class="section-card">
          <q-card-section class="card-section">
            <div class="filters-grid">
              <q-select
                v-model="form.fiscal_year"
                label="Fiscal Year"
                :options="fiscalYearOptions"
                map-options
                emit-value
                outlined
                dense
                class="filter-field"
              />

              <q-select
                v-model="form.month"
                label="Bulan"
                :options="monthOptions"
                map-options
                emit-value
                outlined
                dense
                class="filter-field"
              />

              <q-select
                v-model="form.compare_year"
                label="Compare Year"
                :options="compareYearOptions"
                map-options
                emit-value
                outlined
                dense
                class="filter-field"
              />

              <div class="filter-actions">
                <q-btn class="action-btn" color="primary" icon="search" label="Terapkan" dense @click="applyFilters" />
                <q-btn class="action-btn" color="grey-7" flat icon="refresh" label="Reset" dense @click="resetFilters" />
              </div>
            </div>
          </q-card-section>
        </q-card>

        <!-- ── KPI Cards ── -->
        <div class="stats-grid">
          <q-card flat bordered square class="kpi-card stats-card">
            <q-card-section class="card-section kpi-card-content">
              <div class="text-caption text-grey-7">Total Penjualan</div>
              <div class="kpi-value text-weight-bold">Rp {{ formatNumber(currentStats.total_sales || 0) }}</div>
              <template v-if="prevStats">
                <div class="text-caption text-grey-7 kpi-prev">Prev: Rp {{ formatNumber(prevStats.total_sales || 0) }}</div>
                <q-badge dense :color="pctChange(currentStats.total_sales, prevStats.total_sales) >= 0 ? 'green-8' : 'red-8'">
                  {{ pctChange(currentStats.total_sales, prevStats.total_sales) >= 0 ? '+' : '' }}{{ formatNumber(pctChange(currentStats.total_sales, prevStats.total_sales) || 0, 'id-ID', 1) }}%
                </q-badge>
              </template>
            </q-card-section>
          </q-card>

          <q-card flat bordered square class="kpi-card stats-card">
            <q-card-section class="card-section kpi-card-content">
              <div class="text-caption text-grey-7">Transaksi</div>
              <div class="kpi-value text-weight-bold">{{ formatNumber(currentStats.total_transactions || 0) }}</div>
              <template v-if="prevStats">
                <div class="text-caption text-grey-7 kpi-prev">Prev: {{ formatNumber(prevStats.total_transactions || 0) }}</div>
                <q-badge dense :color="pctChange(currentStats.total_transactions, prevStats.total_transactions) >= 0 ? 'green-8' : 'red-8'">
                  {{ pctChange(currentStats.total_transactions, prevStats.total_transactions) >= 0 ? '+' : '' }}{{ formatNumber(pctChange(currentStats.total_transactions, prevStats.total_transactions) || 0, 'id-ID', 1) }}%
                </q-badge>
              </template>
            </q-card-section>
          </q-card>

          <q-card flat bordered square class="kpi-card stats-card">
            <q-card-section class="card-section kpi-card-content">
              <div class="text-caption text-grey-7">Distributor Aktif</div>
              <div class="kpi-value text-weight-bold">{{ formatNumber(currentStats.active_distributors || 0) }}</div>
              <div class="text-caption text-grey-7 kpi-prev" v-if="prevStats">Prev: {{ formatNumber(prevStats.active_distributors || 0) }}</div>
            </q-card-section>
          </q-card>

          <q-card flat bordered square class="kpi-card stats-card">
            <q-card-section class="card-section kpi-card-content">
              <div class="text-caption text-grey-7">Total Qty</div>
              <div class="kpi-value text-weight-bold">{{ formatNumber(currentStats.total_qty || 0, 'id-ID', 2) }}</div>
              <div class="text-caption text-grey-7 kpi-prev" v-if="prevStats">Prev: {{ formatNumber(prevStats.total_qty || 0, 'id-ID', 2) }}</div>
            </q-card-section>
          </q-card>
        </div>

        <!-- ── Line Chart Tren Bulanan ── -->
        <q-card flat bordered square class="section-card">
          <q-card-section class="card-section">
            <div class="text-subtitle2 text-weight-bold">
              Tren Penjualan Bulanan
              <span v-if="form.compare_year" class="text-caption text-grey-7 q-ml-xs">
                FY{{ form.fiscal_year }}/{{ (form.fiscal_year || 0) + 1 }} vs FY{{ form.compare_year }}/{{ (form.compare_year || 0) + 1 }}
              </span>
            </div>
            <div v-if="monthlyRows.length" class="chart-wrap">
              <ECharts
                :option="monthlyChartOption"
                autoresize
                class="monthly-chart"
              />
            </div>
            <div v-else class="text-center text-grey q-py-md">Tidak ada data</div>
          </q-card-section>
        </q-card>

        <!-- ── Tabel Tren Bulanan ── -->
        <q-card flat bordered square class="section-card">
          <q-card-section class="card-section">
            <div class="text-subtitle2 text-weight-bold q-mb-xs">Detail Tren Bulanan</div>
            <div class="table-scroll">
              <table class="data-table">
                <thead>
                  <tr>
                    <th class="text-left">Bulan</th>
                    <th class="text-right">
                      {{
                        form.compare_year
                          ? `FY ${form.fiscal_year}/${Number(form.fiscal_year || 0) + 1} (Rp)`
                          : 'Total (Rp)'
                      }}
                    </th>
                    <th class="text-right" v-if="form.compare_year">
                      {{ `FY ${form.compare_year}/${Number(form.compare_year || 0) + 1} (Rp)` }}
                    </th>
                    <th class="text-right" v-if="form.compare_year">Growth</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in monthlyRows" :key="row.month">
                    <td>{{ dayjs(`${row.month}-01`).format(form.compare_year ? 'MMM' : 'MMM YYYY') }}</td>
                    <td class="text-right">{{ formatNumber(row.current || 0) }}</td>
                    <td class="text-right" v-if="form.compare_year">{{ formatNumber(row.previous || 0) }}</td>
                    <td class="text-right" v-if="form.compare_year">
                      <span v-if="row.growth !== null" :class="row.growth >= 0 ? 'text-green-8 text-weight-bold' : 'text-red-8 text-weight-bold'">
                        {{ row.growth >= 0 ? '+' : '' }}{{ formatNumber(row.growth, 'id-ID', 1) }}%
                      </span>
                      <span v-else class="text-grey">-</span>
                    </td>
                  </tr>
                  <tr v-if="!monthlyRows.length">
                    <td :colspan="form.compare_year ? 4 : 2" class="text-center text-grey q-pa-sm">Tidak ada data</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </q-card-section>
        </q-card>

        <!-- ── Penjualan per BS & Distributor ── -->
        <div class="two-column-grid">
          <q-card flat bordered square class="section-card">
            <q-card-section class="card-section">
              <div class="text-subtitle2 text-weight-bold q-mb-xs">Penjualan per BS</div>
              <div class="table-scroll">
                <table class="data-table">
                  <thead><tr>
                    <th class="text-left">BS</th>
                    <th class="text-right" style="width:18%">Trx</th>
                    <th class="text-right" style="width:38%">Total (Rp)</th>
                  </tr></thead>
                  <tbody>
                    <tr v-for="row in salesByBS" :key="`bs-${row.id}`">
                      <td class="td-name">{{ row.user_name }}</td>
                      <td class="text-right">{{ formatNumber(row.transaction_count || 0) }}</td>
                      <td class="text-right">{{ formatNumber(row.total_sales || 0) }}</td>
                    </tr>
                    <tr v-if="!salesByBS.length"><td colspan="3" class="text-center text-grey q-pa-sm">Tidak ada data</td></tr>
                  </tbody>
                </table>
              </div>
            </q-card-section>
          </q-card>

          <q-card flat bordered square class="section-card">
            <q-card-section class="card-section">
              <div class="text-subtitle2 text-weight-bold q-mb-xs">Penjualan per Distributor</div>
              <div class="table-scroll">
                <table class="data-table">
                  <thead><tr>
                    <th class="text-left">Distributor</th>
                    <th class="text-right" style="width:18%">Trx</th>
                    <th class="text-right" style="width:38%">Total (Rp)</th>
                  </tr></thead>
                  <tbody>
                    <tr v-for="row in salesByDistributor" :key="`dist-${row.id}`">
                      <td class="td-name">{{ row.distributor_name }}</td>
                      <td class="text-right">{{ formatNumber(row.transaction_count || 0) }}</td>
                      <td class="text-right">{{ formatNumber(row.total_sales || 0) }}</td>
                    </tr>
                    <tr v-if="!salesByDistributor.length"><td colspan="3" class="text-center text-grey q-pa-sm">Tidak ada data</td></tr>
                  </tbody>
                </table>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- ── Penjualan per Produk & Top Performer ── -->
        <div class="two-column-grid">
          <q-card flat bordered square class="section-card">
            <q-card-section class="card-section">
              <div class="text-subtitle2 text-weight-bold q-mb-xs">Penjualan per Produk</div>
              <div class="table-scroll">
                <table class="data-table">
                  <thead><tr>
                    <th class="text-left">Produk</th>
                    <th class="text-right" style="width:22%">Qty</th>
                    <th class="text-right" style="width:38%">Total (Rp)</th>
                  </tr></thead>
                  <tbody>
                    <tr v-for="row in salesByProduct" :key="`prod-${row.id}`">
                      <td class="td-name">{{ row.product_name }}</td>
                      <td class="text-right">{{ formatNumber(row.total_quantity || 0, 'id-ID', 2) }}</td>
                      <td class="text-right">{{ formatNumber(row.total_sales || 0) }}</td>
                    </tr>
                    <tr v-if="!salesByProduct.length"><td colspan="3" class="text-center text-grey q-pa-sm">Tidak ada data</td></tr>
                  </tbody>
                </table>
              </div>
            </q-card-section>
          </q-card>

          <q-card flat bordered square class="section-card">
            <q-card-section class="card-section">
              <div class="text-subtitle2 text-weight-bold q-mb-xs">Top Performer</div>
              <div class="table-scroll">
                <table class="data-table">
                  <thead><tr>
                    <th class="text-left" style="width:24%">Tipe</th>
                    <th class="text-left">Nama</th>
                    <th class="text-right" style="width:38%">Total (Rp)</th>
                  </tr></thead>
                  <tbody>
                    <tr v-for="row in topDistributors" :key="`top-dist-${row.id}`">
                      <td><q-chip dense color="blue-1" text-color="blue-9" class="q-ma-none chip-sm">Dist</q-chip></td>
                      <td class="td-name">{{ row.name }}</td>
                      <td class="text-right">{{ formatNumber(row.total_sales || 0) }}</td>
                    </tr>
                    <tr v-for="row in topRetailers" :key="`top-ret-${row.id}`">
                      <td><q-chip dense color="green-1" text-color="green-9" class="q-ma-none chip-sm">Ret</q-chip></td>
                      <td class="td-name">{{ row.name }}</td>
                      <td class="text-right">{{ formatNumber(row.total_sales || 0) }}</td>
                    </tr>
                    <tr v-if="!topDistributors.length && !topRetailers.length"><td colspan="3" class="text-center text-grey q-pa-sm">Tidak ada data</td></tr>
                  </tbody>
                </table>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- ── Aktivitas vs Penjualan & Target vs Aktual ── -->
        <div class="two-column-grid">
          <q-card flat bordered square class="section-card">
            <q-card-section class="card-section">
              <div class="text-subtitle2 text-weight-bold q-mb-xs">Aktivitas vs Penjualan</div>
              <q-inner-loading :showing="loadingActivityVsSales">
                <q-spinner size="24px" color="primary" />
              </q-inner-loading>
              <div class="table-scroll">
                <table class="data-table">
                  <thead><tr>
                    <th class="text-left">Wilayah</th>
                    <th class="text-right" style="width:20%">Aktvts</th>
                    <th class="text-right" style="width:38%">Penjualan (Rp)</th>
                  </tr></thead>
                  <tbody>
                    <tr v-for="row in activityVsSales" :key="`avs-${row.province_id}`">
                      <td class="td-name">{{ row.province_name }}</td>
                      <td class="text-right">{{ formatNumber(row.activity_count || 0) }}</td>
                      <td class="text-right">{{ formatNumber(row.total_sales || 0) }}</td>
                    </tr>
                    <tr v-if="!activityVsSales.length && !loadingActivityVsSales"><td colspan="3" class="text-center text-grey q-pa-sm">Tidak ada data</td></tr>
                  </tbody>
                </table>
              </div>
            </q-card-section>
          </q-card>

          <q-card flat bordered square class="section-card">
            <q-card-section class="card-section">
              <div class="text-subtitle2 text-weight-bold q-mb-xs">Target vs Aktual</div>
              <div class="table-scroll">
                <table class="data-table">
                  <thead><tr>
                    <th class="text-left">Produk</th>
                    <th class="text-right" style="width:24%">Target</th>
                    <th class="text-right" style="width:24%">Aktual</th>
                    <th class="text-right" style="width:22%">Capai</th>
                  </tr></thead>
                  <tbody>
                    <tr v-for="row in targetVsActual" :key="`target-${row.product_name}`">
                      <td class="td-name">
                        {{ row.product_name }}
                        <span class="text-caption text-grey-6" v-if="row.uom">({{ row.uom }})</span>
                      </td>
                      <td class="text-right">{{ formatNumber(row.total_target || 0, 'id-ID', 2) }}</td>
                      <td class="text-right">{{ formatNumber(row.total_actual || 0, 'id-ID', 2) }}</td>
                      <td class="text-right">
                        <q-badge v-if="row.achievement !== null" :color="row.achievement >= 100 ? 'green-8' : 'orange-8'">
                          {{ formatNumber(row.achievement || 0, 'id-ID', 1) }}%
                        </q-badge>
                        <span v-else class="text-grey">-</span>
                      </td>
                    </tr>
                    <tr v-if="!targetVsActual.length"><td colspan="4" class="text-center text-grey q-pa-sm">Tidak ada data</td></tr>
                  </tbody>
                </table>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </q-page>
  </authenticated-layout>
</template>

<style scoped>
.analytics-page {
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
}

.analytics-content {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-card {
  width: 100%;
  max-width: 100%;
}

.card-section {
  padding: 16px;
}

.filters-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 12px;
  align-items: end;
}

.filter-field {
  width: 100%;
  min-width: 0;
}

.filter-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  width: 100%;
}

.action-btn {
  flex: 1 1 calc(50% - 6px);
  min-width: 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  width: 100%;
  max-width: 100%;
  align-items: stretch;
}

.stats-card {
  height: 100%;
  display: block;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  min-width: 0;
  border: 1px solid #dcdcdc !important;
}

.kpi-card-content {
  width: 100%;
  max-width: 100%;
  min-height: 122px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-start;
  min-width: 0;
  gap: 4px;
}

.kpi-card-content > * {
  max-width: 100%;
}

.kpi-card .kpi-value {
  width: 100%;
  max-width: 100%;
  font-size: clamp(0.92rem, 2.9vw, 1.15rem);
  line-height: 1.25;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kpi-card .kpi-prev {
  width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.kpi-card .q-badge {
  display: inline-flex;
  align-self: flex-start;
  width: fit-content;
  max-width: 100%;
  flex: 0 0 auto;
}

.chart-wrap {
  width: 100%;
  max-width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  overflow: hidden;
}

.monthly-chart {
  width: 100% !important;
  max-width: 100%;
  height: 220px;
}

.two-column-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 12px;
}

.table-scroll {
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior-x: contain;
}

.data-table {
  width: 100%;
  min-width: 540px;
  border-collapse: collapse;
  font-size: 0.78rem;
}

.data-table th {
  padding: 8px 10px;
  background: #f5f5f5;
  border-bottom: 1px solid #ddd;
  font-weight: 600;
  color: #444;
  white-space: nowrap;
}

.data-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #eeeeee;
  vertical-align: middle;
}

.data-table tr:last-child td {
  border-bottom: none;
}

.td-name {
  overflow-wrap: anywhere;
}

.chip-sm {
  font-size: 0.7rem;
  height: 18px;
  padding: 0 6px;
}

@media (min-width: 600px) {
  .filters-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .filter-actions {
    grid-column: span 2;
  }

  .action-btn {
    flex: 0 0 auto;
    min-width: 120px;
  }

  .monthly-chart {
    height: 260px;
  }

  .data-table {
    font-size: 0.84rem;
  }
}

@media (min-width: 1024px) {
  .filters-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .filter-actions {
    grid-column: 4;
    justify-content: flex-end;
  }

  .stats-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .two-column-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .monthly-chart {
    height: 280px;
  }
}
</style>
