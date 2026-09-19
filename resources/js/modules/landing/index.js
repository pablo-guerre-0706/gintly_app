function initImageFallbacks() {
    document.querySelectorAll('[data-image-fallback]').forEach((image) => {
        const showFallback = () => {
            image.hidden = true;
            image.nextElementSibling?.classList.remove('hidden');
        };

        image.addEventListener('error', showFallback, { once: true });

        if (image.complete && image.naturalWidth === 0) {
            showFallback();
        }
    });
}

function initFaq() {
    document.querySelectorAll('[data-faq-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const content = button.nextElementSibling;
            const icon = button.querySelector('svg');
            const isOpen = button.getAttribute('aria-expanded') === 'true';

            button.setAttribute('aria-expanded', String(!isOpen));

            if (content) {
                content.style.maxHeight = isOpen ? '0px' : `${content.scrollHeight}px`;
            }

            if (icon) {
                icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        });
    });
}

function initBillingSwitch() {
    const indicator = document.getElementById('switch-indicator');
    const monthlyButton = document.getElementById('btn-monthly');
    const annualButton = document.getElementById('btn-annual');

    if (!indicator || !monthlyButton || !annualButton) {
        return;
    }

    const priceElements = document.querySelectorAll('.price-main, .price-usd, .price-billing');

    document.querySelectorAll('[data-landing-billing]').forEach((button) => {
        button.addEventListener('click', () => {
            const cycle = button.dataset.landingBilling;
            const isAnnual = cycle === 'annual';

            indicator.style.transform = isAnnual ? 'translateX(100%)' : 'translateX(0%)';
            monthlyButton.classList.toggle('text-white', !isAnnual);
            monthlyButton.classList.toggle('text-[#555555]', isAnnual);
            annualButton.classList.toggle('text-white', isAnnual);
            annualButton.classList.toggle('text-[#555555]', !isAnnual);

            priceElements.forEach((element) => {
                const value = element.dataset[cycle];

                if (value !== undefined) {
                    element.textContent = value;
                }
            });
        });
    });
}

function initPlanButtons() {
    document.querySelectorAll('[data-plan-action]').forEach((button) => {
        button.addEventListener('click', () => {
            button.style.transform = 'scale(0.96)';
            button.style.backgroundColor = '#0f556b';
            button.style.color = '#ffffff';

            window.setTimeout(() => {
                button.style.transform = 'scale(1)';
            }, 150);
        });
    });
}

function initTestimonials() {
    const track = document.getElementById('testimonials-track');
    const container = document.getElementById('testimonials-container');
    const previousButton = document.querySelector('[data-testimonial-prev]');
    const nextButton = document.querySelector('[data-testimonial-next]');

    if (!track || !container || !previousButton || !nextButton || !track.firstElementChild) {
        return;
    }

    let currentIndex = 0;
    let autoPlayInterval = null;

    const visibleCards = () => {
        if (window.matchMedia('(min-width: 1024px)').matches) {
            return 3;
        }

        if (window.matchMedia('(min-width: 768px)').matches) {
            return 2;
        }

        return 1;
    };

    const maxIndex = () => Math.max(0, track.children.length - visibleCards());

    const updateSlider = () => {
        currentIndex = Math.min(currentIndex, maxIndex());
        const cardWidth = track.firstElementChild.offsetWidth + 32;
        track.style.transform = `translateX(-${currentIndex * cardWidth}px)`;
    };

    const next = () => {
        currentIndex = currentIndex < maxIndex() ? currentIndex + 1 : 0;
        updateSlider();
    };

    const previous = () => {
        currentIndex = currentIndex > 0 ? currentIndex - 1 : maxIndex();
        updateSlider();
    };

    const stopAutoPlay = () => {
        if (autoPlayInterval !== null) {
            window.clearInterval(autoPlayInterval);
            autoPlayInterval = null;
        }
    };

    const startAutoPlay = () => {
        stopAutoPlay();
        autoPlayInterval = window.setInterval(next, 4000);
    };

    previousButton.addEventListener('click', previous);
    nextButton.addEventListener('click', next);
    container.addEventListener('mouseenter', stopAutoPlay);
    container.addEventListener('mouseleave', startAutoPlay);
    window.addEventListener('resize', updateSlider);
    startAutoPlay();
}

export default function initLanding() {
    initImageFallbacks();
    initFaq();
    initBillingSwitch();
    initPlanButtons();
    initTestimonials();
}
