<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Pitch Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }
        html {
            scroll-behavior: smooth;
        }
        .gradient-text {
            background: linear-gradient(to right, #4f46e5, #9333ea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        /* Dark mode gradient text adjustment */
        .dark .gradient-text {
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 dark:bg-gray-950 dark:text-gray-100 flex flex-col min-h-screen transition-colors duration-300" id="top">

    <!-- Navigation -->
    <!-- <nav class="bg-white dark:bg-gray-900 shadow-sm fixed w-full z-50 transition-colors duration-300 border-b dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="#top" class="flex-shrink-0 flex items-center gap-2">
                        <i data-lucide="layers" class="h-8 w-8 text-indigo-600 dark:text-indigo-400"></i>
                        <span class="font-bold text-2xl tracking-tight text-gray-900 dark:text-white">Pitch Hub</span>
                    </a>
                </div>
                
              Theme Toggle Button (Replaces Menu) -->
                <!-- <div class="flex items-center">
                    <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 transition-colors focus:outline-none" aria-label="Toggle Dark Mode">
                        <i data-lucide="moon" class="w-6 h-6 hidden dark:block"></i>
                        <i data-lucide="sun" class="w-6 h-6 block dark:hidden"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav> -->
        <?php require_once 'includes/header.php'?>
    <!-- Hero Section -->
    <header class="pt-32 pb-16 md:pt-40 md:pb-24 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto text-center">
        <h1 class="text-4xl md:text-6xl font-bold tracking-tight text-gray-900 dark:text-white mb-6">
            Empowering Creators <br class="hidden md:block" /> at <span class="gradient-text">Pitch Hub</span>
        </h1>
        <p class="mt-4 text-xl text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
            We are the central hub where ideas meet execution. Whether you're pitching a startup or showcasing art, we provide the platform to make it happen.
        </p>
    </header>

    <!-- Our Mission Section -->
    <section id="mission" class="py-16 bg-white dark:bg-gray-900 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div class="relative">
                    <div class="absolute -top-4 -left-4 w-24 h-24 bg-purple-100 dark:bg-purple-900/30 rounded-full z-0"></div>
                    <div class="absolute -bottom-4 -right-4 w-32 h-32 bg-indigo-100 dark:bg-indigo-900/30 rounded-full z-0"></div>
                    <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                         alt="Team collaborating" 
                         class="relative z-10 rounded-2xl shadow-xl w-full object-cover h-96 brightness-100 dark:brightness-90">
                </div>
                <div>
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">Our Mission</h2>
                    <p class="text-lg text-gray-600 dark:text-gray-300 mb-6 leading-relaxed">
                        At Pitch Hub, we believe that every great idea deserves a chance to be heard. Our mission is to democratize visibility for creators, entrepreneurs, and visionaries.
                    </p>
                    <p class="text-lg text-gray-600 dark:text-gray-300 mb-8 leading-relaxed">
                        We build tools that simplify the complex process of connecting talent with opportunity. From seamless portfolio management to networking algorithms, we are the bridge to your next big break.
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-100 dark:border-gray-700">
                            <h3 class="font-bold text-2xl text-indigo-600 dark:text-indigo-400">50K+</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Active Users</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-100 dark:border-gray-700">
                            <h3 class="font-bold text-2xl text-indigo-600 dark:text-indigo-400">120+</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Countries Reached</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Values -->
    <section id="values" class="py-20 bg-gray-50 dark:bg-gray-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Core Values</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-400">The principles that drive everything we do.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white dark:bg-gray-900 p-8 rounded-xl shadow-sm hover:shadow-md transition duration-300 border border-gray-100 dark:border-gray-800">
                    <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center mb-6 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="lightbulb" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Innovation First</h3>
                    <p class="text-gray-600 dark:text-gray-400">We constantly push boundaries to provide the most cutting-edge tools for our community.</p>
                </div>
                <div class="bg-white dark:bg-gray-900 p-8 rounded-xl shadow-sm hover:shadow-md transition duration-300 border border-gray-100 dark:border-gray-800">
                    <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center mb-6 text-purple-600 dark:text-purple-400">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Community Driven</h3>
                    <p class="text-gray-600 dark:text-gray-400">Our users are our heartbeat. Every feature we build starts with listening to you.</p>
                </div>
                <div class="bg-white dark:bg-gray-900 p-8 rounded-xl shadow-sm hover:shadow-md transition duration-300 border border-gray-100 dark:border-gray-800">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center mb-6 text-blue-600 dark:text-blue-400">
                        <i data-lucide="shield-check" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">Trust & Transparency</h3>
                    <p class="text-gray-600 dark:text-gray-400">We build relationships on honesty. Your data and your creative rights are always protected.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section id="team" class="py-20 bg-white dark:bg-gray-900 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Meet the Minds</h2>
                <p class="mt-4 text-gray-600 dark:text-gray-400">The passionate team behind Pitch Hub.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Team Member 1 -->
                <div class="text-center group">
                    <div class="relative inline-block mb-4 overflow-hidden rounded-full w-40 h-40 ring-4 ring-transparent group-hover:ring-indigo-100 dark:group-hover:ring-indigo-900 transition-all">
                        <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="CEO" class="w-full h-full object-cover transition duration-300 group-hover:scale-110">
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Alex Rivera</h3>
                    <p class="text-indigo-600 dark:text-indigo-400 font-medium text-sm">Founder & CEO</p>
                </div>
                <!-- Team Member 2 -->
                <div class="text-center group">
                    <div class="relative inline-block mb-4 overflow-hidden rounded-full w-40 h-40 ring-4 ring-transparent group-hover:ring-indigo-100 dark:group-hover:ring-indigo-900 transition-all">
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="CTO" class="w-full h-full object-cover transition duration-300 group-hover:scale-110">
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Sarah Jenkins</h3>
                    <p class="text-indigo-600 dark:text-indigo-400 font-medium text-sm">Head of Product</p>
                </div>
                <!-- Team Member 3 -->
                <div class="text-center group">
                    <div class="relative inline-block mb-4 overflow-hidden rounded-full w-40 h-40 ring-4 ring-transparent group-hover:ring-indigo-100 dark:group-hover:ring-indigo-900 transition-all">
                        <img src="https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Designer" class="w-full h-full object-cover transition duration-300 group-hover:scale-110">
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Marcus Chen</h3>
                    <p class="text-indigo-600 dark:text-indigo-400 font-medium text-sm">Lead Designer</p>
                </div>
                <!-- Team Member 4 -->
                <div class="text-center group">
                    <div class="relative inline-block mb-4 overflow-hidden rounded-full w-40 h-40 ring-4 ring-transparent group-hover:ring-indigo-100 dark:group-hover:ring-indigo-900 transition-all">
                        <img src="https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Marketing" class="w-full h-full object-cover transition duration-300 group-hover:scale-110">
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Emily Davis</h3>
                    <p class="text-indigo-600 dark:text-indigo-400 font-medium text-sm">Community Manager</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action / Contact -->
    <section id="contact" class="py-20 bg-gray-900 dark:bg-black text-white transition-colors duration-300">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to join the hub?</h2>
            <p class="text-gray-300 text-lg mb-8">Join thousands of creators and businesses transforming their ideas into reality today.</p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <button class="bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-700 dark:hover:bg-indigo-600 text-white font-bold py-3 px-8 rounded-lg transition duration-300">
                    Join Pitch Hub
                </button>
                <a href="mailto:smartpitchhub@gmail.com" class="bg-transparent border border-white hover:bg-white hover:text-gray-900 dark:hover:text-black text-white font-bold py-3 px-8 rounded-lg transition duration-300 inline-block">
                    Contact
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <!-- <footer class="bg-gray-50 dark:bg-gray-950 border-t border-gray-200 dark:border-gray-800 pt-12 pb-8 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <div class="col-span-1 md:col-span-1">
                    <div class="flex items-center gap-2 mb-4">
                        <i data-lucide="layers" class="h-6 w-6 text-indigo-600 dark:text-indigo-400"></i>
                        <span class="font-bold text-xl text-gray-900 dark:text-white">Pitch Hub</span>
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Empowering the next generation of creators and innovators.
                    </p>
                </div>
                <div>
                    <h4 class="font-bold text-gray-900 dark:text-white mb-4">Company</h4>
                    <ul class="space-y-2 text-sm text-gray-500 dark:text-gray-400">
                        <li><a href="#top" class="hover:text-indigo-600 dark:hover:text-indigo-400">About</a></li>
                        <li><a href="#" class="hover:text-indigo-600 dark:hover:text-indigo-400">Careers</a></li>
                        <li><a href="#" class="hover:text-indigo-600 dark:hover:text-indigo-400">Press</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-gray-900 dark:text-white mb-4">Connect</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400"><i data-lucide="twitter" class="w-5 h-5"></i></a>
                        <a href="#" class="text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400"><i data-lucide="linkedin" class="w-5 h-5"></i></a>
                        <a href="#" class="text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400"><i data-lucide="instagram" class="w-5 h-5"></i></a>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-200 dark:border-gray-800 pt-8 text-center text-sm text-gray-400 dark:text-gray-600">
                &copy; 2025 Pitch Hub Inc. All rights reserved.
            </div>
        </div>
    </footer> -->
        <?php require_once 'includes/footer.php'?>
    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        // Theme Toggle Logic
        const themeToggleBtn = document.getElementById('theme-toggle');
        
        // Check for saved theme preference, otherwise use system preference
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        themeToggleBtn.addEventListener('click', () => {
            // Toggle dark class on html element
            document.documentElement.classList.toggle('dark');
            
            // Save preference
            if (document.documentElement.classList.contains('dark')) {
                localStorage.theme = 'dark';
            } else {
                localStorage.theme = 'light';
            }
        });
    </script>
</body>
</html>