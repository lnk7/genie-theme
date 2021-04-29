import Vue from 'vue'
import Checkout from './components/Checkout'
import Order from './components/Order'
import Cart from './components/Cart'
import productquantity from './components/ProductQuantity'
import '../../common/js/filters/ToCurrency.js'
import ContactForm from './components/Forms/ContactForm'
import ReferralForm from './components/Forms/ReferralForm'
import ReviewForm from './components/Forms/ReviewForm'
import NhsForm from './components/Forms/NhsForm'
import GiftPage from './components/Forms/GiftPage'

import axios from 'axios';

if (document.getElementById(`checkout`)) {
    new Vue({
        el: `#checkout`,
        components: {
            Checkout
        }
    })
}
Vue.config.productionTip = false


if (document.getElementById(`order`)) {
    new Vue({
        el: `#order`,
        components: {
            Order
        }
    })
}

console.log(document.getElementById('headercart'));

if (document.getElementById('headercart')) {
    new Vue({
        el: '#headercart',
        components: {
            Cart
        }
    })
}

Vue.config.productionTip = false


if (document.getElementById(`contact_form`)) {

    new Vue({
        el: '#contact_form',

        components: {
            ContactForm,
        }
    });
}


if (document.getElementById(`gift_page`)) {

    new Vue({
        el: '#gift_page',

        components: {
            GiftPage,
        }
    });
}


if (document.getElementById(`nhs_form`)) {

    new Vue({
        el: '#nhs_form',

        components: {
            NhsForm,
        }
    });
}


if (document.getElementById(`referral_form`)) {

    new Vue({
        el: '#referral_form',

        components: {
            ReferralForm,
        }
    });
}

if (document.getElementById(`review_form`)) {

    new Vue({
        el: '#review_form',

        components: {
            ReviewForm,
        }
    });
}


if (document.getElementsByClassName(`quantity_control`)) {

    const sections = document.getElementsByClassName( "quantity_control" );

    for ( let i = 0; i < sections.length; i ++ ) {
        new Vue({
            el: '#' + sections[ i ].id,

            components: {
               productquantity,
            }
        });
    }


}



/**
 * Pull all tracking from the session and send to exponea.
 * This can be run as many times as needed.
 */
window.processTracking = function () {

    if (!exponeaTracking) {
        return;
    }

    axios.post(trackingEndpoint)
        .then((resp) => {
            resp.data.forEach(tracking => {
                switch (tracking.command) {

                    case 'identify' :
                        exponea.identify(tracking.email);
                        break;
                    case 'update' :
                        exponea.update(tracking.properties);
                        break;
                    case 'track' :
                        exponea.track(tracking.event, tracking.properties);
                        break;
                }
            })
        });
}

window.processTracking();