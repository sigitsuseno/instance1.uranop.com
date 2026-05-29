import { h } from 'vue'

const attrs = {
  xmlns: 'http://www.w3.org/2000/svg',
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  'stroke-width': '2',
  'stroke-linecap': 'round',
  'stroke-linejoin': 'round',
}

function renderChildren(children) {
  return children.map((item) => {
    if (typeof item === 'string') return h('path', { d: item })
    if (item.tag === 'circle') return h('circle', { cx: item.cx, cy: item.cy, r: item.r })
    if (item.tag === 'rect') return h('rect', item.props)
    if (item.tag === 'line') return h('line', item.props)
    if (item.tag === 'polyline') return h('polyline', { points: item.points })
    return h('path', { d: item })
  })
}

function makeIcon(children) {
  const childArray = Array.isArray(children) ? children : [children]
  return {
    props: { class: { type: String, default: 'w-5 h-5' } },
    render() {
      return h('svg', { ...attrs, class: this.class }, renderChildren(childArray))
    },
  }
}

export const IconHome = makeIcon([
  'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
  'M9 22V12h6v10',
])

export const IconBuilding = makeIcon([
  'M2 21h20',
  'M4 21V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14',
  'M9 21v-6h6v6',
  'M9 8h.01',
  'M15 8h.01',
])

export const IconUsers = makeIcon([
  'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
  { tag: 'circle', cx: '9', cy: '7', r: '4' },
  'M22 21v-2a4 4 0 0 0-3-3.87',
  'M16 3.13a4 4 0 0 1 0 7.75',
])

export const IconCalendarCheck = makeIcon([
  'M8 2v4',
  'M16 2v4',
  'M3 10h18',
  'M21 10.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9',
  'M16 15l2 2 4-4',
])

export const IconUmbrella = makeIcon([
  'M18 19a3 3 0 0 1-6 0v-7',
  'M5.64 7.64a9 9 0 1 1 12.72 0',
  'M12 2v7',
])

export const IconFileInvoice = makeIcon([
  'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z',
  'M14 2v6h6',
  'M16 13H8',
  'M16 17H8',
  'M10 9H8',
])

export const IconGift = makeIcon([
  'M20 12v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8',
  'M12 2v20',
  'M3 12h18',
  'M7 6a3 3 0 0 1 5-2.83A3 3 0 0 1 17 6',
])

export const IconClock = makeIcon([
  'M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20z',
  'M12 6v6l4 2',
])

export const IconChartBar = makeIcon([
  'M18 20V10',
  'M12 20V4',
  'M6 20v-6',
])

export const IconCog = makeIcon([
  'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z',
  { tag: 'circle', cx: '12', cy: '12', r: '3' },
])

export const IconUserClock = makeIcon([
  'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
  { tag: 'circle', cx: '9', cy: '7', r: '4' },
  'M16 14v2l1 1',
  { tag: 'circle', cx: '16', cy: '17', r: '5' },
  'M20 19v-6',
  'M16 19h4',
])

export const IconChevronDown = makeIcon('M6 9l6 6 6-6')

export const IconChevronRight = makeIcon('M9 18l6-6-6-6')

export const IconChevronLeft = makeIcon('M15 18l-6-6 6-6')

export const IconMoon = makeIcon('M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z')

export const IconSun = makeIcon([
  { tag: 'circle', cx: '12', cy: '12', r: '5' },
  'M12 2v2',
  'M12 20v2',
  'M4.93 4.93l1.41 1.41',
  'M17.66 17.66l1.41 1.41',
  'M2 12h2',
  'M20 12h2',
  'M6.34 17.66l-1.41 1.41',
  'M19.07 4.93l-1.41 1.41',
])

export const IconLogOut = makeIcon([
  'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4',
  'M16 17l5-5-5-5',
  'M21 12H9',
])

export const IconPlus = makeIcon([
  'M12 5v14',
  'M5 12h14',
])

export const IconPencil = makeIcon([
  'M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z',
  'M15 5l4 4',
])

export const IconTrash = makeIcon([
  'M3 6h18',
  'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6',
  'M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
  'M10 11v6',
  'M14 11v6',
])

export const IconEye = makeIcon([
  'M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z',
  { tag: 'circle', cx: '12', cy: '12', r: '3' },
])

export const IconSearch = makeIcon([
  { tag: 'circle', cx: '11', cy: '11', r: '8' },
  'M21 21l-4.3-4.3',
])

export const IconDownload = makeIcon([
  'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4',
  'M7 10l5 5 5-5',
  'M12 15V3',
])

export const IconUpload = makeIcon([
  'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4',
  'M17 8l-5-5-5 5',
  'M12 3v12',
])

export const IconFilter = makeIcon('M22 3H2l8 9.46V19l4 2v-8.54L22 3z')

export const IconDollarSign = makeIcon([
  'M12 2v20',
  'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
])

export const IconX = makeIcon([
  'M18 6L6 18',
  'M6 6l12 12',
])

export const IconCheck = makeIcon('M20 6L9 17l-5-5')

export const IconAlertTriangle = makeIcon([
  'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z',
  'M12 9v4',
  'M12 17h.01',
])

export const IconInfo = makeIcon([
  { tag: 'circle', cx: '12', cy: '12', r: '10' },
  'M12 16v-4',
  'M12 8h.01',
])

export const IconBell = makeIcon([
  'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9',
  'M13.73 21a2 2 0 0 1-3.46 0',
])

export const IconArrowLeft = makeIcon([
  'M19 12H5',
  'M12 19l-7-7 7-7',
])

export const IconRefresh = makeIcon([
  'M23 4v6h-6',
  'M20.49 15a9 9 0 1 1-2.12-9.36L23 10',
])

