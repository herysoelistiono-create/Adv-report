<script setup>
import { usePage } from "@inertiajs/vue3";
import { computed } from "vue";

const data = computed(() => usePage().props.data ?? {});
const rows = computed(() => data.value.rows ?? []);
const columns = computed(() => data.value.columns ?? []);
const columnTotals = computed(() => data.value.column_totals ?? []);
const grandTotal = computed(() => data.value.grand_total ?? 0);
const periodLabel = computed(() => data.value.period_label ?? "");
</script>

<template>
  <div>
    <div class="text-caption text-grey-6 q-mb-sm">{{ periodLabel }}</div>

    <div v-if="rows.length === 0" class="text-center text-grey-7 q-py-lg">
      <q-icon name="group" size="32px" class="q-mb-sm" />
      <div>Tidak ada BS yang terdaftar di bawah Anda.</div>
    </div>

    <div v-else class="table-wrapper">
      <table class="agro-table">
        <thead>
          <tr>
            <th class="col-name">Nama BS</th>
            <th v-for="col in columns" :key="col">{{ col }}</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.name">
            <td class="col-name">{{ row.name }}</td>
            <td v-for="(count, i) in row.counts" :key="i" class="text-center">
              <span :class="count > 0 ? 'text-primary text-bold' : 'text-grey-5'">
                {{ count }}
              </span>
            </td>
            <td class="text-center text-bold">{{ row.total }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="footer-row">
            <td class="col-name text-bold">Total</td>
            <td v-for="(t, i) in columnTotals" :key="i" class="text-center text-bold">
              {{ t }}
            </td>
            <td class="text-center text-bold">{{ grandTotal }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
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
