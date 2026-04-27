import { ref, watch, type Ref } from 'vue'

export function useDebouncedValue<T>(source: Ref<T>, delay = 250): Ref<T> {
  const debounced = ref(source.value) as Ref<T>
  let timeoutId: number | undefined

  watch(source, (value) => {
    window.clearTimeout(timeoutId)
    timeoutId = window.setTimeout(() => {
      debounced.value = value
    }, delay)
  })

  return debounced
}
