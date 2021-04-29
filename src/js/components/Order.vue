<template>
  <div v-if="checkoutData" class="max-w-sm mx-auto font-sans not-italic">
    <div class="border-px border-gold shadow bg-grey-100">
      <div class="px-1000 text-center">
        <h2 class="m-0 mt-0500 font-brand text-1125 leading-1875 text-navy">Order #<span v-text="id" /> received</h2>
        <p class="mb-1000 text-1000">Thank you, we have received your order</p>
        <div
          class="mb-1000 bg-gold-lighter border-px border-gold p-0500 text-gold-dark text-1000"
          v-if="checkoutData.product_messages"
          v-html="checkoutData.product_messages"
        />
      </div>
      <div class="">
        <div class="p-1000">
          <div>
            <p class="mb-0 text-0750 text-grey-400 uppercase">Basket items:</p>
          </div>
          <div
            v-for="item in checkoutData.items"
            class="flex my-1000"
          >
            <div class="self-start mb-auto">
              <img
                :src="item.image"
                class="w-3000 h-3000"
              />
            </div>
            <div class="flex-1 self-start mb-auto px-1000">
              <p
                class="mb-0 text-0875 text-navy"
                v-text="item.product"
              />
              <p class="mb-0 text-0875 text-grey-600">{{ item.price | toCurrency }} x {{ item.quantity }}</p>
            </div>
            <div class="self-start mb-auto ml-auto pl-1000">
              <p class="mb-0 text-0875 text-navy">{{ item.total | toCurrency }}</p>
            </div>
          </div>
          <div>
            <div class="flex pb-1000">
              <p class="mb-0 text-1000 text-navy uppercase">Basket total:</p>
              <p class="mb-0 ml-auto text-1000 text-navy">{{ itemTotal | toCurrency }}</p>
            </div>
          </div>
          <div v-if="checkoutData.coupons.length > 0" class="py-1000">
            <div>
              <p class="mb-0 text-0750 text-grey-400 uppercase">Discount code(s):</p>
            </div>
            <div
              v-for="coupon in checkoutData.coupons"
              class="flex mb-1000 py-1000 bg-white border-px border-grey-400"
            >
              <div class="flex-1 self-start mb-auto px-1000">
                <p class="mb-0 text-0875 text-navy uppercase">
                  <span v-text="coupon.code"/>
                  <span v-if="coupon.label !== ''" v-text="`: ${ coupon.label }`"/>
                </p>
              </div>
              <div class="self-start mb-auto ml-auto px-1000">
                <p class="mb-0 text-0875 text-navy">{{ coupon.amount | toCurrency }}</p>
              </div>
            </div>
          </div>
          <div v-if="checkoutData.shippingData">
            <div class="flex pb-1000">
              <p class="mb-0 text-1000 text-navy uppercase">Delivery (<span v-text="checkoutData.shippingData.name" />):</p>
              <p class="mb-0 ml-auto text-1000 text-navy">{{ parseFloat(checkoutData.shippingData.amount) | toCurrency }}</p>
            </div>
          </div>
          <div v-if="checkoutData.giftCards.length > 0">
            <div>
              <p class="mb-0 text-0750 text-grey-400 uppercase">Giftcard(s):</p>
            </div>
            <div
              v-for="card in checkoutData.giftCards"
              class="flex mb-1000 py-1000 bg-white border-px border-grey-400"
            >
              <div class="flex-1 self-start mb-auto px-1000">
                <p class="mb-0 text-0875 text-navy uppercase">
                  <span v-text="card.number" /> <span v-if="card.expired" class="text-red">[Expired]</span>
                </p>
                <div class="text-0750 text-grey-400">
                   {{ card.remaining | toCurrency }} remaining on card
                </div>
              </div>
              <div class="self-start mb-auto ml-auto px-1000">
                <p class="mb-0 text-0875 text-navy uppercase">{{ card.amount | toCurrency }}</p>
              </div>
            </div>
          </div>
          <div>
            <div class="flex">
              <p class="mb-0 text-1000 text-navy uppercase">Grand total:</p>
              <p class="mb-0 ml-auto text-1000 text-navy">{{ totalPaid | toCurrency }}</p>
            </div>
          </div>
        </div>
        <div class="sm:flex">
          <div class="sm:w-6/12 p-1000">
            <div>
              <p class="mb-0 text-0750 text-grey-400 uppercase">Delivery address:</p>
            </div>
            <div class="text-navy">
              <span
                v-if="checkoutData.customerData.shippingAddress.firstName != ''" v-text="checkoutData.customerData.shippingAddress.firstName"
                class="text-1000"
              />
              <span
                v-if="checkoutData.customerData.shippingAddress.lastName != ''" v-text="checkoutData.customerData.shippingAddress.lastName"
                class="text-1000"
              />
            </div>
            <div
              v-if="checkoutData.customerData.shippingAddress.address1 != ''" v-text="checkoutData.customerData.shippingAddress.address1"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.shippingAddress.address2 != ''" v-text="checkoutData.customerData.shippingAddress.address2"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.shippingAddress.city != ''" v-text="checkoutData.customerData.shippingAddress.city"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.shippingAddress.state != ''" v-text="checkoutData.customerData.shippingAddress.state"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.shippingAddress.postcode != ''" v-text="checkoutData.customerData.shippingAddress.postcode"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.shippingAddress.country != ''" v-text="checkoutData.customerData.shippingAddress.country"
              class="text-1000 text-navy"
            />
          </div>
          <div class="sm:w-6/12 p-1000">
            <div>
              <p class="mb-0 text-0750 text-grey-400 uppercase">Billing address:</p>
            </div>
            <div class="text-navy">
              <span
                v-if="checkoutData.customerData.billingAddress.firstName != ''" v-text="checkoutData.customerData.billingAddress.firstName"
                class="text-1000"
              />
              <span
                v-if="checkoutData.customerData.billingAddress.lastName != ''" v-text="checkoutData.customerData.billingAddress.lastName"
                class="text-1000"
              />
            </div>
            <div
              v-if="checkoutData.customerData.billingAddress.address1 != ''" v-text="checkoutData.customerData.billingAddress.address1"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.billingAddress.address2 != ''" v-text="checkoutData.customerData.billingAddress.address2"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.billingAddress.city != ''" v-text="checkoutData.customerData.billingAddress.city"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.billingAddress.state != ''" v-text="checkoutData.customerData.billingAddress.state"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.billingAddress.postcode != ''" v-text="checkoutData.customerData.billingAddress.postcode"
              class="text-1000 text-navy"
            />
            <div
              v-if="checkoutData.customerData.billingAddress.country != ''" v-text="checkoutData.customerData.billingAddress.country"
              class="text-1000 text-navy"
            />
          </div>
        </div>
        <div class="px-1000 pb-2000 text-navy text-center">
          <p class="mb-0500 text-1250">Order scheduled for delivery on <span class="text-gold" v-text="deliveryDate" />.</p>
          <a
            href=/refer-a-friend
            class="inline-block px-2000 py-0500 bg-gold hover:bg-transparent border-px border-gold rounded-0125 text-center uppercase text-1000 text-gold-dark hover:text-gold font-sans not-italic"
            v-text="`Refer a friend`"
          />
            <p class="mt-100 mb-0500 text-1000">Refer a friend and receive £10 off your next order</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import axios from 'axios'
  export default {
    props: {
      urls: {
        type: Object,
        required: true
      },
      orderId: {
        type: String,
        required: true
      },
      id: {
        type: [String, Number],
        required: true
      }
    },
    data() {
      return {
        apiLoading: false,
        checkoutData: null
      }
    },
    computed: {
      deliveryDate() {
        return new Date(this.checkoutData.shippingData.date).toDateString()
      },
      itemTotal() {
        let total = 0
        this.checkoutData.items.forEach(item => total += parseFloat(item.total))
        return total
      },
      couponsTotal() {
        let total = 0
        this.checkoutData.coupons.forEach(item => total += parseFloat(item.amount))
        return total
      },
      giftCardTotal() {
        let total = 0
        this.checkoutData.giftCards.forEach(item => total += parseFloat(item.amount))
        return total
      },
      totalPaid() {
        return this.itemTotal + this.couponsTotal + this.giftCardTotal + parseFloat(this.checkoutData.shippingData.amount)
      }
    },
    created() {
      this.getCheckoutData()
    },
    methods: {
      getCheckoutData() {
        this.apiLoading = true

        axios.post(this.urls.getCheckoutData, { order_id: this.orderId }).then((resp) => {
          this.apiLoading = false
          this.checkoutData = resp.data
        })
      }
    }
  }
</script>
