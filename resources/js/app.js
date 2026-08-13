import Alpine from 'alpinejs';

// Theme: honour a stored choice, else the OS preference. Applied in the <head>
// inline script too, to avoid a flash before this module loads.
window.theme = {
    get current() {
        return localStorage.getItem('theme')
            ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    },
    apply(value) {
        document.documentElement.classList.toggle('dark', value === 'dark');
        localStorage.setItem('theme', value);
    },
    toggle() {
        this.apply(this.current === 'dark' ? 'light' : 'dark');
    },
};

window.Alpine = Alpine;
Alpine.start();
