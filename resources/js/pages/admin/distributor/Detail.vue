<script setup>
import { router, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { formatDate } from "@/helpers/datetime";
import { formatNumber } from "@/helpers/utils";

const page = usePage();
const title = "Detail Distributor";

const distributor = computed(() => page.props.data || {});
const recentSales = computed(() => page.props.recent_sales || []);

const stockRows = computed(() => distributor.value.distributor_stock || []);

const saleProductsLabel = (sale) => {
  const items = sale.items || [];
  if (!items.length) return "-";
  return items
    .map((item) => {
      const qty = formatNumber(item.quantity, "id-ID", 2);
      const productName = item.product?.name || "Produk";
      return `${productName} (${qty})`;
    })
    .join(", ");
};
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
        @click="router.get(route('admin.distributor.index'))"
      />
    </template>

    <template #right-button>
      <q-btn
        v-if="$can('admin.distributor-stock.movements')"
        icon="inventory"
        dense
        color="secondary"
        @click="router.get(route('admin.distributor-stock.movements', { distributorId: distributor.id }))"
      >
        <q-tooltip>Riwayat Pergerakan Stok</q-tooltip>
      </q-btn>
    </template>

    <q-page class="row justify-center">
      <div class="col col-lg-10 q-pa-sm">
        <q-card square flat bordered>
          <q-card-section>
            <div class="row q-col-gutter-md">
              <div class="col-xs-12 col-sm-6 col-md-4">
                <div class="text-caption text-grey-7">Nama Distributor</div>
                <div class="text-body1 text-weight-medium">{{ distributor.name || '-' }}</div>
              </div>

              <div class="col-xs-12 col-sm-6 col-md-4">
                <div class="text-caption text-grey-7">No HP</div>
                <div class="text-body1">{{ distributor.phone || '-' }}</div>
              </div>

              <div class="col-xs-12 col-sm-6 col-md-4">
                <div class="text-caption text-grey-7">Status</div>
                <div>
                  <q-badge :color="distributor.active ? 'green' : 'red'">
                    {{ distributor.active ? "Aktif" : "Tidak Aktif" }}
                  </q-badge>
                </div>
              </div>

              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Provinsi</div>
                <div class="text-body1">{{ distributor.province?.name || '-' }}</div>
              </div>

              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Kabupaten/Kota</div>
                <div class="text-body1">{{ distributor.district?.name || '-' }}</div>
              </div>

              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Desa/Kelurahan</div>
                <div class="text-body1">{{ distributor.village?.name || '-' }}</div>
              </div>

              <div class="col-xs-12 col-sm-6">
                <div class="text-caption text-grey-7">Assigned User</div>
                <div class="text-body1">{{ distributor.assigned_user?.name || '-' }}</div>
              </div>

              <div class="col-12">
                <div class="text-caption text-grey-7">Alamat</div>
                <div class="text-body1" style="white-space: pre-wrap">{{ distributor.address || '-' }}</div>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card square flat bordered class="q-mt-md">
          <q-card-section>
            <div class="text-subtitle1 text-weight-bold q-mb-sm">Stok Saat Ini</div>
            <q-markup-table flat bordered square separator="cell">
              <thead>
                <tr>
                  <th class="text-left">Produk</th>
                  <th class="text-left">Satuan</th>
                  <th class="text-right">Stok</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="stock in stockRows" :key="stock.id">
                  <td>{{ stock.product?.name || '-' }}</td>
                  <td>{{ stock.product?.unit || '-' }}</td>
                  <td class="text-right text-weight-medium">
                    {{ formatNumber(stock.stock_quantity, 'id-ID', 2) }}
                  </td>
                </tr>
                <tr v-if="!stockRows.length">
                  <td colspan="3" class="text-center text-grey">Belum ada data stok</td>
                </tr>
              </tbody>
            </q-markup-table>
          </q-card-section>
        </q-card>

        <q-card square flat bordered class="q-mt-md">
          <q-card-section>
            <div class="text-subtitle1 text-weight-bold q-mb-sm">10 Penjualan Terbaru</div>
            <q-markup-table flat bordered square separator="cell">
              <thead>
                <tr>
                  <th class="text-left">Tanggal</th>
                  <th class="text-left">R1/R2</th>
                  <th class="text-left">Produk</th>
                  <th class="text-right">Total (Rp)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sale in recentSales" :key="sale.id">
                  <td>{{ formatDate(sale.date) }}</td>
                  <td>{{ sale.retailer?.name || '-' }}</td>
                  <td>{{ saleProductsLabel(sale) }}</td>
                  <td class="text-right text-weight-medium">Rp {{ formatNumber(sale.total_amount || 0) }}</td>
                </tr>
                <tr v-if="!recentSales.length">
                  <td colspan="4" class="text-center text-grey">Belum ada penjualan</td>
                </tr>
              </tbody>
            </q-markup-table>
          </q-card-section>
        </q-card>
      </div>
    </q-page>
  </authenticated-layout>
</template>
