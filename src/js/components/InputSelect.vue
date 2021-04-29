<template>
  <div>
    <label class="block border-px border-gold">
      <div>
        <p
          class="mb-0 px-0500 text-0875 leading-1500 text-grey-600 tracking-wide"
          v-text="label"
        />
      </div>
      <div>
        <select
          class="appearance-none p-0500 border-none"
          :name="name"
          :value="value"
          :disabled="disabled"
          :required="required"
          :autocomplete="autocomplete"
          @blur="$emit(`blur`)"
          @input="$emit(`input`, $event.target.value)"
        >
          <slot />
        </select>
      </div>
    </label>
    <div
      v-if="error !== false && showError"
      class="text-0875 text-red"
      v-text="error"
    />
  </div>
</template>

<script>
export default {
  props: {
    id: {
      type: String,
      default: ``
    },
    label: {
      type: String,
      default: ``
    },
    name: {
      type: String,
      default: ``
    },
    autocomplete: {
      type: String,
      default: ``
    },
    value: {
      type: String,
      required: true
    },
    disabled: {
      type: Boolean,
      default: false
    },
    required: {
      type: Boolean,
      default: false
    },
    error: {
      type: [String, Boolean],
      default: false
    },
    showError: {
      type: Boolean,
      default: true
    }
  }
}
</script>