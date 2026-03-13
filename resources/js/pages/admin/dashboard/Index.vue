<script setup>
import BsTargetCard from "./cards/BsTargetCard.vue";
import AgronomistDashboardCard from "./cards/AgronomistDashboardCard.vue";
import { router, usePage } from "@inertiajs/vue3";
import { reactive, ref } from "vue";
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

const filter = reactive({
  year: Number(query.year ?? currentYear),
  month: Number(query.month ?? currentMonth),
  view_type: query.view_type ?? "month",
  quarter: Number(query.quarter ?? currentQuarter),
});

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
      <q-toolbar class="filter-bar">
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
            class="custom-select col-xs-6 col-sm-4"
            style="min-width: 120px"
            v-model="filter.year"
            :options="years"
            label="Tahun"
            dense
            emit-value
            map-options
            outlined
            @update:model-value="onFilterChange"
          />

          <!-- Bulan — BS selalu tampil, agronomist hanya mode 'month' -->
          <q-select
            v-if="userRole === 'bs' || (userRole === 'agronomist' && filter.view_type === 'month')"
            class="custom-select col-xs-6 col-sm-4"
            style="min-width: 120px"
            v-model="filter.month"
            :options="months"
            label="Bulan"
            dense
            emit-value
            map-options
            outlined
            @update:model-value="onFilterChange"
          />

          <!-- Kwartal — agronomist mode 'quarter' -->
          <q-select
            v-if="userRole === 'agronomist' && filter.view_type === 'quarter'"
            class="custom-select col-xs-6 col-sm-4"
            style="min-width: 120px"
            v-model="filter.quarter"
            :options="quarterOptions"
            label="Kwartal"
            dense
            emit-value
            map-options
            outlined
            @update:model-value="onFilterChange"
          />
        </div>
      </q-toolbar>
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
