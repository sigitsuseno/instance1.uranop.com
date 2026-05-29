<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'

const showPrivacy = ref(false)
const showTerms = ref(false)
</script>

<template>
  <div class="bg-(--bg-main) text-(--text-main) selection:bg-(--primary) selection:text-white w-full min-h-screen">
    <nav class="flex items-center justify-between px-8 py-6 max-w-7xl mx-auto">
      <div class="flex items-center space-x-2">
        <div class="w-10 h-10 bg-(--primary) rounded-md flex items-center justify-center font-black text-white shadow-lg shadow-(--primary-glow)">
          U
        </div>
        <div class="flex flex-col">
          <span class="text-xl font-bold tracking-tighter text-(--text-main)">Uranop Enterprise</span>
        </div>
      </div>

      <div class="items-center space-x-6 hidden lg:flex">
        <RouterLink to="/login"
          class="px-5 py-2.5 bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) rounded-md text-sm font-bold hover:bg-(--primary) hover:text-white transition-all duration-300 hover:-translate-y-[1px] hover:shadow-lg">
          Sign In
        </RouterLink>
      </div>
    </nav>

    <main class="max-w-7xl mx-auto px-8 lg:h-[calc(100vh-205px)] grid lg:grid-cols-2 gap-12 items-center">
      <div class="space-y-8 pt-16">
        <h1 class="text-6xl md:text-7xl font-black text-(--text-main) leading-[1.1] tracking-tight">
          Aplikasi Payroll <br />
          <span class="text-(--primary) mt-8 block">
            Uranop Enterprise
          </span>
        </h1>
        <div class="flex flex-col sm:flex-row gap-4">
          <RouterLink to="/login"
            class="px-8 py-4 bg-(--primary) hover:bg-(--primary-hover) text-white rounded-md font-bold text-center transition-all duration-300 shadow-xl shadow-(--primary-glow) hover:-translate-y-1 hover:shadow-(--primary)/50 flex items-center justify-center space-x-3">
            <span>Masuk ke Dashboard</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </RouterLink>
        </div>
      </div>

      <div class="relative hidden lg:block">
        <div class="absolute inset-0 bg-(--primary-glow) blur-[120px] rounded-full"></div>
        <div class="relative bg-(--bg-card) border border-(--border-soft) rounded-3xl p-4 shadow-2xl transition-transform duration-500 hover:scale-[1.02]">
          <div class="bg-(--bg-sidebar) rounded-2xl p-8 border border-(--border-soft)">
            <div class="flex space-x-2 mb-6">
              <div class="w-3 h-3 rounded-full bg-(--danger)"></div>
              <div class="w-3 h-3 rounded-full bg-(--warning)"></div>
              <div class="w-3 h-3 rounded-full bg-(--success)"></div>
            </div>
            <div class="space-y-4">
              <div class="h-4 bg-(--bg-elevated) rounded-full w-3/4"></div>
              <div class="h-4 bg-(--bg-elevated) rounded-full w-full"></div>
              <div class="h-4 bg-(--bg-elevated) rounded-full w-5/6"></div>
              <div class="grid grid-cols-3 gap-4 mt-8">
                <div class="h-20 bg-(--primary-glow) rounded-md border border-(--primary)/20"></div>
                <div class="h-20 bg-(--success)/10 rounded-md border border-(--success)/20"></div>
                <div class="h-20 bg-(--warning)/10 rounded-md border border-(--warning)/20"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <footer class="border-t border-(--border-soft) py-12 mt-12 lg:mt-0">
      <div class="max-w-7xl mx-auto px-8 flex flex-col md:flex-row justify-between items-center text-(--text-muted) text-sm">
        <p>&copy; {{ new Date().getFullYear() }} Uranop Enterprise. All rights reserved.</p>
        <div class="flex space-x-8 mt-4 md:mt-0">
          <button @click="showPrivacy = true" class="hover:text-(--text-main) transition-colors duration-200">Kebijakan Privasi</button>
          <button @click="showTerms = true" class="hover:text-(--text-main) transition-colors duration-200">Syarat & Ketentuan</button>
        </div>
      </div>
    </footer>

    <div v-if="showPrivacy" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm" @click.self="showPrivacy = false">
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto p-8 relative shadow-2xl">
        <button @click="showPrivacy = false" class="absolute top-4 right-4 text-(--text-muted) hover:text-(--text-main) transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <h2 class="text-2xl font-bold text-(--text-main) mb-6">Kebijakan Privasi</h2>
        <div class="prose prose-sm text-(--text-muted) space-y-4">
          <p>Kami di Uranop Enterprise sangat menghargai privasi data Anda. Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, dan melindungi informasi pribadi Anda.</p>
          <h3 class="text-(--text-main) font-semibold">1. Pengumpulan Data</h3>
          <p>Kami mengumpulkan data yang diperlukan untuk operasional payroll, termasuk namun tidak terbatas pada nama, jabatan, gaji, dan data kehadiran.</p>
          <h3 class="text-(--text-main) font-semibold">2. Penggunaan Data</h3>
          <p>Data digunakan semata-mata untuk perhitungan gaji, pelaporan pajak, dan manajemen sumber daya manusia di lingkungan internal perusahaan.</p>
          <h3 class="text-(--text-main) font-semibold">3. Keamanan</h3>
          <p>Kami menggunakan standar enkripsi terkini untuk memastikan data Anda aman dari akses yang tidak sah.</p>
        </div>
      </div>
    </div>

    <div v-if="showTerms" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm" @click.self="showTerms = false">
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl max-w-2xl w-full max-h-[80vh] overflow-y-auto p-8 relative shadow-2xl">
        <button @click="showTerms = false" class="absolute top-4 right-4 text-(--text-muted) hover:text-(--text-main) transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <h2 class="text-2xl font-bold text-(--text-main) mb-6">Syarat & Ketentuan Penggunaan</h2>
        <div class="prose prose-sm text-(--text-muted) space-y-4">
          <p>Selamat datang di sistem Uranop Enterprise. Dengan menggunakan aplikasi ini, Anda setuju untuk mematuhi syarat berikut:</p>
          <h3 class="text-(--text-main) font-semibold">1. Hak Akses</h3>
          <p>Akses ke sistem ini hanya diberikan kepada karyawan resmi perusahaan.</p>
          <h3 class="text-(--text-main) font-semibold">2. Tanggung Jawab Akun</h3>
          <p>Anda bertanggung jawab penuh untuk menjaga kerahasiaan password dan aktivitas yang terjadi melalui akun Anda.</p>
          <h3 class="text-(--text-main) font-semibold">3. Penyalahgunaan</h3>
          <p>Setiap bentuk manipulasi data kehadiran atau gaji akan dikenakan sanksi sesuai dengan peraturan perusahaan yang berlaku.</p>
        </div>
      </div>
    </div>
  </div>
</template>
