<template>
  <div class="border-grey-200 border-px overflow-hidden">
    <div
      class="py-1000"
      :class="{ 'hover:bg-grey-200 cursor-pointer' : !open && stepProgress >= step }"
      ref="header"
      @click="$emit(`clicked`)"
    >
      <slot name="header"/>
    </div>
    <div
      ref="body"
      class="pb-1000"
    >
      <div v-show="ready">
        <slot name="body"/>
      </div>
    </div>
  </div>
</template>

<script>
import gsap from 'gsap'
import { ScrollToPlugin } from 'gsap/ScrollToPlugin'

gsap.registerPlugin(ScrollToPlugin)

export default {
  props: {
    open: {
      type: Boolean,
      default: false
    },
    step: {
      type: Number,
      required: true
    },
    stepProgress: {
      type: Number,
      required: true
    }
  },
  data() {
    return {
      ready: false
    }
  },
  watch: {
    open(newVal) {
      this.toggle(newVal)
    }
  },
  mounted() {
    this.toggle(this.open, 0, false)
  },
  methods: {
    toggle(open, speed = .5, scroll = true) {
      if (open) this.ready = true
      this.$nextTick(() => {
        const header = this.$refs.header
        const body = this.$refs.body
        const height = open ? header.offsetHeight + body.offsetHeight : header.offsetHeight
        const clear = open ? `all` : `none`

        if (open) {
          const header = this.$refs.header
          const body = this.$refs.body
          const height = open ? header.offsetHeight + body.offsetHeight : header.offsetHeight
          const clear = open ? `all` : `none`
          gsap.to(this.$el, speed, {
            height,
            clearProps: clear,
            ease: `power2.inOut`,
            onComplete: () => {
              if (scroll) {
                gsap.to(window, {
                  duration: speed,
                  scrollTo: this.$refs.header
                })
              }
            }
          })
        } else {
          gsap.to(this.$el, speed, {
            height,
            clearProps: clear,
            ease: `power2.inOut`,
            onComplete: () => {
              this.ready = false
            }
          })
        }
      })
    }
  },
}
</script>