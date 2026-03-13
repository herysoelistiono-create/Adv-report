<script setup>
import { computed, reactive, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import axios from "axios";
import dayjs from "dayjs";
import { create_month_options, formatNumber } from "@/helpers/utils";

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
  const base = form.fiscal_year || defaultFiscalYear;
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

const monthlyRows = computed(() => {
  const prevMap = {};
  prevMonthlySales.value.forEach((item) => {
    prevMap[item.month] = Number(item.total_sales || 0);
  });

  return monthlySales.value.map((item) => {
    const current = Number(item.total_sales || 0);
    const prev = Number(prevMap[item.month] || 0);
    return {
      month: item.month,
      current,
      previous: prev,
      growth: pctChange(current, prev),
      transactions: Number(item.transaction_count || 0),
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
</script>

<template>
  <i-head :title="title" />
  <authenticated-layout>
    <template #title>{{ title }}</template>

    <q-page class="q-pa-sm">
      <q-card flat bordered square>
        <q-card-section>
          <div class="row q-col-gutter-sm items-end">
            <div class="col-xs-12 col-sm-4 col-md-3">
              <q-select
                v-model="form.fiscal_year"
                label="Fiscal Year"
                :options="fiscalYearOptions"
                map-options
                emit-value
                outlined
                dense
              />
            </div>

            <div class="col-xs-12 col-sm-4 col-md-3">
              <q-select
                v-model="form.month"
                label="Bulan"
                :options="monthOptions"
                map-options
                emit-value
                outlined
                dense
              />
            </div>

            <div class="col-xs-12 col-sm-4 col-md-3">
              <q-select
                v-model="form.compare_year"
                label="Compare Year"
                :options="compareYearOptions"
                map-options
                emit-value
                outlined
                dense
              />
            </div>

            <div class="col-xs-12 col-sm-12 col-md-3 row q-gutter-sm">
              <q-btn color="primary" icon="search" label="Terapkan" @click="applyFilters" />
              <q-btn color="grey-7" flat icon="refresh" label="Reset" @click="resetFilters" />
            </div>
          </div>
        </q-card-section>
      </q-card>

      <div class="row q-col-gutter-sm q-mt-sm">
        <div class="col-xs-12 col-sm-6 col-md-3">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-caption text-grey-7">Total Penjualan</div>
              <div class="text-h6 text-weight-bold">Rp {{ formatNumber(currentStats.total_sales || 0) }}</div>
              <div class="text-caption" v-if="prevStats">
                Prev: Rp {{ formatNumber(prevStats.total_sales || 0) }}
                <span class="q-ml-xs" :class="pctChange(currentStats.total_sales, prevStats.total_sales) >= 0 ? 'text-green-8' : 'text-red-8'">
                  ({{ formatNumber(Math.abs(pctChange(currentStats.total_sales, prevStats.total_sales) || 0), 'id-ID', 1) }}%)
                </span>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-caption text-grey-7">Jumlah Transaksi</div>
              <div class="text-h6 text-weight-bold">{{ formatNumber(currentStats.total_transactions || 0) }}</div>
              <div class="text-caption" v-if="prevStats">
                Prev: {{ formatNumber(prevStats.total_transactions || 0) }}
                <span class="q-ml-xs" :class="pctChange(currentStats.total_transactions, prevStats.total_transactions) >= 0 ? 'text-green-8' : 'text-red-8'">
                  ({{ formatNumber(Math.abs(pctChange(currentStats.total_transactions, prevStats.total_transactions) || 0), 'id-ID', 1) }}%)
                </span>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-caption text-grey-7">Distributor Aktif</div>
              <div class="text-h6 text-weight-bold">{{ formatNumber(currentStats.active_distributors || 0) }}</div>
              <div class="text-caption" v-if="prevStats">
                Prev: {{ formatNumber(prevStats.active_distributors || 0) }}
              </div>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-3">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-caption text-grey-7">Total Qty</div>
              <div class="text-h6 text-weight-bold">{{ formatNumber(currentStats.total_qty || 0, 'id-ID', 2) }}</div>
              <div class="text-caption" v-if="prevStats">
                Prev: {{ formatNumber(prevStats.total_qty || 0, 'id-ID', 2) }}
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div class="row q-col-gutter-sm q-mt-sm">
        <div class="col-12 col-lg-6">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">Penjualan per BS</div>
              <q-markup-table flat bordered square separator="cell">
                <thead>
                  <tr>
                    <th class="text-left">BS</th>
                    <th class="text-right">Transaksi</th>
                    <th class="text-right">Total (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in salesByBS" :key="`bs-${row.id}`">
                    <td>{{ row.user_name }}</td>
                    <td class="text-right">{{ formatNumber(row.transaction_count || 0) }}</td>
                    <td class="text-right">Rp {{ formatNumber(row.total_sales || 0) }}</td>
                  </tr>
                  <tr v-if="!salesByBS.length">
                    <td colspan="3" class="text-center text-grey">Tidak ada data</td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-12 col-lg-6">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">Penjualan per Distributor</div>
              <q-markup-table flat bordered square separator="cell">
                <thead>
                  <tr>
                    <th class="text-left">Distributor</th>
                    <th class="text-right">Transaksi</th>
                    <th class="text-right">Total (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in salesByDistributor" :key="`dist-${row.id}`">
                    <td>{{ row.distributor_name }}</td>
                    <td class="text-right">{{ formatNumber(row.transaction_count || 0) }}</td>
                    <td class="text-right">Rp {{ formatNumber(row.total_sales || 0) }}</td>
                  </tr>
                  <tr v-if="!salesByDistributor.length">
                    <td colspan="3" class="text-center text-grey">Tidak ada data</td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div class="row q-col-gutter-sm q-mt-sm">
        <div class="col-12 col-lg-6">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">Penjualan per Produk</div>
              <q-markup-table flat bordered square separator="cell">
                <thead>
                  <tr>
                    <th class="text-left">Produk</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Total (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in salesByProduct" :key="`prod-${row.id}`">
                    <td>{{ row.product_name }}</td>
                    <td class="text-right">{{ formatNumber(row.total_quantity || 0, 'id-ID', 2) }}</td>
                    <td class="text-right">Rp {{ formatNumber(row.total_sales || 0) }}</td>
                  </tr>
                  <tr v-if="!salesByProduct.length">
                    <td colspan="3" class="text-center text-grey">Tidak ada data</td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-12 col-lg-6">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">Top Performer</div>
              <q-markup-table flat bordered square separator="cell">
                <thead>
                  <tr>
                    <th class="text-left">Kategori</th>
                    <th class="text-left">Nama</th>
                    <th class="text-right">Total (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in topDistributors" :key="`top-dist-${row.id}`">
                    <td>Distributor</td>
                    <td>{{ row.name }}</td>
                    <td class="text-right">Rp {{ formatNumber(row.total_sales || 0) }}</td>
                  </tr>
                  <tr v-for="row in topRetailers" :key="`top-ret-${row.id}`">
                    <td>Retailer</td>
                    <td>{{ row.name }}</td>
                    <td class="text-right">Rp {{ formatNumber(row.total_sales || 0) }}</td>
                  </tr>
                  <tr v-if="!topDistributors.length && !topRetailers.length">
                    <td colspan="3" class="text-center text-grey">Tidak ada data</td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div class="row q-col-gutter-sm q-mt-sm">
        <div class="col-12">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">Tren Penjualan Bulanan</div>
              <q-markup-table flat bordered square separator="cell">
                <thead>
                  <tr>
                    <th class="text-left">Bulan</th>
                    <th class="text-right">Transaksi</th>
                    <th class="text-right">Total (Rp)</th>
                    <th class="text-right">Prev Total (Rp)</th>
                    <th class="text-right">Growth</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in monthlyRows" :key="row.month">
                    <td>{{ dayjs(`${row.month}-01`).format('MMM YYYY') }}</td>
                    <td class="text-right">{{ formatNumber(row.transactions || 0) }}</td>
                    <td class="text-right">Rp {{ formatNumber(row.current || 0) }}</td>
                    <td class="text-right">Rp {{ formatNumber(row.previous || 0) }}</td>
                    <td class="text-right">
                      <span v-if="row.growth !== null" :class="row.growth >= 0 ? 'text-green-8' : 'text-red-8'">
                        {{ row.growth >= 0 ? '+' : '-' }}{{ formatNumber(Math.abs(row.growth), 'id-ID', 1) }}%
                      </span>
                      <span v-else>-</span>
                    </td>
                  </tr>
                  <tr v-if="!monthlyRows.length">
                    <td colspan="5" class="text-center text-grey">Tidak ada data</td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div class="row q-col-gutter-sm q-mt-sm">
        <div class="col-12 col-lg-6">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">Aktivitas vs Penjualan per Wilayah</div>
              <q-inner-loading :showing="loadingActivityVsSales">
                <q-spinner size="32px" color="primary" />
              </q-inner-loading>
              <q-markup-table flat bordered square separator="cell">
                <thead>
                  <tr>
                    <th class="text-left">Wilayah</th>
                    <th class="text-right">Aktivitas</th>
                    <th class="text-right">Total Penjualan (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in activityVsSales" :key="`avs-${row.province_id}`">
                    <td>{{ row.province_name }}</td>
                    <td class="text-right">{{ formatNumber(row.activity_count || 0) }}</td>
                    <td class="text-right">Rp {{ formatNumber(row.total_sales || 0) }}</td>
                  </tr>
                  <tr v-if="!activityVsSales.length && !loadingActivityVsSales">
                    <td colspan="3" class="text-center text-grey">Tidak ada data</td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-12 col-lg-6">
          <q-card flat bordered square>
            <q-card-section>
              <div class="text-subtitle1 text-weight-bold q-mb-sm">Target vs Aktual (Distributor Sales)</div>
              <q-markup-table flat bordered square separator="cell">
                <thead>
                  <tr>
                    <th class="text-left">Produk</th>
                    <th class="text-right">Target</th>
                    <th class="text-right">Aktual</th>
                    <th class="text-right">Achievement</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in targetVsActual" :key="`target-${row.product_name}`">
                    <td>{{ row.product_name }} <span class="text-caption text-grey">({{ row.uom || '-' }})</span></td>
                    <td class="text-right">{{ formatNumber(row.total_target || 0, 'id-ID', 2) }}</td>
                    <td class="text-right">{{ formatNumber(row.total_actual || 0, 'id-ID', 2) }}</td>
                    <td class="text-right">
                      <span v-if="row.achievement !== null" :class="row.achievement >= 100 ? 'text-green-8' : 'text-orange-8'">
                        {{ formatNumber(row.achievement || 0, 'id-ID', 1) }}%
                      </span>
                      <span v-else>-</span>
                    </td>
                  </tr>
                  <tr v-if="!targetVsActual.length">
                    <td colspan="4" class="text-center text-grey">Tidak ada data target</td>
                  </tr>
                </tbody>
              </q-markup-table>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </q-page>
  </authenticated-layout>
</template>
