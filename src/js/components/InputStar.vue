<template>
    <div class="star-input">
        <div
                v-for="star in 6"
                class="input-star-button"
                :class="[{ 'preview' : current >= star },{ 'active' : start >= star } ]"
                @focus="preview(star)"
                @mouseenter="preview(star)"
                @mouseleave="preview(0)"
                @click.prevent="select(star)"
        >
            <svg viewBox="0 12.705 512 486.59">
                <polygon points="256.814,12.705 317.205,198.566 512.631,198.566 354.529,313.435 414.918,499.295 256.814,384.427 98.713,499.295 159.102,313.435 1,198.566 196.426,198.566 "/>
            </svg>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        stars: {
            type: Array,
            required: true
        },

        start: {
            type: Number,
            default: 0
        }
    },

    data() {
        return {
            current: this.start,
        }
    },

    methods: {
        preview(i) {
            this.current = i
        },

        select(i) {
            this.current = i
            this.$emit('change', i)
        }
    }
}
</script>

<style lang="scss" scoped>


.star-input {
  display: flex;
  max-width: 400px;
}

.input-star-button {

  flex: 1;
  margin-right: 10px;

  svg {
    stroke: #D2C298;
    stroke-width: 1rem;
    fill: transparent;

  }

  &.preview {
    svg {
      fill: #D2C298;
      fill-opacity: 35%;
    }
  }

  &.active {
    svg {
      fill: #D2C298;
      fill-opacity: 1000%;
    }
  }
}
</style>
