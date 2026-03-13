<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { handleSubmit } from "@/helpers/client-req-handler";
import { formatNumber } from "@/helpers/utils";

const page = usePage();
const currentUser = page.props.auth.user;

const rawData = page.props.data || {};
const isEdit = !!rawData.id;
const title = `${isEdit ? "Edit" : "Tambah"} Penjualan`;

const toNumberOrNull = (val) => {
  if (val === null || val === undefined || val === "") return null;
  return Number(val);
};

const emptyItem = () => ({
  product_id: null,
  quantity: 1,
  unit: "",
  price: 0,
  subtotal: 0,
});

const mappedItems = (rawData.items || []).map((item) => {
  const quantity = Number(item.quantity || 0);
  const price = Number(item.price || 0);
  return {
    product_id: toNumberOrNull(item.product_id || item.product?.id),
    quantity,
    unit: item.unit || "",
    price,
    subtotal: Number(item.subtotal || quantity * price),
  };
});

const initialSaleType = rawData.sale_type || page.props.saleType || "distributor";
const initialDistributorId =
  toNumberOrNull(rawData.distributor_id) ||
  toNumberOrNull(page.props.defaultDistributorId) ||
  null;

const form = useForm({
  id: rawData.id || null,
  sale_type: initialSaleType,
  date: rawData.date ? String(rawData.date).substring(0, 10) : new Date().toISOString().slice(0, 10),
  distributor_id: initialDistributorId,
  retailer_id: toNumberOrNull(rawData.retailer_id),
  province_id: toNumberOrNull(rawData.province_id),
  district_id: toNumberOrNull(rawData.district_id),
  village_id: toNumberOrNull(rawData.village_id),
  notes: rawData.notes || "",
  items: mappedItems.length > 0 ? mappedItems : [emptyItem()],
});

const productOptions = computed(() =>
  (page.props.products || []).map((product) => ({
    value: Number(product.id),
    label: product.name,
  }))
);

const productMap = computed(() => {
  const out = {};
  (page.props.products || []).forEach((product) => {
    out[Number(product.id)] = product;
  });
  return out;
});

const distributorOptions = computed(() =>
  (page.props.distributors || []).map((item) => ({
    value: Number(item.id),
    label: item.name,
  }))
);

const retailerOptions = computed(() =>
  (page.props.retailers || []).map((item) => ({
    value: Number(item.id),
    label: `${item.name} (${item.type})`,
  }))
);

const saleTypeOptions = computed(() => [
  { value: "distributor", label: "Distributor (masuk ke stok distributor)" },
  { value: "retailer", label: "Retailer (keluar dari stok distributor)" },
]);

const isSaleTypeLocked = computed(() => ["bs", "field_officer"].includes(currentUser.role));
const isDistributorLocked = computed(
  () => currentUser.role === "distributor" && !!page.props.defaultDistributorId
);

const provinces = computed(() =>
  (page.props.provinces || []).map((item) => ({
    value: Number(item.id),
    label: item.name,
  }))
);

const districts = ref([]);
const villages = ref([]);

const districtOptions = computed(() =>
  districts.value.map((item) => ({ value: Number(item.id), label: item.name }))
);

const villageOptions = computed(() =>
  villages.value.map((item) => ({ value: Number(item.id), label: item.name }))
);

const loadDistricts = async (provinceId) => {
  if (!provinceId) {
    districts.value = [];
    return;
  }

  const response = await axios.get(route("admin.territory.api.districts", { provinceId }));
  districts.value = response.data || [];
};

const loadVillages = async (districtId) => {
  if (!districtId) {
    villages.value = [];
    return;
  }

  const response = await axios.get(route("admin.territory.api.villages", { districtId }));
  villages.value = response.data || [];
};

const updateSubtotal = (item) => {
  const quantity = Number(item.quantity || 0);
  const price = Number(item.price || 0);
  item.subtotal = Number((quantity * price).toFixed(2));
};

const productById = (productId) => productMap.value[Number(productId)] || null;

const onProductChange = (item) => {
  const product = productById(item.product_id);
  if (!product) return;

  if (!item.unit) {
    item.unit = product.uom_1 || product.uom_2 || "";
  }

  if (!Number(item.price)) {
    item.price = Number(product.price_1 || product.price_2 || 0);
  }

  updateSubtotal(item);
};

const itemUnitOptions = (item) => {
  const product = productById(item.product_id);
  if (!product) return [];

  const units = [product.uom_1, product.uom_2].filter((u) => !!u);
  return [...new Set(units)].map((u) => ({ value: u, label: u }));
};

const addItem = () => {
  form.items.push(emptyItem());
};

const removeItem = (index) => {
  if (form.items.length === 1) return;
  form.items.splice(index, 1);
};

const grandTotal = computed(() =>
  form.items.reduce((carry, item) => carry + Number(item.quantity || 0) * Number(item.price || 0), 0)
);

const submit = () => {
  form.transform((data) => ({
    ...data,
    distributor_id: toNumberOrNull(data.distributor_id),
    retailer_id: data.sale_type === "retailer" ? toNumberOrNull(data.retailer_id) : null,
    province_id: toNumberOrNull(data.province_id),
    district_id: toNumberOrNull(data.district_id),
    village_id: toNumberOrNull(data.village_id),
    items: data.items
      .filter((item) => toNumberOrNull(item.product_id) && Number(item.quantity) > 0)
      .map((item) => ({
        product_id: toNumberOrNull(item.product_id),
        quantity: Number(item.quantity || 0),
        unit: item.unit || null,
        price: Number(item.price || 0),
      })),
  }));

  handleSubmit({
    form,
    url: route("admin.sale.save"),
  });
};

watch(
  () => form.sale_type,
  (value) => {
    if (value === "distributor") {
      form.retailer_id = null;
    }
  }
);

watch(
  () => form.province_id,
  async (newValue, oldValue) => {
    if (newValue !== oldValue) {
      form.district_id = null;
      form.village_id = null;
    }
    await loadDistricts(newValue);
    villages.value = [];
  }
);

watch(
  () => form.district_id,
  async (newValue, oldValue) => {
    if (newValue !== oldValue) {
      form.village_id = null;
    }
    await loadVillages(newValue);
  }
);

if (isSaleTypeLocked.value) {
  form.sale_type = "retailer";
}

onMounted(async () => {
  if (form.province_id) {
    await loadDistricts(form.province_id);
  }
  if (form.district_id) {
    await loadVillages(form.district_id);
  }

  form.items.forEach((item) => {
    if (!item.unit) {
      onProductChange(item);
    } else {
      updateSubtotal(item);
    }
  });
});
</script>

<template>
  <i-head :title="title" />
  <authenticated-layout>
    <template #title>{{ title }}</template>

    <q-page class="row justify-center">
      <div class="col col-lg-10 q-pa-sm">
        <q-form class="row" @submit.prevent="submit">
          <q-card square flat bordered class="col">
            <q-inner-loading :showing="form.processing">
              <q-spinner size="50px" color="primary" />
            </q-inner-loading>

            <q-card-section>
              <div class="text-subtitle1 text-bold q-mb-sm">Informasi Penjualan</div>

              <div class="row q-col-gutter-md">
                <div class="col-xs-12 col-sm-6 col-md-4">
                  <q-select
                    v-model="form.sale_type"
                    label="Jenis Penjualan"
                    :options="saleTypeOptions"
                    map-options
                    emit-value
                    :disable="form.processing || isSaleTypeLocked"
                    :error="!!form.errors.sale_type"
                    :error-message="form.errors.sale_type"
                  />
                </div>

                <div class="col-xs-12 col-sm-6 col-md-4">
                  <q-input
                    v-model="form.date"
                    type="date"
                    label="Tanggal"
                    :disable="form.processing"
                    :error="!!form.errors.date"
                    :error-message="form.errors.date"
                  />
                </div>

                <div class="col-xs-12 col-sm-6 col-md-4">
                  <q-select
                    v-model="form.distributor_id"
                    label="Distributor"
                    :options="distributorOptions"
                    map-options
                    emit-value
                    :disable="form.processing || isDistributorLocked"
                    :error="!!form.errors.distributor_id"
                    :error-message="form.errors.distributor_id"
                    use-input
                    fill-input
                    hide-selected
                  />
                </div>

                <div class="col-xs-12 col-sm-6 col-md-4" v-if="form.sale_type === 'retailer'">
                  <q-select
                    v-model="form.retailer_id"
                    label="R1/R2"
                    :options="retailerOptions"
                    map-options
                    emit-value
                    :disable="form.processing"
                    :error="!!form.errors.retailer_id"
                    :error-message="form.errors.retailer_id"
                    use-input
                    fill-input
                    hide-selected
                  />
                </div>

                <div class="col-xs-12 col-sm-6 col-md-4">
                  <q-select
                    v-model="form.province_id"
                    label="Provinsi"
                    :options="provinces"
                    map-options
                    emit-value
                    clearable
                    :disable="form.processing"
                    :error="!!form.errors.province_id"
                    :error-message="form.errors.province_id"
                  />
                </div>

                <div class="col-xs-12 col-sm-6 col-md-4">
                  <q-select
                    v-model="form.district_id"
                    label="Kabupaten/Kota"
                    :options="districtOptions"
                    map-options
                    emit-value
                    clearable
                    :disable="form.processing || !form.province_id"
                    :error="!!form.errors.district_id"
                    :error-message="form.errors.district_id"
                  />
                </div>

                <div class="col-xs-12 col-sm-6 col-md-4">
                  <q-select
                    v-model="form.village_id"
                    label="Desa/Kelurahan"
                    :options="villageOptions"
                    map-options
                    emit-value
                    clearable
                    :disable="form.processing || !form.district_id"
                    :error="!!form.errors.village_id"
                    :error-message="form.errors.village_id"
                  />
                </div>

                <div class="col-12">
                  <q-input
                    v-model="form.notes"
                    type="textarea"
                    autogrow
                    maxlength="500"
                    counter
                    label="Catatan"
                    :disable="form.processing"
                    :error="!!form.errors.notes"
                    :error-message="form.errors.notes"
                  />
                </div>
              </div>
            </q-card-section>

            <q-separator />

            <q-card-section>
              <div class="row items-center justify-between q-mb-sm">
                <div class="text-subtitle1 text-bold">Item Penjualan</div>
                <q-btn
                  icon="add"
                  label="Tambah Item"
                  color="primary"
                  dense
                  :disable="form.processing"
                  @click="addItem"
                />
              </div>

              <q-banner v-if="form.errors.items" class="bg-red-1 text-red-10 q-mb-sm">
                {{ form.errors.items }}
              </q-banner>

              <div class="overflow-auto">
                <q-markup-table flat bordered square separator="cell" style="min-width: 860px">
                  <thead>
                    <tr>
                      <th style="width: 32%">Produk</th>
                      <th style="width: 14%">Qty</th>
                      <th style="width: 14%">Satuan</th>
                      <th style="width: 16%">Harga</th>
                      <th style="width: 18%">Subtotal</th>
                      <th style="width: 6%"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(item, index) in form.items" :key="index">
                      <td>
                        <q-select
                          v-model="item.product_id"
                          :options="productOptions"
                          map-options
                          emit-value
                          dense
                          outlined
                          use-input
                          fill-input
                          hide-selected
                          :disable="form.processing"
                          @update:model-value="onProductChange(item)"
                        />
                        <div v-if="form.errors[`items.${index}.product_id`]" class="text-caption text-red q-mt-xs">
                          {{ form.errors[`items.${index}.product_id`] }}
                        </div>
                      </td>

                      <td>
                        <q-input
                          v-model.number="item.quantity"
                          type="number"
                          dense
                          outlined
                          min="0"
                          step="0.01"
                          :disable="form.processing"
                          @update:model-value="updateSubtotal(item)"
                        />
                        <div v-if="form.errors[`items.${index}.quantity`]" class="text-caption text-red q-mt-xs">
                          {{ form.errors[`items.${index}.quantity`] }}
                        </div>
                      </td>

                      <td>
                        <q-select
                          v-model="item.unit"
                          :options="itemUnitOptions(item)"
                          map-options
                          emit-value
                          dense
                          outlined
                          clearable
                          :disable="form.processing"
                        />
                      </td>

                      <td>
                        <q-input
                          v-model.number="item.price"
                          type="number"
                          dense
                          outlined
                          min="0"
                          step="0.01"
                          :disable="form.processing"
                          @update:model-value="updateSubtotal(item)"
                        />
                        <div v-if="form.errors[`items.${index}.price`]" class="text-caption text-red q-mt-xs">
                          {{ form.errors[`items.${index}.price`] }}
                        </div>
                      </td>

                      <td class="text-right text-weight-medium">
                        Rp {{ formatNumber(item.subtotal) }}
                      </td>

                      <td class="text-center">
                        <q-btn
                          flat
                          round
                          dense
                          icon="delete"
                          color="red"
                          :disable="form.processing || form.items.length <= 1"
                          @click="removeItem(index)"
                        />
                      </td>
                    </tr>
                  </tbody>
                </q-markup-table>
              </div>

              <div class="row justify-end q-mt-md">
                <div class="text-subtitle1 text-weight-bold">
                  Grand Total: Rp {{ formatNumber(grandTotal) }}
                </div>
              </div>
            </q-card-section>

            <q-card-section class="q-gutter-sm">
              <q-btn
                icon="save"
                type="submit"
                label="Simpan"
                color="primary"
                :disable="form.processing"
              />
              <q-btn
                icon="cancel"
                label="Batal"
                :disable="form.processing"
                @click="router.get(route('admin.sale.index'))"
              />
            </q-card-section>
          </q-card>
        </q-form>
      </div>
    </q-page>
  </authenticated-layout>
</template>
