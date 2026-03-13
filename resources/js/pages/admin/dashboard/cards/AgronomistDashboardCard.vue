<script setup>
import { usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import ECharts from "vue-echarts";
import * as echarts from "echarts";

const data = computed(() => usePage().props.data ?? {});
const rows = computed(() => data.value.rows ?? []);
const columns = computed(() => data.value.columns ?? []);
const columnTotals = computed(() => data.value.column_totals ?? []);
const grandTotal = computed(() => data.value.grand_total ?? 0);
const periodLabel = computed(() => data.value.period_label ?? "");

const showDetail = ref(true);

const COLORS = [
  "#1976d2", "#43a047", "#f57c00", "#8e24aa",
  "#e53935", "#00897b", "#6d4c41", "#039be5",
];

// Stacked bar chart: X = BS users, Y = count, stacked per activity type
const chartBarOption = computed(() => ({
  tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
  legend: { bottom: 0, type: "scroll", textStyle: { fontSize: 11 } },
  grid: { left: "5px", right: "5px", bottom: "40px", top: "10px", containLabel: true },
  xAxis: {
    type: "category",
    data: rows.value.map((r) => r.name),
    axisLabel: { fontSize: 11 },
  },
  yAxis: {
    type: "value",
    minInterval: 1,
    splitLine: { lineStyle: { type: "dashed", color: "#ddd" } },
  },
  series: columns.value.map((col, ci) => ({
    name: col,
    type: "bar",
    stack: "total",
    emphasis: { focus: "series" },
    itemStyle: { color: COLORS[ci % COLORS.length] },
    data: rows.value.map((r) => r.counts[ci] ?? 0),
  })),
}));

// Donut chart: Distribusi Jenis
const chartDonutOption = computed(() => ({
  title: {
    text: "Distribusi Jenis",
    left: "center",
    textStyle: { fontSize: 13, color: "#555", fontWeight: "bold" },
  },
  tooltip: { trigger: "item", formatter: "{b}: {c} ({d}%)" },
  legend: { orient: "vertical", left: "left", textStyle: { fontSize: 11 } },
  series: [
    {
      name: "Jenis Kegiatan",
      type: "pie",
      radius: ["38%", "65%"],
      center: ["55%", "55%"],
      avoidLabelOverlap: true,
      label: { show: true, formatter: "{b}: {c}", fontSize: 11 },
      emphasis: { label: { show: true, fontWeight: "bold" } },
      data: columns.value
        .map((col, ci) => ({
          name: col,
          value: columnTotals.value[ci] ?? 0,
          itemStyle: { color: COLORS[ci % COLORS.length] },
        }))
        .filter((d) => d.value > 0),
    },
  ],
}));
</script>

<template>
  <div>
    <div class="text-caption text-grey-6 q-mb-sm">{{ periodLabel }}</div>

    <div v-if="rows.length === 0" class="text-center text-grey-7 q-py-lg">
      <q-icon name="group" size="32px" class="q-mb-sm" />
      <div>Tidak ada BS yang terdaftar di bawah Anda.</div>
    </div>

    <template v-else>
      <!-- Stacked Bar Chart per BS -->
      <q-card square bordered class="no-shadow bg-white q-mb-sm">
        <q-card-section class="q-pa-sm">
          <ECharts :option="chartBarOption" autoresize style="height: 240px; width: 100%" />
        </q-card-section>
      </q-card>

      <!-- Donut Chart Distribusi Jenis -->
      <q-card square bordered class="no-shadow bg-white q-mb-sm">
        <q-card-section class="q-pa-sm">
          <ECharts :option="chartDonutOption" autoresize style="height: 260px; width: 100%" />
        </q-card-section>
      </q-card>

      <!-- Toggle Detail -->
      <div
        class="text-caption text-primary cursor-pointer q-mb-xs flex items-center"
        @click="showDetail = !showDetail"
      >
        <q-icon :name="showDetail ? 'expand_less' : 'expand_more'" size="16px" class="q-mr-xs" />
        {{ showDetail ? "Sembunyikan Detail" : "Tampilkan Detail" }}
      </div>

      <!-- Detail Table -->
      <div v-show="showDetail" class="table-wrapper">
        <table class="agro-table">
          <thead>
            <tr>
              <th class="col-name">Jenis Kegiatan</th>
              <th v-for="row in rows" :key="row.name">{{ row.name }}</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(col, ci) in columns" :key="col">
              <td class="col-name">{{ col }}</td>
              <td v-for="row in rows" :key="row.name" class="text-center">
                <span :class="row.counts[ci] > 0 ? 'text-primary text-bold' : 'text-grey-5'">
                  {{ row.counts[ci] ?? 0 }}
                </span>
              </td>
              <td class="text-center text-bold">{{ columnTotals[ci] ?? 0 }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="footer-row">
              <td class="col-name text-bold">Total</td>
              <td v-for="row in rows" :key="row.name" class="text-center text-bold">
                {{ row.total }}
              </td>
              <td class="text-center text-bold">{{ grandTotal }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.table-wrapper {
  overflow-x: auto;
}
.agro-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.agro-table th,
.agro-table td {
  padding: 5px 10px;
  border: 1px solid #e0e0e0;
  white-space: nowrap;
}
.agro-table th {
  background: #f5f5f5;
  text-align: center;
  font-weight: 600;
}
.agro-table th.col-name,
.agro-table td.col-name {
  text-align: left;
  min-width: 140px;
}
.footer-row td {
  background: #fafafa;
  border-top: 2px solid #bdbdbd;
}
</style>
