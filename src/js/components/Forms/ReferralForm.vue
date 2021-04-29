<template>
    <div id="cahFrontend" class="font-sans not-italic">
        <form @submit.prevent="submit()" class="max-w-md mx-auto font-sans not-italic">
            <div
                v-if="formSubmitted"
                class="mb-2000 px-1000 py-2000 border-px text-center text-white"
                :class="[ response.success ? 'bg-navy border-navy' : 'bg-red-light border-red' ]"
            >
                <h2
                  class="m-0 mb-0500 font-brand text-1125 leading-1875"
                  :class="[ response.success ? 'text-white' : 'text-red' ]"
                  v-text="response.success ? 'Thank you for your referral.' : 'Something went wrong.'"
                />
                <p
                  class="text-1000"
                  :class="[ response.success ? 'text-white' : 'text-red' ]"
                >{{ response.message}}</p>
            </div>

            Your Details:
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

            To:
            <div v-for="(contact, index) in to" class="pb-1000">
              <div class="md:flex">
                <div class="flex-1 mr-1000 pb-1000 md:pb-0">
                    <InputText
                        v-model="contact.firstName"
                        label="Their First Name*"
                        :required="true"
                    />
                </div>

                <div class="flex-1 pb-1000 md:pb-0">
                    <InputText
                        v-model="contact.email"
                        label="Their email*"
                        :required="true"
                    />
                </div>
              </div>
              <div
              v-if="index > 0"
                class="text-right text-0750 underline"
              >
                  <a
                      href="#"
                      class="hover:text-gold"
                      v-text="'Remove'"
                      @click.prevent="deleteRow(index)"
                  />
              </div>
            </div>

            <div class="pb-2000">
              <ButtonElement
                  text="Add a new recipient"
                  @clicked="addRow"
              />
            </div>

            <div class="mb-1000 border-px border-gold">
                <label class="block p-0500 text-1000">
                    Your message
                </label>
                <div class="p-0500 text-1000">
                    <textarea
                        v-model="message"
                        rows="4"
                        class="appearance-none block p-0500 rounded-0 border-px border-grey-200"
                        :required="true"
                        placeholder="Type a message here..."
                    />
                </div>
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


    props: {
      endpoint: {
        type: String,
        required: true

      }
    },


    data() {
      return {
        formSubmitted: false,
        firstName: '',
        lastName: '',
        email: '',
        phone: '',
        message: '',
        to: [{
          firstName: '',
          email: ''
        }],
        consent: false,
        loading: false,
        waitingForAPI: true,
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

      addRow() {
        this.to.push({
          firstName: '',
          email: ''
        })
      },

      deleteRow(index) {
        this.to.splice(index, 1);
      },

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
          message: this.message,
          to: this.to,
          consent: this.consent,
          location: window.location.href,
          terms: this.terms,
        }


        axios
          .post(this.endpoint, data)
          .then((resp) => {
            this.formSubmitted = true;
            this.response = resp.data;
            this.loading = false;
            window.scrollTo(0, 0)
            if (resp.data.success) {
              this.loading = false
              this.to = [{
                firstName: '',
                email: ''
              }];
            }
          })
          .catch((err) => {
            this.formSubmitted = true;
            this.loading = false;
            window.scrollTo(0, 0)
            this.response = {
              success: false,
              message: 'There was an error saving the form, please try again later'
            }
          });
      }
    },
    mounted() {

      const url = new URL(window.location.href);
      const ee = url.searchParams.get("ee");
      console.log('ee', ee);
      if (ee) {
        axios.get('https://hub.cote.co.uk/api/customer/' + ee)
          .then((resp) => {
            console.log(resp.data);
            if (resp.data.success) {
              this.email = resp.data.customer.email;
              this.firstName = resp.data.customer.first_name;
              this.lastName = resp.data.customer.last_name;
              this.waitingForAPI = false;
            }
          })
          .catch(err => {
            this.waitingForAPI = false;
          });
      } else {
        this.waitingForAPI = false;
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