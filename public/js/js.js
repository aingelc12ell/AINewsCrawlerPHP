
// Theme toggle functionality
const themeToggle = document.getElementById('themeToggle');

// Check for saved theme preference or respect OS preference
const savedTheme = localStorage.getItem('theme');
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

if (savedTheme) {
    document.documentElement.setAttribute('data-theme', savedTheme);
} else if (prefersDark) {
    document.documentElement.setAttribute('data-theme', 'dark');
}

// Update button text based on current theme
function updateButtonText() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    themeToggle.textContent = currentTheme === 'dark' ? 'Light Theme' : 'Dark Theme';
}

updateButtonText();

// Toggle theme on button click
themeToggle.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateButtonText();
});

// View toggle functionality
function setupViewToggle() {
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    const gridContainer = document.getElementById('gridView');
    const listContainer = document.getElementById('listView');

    if (!gridBtn || !listBtn || !gridContainer || !listContainer) {
        return;
    }

    // Check for saved view preference
    const savedView = localStorage.getItem('viewMode') || 'grid';

    if (savedView === 'list') {
        gridContainer.style.display = 'none';
        listContainer.style.display = 'block';
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
    } else {
        gridContainer.style.display = 'grid';
        listContainer.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    }

    // Grid view button
    gridBtn.addEventListener('click', () => {
        gridContainer.style.display = 'grid';
        listContainer.style.display = 'none';
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
        localStorage.setItem('viewMode', 'grid');
    });

    // List view button
    listBtn.addEventListener('click', () => {
        gridContainer.style.display = 'none';
        listContainer.style.display = 'block';
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
        localStorage.setItem('viewMode', 'list');
    });
}

// Per page change handler
function setupPerPageHandler() {
    const perPageSelect = document.getElementById('perPageSelect');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', this.value);
            currentUrl.searchParams.set('page', '1'); // Reset to page 1
            window.location.href = currentUrl.toString();
        });
    }
}

// Add background image to cards with data-background attribute
function setupCardBackgrounds() {
    const cards = document.querySelectorAll('.article-card.has-image');
    cards.forEach(card => {
        const imageUrl = card.getAttribute('style')?.match(/url\(['"]?([^'"]*)['"]?\)/)?.[1];
        if (imageUrl) {
            // Create a pseudo-element for the background image
            const style = document.createElement('style');
            style.textContent = `
                    .article-card.has-image[data-url="${CSS.escape(imageUrl)}"]::before {
                        background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('${imageUrl}');
                        background-size: cover;
                        background-position: center;
                    }
                `;
            document.head.appendChild(style);

            // Add data attribute for CSS targeting
            card.setAttribute('data-url', imageUrl);
        }
    });
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupViewToggle();
    setupPerPageHandler();
    setupCardBackgrounds();
    // Focus search input if search query exists
    const searchInput = document.querySelector('.search-input');
    if (searchInput && window.location.search.includes('q=')) {
        searchInput.focus();
    }
});