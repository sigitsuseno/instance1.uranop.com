import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi'

export const useScheduleStore = defineStore('schedule', () => {
  // 1. STATE
  const shifts = ref([])
  const calendars = ref([])
  const workPatterns = ref([])
  const rosterSchedules = ref({}) // Key format: 'YYYY-MM'. Contains array of employee schedules

  const api = useApi()

  // 2. HELPER
  function getDaysInMonthRange(year, month) {
    const list = []
    const prevYear = month === 1 ? year - 1 : year
    const prevMonth = month === 1 ? 12 : month - 1
    
    const startDate = new Date(prevYear, prevMonth - 1, 25)
    const endDate = new Date(year, month - 1, 24)

    const current = new Date(startDate)
    while (current <= endDate) {
      list.push({
        date: new Date(current),
        day: current.getDate(),
        dow: current.getDay(),
        dateStr: current.toISOString().slice(0, 10)
      })
      current.setDate(current.getDate() + 1)
    }
    return list
  }

  // 3. ACTIONS
  async function fetchShifts() {
    try {
      const res = await api.get('/api/schedule/shifts')
      // Inject some default colors if not exist
      const colorPresets = { 'P': '#3b82f6', 'S': '#f59e0b', 'ML': '#8b5cf6', 'L': '#ef4444' }
      shifts.value = res.data.map(s => {
        let color = s.color || colorPresets[s.external_code] || '#64748b'
        if (s.metadata && s.metadata.color) {
          color = s.metadata.color
        }
        return {
          ...s,
          color
        }
      })
    } catch (error) {
      console.error('Failed to fetch shifts', error)
    }
  }

  async function saveShift(payload) {
    try {
      await api.post('/api/schedule/shifts', payload)
      await fetchShifts()
      return true
    } catch (error) {
      console.error('Failed to save shift', error)
      throw error
    }
  }

  async function updateShift(id, payload) {
    try {
      await api.put(`/api/schedule/shifts/${id}`, payload)
      await fetchShifts()
      return true
    } catch (error) {
      console.error('Failed to update shift', error)
      throw error
    }
  }

  async function deleteShift(id) {
    try {
      await api.destroy(`/api/schedule/shifts/${id}`)
      await fetchShifts()
      return true
    } catch (error) {
      console.error('Failed to delete shift', error)
      throw error
    }
  }

  async function fetchWorkPatterns() {
    try {
      const res = await api.get('/api/schedule/work-patterns')
      workPatterns.value = res.data.map(wp => {
        const detailGroups = {}
        if (wp.details && Array.isArray(wp.details)) {
          wp.details.forEach(detail => {
            if (!detailGroups[detail.name]) {
              detailGroups[detail.name] = []
            }
            detailGroups[detail.name].push(detail)
          })
          
          // Sort details by day_number
          Object.keys(detailGroups).forEach(key => {
            detailGroups[key].sort((a, b) => a.day_number - b.day_number)
          })
        }
        return {
          ...wp,
          detailGroups
        }
      })
    } catch (error) {
      console.error('Failed to fetch work patterns', error)
    }
  }

  async function fetchCalendars() {
    try {
      const res = await api.get('/api/schedule/calendars')
      calendars.value = res.data
    } catch (error) {
      console.error('Failed to fetch calendars', error)
    }
  }

  async function fetchRosterForPeriod(year, month) {
    const key = `${year}-${String(month).padStart(2, '0')}`
    try {
      // useApi GET doesn't automatically append query params from an object like axios
      const res = await api.get(`/api/schedule/roster?year=${year}&month=${month}`)
      rosterSchedules.value[key] = res.data
      return rosterSchedules.value[key]
    } catch (error) {
      console.error('Failed to fetch roster', error)
      return []
    }
  }

  // Backwards compatibility for UI that expects sync return, though they should use fetchRosterForPeriod
  function getRosterForPeriod(year, month) {
    const key = `${year}-${String(month).padStart(2, '0')}`
    return rosterSchedules.value[key] || []
  }

  async function saveHoliday(calendarId, holidayId, payload) {
    try {
      if (holidayId) {
        await api.put(`/api/schedule/calendars/${calendarId}/holidays/${holidayId}`, payload)
      } else {
        await api.post(`/api/schedule/calendars/${calendarId}/holidays`, payload)
      }
      await fetchCalendars() // Refresh data
      return true
    } catch (error) {
      console.error('Failed to save holiday', error)
      throw error
    }
  }

  async function deleteHoliday(calendarId, holidayId) {
    try {
      await api.destroy(`/api/schedule/calendars/${calendarId}/holidays/${holidayId}`)
      await fetchCalendars() // Refresh data
      return true
    } catch (error) {
      console.error('Failed to delete holiday', error)
      throw error
    }
  }

  async function saveWorkPatternDetails(patternId, payload) {
    try {
      await api.post(`/api/schedule/work-patterns/${patternId}/details`, payload)
      await fetchWorkPatterns()
      return true
    } catch (error) {
      console.error('Failed to save work pattern details', error)
      throw error
    }
  }

  async function deleteWorkPatternDetailsGroup(patternId, groupName) {
    try {
      await api.destroy(`/api/schedule/work-patterns/${patternId}/details/group`, { body: JSON.stringify({ name: groupName }) })
      await fetchWorkPatterns()
      return true
    } catch (error) {
      console.error('Failed to delete work pattern details group', error)
      throw error
    }
  }

  async function generateRosterBulk(payload) {
    try {
      await api.post(`/api/schedule/roster/generate`, payload)
      return true
    } catch (error) {
      console.error('Failed to generate roster', error)
      throw error
    }
  }

  async function overrideRosterCell(payload) {
    try {
      await api.post(`/api/schedule/roster/override`, payload)
      return true
    } catch (error) {
      console.error('Failed to override roster', error)
      throw error
    }
  }

  return {
    shifts,
    calendars,
    workPatterns,
    rosterSchedules,
    fetchShifts,
    fetchWorkPatterns,
    fetchCalendars,
    fetchRosterForPeriod,
    getRosterForPeriod,
    getDaysInMonthRange,
    saveHoliday,
    deleteHoliday,
    saveShift,
    updateShift,
    deleteShift,
    saveWorkPatternDetails,
    deleteWorkPatternDetailsGroup,
    generateRosterBulk,
    overrideRosterCell,
  }
})
