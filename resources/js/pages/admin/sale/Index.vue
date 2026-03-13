<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { Dialog, Notify, useQuasar } from "quasar";
import axios from "axios";
import { formatDate } from "@/helpers/datetime";
import { create_month_options, formatNumber } from "@/helpers/utils";
import { usePageStorage } from "@/helpers/usePageStorage";
import useTableHeight from "@/composables/useTableHeight";

const page = usePage();
const $q = useQuasar();
const storage = usePageStorage("sale");

const title = "Penjualan";
const rows = ref([]);
const loading = ref(false);
const totalSalesSum = ref(0);
const tableRef = ref(null);
const showFilter = ref(storage.get("show-filter", true));
const filterToolbarRef = ref(null);
const tableHeight = useTableHeight(filterToolbarRef);

const importDialog = ref(false);
const importing = ref(false);
const importFile = ref(null);
const importResult = ref(null);

const now = new Date();
const thisFiscalYear = now.getMonth() + 1 >= 4 ? now.getFullYear() : now.getFullYear() - 1;

const filter = reactive(
  storage.get("filter", {
    search: "",
    distributor_id: null,
    retailer_id: null,
    fiscal_year: thisFiscalYear,
    month: null,
  })
);

const pagination = ref(
  storage.get("pagination", {
    page: 1,
    rowsPerPage: 10,
    rowsNumber: 0,
    sortBy: "date",
    descending: true,
  })
);

const columns = [
  {
    name: "date",
    label: $q.screen.gt.sm ? "Tanggal" : "Penjualan",
    field: "date",
    align: "left",
    sortable: true,
  },
  {
    name: "sale_type",
    label: "Jenis",
    field: "sale_type",
    align: "left",
    sortable: true,
  },
  {
    name: "distributor",
    label: "Distributor",
    field: "distributor",
    align: "left",
    sortable: false,
  },
  {
    name: "retailer",
    label: "R1/R2",
    field: "retailer",
    align: "left",
    sortable: false,
  },
  {
    name: "total_amount",
    label: "Total (Rp)",
    field: "total_amount",
    align: "right",
    sortable: true,
  },
  {
    name: "created_by_user",
    label: "Input By",
    field: "created_by_user",
    align: "left",
  },
  { name: "action", align: "right" },
];

const fiscalYearOptions = computed(() => {
  const out = [{ value: null, label: "Semua FY" }];
  for (let i = 0; i < 8; i++) {
    const fy = thisFiscalYear - i;
    out.push({ value: fy, label: `FY ${fy}/${fy + 1}` });
  }
  return out;
});

const monthOptions = computed(() => [{ value: null, label: "Semua Bulan" }, ...create_month_options()]);

const distributorOptions = computed(() => [
  { value: null, label: "Semua Distributor" },
  ...(page.props.distributors || []).map((item) => ({ value: item.id, label: item.name })),
]);

const retailerOptions = computed(() => [
  { value: null, label: "Semua R1/R2" },
  ...(page.props.retailers || []).map((item) => ({ value: item.id, label: `${item.name} (${item.type})` })),
]);

const computedColumns = computed(() => {
  if ($q.screen.gt.sm) return columns;
  return columns.filter((col) => ["date", "total_amount", "action"].includes(col.name));
});

const saleTypeLabel = (saleType) => {
  if (saleType === "retailer") return "Retailer";
  return "Distributor";
};

const buildParams = (sourcePagination) => ({
  page: sourcePagination.page,
  per_page: sourcePagination.rowsPerPage,
  order_by: sourcePagination.sortBy || "date",
  order_type: sourcePagination.descending ? "desc" : "asc",
  filter: {
    search: filter.search,
    distributor_id: filter.distributor_id,
    retailer_id: filter.retailer_id,
    fiscal_year: filter.fiscal_year,
    month: filter.month,
  },
});

const fetchItems = async (props = null) => {
  const source = props ? props.pagination : pagination.value;
  loading.value = true;
  try {
    const response = await axios.get(route("admin.sale.data"), {
      params: buildParams(source),
    });

    rows.value = response.data.data || [];
    totalSalesSum.value = Number(response.data.total_sales_sum || 0);

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

const onRowClicked = (row) => {
  router.get(route("admin.sale.detail", { id: row.id }));
};

const deleteItem = (row) => {
  Dialog.create({
    title: "Konfirmasi",
    message: `Hapus data penjualan #${row.id}?`,
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    loading.value = true;
    try {
      const response = await axios.post(route("admin.sale.delete", row.id));
      Notify.create({ message: response.data.message || "Berhasil dihapus", color: "positive" });
      await fetchItems();
    } catch (error) {
      Notify.create({
        message: error?.response?.data?.message || "Gagal menghapus data",
        color: "negative",
      });
    } finally {
      loading.value = false;
    }
  });
};

const openImportDialog = () => {
  importFile.value = null;
  importResult.value = null;
  importDialog.value = true;
};

const submitImport = async () => {
  if (!importFile.value) {
    Notify.create({ message: "Pilih file Excel terlebih dahulu", color: "warning" });
    return;
  }

  importing.value = true;
  try {
    const formData = new FormData();
    formData.append("file", importFile.value);

    const response = await axios.post(route("admin.sale.import"), formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    importResult.value = response.data;

    const errorCount = response.data.errors?.length || 0;
    const successCount = Number(response.data.success || 0);
    Notify.create({
      message: `Import selesai. Berhasil: ${successCount}, Error: ${errorCount}`,
      color: errorCount > 0 ? "warning" : "positive",
    });

    if (successCount > 0) {
      await fetchItems();
    }
  } catch (error) {
    const message =
      error?.response?.data?.errors?.[0] ||
      error?.response?.data?.message ||
      "Import gagal";
    Notify.create({ message, color: "negative" });
  } finally {
    importing.value = false;
  }
};

const buildExportUrl = (format) => {
  const query = new URLSearchParams();
  query.append("format", format);

  const f = {
    search: filter.search,
    distributor_id: filter.distributor_id,
    retailer_id: filter.retailer_id,
    fiscal_year: filter.fiscal_year,
    month: filter.month,
  };

  Object.entries(f).forEach(([key, value]) => {
    if (value !== null && value !== "") {
      query.append(`filter[${key}]`, String(value));
    }
  });

  return `${route("admin.sale.export")}?${query.toString()}`;
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
        v-if="$can('admin.sale.add')"
        icon="add"
        dense
        color="primary"
        @click="router.get(route('admin.sale.add'))"
      />
      <q-btn
        class="q-ml-sm"
        :icon="!showFilter ? 'filter_alt' : 'filter_alt_off'"
        dense
        color="grey"
        @click="showFilter = !showFilter"
      />

      <q-btn
        v-if="$can('admin.sale.import-template')"
        class="q-ml-sm"
        icon="download"
        dense
        color="secondary"
        :href="route('admin.sale.import-template')"
        target="_blank"
      >
        <q-tooltip>Template Import</q-tooltip>
      </q-btn>

      <q-btn
        v-if="$can('admin.sale.import')"
        class="q-ml-sm"
        icon="upload_file"
        dense
        color="secondary"
        @click="openImportDialog"
      >
        <q-tooltip>Import Penjualan</q-tooltip>
      </q-btn>

      <q-btn
        class="q-ml-sm"
        icon="file_export"
        dense
        color="grey"
      >
        <q-menu anchor="bottom right" self="top right">
          <q-list style="min-width: 190px">
            <q-item clickable v-ripple :href="buildExportUrl('pdf')">
              <q-item-section avatar>
                <q-icon name="picture_as_pdf" color="red-8" />
              </q-item-section>
              <q-item-section>Export PDF</q-item-section>
            </q-item>
            <q-item clickable v-ripple :href="buildExportUrl('excel')">
              <q-item-section avatar>
                <q-icon name="csv" color="green-8" />
              </q-item-section>
              <q-item-section>Export Excel</q-item-section>
            </q-item>
          </q-list>
        </q-menu>
      </q-btn>
    </template>

    <template #header v-if="showFilter">
      <q-toolbar class="filter-bar" ref="filterToolbarRef">
        <div class="row q-col-gutter-xs items-center q-pa-sm full-width">
          <q-select
            class="custom-select col-xs-6 col-sm-2"
            style="min-width: 150px"
            v-model="filter.fiscal_year"
            :options="fiscalYearOptions"
            label="Fiscal Year"
            dense
            outlined
            map-options
            emit-value
            @update:model-value="onFilterChange"
          />

          <q-select
            class="custom-select col-xs-6 col-sm-2"
            style="min-width: 150px"
            v-model="filter.month"
            :options="monthOptions"
            label="Bulan"
            dense
            outlined
            map-options
            emit-value
            @update:model-value="onFilterChange"
          />

          <q-select
            class="custom-select col-xs-12 col-sm-2"
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
            class="custom-select col-xs-12 col-sm-2"
            style="min-width: 180px"
            v-model="filter.retailer_id"
            :options="retailerOptions"
            label="R1/R2"
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
            placeholder="Cari distributor atau retailer"
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
      <q-banner class="bg-blue-1 text-blue-10 q-mb-sm rounded-borders">
        Total Penjualan: <strong>Rp {{ formatNumber(totalSalesSum) }}</strong>
      </q-banner>

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
          <q-tr :props="props" class="cursor-pointer" @click="onRowClicked(props.row)">
            <q-td key="date" :props="props" class="wrap-column">
              {{ formatDate(props.row.date) }}
              <template v-if="$q.screen.lt.md">
                <div>Jenis: {{ saleTypeLabel(props.row.sale_type) }}</div>
                <div>Distributor: {{ props.row.distributor?.name || '-' }}</div>
                <div>R1/R2: {{ props.row.retailer?.name || '-' }}</div>
                <div>Total: Rp {{ formatNumber(props.row.total_amount) }}</div>
              </template>
            </q-td>
            <q-td key="sale_type" :props="props">
              <q-badge :color="props.row.sale_type === 'retailer' ? 'orange-8' : 'primary'">
                {{ saleTypeLabel(props.row.sale_type) }}
              </q-badge>
            </q-td>
            <q-td key="distributor" :props="props">
              {{ props.row.distributor?.name || '-' }}
            </q-td>
            <q-td key="retailer" :props="props">
              {{ props.row.retailer?.name || '-' }}
            </q-td>
            <q-td key="total_amount" :props="props" class="text-right">
              Rp {{ formatNumber(props.row.total_amount) }}
            </q-td>
            <q-td key="created_by_user" :props="props">
              {{ props.row.created_by_user?.name || '-' }}
            </q-td>
            <q-td key="action" :props="props" @click.stop>
              <q-btn flat dense icon="more_vert">
                <q-menu anchor="bottom right" self="top right">
                  <q-list style="min-width: 190px">
                    <q-item clickable v-ripple @click="router.get(route('admin.sale.detail', props.row.id))">
                      <q-item-section avatar>
                        <q-icon name="visibility" />
                      </q-item-section>
                      <q-item-section>Detail</q-item-section>
                    </q-item>
                    <q-item
                      v-if="$can('admin.sale.edit')"
                      clickable
                      v-ripple
                      @click="router.get(route('admin.sale.edit', props.row.id))"
                    >
                      <q-item-section avatar>
                        <q-icon name="edit" />
                      </q-item-section>
                      <q-item-section>Edit</q-item-section>
                    </q-item>
                    <q-item
                      v-if="$can('admin.sale.delete')"
                      clickable
                      v-ripple
                      class="text-red"
                      @click="deleteItem(props.row)"
                    >
                      <q-item-section avatar>
                        <q-icon name="delete" color="red" />
                      </q-item-section>
                      <q-item-section>Hapus</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </q-td>
          </q-tr>
        </template>
      </q-table>
    </div>

    <q-dialog v-model="importDialog">
      <q-card style="min-width: 520px; max-width: 92vw">
        <q-card-section class="text-subtitle1 text-bold">
          Import Penjualan
        </q-card-section>

        <q-card-section>
          <q-file
            v-model="importFile"
            outlined
            accept=".xlsx,.xls"
            label="Pilih file Excel"
            :disable="importing"
          />

          <div class="q-mt-sm text-caption text-grey-8">
            Gunakan template resmi agar format data valid.
          </div>

          <q-banner v-if="importResult" class="q-mt-md" :class="(importResult.errors?.length || 0) > 0 ? 'bg-orange-1 text-orange-10' : 'bg-green-1 text-green-10'">
            Berhasil: {{ importResult.success || 0 }} baris
            <br />
            Error: {{ importResult.errors?.length || 0 }} baris
          </q-banner>

          <q-list
            v-if="importResult?.errors?.length"
            bordered
            separator
            class="q-mt-md"
            style="max-height: 220px; overflow: auto"
          >
            <q-item v-for="(err, idx) in importResult.errors" :key="idx">
              <q-item-section>
                <q-item-label caption class="text-red">{{ err }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Tutup" :disable="importing" @click="importDialog = false" />
          <q-btn
            color="primary"
            icon="upload"
            label="Import"
            :loading="importing"
            :disable="!importFile"
            @click="submitImport"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </authenticated-layout>
</template>
