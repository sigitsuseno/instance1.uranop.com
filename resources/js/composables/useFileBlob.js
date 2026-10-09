const BASE_URL = import.meta.env.VITE_API_URL || ''

/**
 * Header ber-autentikasi.
 *
 * Token disimpan di localStorage dan dikirim sebagai Bearer, sehingga berkas
 * TIDAK bisa diambil lewat window.open / <a href> biasa — keduanya tidak
 * membawa header. Karena itu unduhan dan pratinjau diambil via fetch + Blob.
 */
function authHeaders(extra = {}) {
    const headers = { ...extra }
    const token = localStorage.getItem('token')
    if (token) {
        headers['Authorization'] = `Bearer ${token}`
    }
    return headers
}

/** Ambil berkas dari endpoint ber-autentikasi sebagai Blob. */
export async function fetchFileBlob(path) {
    const response = await fetch(`${BASE_URL}${path}`, {
        headers: authHeaders({ Accept: '*/*' }),
    })

    if (!response.ok) {
        let message = `Gagal memuat berkas (${response.status}).`
        try {
            const data = await response.json()
            if (data?.message) message = data.message
        } catch {
            // Respons bukan JSON — pakai pesan bawaan.
        }

        const error = new Error(message)
        error.response = { status: response.status }
        throw error
    }

    return response.blob()
}

/** Simpan Blob sebagai unduhan. */
export function saveBlob(blob, filename) {
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
}

/** Buat object URL dari Blob; ingat untuk melepasnya dengan releaseObjectUrl(). */
export function toObjectUrl(blob) {
    return URL.createObjectURL(blob)
}

/** Lepas object URL agar memori tidak tertahan. */
export function releaseObjectUrl(url) {
    if (url) {
        URL.revokeObjectURL(url)
    }
}
