// Mock API Service for Investor Dashboard
class MockAPI {
    constructor() {
        this.baseURL = '/api';
        this.delay = 500; // Simulate network delay
        this.initializeData();
    }

    initializeData() {
        // Initialize with demo data if not exists
        if (!localStorage.getItem('mock-api-startups')) {
            const startups = this.generateDemoStartups();
            localStorage.setItem('mock-api-startups', JSON.stringify(startups));
        }

        if (!localStorage.getItem('mock-api-portfolio')) {
            localStorage.setItem('mock-api-portfolio', JSON.stringify([]));
        }

        if (!localStorage.getItem('mock-api-deals')) {
            localStorage.setItem('mock-api-deals', JSON.stringify([]));
        }

        if (!localStorage.getItem('mock-api-bookmarks')) {
            localStorage.setItem('mock-api-bookmarks', JSON.stringify([]));
        }

        if (!localStorage.getItem('mock-api-connections')) {
            const connections = this.generateDemoConnections();
            localStorage.setItem('mock-api-connections', JSON.stringify(connections));
        }

        if (!localStorage.getItem('mock-api-messages')) {
            const messages = this.generateDemoMessages();
            localStorage.setItem('mock-api-messages', JSON.stringify(messages));
        }
    }

    // Simulate network delay
    async delayResponse() {
        return new Promise(resolve => setTimeout(resolve, this.delay + Math.random() * 300));
    }

    // Generic fetch simulation
    async mockFetch(endpoint, options = {}) {
        await this.delayResponse();

        const method = options.method || 'GET';
        const body = options.body ? JSON.parse(options.body) : null;

        try {
            let result;

            switch (method) {
                case 'GET':
                    result = this.handleGet(endpoint);
                    break;
                case 'POST':
                    result = this.handlePost(endpoint, body);
                    break;
                case 'PUT':
                    result = this.handlePut(endpoint, body);
                    break;
                case 'DELETE':
                    result = this.handleDelete(endpoint);
                    break;
                default:
                    throw new Error(`Method ${method} not supported`);
            }

            return {
                ok: true,
                status: 200,
                json: async () => result
            };
        } catch (error) {
            return {
                ok: false,
                status: 400,
                json: async () => ({ error: error.message })
            };
        }
    }

    handleGet(endpoint) {
        if (endpoint === '/api/startups') {
            return JSON.parse(localStorage.getItem('mock-api-startups') || '[]');
        }

        if (endpoint.startsWith('/api/startups/')) {
            const id = endpoint.split('/').pop();
            const startups = JSON.parse(localStorage.getItem('mock-api-startups') || '[]');
            return startups.find(s => s.id === id) || null;
        }

        if (endpoint === '/api/portfolio') {
            return JSON.parse(localStorage.getItem('mock-api-portfolio') || '[]');
        }

        if (endpoint === '/api/deals') {
            return JSON.parse(localStorage.getItem('mock-api-deals') || '[]');
        }

        if (endpoint === '/api/bookmarks') {
            return JSON.parse(localStorage.getItem('mock-api-bookmarks') || '[]');
        }

        if (endpoint === '/api/connections') {
            return JSON.parse(localStorage.getItem('mock-api-connections') || '[]');
        }

        if (endpoint === '/api/messages') {
            return JSON.parse(localStorage.getItem('mock-api-messages') || '[]');
        }

        throw new Error('Endpoint not found');
    }

    handlePost(endpoint, body) {
        if (endpoint === '/api/portfolio') {
            const portfolio = JSON.parse(localStorage.getItem('mock-api-portfolio') || '[]');
            const newEntry = { ...body, id: Date.now().toString() };
            portfolio.push(newEntry);
            localStorage.setItem('mock-api-portfolio', JSON.stringify(portfolio));
            return newEntry;
        }

        if (endpoint === '/api/deals') {
            const deals = JSON.parse(localStorage.getItem('mock-api-deals') || '[]');
            const newDeal = { ...body, id: Date.now().toString() };
            deals.push(newDeal);
            localStorage.setItem('mock-api-deals', JSON.stringify(deals));
            return newDeal;
        }

        if (endpoint === '/api/bookmarks') {
            const bookmarks = JSON.parse(localStorage.getItem('mock-api-bookmarks') || '[]');
            if (!bookmarks.includes(body.startupId)) {
                bookmarks.push(body.startupId);
                localStorage.setItem('mock-api-bookmarks', JSON.stringify(bookmarks));
            }
            return { success: true };
        }

        if (endpoint === '/api/connections') {
            const connections = JSON.parse(localStorage.getItem('mock-api-connections') || '[]');
            const newConnection = { ...body, id: Date.now().toString(), status: 'pending' };
            connections.push(newConnection);
            localStorage.setItem('mock-api-connections', JSON.stringify(connections));
            return newConnection;
        }

        if (endpoint === '/api/messages') {
            const messages = JSON.parse(localStorage.getItem('mock-api-messages') || '[]');
            const newMessage = { ...body, id: Date.now().toString(), timestamp: new Date().toISOString() };
            messages.push(newMessage);
            localStorage.setItem('mock-api-messages', JSON.stringify(messages));
            return newMessage;
        }

        throw new Error('Endpoint not found');
    }

    handlePut(endpoint, body) {
        if (endpoint.startsWith('/api/deals/')) {
            const id = endpoint.split('/').pop();
            const deals = JSON.parse(localStorage.getItem('mock-api-deals') || '[]');
            const index = deals.findIndex(d => d.id === id);
            if (index !== -1) {
                deals[index] = { ...deals[index], ...body };
                localStorage.setItem('mock-api-deals', JSON.stringify(deals));
                return deals[index];
            }
            throw new Error('Deal not found');
        }

        if (endpoint.startsWith('/api/connections/')) {
            const id = endpoint.split('/').pop();
            const connections = JSON.parse(localStorage.getItem('mock-api-connections') || '[]');
            const index = connections.findIndex(c => c.id === id);
            if (index !== -1) {
                connections[index] = { ...connections[index], ...body };
                localStorage.setItem('mock-api-connections', JSON.stringify(connections));
                return connections[index];
            }
            throw new Error('Connection not found');
        }

        throw new Error('Endpoint not found');
    }

    handleDelete(endpoint) {
        if (endpoint.startsWith('/api/portfolio/')) {
            const id = endpoint.split('/').pop();
            const portfolio = JSON.parse(localStorage.getItem('mock-api-portfolio') || '[]');
            const filtered = portfolio.filter(p => p.id !== id);
            localStorage.setItem('mock-api-portfolio', JSON.stringify(filtered));
            return { success: true };
        }

        if (endpoint.startsWith('/api/bookmarks/')) {
            const startupId = endpoint.split('/').pop();
            const bookmarks = JSON.parse(localStorage.getItem('mock-api-bookmarks') || '[]');
            const filtered = bookmarks.filter(b => b !== startupId);
            localStorage.setItem('mock-api-bookmarks', JSON.stringify(filtered));
            return { success: true };
        }

        throw new Error('Endpoint not found');
    }

    generateDemoStartups() {
        return [
            {
                id: 'tech-innovate',
                name: 'TechInnovate AI',
                tagline: 'Next-gen AI solutions for enterprise',
                industry: 'AI/ML',
                stage: 'Series A',
                seeking: '$2.5M',
                valuation: '$10M',
                revenue: '$1.2M',
                growth: '150% YoY',
                customers: '25 Enterprise',
                founded: '2023',
                location: 'San Francisco, CA',
                description: 'TechInnovate AI is revolutionizing enterprise data analysis with our proprietary AI engine that provides real-time insights and automated decision-making capabilities.',
                team: ['John Smith (CEO)', 'Sarah Johnson (CTO)', 'Mike Chen (Head of Product)'],
                traction: '40% operational efficiency improvement for Fortune 500 clients'
            },
            {
                id: 'greentech',
                name: 'GreenTech Solutions',
                tagline: 'Sustainable energy management platform',
                industry: 'Clean Energy',
                stage: 'Seed',
                seeking: '$1.2M',
                valuation: '$5M',
                revenue: '$800K',
                growth: '200% YoY',
                customers: '150 SMEs',
                founded: '2022',
                location: 'Berlin, Germany',
                description: 'GreenTech Solutions provides smart energy management systems that help businesses reduce their carbon footprint while optimizing energy costs.',
                team: ['Anna Mueller (CEO)', 'David Brown (COO)', 'Lisa Wang (Head of Engineering)'],
                traction: '30% average energy consumption reduction for clients'
            },
            {
                id: 'financeflow',
                name: 'FinanceFlow',
                tagline: 'Next-generation fintech for small businesses',
                industry: 'Fintech',
                stage: 'Pre-seed',
                seeking: '$800K',
                valuation: '$3.2M',
                revenue: '$400K',
                growth: '300% YoY',
                customers: '500 SMBs',
                founded: '2023',
                location: 'London, UK',
                description: 'FinanceFlow offers a comprehensive financial management platform designed specifically for small and medium enterprises.',
                team: ['Robert Taylor (CEO)', 'Emma Davis (CFO)', 'James Wilson (Head of Sales)'],
                traction: 'Integrated accounting, invoicing, and cash flow management'
            },
            {
                id: 'healthtech',
                name: 'MediConnect',
                tagline: 'AI-powered telemedicine platform',
                industry: 'Healthcare',
                stage: 'Series B',
                seeking: '$5M',
                valuation: '$25M',
                revenue: '$3.5M',
                growth: '180% YoY',
                customers: '1000+ Healthcare Providers',
                founded: '2021',
                location: 'Boston, MA',
                description: 'MediConnect revolutionizes healthcare delivery with AI-powered telemedicine solutions connecting patients with healthcare providers globally.',
                team: ['Dr. Maria Rodriguez (CEO)', 'Dr. Kevin Park (CMO)', 'Rachel Green (Head of Technology)'],
                traction: 'Reduced patient wait times by 60%, improved diagnostic accuracy by 35%'
            },
            {
                id: 'edtech',
                name: 'LearnSphere',
                tagline: 'Personalized learning platform for K-12',
                industry: 'Education',
                stage: 'Series A',
                seeking: '$3M',
                valuation: '$12M',
                revenue: '$1.8M',
                growth: '220% YoY',
                customers: '50 Schools',
                founded: '2022',
                location: 'Austin, TX',
                description: 'LearnSphere provides adaptive learning technology that personalizes education for each student based on their learning style and pace.',
                team: ['Dr. Jennifer Adams (CEO)', 'Mark Thompson (CTO)', 'Susan Lee (Head of Education)'],
                traction: '25% improvement in student test scores, 40% increase in engagement'
            }
        ];
    }

    generateDemoConnections() {
        return [
            {
                id: '1',
                name: 'Sarah Anderson',
                title: 'Angel Investor',
                focus: 'Tech Focus',
                avatar: 'https://via.placeholder.com/40x40/3B82F6/FFFFFF?text=SA',
                status: 'pending',
                message: 'Hi Michael! I\'m interested in connecting to discuss potential co-investment opportunities in the AI space.',
                mutualConnections: 3,
                timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(), // 2 hours ago
                badges: ['AI/ML', 'Active']
            },
            {
                id: '2',
                name: 'David Chen',
                title: 'VC Partner',
                focus: 'GreenTech Ventures',
                avatar: 'https://via.placeholder.com/40x40/10B981/FFFFFF?text=DC',
                status: 'pending',
                message: 'Michael, I\'d like to connect regarding a potential syndicate opportunity in the clean energy sector.',
                mutualConnections: 1,
                timestamp: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(), // 1 day ago
                badges: ['Green Tech', 'Busy']
            }
        ];
    }

    generateDemoMessages() {
        return [
            {
                id: '1',
                from: 'Jennifer Rodriguez',
                avatar: 'https://via.placeholder.com/40x40/EF4444/FFFFFF?text=JR',
                message: 'Thanks for sharing that pitch deck. The AI startup looks promising...',
                timestamp: new Date(Date.now() - 5 * 60 * 1000).toISOString(), // 5 min ago
                badges: ['AI/ML', 'Deal Discussion']
            },
            {
                id: '2',
                from: 'Marcus Kim',
                avatar: 'https://via.placeholder.com/40x40/F59E0B/FFFFFF?text=MK',
                message: 'Are you available for a quick call tomorrow? I have a startup that might fit your criteria...',
                timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(), // 2 hours ago
                badges: ['Green Tech', 'Meeting Request']
            }
        ];
    }
}

// Global mock API instance
window.mockAPI = new MockAPI();

// Override fetch for mock API calls
const originalFetch = window.fetch;
window.fetch = function(url, options) {
    if (url.startsWith('/api/')) {
        return window.mockAPI.mockFetch(url, options);
    }
    return originalFetch.call(this, url, options);
};
