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

const showDetail = ref(true);

const COLORS = [
  "#1976d2", "#43a047", "#f57c00", "#8e24aa",
  "#e53935", "#00897b", "#6d4c41", "#039be5",
  "#f48fb1", "#a5d6a7",
];

// Untuk setiap BS, jumlahkan count per activity type lintas semua period
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

// Stacked bar chart: X = Nama BS, bertumpuk per jenis kegiatan
const chartBarOption = computed(() => ({
  tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
  legend: { bottom: 0, type: "scroll", textStyle: { fontSize: 11 } },
  grid: { left: "5px", right: "5px", bottom: "52px", top: "8px", containLabel: true },
  xAxis: {
    type: "category",
    data: bsTotals.value.map((bs) => bs.name),
    axisLabel: { fontSize: 11, interval: 0 },
    axisLine: { lineStyle: { color: "#bbb" } },
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
    barMaxWidth: 48,
    emphasis: { focus: "series" },
    itemStyle: { color: COLORS[ci % COLORS.length] },
    data: bsTotals.value.map((bs) => bs.typeTotals[at.id] ?? 0),
  })),
}));

// Donut chart: Distribusi Jenis
const chartDonutOption = computed(() => {
  const donutData = typeTotals.value.filter((t) => t.total > 0);
  return {
    title: {
      text: "Distribusi Jenis",
      left: "center",
      top: 4,
      textStyle: { fontSize: 13, color: "#444", fontWeight: "bold" },
    },
    tooltip: { trigger: "item", formatter: "{b}: {c} ({d}%)" },
    legend: {
      orient: "vertical",
      left: 4,
      top: "middle",
      textStyle: { fontSize: 11 },
      itemHeight: 10,
      itemWidth: 10,
    },
    series: [
      {
        name: "Jenis Kegiatan",
        type: "pie",
        radius: ["38%", "65%"],
        center: ["65%", "56%"],
        avoidLabelOverlap: true,
        label: { show: true, formatter: "{b}\n{c}", fontSize: 10, lineHeight: 14 },
        emphasis: {
          label: { show: true, fontWeight: "bold" },
          itemStyle: { shadowBlur: 8, shadowOffsetX: 0, shadowColor: "rgba(0,0,0,0.15)" },
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
  <div>
    <!-- Period label -->
    <div class="text-caption text-grey-6 q-mb-sm">{{ periodLabel }}</div>

    <!-- Empty state -->
    <div v-if="rows.length === 0" class="text-center text-grey-7 q-py-xl">
      <q-icon name="group" size="48px" class="q-mb-sm text-grey-4" />
      <div class="text-body2">Tidak ada BS yang terdaftar di bawah Anda.</div>
    </div>

    <template v-else>
      <!-- Stat mini cards -->
      <div class="row q-col-gutter-sm q-mb-sm">
        <div class="col-4">
          <q-card square bordered class="no-shadow stat-mini">
            <q-card-section class="q-pa-sm text-center">
              <div class="text-h6 text-primary text-bold">{{ grandTotal }}</div>
              <div class="text-caption text-grey-7">Total Kegiatan</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-4">
          <q-card square bordered class="no-shadow stat-mini">
            <q-card-section class="q-pa-sm text-center">
              <div class="text-h6 text-teal text-bold">{{ rows.length }}</div>
              <div class="text-caption text-grey-7">Jumlah BS</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-4">
          <q-card square bordered class="no-shadow stat-mini">
            <q-card-section class="q-pa-sm text-center">
              <div class="text-h6 text-orange text-bold">
                {{ typeTotals.filter((t) => t.total > 0).length }}
              </div>
              <div class="text-caption text-grey-7">Jenis Aktif</div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Charts -->
      <div class="row q-col-gutter-sm q-mb-sm">
        <div class="col-xs-12 col-sm-7">
          <q-card square bordered class="no-shadow bg-white">
            <q-card-section class="q-pa-sm">
              <div class="text-caption text-bold text-grey-7 q-mb-xs">Kegiatan per BS</div>
              <ECharts :option="chartBarOption" autoresize style="height: 230px; width: 100%" />
            </q-card-section>
          </q-card>
        </div>
        <div class="col-xs-12 col-sm-5">
          <q-card square bordered class="no-shadow bg-white">
            <q-card-section class="q-pa-sm">
              <ECharts :option="chartDonutOption" autoresize style="height: 262px; width: 100%" />
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- KPI cards (hanya untuk quarter & fiscal_year) -->
      <div v-if="summary" class="q-mb-sm">
        <q-card square bordered class="no-shadow bg-white">
          <q-card-section class="q-pa-sm">
            <div class="text-caption text-bold text-grey-7 q-mb-sm">KPI per BS</div>
            <div class="row q-col-gutter-xs">
              <div
                v-for="sr in summary.rows"
                :key="sr.name"
                class="col-xs-6 col-sm-4 col-md-3"
              >
                <div class="kpi-bs-item q-pa-xs rounded-borders">
                  <div class="text-caption text-grey-8 text-bold ellipsis">{{ sr.name }}</div>
                  <div class="text-caption text-grey-6 q-mb-xs">
                    {{ sr.total_actual }} / {{ sr.total_target }} kegiatan
                  </div>
                  <q-linear-progress
                    :value="sr.total_target > 0 ? Math.min(sr.total_actual / sr.total_target, 1) : 0"
                    :color="sr.kpi !== null && sr.kpi >= 80 ? 'positive' : sr.kpi !== null && sr.kpi >= 60 ? 'warning' : 'negative'"
                    rounded
                    size="8px"
                  />
                  <div
                    class="text-caption text-right q-mt-xs"
                    :class="sr.kpi !== null && sr.kpi >= 80 ? 'text-positive' : sr.kpi !== null && sr.kpi >= 60 ? 'text-warning' : 'text-negative'"
                  >
                    {{ sr.kpi !== null ? sr.kpi + "%" : "-" }}
                  </div>
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>

      <!-- Toggle detail -->
      <div class="detail-toggle q-mb-xs" @click="showDetail = !showDetail">
        <q-icon :name="showDetail ? 'expand_less' : 'expand_more'" size="16px" class="q-mr-xs" />
        {{ showDetail ? "Sembunyikan Detail" : "Tampilkan Detail" }}
      </div>

      <!-- Detail table -->
      <q-slide-transition>
        <div v-show="showDetail" class="table-wrapper">
          <table class="agro-table">
            <thead>
              <tr>
                <th class="col-name">Jenis Kegiatan</th>
                <th v-for="bs in bsTotals" :key="bs.name">{{ bs.name }}</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(at, ci) in activityTypes" :key="at.id">
                <td class="col-name">
                  <span class="type-dot" :style="{ background: COLORS[ci % COLORS.length] }"></span>
                  {{ at.name }}
                </td>
                <td v-for="bs in bsTotals" :key="bs.name" class="text-center">
                  <span
                    :class="
                      (bs.typeTotals[at.id] ?? 0) > 0
                        ? 'count-badge'
                        : 'text-grey-4'
                    "
                  >
                    {{ bs.typeTotals[at.id] ?? 0 }}
                  </span>
                </td>
                <td class="text-center">
                  <span :class="typeTotals[ci].total > 0 ? 'count-total' : 'text-grey-4'">
                    {{ typeTotals[ci].total }}
                  </span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="footer-row">
                <td class="col-name text-bold">Total</td>
                <td v-for="bs in bsTotals" :key="bs.name" class="text-center text-bold text-primary">
                  {{ bs.total }}
                </td>
                <td class="text-center text-bold text-primary">{{ grandTotal }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </q-slide-transition>
    </template>
  </div>
</template>

<style scoped>
/* Stat mini */
.stat-mini {
  border-radius: 6px;
}

/* Toggle */
.detail-toggle {
  display: inline-flex;
  align-items: center;
  font-size: 0.78rem;
  color: #1976d2;
  cursor: pointer;
  user-select: none;
  padding: 2px 6px;
  border-radius: 4px;
  transition: background 0.15s;
}
.detail-toggle:hover {
  background: #e3f2fd;
}

/* Table */
.table-wrapper {
  overflow-x: auto;
}
.agro-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}
.agro-table th,
.agro-table td {
  padding: 5px 10px;
  border: 1px solid #e8e8e8;
  white-space: nowrap;
}
.agro-table th {
  background: #f5f5f5;
  text-align: center;
  font-weight: 600;
  font-size: 0.8rem;
}
.agro-table th.col-name,
.agro-table td.col-name {
  text-align: left;
  min-width: 140px;
}
.footer-row td {
  background: #f0f4ff;
  border-top: 2px solid #c5cae9;
}

/* Type dot in table */
.type-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 5px;
  vertical-align: middle;
}

/* Count badge */
.count-badge {
  display: inline-block;
  background: #e3f2fd;
  color: #1565c0;
  font-weight: 700;
  border-radius: 10px;
  padding: 1px 8px;
  font-size: 0.78rem;
}
.count-total {
  display: inline-block;
  background: #e8f5e9;
  color: #2e7d32;
  font-weight: 700;
  border-radius: 10px;
  padding: 1px 8px;
  font-size: 0.78rem;
}

/* KPI item */
.kpi-bs-item {
  border: 1px solid #eeeeee;
  border-radius: 6px;
  background: #fafafa;
}
</style>

