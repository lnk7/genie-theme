<template>
  <div>
    <!-- Card number* -->
    <div>
      <label class="block border-px border-gold">
        <div>
          <p class="mb-0 px-0500 text-0875 leading-1500 text-grey-600 tracking-wide">Card number*</p>
        </div>
        <div class="flex">
          <div class="w-full">
            <input
              class="appearance-none px-0500 pt-0 pb-0250 border-none"
              :value="cardNumber"
              @input="$emit(`updateNumber`, $event.target.value)"
              type="tel"
              inputmode="numeric"
              maxlength="20"
              autocomplete="off"
              required
              placeholder="•••• •••• •••• ••••"
            />
          </div>
          <div v-if="cardNumber.length >= 4" class="relative pointer-events-none">
            <CardTypeIcons :type="cardType" />
          </div>
        </div>
      </label>
    </div>
    <div class="flex pt-1000">
      <!-- Expiry (MM)* -->
      <div class="flex-1 mr-1000">
        <div class="block border-px border-gold">
          <div>
            <p class="mb-0 px-0500 text-0875 leading-1500 text-grey-600 tracking-wide">Expiry month*</p>
          </div>
          <div>
            <label class="block">
              <input
                class="appearance-none w-2500 px-0500 pt-0 pb-0250 border-none"
                v-model="expiryMonth"
                type="tel"
                inputmode="numeric"
                maxlength="2"
                autocomplete="off"
                required
                placeholder="MM"
              />
            </label>
          </div>
        </div>
      </div>
      <!-- Expiry (YY)* -->
      <div class="flex-1 mr-1000">
        <div class="block border-px border-gold">
          <div>
            <p class="mb-0 px-0500 text-0875 leading-1500 text-grey-600 tracking-wide">Expiry year*</p>
          </div>
          <div>
            <label class="block w-full">
              <input
                class="appearance-none px-0500 pt-0 pb-0250 border-none"
                v-model="expiryYear"
                type="tel"
                inputmode="numeric"
                maxlength="2"
                autocomplete="off"
                required
                placeholder="YY"
              />
            </label>
          </div>
        </div>
      </div>
      <!-- Card code* -->
      <div class="flex-1 ">
        <label class="block border-px border-gold">
          <div>
            <p class="mb-0 px-0500 text-0875 leading-1500 text-grey-600 tracking-wide">CVC code*</p>
          </div>
          <div>
            <input
              class="appearance-none px-0500 pt-0 pb-0250 border-none"
              :value="cardCvcCode"
              @input="$emit(`updateCvc`, $event.target.value)"
              type="tel"
              inputmode="numeric"
              maxlength="4"
              autocomplete="off"
              required
              placeholder="•••"
            />
          </div>
        </label>
      </div>
    </div>
  </div>
</template>

<script>
// Airplus  122000000000003
// American Express  34343434343434
// Cartebleue  5555555555554444
// Dankort  5019717010103742
// Diners  36700102000000 36148900647913
// Discover card  6011000400000000
// JCB  3528000700000000
// Laser  630495060000000000 630490017740292441
// Maestro  6759649826438453 6799990100000000019
// Mastercard  5555555555554444 5454545454545454
// Visa  4444333322221111 4911830000000 4917610000000000
// Visa Debit  4462030000000000 4917610000000000003
// Visa Electron (UK only)  4917300800000000
// Visa Purchasing  4484070000000000
import creditCardType from 'credit-card-type'
import CardTypeIcons from './CardTypeIcons'
export default {
  components: {
    CardTypeIcons
  },
  props: {
    cardNumber: {
      type: String,
      default: ``
    },
    cardExpiryDate: {
      type: String,
      default: ``
    },
    cardCvcCode: {
      type: String,
      default: ``
    }
  },
  data() {
    return {
      cardType: ``,
      expiryYear: ``,
      expiryMonth: ``
    }
  },
  watch: {
    cardType: {
      immediate: true,
      handler(newVal, oldVal) {
        if (newVal !== oldVal) {
          let cardType = ``
          if (newVal === `Mastercard`) {
            cardType = `MasterCard`
          } else {
            cardType = newVal
          }
          this.$emit(`updateCardType`, cardType)
        }
      }
    },
    cardNumber: {
      immediate: true,
      handler(newVal, oldVal) {
        if (newVal !== oldVal) {
          if (creditCardType(newVal)[0] !== undefined && creditCardType(newVal)[0] !== undefined) {
            this.cardType = creditCardType(newVal)[0].niceType
          }
        }
      }
    },
    cardExpiryDate(newVal, oldVal) {
      if (newVal !== oldVal) {
        if (this.cardExpiryDate.length === 5) {
          this.expiryMonth = newVal.slice(0, 2)
          this.expiryYear = newVal.substring(3, 5)
        }
      }
    },
    expiryMonth(newVal, oldVal) {
      if (newVal !== oldVal) {
        const expiryDate = `${ newVal }/${ this.expiryYear }`
        this.$emit(`updateExpiryDate`, expiryDate)
      }
    },
    expiryYear(newVal, oldVal) {
      if (newVal !== oldVal) {
        const expiryDate = `${ this.expiryMonth }/${ newVal }`
        this.$emit(`updateExpiryDate`, expiryDate)
      }
    }
  }
}
</script>