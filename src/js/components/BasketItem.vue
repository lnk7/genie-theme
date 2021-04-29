<template>
  <div class="flex flex-wrap mb-1000">
    <div class="sm:flex-1 flex w-full sm:w-auto pb-1000 sm:pb-0">
      <div class="self-center my-auto">
        <img
          :src="item.image"
          class="w-3000 h-3000"
        />
      </div>
      <div class="flex-1 self-center my-auto px-1000">
        <p
          class="mb-0 text-0875"
          v-text="item.product"
        />
        <p class="mb-0 text-0875 text-grey-600">{{ item.total | toCurrency }}</p>
      </div>
    </div>
    <div class="flex w-full sm:w-auto">
      <div class="self-center my-auto">
        <div class="flex border-px border-gold">
          <button
            type="button"
            class="appearance-none w-1500 p-0 bg-white hover:bg-gold focus:bg-gold border-none rounded-0 text-0875 leading-1750 text-center text-gold hover:text-white focus:text-white"
            @click="$emit(`decrease`)"
          >
            <span class="block mt-n-0250" v-text="`-`" />
          </button>
          <div
            class="w-1500 text-0750 leading-2000 text-center"
            v-text="item.quantity"
          />
          <button
            type="button"
            class="appearance-none w-1500 p-0 bg-white hover:bg-gold focus:bg-gold border-none rounded-0 text-0875 leading-1750 text-center text-gold hover:text-white focus:text-white"
            @click="$emit(`increase`)"
          >
            <span class="block" v-text="`+`" />
          </button>
        </div>
      </div>
      <div class="self-center my-auto ml-auto sm:ml-1000">
        <button
          type="button"
          class="appearance-none block w-1625 p-0 bg-white hover:bg-gold focus:bg-gold border-px border-gold rounded-0 text-0750 leading-2000 text-center text-gold hover:text-white focus:text-white"
          v-text="`x`"
          @click="$emit(`remove`)"
        />
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    item: {
      type: Object,
      required: true
    }
  },
  data() {
    return {}
  }
}
</script>