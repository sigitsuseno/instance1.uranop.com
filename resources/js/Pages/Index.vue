<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

const showPrivacy = ref(false)
const showTerms = ref(false)
</script>

<template>
  <div class="bg-slate-950 text-slate-200 selection:bg-blue-500 selection:text-white w-full min-h-screen">
    <nav class="flex items-center justify-between px-8 py-6 max-w-7xl mx-auto">
      <div class="flex items-center space-x-2">
        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center font-black text-white shadow-lg shadow-blue-600/20">
          U
        </div>
        <div class="flex flex-col">
          <span class="text-xl font-bold tracking-tighter text-white">Uranop Enterprise</span>
        </div>
      </div>

      <div class="items-center space-x-6 hidden lg:flex">
        <RouterLink to="/login"
          class="px-5 py-2.5 bg-white text-slate-950 rounded-full text-sm font-bold hover:bg-blue-400 hover:text-white transition-all">
          Sign In
        </RouterLink>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto px-8 lg:h-[calc(100vh-205px)] grid lg:grid-cols-2 gap-12 items-center">
      <div class="space-y-8 pt-16">
        <h1 class="text-6xl md:text-7xl font-black text-white leading-[1.1] tracking-tight">
          Aplikasi Payroll <br />
          <span class="bg-linear-to-r from-blue-400 to-emerald-400 bg-clip-text text-transparent mt-8 block">
            Uranop Enterprise
          </span>
        </h1>
        <div class="flex flex-col sm:flex-row gap-4">
          <RouterLink to="/login"
            class="px-8 py-4 bg-blue-600 hover:bg-blue-500 text-white rounded-2xl font-bold text-center transition-all shadow-xl shadow-blue-600/20 flex items-center justify-center space-x-3">
            <span>Masuk ke Dashboard</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </RouterLink>
        </div>
      </div>

      <div class="relative hidden lg:block">
        <div class="absolute inset-0 bg-blue-500/20 blur-[120px] rounded-full"></div>
        <div class="relative bg-slate-900 border border-white/10 rounded-3xl p-4 shadow-2xl">
          <div class="bg-slate-950 rounded-2xl p-8 border border-white/5">
            <div class="flex space-x-2 mb-6">
              <div class="w-3 h-3 rounded-full bg-red-500/50"></div>
              <div class="w-3 h-3 rounded-full bg-yellow-500/50"></div>
              <div class="w-3 h-3 rounded-full bg-green-500/50"></div>
            </div>
            <div class="space-y-4">
              <div class="h-4 bg-white/5 rounded-full w-3/4"></div>
              <div class="h-4 bg-white/5 rounded-full w-full"></div>
              <div class="h-4 bg-white/5 rounded-full w-5/6"></div>
              <div class="grid grid-cols-3 gap-4 mt-8">
                <div class="h-20 bg-blue-500/10 rounded-xl border border-blue-500/20"></div>
                <div class="h-20 bg-emerald-500/10 rounded-xl border border-emerald-500/20"></div>
                <div class="h-20 bg-purple-500/10 rounded-xl border border-purple-500/20"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <footer class="border-t border-white/5 py-12">
      <div class="max-w-7xl mx-auto px-8 flex flex-col md:flex-row justify-between items-center text-slate-500 text-sm">
        <p>&copy; {{ new Date().getFullYear() }} Uranop Enterprise. All rights reserved.</p>
        <div class="flex space-x-8 mt-4 md:mt-0">
          <button @click="showPrivacy = true" class="hover:text-white transition-colors">Kebijakan Privasi</button>
          <button @click="showTerms = true" class="hover:text-white transition-colors">Syarat & Ketentuan</button>
        </div>
      </div>
    </footer>

    <div v-if="showPrivacy" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="showPrivacy = false">
      <div class="bg-slate-900 border border-white/10 rounded-3xl max-w-2xl w-full max-h-[80vh] overflow-y-auto p-8 relative">
        <button @click="showPrivacy = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <h2 class="text-2xl font-bold text-white mb-6">Kebijakan Privasi</h2>
        <div class="prose prose-invert prose-sm text-slate-400 space-y-4">
          <p>Kami di Uranop Enterprise sangat menghargai privasi data Anda. Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi informasi pribadi Anda.</p>
          <h3 class="text-white font-semibold">1. Pengumpulan Data</h3>
          <p>Kami mengumpulkan data yang diperlukan untuk operasional payroll, termasuk namun tidak terbatas pada nama, jabatan, gaji, dan data kehadiran.</p>
          <h3 class="text-white font-semibold">2. Penggunaan Data</h3>
          <p>Data digunakan semata-mata untuk perhitungan gaji, pelaporan pajak, dan manajemen sumber daya manusia di lingkungan internal perusahaan.</p>
          <h3 class="text-white font-semibold">3. Keamanan</h3>
          <p>Kami menggunakan standar enkripsi terkini untuk memastikan data Anda aman dari akses yang tidak sah.</p>
        </div>
      </div>
    </div>

    <div v-if="showTerms" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" @click.self="showTerms = false">
      <div class="bg-slate-900 border border-white/10 rounded-3xl max-w-2xl w-full max-h-[80vh] overflow-y-auto p-8 relative">
        <button @click="showTerms = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <h2 class="text-2xl font-bold text-white mb-6">Syarat & Ketentuan Penggunaan</h2>
        <div class="prose prose-invert prose-sm text-slate-400 space-y-4">
          <p>Selamat datang di sistem Uranop Enterprise. Dengan menggunakan aplikasi ini, Anda setuju untuk mematuhi syarat berikut:</p>
          <h3 class="text-white font-semibold">1. Hak Akses</h3>
          <p>Akses ke sistem ini hanya diberikan kepada karyawan resmi perusahaan.</p>
          <h3 class="text-white font-semibold">2. Tanggung Jawab Akun</h3>
          <p>Anda bertanggung jawab penuh untuk menjaga kerahasiaan password dan aktivitas yang terjadi melalui akun Anda.</p>
          <h3 class="text-white font-semibold">3. Penyalahgunaan</h3>
          <p>Setiap bentuk manipulasi data kehadiran atau gaji akan dikenakan sanksi sesuai dengan peraturan perusahaan yang berlaku.</p>
        </div>
      </div>
    </div>
  </div>
</template>
