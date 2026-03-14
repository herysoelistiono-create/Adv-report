<script setup>
import { usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ECharts from "vue-echarts";
import * as echarts from "echarts";

const data = computed(() => usePage().props.data ?? {});
const rows = computed(() => data.value.rows ?? []);
const activityTypes = computed(() => data.value.activity_types ?? []);
const grandTotal = computed(() => data.value.grand_total ?? 0);
const periodLabel = computed(() => data.value.period_label ?? "");
const summary = computed(() => data.value.summary ?? null);

const showDetail = ref(false);

const COLORS = [
  "#1976d2", "#43a047", "#f57c00", "#8e24aa",
  "#e53935", "#00897b", "#6d4c41", "#039be5",
  "#e91e63", "#00bcd4",
];

// Jumlahkan count per activity type lintas semua period untuk tiap BS
const bsTotals = computed(() =>
  rows.value.map((row) => {
    const typeTotals = {};
    activityTypes.value.forEach((at) => {
      typeTotals[at.id] = (row.data ?? []).reduce(
        (sum, period) => sum + (period.type_counts?.[at.id] ?? 0),
        0
      );
    });
    return { name: row.name, typeTotals, total: row.total };
  })
);

// Total per activity type lintas semua BS
const typeTotals = computed(() =>
  activityTypes.value.map((at, ci) => ({
    id: at.id,
    name: at.name,
    color: COLORS[ci % COLORS.length],
    total: bsTotals.value.reduce((sum, bs) => sum + (bs.typeTotals[at.id] ?? 0), 0),
  }))
);

// Best BS — jika ada summary (quarter/FY) pakai KPI, jika bulan pakai total kegiatan
const bestBS = computed(() => {
  if (summary.value?.rows?.length) {
    const withKpi = summary.value.rows.filter((r) => r.kpi !== null);
    if (withKpi.length) return withKpi.reduce((a, b) => (a.kpi >= b.kpi ? a : b));
  }
  if (!bsTotals.value.length) return null;
  return bsTotals.value.reduce((a, b) => (a.total >= b.total ? a : b));
});

// KPI overall (dari summary.totals)
const kpiOverall = computed(() => summary.value?.totals ?? null);

// Warna KPI
const kpiColor = (val) =>
  val === null ? "grey-5" : val >= 80 ? "positive" : val >= 60 ? "warning" : "negative";

// Singkat nama BS dengan alias yang diminta user
const shortName = (name) => {
  const n = (name ?? "").toLowerCase();
  if (n.includes("iing")) return "iing";
  if (n.includes("rifki")) return "rifki";
  if (n.includes("listianto")) return "listianto";
  if (n.includes("fatkhur")) return "Fatkhur";

  const first = (name ?? "").trim().split(/\s+/)[0] ?? "";
  return first.length > 10 ? first.slice(0, 9) + "…" : first;
};

// Stacked bar chart
const chartBarOption = computed(() => ({
  tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
  legend: { bottom: 0, type: "scroll", textStyle: { fontSize: 11 } },
  grid: { left: "4px", right: "4px", bottom: "46px", top: "6px", containLabel: true },
  xAxis: {
    type: "category",
    data: bsTotals.value.map((bs) => shortName(bs.name)),
    axisLabel: { fontSize: 11, interval: 0 },
    axisLine: { lineStyle: { color: "#ccc" } },
    axisTick: { show: false },
  },
  yAxis: {
    type: "value",
    minInterval: 1,
    splitLine: { lineStyle: { type: "dashed", color: "#eee" } },
    axisLabel: { fontSize: 11 },
  },
  series: activityTypes.value.map((at, ci) => ({
    name: at.name,
    type: "bar",
    stack: "total",
    barMaxWidth: 44,
    emphasis: { focus: "series" },
    itemStyle: { color: COLORS[ci % COLORS.length], borderRadius: ci === activityTypes.value.length - 1 ? [3, 3, 0, 0] : 0 },
    data: bsTotals.value.map((bs) => bs.typeTotals[at.id] ?? 0),
  })),
}));

// Donut chart
const chartDonutOption = computed(() => {
  const donutData = typeTotals.value.filter((t) => t.total > 0);
  return {
    title: {
      text: "Distribusi Jenis",
      left: "center",
      top: 4,
      textStyle: { fontSize: 12, color: "#555", fontWeight: "bold" },
    },
    tooltip: { trigger: "item", formatter: "{b}: {c} ({d}%)" },
    legend: {
      orient: "vertical",
      left: 2,
      top: "middle",
      textStyle: { fontSize: 10 },
      itemHeight: 9,
      itemWidth: 9,
    },
    series: [
      {
        name: "Jenis Kegiatan",
        type: "pie",
        radius: ["36%", "62%"],
        center: ["66%", "56%"],
        avoidLabelOverlap: true,
        label: { show: grandTotal.value > 0, formatter: "{c}", fontSize: 10, fontWeight: "bold" },
        emphasis: {
          label: { show: true, fontWeight: "bold", fontSize: 12 },
          itemStyle: { shadowBlur: 8, shadowColor: "rgba(0,0,0,0.1)" },
        },
        data: donutData.map((t) => ({
          name: t.name,
          value: t.total,
          itemStyle: { color: t.color },
        })),
      },
    ],
  };
});
</script>

<template>
  <div class="dashboard-shell">
    <!-- Period label -->
    <div class="text-caption text-grey-6 q-mb-sm flex items-center" style="min-width:0;overflow:hidden">
      <q-icon name="calendar_today" size="12px" class="q-mr-xs flex-shrink-0" />
      <span class="ellipsis">{{ periodLabel }}</span>
    </div>

    <!-- Empty state -->
    <div v-if="rows.length === 0" class="text-center text-grey-6 q-py-xl">
      <q-icon name="group_off" size="52px" class="text-grey-4 q-mb-sm" />
      <div class="text-body2">Tidak ada BS yang terdaftar di bawah Anda.</div>
    </div>

    <template v-else>

      <!-- ── Baris 1: Stat + Best BS + KPI Overall ── -->
      <div class="row q-col-gutter-sm q-mb-sm">

        <!-- Stat mini: 3 kolom -->
        <div class="col-12">
          <div class="row q-col-gutter-xs">
            <div class="col-4">
              <q-card flat bordered class="stat-card">
                <q-card-section class="q-pa-xs text-center">
                  <div class="stat-val text-primary">{{ grandTotal }}</div>
                  <div class="stat-lbl">Total Kegiatan</div>
                </q-card-section>
              </q-card>
            </div>
            <div class="col-4">
              <q-card flat bordered class="stat-card">
                <q-card-section class="q-pa-xs text-center">
                  <div class="stat-val text-teal">{{ rows.length }}</div>
                  <div class="stat-lbl">Jumlah BS</div>
                </q-card-section>
              </q-card>
            </div>
            <div class="col-4">
              <q-card flat bordered class="stat-card">
                <q-card-section class="q-pa-xs text-center">
                  <div class="stat-val text-orange">{{ typeTotals.filter((t) => t.total > 0).length }}</div>
                  <div class="stat-lbl">Jenis Aktif</div>
                </q-card-section>
              </q-card>
            </div>
          </div>
        </div>

        <!-- Best BS card -->
        <div v-if="bestBS" class="col-xs-12 col-sm-6">
          <q-card flat bordered class="best-bs-card">
            <q-card-section class="q-pa-sm">
              <div class="flex items-center q-mb-xs">
                <q-icon name="emoji_events" color="amber-8" size="20px" class="q-mr-xs" />
                <span class="text-caption text-bold text-grey-7">Best BS Periode Ini</span>
              </div>
              <div class="text-subtitle2 text-bold text-grey-9 ellipsis">{{ shortName(bestBS.name) }}</div>
              <div class="row q-col-gutter-xs q-mt-xs">
                <div class="col-6">
                  <div class="best-bs-stat">
                    <div class="text-weight-bold text-primary" style="font-size:1.1rem">
                      {{ bestBS.total ?? bestBS.total_actual ?? 0 }}
                    </div>
                    <div class="text-caption text-grey-6">Kegiatan</div>
                  </div>
                </div>
                <div class="col-6" v-if="bestBS.kpi !== undefined && bestBS.kpi !== null">
                  <div class="best-bs-stat">
                    <div
                      class="text-weight-bold"
                      :class="`text-${kpiColor(bestBS.kpi)}`"
                      style="font-size:1.1rem"
                    >
                      {{ bestBS.kpi }}%
                    </div>
                    <div class="text-caption text-grey-6">KPI</div>
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- KPI Overall summary (hanya quarter/FY) -->
        <div v-if="kpiOverall" class="col-xs-12 col-sm-6">
          <q-card flat bordered class="kpi-summary-card">
            <q-card-section class="q-pa-sm">
              <div class="flex items-center q-mb-xs">
                <q-icon name="track_changes" color="indigo-6" size="18px" class="q-mr-xs" />
                <span class="text-caption text-bold text-grey-7">Ringkasan KPI</span>
              </div>
              <div class="row items-center q-mb-xs">
                <div class="col">
                  <div class="text-caption text-grey-6">
                    {{ kpiOverall.total_actual }} / {{ kpiOverall.total_target }} kegiatan
                  </div>
                  <q-linear-progress
                    :value="kpiOverall.total_target > 0 ? Math.min(kpiOverall.total_actual / kpiOverall.total_target, 1) : 0"
                    :color="kpiColor(kpiOverall.kpi)"
                    rounded
                    size="10px"
                    class="q-mt-xs"
                  />
                </div>
                <div class="col-auto q-ml-sm">
                  <div
                    class="kpi-badge"
                    :class="`kpi-badge--${kpiColor(kpiOverall.kpi)}`"
                  >
                    {{ kpiOverall.kpi !== null ? kpiOverall.kpi + "%" : "—" }}
                  </div>
                </div>
              </div>
              <!-- Per-type mini breakdown -->
              <div class="row q-col-gutter-xs">
                <template v-for="(at, ci) in activityTypes" :key="at.id">
                  <div
                    v-if="(kpiOverall.type_targets?.[at.id] ?? 0) > 0"
                    class="col-6"
                  >
                    <div class="type-kpi-row">
                      <span class="type-dot" :style="{ background: COLORS[ci % COLORS.length] }"></span>
                      <span class="text-caption text-grey-7 ellipsis" style="max-width:60px">{{ at.name }}</span>
                      <span class="text-caption text-grey-8 text-bold q-ml-auto">
                        {{ kpiOverall.type_actuals?.[at.id] ?? 0 }}/{{ kpiOverall.type_targets?.[at.id] ?? 0 }}
                      </span>
                    </div>
                  </div>
                </template>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- ── Baris 2: Charts ── -->
      <div class="row q-col-gutter-sm q-mb-sm">
        <div class="col-xs-12 col-sm-7" style="min-width:0">
          <q-card flat bordered class="bg-white">
            <q-card-section class="q-pa-sm">
              <div class="chart-title">Kegiatan per BS</div>
              <ECharts :option="chartBarOption" autoresize style="height: 210px; width: 100%; min-width: 0" />
            </q-card-section>
          </q-card>
        </div>
        <div class="col-xs-12 col-sm-5" style="min-width:0">
          <q-card flat bordered class="bg-white">
            <q-card-section class="q-pa-sm">
              <ECharts :option="chartDonutOption" autoresize style="height: 240px; width: 100%; min-width: 0" />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- ── Baris 3: KPI per BS (quarter/FY) ── -->
      <div v-if="summary" class="q-mb-sm">
        <q-card flat bordered class="bg-white">
          <q-card-section class="q-pa-sm">
            <div class="chart-title q-mb-sm">KPI per BS</div>
            <div class="row q-col-gutter-xs">
              <div
                v-for="sr in summary.rows"
                :key="sr.name"
                class="col-xs-6 col-sm-4 col-md-3"
              >
                <div class="kpi-bs-item">
                  <div class="kpi-bs-name ellipsis">{{ shortName(sr.name) }}</div>
                  <div class="text-caption text-grey-6 q-mb-xs">
                    {{ sr.total_actual }} / {{ sr.total_target }}
                  </div>
                  <q-linear-progress
                    :value="sr.total_target > 0 ? Math.min(sr.total_actual / sr.total_target, 1) : 0"
                    :color="kpiColor(sr.kpi)"
                    rounded
                    size="7px"
                  />
                  <div
                    class="text-caption text-right q-mt-xs text-bold"
                    :class="`text-${kpiColor(sr.kpi)}`"
                  >
                    {{ sr.kpi !== null ? sr.kpi + "%" : "—" }}
                  </div>
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- ── Toggle & Tabel Detail ── -->
      <div class="detail-toggle q-mb-xs" @click="showDetail = !showDetail">
        <q-icon :name="showDetail ? 'expand_less' : 'expand_more'" size="16px" class="q-mr-xs" />
        {{ showDetail ? "Sembunyikan Detail" : "Tampilkan Detail" }}
      </div>

      <q-slide-transition>
        <div v-show="showDetail" class="table-wrapper">
          <table class="agro-table">
            <thead>
              <tr>
                <th class="col-name">Jenis</th>
                <th v-for="bs in bsTotals" :key="bs.name" class="col-bs">{{ shortName(bs.name) }}</th>
                <th class="col-bs col-total-head">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(at, ci) in activityTypes" :key="at.id">
                <td class="col-name">
                  <span class="type-dot" :style="{ background: COLORS[ci % COLORS.length] }"></span>{{ at.name }}
                </td>
                <td v-for="bs in bsTotals" :key="bs.name" class="col-num">
                  <span :class="(bs.typeTotals[at.id] ?? 0) > 0 ? 'num-active' : 'num-zero'">{{ bs.typeTotals[at.id] ?? 0 }}</span>
                </td>
                <td class="col-num col-total">
                  <span :class="typeTotals[ci].total > 0 ? 'num-total' : 'num-zero'">{{ typeTotals[ci].total }}</span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="footer-row">
                <td class="col-name"><strong>Total</strong></td>
                <td v-for="bs in bsTotals" :key="bs.name" class="col-num col-grand">{{ bs.total }}</td>
                <td class="col-num col-grand">{{ grandTotal }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </q-slide-transition>

    </template>
  </div>
</template>

<style scoped>
.dashboard-shell {
  width: 100%;
  max-width: 100%;
  overflow-x: clip;
}

/* ── Stat mini ── */
.stat-card { border-radius: 6px; }
.stat-val {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.2;
  min-height: 1.4rem;
}
.stat-lbl {
  font-size: 0.66rem;
  color: #9e9e9e;
  line-height: 1.3;
  white-space: nowrap;
}

/* ── Best BS ── */
.best-bs-card {
  border-radius: 8px;
  background: linear-gradient(135deg, #fff9e6 0%, #ffffff 100%);
  border-color: #ffe082 !important;
}
.best-bs-stat {
  background: #f5f5f5;
  border-radius: 6px;
  padding: 4px 8px;
  text-align: center;
}

/* ── KPI Summary ── */
.kpi-summary-card {
  border-radius: 8px;
  background: linear-gradient(135deg, #f3f0ff 0%, #ffffff 100%);
  border-color: #b39ddb !important;
}
.kpi-badge {
  font-size: 1.1rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 20px;
  white-space: nowrap;
}
.kpi-badge--positive    { background: #e8f5e9; color: #2e7d32; }
.kpi-badge--warning     { background: #fff8e1; color: #e65100; }
.kpi-badge--negative    { background: #ffebee; color: #c62828; }
.kpi-badge--grey-5      { background: #f5f5f5; color: #757575; }

.type-kpi-row {
  display: flex;
  align-items: center;
  gap: 4px;
  background: #fafafa;
  border-radius: 4px;
  padding: 2px 6px;
}

/* ── Chart title ── */
.chart-title {
  font-size: 0.78rem;
  font-weight: 600;
  color: #616161;
}

/* ── KPI per BS ── */
.kpi-bs-item {
  border: 1px solid #eeeeee;
  border-radius: 6px;
  background: #fafafa;
  padding: 6px 8px;
}
.kpi-bs-name {
  font-size: 0.78rem;
  font-weight: 600;
  color: #424242;
}

/* ── Toggle ── */
.detail-toggle {
  display: inline-flex;
  align-items: center;
  font-size: 0.76rem;
  color: #1976d2;
  cursor: pointer;
  user-select: none;
  padding: 2px 6px;
  border-radius: 4px;
  transition: background 0.15s;
}
.detail-toggle:hover { background: #e3f2fd; }

/* ── Table ── */
.table-wrapper {
  width: 100%;
  overflow: hidden;
}
.agro-table {
  width: 100%;
  table-layout: fixed;
  border-collapse: collapse;
  font-size: 0.74rem;
}
.agro-table th,
.agro-table td {
  box-sizing: border-box;
  padding: 4px 5px;
  border: 1px solid #e8e8e8;
  overflow: hidden;
}
/* Header — rata kanan kecuali col-name */
.agro-table th {
  background: #f5f5f5;
  text-align: right;
  font-weight: 600;
  font-size: 0.7rem;
  overflow: hidden;
  text-overflow: ellipsis;
}
/* Kolom Jenis — kiri, lebar 36% */
.agro-table th.col-name,
.agro-table td.col-name {
  text-align: left;
  width: 36%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
/* Kolom angka BS & Total — rata kanan */
.agro-table td.col-num {
  text-align: right;
}
.agro-table th.col-bs,
.agro-table th.col-total-head {
  text-align: right;
}
/* Kolom Total — latar hijau muda */
.agro-table td.col-total,
.agro-table th.col-total-head {
  background: #f1f8e9;
}
.footer-row td {
  background: #e8eaf6;
  border-top: 2px solid #9fa8da;
}

/* ── Dot & Angka ── */
.type-dot {
  display: inline-block;
  width: 7px; height: 7px;
  border-radius: 50%;
  margin-right: 4px;
  flex-shrink: 0;
  vertical-align: middle;
}
.num-active { color: #1565c0; font-weight: 700; }
.num-total  { color: #2e7d32; font-weight: 700; }
.num-zero   { color: #bdbdbd; }
.col-grand  { color: #1565c0; font-weight: 700; }

/* ── Prevent outer horizontal scroll ── */
:deep(.q-card) { min-width: 0; }
:deep(.row.q-col-gutter-sm),
:deep(.row.q-col-gutter-xs) {
  margin-left: 0 !important;
  margin-right: 0 !important;
}
:deep(.row.q-col-gutter-sm > [class*='col-']),
:deep(.row.q-col-gutter-xs > [class*='col-']) {
  padding-left: 4px !important;
  padding-right: 4px !important;
}

/* ── Mobile tweaks ── */
@media (max-width: 599px) {
  .stat-val { font-size: 1rem; }
  .stat-lbl { font-size: 0.6rem; }
  .kpi-badge { font-size: 0.9rem; padding: 2px 7px; }
  .type-kpi-row { padding: 2px 4px; }
  .agro-table { font-size: 0.68rem; }
  .agro-table th { font-size: 0.64rem; }
  .agro-table th,
  .agro-table td { padding: 3px 4px; }
}
</style>
