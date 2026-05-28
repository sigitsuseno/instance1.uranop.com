const BASE_URL = import.meta.env.VITE_API_URL || ''

export async function api(path, options = {}) {
    const url = `${BASE_URL}${path}`

    const defaultHeaders = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    }

    const token = localStorage.getItem('token')
    if (token) {
        defaultHeaders['Authorization'] = `Bearer ${token}`
    }

    const response = await fetch(url, {
        headers: defaultHeaders,
        ...options,
    })

    if (!response.ok) {
        let message = `API error: ${response.status}`
        try {
            const body = await response.json()
            if (body.message) {
                message = body.message
            } else if (body.errors) {
                const first = Object.values(body.errors).flat()
                message = first.length ? first[0] : message
            }
        } catch {}
        throw new Error(message)
    }

    return response.json()
}

export function useApi() {
    const get = (path, options = {}) => api(path, { ...options, method: 'GET' })
    const post = (path, body, options = {}) => api(path, { ...options, method: 'POST', body: JSON.stringify(body) })
    const put = (path, body, options = {}) => api(path, { ...options, method: 'PUT', body: JSON.stringify(body) })
    const destroy = (path, options = {}) => api(path, { ...options, method: 'DELETE' })

    return { get, post, put, destroy, api }
}
