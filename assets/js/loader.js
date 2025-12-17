// Modern Animated Loader for Car Rental System
class ModernLoader {
    constructor() {
        this.loader = null;
        this.progress = 0;
        this.init();
    }

    init() {
        this.createLoaderHTML();
        this.animateLoader();
    }

    createLoaderHTML() {
        const loaderHTML = `
            <div id="modernLoader" class="modern-loader">
                <div class="loader-backdrop"></div>
                <div class="loader-content">
                    <div class="loader-logo">
                        <div class="car-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="loader-text">
                            <h2>ELITE MOTORS</h2>
                            <p>Preparing your premium drive...</p>
                        </div>
                    </div>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('afterbegin', loaderHTML);
        this.loader = document.getElementById('modernLoader');
    }

    animateLoader() {
        const progressFill = document.querySelector('.progress-fill');
        const progressText = document.querySelector('.progress-text');
        
        const interval = setInterval(() => {
            this.progress += Math.random() * 15;
            if (this.progress >= 100) {
                this.progress = 100;
                clearInterval(interval);
                setTimeout(() => this.hideLoader(), 500);
            }
            
            progressFill.style.width = this.progress + '%';
            progressText.textContent = Math.round(this.progress) + '%';
        }, 100);
    }

    hideLoader() {
        this.loader.style.opacity = '0';
        this.loader.style.transform = 'scale(0.8)';
        
        setTimeout(() => {
            this.loader.remove();
            document.body.classList.add('loaded');
        }, 300);
    }
}

// Initialize loader when page loads
document.addEventListener('DOMContentLoaded', () => {
    new ModernLoader();
});
