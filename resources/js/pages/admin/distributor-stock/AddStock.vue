<script setup>
import { router, useForm, usePage } from "@inertiajs/vue3";
import { computed } from "vue";
import { handleSubmit } from "@/helpers/client-req-handler";

const page = usePage();
const title = "Tambah Stok Distributor";

const distributorOptions = computed(() =>
  (page.props.distributors || []).map((item) => ({
    value: item.id,
    label: item.name,
  }))
);

const productOptions = computed(() =>
  (page.props.products || []).map((item) => ({
    value: item.id,
    label: item.name,
  }))
);

const form = useForm({
  distributor_id: null,
  product_id: null,
  quantity: null,
  reference: "",
  notes: "",
});

const submit = () => {
  handleSubmit({
    form,
    url: route("admin.distributor-stock.save"),
  });
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
        @click="router.get(route('admin.distributor-stock.index'))"
      />
    </template>

    <q-page class="row justify-center">
      <div class="col col-md-6 q-pa-sm">
        <q-form class="row" @submit.prevent="submit">
          <q-card square flat bordered class="col">
            <q-inner-loading :showing="form.processing">
              <q-spinner size="50px" color="primary" />
            </q-inner-loading>

            <q-card-section>
              <q-select
                v-model="form.distributor_id"
                label="Distributor"
                :options="distributorOptions"
                map-options
                emit-value
                use-input
                fill-input
                hide-selected
                :disable="form.processing"
                :error="!!form.errors.distributor_id"
                :error-message="form.errors.distributor_id"
              />

              <q-select
                v-model="form.product_id"
                label="Produk"
                :options="productOptions"
                map-options
                emit-value
                use-input
                fill-input
                hide-selected
                :disable="form.processing"
                :error="!!form.errors.product_id"
                :error-message="form.errors.product_id"
              />

              <q-input
                v-model.number="form.quantity"
                type="number"
                min="0"
                step="0.01"
                label="Qty"
                :disable="form.processing"
                :error="!!form.errors.quantity"
                :error-message="form.errors.quantity"
              />

              <q-input
                v-model.trim="form.reference"
                type="text"
                label="Referensi"
                :disable="form.processing"
                :error="!!form.errors.reference"
                :error-message="form.errors.reference"
              />

              <q-input
                v-model.trim="form.notes"
                type="textarea"
                autogrow
                maxlength="500"
                counter
                label="Catatan"
                :disable="form.processing"
                :error="!!form.errors.notes"
                :error-message="form.errors.notes"
              />
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
                @click="router.get(route('admin.distributor-stock.index'))"
              />
            </q-card-section>
          </q-card>
        </q-form>
      </div>
    </q-page>
  </authenticated-layout>
</template>
