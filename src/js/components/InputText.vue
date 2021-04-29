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
        <input
          class="appearance-none px-0500 pt-0 pb-0250 border-none"
          type="text"
          :name="name"
          :value="value"
          :disabled="disabled"
          :required="required"
          :autocomplete="autocomplete"
          @focus="$emit(`focus`)"
          @blur="$emit(`blur`)"
          @input="$emit(`input`, $event.target.value)"
        >
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