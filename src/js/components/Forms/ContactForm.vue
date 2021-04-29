<template>
    <div id="cahFrontend" class="font-sans not-italic">
        <form @submit.prevent="submit()" class="max-w-md mx-auto font-sans not-italic">
            <div
                v-if="formSubmitted"
                class="mb-2000 px-1000 py-2000 bg-navy border-px border-navy text-center text-white"
            >
                <h2 class="m-0 mb-0500 font-brand text-1125 leading-1875 text-white">Thank you!</h2>
                <p class="text-1000 text-white">Your message has been sent successfully. We will be in touch soon</p>
            </div>

            <div v-else>
                <div class="pb-1000">
                    <InputSelect
                        v-model="purpose"
                        label="Purpose for contacting us*"
                        :required="true"
                    >
                        <option value="">Please choose</option>
                        <option value="general">General Enquiry</option>
                        <option value="feedback">Service/Product Feedback</option>
                    </InputSelect>
                </div>

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
                        :error="emailError"

                    />
                </div>

                <div class="pb-1000">
                    <InputText
                        v-model="phone"
                        label="Phone"
                    />
                </div>

                <div class="pb-1000">
                    <InputText
                        v-model="orderID"
                        label="Order number"
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


                <div class="photos mb-1000 bg-grey-100 border-px border-gold">
                    <div class="flex justify-center items-center relative min-h-6000 hover:bg-grey-200 cursor-pointer">
                        <input type="file" ref="photos" multiple name="photos" :disabled="disabled" @change="change($event)" accept="image/*" class="input-file">
                        <p class="m-auto p-0 text-1000 font-sans not-italic text-center">Drag your file(s) here to begin<br> or click to browse</p>
                    </div>
                    <div class="flex flex-wrap justify-center items-center">
                        <div class="image" v-for="photo in photos" :style="`background-image:url(${photo.url})`">
                            <div class="x" @click="deletePhoto(photo.name)"></div>
                        </div>
                    </div>
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

    props: {
      endpoint: {
        type: String,
        required: true

      }
    },

    data() {
      return {
        orderID : '',
        formSubmitted: false,
        purpose: '',
        firstName: '',
        lastName: '',
        email: '',
          emailError:null,
        phone: '',
        message: '',
        loading: false,
        photos: [],
      }
    },

    computed: {
      disabled() {
        return this.loading;
      }
    },

    methods: {

      deletePhoto(name) {
        this.photos = this.photos.filter(photo => {
          return photo.name !== name
        })
      },

      change($event) {
        let newFiles = $event.target.files.length

        for (let i = 0; i < newFiles; i++) {
          let file = $event.target.files[i];
          this.photos.push({
            name: file.name,
            url: URL.createObjectURL(file),
            file: file,
          })
        }
      },
        submit(){

          if(this.email == '' || this.validateEmail(this.email) === false){
              this.emailError = "Please enter a valid email address";
          }else{
              this.process();
              this.emailError = '';
          }

        },
        validateEmail(email){
            //Regex email checker
            const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        },
      process() {
        if (this.loading) {
          return;
        }
        this.loading = true;

        let formData = new FormData();

        this.photos.forEach((photo, index) => {
          formData.append(`photos[${index}]`, photo.file);
        });
        formData.append('purpose', this.purpose);
        formData.append('photoCount', this.photos.length);
        formData.append('firstName', this.firstName);
        formData.append('lastName', this.lastName);
        formData.append('email', this.email);
        formData.append('phone', this.phone);
        formData.append('message', this.message);
        formData.append('orderID', this.orderID);
        formData.append('location', window.location.href);

        axios.post(
          this.endpoint,
          formData,
          {
            headers: {
              'Content-Type': 'multipart/form-data'
            }
          }
        ).then((resp) => {
          this.formSubmitted = true;
          this.loading = false
          this.firstName = '';
          this.lastName = '';
          this.email = '';
          this.phone = '';
          this.message = '';
          this.photos = [];
          this.orderID = ''
        });
      }
    },

  }
</script>

<style scoped>
    .image {
        width: 150px;
        height: 150px;
        margin: 15px;
        position: relative;
        background-size: cover;
        -webkit-box-shadow: 0px 0px 15px 0px rgba(0, 0, 0, 0.37);
        -moz-box-shadow: 0px 0px 15px 0px rgba(0, 0, 0, 0.37);
        box-shadow: 0px 0px 15px 0px rgba(0, 0, 0, 0.37);

        .x {
            position: absolute;
            top: -12px;
            right: -12px;
            width: 24px;
            height: 24px;
            border: 2px solid #eef5df;
            background-color: #333;
            border-radius: 50%;
        }

        .x::before,
        .x::after {
            position: absolute;
            top: 9px;
            left: 3px;
            width: 14px;
            height: 3px;
            content: "";
            background-color: #eef5df;
            /*display: none;*/
        }

        .x::before {
            -ms-transform: rotate(-45deg);
            -webkit-transform: rotate(-45deg);
            transform: rotate(-45deg);
        }

        .x::after {
            -ms-transform: rotate(45deg);
            -webkit-transform: rotate(45deg);
            transform: rotate(45deg);
        }

        .x:hover {
            cursor: pointer;
        }
    }

    .input-file {
        opacity: 0; /* invisible but it's there! */
        width: 100%;
        height: 100px;
        position: absolute;
        cursor: pointer;
    }

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