<template>
    <div class="relative">
        <div v-if="status !== 'cart' && status !== 'pending' && status !== 'failed'">
            <!-- // CART NO LONGER EDITIBLE MESSAGE -->
            <div class="p-1000">
                <div class="mb-2000 px-1000 py-2000 bg-navy border-px border-navy text-center text-white">
                    <h2 class="m-0 mb-0500 font-sans text-1125 leading-1875 text-white">This order has been processed and can no longer be edited.</h2>
                    <a
                            href=/collections
                            class="inline-block px-2000 py-0500 bg-gold hover:bg-transparent border-px border-gold rounded-0125 text-center uppercase text-1000 text-gold-dark hover:text-gold font-sans not-italic"
                            v-text="`Back to the shop`"
                    />
                </div>
            </div>
        </div>
        <div v-else>
            <transition name="vt-fade">
                <div v-if="loaded && apiLoading || loaded && loadingData" class="flex absolute inset-0 z-20 opacity-70 bg-white">
                    <div class="self-center m-auto">
                        <LoadingWheel/>
                    </div>
                </div>
            </transition>
            <transition name="vt-fade">
                <div
                        v-if="!loaded"
                        class="text-center"
                >
                    <div class="inline-block mx-auto p-2000">
                        <LoadingWheel/>
                    </div>
                </div>
                <div v-else class="font-sans not-italic text-navy">
                    <div class="px-1000">
                        <h1 class="font-brand font-200 text-1500 text-center text-navy">Checkout</h1>
                    </div>
                    <div
                            v-if="items.length === 0"
                            class="px-1000"
                    >
                        <div class="mb-2000 px-1000 py-2000 bg-navy border-px border-navy text-center text-white">
                            <h2 class="m-0 mb-0500 font-sans text-1125 leading-1875 text-white">Your basket is empty.</h2>
                            <a
                                    href="/collections"
                                    class="inline-block px-2000 py-0500 bg-gold hover:bg-transparent border-px border-gold rounded-0125 text-center uppercase text-1000 text-gold-dark hover:text-gold"
                                    v-text="`Start shopping`"
                            />
                        </div>
                    </div>
                    <div v-else>

                        <!-- // FREE SHIPPING MESSAGE - not needed anymore-->
                        <!--                        <div-->
                        <!--                                v-if="!loading && errors.products === false && shippingIsSetAndNotFreeOrNoShipping && (checkoutData.orderMinimum > 0 && itemTotal > checkoutData.orderMinimum && itemTotal < 80)"-->
                        <!--                                class="px-1000"-->
                        <!--                        >-->
                        <!--                            <div class="mb-2000 px-1000 py-2000 bg-navy border-px border-navy text-center text-white">-->
                        <!--                                <h2 class="m-0 mb-0500 font-sans text-1125 leading-1875 text-white">Order {{ (80 - itemTotal)| toCurrency }} more to get free delivery</h2>-->
                        <!--                                <a-->
                        <!--                                        href=/collections-->
                        <!--                                        class="inline-block px-2000 py-0500 bg-gold hover:bg-transparent border-px border-gold rounded-0125 text-center uppercase text-1000 text-gold-dark hover:text-gold"-->
                        <!--                                        v-text="`Keep shopping`"-->
                        <!--                                />-->
                        <!--                            </div>-->
                        <!--                        </div>-->

                        <!-- // PAYMENT ERROR MESSAGE -->
                        <div
                                v-if="payment_error"
                                class="px-1000"
                        >
                            <div class="mb-2000 px-1000 py-2000 bg-red-light border-px border-red text-center text-red">
                                <h2 class="m-0 mb-0500 font-sans text-1125 leading-1875 text-red">Sorry, something went wrong</h2>
                                <div v-text="payment_error"/>
                            </div>
                        </div>

                        <div class="lg:flex lg:flex-row-reverse">
                            <div class="lg:w-6/12 px-1000 pb-2000">
                                <OrderSummary
                                        :items="items"
                                        :itemTotal="itemTotal"
                                        @removeProduct="removeProduct"
                                        @setItemQuantity="(vals) => setItemQuantity(vals)"

                                        :coupons="coupons"
                                        :couponCode="couponCode"
                                        :couponError="couponError"
                                        :couponTotal="couponTotal"
                                        @removeCoupon="val => removeCoupon(val)"
                                        @changeCouponCode="(newVal) => couponCode = newVal"
                                        @applyCouponCode="applyCouponCode"


                                        :giftCards="giftCards"
                                        :giftCardTotal="giftCardTotal"
                                        @removeGiftCard="val => removeGiftCard(val)"

                                        :shippingData="shippingData"
                                        :shippingTotal="shippingTotal"
                                        @changeShipping="changeShipping"

                                        :activeStep="activeStep"

                                        :paid="paid"
                                        :total="total"
                                        :pending="pending"
                                />
                            </div>
                            <div class="lg:w-6/12 px-1000">
                                <div v-if="errors.products !== false" class="mb-1000">
                                    <div class="px-1000 py-2000 bg-navy border-px border-navy text-center">
                                        <h2 class="m-0 mb-0500 font-sans text-1125 leading-1875 text-white" v-text="`Please review your basket`"/>
                                        <p class="m-0 mb-0500 font-sans text-1000 leading-1500 text-white" v-text="errors.products"/>
                                    </div>
                                </div>
                                <div v-if="underMinimumOrder && errors.products === false" class="mb-1000">
                                    <div class="px-1000 py-2000 bg-navy border-px border-navy text-center">
                                        <h2 class="m-0 mb-0500 font-sans text-1125 leading-1875 text-white">Minimum Order is £40</h2>
                                        <a
                                                href=/collections
                                                class="inline-block px-2000 py-0500 bg-gold hover:bg-transparent border-px border-gold rounded-0125 text-center uppercase text-1000 text-white"
                                                v-text="`Keep shopping`"
                                        />
                                    </div>
                                </div>
                                <div v-if="errors.products === false && !underMinimumOrder">
                                    <form id="checkoutForm" method="post" :action="pending > 0 ? checkoutData.checkoutUrl : checkoutData.zeroCheckoutUrl" enctype="multipart/form-data" @submit="apiLoading = true">
                                        <input type="hidden" name="billing_country" id="billing_country" value="GB">
                                        <input type="hidden" name="shipping_country" id="shipping_country" value="GB">

                                        <div class="mb-1000">
                                            <AccordionGroup
                                                    :step="0"
                                                    :open="activeStep === 0"
                                                    :stepProgress="stepProgress"
                                                    @clicked="openStep(0)"
                                            >
                                                <template slot="header">
                                                    <div class="flex px-1000">
                                                        <h2
                                                                class="self-center my-auto mx-0 font-brand text-0750 md:text-1000 lg:text-1125 leading-1875"
                                                                :class="[ stepProgress >= 0 ? 'text-navy' : 'text-grey-400' ]"
                                                                v-text="`Your details`"
                                                        />
                                                        <span
                                                                class="self-center block my-auto ml-auto w-2000 h-2000 rounded-full border-px text-1125 leading-1500 text-center"
                                                                :class="[
                                                                    { 'border-grey-400 text-grey-400' : stepProgress < 0 },
                                                                    { 'border-navy text-navy' : stepProgress === 0 },
                                                                    { 'border-navy bg-navy text-white' : stepProgress > 0 }
                                                                ]"
                                                                v-text="`1`"
                                                        />
                                                    </div>
                                                </template>
                                                <template slot="body">
                                                    <div>
                                                        <div class="md:flex px-0500 pt-0500">
                                                            <div class="md:w-6/12 px-0500 pb-1000">
                                                                <InputText
                                                                        v-model="customer.firstName"
                                                                        label="First name*"
                                                                        name="billing_first_name"
                                                                        autocomplete="given-name"
                                                                        :required="true"
                                                                        :error="errors.firstName"
                                                                        :showError="showStepOneValidation"
                                                                />
                                                            </div>
                                                            <div class="md:w-6/12 px-0500 pb-1000">
                                                                <InputText
                                                                        v-model="customer.lastName"
                                                                        label="Last name*"
                                                                        name="billing_last_name"
                                                                        autocomplete="family-name"
                                                                        :required="true"
                                                                        :error="errors.lastName"
                                                                        :showError="showStepOneValidation"
                                                                />
                                                            </div>
                                                        </div>
                                                        <div class="md:flex px-0500">
                                                            <div class="md:w-6/12 px-0500 pb-1000">
                                                                <InputText
                                                                        v-model="customer.email"
                                                                        label="Email*"
                                                                        name="billing_email"
                                                                        autocomplete="email username"
                                                                        :required="true"
                                                                        :error="errors.email"
                                                                        :showError="showStepOneValidation"
                                                                />
                                                            </div>
                                                            <div class="md:w-6/12 px-0500 pb-1000">
                                                                <InputText
                                                                        v-model="customer.phone"
                                                                        label="Phone*"
                                                                        name="billing_phone"
                                                                        autocomplete="tel"
                                                                        :required="true"
                                                                        :error="errors.phone"
                                                                        :showError="showStepOneValidation"
                                                                />
                                                            </div>
                                                        </div>
                                                        <div class="px-1000">
                                                            <div class="pb-1000">
                                                                <InputText
                                                                        v-model="billingAddress.address1"
                                                                        label="Address*"
                                                                        name="billing_address_1"
                                                                        autocomplete="address-line1"
                                                                        :required="true"
                                                                        :error="errors.billingAddress1"
                                                                        :showError="showStepOneValidation"
                                                                />
                                                            </div>
                                                            <div class="pb-1000">
                                                                <InputText
                                                                        v-model="billingAddress.address2"
                                                                        label="Locality"
                                                                        name="billing_address_2"
                                                                        autocomplete="address-line2"
                                                                />
                                                            </div>
                                                            <div class="pb-1000">
                                                                <InputText
                                                                        v-model="billingAddress.city"
                                                                        label="Town / City*"
                                                                        name="billing_city"
                                                                        autocomplete="address-level2"
                                                                        :required="true"
                                                                        :error="errors.billingCity"
                                                                        :showError="showStepOneValidation"
                                                                />
                                                            </div>
                                                            <div class="pb-1000">
                                                                <InputText
                                                                        v-model="billingAddress.state"
                                                                        label="County"
                                                                        name="billing_state"
                                                                        autocomplete="address-level1"
                                                                />
                                                            </div>
                                                            <div class="pb-1000">
                                                                <InputText
                                                                        v-model="billingAddress.postcode"
                                                                        label="Postcode*"
                                                                        name="billing_postcode"
                                                                        autocomplete="postal-code"
                                                                        :required="true"
                                                                        :error="errors.billingPostcode"
                                                                        :showError="showStepOneValidation"
                                                                />
                                                            </div>
                                                        </div>
                                                        <div class="px-1000 pb-1000">
                                                            <label class="block p-0500 border-px border-gold text-1000">
                                                                <input
                                                                        id="ship-to-different-address-checkbox"
                                                                        v-model="deliverToDifferentAddress"
                                                                        type="checkbox"
                                                                        name="ship_to_different_address"
                                                                        value="1"
                                                                >
                                                                <span>Deliver to a different address?</span>
                                                            </label>
                                                        </div>
                                                        <div v-if="deliverToDifferentAddress">
                                                            <div class="px-1000 pt-1000">
                                                                <p class="text-1125" v-text="`Shipping address:`"/>
                                                            </div>
                                                            <div class="md:flex px-0500">
                                                                <div class="md:w-6/12 px-0500 pb-1000">
                                                                    <InputText
                                                                            v-model="shippingAddress.firstName"
                                                                            label="First name*"
                                                                            name="shipping_first_name"
                                                                            autocomplete="given-name"
                                                                            :required="deliverToDifferentAddress === 1"
                                                                            :error="errors.shippingFirstName"
                                                                            :showError="showStepOneValidation"
                                                                    />
                                                                </div>
                                                                <div class="md:w-6/12 px-0500 pb-1000">
                                                                    <InputText
                                                                            v-model="shippingAddress.lastName"
                                                                            label="Last name*"
                                                                            name="shipping_last_name"
                                                                            autocomplete="family-name"
                                                                            :required="deliverToDifferentAddress === 1"
                                                                            :error="errors.shippingLastName"
                                                                            :showError="showStepOneValidation"
                                                                    />
                                                                </div>
                                                            </div>
                                                            <div class="px-1000">
                                                                <div class="pb-1000">
                                                                    <InputText
                                                                            v-model="shippingAddress.phone"
                                                                            label="Phone*"
                                                                            name="shipping_phone"
                                                                            autocomplete="tel"
                                                                            :required="true"
                                                                            :error="errors.shippingPhone"
                                                                            :showError="showStepOneValidation"
                                                                    />
                                                                </div>

                                                                <div class="pb-1000">
                                                                    <InputText
                                                                            v-model="shippingAddress.address1"
                                                                            label="Address*"
                                                                            name="shipping_address_1"
                                                                            autocomplete="address-line1"
                                                                            :required="deliverToDifferentAddress === 1"
                                                                            :error="errors.shippingAddress1"
                                                                            :showError="showStepOneValidation"
                                                                    />
                                                                </div>
                                                                <div class="pb-1000">
                                                                    <InputText
                                                                            v-model="shippingAddress.address2"
                                                                            label="Locality (optional)"
                                                                            name="shipping_address_2"
                                                                            autocomplete="address-line2"
                                                                    />
                                                                </div>
                                                                <div class="pb-1000">
                                                                    <InputText
                                                                            v-model="shippingAddress.city"
                                                                            label="Town / City*"
                                                                            name="shipping_city"
                                                                            autocomplete="address-level2"
                                                                            :required="deliverToDifferentAddress === 1"
                                                                            :error="errors.shippingCity"
                                                                            :showError="showStepOneValidation"
                                                                    />
                                                                </div>
                                                                <div class="pb-1000">
                                                                    <InputText
                                                                            v-model="shippingAddress.state"
                                                                            label="County"
                                                                            name="shipping_state"
                                                                            autocomplete="address-level1"
                                                                    />
                                                                </div>
                                                                <div class="pb-2000">
                                                                    <InputText
                                                                            v-model="shippingAddress.postcode"
                                                                            label="Postcode*"
                                                                            name="shipping_postcode"
                                                                            autocomplete="postal-code"
                                                                            :required="deliverToDifferentAddress === 1"
                                                                            :error="errors.shippingPostcode"
                                                                            :showError="showStepOneValidation"
                                                                    />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="px-1000">
                                                            <div v-if="detailsStepErrors || detailsStepNotFinished" class="text-1000 text-right">
                                                                <p
                                                                        v-if="detailsStepNotFinished"
                                                                        class="text-grey-400"
                                                                        v-text="`Please complete all required (*) fields to proceed`"
                                                                />
                                                                <p
                                                                        v-else
                                                                        class="text-red"
                                                                        v-text="`There are errors, please correct and try again.`"
                                                                />
                                                            </div>
                                                            <div v-if="dateError!=``" class="flex">
                                                                <div class="mb-2000 px-1000 py-2000 bg-red-light border-px border-red text-center text-red">
                                                                    <div v-text="dateError"/>
                                                                </div>
                                                            </div>

                                                            <div class="flex">
                                                                <ButtonElement
                                                                        text="Next"
                                                                        utils="ml-auto"
                                                                        @clicked="checkStepOne"
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </AccordionGroup>
                                        </div>
                                        <div class="mb-1000">
                                            <AccordionGroup
                                                    :step="1"
                                                    :open="activeStep === 1"
                                                    :stepProgress="stepProgress"
                                                    @clicked="openStep(1)"
                                            >
                                                <template slot="header">
                                                    <div class="flex px-1000">
                                                        <h2
                                                                class="self-center my-auto mx-0 font-brand text-0750 md:text-1000 lg:text-1125 leading-1875"
                                                                :class="[ stepProgress >= 1 ? 'text-navy' : 'text-grey-400' ]"
                                                                v-text="`Delivery Date`"
                                                        />
                                                        <span
                                                                class="self-center block my-auto ml-auto w-2000 h-2000 rounded-full border-px text-1125 leading-1500 text-center"
                                                                :class="[
                                { 'border-grey-400 text-grey-400' : stepProgress < 1 },
                                { 'border-navy text-navy' : stepProgress === 1 },
                                { 'border-navy bg-navy text-white' : stepProgress > 1 }
                              ]"
                                                                v-text="`2`"
                                                        />
                                                    </div>
                                                </template>
                                                <template slot="body">
                                                    <div>
                                                        <div>
                                                            <div class="px-1000 pb-1000">
                                                                <div>
                                                                    <div>
                                                                        <p class="text-1125 text-grey-400">
                                                                            Select a delivery date<span v-if="postcodeToUse"> for {{ postcodeToUse }}</span>:
                                                                        </p>
                                                                    </div>
                                                                    <div v-if="shippingComments !== ''">
                                                                        <p
                                                                                class="text-red text-1000"
                                                                                v-text="shippingComments"
                                                                        />
                                                                    </div>
                                                                </div>
                                                                <div
                                                                        v-if="canSelectShippingDate"
                                                                        class="shipping-date-picker"
                                                                >
                                                                    <datepicker
                                                                            id="delivery_date"
                                                                            :key="datePickerComponentKey"
                                                                            :value="deliveryDateMoment"
                                                                            @input="dateChanged"
                                                                            :disabledDates="dateDisabledDays"
                                                                            :inline="true"
                                                                            placeholder="Date"
                                                                    />
                                                                </div>
                                                                <!-- TODO: show something better here -->
                                                                <div v-else>
                                                                    <p class="text-1000 text-red" v-html="reasonWhyCantSelectShippingData"/>
                                                                </div>
                                                            </div>
                                                            <div
                                                                    v-if="errors.shippingPostcode"
                                                                    class="px-1000 pb-1000"
                                                            >
                                                                <div v-html="errors.shippingPostcode"/>
                                                            </div>
                                                            <div
                                                                    v-if="canShowShippingChoices"
                                                                    class="px-1000 pb-1000"
                                                            >
                                                                <div
                                                                        v-for="price in selectedDate.prices"
                                                                        :key="price.code"
                                                                        class="pb-1000"
                                                                >
                                                                    <label class="flex p-0500 border-px border-gold text-1000">
                                                                        <div>
                                                                            <input
                                                                                    type="radio"
                                                                                    name="shipping"
                                                                                    :checked="shippingData.code === price.code"
                                                                                    @change.prevent="setShippingOption(price.code)"
                                                                            >
                                                                            <span>{{ price.name }}</span>
                                                                        </div>
                                                                        <div class="ml-auto">
                                                                            <span>{{ parseFloat(price.price) | toCurrency }}</span>
                                                                        </div>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div
                                                                    v-if="canShowShippingChoices"
                                                                    class="px-1000 pb-1000"
                                                            >
                                                                <div class="pt-1000">
                                                                    <p class="text-1125" v-text="`Delivery options:`"/>
                                                                </div>
                                                                <div class="mb-1000 border-px border-gold">
                                                                    <label class="block p-0500 text-1000">
                                                                        <div>
                                                                            <input
                                                                                    type="checkbox"
                                                                                    v-model="thisOrderHasAShippingNote"
                                                                                    value="1"
                                                                            >
                                                                            <span>Leave a delivery note</span>
                                                                        </div>
                                                                    </label>
                                                                    <div
                                                                            v-if="thisOrderHasAShippingNote"
                                                                            class="p-0500 text-1000"
                                                                    >
                                      <textarea
                                              v-model="shippingData.delivery_note"
                                              name="shipping_note"
                                              rows="2"
                                              class="appearance-none block p-0500 rounded-0 border-px border-grey-200"
                                              :required="thisOrderHasAShippingNote"
                                              placeholder="Please type a delivery note..."
                                              @blur="setShippingData"
                                      />
                                                                    </div>
                                                                </div>
                                                                <div class="border-px border-gold">
                                                                    <label class="block p-0500 text-1000">
                                                                        <div>
                                                                            <input
                                                                                    type="checkbox"
                                                                                    v-model="thisOrderIsAGift"
                                                                                    value="1"
                                                                            >
                                                                            <span>This order is a gift</span>
                                                                        </div>
                                                                    </label>
                                                                    <div
                                                                            v-if="thisOrderIsAGift"
                                                                            class="p-0500 text-1000"
                                                                    >
                                      <textarea
                                              v-model="shippingData.gift_message"
                                              name="gift_message"
                                              rows="2"
                                              class="appearance-none block p-0500 rounded-0 border-px border-grey-200"
                                              :required="thisOrderIsAGift"
                                              placeholder="Type a message for the recipient..."
                                              @blur="setShippingData"
                                      />
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="flex pt-1000 px-1000">
                                                                <ButtonElement
                                                                        text="Previous"
                                                                        theme="secondary"
                                                                        utils="mr-auto"
                                                                        @clicked="previousStep"
                                                                />
                                                                <ButtonElement
                                                                        text="Next"
                                                                        utils="ml-auto"
                                                                        :disabled="shippingStepNotValid"
                                                                        @clicked="nextStep"
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </AccordionGroup>
                                        </div>
                                        <div class="mb-1000">
                                            <AccordionGroup
                                                    :step="2"
                                                    :open="activeStep === 2"
                                                    :stepProgress="stepProgress"
                                                    @clicked="openStep(2)"
                                            >
                                                <template slot="header">
                                                    <div class="flex px-1000">
                                                        <h2
                                                                class="self-center my-auto mx-0 font-brand text-0750 md:text-1000 lg:text-1125 leading-1875"
                                                                :class="[ stepProgress >= 2 ? 'text-navy' : 'text-grey-400' ]"
                                                                v-text="`Complete Order`"
                                                        />
                                                        <span
                                                                class="self-center block my-auto ml-auto w-2000 h-2000 rounded-full border-px text-1125 leading-1500 text-center"
                                                                :class="[
                                                                    { 'border-grey-400 text-grey-400' : stepProgress < 2 },
                                                                    { 'border-navy text-navy' : stepProgress === 2 },
                                                                    { 'border-navy bg-navy text-white' : stepProgress > 2 }
                                                                ]"
                                                                v-text="`3`"
                                                        />
                                                    </div>
                                                </template>
                                                <template slot="body">
                                                    <div class="px-1000 pt-0500">
                                                        <!-- <div> -->
                                                        <div v-if="shippingData.code !== `` && canShowShippingChoices">
                                                            <div v-if="pending > 0">
                                                                <CreditCardInput
                                                                        :cardNumber="cardNumber"
                                                                        @updateNumber="val => cardNumber = val"
                                                                        :cardExpiryDate="cardExpiryDate"
                                                                        @updateExpiryDate="val => cardExpiryDate = val"
                                                                        :cardCvcCode="cardCvcCode"
                                                                        @updateCvc="val => cardCvcCode = val"
                                                                        @updateCardType="val => cardType = val"
                                                                />
                                                            </div>
                                                            <div class="text-1000">
                                                                <slot
                                                                        v-if="pending > 0"
                                                                        :cardType="cardType"
                                                                        :cardNumber="cardNumber"
                                                                        :cardExpiryDate="cardExpiryDate"
                                                                        :cardCvcCode="cardCvcCode"
                                                                        name="payment"
                                                                />
                                                            </div>
                                                            <div>
                                                                <label v-bind:style="{border: termsBorderColor}" class="block mt-1000 mb-1000 p-0500 border-px border-gold text-1000">
                                                                    <input
                                                                            type="checkbox"
                                                                            name="terms"
                                                                            required
                                                                            v-model="terms"
                                                                    >
                                                                    <span>Tick to agree to the <a href="/terms">Côte at Home terms and conditions</a>*</span>
                                                                </label>
                                                                <p v-if="termsError==true" class ="text-1000 text-red">Please accept our terms and conditions to continue.</p>
                                                            </div>
                                                            <div>
                                                                <input type="hidden" name="terms-field" value="1"/>
                                                            </div>
                                                            <div>
                                                                <label class="block mb-1000 p-0500 border-px border-gold text-1000">
                                                                    <input
                                                                            type="checkbox"
                                                                            name="marketing"
                                                                            v-model="customer.acceptsMarketing"
                                                                            @change="saveCustomerData"
                                                                    >
                                                                    <span>Tick to register and hear about the latest news and offers from Côte at Home</span>
                                                                </label>
                                                            </div>
                                                            <!-- <div
                                                                v-if="pending > 0"
                                                                class="flex gap-0500"
                                                            >
                                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-visa.png"/>
                                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-mastercard.png"/>
                                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-maestro.png"/>
                                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-visa-electron.png"/>
                                                                <img src="/wp-content/plugins/woocommerce-gateway-sagepay-form/assets/images/card-american-express.png"/>
                                                            </div> -->
                                                        </div>
                                                        <div class="flex pt-1000">
                                                            <ButtonElement
                                                                    text="Previous"
                                                                    theme="secondary"
                                                                    utils="mr-auto"
                                                                    @clicked="previousStep"
                                                            />
                                                            <ButtonElement
                                                                    text="Place order"
                                                                    utils="ml-auto"
                                                                    :disabled="!canBeSubmitted"
                                                                    @clicked="submitButton"
                                                            />
                                                        </div>
                                                    </div>
                                                </template>
                                            </AccordionGroup>
                                        </div>
                                        <slot name="nonce"/>
                                        <input type="hidden" name="woocommerce_pay" value="1"/>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>

<script>

import moment from 'moment-timezone'
import Datepicker from 'vuejs-datepicker'
import axios from 'axios'
import creditCardType from 'credit-card-type'

import InputText from './InputText'
import LoadingWheel from './LoadingWheel'
import OrderSummary from './OrderSummary'
import ButtonElement from './ButtonElement'
import AccordionGroup from './AccordionGroup'
import CreditCardInput from './CreditCardInput'

export default {
    components: {
        InputText,
        Datepicker,
        LoadingWheel,
        OrderSummary,
        ButtonElement,
        AccordionGroup,
        CreditCardInput
    },

    props: {
        order_id: {
            type: String,
            required: true

        },
        payment_error: {
            type: String,
            default: false

        },
        urls: {
            type: Object,
            required: true
        },
        status: {
            type: String,
            default: ``
        }
    },

    data() {
        return {
            activeStep: 0,
            stepProgress: 0,
            showStepOneValidation: false,
            apiLoading: false,
            cardType: ``,
            cardNumber: ``,
            cardCvcCode: ``,
            cardExpiryDate: ``,
            shippingComments: ``,

            timeZone: 'Europe/London',

            loaded: false,
            thisOrderIsAGift: false,
            thisOrderHasAShippingNote: false,

            paid: 0,
            total: 0,
            pending: 0,

            /**
             * Line Items & Data
             */
            items: [],
            coupons: [],
            shippingData: {
                date: '',
                delivery_company_id: 0,
                delivery_area_id: 0,
                postcode: '',
                amount: 0,
                name: '',
                code: '',
                delivery_note: '',
                gift_message: '',
            },

            giftCards: [],

            /**
             * The coupon Error
             */
            couponError: '',
            errors: {},

            /**
             * The coupon Error
             */
            dateError: '',

            /**
             * if the cart has free items, we can't enforce the £40 min
             */
            orderMinimum: 40,

            /**
             *
             *  The customer
             */
            customer: {
                firstName: '',
                lastName: '',
                email: '',
                phone: '',
                acceptsMarketing: false,
            },
            billingAddress: {
                company: '',
                address1: '',
                address2: '',
                city: '',
                state: '',
                postcode: '',
                country: '',
            },

            shippingAddress: {
                firstName: '',
                lastName: '',
                phone: '',
                company: '',
                address1: '',
                address2: '',
                city: '',
                state: '',
                postcode: '',
                country: '',
            },

            /**
             * data returned from the server
             */
            checkoutData: {},

            /**
             * Should we show the shipping Addresses?
             */
            deliverToDifferentAddress: false,


            /**
             * A Handy key to trigger the update of the date dropdown
             */
            datePickerComponentKey: 1,


            /**
             * An object of disabled dates for the date dropdown
             */
            dateDisabledDays: {},

            /**
             * The date selected from the date dropdown
             */
            deliveryDate: '',

            /**
             * Delivery dates
             */
            availableDates: [],

            /**
             * Selected Date
             */
            selectedDate: {
                date: '',
                prices: [],
            },

            /**
             * The select shipping price
             */
            selectedPrice: {},


            /**
             * config options
             */
            dateFormat: 'YYYY-MM-DD',
            loadingDates: false,
            loadingData: false,

            itemTotal: 0,
            couponTotal: 0,
            giftCardTotal: 0,
            shippingTotal: 0,

            couponCode: '',
            terms:null,
            termsError:null,


            /**Dynamic style */

            termsBorderColor:null,

        }
    },

    watch: {
        stepProgress(newVal) {
            this.activeStep = newVal
        },
        deliverToDifferentAddress(val) {
            if (!val) {
                this.shippingAddress = {
                    firstName: '',
                    lastName: '',
                    company: '',
                    address1: '',
                    address2: '',
                    phone: '',
                    city: '',
                    state: '',
                    postcode: '',
                    country: '',
                }
            }
        },

        /**
         * The date has changed... we need to select a shippingCode because even though
         * the date might be the same, the company might be different
         */
        selectedDate() {

            let found = false;
            if (this.shippingData.code) {
                found = this.selectedDate.prices.find(price => price.code === this.shippingData.code && price.name === this.shippingData.name);
            }
            // Choose the first One
            if (!found) {
                this.availableDates.some(availableDate => {
                    if (availableDate.available) {
                        this.shippingData.code = availableDate.prices[0].code;
                        return true;
                    }
                });
            }
            //Fire again
            this.setShippingOption(this.shippingData.code)

        },

    },


    computed: {
        underMinimumOrder() {
            return this.itemTotal < this.orderMinimum
        },

        detailsStepErrors() {
            const errors = this.errors.email !== false || this.errors.phone !== false || this.errors.billingPostcode !== false

            return errors
        },
        detailsStepNotFinished() {
            if (this.showStepOneValidation) {
                const customerFirstName = this.customer.firstName === '' || this.customer.firstName === undefined
                const customerLastName = this.customer.lastName === '' || this.customer.lastName === undefined
                const customerEmail = this.customer.email === '' || this.customer.email === undefined
                const customerPhone = this.customer.phone === '' || this.customer.phone === undefined
                const customerStreet = this.billingAddress.address1 === '' || this.billingAddress.address1 === undefined
                const customerCity = this.billingAddress.city === '' || this.billingAddress.city === undefined
                const customerPostcode = this.billingAddress.postcode === '' || this.billingAddress.postcode === undefined

                const customerUnfinished = customerFirstName || customerLastName || customerEmail || customerPhone || customerStreet || customerCity || customerPostcode

                return customerUnfinished
            } else {
                return true
            }
        },
        shippingStepNotValid() {
            if (this.errors.shippingPostcode !== false || this.dateError !== '') {
                return true
            } else {
                return false
            }
            // billingPostcode:"The postcode is invalid"
            // email:"Please enter a valid email address"
            // phone:"Please enter a valid phone number"
            // products:false
            // shippingPostcode:false
        },
        shippingIsSetAndNotFreeOrNoShipping() {
            return (this.shippingData.code && this.shippingData.amount !== 0) || !this.shippingData.code
        },

        canSelectShippingDate() {
            //return this.shippingData.postcode && !this.errors.shippingPostcode && !this.errors.billingPostcode && (this.cartHasFreeItems || this.itemTotal >= 40);
            return this.postcodeToUse && !this.errors.shippingPostcode && !this.errors.billingPostcode && (this.itemTotal >= this.orderMinimum);
        },

        /**
         * TODO: use this to block the form
         */
        loading() {
            return this.loadingDates || this.loadingData;
        },


        reasonWhyCantSelectShippingData() {


            if (this.itemTotal < this.orderMinimum) {
                return "The minimum order is £40. Please add items to your basket to choose a delivery date";
            }

            if (!this.postcodeToUse) {
                return "Please enter your address to choose a delivery date";
            }

            if (this.errors.billingPostcode) {
                return "Your postcode is invalid";
            }
            if (this.errors.shippingPostcode) {
                return "Your shipping postcode is invalid";
            }
        },

        canShowShippingChoices() {
            return this.canSelectShippingDate && this.selectedDate && this.selectedDate.prices && this.selectedDate.prices.length > 0
        },

        // total() {
        //     return parseFloat(this.itemTotal) + parseFloat(this.couponTotal) + parseFloat(this.shippingTotal) + parseFloat(this.giftCardTotal);
        // },

        subTotal() {
            return parseFloat(this.itemTotal) + parseFloat(this.couponTotal) + parseFloat(this.shippingTotal);
        },

        deliveryDateMoment() {
            if (this.shippingData.date) {
                return moment.tz(this.shippingData.date, this.timeZone).toDate();
            }
            return null;
        },

        canBeSubmitted() {
            return !this.errors.email &&
                !this.errors.phone &&
                !this.errors.billingPostcode &&
                !this.errors.shippingPostcode &&
                !this.errors.products &&
                this.shippingData.code &&
                this.itemTotal >= this.orderMinimum
        },

        postcodeToUse() {
            if (this.shippingAddress.postcode) {
                return this.shippingAddress.postcode;
            }
            if (this.billingAddress.postcode) {
                return this.billingAddress.postcode;
            }
            return false;
        }


    },

    mounted() {
        document.getElementById(`checkout`).style.display = `block`
    },

    created() {

        // Card checking
        let visaCards = creditCardType("4111");
        //console.log(visaCards); // 'visa'

        moment.tz.setDefault(this.timeZone);
        let self = this;
        this.getCheckoutData(function () {
            if (self.checkoutData.customerData.customer) {
                self.customer = self.checkoutData.customerData.customer;
                self.deliverToDifferentAddress = self.checkoutData.customerData.deliverToDifferentAddress !== ''
                self.billingAddress = self.checkoutData.customerData.billingAddress;
                self.shippingAddress = self.checkoutData.customerData.shippingAddress;
            }
            self.loaded = true;
        });

    },

    methods: {

        /**
         * Double check that the date is still available
         */
        submitButton() {
            this.apiLoading = true;
            const self = this;
            axios.post(this.urls.validateOrder, {order_id: this.order_id})
                .then((resp) => {
                    if (this.giftCards.length > 0) {

                        this.giftCards.forEach(function (giftCard) {
                            axios.post(self.urls.getCurrentGiftcardInfo, {cardnumber: giftCard.number, balance: giftCard.balance})
                                .then((resp) => {
                                    document.getElementById('checkoutForm').submit();
                                })
                                .catch(error => {
                                    self.apiLoading = false
                                    self.couponError = error.response.data.message;
                                    self.loadingData = false;
                                    self.loadingDates = false;
                                    self.stepProgress = 0;
                                    self.resetShippingData()
                                });
                        });

                    }else{
                        document.getElementById('checkoutForm').submit();
                    }



                }).catch(error => {
                this.apiLoading = false
                this.dateError = error.response.data.message;
                this.loadingData = false;
                this.loadingDates = false;
                this.stepProgress = 0;
                this.resetShippingData()
            });
        },


        openStep(step) {
            if (this.stepProgress >= step) {
                this.activeStep = step
            }
        },
        nextStep() {
            this.activeStep += 1
            if (this.stepProgress < this.activeStep) {
                this.stepProgress = this.activeStep
            }
        },
        checkStepOne() {


            this.saveCustomerData((errors) => {
                // console.log(errors)

                for (const error in errors) {
                    if (errors[error] !== false) {
                        this.showStepOneValidation = true
                        return
                    }
                }
                let self = this
                this.getAvailableDeliveryDates(() => self.nextStep())

                this.couponCode = '';
                this.couponError = '';
            })
        },

        changeShipping() {
            if (this.activeStep === 0) {
                this.checkStepOne()
            } else {
                this.previousStep()
            }
        },

        saveCustomerData(callback) {

            this.apiLoading = true
            let data = {
                customer: this.customer,
                billingAddress: this.billingAddress,
                shippingAddress: this.shippingAddress,
                deliverToDifferentAddress: this.deliverToDifferentAddress,
            }

            axios.post(this.urls.saveCustomerData, {order_id: this.order_id, data: data}).then((resp) => {
                this.processCheckoutData(resp.data);

                this.apiLoading = false
                if (callback) {
                    callback(resp.data.errors)
                }
            });
        },
        previousStep() {
            this.activeStep -= 1
        },
        removeProduct(item) {
            this.apiLoading = true
            this.couponCode = '';
            this.couponError = '';

            axios.post(this.urls.removeProduct, {
                order_id: this.order_id,
                product_id: item.product_id,
                variation_id: item.variation_id,

            }).then(resp => {
                this.apiLoading = false
                this.processCheckoutData(resp.data)
                this.getAvailableDeliveryDates();
            });
        },


        setItemQuantity(vals) {
            // int $order_id, int $product_id, int $quantity, int $variation_id
            this.apiLoading = true
            this.couponCode = '';
            this.couponError = '';

            axios.post(this.urls.updateProductQuantity, {
                order_id: this.order_id,
                product_id: vals.item.product_id,
                quantity: vals.newVal,
                variation_id: vals.item.variation_id
            }).then(resp => {
                this.apiLoading = false
                this.processCheckoutData(resp.data)
                this.getAvailableDeliveryDates();
            });
        },


        removeGiftCard(cardNumber) {
            this.apiLoading = true
            this.couponCode = '';
            this.couponError = '';

            axios.post(this.urls.removeGiftCard, {
                order_id: this.order_id,
                cardNumber: cardNumber
            }).then(resp => {
                this.apiLoading = false
                this.processCheckoutData(resp.data)
            });
        },

        removeCoupon(code) {
            this.apiLoading = true
            this.couponCode = '';
            this.couponError = '';

            axios.post(this.urls.removeCoupon, {
                order_id: this.order_id,
                code: code
            })
                .then(resp => {
                    this.apiLoading = false
                    this.processCheckoutData(resp.data)

                    this.getAvailableDeliveryDates()
                });
        },


        /**
         * Shipping has changed - tell the server
         */
        setShippingOption(code = null) {

            if (code !== null) {
                let selectedPrice = this.selectedDate.prices.find(price => price.code === code);
                this.shippingData.amount = selectedPrice.price;
                this.shippingData.name = selectedPrice.name;
                this.shippingData.code = code;
            }

            if (this.shippingData.code) {
                this.shippingData.postcode = this.postcodeToUse;
                this.setShippingData()
            }

        },

        setShippingData() {
            this.apiLoading = true
            let data = {
                ...this.shippingData
            }
            // make sure we send the encrypted version
            data.order_id = this.order_id

            axios.post(this.urls.setShippingData, data).then(resp => {
                this.apiLoading = false
                this.processCheckoutData(resp.data)
            });
        },

        /**
         * A new date was selected from the date picker or when we've made a call to getDates
         *
         * The options in the date picker are run from this.availableDates,
         * so the option should be present.
         *
         * This changes the selectedDate property - which will trigger a rendering of the
         * new options available.
         *
         * @param newDate
         */
        dateChanged(newDate) {

            let dateSelected = moment.tz(newDate, this.timeZone).format(this.dateFormat);

            this.availableDates.forEach(availableDate => {

                if (availableDate.date === dateSelected && availableDate.available) {
                    this.$set(this.shippingData, 'date', availableDate.date);
                    this.$set(this.shippingData, 'delivery_company_id', availableDate.delivery_company_id);
                    this.$set(this.shippingData, 'delivery_area_id', availableDate.delivery_area_id);
                    this.selectedDate = availableDate;
                }
            });
        },

        resetShippingData() {
            this.shippingData = {
                date: '',
                delivery_company_id: 0,
                delivery_area_id: 0,
                postcode: '',
                amount: 0,
                name: '',
                code: '',
                delivery_note: '',
                gift_message: '',
            };
        },


        /**
         * Make an api call and find out what dates are available!
         */
        getAvailableDeliveryDates(callback) {

            if (this.loadingDates) {
                return;
            }

            if (this.errors.billingPostcode || this.errors.shippingPostcode || !this.postcodeToUse) {
                console.log('errors! cant get dates')
                return;
            }

            if (this.itemTotal < this.orderMinimum) {
                return;
            }

            this.dateError = '';
            this.apiLoading = true
            this.loadingDates = true;
            this.shippingComments = ``

            // Ummm.....
            //this.resetShippingData();

            axios.post(this.urls.getDates, {
                order_id: this.order_id,
                postcode: this.postcodeToUse
            }).then((resp) => {

                // Do some tracking
                window.processTracking();

                this.shippingComments = resp.data.comments
                // Set the available dates
                this.availableDates = resp.data.dates;

                // calculate disabled Dates.
                this.dateDisabledDays.dates = [];
                resp.data.disabledDates.forEach((date) => {
                    let jsDate = moment(date, this.dateFormat).tz(this.timeZone).toDate();

                    this.dateDisabledDays.dates.push(jsDate)
                })
                // calculate to and from
                this.dateDisabledDays.to = moment(resp.data.startDate, this.dateFormat).tz(this.timeZone).toDate()
                this.dateDisabledDays.from = moment(resp.data.endDate, this.dateFormat).tz(this.timeZone).toDate()

                // grab the first available date .. we might need this!
                let firstOptionDate = moment(resp.data.firstAvailableDate, this.dateFormat).tz(this.timeZone).toDate();

                // so we don't have a shipping date yet?
                if (!this.shippingData.date) {
                    // Set it to the first one
                    this.dateChanged(firstOptionDate);
                } else {

                    // We have a shipping date...  let's check it's still valid.
                    const found = this.availableDates.find(date => date.date === this.shippingData.date && date.available);
                    if (!found) {
                        this.dateChanged(firstOptionDate);
                    } else {
                        // Fire the event anyway as the delivery_company_id may have have changed.
                        let originalDate = moment(this.shippingData.date, this.dateFormat).tz(this.timeZone).toDate();
                        this.dateChanged(originalDate);
                    }
                }

                //force a re-render
                this.datePickerComponentKey += 1;
                this.loadingDates = false;
                this.apiLoading = false

                if (callback) {
                    callback()
                }
            }).catch(error => {
                this.apiLoading = false
                this.dateError = error.response.data.message;
                this.loadingData = false;
                this.loadingDates = false;
                this.stepProgress = 0;
                this.resetShippingData()
            });


        },


        getCheckoutData(callback) {
            this.apiLoading = true

            if (this.loadingData) {
                return;
            }
            this.loadingData = true;


            axios.post(this.urls.getCheckoutData, {order_id: this.order_id})
                .then((resp) => {
                    this.apiLoading = false
                    this.processCheckoutData(resp.data);
                    this.loadingData = false;
                    if (callback) {
                        callback();
                    }


                });
        },

        /**
         * Parse data that comes back from the server
         *
         * @param data
         */
        processCheckoutData(data) {

            // do some tracking
            window.processTracking();


            this.checkoutData = data;

            this.items = data.items;
            this.coupons = data.coupons;
            this.shippingData = data.shippingData;
            this.giftCards = data.giftCards;

            this.itemTotal = 0;
            if (this.items.length > 0) {
                this.items.forEach(item => this.itemTotal += parseFloat(item.total))
            }

            this.couponTotal = 0;
            if (this.coupons.length > 0) {
                this.coupons.forEach(coupons => this.couponTotal += parseFloat(coupons.amount))
            }

            this.giftCardTotal = 0;
            if (this.giftCards.length > 0) {
                this.giftCards.forEach(giftCard => this.giftCardTotal += parseFloat(giftCard.amount))
            }

            this.shippingTotal = 0;
            if (this.shippingData.amount) {
                this.shippingTotal = parseFloat(this.shippingData.amount);
            }

            this.deliverToDifferentAddress = data.customerData.deliverToDifferentAddress;

            this.errors = data.errors
            this.orderMinimum = parseFloat(data.orderMinimum);
            this.thisOrderIsAGift = this.shippingData.gift_message !== '';
            this.thisOrderHasAShippingNote = this.shippingData.delivery_note !== '';

            this.paid = data.paid
            this.total = data.total
            this.pending = data.pending

        },


        applyCouponCode() {
            if (this.couponCode) {
                this.apiLoading = true

                if (this.loadingData) {
                    return;
                }
                this.loadingData = true;

                axios.post(this.urls.addCouponCode, {order_id: this.order_id, code: this.couponCode, email: this.customer.email})
                    .then((resp) => {

                        this.apiLoading = false
                        this.getAvailableDeliveryDates()
                        this.couponCode = '';
                        this.couponError = '';
                        this.processCheckoutData(resp.data);
                        this.loadingData = false;
                    })
                    .catch(error => {
                        this.apiLoading = false
                        this.couponError = error.response.data.message;
                        this.loadingData = false;
                    });
            }

        },

    }
}
</script>


<style>
.shipping-date-picker {

.vdp-datepicker__calendar {
    @apply w-full rounded-0 border-px border-gold overflow-hidden text-1000;

&
:hover {
    @apply border-grey-400;
}

.cell {
    @apply rounded-0 border-px;
}

.cell.selected,
.cell.selected:hover {
    @apply bg-gold text-white;
}

.cell:not(.blank):not(.disabled).day:hover,
.cell:not(.blank):not(.disabled).month:hover,
cell:not(.blank):not(.disabled).year:hover {
    @apply border-px border-gold;
}

}
}
</style>

