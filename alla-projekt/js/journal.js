// Get projects from localStorage or use default data
function getProjects() {
    const stored = localStorage.getItem('journal_posts');
    if (stored) {
        return JSON.parse(stored);
    }
    
    // Default demo projects if no data exists
    const defaultProjects = [
        {
            id: 1,
            title: "Automationslösning med WAGO PLC",
            category: "automation",
            date: "2025-01-15",
            author: "Salman Yahya",
            company: "Academic Work AB",
            location: "Göteborg, Sverige",
            excerpt: "Omfattande automationslösning med WAGO PLC programmerad i Codesys, inklusive SCADA-integration med AVEVA och inbyggda C#-applikationer för styrsystem.",
            tags: ["WAGO PLC", "Codesys", "SCADA", "AVEVA", "C#", "Automation"],
            icon: "🤖",
            featured: true,
            readTime: "12 min",
            status: "published",
            createdAt: "2025-01-15T10:00:00Z",
            createdBy: "salman"
        },
        {
            id: 2,
            title: "77 GHz Radar - RF Waveguide Design",
            category: "rf-engineering",
            date: "2023-05-20",
            author: "Salman Yahya",
            company: "Gapwaves AB",
            location: "Göteborg, Sverige",
            excerpt: "Design och simulering av mikrostrip-till-gapvågledare för bilradarsystem vid 77 GHz. Elektromagnetiska simuleringar i CST Studio Suite med fokus på högfrekvensprestanda.",
            tags: ["RF Design", "CST Studio", "77 GHz", "EM Simulation", "Automotive"],
            icon: "📡",
            featured: false,
            readTime: "15 min",
            status: "published",
            createdAt: "2023-05-20T09:00:00Z",
            createdBy: "salman"
        },
        {
            id: 3,
            title: "Bioinformatisk Pipeline - Fullstack Development",
            category: "software",
            date: "2022-12-10",
            author: "Salman Yahya",
            company: "Chalmers Tekniska Högskola",
            location: "Göteborg, Sverige",
            excerpt: "Fullstack bioinformatisk pipeline för databehandling och analys. Backend i C# och Blazor, frontend i JavaScript, driftsatt på AWS.",
            tags: ["C#", "Blazor", "JavaScript", "AWS", "Bioinformatics"],
            icon: "🧬",
            featured: false,
            readTime: "10 min",
            status: "published",
            createdAt: "2022-12-10T14:30:00Z",
            createdBy: "salman"
        }
    ];
    
    // Save default projects to localStorage
    localStorage.setItem('journal_posts', JSON.stringify(defaultProjects));
    return defaultProjects;
}

// Use dynamic projects
const projects = getProjects();

// Filter functionality
let currentFilter = 'all';
let searchQuery = '';

function filterProjects() {
    const projects = getProjects().filter(p => p.status === 'published'); // Only show published
    const filteredProjects = projects.filter(project => {
        const matchesCategory = currentFilter === 'all' || project.category === currentFilter;
        const matchesSearch = searchQuery === '' || 
            project.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            project.excerpt.toLowerCase().includes(searchQuery.toLowerCase()) ||
            project.tags.some(tag => tag.toLowerCase().includes(searchQuery.toLowerCase()));
        
        return matchesCategory && matchesSearch;
    });

    renderProjects(filteredProjects);
}

function renderProjects(projectsToRender) {
    const grid = document.getElementById('articles-grid');
    const noResults = document.getElementById('no-results');
    
    if (projectsToRender.length === 0) {
        grid.style.display = 'none';
        noResults.style.display = 'block';
        return;
    }

    grid.style.display = 'grid';
    noResults.style.display = 'none';
    
    grid.innerHTML = projectsToRender.map(project => `
        <article class="article-card fade-in">
            <div class="article-image">
                <span style="font-size: 4rem; z-index: 2;">${project.icon}</span>
                ${project.featured ? '<div class="article-badge">Featured</div>' : ''}
            </div>
            <div class="article-content">
                <div class="article-meta">
                    <span>📅 ${formatDate(project.date)}</span>
                    <span>👤 ${project.author || 'Salman Yahya'}</span>
                    <span>⏱️ ${project.readTime}</span>
                </div>
                <h3 class="article-title">${project.title}</h3>
                <p class="article-excerpt">${project.excerpt}</p>
                <div class="article-tags">
                    ${project.tags.slice(0, 4).map(tag => `<span class="tag">${tag}</span>`).join('')}
                </div>
                <a href="articles/project-${project.id}.html" class="read-more">
                    Läs mer →
                </a>
            </div>
        </article>
    `).join('');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('sv-SE', options);
}

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
    const projects = getProjects().filter(p => p.status === 'published');
    
    // Initial render
    renderProjects(projects);

    // Featured project
    const featuredProject = projects.find(p => p.featured);
    if (featuredProject) {
        const featuredSection = document.getElementById('featured-article');
        if (featuredSection) {
            featuredSection.innerHTML = `
                <div>
                    <span class="featured-badge">⭐ Utvalt Projekt</span>
                    <h3>${featuredProject.title}</h3>
                    <div class="article-meta" style="margin-bottom: 1rem;">
                        <span>📅 ${formatDate(featuredProject.date)}</span>
                        <span>👤 ${featuredProject.author || 'Salman Yahya'}</span>
                        <span>🏢 ${featuredProject.company}</span>
                        <span>⏱️ ${featuredProject.readTime}</span>
                    </div>
                    <p class="article-excerpt">${featuredProject.excerpt}</p>
                    <div class="article-tags" style="margin-bottom: 2rem;">
                        ${featuredProject.tags.map(tag => `<span class="tag">${tag}</span>`).join('')}
                    </div>
                    <a href="articles/project-${featuredProject.id}.html" class="read-more" style="font-size: 1.1rem;">
                        Läs hela projektet →
                    </a>
                </div>
                <div class="featured-image">
                    ${featuredProject.icon}
                </div>
            `;
        }
    }

    // Filter buttons
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            filterProjects();
        });
    });

    // Search
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            filterProjects();
        });
    }

    // Update stats
    const categoryCount = {};
    projects.forEach(p => {
        categoryCount[p.category] = (categoryCount[p.category] || 0) + 1;
    });

    document.getElementById('total-projects').textContent = projects.length;
    document.getElementById('total-categories').textContent = Object.keys(categoryCount).length;
    document.getElementById('total-technologies').textContent = 
        [...new Set(projects.flatMap(p => p.tags))].length;
});

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});