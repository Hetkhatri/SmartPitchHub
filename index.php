     <?php require_once 'includes/header.php'?>
<doctype html>
<html lang="en" class="dark">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SmartPitchHub - Connect Ideas with Investment</title>
    <meta name="description" content="A comprehensive platform connecting startup founders with potential investors. Submit pitches, discover opportunities, and foster meaningful business connections." />
    <meta name="author" content="Lovable" />

    <meta property="og:title" content="smart-pitch-playground" />
    <meta property="og:description" content="Lovable Generated Project" />
    <meta property="og:type" content="website" />
    <meta property="og:image" content="https://lovable.dev/opengraph-image-p98pqg.png" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@lovable_dev" />
    <meta name="twitter:image" content="https://lovable.dev/opengraph-image-p98pqg.png" />
    
    <script src="https://cdn.tailwindcss.com"></script>
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
    <div class="min-h-screen bg-background transition-colors duration-300">
      <!-- Header -->

      <!-- Hero Section -->
      <section class="py-20 px-4 relative overflow-hidden"> 
        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-purple-500/5 to-background"></div>
        <div class="container mx-auto text-center relative z-10">
          <div class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80 mb-6 animate-fade-in hover-scale">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2"><path d="M15 4V2a2 2 0 0 0-2-2H2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5" /><path d="m14 22-4-4 4-4"/><path d="m20 2-4 4 4 4"/><path d="M12.5 7.5h4l-2.5 5 2.5 5h-4l1.5-5z"/></svg>
            Connecting Innovation with Investment
          </div>
          <h1 class="text-5xl md:text-6xl font-bold text-foreground mb-6 leading-tight animate-slide-up">
            Bridge the Gap Between
            <span class="gradient-text block animate-float">Ideas & Funding</span>
          </h1>
          <p class="text-xl text-muted-foreground mb-8 max-w-3xl mx-auto leading-relaxed animate-fade-in">
            SmartPitchHub is a comprehensive platform that connects startup founders with potential investors. 
            Showcase your business ideas, discover investment opportunities, and foster meaningful business connections.
          </p>
           <a href="register.php">
          <div class="flex flex-col sm:flex-row items-center justify-center gap-4 animate-slide-up">
             <button href="register.php" class="inline-flex items-center justify-center rounded-md text-sm font-medium h-11 px-8 min-w-[200px] hover-scale hover-glow gradient-bg border-0">
              Start Pitching 
          </a>
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
            <a href="Pitches/explorePitches.php">
            <button  class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background hover:bg-accent hover:text-accent-foreground h-11 px-8 min-w-[200px] hover-scale hover-glow">
              Explore Pitches
            </button>
          </a>
          </div>
          <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
            <div class="text-center hover-lift animate-fade-in group">
              <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 hover-glow group-hover:scale-110 transition-all duration-300">
                 <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <h3 class="font-semibold text-lg mb-2">For Founders</h3>
              <p class="text-muted-foreground">Submit pitches, track investor interest, and manage your startup journey</p>
            </div>
            <div class="text-center hover-lift animate-fade-in group" style="animation-delay: 0.2s">
              <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 hover-glow group-hover:scale-110 transition-all duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              </div>
              <h3 class="font-semibold text-lg mb-2">For Investors</h3>
              <p class="text-muted-foreground">Browse curated pitches, save favorites, and express investment interest</p>
            </div>
            <div class="text-center hover-lift animate-fade-in group" style="animation-delay: 0.4s">
              <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4 hover-glow group-hover:scale-110 transition-all duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
              </div>
              <h3 class="font-semibold text-lg mb-2">Quality Assured</h3>
              <p class="text-muted-foreground">Admin-moderated content ensures high-quality pitches and prevents spam</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Features Section -->
      <section id="features" class="py-20 px-4 bg-muted/30">
        <div class="container mx-auto">
          <div class="text-center mb-16 animate-fade-in">
            <h2 class="text-4xl font-bold text-foreground mb-4">Powerful Features</h2>
            <p class="text-xl text-muted-foreground max-w-2xl mx-auto">
              Everything you need to connect ideas with investment opportunities
            </p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm border-2 hover:border-primary/50 transition-all duration-300 hover-lift hover-glow group animate-fade-in">
              <div class="flex flex-col space-y-1.5 p-6">
                 <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary mb-4 group-hover:scale-110 transition-transform duration-300"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <h3 class="text-2xl font-semibold leading-none tracking-tight">Smart Search</h3>
                <p class="text-sm text-muted-foreground">
                  Category-based search with trending filters to discover relevant opportunities
                </p>
              </div>
            </div>
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm border-2 hover:border-primary/50 transition-all duration-300 hover-lift hover-glow group animate-fade-in" style="animation-delay: 0.1s">
              <div class="flex flex-col space-y-1.5 p-6">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary mb-4 group-hover:scale-110 transition-transform duration-300"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                <h3 class="text-2xl font-semibold leading-none tracking-tight">Save Favorites</h3>
                <p class="text-sm text-muted-foreground">
                  Like and save pitches that interest you for easy access later
                </p>
              </div>
            </div>
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm border-2 hover:border-primary/50 transition-all duration-300 hover-lift hover-glow group animate-fade-in" style="animation-delay: 0.2s">
              <div class="flex flex-col space-y-1.5 p-6">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary mb-4 group-hover:scale-110 transition-transform duration-300"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                <h3 class="text-2xl font-semibold leading-none tracking-tight">Smart Notifications</h3>
                <p class="text-sm text-muted-foreground">
                  Get notified about investor interest and new matching opportunities
                </p>
              </div>
            </div>
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm border-2 hover:border-primary/50 transition-all duration-300 hover-lift hover-glow group animate-fade-in" style="animation-delay: 0.3s">
              <div class="flex flex-col space-y-1.5 p-6">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary mb-4 group-hover:scale-110 transition-transform duration-300"><path d="M3 3v18h18"/><path d="M7 12v4h4"/><path d="M15.5 3.5a2.12 2.12 0 0 1 3 3L7 18.5V21h2.5L21 9.5a2.12 2.12 0 0 0-3-3Z"/></svg>
                <h3 class="text-2xl font-semibold leading-none tracking-tight">Analytics Dashboard</h3>
                <p class="text-sm text-muted-foreground">
                  Track pitch performance and investor engagement with detailed analytics
                </p>
              </div>
            </div>
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm border-2 hover:border-primary/50 transition-all duration-300 hover-lift hover-glow group animate-fade-in" style="animation-delay: 0.4s">
              <div class="flex flex-col space-y-1.5 p-6">
                 <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary mb-4 group-hover:scale-110 transition-transform duration-300"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <h3 class="text-2xl font-semibold leading-none tracking-tight">Role-Based Access</h3>
                <p class="text-sm text-muted-foreground">
                  Dedicated experiences for founders, investors, and admins
                </p>
              </div>
            </div>
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm border-2 hover:border-primary/50 transition-all duration-300 hover-lift hover-glow group animate-fade-in" style="animation-delay: 0.5s">
              <div class="flex flex-col space-y-1.5 p-6">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary mb-4 group-hover:scale-110 transition-transform duration-300"><path d="M3 3v18h18"/><path d="M7 12v4h4"/><path d="M15.5 3.5a2.12 2.12 0 0 1 3 3L7 18.5V21h2.5L21 9.5a2.12 2.12 0 0 0-3-3Z"/></svg>
                <h3 class="text-2xl font-semibold leading-none tracking-tight">Trending Insights</h3>
                <p class="text-sm text-muted-foreground">
                  Discover trending categories and popular investment areas
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- How It Works -->
      <section id="how-it-works" class="py-20 px-4">
        <div class="container mx-auto">
          <div class="text-center mb-16 animate-fade-in">
            <h2 class="text-4xl font-bold text-foreground mb-4">How It Works</h2>
            <p class="text-xl text-muted-foreground max-w-2xl mx-auto">
              Simple steps to connect entrepreneurs with investors
            </p>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center hover-lift animate-fade-in group">
              <div class="w-20 h-20 gradient-bg rounded-full flex items-center justify-center mx-auto mb-6 text-2xl font-bold text-primary-foreground hover-glow group-hover:scale-110 transition-all duration-300">
                1
              </div>
              <h3 class="text-xl font-semibold mb-4">Submit Your Pitch</h3>
              <p class="text-muted-foreground">
                Founders create comprehensive pitches including title, category, funding requirements, and detailed descriptions
              </p>
            </div>
            <div class="text-center hover-lift animate-fade-in group" style="animation-delay: 0.2s">
              <div class="w-20 h-20 gradient-bg rounded-full flex items-center justify-center mx-auto mb-6 text-2xl font-bold text-primary-foreground hover-glow group-hover:scale-110 transition-all duration-300">
                2
              </div>
              <h3 class="text-xl font-semibold mb-4">Get Discovered</h3>
              <p class="text-muted-foreground">
                Admin-approved pitches become visible to investors who can browse, search, and filter based on their interests
              </p>
            </div>
            <div class="text-center hover-lift animate-fade-in group" style="animation-delay: 0.4s">
              <div class="w-20 h-20 gradient-bg rounded-full flex items-center justify-center mx-auto mb-6 text-2xl font-bold text-primary-foreground hover-glow group-hover:scale-110 transition-all duration-300">
                3
              </div>
              <h3 class="text-xl font-semibold mb-4">Connect & Fund</h3>
              <p class="text-muted-foreground">
                Investors express interest through dedicated forms, leading to meaningful connections and potential funding
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- CTA Section -->
      <section class="py-20 px-4 bg-primary/5 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-primary/10 via-purple-500/10 to-primary/10 animate-glow"></div>
        <div class="container mx-auto text-center relative z-10 animate-fade-in">
          <h2 class="text-4xl font-bold text-foreground mb-6">Ready to Transform Your Startup Journey?</h2>
          <p class="text-xl text-muted-foreground mb-8 max-w-2xl mx-auto">
            Join SmartPitchHub today and be part of a thriving ecosystem that fosters innovation and enables business success.
          </p>
          <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
             <a href="register.php">
            <button class="inline-flex items-center justify-center rounded-md text-sm font-medium h-11 px-8 min-w-[200px] hover-scale hover-glow gradient-bg border-0">
              Join as Founder
            </button>
    </a>
     <a href="register.php">
            <button class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background hover:bg-accent hover:text-accent-foreground h-11 px-8 min-w-[200px] hover-scale hover-glow">
              Join as Investor
            </button>
    </a>
          </div>
        </div>
      </section>

      <!-- Footer -->
      <!-- <footer class="bg-card border-t border-border py-12 px-4">
        <div class="container mx-auto">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="animate-fade-in">
              <div class="flex items-center space-x-2 mb-4 hover-scale cursor-pointer">
                <div class="w-8 h-8 gradient-bg rounded-lg flex items-center justify-center hover-glow">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-foreground"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>
                </div>
                <h3 class="text-xl font-bold gradient-text">SmartPitchHub</h3>
              </div>
              <p class="text-muted-foreground">
                Connecting innovation with investment opportunities.
              </p>
            </div>
            <div class="animate-fade-in" style="animation-delay: 0.1s">
              <h4 class="font-semibold mb-4">Platform</h4>
              <ul class="space-y-2 text-muted-foreground">
                <li><a href="register.php" class="hover:text-foreground animated-underline">For Founders</a></li>
                <li><a href="register.php" class="hover:text-foreground animated-underline">For Investors</a></li>
                <li><a href="explorePitches.php" class="hover:text-foreground animated-underline">Browse Pitches</a></li>
                <li><a href="#" class="hover:text-foreground animated-underline">Analytics</a></li> -->
              <!-- </ul>
            </div> -->
            <!-- <div class="animate-fade-in" style="animation-delay: 0.2s">
              <h4 class="font-semibold mb-4">Resources</h4>
              <ul class="space-y-2 text-muted-foreground">
                <li><a href="about.php" class="hover:text-foreground animated-underline">Help Center</a></li>
                <li><a href="#" class="hover:text-foreground animated-underline">API Documentation</a></li> -->
                <!-- <li><a href="about.php" class="hover:text-foreground animated-underline">Blog</a></li> -->
                <!-- <li><a href="#" class="hover:text-foreground animated-underline">Community</a></li> -->
              <!-- </ul> -->
            <!-- </div> -->
            <!-- <div class="animate-fade-in" style="animation-delay: 0.3s">
              <h4 class="font-semibold mb-4">Company</h4>
              <ul class="space-y-2 text-muted-foreground">
                <li><a href="pages/about.php" class="hover:text-foreground animated-underline">About Us</a></li>
                 <li><a href="#" class="hover:text-foreground animated-underline">Contact</a></li> -->
                <!-- <li><a href="pages/privacy-policy.php" class="hover:text-foreground animated-underline">Privacy Policy</a></li>
                <li><a href="pages/Tac.php" class="hover:text-foreground animated-underline">Terms of Service</a></li>
              </ul>
            </div>
          </div>
          <div class="border-t border-border mt-8 pt-8 text-center text-muted-foreground animate-fade-in" style="animation-delay: 0.4s">
            <p>&copy; 2024 SmartPitchHub. All rights reserved.</p>
          </div>
        </div>
      </footer>  -->
      <?php require_once 'includes/footer.php' ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggleBtn = document.getElementById('theme-toggle');
            const sunIcon = themeToggleBtn.querySelector('.sun-icon');
            const moonIcon = themeToggleBtn.querySelector('.moon-icon');

            const applyTheme = (theme) => {
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            };
            
            // On page load, check for saved theme
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            const currentTheme = savedTheme || (prefersDark ? 'dark' : 'light');
            applyTheme(currentTheme);

            themeToggleBtn.addEventListener('click', () => {
                const isDark = document.documentElement.classList.contains('dark');
                const newTheme = isDark ? 'light' : 'dark';
                applyTheme(newTheme);
                localStorage.setItem('theme', newTheme);
            });
        });
    </script>
  </body>
</html>
