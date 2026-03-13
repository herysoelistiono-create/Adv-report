<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { useQuasar } from "quasar";
import { formatNumber } from "@/helpers/utils";
import { usePageStorage } from "@/helpers/usePageStorage";
import useTableHeight from "@/composables/useTableHeight";

const page = usePage();
const $q = useQuasar();
const storage = usePageStorage("distributor");

const title = "Distributor";
const showFilter = ref(storage.get("show-filter", true));
const rows = ref([]);
const loading = ref(false);
const tableRef = ref(null);
const filterToolbarRef = ref(null);
const tableHeight = useTableHeight(filterToolbarRef);

const filter = reactive(
  storage.get("filter", {
    search: "",
    status: "all",
    province_id: null,
  })
);

const pagination = ref(
  storage.get("pagination", {
    page: 1,
    rowsPerPage: 10,
    rowsNumber: 0,
    sortBy: "name",
    descending: false,
  })
);

const statuses = [
  { value: "all", label: "Semua Status" },
  { value: "active", label: "Aktif" },
  { value: "inactive", label: "Tidak Aktif" },
];

const provinceOptions = computed(() => [
  { value: null, label: "Semua Provinsi" },
  ...((page.props.provinces || []).map((item) => ({
    value: item.id,
    label: item.name,
  })) || []),
]);

const columns = [
  {
    name: "name",
    label: $q.screen.gt.sm ? "Distributor" : "Distributor",
    field: "name",
    align: "left",
    sortable: true,
  },
  {
    name: "phone",
    label: "No HP",
    field: "phone",
    align: "left",
    sortable: false,
  },
  {
    name: "area",
    label: "Wilayah",
    field: "area",
    align: "left",
    sortable: false,
  },
  {
    name: "total_transactions",
    label: "Transaksi",
    field: "total_transactions",
    align: "right",
    sortable: true,
  },
  {
    name: "total_sales",
    label: "Total Sales (Rp)",
    field: "total_sales",
    align: "right",
    sortable: true,
  },
  {
    name: "status",
    label: "Status",
    field: "active",
    align: "center",
    sortable: true,
  },
  { name: "action", align: "right" },
];

const computedColumns = computed(() => {
  if ($q.screen.gt.sm) return columns;
  return columns.filter((col) => ["name", "total_sales", "action"].includes(col.name));
});

const fetchItems = async (props = null) => {
  const source = props ? props.pagination : pagination.value;

  loading.value = true;
  try {
    const response = await axios.get(route("admin.distributor.data"), {
      params: {
        page: source.page,
        per_page: source.rowsPerPage,
        order_by: source.sortBy || "name",
        order_type: source.descending ? "desc" : "asc",
        filter: {
          search: filter.search,
          status: filter.status,
          province_id: filter.province_id,
        },
      },
    });

    rows.value = response.data.data || [];

    pagination.value.page = response.data.current_page;
    pagination.value.rowsPerPage = response.data.per_page;
    pagination.value.rowsNumber = response.data.total;

    if (props) {
      pagination.value.sortBy = source.sortBy;
      pagination.value.descending = source.descending;
    }
  } finally {
    loading.value = false;
    nextTick(() => {
      const scrollableElement = tableRef.value?.$el?.querySelector(".q-table__middle");
      if (scrollableElement) scrollableElement.scrollTop = 0;
    });
  }
};

const onFilterChange = () => {
  pagination.value.page = 1;
  fetchItems();
};

const openDetail = (row) => {
  router.get(route("admin.distributor.detail", row.id));
};

onMounted(fetchItems);

watch(filter, () => storage.set("filter", filter), { deep: true });
watch(showFilter, () => storage.set("show-filter", showFilter.value));
watch(pagination, () => storage.set("pagination", pagination.value), { deep: true });
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
      <q-toolbar class="filter-bar" ref="filterToolbarRef">
        <div class="row q-col-gutter-xs items-center q-pa-sm full-width">
          <q-select
            class="custom-select col-xs-12 col-sm-3"
            style="min-width: 160px"
            v-model="filter.status"
            :options="statuses"
            label="Status"
            dense
            outlined
            map-options
            emit-value
            @update:model-value="onFilterChange"
          />

          <q-select
            class="custom-select col-xs-12 col-sm-3"
            style="min-width: 180px"
            v-model="filter.province_id"
            :options="provinceOptions"
            label="Provinsi"
            dense
            outlined
            map-options
            emit-value
            @update:model-value="onFilterChange"
          />

          <q-input
            class="col"
            outlined
            dense
            debounce="300"
            v-model="filter.search"
            placeholder="Cari nama atau no HP"
            clearable
            @update:model-value="onFilterChange"
          >
            <template #append>
              <q-icon name="search" />
            </template>
          </q-input>
        </div>
      </q-toolbar>
    </template>

    <div class="q-pa-sm">
      <q-table
        ref="tableRef"
        :style="{ height: tableHeight }"
        class="full-height-table"
        flat
        bordered
        square
        row-key="id"
        virtual-scroll
        v-model:pagination="pagination"
        :loading="loading"
        :columns="computedColumns"
        :rows="rows"
        :rows-per-page-options="[10, 25, 50]"
        @request="fetchItems"
        binary-state-sort
      >
        <template #loading>
          <q-inner-loading showing color="red" />
        </template>

        <template #body="props">
          <q-tr :props="props" class="cursor-pointer" @click="openDetail(props.row)">
            <q-td key="name" :props="props" class="wrap-column">
              <div class="text-weight-medium">{{ props.row.name }}</div>
              <template v-if="$q.screen.lt.md">
                <div>No HP: {{ props.row.phone || '-' }}</div>
                <div>
                  Wilayah:
                  {{ props.row.province?.name || '-' }}
                  {{ props.row.district?.name ? `, ${props.row.district?.name}` : '' }}
                </div>
                <div>Transaksi: {{ formatNumber(props.row.total_transactions || 0) }}</div>
                <div>Total: Rp {{ formatNumber(props.row.total_sales || 0) }}</div>
              </template>
            </q-td>

            <q-td key="phone" :props="props">{{ props.row.phone || '-' }}</q-td>

            <q-td key="area" :props="props">
              {{ props.row.province?.name || '-' }}
              {{ props.row.district?.name ? `, ${props.row.district?.name}` : '' }}
            </q-td>

            <q-td key="total_transactions" :props="props" class="text-right">
              {{ formatNumber(props.row.total_transactions || 0) }}
            </q-td>

            <q-td key="total_sales" :props="props" class="text-right text-weight-medium">
              Rp {{ formatNumber(props.row.total_sales || 0) }}
            </q-td>

            <q-td key="status" :props="props" class="text-center">
              <q-badge :color="props.row.active ? 'green' : 'red'">
                {{ props.row.active ? "Aktif" : "Tidak Aktif" }}
              </q-badge>
            </q-td>

            <q-td key="action" :props="props" @click.stop>
              <q-btn
                icon="visibility"
                dense
                flat
                color="primary"
                @click="openDetail(props.row)"
              >
                <q-tooltip>Detail</q-tooltip>
              </q-btn>
            </q-td>
          </q-tr>
        </template>
      </q-table>
    </div>
  </authenticated-layout>
</template>
