<template>
    <div id="cahFrontend" class="font-sans not-italic">
        <form @submit.prevent="submit()" class="max-w-md mx-auto font-sans not-italic">

            <div
                v-if="formSubmitted"
                class="mb-2000 px-1000 py-2000 bg-navy border-px border-navy text-center text-white"
            >
                <div v-if="response.success">
                    <h2 class="m-0 mb-0500 font-brand text-1125 leading-1875 text-white">Thank you for registering.</h2>
                    <p class="text-1000 text-white">Your discount code will be delivered by email shortly. Should it not arrive, please check your junk/spam folder.</p>
                </div>
                <div v-else>
                    <h2 class="m-0 mb-0500 font-brand text-1125 leading-1875 text-white">Sorry there was a problem.</h2>
                    <p class="text-1000 text-white">{{ response.message }}</p>
                </div>
            </div>

            <div v-else>

                <div class="md:flex">
                    <div class="flex-1 mr-1000 pb-1000">
                        <InputText
                            v-model="firstName"
                            label="First name*"
                            :required="true"
                        />
                    </div>
                    <div class="flex-1 pb-1000">
                        <InputText
                            v-model="lastName"
                            label="Last name*"
                            :required="true"
                        />
                    </div>
                </div>

                <div class="pb-1000">
                    <InputText
                        v-model="email"
                        label="Email*"
                        :required="true"
                    />
                </div>

                <div class="pb-1000">
                    <InputText
                        v-model="phone"
                        label="Phone"
                    />
                </div>

                <div class="pb-1000">
                    <label class="block p-0500 border-px border-gold text-1000 font-sans not-italic">
                        <input
                            v-model="consent"
                            type="checkbox"
                            value="0"
                        >
                        <span v-html="terms" />
                    </label>
                </div>


                <div class="flex pb-1000">
                    <div class="ml-auto">
                        <ButtonElement
                            text="Send message"
                            utils="ml-auto"
                            type="submit"
                            :loading="loading"
                        />
                    </div>
                </div>

            </div>
        </form>
    </div>
</template>

<script>

  import axios from 'axios';
  import InputText from '../InputText'
  import InputSelect from '../InputSelect'
  import ButtonElement from '../ButtonElement'

  export default {
    components: {
        InputText,
        InputSelect,
        ButtonElement
    },

    data() {
      return {
        formSubmitted: false,
        firstName: '',
        lastName: '',
        email: '',
        phone: '',
        consent: false,
        loading: false,
        response: {
          success: false,
          message: ''
        },
        terms: 'I agree to receive news and updates from Côte via email. <i>(Please note, your privacy is important to us, we won’t share your information with any other parties. View our privacy policy <a href="https://cote.co.uk/privacy-policy-cookies/" target="_blank" rel="noopener"><u>here</u></a>.)</i>'
      }
    },

    computed: {
      disabled() {
        return this.loading;
      }
    },

    methods: {
      submit() {
        if (this.loading) {
          return;
        }
        this.loading = true;

        let data = {
          firstName: this.firstName,
          lastName: this.lastName,
          email: this.email,
          phone: this.phone,
          consent: this.consent,
          location: window.location.href,
          terms: this.terms,
        }

        axios
          .post('https://hub.cote.co.uk/api/at_home/es_form', data)
          .then((resp) => {
            this.formSubmitted = true;
            this.response = resp.data;
            this.loading = false;
            if (resp.data.success) {
              this.loading = false
              this.firstName = '';
              this.lastName = '';
              this.phone = '';
              this.email = '';
              this.consent = false;
            }
          })
          .catch((err) => {
            this.formSubmitted = true;
            this.loading = false;
            this.response = {
              success: false,
              message: 'There was an error saving the form, please try again later'
            }
          });
      }
    }
  }
</script>

<style lang="scss" scoped>

    @keyframes spinner {
        to {
            transform: rotate(360deg);
        }
    }

    .spinner:before {
        content: '';
        box-sizing: border-box;
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin-top: -10px;
        margin-left: -10px;
        border-radius: 50%;
        border: 2px solid #ccc;
        border-top-color: #000;
        animation: spinner .6s linear infinite;
    }

</style>