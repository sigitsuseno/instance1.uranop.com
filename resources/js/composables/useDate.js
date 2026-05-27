export function useDate() {
  function format(value, fmt = 'dd/MM/yyyy') {
    if (!value) return '-'
    const d = new Date(value)
    if (isNaN(d.getTime())) return '-'

    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')

    switch (fmt) {
      case 'dd/MM/yyyy': return `${day}/${month}/${year}`
      case 'dd MMM yyyy':
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
        return `${day} ${months[d.getMonth()]} ${year}`
      case 'dd/MM/yyyy HH:mm': return `${day}/${month}/${year} ${hours}:${minutes}`
      case 'yyyy-MM-dd': return `${year}-${month}-${day}`
      default: return `${day}/${month}/${year}`
    }
  }

  function relative(value) {
    if (!value) return '-'
    const d = new Date(value)
    if (isNaN(d.getTime())) return '-'
    const now = new Date()
    const diffMs = now - d
    const diffMins = Math.floor(diffMs / 60000)
    if (diffMins < 1) return 'Baru saja'
    if (diffMins < 60) return `${diffMins} menit lalu`
    const diffHours = Math.floor(diffMins / 60)
    if (diffHours < 24) return `${diffHours} jam lalu`
    const diffDays = Math.floor(diffHours / 24)
    if (diffDays < 7) return `${diffDays} hari lalu`
    return format(value)
  }

  function monthName(month) {
    const names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
    return names[month - 1] || ''
  }

  return { format, relative, monthName }
}
