class ThemeSwitcher {
  constructor() {
    this.themes = {
      'dark-brown-gold': {
        name: 'Dark Brown Gold',
        primary: '#2C1810',
        secondary: '#5D4037',
        accent: '#D4AF37',
        text: '#E0E0E0',
        bg: '#121212'
      }
    };
    this.init();
  }
  
  init() {
    this.loadTheme();
    this.renderSwitcher();
    this.setupEventListeners();
  }
  
  loadTheme() {
    const savedTheme = localStorage.getItem('selectedTheme') || 'dark-brown-gold';
    this.applyTheme(savedTheme);
  }
  
  applyTheme(themeName) {
    const theme = this.themes[themeName];
    if (!theme) return;
    
    const root = document.documentElement;
    root.style.setProperty('--primary-900', theme.primary);
    root.style.setProperty('--primary-700', this.darkenColor(theme.primary, 20));
    root.style.setProperty('--primary-500', theme.secondary);
    root.style.setProperty('--gold-900', theme.accent);
    root.style.setProperty('--text-primary', theme.text);
    root.style.setProperty('--bg-primary', theme.bg);
  }
  
  darkenColor(color, percent) {
    const num = parseInt(color.replace('#', ''), 16);
    const amt = Math.round(2.55 * percent);
    const R = (num >> 16) - amt;
    const G = (num >> 8 & 0x00FF) - amt;
    const B = (num & 0x0000FF) - amt;
    return `#${(0x1000000 + (R < 0 ? 0 : R) * 0x10000 + 
             (G < 0 ? 0 : G) * 0x100 + 
             (B < 0 ? 0 : B)).toString(16).slice(1)}`;
  }
  
  renderSwitcher() {
    const container = document.createElement('div');
    container.className = 'theme-switcher';
    container.innerHTML = `
      <button class="theme-toggle" aria-label="Change theme">
        <i class="fas fa-palette"></i>
      </button>
    `;
    document.body.appendChild(container);
  }
  
  setupEventListeners() {
    document.querySelector('.theme-toggle').addEventListener('click', () => {
      const currentTheme = localStorage.getItem('selectedTheme') || 'dark-brown-gold';
      const newTheme = currentTheme === 'dark-brown-gold' ? 'dark-brown-gold' : 'dark-brown-gold';
      this.applyTheme(newTheme);
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  window.themeSwitcher = new ThemeSwitcher();
});
