<template>
  <div class="px-1000 border-px border-gold shadow bg-grey-100">
    <h2 class="m-0 mt-0500 mb-1000 font-brand text-1125 leading-1875 text-navy text-center">Order summary</h2>
    <div>
      <BasketItem
        v-for="item in items"
        :key="item.key"
        :item="item"
        @remove="$emit(`removeProduct`, item)"
        @increase="$emit(`setItemQuantity`, { item: item, newVal: item.quantity + 1 })"
        @decrease="$emit(`setItemQuantity`, { item: item, newVal: item.quantity - 1 })"
      />
      <div class="flex text-1000 text-grey-600">
        <div class="">Subtotal:</div>
        <div class="ml-auto">{{ itemTotal | toCurrency }}</div>
      </div>
      <div>
        <hr class="mt-1000 mb-1500 border-grey-200">
      </div>
    </div>
    <div>
      <div>
        <div class="flex">
          <input
            type="text"
            class=" appearance-none flex-1 inline-block px-0500 py-0500 bg-transparent border-px border-gold rounded-0 text-1000"
            :value="couponCode"
            placeholder="Enter a discount or gift card code"
            @input="$emit(`changeCouponCode`, $event.target.value)"
            @keyup.enter="$emit(`applyCouponCode`)"
          >
          <button
            type="button"
            class="inline-block px-2000 py-0500 bg-gold hover:bg-gold-light border-px border-gold rounded-0 rounded-tr-0125 rounded-br-0125 text-center uppercase text-1000 text-gold-dark"
            @click.prevent="$emit(`applyCouponCode`)"
            v-text="`Apply`"
          />
        </div>
        <div v-if="couponError" class="text-1000 text-red" v-html="couponError"></div>
        <div>
          <div v-if="coupons.length > 0">
            <div>
              <p class="mt-0500 mb-0 text-0750 text-grey-600" v-text="`Discout(s):`" />
            </div>
            <div
              v-for="coupon in coupons"
              class="flex mb-1000 p-0500 rounded-0125 border-px border-grey-400 text-0875 bg-white"
            >
              <div class="self-center my-auto uppercase" v-text="coupon.code" />
              <div class="self-center my-auto ml-auto">{{ coupon.amount | toCurrency }}</div>
              <div class="self-center my-auto">
                <button
                  class="inline-block ml-1000 mr-0500 p-0 bg-transparent hover:bg-transparent focus:bg-transparent border-0 text-0750 hover:text-gold"
                  @click.prevent="$emit(`removeCoupon`, coupon.code)"
                  v-text="`x`"
                />
              </div>
            </div>
          </div>
          <div v-if="giftCards.length > 0">
            <div>
              <p class="mt-0500 mb-0 text-0750 text-grey-600" v-text="`Gift card(s):`" />
            </div>
            <div
              v-for="card in giftCards"
              v-if="giftCards.length > 0"
              class="flex mb-1000 p-0500 rounded-0125 border-px border-grey-400 text-0875 bg-white"
            >
              <div class="self-start mb-auto uppercase">
                <div>
                  <span v-text="card.number" /> <span v-if="card.expired" class="text-red">[Expired]</span>
                </div>
                <div class="text-0750 text-grey-400">
                   {{ card.remaining | toCurrency }} remaining on card
                </div>
              </div>
              <div class="self-start mb-auto ml-auto">{{ card.amount | toCurrency }}</div>
              <div class="self-start mb-auto">
                <button
                  class="inline-block ml-1000 mr-0500 p-0 bg-transparent hover:bg-transparent focus:bg-transparent border-0 text-0750 hover:text-gold"
                  @click.prevent="$emit(`removeGiftCard`, card.number)"
                  v-text="`x`"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
      <div>
        <hr class="mt-1500 mb-1000 border-grey-200">
      </div>
    </div>
    <div>
      <div class="flex text-1000 text-grey-600 pb-0500">
        <div v-if="shippingInfo">
          <span v-text="shippingInfo" /> <a v-if="activeStep !== 1" class="underline text-navy hover:text-gold" v-text="`(change)`" @click.prevent="$emit(`changeShipping`)" />
        </div>
        <div v-if="shippingInfo" class="ml-auto">{{ shippingTotal | toCurrency }}</div>
        <div v-else class="ml-auto italic" v-text="`Shipping not yet calculated`" />
      </div>
      <div v-if="paid > 0" class="flex pb-1000">
        <div>Payment made:</div>
        <div class="ml-auto">{{ paid | toCurrency }}</div>
      </div>
      <div class="flex pb-1000">
        <div>Total payable:</div>
        <div class="ml-auto">{{ pending | toCurrency }}</div>
      </div>
    </div>
  </div>
</template>

<script>
import BasketItem from './BasketItem'
export default {
  components: {
    BasketItem
  },
  props: {
    items: {
      type: Array,
      default: []
    },
    itemTotal: {
      type: Number
    },
    coupons: {
      type: Array
    },
    couponCode: {
      type: String
    },
    couponError: {
      type: String
    },
    couponTotal: {
      type: Number
    },
    giftCards: {
      type: Array
    },
    giftCardTotal: {
      type: Number
    },
    shippingData: {
      type: Object
    },
    shippingTotal: {
      type: Number
    },
    activeStep: {
      type: Number
    },
    paid: {
      type: Number
    },
    total: {
      type: Number
    },
    pending: {
      type: Number
    }
  },
  computed: {
    shippingInfo() {
      if (this.shippingData.name) {
        return `${ this.shippingData.name }, ${ new Date(this.shippingData.date).toDateString() }`
      } else {
        return null
      }
    }
  }
}
</script> 