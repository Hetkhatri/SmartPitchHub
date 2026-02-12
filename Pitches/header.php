<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Startup Pitch Hub - Connect Ideas with Funding</title>
    
    <!-- CORRECTED CSS PATH using BASE_URL from db.php -->
    <link rel="stylesheet" href="<?php echo defined('BASE_URL') ? BASE_URL : '/SmartPitchHub-1/'; ?>css/style.css">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
      /* Custom CSS from index.css and tailwind.config.ts */
      :root {
        --background: 0 0% 100%;
        --foreground: 222.2 84% 4.9%;
        --card: 0 0% 100%;
        --card-foreground: 222.2 84% 4.9%;
        --popover: 0 0% 100%;
        --popover-foreground: 222.2 84% 4.9%;
        --primary: 262 80% 65%;
        --primary-foreground: 210 40% 98%;
        --secondary: 210 40% 96.1%;
        --secondary-foreground: 222.2 47.4% 11.2%;
        --muted: 210 40% 96.1%;
        --muted-foreground: 215.4 16.3% 46.9%;
        --accent: 210 40% 96.1%;
        --accent-foreground: 222.2 47.4% 11.2%;
        --destructive: 0 84.2% 60.2%;
        --destructive-foreground: 210 40% 98%;
        --border: 214.3 31.8% 91.4%;
        --input: 214.3 31.8% 91.4%;
        --ring: 262 80% 65%;
        --radius: 0.75rem;

        /* Custom gradient and animation variables */
        --gradient-primary: linear-gradient(135deg, hsl(262 80% 65%), hsl(220 90% 70%));
        --shadow-glow: 0 0 40px hsl(262 80% 65% / 0.3);
        --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }

      .dark {
        --background: 222.2 84% 4.9%;
        --foreground: 210 40% 98%;
        --card: 222.2 84% 4.9%;
        --card-foreground: 210 40% 98%;
        --popover: 222.2 84% 4.9%;
        --popover-foreground: 210 40% 98%;
        --primary: 262 80% 70%;
        --primary-foreground: 222.2 47.4% 11.2%;
        --secondary: 217.2 32.6% 17.5%;
        --secondary-foreground: 210 40% 98%;
        --muted: 217.2 32.6% 17.5%;
        --muted-foreground: 215 20.2% 65.1%;
        --accent: 217.2 32.6% 17.5%;
        --accent-foreground: 210 40% 98%;
        --destructive: 0 62.8% 30.6%;
        --destructive-foreground: 210 40% 98%;
        --border: 217.2 32.6% 17.5%;
        --input: 217.2 32.6% 17.5%;
        --ring: 262 80% 70%;
        --gradient-primary: linear-gradient(135deg, hsl(262 80% 70%), hsl(280 90% 75%));
        --shadow-glow: 0 0 40px hsl(262 80% 70% / 0.4);
      }
      
      * {
        border-color: hsl(var(--border));
        transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 300ms;
      }

      body {
        background-color: hsl(var(--background));
        color: hsl(var(--foreground));
      }

      html {
        scroll-behavior: smooth;
      }

      .hover-glow:hover { box-shadow: var(--shadow-glow); }
      .gradient-bg { background: var(--gradient-primary); }
      .gradient-text {
        background-image: linear-gradient(to right, hsl(var(--primary)), #8b5cf6); /* purple-600 */
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }
      .animated-underline { position: relative; }
      .animated-underline::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        height: 2px;
        width: 0;
        background-color: hsl(var(--primary));
        transition: all 0.3s;
      }
      .animated-underline:hover::after { width: 100%; }

      @keyframes fade-in {
          from { opacity: 0; transform: translateY(10px); }
          to { opacity: 1; transform: translateY(0); }
      }
      .animate-fade-in { animation: fade-in 0.6s ease-out forwards; }
      
      @keyframes slide-up {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
      }
      .animate-slide-up { animation: slide-up 0.6s ease-out forwards; }

      @keyframes float {
          0%, 100% { transform: translateY(0px); }
          50% { transform: translateY(-10px); }
      }
      .animate-float { animation: float 6s ease-in-out infinite; }

      @keyframes glow {
          0%, 100% { opacity: 1; }
          50% { opacity: 0.5; }
      }
      .animate-glow { animation: glow 2s ease-in-out infinite; }

    </style>
</head>
<body>
     <header class="border-b border-border bg-card/50 backdrop-blur-sm sticky top-0 z-50 animate-slide-up">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
          <div class="flex items-center space-x-2 hover-scale cursor-pointer">
            <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center hover-glow transition-all duration-300">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-foreground animate-glow"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
            </div>
            <!-- Use BASE_URL for Home link -->
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/SmartPitchHub-1/'; ?>index.php" class="text-2xl font-bold gradient-text">SmartPitchHub</a>
          </div>
          <nav class="hidden md:flex items-center space-x-6">
            <!-- Updated Links with BASE_URL -->
             <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/SmartPitchHub-1/'; ?>index.php" class="text-muted-foreground hover:text-foreground animated-underline">Home</a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/SmartPitchHub-1/'; ?>index.php#features" class="text-muted-foreground hover:text-foreground animated-underline">Features</a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/SmartPitchHub-1/'; ?>index.php#how-it-works" class="text-muted-foreground hover:text-foreground animated-underline">How It Works</a>
            <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/SmartPitchHub-1/'; ?>about.php" class="text-muted-foreground hover:text-foreground animated-underline">About</a>
          </nav>
          <div class="flex items-center space-x-3">
            <button id="theme-toggle" class="relative group inline-flex items-center justify-center rounded-md text-sm font-medium h-10 w-10 hover:bg-accent hover:text-accent-foreground transition-all duration-300 hover:scale-110">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sun-icon h-5 w-5 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="moon-icon absolute h-5 w-5 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                <span class="sr-only">Toggle theme</span>
                <div class="absolute inset-0 rounded-md bg-gradient-to-r from-primary/20 to-purple-600/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300 blur-xl"></div>
            </button>
            <!-- Updated Buttons with BASE_URL -->
            <button class="inline-flex items-center justify-center rounded-md text-sm font-medium h-10 px-4 py-2 hover:bg-accent hover:text-accent-foreground hover-scale"><a href="<?php echo defined('BASE_URL') ? BASE_URL : '/SmartPitchHub-1/'; ?>login.php">Sign In</a></button>
            <button class="inline-flex items-center justify-center rounded-md text-sm font-medium h-10 px-4 py-2 bg-primary text-primary-foreground hover-scale hover-glow gradient-bg border-0"><a href="<?php echo defined('BASE_URL') ? BASE_URL : '/SmartPitchHub-1/'; ?>register.php">Get Started</a></button>
          </div>
        </div>
      </header>
    <main>