document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.querySelector('.testimonials_carousel');
    const contents = document.querySelectorAll('.testimonials_content');
    const prevButton = document.querySelector('.listing-carousel-button-prev');
    const nextButton = document.querySelector('.listing-carousel-button-next');
    
    let currentIndex = 0;
    const totalTestimonials = contents.length;
    
    function updateCarousel() {
        contents.forEach((content, index) => {
            content.classList.remove('active');
            if (index === currentIndex) {
                content.classList.add('active');
            }
        });
    }
    
    function showNext() {
        currentIndex = (currentIndex + 1) % totalTestimonials;
        updateCarousel();
    }
    
    function showPrev() {
        currentIndex = (currentIndex - 1 + totalTestimonials) % totalTestimonials;
        updateCarousel();
    }
    
    // Add click event listeners to buttons
    nextButton.addEventListener('click', showNext);
    prevButton.addEventListener('click', showPrev);
    
    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowRight') {
            showNext();
        } else if (e.key === 'ArrowLeft') {
            showPrev();
        }
    });
    
    // Initialize the carousel
    updateCarousel();
}); 