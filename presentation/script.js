/**
 * SkyLounge Presentation - Simple Version
 */

document.addEventListener('DOMContentLoaded', function() {
    var slides = document.querySelectorAll('.slide');
    var totalSlides = slides.length;
    var currentSlide = 1;

    // Create nav dots
    var navDots = document.getElementById('navDots');
    for (var i = 1; i <= totalSlides; i++) {
        var dot = document.createElement('button');
        dot.className = 'nav-dot' + (i === 1 ? ' active' : '');
        dot.dataset.slide = i;
        (function(num) {
            dot.onclick = function() { goToSlide(num); };
        })(i);
        navDots.appendChild(dot);
    }

    // Update progress
    function updateProgress() {
        document.getElementById('progressBar').style.width = ((currentSlide / totalSlides) * 100) + '%';
        document.getElementById('progressText').textContent = currentSlide + ' / ' + totalSlides;
        
        var dots = document.querySelectorAll('.nav-dot');
        for (var i = 0; i < dots.length; i++) {
            if (i + 1 === currentSlide) {
                dots[i].classList.add('active');
            } else {
                dots[i].classList.remove('active');
            }
        }
    }

    // Go to slide
    function goToSlide(n) {
        if (n < 1 || n > totalSlides || n === currentSlide) return;

        slides[currentSlide - 1].classList.remove('active');
        slides[n - 1].classList.add('active');
        currentSlide = n;
        updateProgress();
    }

    function nextSlide() {
        if (currentSlide < totalSlides) goToSlide(currentSlide + 1);
    }

    function prevSlide() {
        if (currentSlide > 1) goToSlide(currentSlide - 1);
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === ' ' || e.key === 'PageDown') {
            e.preventDefault();
            nextSlide();
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp' || e.key === 'PageUp') {
            e.preventDefault();
            prevSlide();
        } else if (e.key === 'Home') {
            e.preventDefault();
            goToSlide(1);
        } else if (e.key === 'End') {
            e.preventDefault();
            goToSlide(totalSlides);
        } else if (e.key === 'f' || e.key === 'F') {
            e.preventDefault();
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        } else if (e.key === '?') {
            e.preventDefault();
            document.getElementById('shortcutsHelp').classList.toggle('visible');
        } else if (e.key === 'Escape') {
            document.getElementById('shortcutsHelp').classList.remove('visible');
        } else if (e.key >= '1' && e.key <= '9') {
            e.preventDefault();
            var num = parseInt(e.key);
            if (num <= totalSlides) goToSlide(num);
        }
    });

    // Touch support
    var touchStartX = 0;
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    document.addEventListener('touchend', function(e) {
        var diff = touchStartX - e.changedTouches[0].screenX;
        if (Math.abs(diff) > 50) {
            if (diff > 0) nextSlide();
            else prevSlide();
        }
    }, { passive: true });

    // Click navigation
    document.getElementById('slidesContainer').addEventListener('click', function(e) {
        if (e.target.closest('a, button, input')) return;
        var x = e.clientX / window.innerWidth;
        if (x > 0.8) nextSlide();
        else if (x < 0.2) prevSlide();
    });

    // Close help on click
    document.getElementById('shortcutsHelp').addEventListener('click', function(e) {
        if (e.target.id === 'shortcutsHelp') {
            e.target.classList.remove('visible');
        }
    });

    updateProgress();
    console.log('Presentation ready: ' + totalSlides + ' slides');
});