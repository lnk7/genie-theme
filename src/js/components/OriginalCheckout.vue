<template>
  <div v-if="showCheckout" class="checkout hidden">

            <div v-if="items.length === 0">
                <p>Your cart is empty, please <a href="/collections">Click here</a></p>
            </div>

            <div v-else>

                <div v-if="!cartHasFreeItems && (shippingData && shippingData.code && shippingData.amount !==0)">
                    <div v-if="itemTotal < 40" class="notice">
                        <span>Minimum Order is £40 <a href=/shop>Click here to keep shopping</a></span>
                    </div>
                    <div v-else-if="itemTotal < 80" class="notice">
                        <span>Order {{ (80 - itemTotal)| toCurrency }} more to get free delivery <a href=/shop>Click here to keep shopping</a></span>
                    </div>
                </div>

                <form method="post" :action="urls.checkout" enctype="multipart/form-data" @submit.prevent>

                    <div class="container">
                        <div class="leftColumn">
                            <div>

                                <div class="section">

                                    <p class="sectionTitle">Your Order</p>

                                    <div class="error" v-if="errors.products" v-html="errors.products"></div>

                                    <div v-for="item in items" class="checkoutRow">
                                        <div style="flex:2" class="cell">
                                            <img :src="item.image" style="height:50px;width:50px;"/>
                                        </div>
                                        <div style="flex:10" class="cell">
                                            {{ item.product }}&nbsp;
                                        </div>
                                        <div style="flex:4" class="cell">
                                            <button class="control" @click.prevent="setItemQuantity(item.key, item.quantity-1)">-</button>
                                            <span>{{ item.quantity }}</span>
                                            <button class="control" @click.prevent="setItemQuantity(item.key, item.quantity+1)">+</button>
                                        </div>
                                        <div style="flex:3" class="cell number">{{ item.total| toCurrency }}</div>
                                        <div style="flex:1" class="cell">
                                            <button class="control" @click.prevent="removeItem(item.key)">x</button>
                                        </div>
                                    </div>
                                    <div class="checkoutRow">
                                        <div style="flex:16"></div>
                                        <div style="flex:3" class="number total cell">{{ itemTotal | toCurrency }}</div>
                                        <div style="flex:1"></div>
                                    </div>

                                </div>

                                <div class="section">

                                    <div class="checkoutRow">
                                        <div style="flex:10">
                                            <p class="sectionTitle">Choose a delivery date <span v-if="postcodeToUse">(for {{ postcodeToUse }})</span>&nbsp;<span class="required">*</span></p>
                                        </div>
                                        <div style="flex:10">
                                            <div v-if="canSelectShippingDate">
                                                <datepicker
                                                        id="delivery_date"
                                                        :key="datePickerComponentKey"
                                                        :value="deliveryDateMoment"
                                                        @input="dateChanged"
                                                        :disabledDates="dateDisabledDays"
                                                        wrapper-class=""
                                                        input-class="calendarInput"
                                                        calendar-class="calendarClass"
                                                        placeholder="Date"
                                                />
                                            </div>
                                            <!-- show something better here
                                            <div v-else>
                                                <p class="error" v-html="reasonWhyCantSelectShippingData"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="checkoutRow" v-for="price in selectedDate.prices" :key="price.code" v-if="canShowShippingChoices">

                                        <div style="flex:16" class="cell">
                                            <input type="radio" name="shipping" :checked="shippingData.code === price.code" @change.prevent="setShippingOption(price.code)">&nbsp;{{ price.name }}
                                        </div>
                                        <div style="flex:3" class="cell total number">
                                            {{ parseFloat(price.price)|toCurrency }}
                                        </div>
                                        <div style="flex:1"></div>
                                    </div>

                                    <div class="checkoutRow" style="margin-top: 20px;" v-if="canShowShippingChoices">
                                        <div style="flex:1" class="cell">
                                            <label>
                                                <input v-model="thisOrderHasAShippingNote" type="checkbox" value="1">
                                                Leave a shipping note
                                            </label>
                                        </div>
                                        <div style="flex:1" class="cell">
                                            <label>
                                                <input v-model="thisOrderIsAGift" type="checkbox" value="1">
                                                This order is a gift
                                            </label>
                                        </div>
                                    </div>
                                    <div class="checkoutRow" v-if="canShowShippingChoices">
                                        <div style="flex:1" class="cell">
                                            <textarea name="shipping_note" v-model="shippingData.delivery_note" rows="2" v-if="thisOrderHasAShippingNote" :required="thisOrderHasAShippingNote" @blur="setShippingOption"></textarea>
                                        </div>
                                        <div style="flex:1" class="cell">
                                            <textarea name="gift_message" v-model="shippingData.gift_message" rows="2" v-if="thisOrderIsAGift" :required="thisOrderIsAGift" @blur="setShippingOption"></textarea>
                                        </div>
                                    </div>
                                </div>


                                <div class="section" >

                                    <p class="sectionTitle">Coupons & Gift Cards</p>


                                    <div class="checkoutRow" v-for="coupon in coupons" v-if="coupons.length > 0">
                                        <div style="flex:16" class="cell" v-html="coupon.label"></div>
                                        <div style="flex:3" class="number total cell">{{ coupon.amount | toCurrency }}</div>
                                        <div style="flex:1" class="cell">
                                            <button class="control" @click.prevent="removeCoupon(coupon.code)">x</button>
                                        </div>
                                    </div>



                                    <div class="checkoutRow" v-for="card in giftCards" v-if="giftCards.length > 0">
                                        <div style="flex:16" class="cell">
                                            <span v-html="card.number"/>
                                            (Remaining balance is {{ card.remaining | toCurrency }})
                                            <div v-if="card.expired">
                                                <br/><span style="color: red; font-weight: 600;">Expired</span>
                                            </div>
                                        </div>
                                        <div style="flex:3" class="cell number total">
                                            <span>{{ card.amount| toCurrency }}</span>
                                        </div>
                                        <div style="flex:1" class="cell">
                                            <button class="control" @click.prevent="removeGiftCard(card.number)">x</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="section">
                                    <div class="checkoutRow">
                                        <div style="flex:16" class="sectionTitle">Total Payable</div>
                                        <div style="flex:3" class="cell number total">
                                            <span>{{ total| toCurrency }}</span>
                                        </div>
                                        <div style="flex:1" class="cell"></div>
                                    </div>

                                    <div v-if="shippingData.code !== `` && canShowShippingChoices">
                                        <slot name="payment" v-if="total > 0"/>

                                        <div class="cell">
                                            <label>
                                                <input type="checkbox" id="terms" name="terms" required/>
                                                I agree to the <a href="/terms">terms and conditions</a><span class="required">*</span>
                                            </label>
                                            <input type="hidden" name="terms-field" value="1"/>
                                            <label>
                                                <input type="checkbox" name="marketing" id="marketing" v-model="customer.acceptsMarketing" @change="saveCustomerData"/>
                                                I agree to receive marketing
                                            </label>
                                        </div>

                                        <div class="checkoutRow" style="margin-top:20px">
                                            <div style="flex:10" class="cell">
                                                <button type="submit" :disabled="!canBeSubmitted" name="woocommerce_checkout_place_order">
                                                    <span>Place Order</span>
                                                </button>
                                            </div>
                                            <div style="flex:10;text-align:right" class="cell" v-if="total >0">
                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-american-express.png"/>
                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-visa.png"/>
                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-mastercard.png"/>
                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-maestro.png"/>
                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-visa-electron.png"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="rightColumn">

                            <div class="checkoutRow">
                                <div style="flex:8">
                                    <label for="couponCode">Have a coupon or Gift Card?</label>
                                    <input id="couponCode" type="text" v-model="couponCode"/>
                                </div>
                                <div style="flex:2; padding:20px;">
                                    <button @click.prevent="applyCouponCode"><span>Apply</span></button>
                                </div>
                            </div>
                            <div v-if="couponError" class="error" v-html="couponError"></div>


                            <p class="sectionTitle">Your Details</p>
                            <div id="customer_details">

                                <input type="hidden" name="billing_country" id="billing_country" value="GB">
                                <input type="hidden" name="shipping_country" id="shipping_country" value="GB">

                                <label for="billing_first_name">First name&nbsp;<span class="required">*</span></label>
                                <input type="text" name="billing_first_name" id="billing_first_name" v-model="customer.firstName" placeholder="" value="" autocomplete="given-name" required @blur="saveCustomerData"/>

                                <label for="billing_last_name">Last name&nbsp;<span class="required">*</span></label>
                                <input type="text" name="billing_last_name" id="billing_last_name" v-model="customer.lastName" placeholder="" value="" autocomplete="family-name" required @blur="saveCustomerData"/>

                                <label for="billing_address_1">Street address&nbsp;<span class="required">*</span></label>
                                <input type="text" name="billing_address_1" id="billing_address_1" v-model="billingAddress.address1" placeholder="House number and street name" required value="" autocomplete="address-line1" @blur="saveCustomerData"/>

                                <label for="billing_address_2" class="screen-reader-text">Apartment, suite, unit, etc. (optional)&nbsp;<span class="optional">(optional)</span></label>
                                <input type="text" name="billing_address_2" id="billing_address_2" v-model="billingAddress.address2" placeholder="Locality (optional)" value="" autocomplete="address-line2" @blur="saveCustomerData"/>

                                <label for="billing_city">Town / City&nbsp;<span class="required">*</span></label>
                                <input type="text" name="billing_city" id="billing_city" placeholder="" v-model="billingAddress.city" value="" autocomplete="address-level2" required @blur="saveCustomerData"/>

                                <label for="billing_state">County</label>
                                <input type="text" value="" placeholder="" name="billing_state" id="billing_state" v-model="billingAddress.state" autocomplete="address-level1" data-input-classes="" @blur="saveCustomerData"/>

                                <label for="billing_postcode">Postcode&nbsp;<span class="required">*</span></label>
                                <input type="text" name="billing_postcode" id="billing_postcode" v-model="billingAddress.postcode" placeholder="" value="" required autocomplete="postal-code" @blur="saveCustomerData"/>
                                <div class="error" v-if="errors.billingPostcode" v-html="errors.billingPostcode"></div>

                                <label for="billing_phone">Phone&nbsp;<span class="required">*</span></label>
                                <input type="tel" name="billing_phone" id="billing_phone" v-model="customer.phone" placeholder="" value="" autocomplete="tel" required @blur="saveCustomerData"/>
                                <div class="error" v-if="errors.phone" v-html="errors.phone"></div>

                                <label for="billing_email">Email address&nbsp;<span class="required">*</span></label>
                                <input type="email" name="billing_email" id="billing_email" v-model="customer.email" placeholder="" value="" autocomplete="email username" required @blur="saveCustomerData"/>
                                <div class="error" v-if="errors.email" v-html="errors.email"></div>

                                <label>
                                    <input id="ship-to-different-address-checkbox" v-model="deliverToDifferentAddress" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" type="checkbox" name="ship_to_different_address" value="1">
                                    Deliver to a different address?
                                </label>

                                <div class="shipping_address" v-if="deliverToDifferentAddress">

                                    <p class="sectionTitle">Shipping Address</p>

                                    <label for="shipping_first_name" class="">First name&nbsp;<span class="required">*</span></label>
                                    <input type="text" name="shipping_first_name" id="shipping_first_name" v-model="shippingAddress.firstName" placeholder="" :required="deliverToDifferentAddress" value="" autocomplete="given-name" @blur="saveCustomerData"/>

                                    <label for="shipping_last_name" class="">Last name&nbsp;<span class="required">*</span></label>
                                    <input type="text" name="shipping_last_name" id="shipping_last_name" v-model="shippingAddress.lastName" placeholder="" :required="deliverToDifferentAddress" value="" autocomplete="family-name" @blur="saveCustomerData"/>

                                    <label for="shipping_address_1" class="">Street address&nbsp;<span class="required">*</span></label>
                                    <input type="text" name="shipping_address_1" id="shipping_address_1" v-model="shippingAddress.address1" placeholder="House number and street name" :required="deliverToDifferentAddress" value="" autocomplete="address-line1" @blur="saveCustomerData"/>

                                    <label for="shipping_address_2" class="screen-reader-text">Apartment,suite, unit, etc. (optional)&nbsp;<span class="optional">(optional)</span></label>
                                    <input type="text" name="shipping_address_2" id="shipping_address_2" v-model="shippingAddress.address2" placeholder="Apartment, suite, unit, etc. (optional)" value="" autocomplete="address-line2" @blur="saveCustomerData"/>

                                    <label for="shipping_city" class="">Town / City&nbsp;<span class="required">*</span></label>
                                    <input type="text" name="shipping_city" id="shipping_city" placeholder="" v-model="shippingAddress.city" value="" autocomplete="address-level2" :required="deliverToDifferentAddress" @blur="saveCustomerData"/>

                                    <label for="shipping_state" class="">County</label>
                                    <input type="text" value="" placeholder="" name="shipping_state" id="shipping_state" v-model="shippingAddress.state" autocomplete="address-level1" @blur="saveCustomerData"/>

                                    <label for="shipping_postcode" class="">Postcode&nbsp;<span class="required">*</span></label>
                                    <input type="text" name="shipping_postcode" v-model="shippingAddress.postcode" id="shipping_postcode" placeholder="" value="" :required="deliverToDifferentAddress" autocomplete="postal-code" @blur="saveCustomerData"/>
                                    <div class="error" v-if="errors.shippingPostcode" v-html="errors.shippingPostcode"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <slot name="nonce"/>
                </form>
            </div>
        </div>
</template>

<script>
export default {
  data() {
    return {}
  }
}
</script>

<style>
.calendarInput {
    width: 100%;
    font-family: "Gill Sans MT", sans-serif;
    font-size: 16px;
    padding: 5px;
    margin-bottom: 10px;
    font-style: normal;
    color: #333;

}

.calendarClass {
    font-family: "Gill Sans MT", sans-serif;
    font-style: normal;
}
</style>

<style scoped>
h1,
h2,
h3,
h4,
h5,
h6 {
  color: inherit;
}



.checkout {
    font-family: "Gill Sans MT", sans-serif;
    font-size: 17px;
}

.container {
    display: flex;
}

.leftColumn {
    flex: 2;
}


.notice {
    background-color: #FFEE85;
    font-size: 120%;
    margin-bottom: 20px;
    padding: 20px;
    text-align: center;
    border-radius: 5px;
    border: 1px solid #999;
}

.rightColumn {
    flex: 1;
    background-color: #ddd;
    border-radius: 5px;
    margin-left: 20px;
    padding: 20px;
}

.error {
    color: red;
    background-color: #fae4e4;
    border-radius:5px;
    padding: 7px;
    margin-bottom: 5px;
    font-family: "Gill Sans MT", sans-serif;
}

.checkout label {
    font-weight: normal;
    font-size: 15px;
    font-style: normal;
    display: block;
    color: #999;
    margin-bottom: 5px;
}


.checkout input, .checkout select {
    width: 100%;
    font-family: "Gill Sans MT", sans-serif;
    font-size: 16px;
    color: #333;
    padding: 5px;
    margin-bottom: 10px;
    font-style: normal;

}

.checkout input[type="checkbox"], .checkout input[type="radio"] {
    width: 15px;
    height: 15px;
    display: inline-block;
    margin-bottom: 10px;
}


.container button {
    font-size: 15px;
    background-color: rgb(209, 194, 153);
    border-radius: 3px;
    color: rgb(20, 32, 50);
    padding: 10px;
    border: 0;
}


.container .control {
    background-color: #ddd;
    border-radius: 3px;
    color: rgb(20, 32, 50);
    padding: 2px;
    border: 0;
    width: 30px;
    height: 30px;
    cursor: pointer;
}

.section {
    background-color: #eee;
    margin-bottom: 20px;
    border-radius: 5px;
    padding: 20px;
}

.sectionTitle {
    font-size: 120%;
    font-weight: bold;
}


.checkoutRow {
    display: flex;
    align-items: center;
}

.cell {
    padding: 3px;
}

.number {
    text-align: right;
}

.total {
    font-weight: bold;
    font-size: 125%;
}

.required {
    color: red;
}


button {
    padding: 10px;
    font-size: 20px;
}
</style>