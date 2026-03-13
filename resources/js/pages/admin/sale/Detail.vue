<script setup>
import { router, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { formatDate } from "@/helpers/datetime";
import { formatNumber } from "@/helpers/utils";

const page = usePage();
const title = "Detail Penjualan";
const sale = computed(() => page.props.data || {});

const saleTypeLabel = computed(() =>
  sale.value.sale_type === "retailer"
    ? "Retailer (keluar stok distributor)"
    : "Distributor (masuk stok distributor)"
);
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
        @click="router.get(route('admin.sale.index'))"
      />
    </template>

    <template #right-button>
      <q-btn
        v-if="$can('admin.sale.edit')"
        icon="edit"
        dense
        color="primary"
        @click="router.get(route('admin.sale.edit', { id: sale.id }))"
      />
    </template>

    <q-page class="row justify-center">
      <div class="col col-lg-10 q-pa-sm">
        <q-card square flat bordered>
          <q-card-section>
            <div class="row q-col-gutter-md">
              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Tanggal</div>
                <div class="text-body1 text-weight-medium">{{ formatDate(sale.date) }}</div>
              </div>
              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Jenis Penjualan</div>
                <div class="text-body1 text-weight-medium">{{ saleTypeLabel }}</div>
              </div>
              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Total</div>
                <div class="text-body1 text-weight-bold">Rp {{ formatNumber(sale.total_amount) }}</div>
              </div>

              <div class="col-xs-12 col-sm-6">
                <div class="text-caption text-grey-7">Distributor</div>
                <div class="text-body1">{{ sale.distributor?.name || "-" }}</div>
              </div>
              <div class="col-xs-12 col-sm-6">
                <div class="text-caption text-grey-7">R1/R2</div>
                <div class="text-body1">{{ sale.retailer?.name || "-" }}</div>
              </div>

              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Provinsi</div>
                <div class="text-body1">{{ sale.province?.name || "-" }}</div>
              </div>
              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Kabupaten/Kota</div>
                <div class="text-body1">{{ sale.district?.name || "-" }}</div>
              </div>
              <div class="col-xs-12 col-sm-4">
                <div class="text-caption text-grey-7">Desa/Kelurahan</div>
                <div class="text-body1">{{ sale.village?.name || "-" }}</div>
              </div>

              <div class="col-xs-12 col-sm-6">
                <div class="text-caption text-grey-7">Dibuat oleh</div>
                <div class="text-body1">{{ sale.created_by_user?.name || "-" }}</div>
              </div>
              <div class="col-xs-12 col-sm-6">
                <div class="text-caption text-grey-7">Diubah oleh</div>
                <div class="text-body1">{{ sale.updated_by_user?.name || "-" }}</div>
              </div>

              <div class="col-12">
                <div class="text-caption text-grey-7">Catatan</div>
                <div class="text-body1" style="white-space: pre-wrap">{{ sale.notes || "-" }}</div>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card square flat bordered class="q-mt-md">
          <q-card-section>
            <div class="text-subtitle1 text-weight-bold q-mb-sm">Item Penjualan</div>
            <q-markup-table flat bordered square separator="cell">
              <thead>
                <tr>
                  <th class="text-left">Produk</th>
                  <th class="text-right">Qty</th>
                  <th class="text-left">Satuan</th>
                  <th class="text-right">Harga</th>
                  <th class="text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in sale.items || []" :key="item.id">
                  <td>{{ item.product?.name || "-" }}</td>
                  <td class="text-right">{{ formatNumber(item.quantity, "id-ID", 2) }}</td>
                  <td>{{ item.unit || "-" }}</td>
                  <td class="text-right">Rp {{ formatNumber(item.price) }}</td>
                  <td class="text-right text-weight-medium">Rp {{ formatNumber(item.subtotal) }}</td>
                </tr>
                <tr v-if="!(sale.items || []).length">
                  <td colspan="5" class="text-center text-grey">Belum ada item</td>
                </tr>
              </tbody>
            </q-markup-table>
          </q-card-section>
        </q-card>
      </div>
    </q-page>
  </authenticated-layout>
</template>
