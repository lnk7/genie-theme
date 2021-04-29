<template>
    <div id="cahFrontend">
        <div class="font-sans not-italic md:max-w-6/12 mx-auto">
            <div v-if="already_submitted">
                <div class="mb-1500 px-1000 py-2000 bg-navy border-px border-navy text-center text-white">
                    <h2 class="m-0 mb-0500 font-brand text-1125 leading-1875 text-white">Review already received!</h2>
                    <p class="text-1000 text-white">Thank you, however a review has already been submitted for this order. </p>
                </div>
                <p class="font-serif text-1125"> </p>
            </div>
            <div v-else-if="!sent">
                <form action="#" method="post" @submit.prevent="validate">
                    <div class="mb-1500">
                        <p class="font-serif text-1125">Rate your overall experience</p>
                        <InputStar :stars="starsValue" :start="overall" @change="overall = $event; overallError = false"/>

                        <div v-if="overallError" class="text-red text-1000 pt-0500">
                            Please select your rating
                        </div>

                    </div>

                    <div class="mb-1500">
                        <textarea v-model="message" rows="5" class="appearance-none block p-0500 rounded-0 border-px border-grey-200" required placeholder="Share your thoughts...*" @focus="messageError = false"/>
                        <div class="text-right text-1000">
                            <span class="font-serif" :class="[ messageWords >= messageWordsMin ? 'text-gold' : 'text-red' ]" v-text="`${messageWords} words entered.`"/>
                            <span class="font-serif" v-text="`${messageWordsMin} words minimum.`"/>
                        </div>
                    </div>

                    <div class="mb-1500">
                        <p class="font-serif text-1125">Food</p>
                        <InputStar :stars="starsValue" :start="food" @change="food = $event"/>
                    </div>

                    <div class="mb-1500">
                        <p class="font-serif  text-1125">Value for money</p>
                        <InputStar :stars="starsValue" :start="value" @change="value = $event"/>
                    </div>

                    <div class="mb-1500">
                        <p class="font-serif text-1125">Delivery</p>
                        <InputStar :stars="starsValue" :start="delivery" @change="delivery = $event"/>
                    </div>

                    <div v-if="!order_id">

                        <p>Your Details</p>

                        <div class="mb-1500">
                            <InputSelect v-model="title" label="Title*" :required="true">
                                <option value="">Please choose</option>
                                <option v-for="title in titles" :value="title">{{ title }}</option>
                            </InputSelect>
                        </div>

                        <div class="mb-1500">
                            <InputText v-model="first_name" label="First name*" :required="true"/>
                        </div>

                        <div class="mb-1500">
                            <InputText v-model="last_name" label="Last name*" :required="true"/>
                        </div>

                        <div class="mb-1500">
                            <InputText v-model="email" label="Email*" :required="true" @focus="emailError = false"/>
                            <div v-if="emailError" class="text-red text-1000 pt-0500">
                                Please enter a valid email address
                            </div>
                        </div>

                        <div class="mb-1500">
                            <InputText v-model="phone" label="Phone" @focus="phoneError = false"/>
                            <div v-if="phoneError" class="text-red text-1000 pt-0500">
                                Please enter a valid phone number
                            </div>
                        </div>
                    </div>
                    <div class="mt-2000">
                        <ButtonElement text="Send review" utils="ml-auto" type="submit" :loading="loading"/>
                        <div v-if="hasErrors" class="text-red text-1000 pt-0500">
                            Please correct the errors above, and try again
                        </div>
                    </div>
                </form>
            </div>
            <div v-else class="mb-1500 px-1000 py-2000 bg-navy border-px border-navy text-center text-white">
                <h2 class="m-0 mb-0500 font-brand text-1125 leading-1875 text-white">Thank you!</h2>
                <p class="text-1000 text-white">Your review has been sent successfully. </p>
            </div>
        </div>
    </div>
</template>

<script>

import axios from 'axios';
import InputStar from '../InputStar'
import InputText from '../InputText'
import InputSelect from '../InputSelect'
import ButtonElement from '../ButtonElement'

export default {

    components: {
        InputText,
        InputSelect,
        ButtonElement,
        InputStar
    },

    props: {
        order_id: {
            type: Number,
            default: 0
        },
        endpoint: {
            type: String,
            required: true
        },
        score: {
            type: Number,
            default: 0
        },
        already_submitted: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {

            titles: ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Professor', 'Other'],
            title: '',
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            overall: this.score,
            food: 0,
            value: 0,
            delivery: 0,
            starsValue: [1, 2, 3, 4, 5, 6],
            ratings: [],
            message: '',
            messageWordsMin: 10,
            loading: false,
            sent: false,
            messageError: false,
            overallError: false,
            emailError: false,
            phoneError: false
        }
    },

    computed: {
        messageWords() {
            return this.message === '' ? 0 : this.message.trim().split(" ").length
        },

        hasErrors() {
            return this.messageError || this.overallError || this.emailError || this.phoneError
        }

    },

    methods: {

        validate() {
            this.overallError = this.overall === 0;
            this.messageError = this.messageWords < this.messageWordsMin;

            if (!this.hasErrors) {
                this.submit();
            }
        },

        submit() {

            if (this.loading) {
                return;
            }

            this.loading = true;

            let data = {
                overall: this.overall,
                food: this.food,
                value: this.value,
                delivery: this.delivery,
                order_id: this.order_id,
                reviewText: this.message,
                title: this.title,
                first_name: this.first_name,
                last_name: this.last_name,
                phone: this.phone,
                email: this.email,
            }

            axios.post(this.endpoint, data).then((resp) => {

                if (resp.data.success === false) {
                    this.emailError = resp.data.errors.email;
                    this.phoneError = resp.data.errors.phone;
                } else {
                    this.sent = true;
                }

                this.loading = false;
            }).catch((error) => {
                this.loading = false;
            })
        }
    },
}
</script>