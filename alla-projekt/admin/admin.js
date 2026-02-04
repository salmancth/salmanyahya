// Admin Panel JavaScript - Complete Blog CMS

// Authentication
const USERS = {
    admin: 'admin123', // Change this in production!
    salman: 'salman2025'
};

let currentUser = null;
let currentTags = [];
let editingPostId = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    setupEventListeners();
    setTodayDate();
});

function checkAuth() {
    const user = localStorage.getItem('admin_user');
    if (user) {
        currentUser = user;
        showDashboard();
    } else {
        showLogin();
    }
}

function showLogin() {
    document.getElementById('login-screen').classList.remove('hidden');
    document.getElementById('admin-dashboard').classList.add('hidden');
}

function showDashboard() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('admin-dashboard').classList.remove('hidden');
    document.getElementById('logged-user').textContent = `👤 ${currentUser}`;
    loadDashboard();
}

// Login Form
document.getElementById('login-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorDiv = document.getElementById('login-error');

    if (USERS[username] && USERS[username] === password) {
        currentUser = username;
        localStorage.setItem('admin_user', username);
        errorDiv.classList.add('hidden');
        showDashboard();
    } else {
        errorDiv.textContent = '❌ Fel användarnamn eller lösenord';
        errorDiv.classList.remove('hidden');
    }
});

function logout() {
    if (confirm('Är du säker på att du vill logga ut?')) {
        localStorage.removeItem('admin_user');
        currentUser = null;
        showLogin();
    }
}

// Navigation
function showSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.add('hidden');
    });

    // Remove active class from all nav links
    document.querySelectorAll('.sidebar nav a').forEach(link => {
        link.classList.remove('active');
    });

    // Show selected section
    document.getElementById(`section-${sectionName}`).classList.remove('hidden');

    // Add active class to clicked link
    const activeLink = document.querySelector(`.sidebar nav a[data-section="${sectionName}"]`);
    if (activeLink) {
        activeLink.classList.add('active');
    }

    // Load section data
    if (sectionName === 'dashboard') {
        loadDashboard();
    } else if (sectionName === 'posts') {
        loadPostsList();
    } else if (sectionName === 'new-post') {
        resetPostForm();
    }
}

// Local Storage Functions
function getPosts() {
    const posts = localStorage.getItem('journal_posts');
    return posts ? JSON.parse(posts) : [];
}

function savePosts(posts) {
    localStorage.setItem('journal_posts', JSON.stringify(posts));
}

function getNextId() {
    const posts = getPosts();
    return posts.length > 0 ? Math.max(...posts.map(p => p.id)) + 1 : 1;
}

// Dashboard
function loadDashboard() {
    const posts = getPosts();
    const published = posts.filter(p => p.status === 'published').length;
    const drafts = posts.filter(p => p.status === 'draft').length;
    const categories = [...new Set(posts.map(p => p.category))].length;

    document.getElementById('total-posts').textContent = posts.length;
    document.getElementById('published-posts').textContent = published;
    document.getElementById('draft-posts').textContent = drafts;
    document.getElementById('categories-count').textContent = categories;

    // Recent posts
    const recentPosts = posts.slice(-5).reverse();
    const recentPostsHTML = recentPosts.length > 0 ? `
        <table class="posts-table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Författare</th>
                    <th>Kategori</th>
                    <th>Datum</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                ${recentPosts.map(post => `
                    <tr onclick="editPost(${post.id})" style="cursor: pointer;">
                        <td>${post.icon} ${post.title}</td>
                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">${post.author || 'Okänd'}</td>
                        <td>${getCategoryName(post.category)}</td>
                        <td>${formatDate(post.date)}</td>
                        <td><span class="badge badge-${post.status === 'published' ? 'published' : 'draft'}">${post.status === 'published' ? 'Publicerad' : 'Utkast'}</span></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    ` : '<p style="color: var(--text-muted);">Inga projekt än. Skapa ditt första projekt!</p>';

    document.getElementById('recent-posts').innerHTML = recentPostsHTML;
}

// Posts List
function loadPostsList() {
    const posts = getPosts();
    
    if (posts.length === 0) {
        document.getElementById('posts-list').innerHTML = `
            <p style="text-align: center; color: var(--text-muted); padding: 3rem;">
                Inga projekt än. <a href="#" onclick="showSection('new-post')" style="color: var(--accent-primary);">Skapa ditt första projekt →</a>
            </p>
        `;
        return;
    }

    const postsHTML = `
        <table class="posts-table">
            <thead>
                <tr>
                    <th>Titel</th>
                    <th>Författare</th>
                    <th>Kategori</th>
                    <th>Publicerad</th>
                    <th>Skapad</th>
                    <th>Status</th>
                    <th>Åtgärder</th>
                </tr>
            </thead>
            <tbody>
                ${posts.map(post => `
                    <tr>
                        <td>${post.icon} ${post.title}</td>
                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">${post.author || 'Okänd'}</td>
                        <td>${getCategoryName(post.category)}</td>
                        <td>${formatDate(post.date)}</td>
                        <td style="font-size: 0.85rem; color: var(--text-muted);">${post.createdAt ? formatDateTime(post.createdAt).split(' ')[0] : '-'}</td>
                        <td><span class="badge badge-${post.status === 'published' ? 'published' : 'draft'}">${post.status === 'published' ? 'Publicerad' : 'Utkast'}</span></td>
                        <td style="white-space: nowrap;">
                            <button onclick="editPost(${post.id})" class="btn btn-secondary btn-sm" style="margin-right: 0.5rem;">✏️ Redigera</button>
                            <button onclick="deletePost(${post.id})" class="btn btn-danger btn-sm">🗑️ Ta bort</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    document.getElementById('posts-list').innerHTML = postsHTML;
}

// Post Form
function resetPostForm() {
    editingPostId = null;
    document.getElementById('editor-title').textContent = 'Nytt Projekt';
    document.getElementById('post-form').reset();
    document.getElementById('post-id').value = '';
    document.getElementById('post-author').value = 'Salman Yahya'; // Default author
    currentTags = [];
    renderTags();
    document.getElementById('save-alert').classList.add('hidden');
    
    // Reset metadata display
    document.getElementById('created-date-display').textContent = 'Skapas nu';
    document.getElementById('modified-date-display').textContent = 'Skapas nu';
}

function setupEventListeners() {
    // Post form submit
    document.getElementById('post-form').addEventListener('submit', savePost);

    // Tag input
    const tagInput = document.getElementById('tag-input');
    tagInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const tag = tagInput.value.trim();
            if (tag && !currentTags.includes(tag)) {
                currentTags.push(tag);
                renderTags();
                tagInput.value = '';
            }
        }
    });
}

function setTodayDate() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('post-date').value = today;
}

function renderTags() {
    const container = document.getElementById('tags-container');
    const tagInput = document.getElementById('tag-input');
    
    // Clear existing tags (except input)
    const existingTags = container.querySelectorAll('.tag-item');
    existingTags.forEach(tag => tag.remove());

    // Add new tags
    currentTags.forEach((tag, index) => {
        const tagElement = document.createElement('div');
        tagElement.className = 'tag-item';
        tagElement.innerHTML = `
            ${tag}
            <span class="tag-remove" onclick="removeTag(${index})">×</span>
        `;
        container.insertBefore(tagElement, tagInput);
    });
}

function removeTag(index) {
    currentTags.splice(index, 1);
    renderTags();
}

function savePost(e) {
    e.preventDefault();

    const now = new Date().toISOString();
    const postId = editingPostId || getNextId();

    const post = {
        id: postId,
        title: document.getElementById('post-title').value,
        category: document.getElementById('post-category').value,
        icon: document.getElementById('post-icon').value,
        date: document.getElementById('post-date').value,
        author: document.getElementById('post-author').value,
        company: document.getElementById('post-company').value,
        location: document.getElementById('post-location').value || '',
        readTime: document.getElementById('post-read-time').value || '10 min',
        excerpt: document.getElementById('post-excerpt').value,
        content: document.getElementById('post-content').value,
        tags: [...currentTags],
        featured: document.getElementById('post-featured').checked,
        status: document.getElementById('post-status').value,
        createdAt: editingPostId ? (getPosts().find(p => p.id === editingPostId)?.createdAt || now) : now,
        createdBy: editingPostId ? (getPosts().find(p => p.id === editingPostId)?.createdBy || currentUser) : currentUser,
        modifiedAt: now,
        modifiedBy: currentUser
    };

    let posts = getPosts();
    
    if (editingPostId) {
        // Update existing post
        posts = posts.map(p => p.id === editingPostId ? post : p);
    } else {
        // Add new post
        posts.push(post);
    }

    savePosts(posts);
    
    // Show success message
    const alertDiv = document.getElementById('save-alert');
    alertDiv.textContent = editingPostId ? '✅ Projektet har uppdaterats!' : '✅ Projektet har skapats!';
    alertDiv.classList.remove('hidden');

    setTimeout(() => {
        alertDiv.classList.add('hidden');
        showSection('posts');
    }, 2000);
}

function editPost(id) {
    const posts = getPosts();
    const post = posts.find(p => p.id === id);
    
    if (!post) return;

    editingPostId = id;
    document.getElementById('editor-title').textContent = 'Redigera Projekt';
    document.getElementById('post-id').value = id;
    document.getElementById('post-title').value = post.title;
    document.getElementById('post-category').value = post.category;
    document.getElementById('post-icon').value = post.icon;
    document.getElementById('post-date').value = post.date;
    document.getElementById('post-author').value = post.author || 'Salman Yahya';
    document.getElementById('post-company').value = post.company || '';
    document.getElementById('post-location').value = post.location || '';
    document.getElementById('post-read-time').value = post.readTime;
    document.getElementById('post-excerpt').value = post.excerpt;
    document.getElementById('post-content').value = post.content || '';
    currentTags = [...(post.tags || [])];
    document.getElementById('post-featured').checked = post.featured || false;
    document.getElementById('post-status').value = post.status || 'published';

    // Display metadata
    document.getElementById('created-date-display').textContent = 
        post.createdAt ? formatDateTime(post.createdAt) + ' av ' + (post.createdBy || 'Okänd') : 'Ny';
    document.getElementById('modified-date-display').textContent = 
        post.modifiedAt ? formatDateTime(post.modifiedAt) + ' av ' + (post.modifiedBy || 'Okänd') : 'Aldrig';

    renderTags();
    showSection('new-post');
}

function deletePost(id) {
    if (confirm('Är du säker på att du vill ta bort detta projekt?')) {
        let posts = getPosts();
        posts = posts.filter(p => p.id !== id);
        savePosts(posts);
        loadPostsList();
        loadDashboard();
    }
}

function previewPost() {
    const title = document.getElementById('post-title').value;
    const excerpt = document.getElementById('post-excerpt').value;
    const content = document.getElementById('post-content').value;

    const previewWindow = window.open('', 'Preview', 'width=800,height=600');
    previewWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;600;700&family=Crimson+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
            <style>
                body {
                    font-family: 'Crimson Pro', serif;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 2rem;
                    background: #0f1419;
                    color: #e2e8f0;
                    line-height: 1.8;
                }
                h1 { font-size: 2.5rem; margin-bottom: 1rem; }
                p { font-size: 1.1rem; color: #94a3b8; margin-bottom: 1.5rem; }
            </style>
        </head>
        <body>
            <h1>${title}</h1>
            <p><strong>Beskrivning:</strong> ${excerpt}</p>
            <hr>
            <div>${content}</div>
        </body>
        </html>
    `);
}

// Export/Import
function exportData() {
    const posts = getPosts();
    const dataStr = JSON.stringify(posts, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `projekt-journal-backup-${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(url);
}

function importData(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const posts = JSON.parse(e.target.result);
            if (confirm(`Importera ${posts.length} projekt? Detta kommer ersätta befintliga data.`)) {
                savePosts(posts);
                loadDashboard();
                loadPostsList();
                alert('✅ Import lyckades!');
            }
        } catch (error) {
            alert('❌ Fel vid import: ' + error.message);
        }
    };
    reader.readAsText(file);
}

function clearAllData() {
    if (confirm('⚠️ VARNING: Detta kommer radera ALLA projekt permanent. Fortsätta?')) {
        if (confirm('Är du VERKLIGEN säker? Detta går inte att ångra!')) {
            localStorage.removeItem('journal_posts');
            loadDashboard();
            loadPostsList();
            alert('✅ Alla projekt har raderats.');
        }
    }
}

// Utility Functions
function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('sv-SE', options);
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    const dateOptions = { year: 'numeric', month: 'short', day: 'numeric' };
    const timeOptions = { hour: '2-digit', minute: '2-digit' };
    return date.toLocaleDateString('sv-SE', dateOptions) + ' ' + date.toLocaleTimeString('sv-SE', timeOptions);
}

function getCategoryName(category) {
    const categories = {
        automation: '🤖 Automation',
        software: '💻 Mjukvara',
        'rf-engineering': '📡 RF Engineering',
        robotics: '🦾 Robotik',
        devops: '🔧 DevOps',
        infrastructure: '☁️ Infrastruktur',
        embedded: '🔌 Embedded'
    };
    return categories[category] || category;
}

// Initialize with demo data if empty
function initializeDemoData() {
    const posts = getPosts();
    if (posts.length === 0) {
        const now = new Date().toISOString();
        const demoPost = {
            id: 1,
            title: "Välkommen till Projekt Journal CMS",
            category: "software",
            icon: "🎉",
            date: new Date().toISOString().split('T')[0],
            author: "Salman Yahya",
            company: "Personal Portfolio",
            location: "Göteborg, Sverige",
            readTime: "2 min",
            excerpt: "Detta är ditt första projekt! Använd admin-panelen för att skapa, redigera och hantera dina tekniska projekt och artiklar.",
            content: "<p>Välkommen till din nya bloggplattform!</p><p>Här kan du dokumentera alla dina tekniska projekt, forskningsarbeten och artiklar.</p>",
            tags: ["Demo", "Tutorial", "CMS"],
            featured: true,
            status: "published",
            createdAt: now,
            createdBy: "system",
            modifiedAt: now,
            modifiedBy: "system"
        };
        savePosts([demoPost]);
    }
}

// Call this on first load
initializeDemoData();