const storage = {
    get(key) {
        try { return window.localStorage.getItem(key); } catch (error) { return null; }
    },
    set(key, value) {
        try { window.localStorage.setItem(key, value); } catch (error) {
            // Preferences still work for this page when storage is unavailable.
        }
    }
};

const themeToggle = document.getElementById('themeToggle');
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

function setTheme(theme, persist = false) {
    document.documentElement.setAttribute('data-theme', theme);
    if (themeToggle) {
        const isDark = theme === 'dark';
        themeToggle.textContent = isDark ? 'Use light theme' : 'Use dark theme';
        themeToggle.setAttribute('aria-pressed', String(isDark));
    }
    if (persist) storage.set('theme', theme);
}

setTheme(storage.get('theme') || (systemTheme.matches ? 'dark' : 'light'));
themeToggle?.addEventListener('click', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    setTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
});
systemTheme.addEventListener?.('change', event => {
    if (!storage.get('theme')) setTheme(event.matches ? 'dark' : 'light');
});

function setupViewToggle() {
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    const gridContainer = document.getElementById('gridView');
    const listContainer = document.getElementById('listView');
    if (!gridBtn || !listBtn || !gridContainer || !listContainer) return;

    const setView = view => {
        const showList = view === 'list';
        gridContainer.hidden = showList;
        listContainer.hidden = !showList;
        gridBtn.classList.toggle('active', !showList);
        listBtn.classList.toggle('active', showList);
        gridBtn.setAttribute('aria-pressed', String(!showList));
        listBtn.setAttribute('aria-pressed', String(showList));
    };

    setView(storage.get('viewMode') === 'list' ? 'list' : 'grid');
    gridBtn.addEventListener('click', () => { setView('grid'); storage.set('viewMode', 'grid'); });
    listBtn.addEventListener('click', () => { setView('list'); storage.set('viewMode', 'list'); });
}

function setupPerPageHandler() {
    const select = document.getElementById('perPageSelect');
    if (!select) return;
    select.addEventListener('change', function () {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', this.value);
        currentUrl.searchParams.set('page', '1');
        window.location.assign(currentUrl);
    });
}

let cardImageObserver;

function loadCardBackgrounds() {
    const cards = document.querySelectorAll('.article-card[data-image-url]:not(.image-loaded)');
    const loadImage = card => {
        try {
            const imageUrl = new URL(card.dataset.imageUrl);
            if (!['http:', 'https:'].includes(imageUrl.protocol)) return;
            card.style.setProperty('--article-image', `url(${JSON.stringify(imageUrl.href)})`);
            card.classList.add('image-loaded');
        } catch (error) {
            // Invalid legacy image URLs use the standard card surface.
        }
    };

    if (!('IntersectionObserver' in window)) {
        cards.forEach(loadImage);
        return;
    }
    cardImageObserver ||= new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                loadImage(entry.target);
                cardImageObserver.unobserve(entry.target);
            }
        });
    }, {rootMargin: '300px 0px'});
    cards.forEach(card => cardImageObserver.observe(card));
}

function setupCardBackgroundToggle() {
    const toggle = document.getElementById('cardImageToggle');
    if (!toggle) return;

    const setCardImages = (enabled, persist = false) => {
        document.documentElement.setAttribute('data-card-images', enabled ? 'on' : 'off');
        toggle.textContent = `Card images: ${enabled ? 'on' : 'off'}`;
        toggle.setAttribute('aria-pressed', String(enabled));
        if (enabled) loadCardBackgrounds();
        if (persist) storage.set('cardImages', enabled ? 'on' : 'off');
    };

    setCardImages(storage.get('cardImages') !== 'off');
    toggle.addEventListener('click', () => {
        setCardImages(document.documentElement.getAttribute('data-card-images') !== 'on', true);
    });
}

function splitSpeechText(text, maximumLength = 220) {
    const sentences = text.match(/[^.!?]+[.!?]+|[^.!?]+$/g) || [text];
    const chunks = [];
    let current = '';
    const addWords = sentence => {
        sentence.trim().split(/\s+/).forEach(word => {
            if (`${current} ${word}`.trim().length > maximumLength && current) {
                chunks.push(current.trim());
                current = '';
            }
            current = `${current} ${word}`.trim();
        });
    };

    sentences.forEach(sentence => {
        const cleanSentence = sentence.trim();
        if (!cleanSentence) return;
        if (cleanSentence.length > maximumLength) {
            if (current) { chunks.push(current.trim()); current = ''; }
            addWords(cleanSentence);
        } else if (`${current} ${cleanSentence}`.trim().length > maximumLength) {
            chunks.push(current.trim());
            current = cleanSentence;
        } else {
            current = `${current} ${cleanSentence}`.trim();
        }
    });
    if (current) chunks.push(current.trim());
    return chunks;
}

function setupTextToSpeech() {
    const reader = document.querySelector('[data-article-reader]');
    const playBtn = document.getElementById('speechPlayBtn');
    const pauseBtn = document.getElementById('speechPauseBtn');
    const stopBtn = document.getElementById('speechStopBtn');
    const rateSelect = document.getElementById('speechRate');
    const status = document.getElementById('speechStatus');
    if (!reader || !playBtn || !pauseBtn || !stopBtn || !rateSelect || !status) return;

    if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) {
        [playBtn, pauseBtn, stopBtn, rateSelect].forEach(control => { control.disabled = true; });
        status.textContent = 'Text-to-speech is not supported by this browser.';
        return;
    }

    const synth = window.speechSynthesis;
    const sourceParts = ['[data-reader-title]', '[data-reader-summary]', '[data-reader-content]']
        .map(selector => reader.querySelector(selector)?.textContent?.replace(/\s+/g, ' ').trim())
        .filter(Boolean);
    const chunks = splitSpeechText(sourceParts.join('. '));
    let chunkIndex = 0;
    let runId = 0;
    let state = 'idle';

    const renderState = (nextState, message) => {
        state = nextState;
        playBtn.disabled = nextState !== 'idle';
        pauseBtn.disabled = nextState === 'idle';
        stopBtn.disabled = nextState === 'idle';
        pauseBtn.textContent = nextState === 'paused' ? 'Resume' : 'Pause';
        status.textContent = message;
    };

    const speakNext = currentRun => {
        if (currentRun !== runId || chunkIndex >= chunks.length) {
            if (currentRun === runId) renderState('idle', 'Finished listening.');
            return;
        }
        const utterance = new SpeechSynthesisUtterance(chunks[chunkIndex]);
        utterance.rate = Number(rateSelect.value);
        utterance.onend = () => {
            if (currentRun !== runId) return;
            chunkIndex += 1;
            speakNext(currentRun);
        };
        utterance.onerror = event => {
            if (currentRun === runId && !['canceled', 'interrupted'].includes(event.error)) {
                runId += 1;
                renderState('idle', 'Playback stopped because the browser voice could not continue.');
            }
        };
        synth.speak(utterance);
    };

    const stopSpeech = () => {
        runId += 1;
        chunkIndex = 0;
        synth.cancel();
        renderState('idle', 'Playback stopped.');
    };

    playBtn.addEventListener('click', () => {
        if (!chunks.length) {
            status.textContent = 'There is no article text to read.';
            return;
        }
        runId += 1;
        chunkIndex = 0;
        synth.cancel();
        renderState('speaking', 'Reading the article aloud.');
        speakNext(runId);
    });
    pauseBtn.addEventListener('click', () => {
        if (state === 'paused') {
            synth.resume();
            renderState('speaking', 'Reading the article aloud.');
        } else if (state === 'speaking') {
            synth.pause();
            renderState('paused', 'Playback paused.');
        }
    });
    stopBtn.addEventListener('click', stopSpeech);
    window.addEventListener('pagehide', () => { runId += 1; synth.cancel(); });
}

document.addEventListener('DOMContentLoaded', () => {
    setupViewToggle();
    setupPerPageHandler();
    setupCardBackgroundToggle();
    setupTextToSpeech();
    const searchInput = document.querySelector('.search-input');
    if (searchInput && new URLSearchParams(window.location.search).has('q')) searchInput.focus();
});
