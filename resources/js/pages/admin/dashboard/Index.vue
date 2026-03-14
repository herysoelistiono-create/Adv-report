<script setup>
import BsTargetCard from "./cards/BsTargetCard.vue";
import AgronomistDashboardCard from "./cards/AgronomistDashboardCard.vue";
import { router, usePage } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
import {
  create_month_options,
  current_month,
  current_quarter,
  current_year,
  getQueryParams,
} from "@/helpers/utils";

const query = getQueryParams();
const currentYear = current_year();
const currentMonth = current_month();
const currentQuarter = current_quarter();
const userRole = usePage().props.auth.user.role;
const title = "Dashboard";
const showFilter = ref(true);

const allowedViewTypes = ["month", "quarter", "fiscal_year"];

const toValidNumber = (value, fallback, allowed) => {
  const num = Number(value);
  return Number.isFinite(num) && allowed.includes(num) ? num : fallback;
};

const toValidViewType = (value) =>
  allowedViewTypes.includes(value) ? value : "month";

const years = [
  ...Array.from({ length: 3 }, (_, i) => {
    const year = currentYear - 1 + i;
    return { value: year, label: String(year) + " / " + String(year + 1) };
  }),
];

const months = create_month_options();

const quarterOptions = [
  { value: 1, label: "Q1 (Apr-Jun)" },
  { value: 2, label: "Q2 (Jul-Sep)" },
  { value: 3, label: "Q3 (Okt-Des)" },
  { value: 4, label: "Q4 (Jan-Mar)" },
];

const yearValues = years.map((y) => y.value);
const monthValues = months.map((m) => m.value);
const quarterValues = quarterOptions.map((q) => q.value);

const filter = reactive({
  year: toValidNumber(query.year, currentYear, yearValues),
  month: toValidNumber(query.month, currentMonth, monthValues),
  view_type: toValidViewType(query.view_type),
  quarter: toValidNumber(query.quarter, currentQuarter, quarterValues),
});

const optionLabel = (options, value, fallback = "") => {
  const exact = options.find((opt) => opt.value === value);
  if (exact) return exact.label;

  const loose = options.find((opt) => Number(opt.value) === Number(value));
  if (loose) return loose.label;

  return fallback;
};

const selectedYearLabel = computed(() =>
  optionLabel(years, filter.year, `${filter.year} / ${Number(filter.year) + 1}`)
);
const selectedMonthLabel = computed(() =>
  optionLabel(months, filter.month, "Pilih Bulan")
);
const selectedQuarterLabel = computed(() =>
  optionLabel(quarterOptions, filter.quarter, "Pilih Kwartal")
);

const viewTypeOptions = [
  { value: "month", label: "Per Bulan" },
  { value: "quarter", label: "Per Kwartal" },
  { value: "fiscal_year", label: "Tahun Fiskal" },
];

const onFilterChange = () => {
  router.visit(route("admin.dashboard", filter));
};
</script>

<template>
  <i-head :title="title" />
  <authenticated-layout>
    <template #title>{{ title }}</template>
    <template #right-button>
      <q-btn
        class="q-ml-sm"
        :icon="!showFilter ? 'filter_alt' : 'filter_alt_off'"
        color="grey"
        dense
        @click="showFilter = !showFilter"
      />
    </template>

    <template #header v-if="showFilter">
      <div class="filter-bar">
        <div class="row q-col-gutter-xs items-center q-pa-sm full-width">

          <!-- Tab view type — agronomist only -->
          <div v-if="userRole === 'agronomist'" class="col-12 q-pb-xs">
            <q-btn-toggle
              v-model="filter.view_type"
              :options="viewTypeOptions"
              size="sm"
              dense
              toggle-color="primary"
              color="white"
              text-color="grey-8"
              unelevated
              spread
              @update:model-value="onFilterChange"
            />
          </div>

          <!-- Tahun -->
          <q-select
            class="dash-filter-select col-xs-12 col-sm-4"
            v-model="filter.year"
            :options="years"
            option-value="value"
            option-label="label"
            :display-value="selectedYearLabel"
            label="Tahun"
            dense
            stack-label
            emit-value
            map-options
            outlined
            @update:model-value="onFilterChange"
          >
            <template #selected-item="scope">
              <span class="dash-filter-selected">{{ scope.opt?.label ?? selectedYearLabel }}</span>
            </template>
          </q-select>

          <!-- Bulan — BS selalu tampil, agronomist hanya mode 'month' -->
          <q-select
            v-if="userRole === 'bs' || (userRole === 'agronomist' && filter.view_type === 'month')"
            class="dash-filter-select col-xs-12 col-sm-4"
            v-model="filter.month"
            :options="months"
            option-value="value"
            option-label="label"
            :display-value="selectedMonthLabel"
            label="Bulan"
            dense
            stack-label
            emit-value
            map-options
            outlined
            @update:model-value="onFilterChange"
          >
            <template #selected-item="scope">
              <span class="dash-filter-selected">{{ scope.opt?.label ?? selectedMonthLabel }}</span>
            </template>
          </q-select>

          <!-- Kwartal — agronomist mode 'quarter' -->
          <q-select
            v-if="userRole === 'agronomist' && filter.view_type === 'quarter'"
            class="dash-filter-select col-xs-12 col-sm-4"
            v-model="filter.quarter"
            :options="quarterOptions"
            option-value="value"
            option-label="label"
            :display-value="selectedQuarterLabel"
            label="Kwartal"
            dense
            stack-label
            emit-value
            map-options
            outlined
            @update:model-value="onFilterChange"
          >
            <template #selected-item="scope">
              <span class="dash-filter-selected">{{ scope.opt?.label ?? selectedQuarterLabel }}</span>
            </template>
          </q-select>
        </div>
      </div>
    </template>

    <!-- BS Dashboard -->
    <div class="q-pa-sm" v-if="userRole === 'bs'">
      <BsTargetCard />
    </div>

    <!-- Agronomist Dashboard -->
    <div class="q-pa-sm" v-if="userRole === 'agronomist'">
      <div class="text-subtitle1 text-bold text-grey-8 q-mb-sm">
        Rekap Kegiatan BS
      </div>
      <AgronomistDashboardCard />
    </div>

    <!-- Admin Dashboard -->
    <div class="q-pa-sm" v-if="userRole === 'admin'">
      <div>
        <div class="text-subtitle1 text-bold text-grey-8">Statistik Aktual</div>
        <p class="text-grey-8">Belum Tersedia</p>
        <div class="row q-col-gutter-sm q-pt-sm"></div>
      </div>
      <div class="q-pt-md">
        <div class="text-subtitle1 text-bold text-grey-8">Statistik Keseluruhan</div>
        <p class="text-grey-8">Belum Tersedia</p>
      </div>
    </div>
  </authenticated-layout>
</template>

<style scoped>
/* Dashboard content wrappers — full width, no overflow */
.q-pa-sm {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow-x: hidden;
}

/* Filter bar row — neutralize gutter negative margins */
:deep(.filter-bar .row) {
  margin-left: 0 !important;
  margin-right: 0 !important;
  width: 100%;
}

/* Keep selected value text visible in dashboard filters */
:deep(.dash-filter-selected) {
  display: inline-block;
  max-width: 100%;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: rgba(0, 0, 0, 0.87);
}

/* Mobile: stack filter selects, shrink to fit */
@media (max-width: 599px) {
  :deep(.dash-filter-select .q-field__label),
  :deep(.dash-filter-select .q-field__native) {
    font-size: 0.78rem;
  }
  :deep(.q-btn-toggle .q-btn) {
    font-size: 0.7rem;
    padding: 2px 4px;
  }
}
</style>
