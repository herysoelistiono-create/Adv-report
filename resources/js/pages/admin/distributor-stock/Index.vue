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
const storage = usePageStorage("distributor-stock");

const title = "Stok Distributor";
const rows = ref([]);
const loading = ref(false);
const showFilter = ref(storage.get("show-filter", true));
const tableRef = ref(null);
const filterToolbarRef = ref(null);
const tableHeight = useTableHeight(filterToolbarRef);

const filter = reactive(
  storage.get("filter", {
    distributor_id: null,
    product_id: null,
  })
);

const pagination = ref(
  storage.get("pagination", {
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 0,
    sortBy: "stock_quantity",
    descending: true,
  })
);

const columns = [
  {
    name: "distributor",
    label: $q.screen.gt.sm ? "Distributor" : "Stok",
    field: "distributor",
    align: "left",
  },
  {
    name: "product",
    label: "Produk",
    field: "product",
    align: "left",
  },
  {
    name: "unit",
    label: "Satuan",
    field: "unit",
    align: "left",
  },
  {
    name: "stock_quantity",
    label: "Qty",
    field: "stock_quantity",
    align: "right",
    sortable: true,
  },
  { name: "action", align: "right" },
];

const distributorOptions = computed(() => [
  { value: null, label: "Semua Distributor" },
  ...((page.props.distributors || []).map((item) => ({ value: item.id, label: item.name })) || []),
]);

const productOptions = computed(() => [
  { value: null, label: "Semua Produk" },
  ...((page.props.products || []).map((item) => ({ value: item.id, label: item.name })) || []),
]);

const computedColumns = computed(() => {
  if ($q.screen.gt.sm) return columns;
  return columns.filter((col) => ["distributor", "stock_quantity", "action"].includes(col.name));
});

const fetchItems = async (props = null) => {
  const source = props ? props.pagination : pagination.value;

  loading.value = true;
  try {
    const response = await axios.get(route("admin.distributor-stock.data"), {
      params: {
        page: source.page,
        per_page: source.rowsPerPage,
        order_by: source.sortBy || "stock_quantity",
        order_type: source.descending ? "desc" : "asc",
        filter: {
          distributor_id: filter.distributor_id,
          product_id: filter.product_id,
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

const openMovements = (row) => {
  router.get(route("admin.distributor-stock.movements", { distributorId: row.distributor_id }));
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
        v-if="$can('admin.distributor-stock.add')"
        icon="add"
        dense
        color="primary"
        @click="router.get(route('admin.distributor-stock.add'))"
      />
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
            style="min-width: 180px"
            v-model="filter.distributor_id"
            :options="distributorOptions"
            label="Distributor"
            dense
            outlined
            map-options
            emit-value
            @update:model-value="onFilterChange"
          />

          <q-select
            class="custom-select col-xs-12 col-sm-3"
            style="min-width: 180px"
            v-model="filter.product_id"
            :options="productOptions"
            label="Produk"
            dense
            outlined
            map-options
            emit-value
            @update:model-value="onFilterChange"
          />
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
        :rows-per-page-options="[10, 20, 50]"
        @request="fetchItems"
        binary-state-sort
      >
        <template #loading>
          <q-inner-loading showing color="red" />
        </template>

        <template #body="props">
          <q-tr :props="props">
            <q-td key="distributor" :props="props" class="wrap-column">
              <div class="text-weight-medium">{{ props.row.distributor?.name || '-' }}</div>
              <template v-if="$q.screen.lt.md">
                <div>Produk: {{ props.row.product?.name || '-' }}</div>
                <div>Satuan: {{ props.row.product?.unit || '-' }}</div>
                <div>Qty: {{ formatNumber(props.row.stock_quantity, 'id-ID', 2) }}</div>
              </template>
            </q-td>

            <q-td key="product" :props="props">{{ props.row.product?.name || '-' }}</q-td>
            <q-td key="unit" :props="props">{{ props.row.product?.unit || '-' }}</q-td>
            <q-td key="stock_quantity" :props="props" class="text-right text-weight-medium">
              {{ formatNumber(props.row.stock_quantity, "id-ID", 2) }}
            </q-td>

            <q-td key="action" :props="props" class="text-right">
              <q-btn
                v-if="$can('admin.distributor-stock.movements')"
                icon="history"
                dense
                flat
                color="primary"
                @click="openMovements(props.row)"
              >
                <q-tooltip>Pergerakan Stok</q-tooltip>
              </q-btn>
            </q-td>
          </q-tr>
        </template>
      </q-table>
    </div>
  </authenticated-layout>
</template>
