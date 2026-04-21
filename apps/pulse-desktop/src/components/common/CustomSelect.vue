<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'

type SelectValue = string | number | null

type SelectOption = {
  label: string
  value: SelectValue
  disabled?: boolean
}

const props = withDefaults(defineProps<{
  modelValue: SelectValue
  options: SelectOption[]
  placeholder?: string
  disabled?: boolean
}>(), {
  placeholder: 'Выберите…',
  disabled: false,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: SelectValue): void
  (e: 'change', value: SelectValue): void
}>()

const rootRef = ref<HTMLElement | null>(null)
const isOpen = ref(false)

const selected = computed(() => props.options.find((opt) => opt.value === props.modelValue) ?? null)

function toggleOpen(): void {
  if (props.disabled) return
  isOpen.value = !isOpen.value
}

function close(): void {
  isOpen.value = false
}

function selectOption(option: SelectOption): void {
  if (option.disabled) return
  emit('update:modelValue', option.value)
  emit('change', option.value)
  close()
}

function onDocumentClick(event: MouseEvent): void {
  const root = rootRef.value
  if (!root) return
  if (!root.contains(event.target as Node)) {
    close()
  }
}

function onDocumentKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    close()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onDocumentKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onDocumentKeydown)
})

watch(
  () => props.disabled,
  (value) => {
    if (value) {
      close()
    }
  },
)
</script>

<template>
  <div ref="rootRef" class="custom-select" :class="{ 'custom-select--open': isOpen, 'custom-select--disabled': disabled }">
    <button type="button" class="custom-select__button" :disabled="disabled" @click="toggleOpen">
      <span class="custom-select__value">
        {{ selected?.label ?? placeholder }}
      </span>
      <ChevronDown class="custom-select__chevron" />
    </button>

    <div v-if="isOpen" class="custom-select__menu" role="listbox">
      <button
        v-for="option in options"
        :key="String(option.value)"
        type="button"
        class="custom-select__option"
        :class="{ 'custom-select__option--active': option.value === modelValue }"
        :disabled="option.disabled"
        @click="selectOption(option)"
      >
        <span>{{ option.label }}</span>
        <Check v-if="option.value === modelValue" class="custom-select__check" />
      </button>
    </div>
  </div>
</template>

<style scoped>
.custom-select {
  position: relative;
}

.custom-select__button {
  height: 44px;
  width: 100%;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-light);
  background: var(--bg-inbox);
  color: var(--text-primary);
  padding: 0 12px;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}

.custom-select__button:hover {
  border-color: color-mix(in srgb, var(--text-primary) 20%, var(--border-light));
}

.custom-select--open .custom-select__button {
  border-color: var(--color-brand);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-brand) 18%, transparent);
}

.custom-select__value {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
}

.custom-select__chevron {
  width: 16px;
  height: 16px;
  color: var(--text-secondary);
  flex-shrink: 0;
  transition: transform 0.15s ease;
}

.custom-select--open .custom-select__chevron {
  transform: rotate(180deg);
}

.custom-select__menu {
  position: absolute;
  z-index: 80;
  top: calc(100% + 6px);
  left: 0;
  right: 0;
  max-height: 240px;
  overflow-y: auto;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-light);
  background: var(--bg-inbox);
  box-shadow: var(--shadow-lg);
  padding: 6px;
}

.custom-select__option {
  width: 100%;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--text-primary);
  min-height: 36px;
  padding: 8px 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  text-align: left;
  font-size: 14px;
  transition: background-color 0.15s ease;
}

.custom-select__option:hover {
  background: color-mix(in srgb, var(--bg-thread) 75%, transparent);
}

.custom-select__option--active {
  background: color-mix(in srgb, var(--color-brand) 14%, transparent);
}

.custom-select__check {
  width: 14px;
  height: 14px;
  color: var(--color-brand);
  flex-shrink: 0;
}

.custom-select--disabled .custom-select__button,
.custom-select__button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
