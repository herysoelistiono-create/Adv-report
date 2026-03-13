<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { useQuasar } from "quasar";
import { formateDatetime } from "@/helpers/datetime";
import { formatNumber } from "@/helpers/utils";
import { usePageStorage } from "@/helpers/usePageStorage";
import useTableHeight from "@/composables/useTableHeight";

const page = usePage();
const $q = useQuasar();
const distributor = page.props.distributor || {};

const title = `Pergerakan Stok - ${distributor.name || "Distributor"}`;
const storage = usePageStorage(`distributor-stock-movements-${distributor.id || 0}`);

const rows = ref([]);
const loading = ref(false);
const showFilter = ref(storage.get("show-filter", true));
const tableRef = ref(null);
const filterToolbarRef = ref(null);
const tableHeight = useTableHeight(filterToolbarRef);

const filter = reactive(
  storage.get("filter", {
    product_id: null,
    type: null,
  })
);

const pagination = ref(
  storage.get("pagination", {
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 0,
    sortBy: "created_datetime",
    descending: true,
  })
);

const typeOptions = [
  { value: null, label: "Semua Tipe" },
  { value: "in", label: "Masuk" },
  { value: "out", label: "Keluar" },
];

const productOptions = computed(() => [
  { value: null, label: "Semua Produk" },
  ...((page.props.products || []).map((item) => ({ value: item.id, label: item.name })) || []),
]);

const columns = [
  {
    name: "created_datetime",
    label: $q.screen.gt.sm ? "Waktu" : "Pergerakan",
    field: "created_datetime",
    align: "left",
    sortable: true,
  },
  {
    name: "type",
    label: "Tipe",
    field: "type",
    align: "left",
    sortable: true,
  },
  {
    name: "product",
    label: "Produk",
    field: "product",
    align: "left",
  },
  {
    name: "quantity",
    label: "Qty",
    field: "quantity",
    align: "right",
    sortable: true,
  },
  {
    name: "reference",
    label: "Referensi",
    field: "reference",
    align: "left",
  },
  {
    name: "notes",
    label: "Catatan",
    field: "notes",
    align: "left",
  },
  {
    name: "created_by_user",
    label: "User",
    field: "created_by_user",
    align: "left",
  },
];

const computedColumns = computed(() => {
  if ($q.screen.gt.sm) return columns;
  return columns.filter((col) => ["created_datetime", "quantity"].includes(col.name));
});

const formatMovementType = (value) => (value === "in" ? "Masuk" : "Keluar");

const fetchItems = async (props = null) => {
  const source = props ? props.pagination : pagination.value;

  loading.value = true;
  try {
    const response = await axios.get(
      route("admin.distributor-stock.movements.data", { distributorId: distributor.id }),
      {
        params: {
          page: source.page,
          per_page: source.rowsPerPage,
          product_id: filter.product_id,
          type: filter.type,
        },
      }
    );

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

onMounted(fetchItems);

watch(filter, () => storage.set("filter", filter), { deep: true });
watch(showFilter, () => storage.set("show-filter", showFilter.value));
watch(pagination, () => storage.set("pagination", pagination.value), { deep: true });
</script>

<template>
  <i-head :title="title" />
  <authenticated-layout>
    <template #title>{{ title }}</template>

    <template #left-button>
      <q-btn
        icon="arrow_back"
        dense
        color="grey-7"
        flat
        rounded
        @click="router.get(route('admin.distributor-stock.index'))"
      />
    </template>

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
            style="min-width: 180px"
            v-model="filter.type"
            :options="typeOptions"
            label="Tipe"
            dense
            outlined
            map-options
            emit-value
            @update:model-value="onFilterChange"
          />

          <q-select
            class="custom-select col-xs-12 col-sm-3"
            style="min-width: 220px"
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
            <q-td key="created_datetime" :props="props" class="wrap-column">
              {{ formateDatetime(props.row.created_datetime) }}
              <template v-if="$q.screen.lt.md">
                <div>
                  Tipe:
                  <q-badge :color="props.row.type === 'in' ? 'green' : 'orange-8'" class="q-ml-xs">
                    {{ formatMovementType(props.row.type) }}
                  </q-badge>
                </div>
                <div>Produk: {{ props.row.product?.name || '-' }}</div>
                <div>Qty: {{ formatNumber(props.row.quantity, 'id-ID', 2) }}</div>
                <div>Ref: {{ props.row.reference || '-' }}</div>
              </template>
            </q-td>

            <q-td key="type" :props="props">
              <q-badge :color="props.row.type === 'in' ? 'green' : 'orange-8'">
                {{ formatMovementType(props.row.type) }}
              </q-badge>
            </q-td>

            <q-td key="product" :props="props">{{ props.row.product?.name || '-' }}</q-td>

            <q-td key="quantity" :props="props" class="text-right text-weight-medium">
              {{ formatNumber(props.row.quantity, "id-ID", 2) }}
            </q-td>

            <q-td key="reference" :props="props">{{ props.row.reference || '-' }}</q-td>
            <q-td key="notes" :props="props">{{ props.row.notes || '-' }}</q-td>
            <q-td key="created_by_user" :props="props">{{ props.row.created_by_user?.name || '-' }}</q-td>
          </q-tr>
        </template>
      </q-table>
    </div>
  </authenticated-layout>
</template>
