<template>

  <div class="flex flex-wrap mb-1000">
      <div v-if="quantity==0">
        <div class="buy-now" @click="addToCart()">Add</div>
      </div>
    <div v-if="quantity>0" class="quantity">
      <button
        type="button"
        @click="setItemQuantity(false)"
      >
        <span class="block mt-n-0250" v-text="`-`" />
      </button>
      <div>{{ quantity }}</div>
      <button
        type="button"
        @click="setItemQuantity(true)"
      >
        <span class="block" v-text="`+`" />
      </button>
    </div>
  </div>
</template>

<script>

import axios from 'axios'

export default {
    name: "productquantity",

    data(){
        return {
            quantity: 0,
        }
    },
    props:{
        product:0,
        urls:null,
        order_id:'',
        init_quantity:0,
    },
    mounted() {
        //Expert code this
        this.quantity = this.init_quantity;
    },
    methods:{
        setItemQuantity(mode) {
            if(mode){
                this.quantity = this.quantity +1;
            }else{
                this.quantity = this.quantity -1;
            }
            axios.post(this.urls.updateProductQuantity, {
                order_id: this.order_id,
                product_id: this.product,
                quantity: this.quantity,
                variation_id:0,
            }).then(resp => {

            });
        },
        addToCart(){
            this.quantity=1;

            document.dispatchEvent(new Event('reload_cart_count'));

            axios.post('/?add-to-cart=' + this.product, {

            }).then(resp => {

            });

        }
    }

}
</script>

<style scoped>


.quantity button {
    display: inline-block;
    border:0;
}

.quantity div {
    display: inline-block;
}

.buy-now:hover{
    cursor:pointer;
}


</style>