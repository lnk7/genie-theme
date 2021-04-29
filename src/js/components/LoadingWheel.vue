<template>
  <div class="relative rounded-full bg-gradient" :class="utils">
    <div class="absolute inset-1/8 z-10 w-9/12 h-9/12 rounded-full" :class="background" />
    <div ref="wheel" class="absolute inset-0 z-20 w-full h-full rounded-full border-solid border-transparent" />
  </div>
</template>

<script>
import gsap from 'gsap'
export default {
  props: {
    utils: {
      type: String,
      default: ``
    },
    width: {
      type: Number,
      default: 4
    },
    colour: {
      type: String,
      default: `#132032`
    },
    background: {
      type: String,
      default: `bg-white`
    }
  },
  mounted() {
    this.$el.style.width = `${ this.width }rem`
    this.$el.style.height = `${ this.width }rem`
    this.$refs.wheel.style.borderWidth = `${ this.width/8 }rem`
    this.$refs.wheel.style.borderBottomColor = this.colour
    gsap.timeline({ repeat: -1 }).to(this.$refs.wheel, 1, {
      rotate: `360deg`,
      ease: `power1.inOut`
    })
  }
}
</script>

<style scoped>
  .bg-gradient {
    background-image: linear-gradient(to bottom, #f3f3f3, #eff5fd);
  }
</style>