<template>
  <button
    :type="type"
    class="appearance-none relative inline-block px-2000 py-0500 border-px rounded-0125 text-center uppercase text-1000"
    :class="[
      { 'bg-gold hover:bg-gold-light border-gold text-gold-dark' : theme === `primary` && !disabled },
      { 'opacity-50 bg-grey-400 border-grey-400 cursor-not-allowed text-grey-600' : theme === `primary` && disabled },
      { 'bg-transparent hover:bg-gold border-navy hover:border-gold text-navy hover:text-gold-dark' : theme === `secondary` },
      { 'opacity-50 bg-transparent border-grey-600 cursor-not-allowed text-grey-600' : theme === `secondary` && disabled },
      utils
    ]"
    @click="$emit(`clicked`)"
  >
    <span
      :class="{ 'opacity-0' : loading }"
      v-text="text"
    />
    <div
      v-if="loading"
      class="flex absolute inset-0"
    >
      <div class="self-center m-auto">
        <LoadingWheel
          :width="2"
          colour="#776229"
          :background="theme === `primary` ? 'bg-gold' : 'bg-white'"
        />
      </div>
    </div>
  </button>
</template>

<script>
import LoadingWheel from './LoadingWheel'
export default {
  components: {
   LoadingWheel 
  },
  props: {
    type: {
      type: String,
      default: `button`
    },
    text: {
      type: String,
      default: ``
    },
    utils: {
      type: String,
      default: ``
    },
    theme: {
      type: String,
      default: `primary`
    },
    loading: {
      type: Boolean,
      default: false
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {}
  }
}
</script> 