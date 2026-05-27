export function useCurrency() {
  function format(value) {
    if (value == null) return 'Rp 0'
    return 'Rp ' + Number(value).toLocaleString('id-ID')
  }

  function formatNoPrefix(value) {
    if (value == null) return '0'
    return Number(value).toLocaleString('id-ID')
  }

  return { format, formatNoPrefix }
}
