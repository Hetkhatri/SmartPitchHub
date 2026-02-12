<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Pitch Hub</title>
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
                    <a href="about_us.html" class="flex-shrink-0 flex items-center gap-2">
                        <i data-lucide="layers" class="h-8 w-8 text-indigo-600 dark:text-indigo-400"></i>
                        <span class="font-bold text-2xl tracking-tight text-gray-900 dark:text-white">Pitch Hub</span>
                    </a>
                </div>
                
           Theme Toggle Button -->
                <!-- <div class="flex items-center">
                    <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400 transition-colors focus:outline-none" aria-label="Toggle Dark Mode">
                        <i data-lucide="moon" class="w-6 h-6 hidden dark:block"></i>
                        <i data-lucide="sun" class="w-6 h-6 block dark:hidden"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav> -->
<?php require_once 'header.php' ?>
    <!-- Header -->
    <header class="pt-32 pb-12 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-bold tracking-tight text-gray-900 dark:text-white mb-6">
            Privacy <span class="gradient-text">Policy</span>
        </h1>
        <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">
            Last updated: November 20, 2025
        </p>
    </header>

    <!-- Policy Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pb-24">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm p-8 md:p-12 border border-gray-100 dark:border-gray-800 space-y-12 transition-colors duration-300">
            
            <!-- Section 1 -->
            <section>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    1. Information We Collect
                </h2>
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed mb-4">
                    We collect information you provide directly to us when you register, create a profile, or communicate with us. This may include:
                </p>
                <ul class="list-disc list-inside text-gray-600 dark:text-gray-300 space-y-2 pl-4">
                    <li>Name, email address, and contact details.</li>
                    <li>Profile information such as biography, skills, and portfolio content.</li>
                    <li>Payment information if you purchase premium services.</li>
                </ul>
            </section>

            <!-- Section 2 -->
            <section>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    2. How We Use Your Information
                </h2>
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                    We use the information we collect to operate and maintain Pitch Hub, to improve your user experience, to send you updates and marketing communications, and to protect our community from fraud and abuse.
                </p>
            </section>

            <!-- Section 3 -->
            <section>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    3. Cookies and Tracking
                </h2>
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                    We use cookies and similar tracking technologies to track the activity on our Service and hold certain information. You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our Service.
                </p>
            </section>

            <!-- Section 4 -->
            <section>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    4. Data Security
                </h2>
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                    The security of your data is important to us, but remember that no method of transmission over the Internet, or method of electronic storage is 100% secure. While we strive to use commercially acceptable means to protect your Personal Data, we cannot guarantee its absolute security.
                </p>
            </section>

            <!-- Section 5 -->
            <section>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">
                    5. Third-Party Links
                </h2>
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                    Our Service may contain links to other sites that are not operated by us. If you click on a third-party link, you will be directed to that third party's site. We strongly advise you to review the Privacy Policy of every site you visit.
                </p>
            </section>

            <!-- Contact Section -->
            <section class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                    Contact Us
                </h2>
                <p class="text-gray-600 dark:text-gray-300 mb-4">
                    If you have any questions about this Privacy Policy, please contact us:
                </p>
                <a href="mailto:smartpitchhub@gmail.com" class="inline-flex items-center gap-2 text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                    <i data-lucide="mail" class="w-4 h-4"></i>
                    smartpitchhub@gmail.com
                </a>
            </section>

        </div>
    </main>

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
                        <li><a href="about_us.html" class="hover:text-indigo-600 dark:hover:text-indigo-400">About</a></li>
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
                        <?php require_once 'footer.php' ?>
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