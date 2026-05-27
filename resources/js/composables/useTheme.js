import { ref, watchEffect } from 'vue'

const isDark = ref(false)

export function useTheme() {
    function apply() {
        document.documentElement.classList.toggle('dark', isDark.value)
        localStorage.setItem('theme', isDark.value ? 'dark' : 'light')
    }

    function init() {
        const stored = localStorage.getItem('theme')
        isDark.value = stored === 'dark'
        apply()
    }

    function toggle() {
        isDark.value = !isDark.value
        apply()
    }

    watchEffect(apply)

    return { isDark, init, toggle }
}
