<script setup>
import BsTargetCard from "./cards/BsTargetCard.vue";
import AgronomistDashboardCard from "./cards/AgronomistDashboardCard.vue";
import { router, usePage } from "@inertiajs/vue3";
import { computed, onMounted, reactive, ref } from "vue";
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

// Fiscal year starts in April (month 4)
// Jan-Mar: fiscal year is (currentYear - 2)/(currentYear - 1)
// Apr-Dec: fiscal year is (currentYear - 1)/currentYear
const fiscalYearStart = currentMonth <= 3 ? currentYear - 2 : currentYear - 1;

const initialYearOptions = [
  ...Array.from({ length: 3 }, (_, i) => {
    const year = fiscalYearStart + i;
    return { value: year, label: String(year) + " / " + String(year + 1) };
  }),
];

const initialMonthOptions = create_month_options();

const initialQuarterOptions = [
  { value: 1, label: "Q1 (Apr-Jun)" },
  { value: 2, label: "Q2 (Jul-Sep)" },
  { value: 3, label: "Q3 (Okt-Des)" },
  { value: 4, label: "Q4 (Jan-Mar)" },
];

const yearValues = initialYearOptions.map((y) => y.value);
const monthValues = initialMonthOptions.map((m) => m.value);
const quarterValues = initialQuarterOptions.map((q) => q.value);

const filterOptions = reactive({
  years: [],
  months: [],
  quarters: [],
});
const loadingFilterOptions = ref(true);
const filterOptionsError = ref(false);

const filter = reactive({
  year: toValidNumber(query.year, currentYear, yearValues),
  month: toValidNumber(query.month, currentMonth, monthValues),
  view_type: toValidViewType(query.view_type),
  quarter: toValidNumber(query.quarter, currentQuarter, quarterValues),
});

const optionLabel = (options = [], value, fallback = "") => {
  const exact = options.find((opt) => opt.value === value);
  if (exact) return exact.label;

  const loose = options.find((opt) => Number(opt.value) === Number(value));
  if (loose) return loose.label;

  return fallback;
};

const selectedYearLabel = computed(() =>
  optionLabel(
    filterOptions.years,
    filter.year,
    `${filter.year} / ${Number(filter.year) + 1}`
  )
);
const selectedMonthLabel = computed(() =>
  optionLabel(filterOptions.months, filter.month, "Pilih Bulan")
);
const selectedQuarterLabel = computed(() =>
  optionLabel(filterOptions.quarters, filter.quarter, "Pilih Kwartal")
);

const hasYearOptions = computed(() => filterOptions.years.length > 0);
const hasMonthOptions = computed(() => filterOptions.months.length > 0);
const hasQuarterOptions = computed(() => filterOptions.quarters.length > 0);

const viewTypeOptions = [
  { value: "month", label: "Per Bulan" },
  { value: "quarter", label: "Per Kwartal" },
  { value: "fiscal_year", label: "Tahun Fiskal" },
];

const fetchFilterOptions = async () => {
  loadingFilterOptions.value = true;
  filterOptionsError.value = false;

  try {
    await Promise.resolve();

    const years = [...initialYearOptions];
    const months = create_month_options();
    const quarters = [...initialQuarterOptions];

    filterOptions.years = years;
    filterOptions.months = months;
    filterOptions.quarters = quarters;

    filter.year = toValidNumber(
      filter.year,
      currentYear,
      years.map((item) => item.value)
    );
    filter.month = toValidNumber(
      filter.month,
      currentMonth,
      months.map((item) => item.value)
    );
    filter.quarter = toValidNumber(
      filter.quarter,
      currentQuarter,
      quarters.map((item) => item.value)
    );

    console.debug("[Dashboard] Filter options loaded", {
      years,
      months,
      quarters,
    });
  } catch (error) {
    filterOptions.years = [];
    filterOptions.months = [];
    filterOptions.quarters = [];
    filterOptionsError.value = true;
    console.error("[Dashboard] Failed to load filter options", error);
  } finally {
    loadingFilterOptions.value = false;
  }
};

const onFilterChange = () => {
  router.visit(route("admin.dashboard", filter));
};

onMounted(() => {
  fetchFilterOptions();
});
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

          <div v-if="loadingFilterOptions" class="col-12">
            <div class="dash-filter-loading">
              <q-spinner color="primary" size="20px" />
              <span>Memuat opsi filter...</span>
            </div>
          </div>

          <template v-else>
            <!-- Tahun -->
            <q-select
              v-if="hasYearOptions"
              class="dash-filter-select col-12 col-sm-4"
              v-model="filter.year"
              :options="filterOptions.years"
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
            <div v-else class="dash-filter-empty col-12 col-sm-4">No options available</div>

            <!-- Bulan — BS selalu tampil, agronomist hanya mode 'month' -->
            <template
              v-if="userRole === 'bs' || (userRole === 'agronomist' && filter.view_type === 'month')"
            >
              <q-select
                v-if="hasMonthOptions"
                class="dash-filter-select col-12 col-sm-4"
                v-model="filter.month"
                :options="filterOptions.months"
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
              <div v-else class="dash-filter-empty col-12 col-sm-4">No options available</div>
            </template>

            <!-- Kwartal — agronomist mode 'quarter' -->
            <template v-if="userRole === 'agronomist' && filter.view_type === 'quarter'">
              <q-select
                v-if="hasQuarterOptions"
                class="dash-filter-select col-12 col-sm-4"
                v-model="filter.quarter"
                :options="filterOptions.quarters"
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
              <div v-else class="dash-filter-empty col-12 col-sm-4">No options available</div>
            </template>

            <div
              v-if="filterOptionsError && !hasYearOptions && !hasMonthOptions && !hasQuarterOptions"
              class="dash-filter-empty col-12"
            >
              No options available
            </div>
          </template>
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

:deep(.dash-filter-select) {
  width: 100%;
}

.dash-filter-loading {
  min-height: 40px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 6px;
  color: rgba(0, 0, 0, 0.75);
}

.dash-filter-empty {
  min-height: 40px;
  width: 100%;
  display: flex;
  align-items: center;
  padding: 0 12px;
  border: 1px dashed rgba(0, 0, 0, 0.2);
  border-radius: 8px;
  color: rgba(0, 0, 0, 0.56);
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
