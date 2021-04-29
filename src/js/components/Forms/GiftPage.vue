<template>
    <div id="cahFrontend" class="font-sans not-italic">
        <form @submit.prevent="submit()" class="max-w-md mx-auto font-sans not-italic">

            <div>

                <div class="pb-1000">
                    <InputText
                            v-model="orderID"
                            label="Order ID*"
                            :required="true"
                    />
                </div>

                <div class="flex pb-1000">
                    <div class="ml-auto">
                        <ButtonElement
                                text="Generate Gift Message"
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
import ButtonElement from '../ButtonElement'

export default {
    components: {
        InputText,
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
            orderID: '',
            loading: false,
            response: {
                success: false,
                message: ''
            }
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
                order_id: this.orderID,
            }

            axios
                .post(this.endpoint, data)
                .then((resp) => {
                    debugger;
                    this.formSubmitted = true;
                    this.response = resp.data;
                    this.loading = false;
                    if (resp.data.success) {
                       var win = window.open(resp.data.url, '_blank');
                        win.focus();
                    } else {
                        this.response = {
                            success: false,
                            message: resp.data.message
                        }

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