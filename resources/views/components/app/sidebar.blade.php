<div class="min-w-fit">
    <!-- Sidebar backdrop (mobile only) -->
    <div
        class="fixed inset-0 bg-gray-900/30 z-40 lg:hidden lg:z-auto transition-opacity duration-200"
        :class="sidebarOpen ? 'opacity-100' : 'opacity-0 pointer-events-none'"
        aria-hidden="true"
        x-cloak
    ></div>

    <!-- Sidebar -->
    <div
        id="sidebar"
        class="flex lg:flex! flex-col absolute z-40 left-0 top-0 lg:static lg:left-auto lg:top-auto lg:translate-x-0 h-[100dvh] overflow-y-scroll lg:overflow-y-auto no-scrollbar w-64 lg:w-20 lg:sidebar-expanded:!w-64 2xl:w-64! shrink-0 bg-white dark:bg-gray-800 p-4 transition-all duration-200 ease-in-out {{ $variant === 'v2' ? 'border-r border-gray-200 dark:border-gray-700/60' : 'rounded-r-2xl shadow-xs' }}"
        :class="sidebarOpen ? 'max-lg:translate-x-0' : 'max-lg:-translate-x-64'"
        @click.outside="sidebarOpen = false"
        @keydown.escape.window="sidebarOpen = false"
    >

        <!-- Sidebar header -->
        <div class="flex justify-between mb-10 pr-3 sm:px-2">
            <!-- Close button -->
            <button class="lg:hidden text-gray-500 hover:text-gray-400" @click.stop="sidebarOpen = !sidebarOpen" aria-controls="sidebar" :aria-expanded="sidebarOpen">
                <span class="sr-only">Close sidebar</span>
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.7 18.7l1.4-1.4L7.8 13H20v-2H7.8l4.3-4.3-1.4-1.4L4 12z" />
                </svg>
            </button>
            <!-- Logo -->
            <a class="block" href="{{ route('dashboard') }}">
                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="192px" height="64px" viewBox="0 0 192 64" version="1.1">
                <g id="surface1">
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(5.490196%,58.039218%,55.686277%);fill-opacity:1;" d="M 27.5625 1.71875 L 29.34375 1.71875 L 30.75 2 L 31.3125 2.1875 L 32.25 2.09375 L 33.84375 1.90625 L 35.8125 2.1875 L 37.21875 2.65625 L 38.53125 3.3125 L 39.46875 4.0625 L 40.125 4.625 L 41.0625 5.84375 L 42 7.25 L 43.125 8.84375 L 43.96875 10.0625 L 44.15625 10.53125 L 38.625 10.625 L 38.34375 10.53125 L 38.25 26.5625 L 36.9375 26.5625 L 36.84375 10.625 L 24.84375 10.625 L 24.5625 10.53125 L 24.65625 10.71875 L 24.65625 49.625 L 36.84375 49.53125 L 36.9375 33.6875 L 50.4375 33.59375 L 50.53125 17.65625 L 50.90625 17.84375 L 51.9375 18.6875 L 53.15625 19.71875 L 54 20.375 L 55.21875 21.40625 L 56.34375 22.4375 L 57 23.1875 L 57.84375 24.59375 L 58.40625 26.28125 L 58.6875 27.78125 L 58.6875 28.71875 L 58.5 30.03125 L 58.125 31.53125 L 58.3125 32.46875 L 58.3125 34.90625 L 58.03125 36.3125 L 57.5625 37.53125 L 56.71875 38.84375 L 55.875 39.78125 L 55.21875 40.4375 L 53.625 41.5625 L 51.375 43.0625 L 50.71875 43.53125 L 50.53125 43.4375 L 50.53125 34.53125 L 38.34375 34.625 L 38.34375 49.53125 L 42.09375 49.53125 L 43.40625 49.625 L 43.21875 50 L 42.375 50.9375 L 41.53125 51.96875 L 40.3125 53.375 L 39.5625 54.40625 L 38.625 55.4375 L 37.96875 56.1875 L 37.03125 56.9375 L 35.8125 57.6875 L 34.59375 58.15625 L 33.46875 58.4375 L 32.53125 58.53125 L 31.5 58.53125 L 29.8125 58.25 L 28.78125 57.96875 L 27.28125 58.25 L 25.96875 58.25 L 24.65625 58.0625 L 23.15625 57.59375 L 21.9375 56.9375 L 20.90625 56.1875 L 20.90625 56 L 20.53125 55.8125 L 20.25 55.53125 L 20.25 55.34375 L 20.0625 55.34375 L 20.0625 55.15625 L 19.875 55.15625 L 19.21875 54.125 L 18.5625 53.1875 L 17.8125 52.15625 L 17.15625 51.125 L 16.40625 50.09375 L 16.21875 49.625 L 17.15625 49.53125 L 23.0625 49.53125 L 23.15625 18.59375 L 10.875 18.59375 L 10.875 43.4375 L 10.40625 43.15625 L 9 41.9375 L 7.96875 41.09375 L 7.125 40.34375 L 6.5625 39.96875 L 5.15625 38.75 L 4.21875 37.90625 L 3.46875 37.0625 L 2.71875 35.84375 L 2.25 34.71875 L 1.78125 32.75 L 1.78125 31.0625 L 2.25 29.09375 L 2.0625 27.03125 L 2.0625 25.90625 L 2.34375 24.125 L 2.8125 22.90625 L 3.375 21.875 L 4.125 20.84375 L 4.96875 20 L 6 19.25 L 6.9375 18.59375 L 8.53125 17.46875 L 9.9375 16.53125 L 10.125 16.53125 L 10.125 16.34375 L 10.875 15.96875 L 10.875 17.75 L 23.0625 17.65625 L 23.0625 10.625 L 17.0625 10.53125 L 17.625 9.78125 L 18.75 8.46875 L 19.59375 7.4375 L 20.4375 6.5 L 21.46875 5.1875 L 22.3125 4.25 L 22.96875 3.59375 L 24.375 2.65625 L 25.5 2.1875 L 26.8125 1.8125 Z M 27.5625 1.71875 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(3.137255%,56.862748%,54.509807%);fill-opacity:1;" d="M 74.625 21.03125 L 80.34375 21.03125 L 80.4375 30.875 L 84.09375 25.8125 L 85.03125 24.5 L 87.46875 21.125 L 87.5625 21.03125 L 94.125 21.03125 L 93.75 21.6875 L 92.90625 22.71875 L 88.21875 28.8125 L 86.25 31.34375 L 86.34375 31.8125 L 87.28125 33.125 L 88.3125 34.625 L 89.625 36.5 L 91.125 38.65625 L 92.15625 40.15625 L 93.46875 42.03125 L 94.5 43.53125 L 94.5 43.625 L 87.5625 43.625 L 85.5 40.53125 L 82.78125 36.5 L 82.03125 35.375 L 81.5625 34.71875 L 81.5625 34.53125 L 81.09375 35 L 80.4375 35.9375 L 80.34375 43.625 L 74.625 43.625 Z M 74.625 21.03125 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(75.294119%,75.294119%,75.294119%);fill-opacity:1;" d="M 102.84375 26 L 104.25 26 L 105.84375 26.1875 L 107.25 26.5625 L 108.75 27.3125 L 109.78125 28.0625 L 110.71875 29.09375 L 111.46875 30.3125 L 112.03125 31.8125 L 112.3125 33.125 L 112.40625 33.96875 L 112.40625 36.21875 L 112.125 38 L 111.65625 39.40625 L 111.09375 40.53125 L 110.15625 41.75 L 109.21875 42.59375 L 107.71875 43.4375 L 106.21875 43.90625 L 105.1875 44.09375 L 102 44.09375 L 100.59375 43.8125 L 99.28125 43.34375 L 98.25 42.78125 L 97.03125 41.75 L 96.28125 40.8125 L 95.53125 39.5 L 95.0625 38.09375 L 94.78125 36.5 L 94.78125 33.6875 L 95.0625 32.09375 L 95.53125 30.6875 L 96.1875 29.46875 L 96.9375 28.53125 L 97.59375 27.875 L 98.53125 27.21875 L 99.65625 26.65625 L 101.25 26.1875 Z M 103.125 29.84375 L 102.28125 30.03125 L 101.4375 30.59375 L 100.875 31.4375 L 100.5 32.375 L 100.3125 33.21875 L 100.21875 34.0625 L 100.21875 36.21875 L 100.40625 37.53125 L 100.875 38.75 L 101.4375 39.59375 L 102.1875 40.0625 L 102.75 40.25 L 103.78125 40.34375 L 104.71875 40.15625 L 105.46875 39.78125 L 106.21875 38.9375 L 106.6875 37.8125 L 106.875 36.96875 L 106.96875 35.84375 L 106.96875 34.4375 L 106.78125 32.84375 L 106.40625 31.625 L 105.84375 30.78125 L 105.09375 30.125 L 103.96875 29.84375 Z M 103.125 29.84375 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(75.294119%,75.294119%,75.294119%);fill-opacity:1;" d="M 168.46875 26 L 169.875 26 L 171.46875 26.1875 L 172.875 26.5625 L 174.375 27.3125 L 175.21875 27.96875 L 175.6875 28.34375 L 176.4375 29.28125 L 177.1875 30.59375 L 177.75 32.28125 L 177.9375 33.3125 L 178.03125 34.34375 L 178.03125 35.84375 L 177.84375 37.4375 L 177.46875 38.84375 L 177 39.96875 L 176.15625 41.28125 L 174.9375 42.5 L 173.53125 43.34375 L 171.84375 43.90625 L 170.71875 44.09375 L 167.625 44.09375 L 166.125 43.8125 L 164.90625 43.34375 L 163.875 42.78125 L 162.9375 42.03125 L 162.28125 41.375 L 161.4375 40.0625 L 160.78125 38.5625 L 160.40625 36.6875 L 160.3125 34.71875 L 160.5 32.84375 L 160.96875 31.0625 L 161.71875 29.5625 L 162.46875 28.625 L 163.3125 27.78125 L 164.625 26.9375 L 165.75 26.46875 L 167.4375 26.09375 Z M 168.75 29.84375 L 167.90625 30.03125 L 167.0625 30.59375 L 166.40625 31.53125 L 165.9375 33.03125 L 165.84375 33.78125 L 165.84375 36.5 L 166.125 37.90625 L 166.59375 39.03125 L 167.15625 39.6875 L 168 40.15625 L 169.03125 40.34375 L 169.96875 40.25 L 170.90625 39.875 L 171.65625 39.21875 L 172.125 38.28125 L 172.5 36.78125 L 172.5 33.59375 L 172.21875 32.1875 L 171.5625 30.875 L 170.8125 30.21875 L 170.15625 29.9375 L 169.59375 29.84375 Z M 168.75 29.84375 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(75.294119%,75.294119%,75.294119%);fill-opacity:1;" d="M 125.34375 26 L 126.375 26 L 127.875 26.28125 L 129 26.84375 L 129.75 27.5 L 130.40625 28.4375 L 130.875 29.5625 L 131.15625 30.875 L 131.25 32.28125 L 131.25 43.53125 L 131.15625 43.625 L 125.8125 43.625 L 125.71875 33.125 L 125.53125 31.8125 L 125.15625 31.0625 L 124.40625 30.59375 L 124.03125 30.5 L 122.625 30.5 L 121.3125 30.96875 L 120.75 31.34375 L 120.5625 31.34375 L 120.5625 43.625 L 115.21875 43.625 L 115.21875 26.5625 L 120.5625 26.5625 L 120.65625 28.25 L 121.59375 27.5 L 122.8125 26.75 L 123.84375 26.28125 Z M 125.34375 26 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(75.294119%,75.294119%,75.294119%);fill-opacity:1;" d="M 135.46875 21.6875 L 140.8125 21.6875 L 140.8125 26.5625 L 145.875 26.5625 L 145.96875 26.65625 L 145.96875 30.21875 L 145.875 30.3125 L 140.8125 30.3125 L 140.90625 38.375 L 141.1875 39.3125 L 141.5625 39.78125 L 142.03125 40.0625 L 143.0625 40.25 L 144.09375 40.15625 L 145.3125 39.78125 L 145.875 39.78125 L 145.96875 40.0625 L 145.96875 43.53125 L 144.9375 43.8125 L 143.625 44 L 140.15625 44 L 138.75 43.71875 L 137.625 43.25 L 136.78125 42.59375 L 136.21875 41.84375 L 135.75 40.8125 L 135.46875 39.3125 L 135.46875 30.3125 L 133.40625 30.3125 L 133.40625 26.5625 L 135.46875 26.5625 Z M 135.46875 21.6875 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(75.294119%,75.294119%,75.294119%);fill-opacity:1;" d="M 181.78125 19.90625 L 183.28125 19.90625 L 186.1875 20 L 186.09375 43.625 L 180.84375 43.625 L 180.84375 20 Z M 181.78125 19.90625 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(75.294119%,75.294119%,75.294119%);fill-opacity:1;" d="M 148.03125 26.5625 L 153.375 26.5625 L 153.46875 28.90625 L 154.875 27.78125 L 156.1875 27.03125 L 157.3125 26.65625 L 157.78125 26.5625 L 159.46875 26.5625 L 159.5625 26.65625 L 159.5625 31.625 L 159.1875 31.71875 L 157.6875 31.53125 L 156.46875 31.53125 L 154.96875 31.8125 L 153.46875 32.28125 L 153.375 43.625 L 148.03125 43.625 Z M 148.03125 26.5625 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(23.921569%,23.529412%,30.980393%);fill-opacity:1;" d="M 64.96875 26.5625 L 70.3125 26.5625 L 70.3125 43.625 L 64.96875 43.625 Z M 64.96875 26.5625 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(25.882354%,24.705882%,31.764707%);fill-opacity:1;" d="M 41.71875 49.625 L 43.40625 49.625 L 43.21875 50 L 42.375 50.9375 L 41.53125 51.96875 L 40.3125 53.375 L 39.5625 54.40625 L 38.625 55.4375 L 37.96875 56.1875 L 37.03125 56.9375 L 35.8125 57.6875 L 34.59375 58.15625 L 33.46875 58.4375 L 32.53125 58.53125 L 31.5 58.53125 L 29.8125 58.25 L 29.15625 57.96875 L 29.0625 57.78125 L 30.28125 57.3125 L 31.3125 56.75 L 33.46875 55.25 L 36.75 53 L 38.53125 51.78125 L 40.6875 50.28125 Z M 41.71875 49.625 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(25.882354%,24.705882%,32.156864%);fill-opacity:1;" d="M 2.34375 29 L 2.53125 29 L 2.90625 30.03125 L 3.5625 31.25 L 4.59375 32.75 L 5.90625 34.625 L 7.3125 36.6875 L 8.34375 38.1875 L 9.84375 40.34375 L 10.78125 41.65625 L 10.875 43.4375 L 10.40625 43.15625 L 9 41.9375 L 7.96875 41.09375 L 7.125 40.34375 L 6.5625 39.96875 L 5.15625 38.75 L 4.21875 37.90625 L 3.46875 37.0625 L 2.71875 35.84375 L 2.25 34.71875 L 1.78125 32.75 L 1.78125 31.0625 L 2.25 29.09375 Z M 2.34375 29 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(25.882354%,25.098041%,32.156864%);fill-opacity:1;" d="M 27.5625 1.71875 L 29.34375 1.71875 L 30.75 2 L 31.40625 2.28125 L 31.40625 2.46875 L 30.28125 2.84375 L 29.0625 3.5 L 27.46875 4.625 L 25.40625 6.03125 L 22.40625 8.09375 L 20.34375 9.5 L 19.03125 10.4375 L 17.0625 10.53125 L 17.625 9.78125 L 18.75 8.46875 L 19.59375 7.4375 L 20.4375 6.5 L 21.46875 5.1875 L 22.3125 4.25 L 22.96875 3.59375 L 24.375 2.65625 L 25.5 2.1875 L 26.8125 1.8125 Z M 27.5625 1.71875 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(21.568628%,73.333335%,70.19608%);fill-opacity:1;" d="M 33.5625 1.90625 L 34.6875 2 L 36.1875 2.28125 L 37.40625 2.75 L 38.53125 3.3125 L 39.46875 4.0625 L 40.125 4.625 L 41.0625 5.84375 L 42 7.25 L 43.125 8.84375 L 43.96875 10.0625 L 44.15625 10.53125 L 38.625 10.625 L 38.34375 10.53125 L 38.25 26.5625 L 36.9375 26.5625 L 36.9375 26.46875 L 38.15625 26.46875 L 38.15625 10.53125 L 38.25 10.4375 L 41.71875 10.34375 L 40.3125 9.125 L 39.28125 8.28125 L 38.0625 7.25 L 37.125 6.5 L 35.90625 5.46875 L 34.875 4.625 L 33.375 3.40625 L 32.0625 2.65625 L 31.875 2.5625 L 31.875 2.375 L 31.40625 2.375 L 31.40625 2.28125 L 32.90625 2 Z M 33.5625 1.90625 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(25.882354%,24.705882%,32.156864%);fill-opacity:1;" d="M 50.625 17.75 L 51.09375 17.9375 L 52.125 18.875 L 53.4375 19.90625 L 54.65625 20.9375 L 55.6875 21.78125 L 56.71875 22.8125 L 57.65625 24.21875 L 58.125 25.34375 L 58.59375 27.03125 L 58.6875 27.78125 L 58.6875 28.71875 L 58.5 30.03125 L 58.125 31.34375 L 57.9375 31.34375 L 57.46875 30.125 L 56.625 28.625 L 55.40625 26.9375 L 54.65625 25.8125 L 53.4375 24.03125 L 52.40625 22.53125 L 51.09375 20.65625 L 50.625 20 Z M 50.625 17.75 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(21.960784%,73.725492%,70.19608%);fill-opacity:1;" d="M 10.78125 15.96875 L 10.875 15.96875 L 10.96875 17.9375 L 9.9375 19.15625 L 9.09375 20.1875 L 8.0625 21.40625 L 7.21875 22.4375 L 6.1875 23.65625 L 5.34375 24.6875 L 4.3125 25.90625 L 3.375 27.125 L 2.53125 28.53125 L 2.4375 29 L 2.34375 29 L 2.15625 28.0625 L 2.0625 27.03125 L 2.0625 25.90625 L 2.34375 24.125 L 2.8125 22.90625 L 3.375 21.875 L 4.125 20.84375 L 4.96875 20 L 6 19.25 L 6.9375 18.59375 L 8.53125 17.46875 L 9.9375 16.53125 L 10.125 16.53125 L 10.125 16.34375 Z M 10.78125 15.96875 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(22.745098%,74.117649%,70.588237%);fill-opacity:1;" d="M 17.15625 49.53125 L 20.25 49.53125 L 20.25 49.625 L 18.5625 49.8125 L 19.5 50.5625 L 20.8125 51.6875 L 21.84375 52.53125 L 23.0625 53.5625 L 24.375 54.59375 L 25.78125 55.8125 L 27.09375 56.84375 L 28.40625 57.59375 L 28.78125 57.96875 L 27.28125 58.25 L 25.96875 58.25 L 24.65625 58.0625 L 23.15625 57.59375 L 21.9375 56.9375 L 20.90625 56.1875 L 20.90625 56 L 20.53125 55.8125 L 20.25 55.53125 L 20.25 55.34375 L 20.0625 55.34375 L 20.0625 55.15625 L 19.875 55.15625 L 19.21875 54.125 L 18.5625 53.1875 L 17.8125 52.15625 L 17.15625 51.125 L 16.40625 50.09375 L 16.21875 49.625 Z M 17.15625 49.53125 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(23.529412%,74.117649%,70.980394%);fill-opacity:1;" d="M 57.9375 31.34375 L 58.125 31.53125 L 58.3125 32.46875 L 58.3125 34.90625 L 58.03125 36.3125 L 57.5625 37.53125 L 56.71875 38.84375 L 55.875 39.78125 L 55.21875 40.4375 L 53.625 41.5625 L 51.375 43.0625 L 50.71875 43.53125 L 50.53125 43.4375 L 50.53125 34.53125 L 50.625 34.53125 L 50.71875 40.8125 L 51.75 39.59375 L 52.5 38.65625 L 53.53125 37.4375 L 54.375 36.40625 L 55.40625 35.1875 L 56.25 34.15625 L 57.1875 32.9375 L 57.84375 31.71875 Z M 57.9375 31.34375 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(24.313726%,23.529412%,30.980393%);fill-opacity:1;" d="M 66 19.90625 L 70.40625 20 L 70.40625 24.125 L 64.78125 24.125 L 64.78125 20 Z M 66 19.90625 "></path>
                <path style=" stroke:none;fill-rule:nonzero;fill:rgb(13.333334%,63.529414%,60.784316%);fill-opacity:1;" d="M 56.15625 39.125 L 56.25 39.40625 L 55.5 40.15625 L 55.21875 40.0625 Z M 56.15625 39.125 "></path>
                </g>
                </svg>               
            </a>  
        </div>

        <!-- Links -->
        <div class="space-y-8">
            <!-- Pages group -->
            <div>
                <h3 class="text-xs uppercase text-gray-400 dark:text-gray-500 font-semibold pl-3">
                    <span class="hidden lg:block lg:sidebar-expanded:hidden 2xl:hidden text-center w-6" aria-hidden="true">•••</span>
                    <span class="lg:hidden lg:sidebar-expanded:block 2xl:block">Pages</span>
                </h3>
                <ul class="mt-3">
                    <!-- Dashboard -->
                    @php
                        $seg1 = Request::segment(1);
                    @endphp

                    {{-- 1 Dashboard --}}
                    <li class="mb-1 last:mb-0">
                        <a class="block text-gray-800 dark:text-gray-100 truncate transition hover:text-gray-900 dark:hover:text-white
                            @if(Route::is('dashboard')){{ 'text-violet-500!' }}@endif"
                            href="{{ route('dashboard') }}">
                            <div class="flex items-center">
                                {{-- Icon: dashboard --}}
                                <svg class="shrink-0 fill-current @if(Route::is('dashboard')){{ 'text-violet-500' }}@else{{ 'text-gray-400 dark:text-gray-500' }}@endif"
                                    xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                                    <path d="M5.936.278A7.983 7.983 0 0 1 8 0a8 8 0 1 1-8 8c0-.722.104-1.413.278-2.064a1 1 0 1 1 1.932.516A5.99 5.99 0 0 0 2 8a6 6 0 1 0 6-6c-.53 0-1.045.076-1.548.21A1 1 0 1 1 5.936.278Z" />
                                    <path d="M6.068 7.482A2.003 2.003 0 0 0 8 10a2 2 0 1 0-.518-3.932L3.707 2.293a1 1 0 0 0-1.414 1.414l3.775 3.775Z" />
                                </svg>
                                <span class="text-sm font-medium ml-4 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                                    Dashboard
                                </span>
                            </div>
                        </a>
                    </li>

                    {{-- 2 Catálogos --}}
                    <li class="pl-4 pr-3 py-2 rounded-lg mb-1 last:mb-0 bg-linear-to-r
                        @if($seg1 === 'catalogos'){{ 'from-violet-500/[0.12] dark:from-violet-500/[0.24] to-violet-500/[0.04]' }}@endif"
                        x-data="{ open: {{ $seg1 === 'catalogos' ? 1 : 0 }} }">

                        <a class="block text-gray-800 dark:text-gray-100 truncate transition
                            @if($seg1 !== 'catalogos'){{ 'hover:text-gray-900 dark:hover:text-white' }}@endif"
                            href="#0" @click.prevent="open = !open; sidebarExpanded = true">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    {{-- Icon: catálogo --}}
                                    <svg class="shrink-0 fill-current @if($seg1 === 'catalogos'){{ 'text-violet-500' }}@else{{ 'text-gray-400 dark:text-gray-500' }}@endif"
                                        xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                                        <path d="M9 6.855A3.502 3.502 0 0 0 8 0a3.5 3.5 0 0 0-1 6.855v1.656L5.534 9.65a3.5 3.5 0 1 0 1.229 1.578L8 10.267l1.238.962a3.5 3.5 0 1 0 1.229-1.578L9 8.511V6.855Z" />
                                    </svg>
                                    <span class="text-sm font-medium ml-4 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                                        Catálogos
                                    </span>
                                </div>
                                <div class="flex shrink-0 ml-2 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                                    <svg class="w-3 h-3 shrink-0 ml-1 fill-current text-gray-400 dark:text-gray-500"
                                        :class="open ? 'rotate-180' : 'rotate-0'" viewBox="0 0 12 12">
                                        <path d="M5.9 11.4L.5 6l1.4-1.4 4 4 4-4L11.3 6z" />
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <div class="lg:hidden lg:sidebar-expanded:block 2xl:block">
                            <ul class="pl-8 mt-1 @if($seg1 !== 'catalogos'){{ 'hidden' }}@endif" :class="open ? 'block!' : 'hidden'">
                                <li class="mb-1 last:mb-0">
                                    <a class="block text-gray-500/90 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition truncate
                                        @if(request()->routeIs('clientes.*')){{ 'text-violet-500!' }}@endif"
                                        href="{{ route('clientes.index') }}">
                                        <span class="text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Clientes</span>
                                    </a>
                                </li>

                                <li class="mb-1 last:mb-0">
                                    <a class="block text-gray-500/90 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition truncate
                                        @if(request()->routeIs('productos.*')){{ 'text-violet-500!' }}@endif"
                                        href="{{ route('productos.index') }}">
                                        <span class="text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Productos</span>
                                    </a>
                                </li>

                                <li class="mb-1 last:mb-0">
                                    <a class="block text-gray-500/90 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition truncate
                                        @if(request()->routeIs('empleados.*')){{ 'text-violet-500!' }}@endif"
                                        href="{{ route('empleados.index') }}">
                                        <span class="text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Empleados</span>
                                    </a>
                                </li>

                                <li class="mb-1 last:mb-0">
                                    <a class="block text-gray-500/90 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition truncate
                                        @if(request()->routeIs('folios.*')){{ 'text-violet-500!' }}@endif"
                                        href="{{ route('folios.index') }}">
                                        <span class="text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Folios</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    {{-- 3) Facturas / Documentos --}}
                    <li class="pl-4 pr-3 py-2 rounded-lg mb-1 last:mb-0 bg-linear-to-r
                        @if($seg1 === 'documentos'){{ 'from-violet-500/[0.12] dark:from-violet-500/[0.24] to-violet-500/[0.04]' }}@endif"
                        x-data="{ open: {{ $seg1 === 'documentos' ? 1 : 0 }} }">

                        <a class="block text-gray-800 dark:text-gray-100 truncate transition
                            @if($seg1 !== 'documentos'){{ 'hover:text-gray-900 dark:hover:text-white' }}@endif"
                            href="#0" @click.prevent="open = !open; sidebarExpanded = true">

                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    {{-- Icon: documentos --}}
                                    <svg class="shrink-0 fill-current @if($seg1 === 'documentos'){{ 'text-violet-500' }}@else{{ 'text-gray-400 dark:text-gray-500' }}@endif"
                                        xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                                        <path d="M14 0H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2ZM5 2h9v2H5V2Zm0 4h9v2H5V6Zm0 4h6v2H5v-2Z" />
                                    </svg>

                                    <span class="text-sm font-medium ml-4 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                                        Facturas / Documentos
                                    </span>
                                </div>

                                <div class="flex shrink-0 ml-2 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                                    <svg class="w-3 h-3 shrink-0 ml-1 fill-current text-gray-400 dark:text-gray-500"
                                        :class="open ? 'rotate-180' : 'rotate-0'" viewBox="0 0 12 12">
                                        <path d="M5.9 11.4L.5 6l1.4-1.4 4 4 4-4L11.3 6z" />
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <div class="lg:hidden lg:sidebar-expanded:block 2xl:block">
                            <ul class="pl-8 mt-1 @if($seg1 !== 'documentos'){{ 'hidden' }}@endif" :class="open ? 'block!' : 'hidden'">

                                <li class="mb-1 last:mb-0">
                                    <a class="block text-gray-500/90 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition truncate
                                        @if(request()->routeIs('facturas.*')){{ 'text-violet-500!' }}@endif"
                                        href="{{ route('facturas.index') }}">
                                        <span class="text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Facturas</span>
                                    </a>
                                </li>

                                <li class="mb-1 last:mb-0">
                                    <a class="block text-gray-500/90 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition truncate
                                        @if(request()->routeIs('complementos.*')){{ 'text-violet-500!' }}@endif"
                                        href="{{ route('complementos.index') }}">
                                        <span class="text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Complementos de pago</span>
                                    </a>
                                </li>

                                <li class="mb-1 last:mb-0">
                                    <a class="block text-gray-500/90 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition truncate
                                        @if(request()->routeIs('nominas.*')){{ 'text-violet-500!' }}@endif"
                                        href="{{ route('nominas.index') }}">
                                        <span class="text-sm font-medium lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">Nóminas</span>
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>

                    {{-- 4) Reportes --}}
                    <li class="mb-1 last:mb-0">
                        <a class="block text-gray-800 dark:text-gray-100 truncate transition hover:text-gray-900 dark:hover:text-white
                            @if(request()->routeIs('reportes.*')){{ 'text-violet-500!' }}@endif"
                            href="{{ route('reportes.index') }}">
                            <div class="flex items-center">
                                {{-- Icon: reportes --}}
                                <svg class="shrink-0 fill-current @if(request()->routeIs('reportes.*')){{ 'text-violet-500' }}@else{{ 'text-gray-400 dark:text-gray-500' }}@endif"
                                    xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                                    <path d="M1 13V3a1 1 0 0 1 2 0v10a1 1 0 0 1-2 0Zm4 0V6a1 1 0 0 1 2 0v7a1 1 0 0 1-2 0Zm4 0V8a1 1 0 0 1 2 0v5a1 1 0 0 1-2 0Zm4 0V4a1 1 0 0 1 2 0v9a1 1 0 0 1-2 0Z"/>
                                </svg>
                                <span class="text-sm font-medium ml-4 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                                    Reportes
                                </span>
                            </div>
                        </a>
                    </li>

                    {{-- 5) Configuración --}}
                    <li class="mb-1 last:mb-0">
                        <a class="block text-gray-800 dark:text-gray-100 truncate transition hover:text-gray-900 dark:hover:text-white
                            @if(request()->routeIs('configuracion.*')){{ 'text-violet-500!' }}@endif"
                            href="{{ route('configuracion.index') }}">
                            <div class="flex items-center">
                                {{-- Icon: configuración --}}
                                <svg class="shrink-0 fill-current @if(request()->routeIs('configuracion.*')){{ 'text-violet-500' }}@else{{ 'text-gray-400 dark:text-gray-500' }}@endif"
                                    xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                                    <path d="M9.667 0 10 2.2l1.4.6 1.933-1.067 1.333 2.31-1.733 1.3.133 1.6 1.6 1.1-1.333 2.31-2-.9-1.4.6L9.667 16H7l-.333-2.2-1.4-.6-2 .9L1.933 11.8l1.6-1.1.133-1.6-1.733-1.3L3.267 2.49 5.2 3.556l1.4-.6L7 0h2.667ZM8.333 5.2A2.8 2.8 0 1 0 8.333 10.8 2.8 2.8 0 0 0 8.333 5.2Z"/>
                                </svg>
                                <span class="text-sm font-medium ml-4 lg:opacity-0 lg:sidebar-expanded:opacity-100 2xl:opacity-100 duration-200">
                                    Configuración
                                </span>
                            </div>
                        </a>
                    </li>

                </ul>
            </div>
        </div>

        <!-- Expand / collapse button -->
        <div class="pt-3 hidden lg:inline-flex 2xl:hidden justify-end mt-auto">
            <div class="w-12 pl-4 pr-3 py-2">
                <button class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 transition-colors" @click="sidebarExpanded = !sidebarExpanded">
                    <span class="sr-only">Expand / collapse sidebar</span>
                    <svg class="shrink-0 fill-current text-gray-400 dark:text-gray-500 sidebar-expanded:rotate-180" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                        <path d="M15 16a1 1 0 0 1-1-1V1a1 1 0 1 1 2 0v14a1 1 0 0 1-1 1ZM8.586 7H1a1 1 0 1 0 0 2h7.586l-2.793 2.793a1 1 0 1 0 1.414 1.414l4.5-4.5A.997.997 0 0 0 12 8.01M11.924 7.617a.997.997 0 0 0-.217-.324l-4.5-4.5a1 1 0 0 0-1.414 1.414L8.586 7M12 7.99a.996.996 0 0 0-.076-.373Z" />
                    </svg>
                </button>
            </div>
        </div>

    </div>
</div>